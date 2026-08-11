<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Api\ChatController;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Coupon;
use App\Models\HelpArticle;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\Series;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ChatSessionLifecycleService;
use App\Services\GeminiChatService;
use App\Services\RagSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ChatSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_customer_endpoints_require_an_authenticated_account(): void
    {
        $this->getJson('/api/chat/conversations')->assertUnauthorized();
        $this->postJson('/api/chat/sessions', ['target_type' => 'platform'])->assertUnauthorized();
    }

    public function test_gemini_uses_flash_lite_fallback_only_after_primary_rate_limit(): void
    {
        config()->set('services.gemini.enabled', true);
        config()->set('services.gemini.api_key', 'test-key-not-real');
        config()->set('services.gemini.model', 'gemini-3.5-flash-lite');
        config()->set('services.gemini.fallback_model', 'gemini-3.1-flash-lite');
        config()->set('services.gemini.allowed_models', ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite']);
        config()->set('services.gemini.max_attempts', 2);
        Http::preventStrayRequests();
        Http::fake([
            '*gemini-3.5-flash-lite*' => Http::response(['error' => ['message' => 'quota']], 429),
            '*gemini-3.1-flash-lite*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Phản hồi từ model dự phòng.']]]]],
            ]),
        ]);

        $session = $this->providerSession();
        $service = app(GeminiChatService::class);
        $method = new \ReflectionMethod($service, 'providerReply');
        $reply = $method->invoke($service, $session, 'Cần hỗ trợ', $this->providerKnowledge('Nguồn kiểm thử', $session));

        $this->assertSame('Phản hồi từ model dự phòng.', $reply);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-3.5-flash-lite')
            && ! str_contains($request->url(), 'test-key-not-real'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-3.1-flash-lite'));
    }

    public function test_generate_reply_reports_the_actual_allowlisted_fallback_model_without_exporting_history(): void
    {
        $this->configureGemini(['max_attempts' => 2]);
        $session = $this->providerSession();
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'customer',
            'message' => 'history-secret-marker',
        ]);
        HelpArticle::create([
            'category_name' => 'Support',
            'title' => 'Chính sách giao hàng',
            'content' => 'Chi tiết chính sách giao hàng.',
            'status' => 'published',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            '*gemini-primary*' => Http::response([], 429),
            '*gemini-fallback*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'fallback integration reply']]]]],
            ]),
        ]);

        $result = app(GeminiChatService::class)->generateReply($session, 'Chính sách giao hàng?');

        $this->assertSame('gemini', $result['metadata']['delivery']);
        $this->assertSame('gemini', $result['metadata']['engine']['provider']);
        $this->assertSame('gemini-fallback', $result['metadata']['engine']['model']);
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $prompt = data_get($request->data(), 'contents.0.parts.0.text', '');

            return str_contains($request->url(), 'gemini-fallback')
                && str_contains($prompt, 'Chính sách giao hàng?')
                && ! str_contains($prompt, 'history-secret-marker');
        });
    }

    public function test_customer_can_chat_with_grounded_local_fallback_when_external_ai_is_disabled(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);

        $created = $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'platform'])
            ->assertOk()
            ->assertJsonPath('session.responder_mode', 'ai')
            ->assertJsonPath('session.status', 'open');

        $sessionId = $created->json('session.id');

        $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/messages", ['message' => 'Tôi cần thông tin chưa có trong hệ thống'])
            ->assertOk()
            ->assertJsonPath('session.messages.2.sender_type', 'ai')
            ->assertJsonPath('session.messages.2.metadata.delivery', 'local_grounded');
    }

    public function test_customer_cannot_read_or_write_another_customers_session(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $session = ChatSession::create([
            'user_id' => $owner->id,
            'target_type' => 'platform',
            'responder_mode' => 'ai',
            'status' => 'open',
        ]);

        $this->actingAs($other)->getJson("/api/chat/sessions/{$session->id}")->assertForbidden();
        $this->actingAs($other)->postJson("/api/chat/sessions/{$session->id}/messages", ['message' => 'Truy cập sai'])->assertForbidden();
        $this->actingAs($other)->postJson("/api/chat/sessions/{$session->id}/request-human")->assertForbidden();
    }

    public function test_chat_images_are_private_and_available_only_inside_the_authorized_conversation(): void
    {
        Storage::fake('local');
        config()->set('services.gemini.enabled', false);
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);

        $sessionId = $this->actingAs($owner)->postJson('/api/chat/sessions', ['target_type' => 'platform'])
            ->assertOk()
            ->json('session.id');

        $response = $this->actingAs($owner)->post("/api/chat/sessions/{$sessionId}/messages", [
            'message' => 'Đây là ảnh cần hỗ trợ',
            'image' => UploadedFile::fake()->image('hoa-don.png', 640, 480),
        ])->assertOk();

        $customerMessage = collect($response->json('session.messages'))->firstWhere('sender_type', 'customer');
        $this->assertNotNull($customerMessage);
        $storedName = $customerMessage['metadata']['attachment']['stored_name'];
        Storage::disk('local')->assertExists("chat-attachments/{$sessionId}/{$storedName}");

        $url = "/api/chat/sessions/{$sessionId}/messages/{$customerMessage['id']}/attachment";
        $this->actingAs($owner, 'sanctum')->get($url)->assertOk();
        $this->actingAs($other, 'sanctum')->get($url)->assertForbidden();
    }

    public function test_assigned_vendor_can_send_an_image_but_another_vendor_cannot_open_it(): void
    {
        Storage::fake('local');
        [$vendorUser, $vendor] = $this->vendor('image-owner');
        [$otherVendorUser] = $this->vendor('image-other');
        $session = ChatSession::create([
            'vendor_id' => $vendor->id,
            'target_type' => 'vendor',
            'assigned_user_id' => $vendorUser->id,
            'responder_mode' => 'human',
            'status' => 'assigned',
        ]);

        $response = $this->actingAs($vendorUser)->post("/api/chat/vendor/sessions/{$session->id}/reply", [
            'image' => UploadedFile::fake()->image('minh-hoa.webp', 320, 240),
        ])->assertOk();

        $message = collect($response->json('session.messages'))->firstWhere('sender_type', 'vendor');
        $url = "/api/chat/sessions/{$session->id}/messages/{$message['id']}/attachment";
        $this->actingAs($vendorUser, 'sanctum')->get($url)->assertOk();
        $this->actingAs($otherVendorUser, 'sanctum')->get($url)->assertForbidden();
    }

    public function test_customer_can_rate_only_ai_messages_in_their_own_session(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $session = ChatSession::create([
            'user_id' => $owner->id,
            'target_type' => 'platform',
            'responder_mode' => 'ai',
            'status' => 'open',
        ]);
        $aiMessage = ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'ai',
            'message' => 'Câu trả lời có căn cứ.',
        ]);
        $customerMessage = ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'customer',
            'sender_id' => $owner->id,
            'message' => 'Câu hỏi.',
        ]);

        $this->actingAs($other, 'sanctum')->postJson("/api/chat/sessions/{$session->id}/messages/{$aiMessage->id}/feedback", ['feedback' => 'helpful'])
            ->assertForbidden();
        $this->actingAs($owner, 'sanctum')->postJson("/api/chat/sessions/{$session->id}/messages/{$customerMessage->id}/feedback", ['feedback' => 'helpful'])
            ->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->postJson("/api/chat/sessions/{$session->id}/messages/{$aiMessage->id}/feedback", ['feedback' => 'helpful'])
            ->assertOk()
            ->assertJsonPath('message.feedback', 'helpful');
    }

    public function test_vendor_scope_and_atomic_takeover_are_enforced(): void
    {
        [$vendorUserA, $vendorA] = $this->vendor('alpha');
        [$vendorUserB] = $this->vendor('beta');

        $session = ChatSession::create([
            'vendor_id' => $vendorA->id,
            'target_type' => 'vendor',
            'responder_mode' => 'human',
            'status' => 'queued',
        ]);

        $this->actingAs($vendorUserB)->getJson("/api/chat/vendor/sessions/{$session->id}")->assertForbidden();
        $this->actingAs($vendorUserB)->postJson("/api/chat/vendor/sessions/{$session->id}/takeover")->assertForbidden();

        $this->actingAs($vendorUserA)->postJson("/api/chat/vendor/sessions/{$session->id}/takeover")
            ->assertOk()
            ->assertJsonPath('session.status', 'assigned')
            ->assertJsonPath('session.assigned_user.id', $vendorUserA->id);

        $this->actingAs($vendorUserB)->postJson("/api/chat/vendor/sessions/{$session->id}/reply", ['message' => 'Không thuộc shop'])->assertForbidden();
        $this->actingAs($vendorUserA)->postJson("/api/chat/vendor/sessions/{$session->id}/reply", ['message' => 'Shop đang hỗ trợ bạn'])
            ->assertOk()
            ->assertJsonPath('session.status', 'waiting_customer');
    }

    public function test_admin_cannot_take_over_vendor_conversation_and_customer_cannot_use_staff_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        [, $vendor] = $this->vendor('shop');
        $session = ChatSession::create([
            'vendor_id' => $vendor->id,
            'target_type' => 'vendor',
            'responder_mode' => 'human',
            'status' => 'queued',
        ]);

        $this->actingAs($admin)->postJson("/api/chat/admin/sessions/{$session->id}/takeover")->assertForbidden();
        $this->actingAs($customer)->getJson('/api/chat/admin/sessions')->assertForbidden();
        $this->actingAs($customer)->getJson('/api/chat/vendor/sessions')->assertForbidden();
    }

    public function test_vendor_handoff_requires_an_explicit_active_vendor(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $sessionId = $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'platform'])->json('session.id');

        $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/request-human", ['target_type' => 'vendor'])
            ->assertUnprocessable();

        $this->assertNull(ChatSession::findOrFail($sessionId)->vendor_id);
    }

    public function test_platform_and_each_vendor_keep_one_separate_persistent_conversation(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        [, $vendorA] = $this->vendor('persistent-a');
        [, $vendorB] = $this->vendor('persistent-b');

        $platformId = $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'platform'])
            ->assertOk()
            ->json('session.id');
        $vendorAId = $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'vendor', 'vendor_id' => $vendorA->id])
            ->assertOk()
            ->json('session.id');
        $vendorAIdAgain = $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'vendor', 'vendor_id' => $vendorA->id])
            ->assertOk()
            ->json('session.id');
        $vendorBId = $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'vendor', 'vendor_id' => $vendorB->id])
            ->assertOk()
            ->json('session.id');

        $this->assertSame($vendorAId, $vendorAIdAgain);
        $this->assertNotSame($platformId, $vendorAId);
        $this->assertNotSame($vendorAId, $vendorBId);

        $this->actingAs($customer)->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonCount(3, 'conversations');
    }

    public function test_ai_answers_vendor_conversation_until_vendor_takes_over(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);
        [$vendorUser, $vendor] = $this->vendor('handoff');
        $sessionId = $this->actingAs($customer)->postJson('/api/chat/sessions', [
            'target_type' => 'vendor',
            'vendor_id' => $vendor->id,
        ])->json('session.id');

        $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/messages", ['message' => 'Shop có sách gì?'])
            ->assertOk();
        $this->assertSame(2, ChatMessage::where('chat_session_id', $sessionId)->where('sender_type', 'ai')->count());

        $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/request-human", [
            'target_type' => 'vendor',
            'vendor_id' => $vendor->id,
        ])->assertOk();
        $this->actingAs($vendorUser, 'sanctum')->postJson("/api/chat/vendor/sessions/{$sessionId}/takeover")
            ->assertOk()
            ->assertJsonPath('session.responder_mode', 'human');
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/messages", ['message' => 'Nhân viên tư vấn giúp tôi'])
            ->assertOk();

        $this->assertSame(2, ChatMessage::where('chat_session_id', $sessionId)->where('sender_type', 'ai')->count());
    }

    public function test_resolved_conversation_reopens_with_ai_without_losing_history(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $sessionId = $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'platform'])->json('session.id');

        $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/request-human")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/chat/admin/sessions/{$sessionId}/takeover")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/chat/admin/sessions/{$sessionId}/close", ['resolution' => 'Đã xử lý'])->assertOk();
        $messageCount = ChatMessage::where('chat_session_id', $sessionId)->count();

        $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'platform'])
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId)
            ->assertJsonPath('session.status', 'open')
            ->assertJsonPath('session.responder_mode', 'ai');

        $this->assertGreaterThan($messageCount, ChatMessage::where('chat_session_id', $sessionId)->count());
    }

    public function test_vendor_book_context_and_catalog_counts_never_leak_other_shops_or_partner_faq(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);
        [, $vendorA] = $this->vendor('catalog-a');
        [, $vendorB] = $this->vendor('catalog-b');
        $category = Category::create(['name' => 'Light novel', 'slug' => 'light-novel']);
        $series = Series::create(['title' => 'Biệt đội 86']);
        $bookA1 = $this->publishedBook($vendorA, $category, ['series_id' => $series->id, 'title' => '86 - Tập 1', 'slug' => '86-tap-1']);
        $this->publishedBook($vendorA, $category, ['series_id' => $series->id, 'title' => '86 - Tập 2', 'slug' => '86-tap-2']);
        $this->publishedBook($vendorB, $category, ['title' => 'Sách Shop Khác', 'slug' => 'sach-shop-khac']);
        HelpArticle::create([
            'category_name' => 'Đối tác & Tác giả',
            'title' => 'Chính sách nhuận bút dành cho Tác giả tự xuất bản',
            'content' => 'Nội dung đối tác không được dùng để trả lời câu hỏi sách.',
            'status' => 'published',
        ]);

        $sessionId = $this->actingAs($customer)->postJson('/api/chat/sessions', [
            'target_type' => 'vendor',
            'vendor_id' => $vendorA->id,
        ])->json('session.id');
        $response = $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/messages", [
            'message' => 'Sách này hiện đang có bao nhiêu tập ạ?',
            'context_book_id' => $bookA1->id,
        ])->assertOk();
        $reply = collect($response->json('session.messages'))->last();

        $this->assertStringContainsString('2 đầu sách', $reply['message']);
        $this->assertStringContainsString('86 - Tập 1', $reply['message']);
        $this->assertStringNotContainsString('nhuận bút', $reply['message']);
        $this->assertStringNotContainsString('Sách Shop Khác', json_encode($reply, JSON_UNESCAPED_UNICODE));

        $countReply = $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/messages", [
            'message' => 'Gian hàng có bao nhiêu sách?',
        ])->assertOk()->json('session.messages');
        $this->assertStringContainsString('2 đầu sách', collect($countReply)->last()['message']);
    }

    public function test_platform_chat_summarizes_the_full_catalog(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);
        [, $vendorA] = $this->vendor('system-a');
        [, $vendorB] = $this->vendor('system-b');
        $category = Category::create(['name' => 'Tổng hợp', 'slug' => 'tong-hop']);
        $this->publishedBook($vendorA, $category, ['title' => 'Sách A', 'slug' => 'sach-a']);
        $this->publishedBook($vendorB, $category, ['title' => 'Sách B', 'slug' => 'sach-b']);

        $sessionId = $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'platform'])->json('session.id');
        $messages = $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/messages", [
            'message' => 'Hệ thống có bao nhiêu sách?',
        ])->assertOk()->json('session.messages');

        $reply = collect($messages)->last()['message'];
        $this->assertStringContainsString('Danh mục toàn hệ thống KomiBook', $reply);
        $this->assertStringContainsString('2 đầu sách', $reply);
    }

    public function test_customer_reply_after_thirty_minutes_keeps_human_assignment(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $sessionId = $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'platform'])->json('session.id');
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$sessionId}/request-human")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/chat/admin/sessions/{$sessionId}/takeover")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/chat/admin/sessions/{$sessionId}/reply", ['message' => 'Bạn cần thêm gì không?'])
            ->assertOk()
            ->assertJsonPath('session.status', 'waiting_customer');

        $this->travel(31)->minutes();
        $response = $this->actingAs($customer, 'sanctum')->postJson("/api/chat/sessions/{$sessionId}/messages", ['message' => 'Tôi cần hỏi tiếp'])
            ->assertOk()
            ->assertJsonPath('session.status', 'assigned')
            ->assertJsonPath('session.responder_mode', 'human')
            ->assertJsonPath('session.assigned_user.id', $admin->id);

        $messages = collect($response->json('session.messages'));
        $this->assertFalse($messages->contains(fn (array $message) => data_get($message, 'metadata.event') === 'ai_auto_resumed'));
        $this->assertTrue($messages->contains(fn (array $message) => $message['sender_type'] === 'customer'));
    }

    public function test_scheduler_no_longer_resumes_idle_human_conversations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $vendor] = $this->vendor('idle-scheduler');
        $sessions = collect([
            ChatSession::create([
                'user_id' => User::factory()->create(['role' => 'customer'])->id,
                'assigned_user_id' => $admin->id,
                'target_type' => 'platform',
                'responder_mode' => 'human',
                'status' => 'waiting_customer',
                'last_message_at' => now()->subMinutes(31),
            ]),
            ChatSession::create([
                'user_id' => User::factory()->create(['role' => 'customer'])->id,
                'vendor_id' => $vendor->id,
                'assigned_user_id' => $vendor->user_id,
                'target_type' => 'vendor',
                'responder_mode' => 'human',
                'status' => 'waiting_customer',
                'last_message_at' => now()->subHours(6),
            ]),
        ]);

        $this->assertArrayNotHasKey('chat:resume'.'-idle-ai', Artisan::all());

        $sessions->each(function (ChatSession $session): void {
            $session->refresh();
            $this->assertSame('human', $session->responder_mode);
            $this->assertSame('waiting_customer', $session->status);
            $this->assertNotNull($session->assigned_user_id);
            $this->assertDatabaseMissing('chat_messages', [
                'chat_session_id' => $session->id,
                'sender_type' => 'system',
            ]);
        });
        $this->assertSame('', Artisan::output());
    }

    public function test_partner_author_help_articles_are_not_public_or_available_to_rag(): void
    {
        $hidden = HelpArticle::create([
            'category_name' => 'Đối tác & Tác giả',
            'title' => 'Chính sách nhuận bút',
            'content' => 'Nội dung phải được ẩn.',
            'status' => 'published',
        ]);

        $this->getJson('/api/help-center/articles')->assertOk()->assertJsonMissing(['id' => $hidden->id]);
        $this->getJson("/api/help-center/articles/{$hidden->id}")->assertNotFound();
    }

    public function test_membership_overview_exposes_only_operational_benefits_as_verified(): void
    {
        $silver = MembershipTier::create(['name' => 'Bạc', 'min_points' => 0, 'discount_percent' => 0, 'benefits' => 'Quà mô tả']);
        $gold = MembershipTier::create(['name' => 'Vàng', 'min_points' => 100, 'discount_percent' => 10, 'benefits' => 'Miễn phí vận chuyển chưa triển khai']);
        $customer = User::factory()->create(['role' => 'customer', 'points' => 50, 'membership_tier_id' => $silver->id]);

        $response = $this->actingAs($customer)->getJson('/api/profile/membership')
            ->assertOk()
            ->assertJsonPath('data.current_tier_id', $silver->id)
            ->assertJsonPath('data.next_tier_id', $gold->id)
            ->assertJsonPath('data.points_to_next_tier', 50)
            ->assertJsonPath('data.tiers.1.operational_benefits.0.code', 'checkout_discount');

        $this->assertSame('Miễn phí vận chuyển chưa triển khai', $response->json('data.tiers.1.program_description'));

        $session = ChatSession::create(['target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $membershipSource = collect(app(RagSearchService::class)->buildKnowledge($session, 'KomiPoint')['entries'])->firstWhere('type', 'membership');
        $this->assertStringContainsString('1 KomiPoint cho mỗi 10.000đ', $membershipSource['content']);
    }

    public function test_rag_uses_only_published_sellable_content_and_never_falls_back_to_unrelated_latest_rows(): void
    {
        [, $vendor] = $this->vendor('knowledge');
        $category = Category::create(['name' => 'Khoa học', 'slug' => 'khoa-hoc']);
        Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách Công Khai Vũ Trụ',
            'slug' => 'sach-cong-khai-vu-tru',
            'author' => 'Tác giả A',
            'description' => 'Khám phá thiên hà.',
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'status' => 'published',
            'publishing_status' => 'published',
        ]);
        Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Bản Nháp Vũ Trụ',
            'slug' => 'ban-nhap-vu-tru',
            'author' => 'Tác giả B',
            'price' => 90000,
            'stock' => 10,
            'type' => 'physical',
            'status' => 'draft',
            'publishing_status' => 'draft',
        ]);
        Article::create([
            'created_by' => User::factory()->create()->id,
            'article_type' => 'news',
            'title' => 'Tin vũ trụ chưa duyệt',
            'slug' => 'tin-vu-tru-chua-duyet',
            'body' => 'Bí mật nội bộ',
            'status' => ArticleStatus::Draft,
        ]);
        HelpArticle::create(['category_name' => 'Khác', 'title' => 'Không liên quan', 'content' => 'Không được fallback', 'status' => 'published']);

        $session = ChatSession::create(['target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $knowledge = app(RagSearchService::class)->buildKnowledge($session, 'gợi ý sách vũ trụ');

        $this->assertStringContainsString('Sách Công Khai Vũ Trụ', $knowledge['context']);
        $this->assertStringNotContainsString('Bản Nháp Vũ Trụ', $knowledge['context']);
        $this->assertStringNotContainsString('Bí mật nội bộ', $knowledge['context']);
        $this->assertStringNotContainsString('Không được fallback', $knowledge['context']);
    }

    public function test_ai_can_recommend_coupons_and_maintain_multi_turn_history(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);
        [, $vendor] = $this->vendor('coupon-shop');

        Coupon::create([
            'vendor_id' => $vendor->id,
            'code' => 'SALE50',
            'coupon_type' => 'product',
            'discount_percent' => 50,
            'min_order_value' => 100000,
            'max_discount_amount' => 50000,
            'status' => 'active',
        ]);

        $session = ChatSession::create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'conversation_key' => "user:{$customer->id}:vendor:{$vendor->id}",
            'target_type' => 'vendor',
            'responder_mode' => 'ai',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/chat/sessions/{$session->id}/messages", [
                'message' => 'Shop có mã giảm giá nào không?',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $messages = collect($response->json('session.messages'));
        $aiMessage = $messages->last();
        $this->assertSame('ai', $aiMessage['sender_type']);
        $this->assertStringContainsString('SALE50', $aiMessage['message']);
        $this->assertNotEmpty($aiMessage['metadata']['recommended_coupons'] ?? []);
        $this->assertSame('SALE50', $aiMessage['metadata']['recommended_coupons'][0]['code']);
    }

    public function test_ai_can_track_user_order_status_for_authenticated_customer(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);
        [, $vendor] = $this->vendor('order-shop');

        $order = Order::create([
            'order_code' => 'ORD-20260807-TEST123',
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shipping_address' => '123 Nguyễn Huệ, Q1, TP.HCM',
            'phone' => '0901234567',
            'total_amount' => 150000,
            'status' => 'shipped',
            'payment_status' => 'paid',
            'shipping_carrier' => 'KomiExpress',
            'shipping_tracking_code' => 'KE123456789',
            'shipping_status' => 'Đang giao hàng',
        ]);

        $session = ChatSession::create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'conversation_key' => "user:{$customer->id}:vendor:{$vendor->id}",
            'target_type' => 'vendor',
            'responder_mode' => 'ai',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/chat/sessions/{$session->id}/messages", [
                'message' => 'Kiểm tra giúp mình đơn hàng ORD-20260807-TEST123',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $messages = collect($response->json('session.messages'));
        $aiMessage = $messages->last();
        $this->assertSame('ai', $aiMessage['sender_type']);
        $this->assertStringContainsString('ORD-20260807-TEST123', $aiMessage['message']);
        $this->assertNotEmpty($aiMessage['metadata']['recommended_orders'] ?? []);
        $this->assertSame('ORD-20260807-TEST123', $aiMessage['metadata']['recommended_orders'][0]['order_code']);
    }

    public function test_ai_recommends_top_viewed_and_most_popular_books_when_asked_for_trending_books(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);
        [, $vendor] = $this->vendor('trending-shop');

        $category = Category::create(['name' => 'Văn Học', 'slug' => 'van-hoc']);

        // Book 1: 50 views
        $this->publishedBook($vendor, $category, [
            'title' => 'Sách Bình Thường',
            'slug' => 'sach-binh-thuong',
            'views' => 50,
        ]);

        // Book 2: 9999 views (HOTTEST)
        $hotBook = $this->publishedBook($vendor, $category, [
            'title' => 'Sách Siêu Hot 9999 Views',
            'slug' => 'sach-sieu-hot-9999-views',
            'views' => 9999,
        ]);

        $session = ChatSession::create([
            'user_id' => $customer->id,
            'conversation_key' => "user:{$customer->id}:platform",
            'target_type' => 'platform',
            'responder_mode' => 'ai',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/chat/sessions/{$session->id}/messages", [
                'message' => 'Cuốn sách nào đang được mọi người quan tâm nhất?',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $messages = collect($response->json('session.messages'));
        $aiMessage = $messages->last();
        $this->assertSame('ai', $aiMessage['sender_type']);
        $this->assertNotEmpty($aiMessage['metadata']['recommended_books'] ?? []);
        $this->assertSame($hotBook->id, $aiMessage['metadata']['recommended_books'][0]['id']);
    }

    public function test_ai_maintains_consistent_accurate_answers_across_repeated_and_multi_turn_questions(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);
        [, $vendor] = $this->vendor('repeat-shop');

        $category = Category::create(['name' => 'Manga', 'slug' => 'manga']);

        $hotBook = $this->publishedBook($vendor, $category, [
            'title' => 'Komi Nữ Thần Siêu Hot 8888 Views',
            'slug' => 'komi-nu-than-sieu-hot-8888-views',
            'views' => 8888,
        ]);

        $session = ChatSession::create([
            'user_id' => $customer->id,
            'conversation_key' => "user:{$customer->id}:platform",
            'target_type' => 'platform',
            'responder_mode' => 'ai',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        // Turn 1: Initial Question
        $response1 = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/chat/sessions/{$session->id}/messages", [
                'message' => 'Cuốn sách nào đang được mọi người quan tâm nhất?',
            ]);
        $response1->assertOk();
        $msg1 = collect($response1->json('session.messages'))->last();
        $this->assertSame($hotBook->id, $msg1['metadata']['recommended_books'][0]['id']);

        // Turn 2: Repeat / Follow-up Question on the same topic
        $response2 = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/chat/sessions/{$session->id}/messages", [
                'message' => 'Còn cuốn nào được quan tâm nữa?',
            ]);
        $response2->assertOk();
        $msg2 = collect($response2->json('session.messages'))->last();
        $this->assertSame('ai', $msg2['sender_type']);
        $this->assertNotEmpty($msg2['metadata']['recommended_books'] ?? []);
        $this->assertSame($hotBook->id, $msg2['metadata']['recommended_books'][0]['id']);
    }

    public function test_gemini_provider_fails_closed_without_each_required_gate(): void
    {
        foreach ([
            ['enabled' => false],
            ['api_key' => ''],
            ['allowed_models' => []],
            ['model' => 'not-allowlisted'],
        ] as $overrides) {
            $this->configureGemini($overrides);
            Http::preventStrayRequests();
            Http::fake();

            $this->assertNull($this->providerReplyForTest());
            Http::assertNothingSent();
        }

        $this->configureGemini();
        Http::preventStrayRequests();
        Http::fake();
        $this->assertNull($this->providerReplyForTest(context: '   '));
        Http::assertNothingSent();
    }

    public function test_external_ai_consent_is_customer_owned_versioned_and_idempotent(): void
    {
        $this->configureGemini();
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        [$vendorUser] = $this->vendor('consent');
        $session = ChatSession::create(['user_id' => $owner->id, 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $url = "/api/chat/sessions/{$session->id}/external-ai-consent";

        $this->actingAs($other, 'sanctum')->postJson($url, ['consent' => true, 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson($url, ['consent' => true, 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION])->assertForbidden();
        $this->actingAs($vendorUser, 'sanctum')->postJson($url, ['consent' => true, 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION])->assertForbidden();
        $this->actingAs($owner, 'sanctum')->postJson($url, ['consent' => true, 'policy_version' => 'obsolete'])->assertUnprocessable();

        $this->actingAs($owner, 'sanctum')->postJson($url, ['consent' => true, 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION])
            ->assertOk()->assertJsonPath('session.external_ai.consented', true);
        $this->assertSame(1, ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'external_ai_consent_granted')->count());
        $lockVersion = $session->fresh()->lock_version;
        $this->actingAs($owner, 'sanctum')->postJson($url, ['consent' => true, 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION])->assertOk();
        $this->assertSame($lockVersion, $session->fresh()->lock_version);
        $this->assertSame(1, ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'external_ai_consent_granted')->count());

        $this->actingAs($owner, 'sanctum')->postJson($url, ['consent' => false, 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION])
            ->assertOk()->assertJsonPath('session.external_ai.consented', false);
        $this->assertSame(1, ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'external_ai_consent_revoked')->count());
        $lockVersion = $session->fresh()->lock_version;
        $this->actingAs($owner, 'sanctum')->postJson($url, ['consent' => false, 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION])->assertOk();
        $this->assertSame($lockVersion, $session->fresh()->lock_version);
    }

    public function test_external_ai_consent_grant_requires_available_provider_but_revoke_does_not(): void
    {
        config()->set('services.gemini.enabled', false);
        $customer = User::factory()->create(['role' => 'customer']);
        $session = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $url = "/api/chat/sessions/{$session->id}/external-ai-consent";

        $this->actingAs($customer, 'sanctum')->postJson($url, ['consent' => true, 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION])->assertUnprocessable();
        $session->update([
            'external_ai_consent_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION,
            'external_ai_consent_scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE,
            'external_ai_consented_at' => now(),
        ]);
        $this->actingAs($customer, 'sanctum')->postJson($url, ['consent' => false, 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION])->assertOk();
    }

    public function test_provider_fails_closed_without_current_active_consent(): void
    {
        $this->configureGemini();
        foreach ([
            ['external_ai_consent_version' => null, 'external_ai_consent_scope' => null, 'external_ai_consented_at' => null, 'external_ai_consent_revoked_at' => null],
            ['external_ai_consent_version' => 'old-policy', 'external_ai_consent_scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE, 'external_ai_consented_at' => now(), 'external_ai_consent_revoked_at' => null],
            ['external_ai_consent_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION, 'external_ai_consent_scope' => ['current_message'], 'external_ai_consented_at' => now(), 'external_ai_consent_revoked_at' => null],
            ['external_ai_consent_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION, 'external_ai_consent_scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE, 'external_ai_consented_at' => now(), 'external_ai_consent_revoked_at' => now()],
        ] as $consent) {
            $session = $this->providerSession();
            $session->update($consent);
            Http::preventStrayRequests();
            Http::fake();

            $this->assertNull($this->providerReplyForTest($session));
            Http::assertNothingSent();
        }
    }

    public function test_role_changed_owner_cannot_use_external_ai_and_session_metadata_is_truthful(): void
    {
        $this->configureGemini(['max_attempts' => 2]);
        $session = $this->providerSession();
        $owner = $session->user;
        $owner->update(['role' => 'vendor']);
        Http::preventStrayRequests();
        Http::fake();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/chat/sessions/{$session->id}/messages", ['message' => 'current-message-after-role-change'])
            ->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_stale_request_user_cannot_change_external_ai_consent_after_owner_role_changes(): void
    {
        $this->configureGemini();
        $owner = User::factory()->create(['role' => 'customer']);
        $session = ChatSession::create(['user_id' => $owner->id, 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $staleRequestUser = $owner->fresh();
        User::query()->whereKey($owner->id)->update(['role' => 'vendor']);
        $request = Request::create('/api/chat/sessions/'.$session->id.'/external-ai-consent', 'POST', [
            'consent' => true,
            'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION,
        ]);
        $request->setUserResolver(fn () => $staleRequestUser);

        try {
            app(ChatController::class)->updateExternalAiConsent($request, $session);
            $this->fail('The stale request user should not pass locked owner authorization.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $session->refresh();
        $this->assertNull($session->external_ai_consented_at);
        $this->assertSame(0, $session->lock_version);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $session->id)->count());
    }

    public function test_provider_exports_only_allowed_structured_public_entries_and_rechecks_revocation_before_fallback(): void
    {
        $this->configureGemini(['max_attempts' => 2]);
        $session = $this->providerSession();
        Http::preventStrayRequests();
        Http::fake([
            '*gemini-primary*' => function () use ($session) {
                $session->update(['external_ai_consent_revoked_at' => now()]);

                return Http::response([], 429);
            },
            '*gemini-fallback*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'must not send']]]]]]),
        ]);
        $knowledge = ['match_state' => 'matched', 'context' => 'poisoned-prebuilt-context-marker https://private.example/context', 'session_user_id' => $session->user_id, 'scope_target_type' => $session->target_type, 'scope_vendor_id' => $session->vendor_id, 'entries' => [
            ['type' => 'help', 'citation' => 'S1', 'title' => 'Public title', 'content' => 'allowed-public-text', 'id' => 'public-id', 'url' => 'https://private.example/public'],
            ['type' => 'order', 'citation' => 'S2', 'title' => 'Order title', 'content' => 'order-secret-marker', 'id' => 'order-id', 'url' => 'https://private.example/order'],
            ['type' => 'future_type', 'citation' => 'S3', 'title' => 'Future title', 'content' => 'future-secret-marker', 'id' => 'future-id', 'url' => 'https://private.example/future'],
        ]];

        $method = new \ReflectionMethod(app(GeminiChatService::class), 'providerResult');
        $this->assertNull($method->invoke(app(GeminiChatService::class), $session, 'current-message-marker', $knowledge));
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($body, 'current-message-marker')
                && str_contains($body, 'allowed-public-text')
                && ! str_contains($body, 'order-secret-marker')
                && ! str_contains($body, 'future-secret-marker')
                && ! str_contains($body, 'public-id')
                && ! str_contains($body, 'poisoned-prebuilt-context-marker')
                && ! str_contains($body, 'private.example');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'gemini-fallback'));
    }

    public function test_provider_freshly_rechecks_persisted_scope_before_every_attempt(): void
    {
        $this->configureGemini(['max_attempts' => 2]);
        $method = new \ReflectionMethod(app(GeminiChatService::class), 'providerReply');

        $tupleDrift = $this->providerSession();
        $staleTuple = $tupleDrift->fresh();
        $tupleDrift->update(['status' => ChatSession::STATUS_QUEUED, 'responder_mode' => ChatSession::MODE_HUMAN]);
        Http::preventStrayRequests();
        Http::fake();
        $this->assertNull($method->invoke(app(GeminiChatService::class), $staleTuple, 'scope drift', $this->providerKnowledge('public scope', $staleTuple)));
        Http::assertNothingSent();

        $owner = User::factory()->create(['role' => 'customer']);
        [, $vendor] = $this->vendor('provider-scope');
        $vendorSession = ChatSession::create([
            'user_id' => $owner->id,
            'vendor_id' => $vendor->id,
            'target_type' => ChatSession::TARGET_VENDOR,
            'responder_mode' => ChatSession::MODE_AI,
            'status' => ChatSession::STATUS_OPEN,
            'external_ai_consent_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION,
            'external_ai_consent_scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE,
            'external_ai_consented_at' => now(),
        ]);
        $staleVendor = $vendorSession->fresh();
        $vendor->update(['status' => 'inactive']);
        Http::preventStrayRequests();
        Http::fake();
        $this->assertNull($method->invoke(app(GeminiChatService::class), $staleVendor, 'vendor drift', $this->providerKnowledge('vendor public scope', $staleVendor)));
        Http::assertNothingSent();

        $fallbackDrift = $this->providerSession();
        $staleFallback = $fallbackDrift->fresh();
        Http::preventStrayRequests();
        Http::fake([
            '*gemini-primary*' => function () use ($fallbackDrift) {
                $fallbackDrift->update(['status' => ChatSession::STATUS_QUEUED, 'responder_mode' => ChatSession::MODE_HUMAN]);

                return Http::response([], 429);
            },
            '*gemini-fallback*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'must not send']]]]]]),
        ]);
        $this->assertNull($method->invoke(app(GeminiChatService::class), $staleFallback, 'fallback scope drift', $this->providerKnowledge('fallback public scope', $staleFallback)));
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'gemini-fallback'));
    }

    public function test_provider_binds_grounding_scope_to_the_lifecycle_snapshot_before_primary(): void
    {
        $this->configureGemini();
        $method = new \ReflectionMethod(app(GeminiChatService::class), 'providerReply');
        $ownerSession = $this->providerSession();
        $ownerSnapshot = $ownerSession->fresh();
        ChatMessage::create(['chat_session_id' => $ownerSession->id, 'sender_type' => 'customer', 'message' => 'raw-message-before-owner-drift']);
        $replacementOwner = User::factory()->create(['role' => 'customer']);
        $ownerSession->update(['user_id' => $replacementOwner->id]);
        Http::preventStrayRequests();
        Http::fake();
        $this->assertNull($method->invoke(app(GeminiChatService::class), $ownerSnapshot, 'owner drift', $this->providerKnowledge('owner-grounded-public-context', $ownerSnapshot)));
        Http::assertNothingSent();

        $scopeSession = $this->providerSession();
        $scopeSnapshot = $scopeSession->fresh();
        [, $vendor] = $this->vendor('provider-target-drift');
        $scopeSession->update(['target_type' => ChatSession::TARGET_VENDOR, 'vendor_id' => $vendor->id]);
        Http::preventStrayRequests();
        Http::fake();
        $this->assertNull($method->invoke(app(GeminiChatService::class), $scopeSnapshot, 'target drift', $this->providerKnowledge('target-grounded-public-context', $scopeSnapshot)));
        Http::assertNothingSent();
    }

    public function test_public_generate_reply_fails_closed_when_owner_transfers_before_grounding(): void
    {
        $this->configureGemini();
        $ownerSession = $this->providerSession();
        $snapshot = $ownerSession->fresh();
        ChatMessage::create(['chat_session_id' => $ownerSession->id, 'sender_type' => 'customer', 'message' => 'raw-message-before-owner-transfer']);
        HelpArticle::create(['category_name' => 'Support', 'title' => 'Chính sách đổi trả', 'content' => 'Nội dung công khai để kiểm thử ràng buộc chủ sở hữu.', 'status' => 'published']);
        $replacementOwner = User::factory()->create(['role' => 'customer']);
        $ownerSession->update(['user_id' => $replacementOwner->id]);
        Http::preventStrayRequests();
        Http::fake(['*gemini-primary*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'must not send']]]]]])]);

        $result = app(GeminiChatService::class)->generateReply($snapshot, 'Chính sách đổi trả là gì?');

        $this->assertSame(['matched', 'help'], [$result['metadata']['match_state'], $result['metadata']['primary_intent']]);
        $this->assertSame('local_grounded', $result['metadata']['delivery']);
        $this->assertSame('local_grounded', $result['metadata']['engine']['provider']);
        $this->assertArrayNotHasKey('usage', $result['metadata']);
        $this->assertStringNotContainsString('test-key-not-real', json_encode($result));
        Http::assertNothingSent();
    }

    public function test_public_generate_reply_fails_closed_when_only_vendor_scope_drifts_before_primary(): void
    {
        $this->configureGemini();
        $owner = User::factory()->create(['role' => 'customer']);
        [, $vendorA] = $this->vendor('provider-public-a');
        [, $vendorB] = $this->vendor('provider-public-b');
        $session = ChatSession::create([
            'user_id' => $owner->id,
            'vendor_id' => $vendorA->id,
            'target_type' => ChatSession::TARGET_VENDOR,
            'responder_mode' => ChatSession::MODE_AI,
            'status' => ChatSession::STATUS_OPEN,
            'external_ai_consent_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION,
            'external_ai_consent_scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE,
            'external_ai_consented_at' => now(),
        ]);
        $snapshot = $session->fresh();
        HelpArticle::create(['category_name' => 'Support', 'title' => 'Chính sách bảo hành', 'content' => 'Nội dung công khai để kiểm thử ràng buộc gian hàng.', 'status' => 'published']);
        $session->update(['vendor_id' => $vendorB->id]);
        Http::preventStrayRequests();
        Http::fake(['*gemini-primary*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'must not send']]]]]])]);

        $result = app(GeminiChatService::class)->generateReply($snapshot, 'Chính sách bảo hành là gì?');

        $this->assertSame(['matched', 'help'], [$result['metadata']['match_state'], $result['metadata']['primary_intent']]);
        $this->assertSame('local_grounded', $result['metadata']['delivery']);
        $this->assertSame('local_grounded', $result['metadata']['engine']['provider']);
        $this->assertArrayNotHasKey('usage', $result['metadata']);
        Http::assertNothingSent();
    }

    public function test_provider_does_not_send_fallback_after_owner_target_vendor_or_consent_drift(): void
    {
        $this->configureGemini(['max_attempts' => 2]);
        $method = new \ReflectionMethod(app(GeminiChatService::class), 'providerReply');
        $replacementOwner = User::factory()->create(['role' => 'customer']);
        [, $targetVendor] = $this->vendor('provider-fallback-target');
        [, $firstVendor] = $this->vendor('provider-fallback-first');
        [, $secondVendor] = $this->vendor('provider-fallback-second');
        $vendorOwner = User::factory()->create(['role' => 'customer']);
        $vendorSession = ChatSession::create([
            'user_id' => $vendorOwner->id,
            'vendor_id' => $firstVendor->id,
            'target_type' => ChatSession::TARGET_VENDOR,
            'responder_mode' => ChatSession::MODE_AI,
            'status' => ChatSession::STATUS_OPEN,
            'external_ai_consent_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION,
            'external_ai_consent_scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE,
            'external_ai_consented_at' => now(),
        ]);
        $cases = [
            [$this->providerSession(), fn (ChatSession $session) => $session->update(['user_id' => $replacementOwner->id])],
            [$this->providerSession(), fn (ChatSession $session) => $session->update(['target_type' => ChatSession::TARGET_VENDOR, 'vendor_id' => $targetVendor->id])],
            [$vendorSession, fn (ChatSession $session) => $session->update(['vendor_id' => $secondVendor->id])],
            [$this->providerSession(), fn (ChatSession $session) => $session->update(['external_ai_consent_revoked_at' => now()])],
        ];

        foreach ($cases as [$session, $drift]) {
            $snapshot = $session->fresh();
            Http::preventStrayRequests();
            Http::fake([
                '*gemini-primary*' => function () use ($session, $drift) {
                    $drift($session);

                    return Http::response([], 429);
                },
                '*gemini-fallback*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'must not send']]]]]]),
            ]);

            $this->assertNull($method->invoke(app(GeminiChatService::class), $snapshot, 'fallback drift', $this->providerKnowledge('fallback-grounded-public-context', $snapshot)));
            Http::assertSentCount(1);
            Http::assertNotSent(fn ($request) => str_contains($request->url(), 'gemini-fallback'));
        }
    }

    public function test_gemini_provider_rejects_disallowed_duplicate_and_budget_exhausted_fallbacks(): void
    {
        foreach ([
            ['fallback_model' => 'not-allowlisted', 'max_attempts' => 2],
            ['fallback_model' => 'gemini-primary', 'max_attempts' => 2],
            ['fallback_model' => 'gemini-fallback', 'max_attempts' => 1],
        ] as $overrides) {
            $this->configureGemini($overrides);
            Http::preventStrayRequests();
            Http::fake(['*gemini-primary*' => Http::response([], 429)]);

            $this->assertNull($this->providerReplyForTest());
            Http::assertSentCount(1);
        }
    }

    public function test_gemini_provider_does_not_fallback_after_non_429_malformed_or_exception(): void
    {
        $responses = [
            Http::response([], 500),
            Http::response(['candidates' => []]),
            function (): void {
                throw new \RuntimeException('exception-secret-marker');
            },
        ];

        foreach ($responses as $response) {
            $this->configureGemini(['max_attempts' => 2]);
            Http::preventStrayRequests();
            Http::fake(['*gemini-primary*' => $response]);

            $this->assertNull($this->providerReplyForTest());
            if ($response instanceof \Closure) {
                Http::assertNothingSent();
            } else {
                Http::assertSentCount(1);
            }
        }
    }

    public function test_gemini_provider_normalizes_allowlist_and_clamps_limits(): void
    {
        $this->configureGemini([
            'allowed_models' => ' gemini-primary, ,gemini-primary, gemini-fallback ',
            'max_attempts' => 99,
            'connect_timeout' => 99,
            'timeout' => -20,
            'max_output_tokens' => 99999,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            '*gemini-primary*' => Http::response([], 429),
            '*gemini-fallback*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]]),
        ]);

        $reply = $this->providerReplyForTest();

        $this->assertSame('gemini-fallback', $reply['model']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => data_get($request->data(), 'generationConfig.maxOutputTokens') === 1200);

        $method = new \ReflectionMethod(app(GeminiChatService::class), 'clamp');
        $this->assertSame(3, $method->invoke(app(GeminiChatService::class), 99, 1, 3));
        $this->assertSame(1, $method->invoke(app(GeminiChatService::class), -20, 1, 12));
    }

    public function test_gemini_provider_excludes_history_attachments_and_sensitive_telemetry(): void
    {
        $this->configureGemini();
        $session = $this->providerSession();
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'customer',
            'message' => 'history-secret-marker',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'ai',
            'message' => 'response-secret-marker',
        ]);

        Log::spy();
        Http::preventStrayRequests();
        Http::fake(['*gemini-primary*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'safe reply']]]]],
            'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 7, 'totalTokenCount' => 19],
            'debug' => 'response-body-secret-marker',
        ])]);

        $reply = $this->providerReplyForTest($session, 'current-question', '[S1] current-context', [
            'attachment' => ['stored_name' => 'attachment-secret-marker', 'mime_type' => 'image/png', 'bytes' => 'attachment-bytes-secret-marker'],
        ]);

        $this->assertSame('safe reply', $reply['message']);
        $this->assertSame(['prompt_tokens' => 12, 'completion_tokens' => 7, 'total_tokens' => 19], $reply['usage']);
        $usageMethod = new \ReflectionMethod(app(GeminiChatService::class), 'usageCounters');
        $this->assertSame(['prompt_tokens' => 12], $usageMethod->invoke(app(GeminiChatService::class), [
            'promptTokenCount' => '12',
            'candidatesTokenCount' => -1,
            'totalTokenCount' => '7.5',
        ]));
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return $request->hasHeader('x-goog-api-key', 'test-key-not-real')
                && ! str_contains($request->url(), 'test-key-not-real')
                && ! str_contains($body, 'history-secret-marker')
                && ! str_contains($body, 'response-secret-marker')
                && ! str_contains($body, 'attachment-secret-marker')
                && ! str_contains($body, 'attachment-bytes-secret-marker')
                && str_contains($body, 'current-question')
                && str_contains($body, 'current-context');
        });
        Http::preventStrayRequests();
        Http::fake(['*gemini-primary*' => function (): void {
            throw new \RuntimeException('exception-secret-marker');
        }]);
        $this->assertNull($this->providerReplyForTest($session));

        Log::shouldHaveReceived('info')->twice()->withArgs(function (string $message, array $context): bool {
            $allowed = ['provider', 'model', 'attempt', 'status', 'outcome', 'elapsed_ms', 'fallback_attempted', 'prompt_tokens', 'completion_tokens', 'total_tokens'];

            return $message === 'Gemini chat provider telemetry'
                && array_diff(array_keys($context), $allowed) === []
                && ! str_contains(json_encode($context), 'secret-marker');
        });
    }

    public function test_rag_resolves_persisted_scope_and_denies_invalid_scopes_without_provider_requests(): void
    {
        $this->configureGemini();
        Http::preventStrayRequests();
        Http::fake();
        [, $activeVendor] = $this->vendor('truth-scope');
        Coupon::create(['code' => 'PLATFORM-ONLY', 'coupon_type' => 'product', 'discount_percent' => 10, 'status' => 'active']);
        Coupon::create(['vendor_id' => $activeVendor->id, 'code' => 'VENDOR-ONLY', 'coupon_type' => 'product', 'discount_percent' => 20, 'status' => 'active']);

        $platform = ChatSession::create(['target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $mutated = $platform->fresh();
        $mutated->target_type = 'vendor';
        $mutated->vendor_id = $activeVendor->id;
        $truthful = app(RagSearchService::class)->buildKnowledge($mutated, 'voucher');
        $this->assertSame('matched', $truthful['match_state']);
        $this->assertSame(['PLATFORM-ONLY'], collect($truthful['recommended_coupons'])->pluck('code')->all());
        $this->assertSame(ChatSession::TARGET_PLATFORM, $truthful['scope_target_type']);
        $this->assertNull($truthful['scope_vendor_id']);

        $invalidTarget = ChatSession::create(['target_type' => 'unknown', 'responder_mode' => 'ai', 'status' => 'open']);
        $platformWithVendor = ChatSession::create(['target_type' => 'platform', 'vendor_id' => $activeVendor->id, 'responder_mode' => 'ai', 'status' => 'open']);
        $missingVendor = ChatSession::create(['target_type' => 'vendor', 'responder_mode' => 'ai', 'status' => 'open']);
        [, $inactiveVendor] = $this->vendor('inactive-scope');
        $inactiveVendor->update(['status' => 'inactive']);
        $inactiveVendorSession = ChatSession::create(['target_type' => 'vendor', 'vendor_id' => $inactiveVendor->id, 'responder_mode' => 'ai', 'status' => 'open']);

        foreach ([$invalidTarget, $platformWithVendor, $missingVendor, $inactiveVendorSession] as $invalid) {
            $knowledge = app(RagSearchService::class)->buildKnowledge($invalid, 'voucher');
            $this->assertSame('denied', $knowledge['match_state']);
            $this->assertSame('invalid_session_scope', $knowledge['match_reason']);
            $this->assertSame([], $knowledge['sources']);
            $this->assertSame([], $knowledge['entries']);
            $this->assertSame([], $knowledge['recommended_books']);

            $reply = app(GeminiChatService::class)->generateReply($invalid, 'Tôi muốn gặp nhân viên');
            $this->assertSame('denied', $reply['metadata']['match_state']);
            $this->assertSame('invalid_session_scope', $reply['metadata']['match_reason']);
        }
        Http::assertNothingSent();
    }

    public function test_rag_enforces_public_visibility_article_tenancy_and_exact_private_lookups(): void
    {
        $this->configureGemini();
        Http::preventStrayRequests();
        Http::fake();
        $category = Category::create(['name' => 'RAG truth', 'slug' => 'rag-truth']);
        [, $vendor] = $this->vendor('rag-own');
        [, $otherVendor] = $this->vendor('rag-other');
        $owner = User::factory()->create(['role' => 'customer']);
        $otherOwner = User::factory()->create(['role' => 'customer']);
        $vendorSession = ChatSession::create(['user_id' => $owner->id, 'target_type' => 'vendor', 'vendor_id' => $vendor->id, 'responder_mode' => 'ai', 'status' => 'open']);
        $platformSession = ChatSession::create(['target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);

        $this->publishedBook($vendor, $category, ['title' => 'Sách Ebook Công Khai', 'slug' => 'ebook-cong-khai', 'type' => 'ebook', 'stock' => 0, 'views' => 77]);
        $this->publishedBook($vendor, $category, ['title' => 'Sách Hết Hàng', 'slug' => 'sach-het-hang', 'stock' => 0]);
        $this->publishedBook($vendor, $category, ['title' => 'Sách Bản Nháp', 'slug' => 'sach-ban-nhap', 'status' => 'draft', 'publishing_status' => 'draft']);
        $this->publishedBook($otherVendor, $category, ['title' => 'Sách Vendor Tắt', 'slug' => 'sach-vendor-tat']);
        $otherVendor->update(['status' => 'inactive']);
        $books = app(RagSearchService::class)->buildKnowledge($platformSession, 'tìm sách Sách');
        $titles = collect($books['recommended_books'])->pluck('title')->all();
        $this->assertContains('Sách Ebook Công Khai', $titles);
        $this->assertNotContains('Sách Hết Hàng', $titles);
        $this->assertNotContains('Sách Bản Nháp', $titles);
        $this->assertNotContains('Sách Vendor Tắt', $titles);
        $bookEntry = collect($books['entries'])->firstWhere('type', 'book');
        $this->assertStringContainsString('Thể loại:', $bookEntry['content']);
        $this->assertStringContainsString('Đánh giá', $bookEntry['content']);
        $this->assertStringContainsString('77 lượt xem', $bookEntry['content']);

        foreach ([
            ['vendor_id' => null, 'title' => 'Bài viết biên tập RAG', 'slug' => 'editorial-rag', 'published_at' => now()],
            ['vendor_id' => $vendor->id, 'title' => 'Bài viết gian hàng RAG', 'slug' => 'own-rag', 'published_at' => now()],
            ['vendor_id' => $otherVendor->id, 'title' => 'Bài viết gian hàng khác RAG', 'slug' => 'other-rag', 'published_at' => now()],
            ['vendor_id' => null, 'title' => 'Bài viết tương lai RAG', 'slug' => 'future-rag', 'published_at' => now()->addDay()],
        ] as $article) {
            Article::create(array_merge(['created_by' => $owner->id, 'article_type' => 'news', 'body' => 'Nội dung RAG', 'status' => ArticleStatus::Published], $article));
        }
        $vendorArticles = app(RagSearchService::class)->buildKnowledge($vendorSession, 'bài viết RAG');
        $vendorArticleTitles = collect($vendorArticles['entries'])->where('type', 'article')->pluck('title')->all();
        $this->assertContains('Bài viết biên tập RAG', $vendorArticleTitles);
        $this->assertContains('Bài viết gian hàng RAG', $vendorArticleTitles);
        $this->assertNotContains('Bài viết gian hàng khác RAG', $vendorArticleTitles);
        $this->assertNotContains('Bài viết tương lai RAG', $vendorArticleTitles);

        Coupon::create(['vendor_id' => $vendor->id, 'code' => 'LIVE-CODE', 'coupon_type' => 'product', 'discount_percent' => 20, 'status' => 'active']);
        Coupon::create(['vendor_id' => $vendor->id, 'code' => 'LEGACY-CODE', 'coupon_type' => 'percentage', 'discount_percent' => 20, 'status' => 'active']);
        Coupon::create(['vendor_id' => $vendor->id, 'code' => 'FUTURE-CODE', 'coupon_type' => 'product', 'discount_percent' => 20, 'status' => 'active', 'start_time' => now()->addDay()]);
        Coupon::create(['vendor_id' => $vendor->id, 'code' => 'EXHAUSTED-CODE', 'coupon_type' => 'product', 'discount_percent' => 20, 'status' => 'active', 'usage_limit' => 1, 'used_count' => 1]);
        $coupon = app(RagSearchService::class)->buildKnowledge($vendorSession, 'voucher');
        $this->assertSame(['LIVE-CODE'], collect($coupon['recommended_coupons'])->pluck('code')->all());
        $this->assertSame(ChatSession::TARGET_VENDOR, $coupon['scope_target_type']);
        $this->assertSame($vendor->id, $coupon['scope_vendor_id']);
        $missingCoupon = app(RagSearchService::class)->buildKnowledge($vendorSession, 'mã MISSING-CODE');
        $this->assertSame(['no_match', 'coupon_not_found', 'coupon'], [$missingCoupon['match_state'], $missingCoupon['match_reason'], $missingCoupon['primary_intent']]);

        Order::create(['order_code' => 'ORD-OWN-100', 'user_id' => $owner->id, 'vendor_id' => $vendor->id, 'shipping_address' => '1 Test Street', 'phone' => '0900000001', 'total_amount' => 100000, 'status' => 'pending', 'payment_status' => 'pending']);
        Order::create(['order_code' => 'ORD-OTHER-100', 'user_id' => $otherOwner->id, 'vendor_id' => $vendor->id, 'shipping_address' => '2 Test Street', 'phone' => '0900000002', 'total_amount' => 100000, 'status' => 'pending', 'payment_status' => 'pending']);
        $missingOrder = app(RagSearchService::class)->buildKnowledge($vendorSession, 'ORD-MISSING-100');
        $this->assertSame(['no_match', 'order_not_found', 'order'], [$missingOrder['match_state'], $missingOrder['match_reason'], $missingOrder['primary_intent']]);
        $this->assertSame([], $missingOrder['recommended_orders']);
        $ownerless = ChatSession::create(['target_type' => 'vendor', 'vendor_id' => $vendor->id, 'responder_mode' => 'ai', 'status' => 'open']);
        $deniedOrder = app(RagSearchService::class)->buildKnowledge($ownerless, 'ORD-OWN-100');
        $this->assertSame(['denied', 'owner_required'], [$deniedOrder['match_state'], $deniedOrder['match_reason']]);
        Http::assertNothingSent();
    }

    public function test_help_order_and_follow_up_states_are_intent_driven_and_never_provider_backed_when_missing(): void
    {
        $this->configureGemini();
        Http::preventStrayRequests();
        Http::fake();
        $customer = User::factory()->create(['role' => 'customer']);
        $session = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        HelpArticle::create(['category_name' => 'Giao hàng', 'title' => 'Chính sách giao hàng và vận chuyển', 'content' => 'Giao trong 48 giờ.', 'status' => 'published']);
        $help = app(GeminiChatService::class)->generateReply($session, 'chính sách giao hàng');
        $this->assertSame(['matched', 'matched', 'help'], [$help['metadata']['match_state'], $help['metadata']['match_reason'], $help['metadata']['primary_intent']]);
        $this->assertArrayNotHasKey('recommended_orders', $help['metadata']);

        $shippingHelp = app(GeminiChatService::class)->generateReply($session, 'chính sách vận chuyển');
        $this->assertSame(['matched', 'matched', 'help'], [$shippingHelp['metadata']['match_state'], $shippingHelp['metadata']['match_reason'], $shippingHelp['metadata']['primary_intent']]);
        $this->assertSame('local_grounded', $shippingHelp['metadata']['delivery']);
        $this->assertArrayNotHasKey('recommended_orders', $shippingHelp['metadata']);

        ChatMessage::create(['chat_session_id' => $session->id, 'sender_type' => 'customer', 'message' => 'tìm sách thiên văn']);
        $unrelated = app(GeminiChatService::class)->generateReply($session, 'mã NO-COUPON');
        $this->assertSame(['no_match', 'coupon_not_found', 'coupon'], [$unrelated['metadata']['match_state'], $unrelated['metadata']['match_reason'], $unrelated['metadata']['primary_intent']]);
        $this->assertSame('local_grounded', $unrelated['metadata']['delivery']);
        Http::assertNothingSent();
    }

    public function test_personal_order_phrases_are_scoped_to_the_current_customer_and_vendor(): void
    {
        [, $vendor] = $this->vendor('personal-orders');
        [, $otherVendor] = $this->vendor('personal-orders-other');
        $owner = User::factory()->create(['role' => 'customer']);
        $otherOwner = User::factory()->create(['role' => 'customer']);
        $session = ChatSession::create(['user_id' => $owner->id, 'target_type' => 'vendor', 'vendor_id' => $vendor->id, 'responder_mode' => 'ai', 'status' => 'open']);

        $ownOlder = Order::create(['order_code' => 'ORD-OWN-OLDER', 'user_id' => $owner->id, 'vendor_id' => $vendor->id, 'shipping_address' => '1 Test Street', 'phone' => '0900000001', 'total_amount' => 100000, 'status' => 'pending', 'payment_status' => 'pending']);
        $ownLatest = Order::create(['order_code' => 'ORD-OWN-LATEST', 'user_id' => $owner->id, 'vendor_id' => $vendor->id, 'shipping_address' => '1 Test Street', 'phone' => '0900000001', 'total_amount' => 200000, 'status' => 'processing', 'payment_status' => 'paid']);
        Order::query()->whereKey($ownOlder->id)->update(['created_at' => now()->subDay()]);
        Order::query()->whereKey($ownLatest->id)->update(['created_at' => now()->addSecond()]);
        Order::create(['order_code' => 'ORD-OTHER-USER', 'user_id' => $otherOwner->id, 'vendor_id' => $vendor->id, 'shipping_address' => '2 Test Street', 'phone' => '0900000002', 'total_amount' => 300000, 'status' => 'pending', 'payment_status' => 'pending']);
        Order::create(['order_code' => 'ORD-OTHER-VENDOR', 'user_id' => $owner->id, 'vendor_id' => $otherVendor->id, 'shipping_address' => '3 Test Street', 'phone' => '0900000003', 'total_amount' => 400000, 'status' => 'pending', 'payment_status' => 'pending']);

        foreach (['đơn hàng của tôi', 'đơn gần đây', 'đơn mới nhất'] as $query) {
            $knowledge = app(RagSearchService::class)->buildKnowledge($session, $query);
            $this->assertSame(['matched', 'matched', 'order'], [$knowledge['match_state'], $knowledge['match_reason'], $knowledge['primary_intent']]);
            $this->assertSame(['ORD-OWN-LATEST', 'ORD-OWN-OLDER'], collect($knowledge['recommended_orders'])->pluck('order_code')->all());
        }

        foreach (['ORD-OTHER-USER', 'ORD-OTHER-VENDOR'] as $code) {
            $knowledge = app(RagSearchService::class)->buildKnowledge($session, $code);
            $this->assertSame(['no_match', 'order_not_found', 'order'], [$knowledge['match_state'], $knowledge['match_reason'], $knowledge['primary_intent']]);
            $this->assertSame([], $knowledge['recommended_orders']);
        }

        $nonCustomer = User::factory()->create(['role' => 'vendor']);
        $nonCustomerSession = ChatSession::create(['user_id' => $nonCustomer->id, 'target_type' => 'vendor', 'vendor_id' => $vendor->id, 'responder_mode' => 'ai', 'status' => 'open']);
        $denied = app(RagSearchService::class)->buildKnowledge($nonCustomerSession, 'đơn gần đây');
        $this->assertSame(['denied', 'owner_required', 'order'], [$denied['match_state'], $denied['match_reason'], $denied['primary_intent']]);
    }

    public function test_follow_up_detection_does_not_inherit_personal_orders_into_an_independent_book_request(): void
    {
        config()->set('services.gemini.enabled', false);
        [, $vendor] = $this->vendor('follow-up-boundary');
        $category = Category::create(['name' => 'Động vật', 'slug' => 'dong-vat-follow-up']);
        $owner = User::factory()->create(['role' => 'customer']);
        $session = ChatSession::create(['user_id' => $owner->id, 'target_type' => 'vendor', 'vendor_id' => $vendor->id, 'responder_mode' => 'ai', 'status' => 'open']);
        $book = $this->publishedBook($vendor, $category, ['title' => 'Thế Giới Động Vật', 'slug' => 'the-gioi-dong-vat']);
        Order::create(['order_code' => 'ORD-FOLLOW-UP-PRIVATE', 'user_id' => $owner->id, 'vendor_id' => $vendor->id, 'shipping_address' => '1 Test Street', 'phone' => '0900000001', 'total_amount' => 100000, 'status' => 'pending', 'payment_status' => 'pending']);
        ChatMessage::create(['chat_session_id' => $session->id, 'sender_type' => 'customer', 'message' => 'đơn hàng của tôi']);

        $independent = app(GeminiChatService::class)->generateReply($session, 'tìm sách về thế giới động vật');

        $this->assertSame('book', $independent['metadata']['primary_intent']);
        $this->assertArrayNotHasKey('recommended_orders', $independent['metadata']);
        $this->assertNotContains('order', collect($independent['metadata']['sources'])->pluck('type')->all());
        $this->assertStringNotContainsString('ORD-FOLLOW-UP-PRIVATE', $independent['message']);
        $this->assertSame($book->id, $independent['metadata']['recommended_books'][0]['id']);

        $continuationSession = ChatSession::create(['user_id' => $owner->id, 'target_type' => 'vendor', 'vendor_id' => $vendor->id, 'responder_mode' => 'ai', 'status' => 'open']);
        ChatMessage::create(['chat_session_id' => $continuationSession->id, 'sender_type' => 'customer', 'message' => 'tìm sách thế giới động vật']);
        $continuationMessage = 'Còn cuốn nào được quan tâm nữa?';
        $resolver = new \ReflectionMethod(app(GeminiChatService::class), 'resolveEffectiveQuery');
        $this->assertSame('tìm sách thế giới động vật '.$continuationMessage, $resolver->invoke(app(GeminiChatService::class), $continuationSession, $continuationMessage));
        $continuation = app(GeminiChatService::class)->generateReply($continuationSession, $continuationMessage);

        $this->assertSame('catalog', $continuation['metadata']['primary_intent']);
        $this->assertSame($book->id, $continuation['metadata']['recommended_books'][0]['id']);
    }

    public function test_coupon_with_elapsed_end_time_is_excluded_from_current_results(): void
    {
        [, $vendor] = $this->vendor('ended-coupon');
        $session = ChatSession::create(['target_type' => 'vendor', 'vendor_id' => $vendor->id, 'responder_mode' => 'ai', 'status' => 'open']);
        Coupon::create(['vendor_id' => $vendor->id, 'code' => 'LIVE-END-TIME', 'coupon_type' => 'product', 'discount_percent' => 10, 'status' => 'active']);
        Coupon::create(['vendor_id' => $vendor->id, 'code' => 'ENDED-END-TIME', 'coupon_type' => 'product', 'discount_percent' => 90, 'status' => 'active', 'end_time' => now()->subSecond()]);

        $knowledge = app(RagSearchService::class)->buildKnowledge($session, 'voucher');

        $this->assertSame(['LIVE-END-TIME'], collect($knowledge['recommended_coupons'])->pluck('code')->all());
        $this->assertStringNotContainsString('ENDED-END-TIME', $knowledge['context']);
    }

    public function test_provider_result_refuses_no_match_even_with_valid_persisted_customer_consent_and_public_context(): void
    {
        $this->configureGemini();
        $session = $this->providerSession();
        Http::preventStrayRequests();
        Http::fake(['*gemini-primary*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'must not send']]]]]])]);
        $knowledge = $this->providerKnowledge('Public context that would otherwise be eligible.', $session);
        $knowledge['match_state'] = 'no_match';

        $method = new \ReflectionMethod(app(GeminiChatService::class), 'providerResult');
        $this->assertNull($method->invoke(app(GeminiChatService::class), $session, 'current message', $knowledge));
        Http::assertNothingSent();
    }

    public function test_lifecycle_replays_are_idempotent_and_audited_once(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $session = ChatSession::create(['user_id' => $customer->id, 'conversation_key' => "user:{$customer->id}:platform", 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);

        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/request-human")->assertOk()->assertJsonPath('session.status', 'queued');
        $afterHandoff = $session->fresh();
        $events = ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'chat_session_transition')->count();
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/request-human")->assertOk();
        $this->assertSame($afterHandoff->lock_version, $session->fresh()->lock_version);
        $this->assertSame($events, ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'chat_session_transition')->count());

        $this->actingAs($admin)->postJson("/api/chat/admin/sessions/{$session->id}/takeover")->assertOk()->assertJsonPath('session.status', 'assigned');
        $taken = $session->fresh();
        $this->actingAs($admin)->postJson("/api/chat/admin/sessions/{$session->id}/takeover")->assertOk();
        $this->assertSame($taken->lock_version, $session->fresh()->lock_version);
        $this->actingAs($admin)->postJson("/api/chat/admin/sessions/{$session->id}/reply", ['message' => 'Đã nhận'])->assertOk()->assertJsonPath('session.status', 'waiting_customer');
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/messages", ['message' => 'Cảm ơn'])->assertOk()->assertJsonPath('session.status', 'assigned');
        $this->actingAs($admin)->postJson("/api/chat/admin/sessions/{$session->id}/close", ['resolution' => '   '])->assertOk()->assertJsonPath('session.status', 'resolved');
        $closed = $session->fresh();
        $this->assertNotEmpty(ChatMessage::query()->where('chat_session_id', $session->id)->latest('id')->value('message'));
        $this->actingAs($admin)->postJson("/api/chat/admin/sessions/{$session->id}/close", ['resolution' => 'different'])->assertOk();
        $this->assertSame($closed->lock_version, $session->fresh()->lock_version);
        $this->actingAs($customer)->postJson('/api/chat/sessions', ['target_type' => 'platform'])->assertOk()->assertJsonPath('session.status', 'open');
    }

    public function test_resume_rejections_takeover_conflict_and_audit_envelope_are_exact(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $session = ChatSession::create(['user_id' => $customer->id, 'conversation_key' => "user:{$customer->id}:platform", 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open', 'external_ai_consent_version' => 'v1', 'external_ai_consent_scope' => ['scope'], 'external_ai_consented_at' => now()]);

        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/request-human")->assertOk();
        $queued = $session->fresh();
        $events = ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'chat_session_transition')->count();
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/resume-ai")->assertOk()->assertJsonPath('session.status', 'open');
        $open = $session->fresh();
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/resume-ai")->assertOk();
        $this->assertSame($open->lock_version, $session->fresh()->lock_version);
        $this->assertSame($open->external_ai_consented_at?->toISOString(), $session->fresh()->external_ai_consented_at?->toISOString());
        $this->assertSame($events + 1, ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'chat_session_transition')->count());

        $session->update(['responder_mode' => 'human', 'status' => 'assigned', 'assigned_user_id' => $admin->id]);
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/resume-ai")->assertStatus(409);
        $session->update(['status' => 'waiting_customer']);
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/resume-ai")->assertStatus(409);
        $session->update(['status' => 'resolved']);
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/resume-ai")->assertStatus(409);
        $session->update(['status' => 'queued', 'assigned_user_id' => $admin->id]);
        $this->actingAs($customer)->postJson("/api/chat/sessions/{$session->id}/resume-ai")->assertStatus(409);

        $session->update(['status' => 'queued', 'assigned_user_id' => null]);
        $this->actingAs($admin)->postJson("/api/chat/admin/sessions/{$session->id}/takeover")->assertOk();
        $taken = $session->fresh();
        $takenEvents = ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'chat_session_transition')->count();
        $this->actingAs($admin)->postJson("/api/chat/admin/sessions/{$session->id}/takeover")->assertOk();
        $this->actingAs($otherAdmin)->postJson("/api/chat/admin/sessions/{$session->id}/takeover")->assertStatus(409);
        $this->assertSame($taken->lock_version, $session->fresh()->lock_version);
        $this->assertSame($takenEvents, ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'chat_session_transition')->count());

        $audit = ChatMessage::query()->where('chat_session_id', $session->id)->where('metadata->event', 'chat_session_transition')->firstOrFail();
        foreach (['event', 'schema_version', 'operation', 'reason', 'actor_type', 'actor_id', 'from', 'to', 'target_type', 'vendor_id', 'lock_version_before', 'lock_version_after'] as $key) {
            $this->assertArrayHasKey($key, $audit->metadata);
        }
        $this->assertSame(1, $audit->metadata['schema_version']);
        $this->assertNotSame($audit->metadata['reason'], $audit->message);
        $this->assertSame($queued->target_type, $audit->metadata['target_type']);
    }

    public function test_lifecycle_revalidates_stale_customer_and_inactive_vendor_inside_lock(): void
    {
        $service = app(ChatSessionLifecycleService::class);
        $customer = User::factory()->create(['role' => 'customer']);
        $platform = ChatSession::create(['user_id' => $customer->id, 'conversation_key' => "stale:{$customer->id}", 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $stale = $customer->fresh();
        User::query()->whereKey($customer->id)->update(['role' => 'vendor']);
        try {
            $service->requestHuman($platform, $stale);
            $this->fail('A stale customer role must be rejected inside the lifecycle lock.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('open', $platform->fresh()->status);

        $owner = User::factory()->create(['role' => 'customer']);
        [, $vendor] = $this->vendor('inactive-transition');
        $vendorSession = ChatSession::create(['user_id' => $owner->id, 'vendor_id' => $vendor->id, 'conversation_key' => "inactive:{$owner->id}:{$vendor->id}", 'target_type' => 'vendor', 'responder_mode' => 'ai', 'status' => 'open']);
        $vendor->update(['status' => 'inactive']);
        try {
            $service->requestHuman($vendorSession, $owner);
            $this->fail('An inactive vendor must reject handoff inside the lifecycle lock.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $vendorSession->update(['responder_mode' => 'human', 'status' => 'resolved', 'assigned_user_id' => User::factory()->create(['role' => 'admin'])->id]);
        try {
            $service->reopen($vendorSession, $owner);
            $this->fail('An inactive vendor must reject reopen inside the lifecycle lock.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_malformed_human_replays_fail_closed_without_writes(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(ChatSessionLifecycleService::class);

        $assigned = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'human', 'status' => 'assigned']);
        $before = $assigned->fresh();
        try {
            $service->customerMessage($assigned, $customer, 'must not write', null);
            $this->fail('Malformed assigned tuple must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame($before->lock_version, $assigned->fresh()->lock_version);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $assigned->id)->count());

        $takeover = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'assigned', 'assigned_user_id' => $admin->id]);
        $before = $takeover->fresh();
        try {
            $service->takeover($takeover, $admin);
            $this->fail('Malformed AI takeover replay must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame($before->lock_version, $takeover->fresh()->lock_version);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $takeover->id)->count());

        $resolved = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'resolved', 'assigned_user_id' => $admin->id]);
        $before = $resolved->fresh();
        try {
            $service->close($resolved, $admin, 'must not replace');
            $this->fail('Malformed resolved replay must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame($before->lock_version, $resolved->fresh()->lock_version);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $resolved->id)->count());
    }

    public function test_reopen_requires_a_canonical_terminal_human_tuple_and_preserves_consent(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(ChatSessionLifecycleService::class);
        $consent = [
            'external_ai_consent_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION,
            'external_ai_consent_scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE,
            'external_ai_consented_at' => now(),
        ];

        foreach ([ChatSession::STATUS_RESOLVED, ChatSession::STATUS_CLOSED] as $status) {
            $terminal = ChatSession::create(array_merge($consent, [
                'user_id' => $customer->id,
                'target_type' => ChatSession::TARGET_PLATFORM,
                'responder_mode' => ChatSession::MODE_HUMAN,
                'status' => $status,
                'assigned_user_id' => $admin->id,
            ]));
            $before = $terminal->fresh();

            $reopened = $service->reopen($terminal, $customer);

            $this->assertSame(ChatSession::STATUS_OPEN, $reopened->status);
            $this->assertSame(ChatSession::MODE_AI, $reopened->responder_mode);
            $this->assertNull($reopened->assigned_user_id);
            $this->assertSame($before->external_ai_consent_version, $reopened->external_ai_consent_version);
            $this->assertSame($before->external_ai_consent_scope, $reopened->external_ai_consent_scope);
            $this->assertNotNull($reopened->external_ai_consented_at);
            $messageCount = ChatMessage::query()->where('chat_session_id', $terminal->id)->count();
            $this->assertSame($reopened->lock_version, $service->reopen($reopened, $customer)->lock_version);
            $this->assertSame($messageCount, ChatMessage::query()->where('chat_session_id', $terminal->id)->count());
        }

        foreach ([
            [ChatSession::STATUS_RESOLVED, ChatSession::MODE_AI, null],
            [ChatSession::STATUS_CLOSED, ChatSession::MODE_AI, $admin->id],
            [ChatSession::STATUS_RESOLVED, ChatSession::MODE_HUMAN, null],
            [ChatSession::STATUS_CLOSED, ChatSession::MODE_HUMAN, null],
            [ChatSession::STATUS_QUEUED, ChatSession::MODE_HUMAN, $admin->id],
        ] as [$status, $mode, $assignedUserId]) {
            $malformed = ChatSession::create([
                'user_id' => $customer->id,
                'target_type' => ChatSession::TARGET_PLATFORM,
                'responder_mode' => $mode,
                'status' => $status,
                'assigned_user_id' => $assignedUserId,
            ]);
            $before = $malformed->fresh();

            try {
                $service->reopen($malformed, $customer);
                $this->fail('Only canonical terminal human tuples may reopen.');
            } catch (HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }

            $this->assertSame($before->lock_version, $malformed->fresh()->lock_version);
            $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $malformed->id)->count());
        }

        $open = ChatSession::create(['user_id' => $customer->id, 'target_type' => ChatSession::TARGET_PLATFORM, 'responder_mode' => ChatSession::MODE_AI, 'status' => ChatSession::STATUS_OPEN]);
        $before = $open->fresh();
        $this->assertSame($open->id, $service->reopen($open, $customer)->id);
        $this->assertSame($before->lock_version, $open->fresh()->lock_version);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $open->id)->count());
    }

    public function test_rejected_image_messages_cleanup_attachments_without_state_or_audit_writes(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'customer']);
        $malformed = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'human', 'status' => 'assigned']);
        $customerBefore = $malformed->fresh();
        $this->actingAs($customer)->post("/api/chat/sessions/{$malformed->id}/messages", ['image' => UploadedFile::fake()->image('rejected-customer.png')])->assertStatus(409);
        Storage::disk('local')->assertDirectoryEmpty("chat-attachments/{$malformed->id}");
        $this->assertSame($customerBefore->lock_version, $malformed->fresh()->lock_version);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $malformed->id)->count());

        $owner = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $assigned = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'human', 'status' => 'assigned', 'assigned_user_id' => $owner->id]);
        $staffBefore = $assigned->fresh();
        $this->actingAs($other)->post("/api/chat/admin/sessions/{$assigned->id}/reply", ['image' => UploadedFile::fake()->image('rejected-staff.png')])->assertForbidden();
        Storage::disk('local')->assertDirectoryEmpty("chat-attachments/{$assigned->id}");
        $this->assertSame($staffBefore->lock_version, $assigned->fresh()->lock_version);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $assigned->id)->count());
    }

    public function test_staff_read_boundary_excludes_open_and_malformed_sessions_without_mutating_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $open = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $malformed = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'human', 'status' => 'queued', 'assigned_user_id' => $admin->id]);
        $queued = ChatSession::create(['user_id' => $customer->id, 'target_type' => 'platform', 'responder_mode' => 'human', 'status' => 'queued']);
        $before = $open->fresh();

        $listed = $this->actingAs($admin)->getJson('/api/chat/admin/sessions')->assertOk()->json('sessions.data');
        $this->assertSame([$queued->id], collect($listed)->pluck('id')->all());
        $this->actingAs($admin)->getJson("/api/chat/admin/sessions/{$open->id}")->assertForbidden();
        $this->actingAs($admin)->getJson("/api/chat/admin/sessions/{$malformed->id}")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/chat/admin/sessions/{$open->id}/takeover")->assertStatus(409);
        $this->assertSame($before->updated_at?->toISOString(), $open->fresh()->updated_at?->toISOString());
        $this->assertSame((int) $before->lock_version, (int) $open->fresh()->lock_version);
        $this->assertSame($before->last_message_at?->toISOString(), $open->fresh()->last_message_at?->toISOString());
    }

    public function test_delayed_ai_reply_is_discarded_after_explicit_handoff_and_returns_current_state(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $session = ChatSession::create(['user_id' => $customer->id, 'conversation_key' => "user:{$customer->id}:platform", 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $service = app(ChatSessionLifecycleService::class);
        [$expected, $generate] = $service->customerMessage($session, $customer, 'Cần trợ giúp', null);
        $this->assertTrue($generate);
        $service->requestHuman($session->fresh(), $customer);
        [$current, $appended] = $service->appendAiReply($expected, 'delayed answer', ['delivery' => 'test']);
        $this->assertFalse($appended);
        $this->assertSame('queued', $current->status);
        $this->assertSame('human', $current->responder_mode);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $session->id)->where('sender_type', 'ai')->count());

        $roleChanged = ChatSession::create(['user_id' => $customer->id, 'conversation_key' => "role:{$customer->id}", 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        [$expected] = $service->customerMessage($roleChanged, $customer, 'still waiting', null);
        User::query()->whereKey($customer->id)->update(['role' => 'vendor']);
        [$current, $appended] = $service->appendAiReply($expected, 'must discard', null);
        $this->assertFalse($appended);
        $this->assertSame($roleChanged->id, $current->id);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $roleChanged->id)->where('sender_type', 'ai')->count());

        $vendorCustomer = User::factory()->create(['role' => 'customer']);
        [, $vendor] = $this->vendor('append-inactive');
        $vendorSession = ChatSession::create(['user_id' => $vendorCustomer->id, 'vendor_id' => $vendor->id, 'conversation_key' => "user:{$vendorCustomer->id}:vendor:{$vendor->id}", 'target_type' => 'vendor', 'responder_mode' => 'ai', 'status' => 'open']);
        [$expected] = $service->customerMessage($vendorSession, $vendorCustomer, 'vendor question', null);
        $vendor->update(['status' => 'inactive']);
        [$current, $appended] = $service->appendAiReply($expected, 'must discard inactive', null);
        $this->assertFalse($appended);
        $this->assertSame($vendorSession->id, $current->id);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $vendorSession->id)->where('sender_type', 'ai')->count());

        $identityCustomer = User::factory()->create(['role' => 'customer']);
        $identity = ChatSession::create(['user_id' => $identityCustomer->id, 'conversation_key' => "identity:{$identityCustomer->id}", 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        [$expected] = $service->customerMessage($identity, $identityCustomer, 'identity question', null);
        $identity->update(['conversation_key' => 'changed-identity']);
        [$current, $appended] = $service->appendAiReply($expected, 'must discard identity', null);
        $this->assertFalse($appended);
        $this->assertSame('changed-identity', $current->conversation_key);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $identity->id)->where('sender_type', 'ai')->count());

        $epochCustomer = User::factory()->create(['role' => 'customer']);
        $epoch = ChatSession::create(['user_id' => $epochCustomer->id, 'conversation_key' => "epoch:{$epochCustomer->id}", 'target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        [$expected] = $service->customerMessage($epoch, $epochCustomer, 'epoch question', null);
        $service->requestHuman($epoch->fresh(), $epochCustomer);
        $service->resumeAi($epoch->fresh(), $epochCustomer);
        [$current, $appended] = $service->appendAiReply($expected, 'must discard old epoch', null);
        $this->assertFalse($appended);
        $this->assertSame('open', $current->status);
        $this->assertSame('ai', $current->responder_mode);
        $this->assertSame(0, ChatMessage::query()->where('chat_session_id', $epoch->id)->where('sender_type', 'ai')->count());
    }

    private function configureGemini(array $overrides = []): void
    {
        $settings = array_merge([
            'enabled' => true,
            'api_key' => 'test-key-not-real',
            'model' => 'gemini-primary',
            'fallback_model' => 'gemini-fallback',
            'allowed_models' => ['gemini-primary', 'gemini-fallback'],
            'max_attempts' => 1,
            'connect_timeout' => 3,
            'timeout' => 12,
            'max_output_tokens' => 1200,
        ], $overrides);

        foreach ($settings as $key => $value) {
            config()->set("services.gemini.{$key}", $value);
        }
    }

    private function providerSession(): ChatSession
    {
        $user = User::factory()->create(['role' => 'customer']);

        return ChatSession::create([
            'user_id' => $user->id,
            'target_type' => 'platform',
            'responder_mode' => 'ai',
            'status' => 'open',
            'external_ai_consent_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION,
            'external_ai_consent_scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE,
            'external_ai_consented_at' => now(),
        ]);
    }

    private function providerReplyForTest(?ChatSession $session = null, string $message = 'current-question', string $context = '[S1] current-context', ?array $attachmentMetadata = null): ?array
    {
        $service = app(GeminiChatService::class);
        $method = new \ReflectionMethod($service, 'providerResult');

        $providerSession = $session ?? $this->providerSession();

        return $method->invoke($service, $providerSession, $message, $this->providerKnowledge($context, $providerSession), $attachmentMetadata);
    }

    /** @return array{match_state: string, entries: list<array<string, string>>} */
    private function providerKnowledge(string $content, ChatSession $session): array
    {
        $scope = ['session_user_id' => $session->user_id, 'scope_target_type' => $session->target_type, 'scope_vendor_id' => $session->vendor_id];

        return trim($content) === '' ? array_merge($scope, ['match_state' => 'matched', 'entries' => []]) : array_merge($scope, ['match_state' => 'matched', 'entries' => [[
            'type' => 'help',
            'citation' => 'S1',
            'title' => 'Nguồn công khai',
            'content' => $content,
        ]]]);
    }

    /** @return array{0: User, 1: Vendor} */
    private function vendor(string $suffix): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'shop_name' => "Shop {$suffix}",
            'slug' => 'shop-'.$suffix.'-'.Str::lower(Str::random(5)),
            'status' => 'active',
        ]);

        return [$user->fresh('vendor'), $vendor];
    }

    private function publishedBook(Vendor $vendor, Category $category, array $overrides = []): Book
    {
        return Book::withoutGlobalScopes()->create(array_merge([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách '.Str::random(6),
            'slug' => 'sach-'.Str::lower(Str::random(8)),
            'author' => 'Tác giả kiểm thử',
            'description' => 'Thông tin sách đang bán.',
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'status' => 'published',
            'publishing_status' => 'published',
        ], $overrides));
    }
}

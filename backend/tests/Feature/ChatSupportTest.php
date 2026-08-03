<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\HelpArticle;
use App\Models\MembershipTier;
use App\Models\Series;
use App\Models\User;
use App\Models\Vendor;
use App\Services\GeminiChatService;
use App\Services\RagSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        Http::fake([
            '*gemini-3.5-flash-lite*' => Http::response(['error' => ['message' => 'quota']], 429),
            '*gemini-3.1-flash-lite*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Phản hồi từ model dự phòng.']]]]],
            ]),
        ]);

        $session = ChatSession::create(['target_type' => 'platform', 'responder_mode' => 'ai', 'status' => 'open']);
        $service = app(GeminiChatService::class);
        $method = new \ReflectionMethod($service, 'providerReply');
        $reply = $method->invoke($service, $session, 'Cần hỗ trợ', '[S1] Nguồn kiểm thử');

        $this->assertSame('Phản hồi từ model dự phòng.', $reply);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-3.5-flash-lite')
            && ! str_contains($request->url(), 'test-key-not-real'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-3.1-flash-lite'));
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

        $message = collect($response->json('session.messages'))->last();
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

    public function test_customer_reply_after_thirty_minutes_automatically_returns_to_ai(): void
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
            ->assertJsonPath('session.status', 'open')
            ->assertJsonPath('session.responder_mode', 'ai')
            ->assertJsonPath('session.assigned_user', null);

        $messages = collect($response->json('session.messages'));
        $this->assertTrue($messages->contains(fn (array $message) => data_get($message, 'metadata.event') === 'ai_auto_resumed'));
        $this->assertSame('ai', $messages->last()['sender_type']);
    }

    public function test_scheduler_returns_all_idle_platform_and_vendor_conversations_to_ai_without_customer_message(): void
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

        Artisan::call('chat:resume-idle-ai');

        $sessions->each(function (ChatSession $session): void {
            $session->refresh();
            $this->assertSame('ai', $session->responder_mode);
            $this->assertSame('open', $session->status);
            $this->assertNull($session->assigned_user_id);
            $this->assertDatabaseHas('chat_messages', [
                'chat_session_id' => $session->id,
                'sender_type' => 'system',
            ]);
        });
        $this->assertStringContainsString('Đã chuyển 2 phiên', Artisan::output());
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

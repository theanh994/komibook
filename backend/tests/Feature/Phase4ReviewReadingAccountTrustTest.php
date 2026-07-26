<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookChapter;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase4ReviewReadingAccountTrustTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $this->vendor = Vendor::create([
            'user_id' => $vendorUser->id, 'shop_name' => 'Phase 4A.2 Shop',
            'slug' => 'phase-4a2-shop', 'status' => 'active',
        ]);
        $category = Category::create(['name' => 'Phase 4A.2', 'slug' => 'phase-4a2']);
        $this->book = Book::create([
            'vendor_id' => $this->vendor->id, 'category_id' => $category->id,
            'title' => 'Trust Book', 'slug' => 'trust-book', 'author' => 'Komi',
            'type' => 'ebook', 'price' => 100000, 'pages' => 100,
            'file_path' => 'ebooks/trust.pdf', 'status' => 'published',
        ]);
    }

    public function test_review_requires_a_completed_paid_purchase(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson("/api/books/{$this->book->id}/reviews", [
            'rating' => 5, 'comment' => 'Chưa mua',
        ])->assertStatus(422);
    }

    public function test_review_is_create_or_update_with_one_active_row(): void
    {
        $user = User::factory()->create();
        $this->completePurchase($user);

        $this->actingAs($user)->postJson("/api/books/{$this->book->id}/reviews", [
            'rating' => 4, 'comment' => 'Lần đầu',
        ])->assertCreated()->assertJsonPath('data.verified_purchase', true);
        $this->actingAs($user)->postJson("/api/books/{$this->book->id}/reviews", [
            'rating' => 5, 'comment' => 'Đã sửa',
        ])->assertOk()->assertJsonPath('data.comment', 'Đã sửa');

        $this->assertSame(1, Review::where('user_id', $user->id)->where('book_id', $this->book->id)->where('active_key', 1)->count());
    }

    public function test_report_and_admin_moderation_are_audited(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->completePurchase($owner);
        $reviewId = $this->actingAs($owner)->postJson("/api/books/{$this->book->id}/reviews", [
            'rating' => 1, 'comment' => 'Nội dung cần kiểm tra',
        ])->json('data.id');

        $this->actingAs($reporter)->postJson("/api/reviews/{$reviewId}/reports", ['reason' => 'abuse'])->assertCreated();
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])
            ->patchJson("/api/admin/reviews/{$reviewId}/moderate", [
                'status' => 'hidden', 'reason' => 'Vi phạm', 'operation_key' => 'moderate-review-'.$reviewId,
            ])->assertOk()->assertJsonPath('data.review.moderation_status', 'hidden');

        $this->assertDatabaseHas('review_moderation_events', ['review_id' => $reviewId, 'to_status' => 'hidden']);
        $this->assertDatabaseHas('review_reports', ['review_id' => $reviewId, 'status' => 'resolved']);
    }

    public function test_annotation_rejects_a_chapter_from_another_book(): void
    {
        $user = User::factory()->create();
        $this->completePurchase($user);
        $otherBook = $this->book->replicate(['slug']);
        $otherBook->slug = 'other-book';
        $otherBook->title = 'Other Book';
        $otherBook->save();
        $chapter = BookChapter::create(['book_id' => $otherBook->id, 'title' => 'Wrong chapter', 'order' => 1, 'status' => 'published']);

        $this->actingAs($user)->postJson('/api/annotations', [
            'book_id' => $this->book->id, 'book_chapter_id' => $chapter->id, 'type' => 'bookmark',
        ])->assertStatus(422)->assertJsonPath('message', 'Chương không thuộc ebook này.');
    }

    public function test_reading_progress_sync_detects_stale_device_version(): void
    {
        $user = User::factory()->create();
        $this->completePurchase($user);

        $first = $this->actingAs($user)->putJson("/api/books/{$this->book->id}/reading-progress", [
            'current_page' => 20, 'total_pages' => 100,
        ])->assertOk();
        $version = $first->json('data.version');
        $this->actingAs($user)->putJson("/api/books/{$this->book->id}/reading-progress", [
            'current_page' => 25, 'total_pages' => 100, 'version' => $version,
        ])->assertOk();
        $this->actingAs($user)->putJson("/api/books/{$this->book->id}/reading-progress", [
            'current_page' => 30, 'total_pages' => 100, 'version' => $version,
        ])->assertStatus(409);
    }

    public function test_sensitive_action_requires_verified_email_and_recent_authentication(): void
    {
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])
            ->deleteJson('/api/profile/sessions/unknown')->assertStatus(403)
            ->assertJsonPath('code', 'EMAIL_VERIFICATION_REQUIRED');

        $verified = User::factory()->create();
        $this->actingAs($verified)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => 0])
            ->deleteJson('/api/profile/sessions/unknown')->assertStatus(423)
            ->assertJsonPath('code', 'RECENT_AUTHENTICATION_REQUIRED');
    }

    public function test_password_change_revokes_other_database_sessions_but_keeps_current(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $currentId = 'current-session';
        foreach ([$currentId, 'other-session'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit', 'payload' => '', 'last_activity' => time(),
            ]);
        }

        $this->actingAs($user)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()]);
        session()->setId($currentId);
        $this->putJson('/api/profile/password', [
            'current_password' => 'old-password', 'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_password_confirmation_unlocks_a_sensitive_action_for_fifteen_minutes(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->actingAs($user)->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => 0]);

        $this->postJson('/api/auth/confirm-password', ['current_password' => 'wrong-password'])->assertStatus(422);
        $this->postJson('/api/auth/confirm-password', ['current_password' => 'correct-password'])->assertOk();
        $this->deleteJson('/api/profile/sessions/unknown')->assertNotFound();
    }

    private function completePurchase(User $user): Order
    {
        $order = Order::create([
            'user_id' => $user->id, 'vendor_id' => $this->vendor->id,
            'total_amount' => 100000, 'status' => 'completed', 'payment_status' => 'paid',
            'payment_method' => 'online', 'shipping_address' => 'KomiBook', 'phone' => '0900000000',
        ]);
        OrderItem::create(['order_id' => $order->id, 'book_id' => $this->book->id, 'quantity' => 1, 'price' => 100000]);

        return $order;
    }
}

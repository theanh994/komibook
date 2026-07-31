<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\ArticleCommentEvent;
use App\Models\ArticleSubmission;
use App\Models\Book;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleBook;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\VendorFlashSaleRequest;
use App\Models\VendorFollow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase11NewsroomTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_newsroom_is_tenant_scoped_and_requires_admin_approval(): void
    {
        [$vendorUserA, $vendorA] = $this->vendor('news-a@example.test', 'news-a');
        [$vendorUserB] = $this->vendor('news-b@example.test', 'news-b');
        $category = Category::create(['name' => 'Tin sách', 'slug' => 'tin-sach']);
        $book = $this->book($vendorA, $category);

        $created = $this->actingAs($vendorUserA)->postJson('/api/vendor/articles', [
            'title' => 'Bản tin của gian hàng A',
            'body' => '<p>Nội dung đáng tin cậy.</p><script>alert(1)</script>',
            'article_type' => 'vendor_announcement',
            'book_ids' => [$book->id],
            'tags' => ['Ra mắt'],
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $article = Article::findOrFail($created->json('data.id'));
        $this->assertSame($vendorA->id, $article->vendor_id);
        $this->assertStringNotContainsString('<script', $article->body);
        $this->actingAs($vendorUserB)->getJson("/api/vendor/articles/{$article->id}")->assertNotFound();
        $this->actingAs($vendorUserA)->postJson("/api/vendor/articles/{$article->id}/submit", [
            'operation_key' => 'phase11-vendor-submit',
        ])->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->getJson("/api/articles/{$article->slug}")->assertNotFound();

        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([['under_review', 'review'], ['approved', 'approve'], ['published', 'publish']] as [$status, $key]) {
            $this->actingAs($admin)->patchJson("/api/admin/articles/{$article->id}/transition", [
                'to_status' => $status,
                'operation_key' => "phase11-{$key}",
            ])->assertOk();
        }
        $this->getJson("/api/articles/{$article->slug}")
            ->assertOk()
            ->assertJsonPath('data.vendor.shop_name', $vendorA->shop_name)
            ->assertJsonPath('data.article_type', 'vendor_announcement');
    }

    public function test_comments_are_fail_closed_until_moderated_and_email_is_never_exposed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = Article::create([
            'created_by' => $admin->id,
            'title' => 'Bài viết có bình luận',
            'slug' => 'bai-viet-co-binh-luan',
            'body' => '<p>Nội dung</p>',
            'status' => 'published',
            'allow_comments' => true,
            'published_at' => now(),
        ]);

        $response = $this->postJson("/api/articles/{$article->slug}/comments", [
            'guest_name' => 'Độc giả',
            'guest_email' => 'reader@example.test',
            'body' => 'Một bình luận hữu ích.',
        ])->assertCreated();
        $this->assertStringNotContainsString('reader@example.test', $response->getContent());
        $comment = ArticleComment::findOrFail($response->json('data.id'));
        $this->assertSame('pending', $comment->status);
        $this->getJson("/api/articles/{$article->slug}")->assertJsonCount(0, 'data.comments');

        $this->actingAs($admin)->patchJson("/api/admin/article-comments/{$comment->id}", [
            'status' => 'approved',
            'operation_key' => 'phase11-comment-approve',
        ])->assertOk();
        $this->assertSame(1, ArticleCommentEvent::where('article_comment_id', $comment->id)->count());
        $this->getJson("/api/articles/{$article->slug}")
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonMissingPath('data.comments.0.guest_email_hash');
    }

    public function test_customer_review_submission_converts_to_draft_instead_of_publishing(): void
    {
        [$vendor, $vendorProfile] = $this->vendor('review-vendor@example.test', 'review-vendor');
        $category = Category::create(['name' => 'Review', 'slug' => 'review']);
        $book = $this->book($vendorProfile, $category);
        $customer = User::factory()->create(['role' => 'customer']);

        $created = $this->actingAs($customer)->postJson('/api/article-submissions', [
            'book_id' => $book->id,
            'title' => 'Một góc nhìn đáng suy ngẫm về cuốn sách',
            'body' => str_repeat('Nội dung review có phân tích và dẫn chứng. ', 40),
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
        $submission = ArticleSubmission::findOrFail($created->json('data.id'));

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->patchJson("/api/admin/article-submissions/{$submission->id}", [
            'action' => 'convert',
        ])->assertOk()->assertJsonPath('data.status', 'converted');
        $article = Article::findOrFail($response->json('data.converted_article_id'));
        $this->assertSame('draft', $article->status->value);
        $this->assertTrue($article->books()->whereKey($book->id)->exists());
        $this->getJson("/api/articles/{$article->slug}")->assertNotFound();
    }

    public function test_vendor_can_request_flash_sale_anytime_and_create_tenant_scoped_coupon(): void
    {
        [$vendorUser, $vendor] = $this->vendor('promotion-vendor@example.test', 'promotion-vendor');
        [$otherVendorUser] = $this->vendor('other-promotion@example.test', 'other-promotion');
        $category = Category::create(['name' => 'Khuyến mãi', 'slug' => 'khuyen-mai']);
        $book = $this->book($vendor, $category);

        $this->actingAs($vendorUser)->postJson('/api/vendor/flash-sale-requests', [
            'title' => 'Đề xuất Flash Sale độc lập',
            'preferred_start_time' => now()->addDays(3)->toISOString(),
            'preferred_end_time' => now()->addDays(5)->toISOString(),
            'groups' => [[
                'book_ids' => [$book->id],
                'discount_percent' => 20,
                'max_quantity' => 5,
            ]],
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertSame($vendor->id, VendorFlashSaleRequest::firstOrFail()->vendor_id);
        $promotionRequest = VendorFlashSaleRequest::firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);
        $follower = User::factory()->create(['role' => 'customer']);
        VendorFollow::create(['user_id' => $follower->id, 'vendor_id' => $vendor->id]);
        $this->actingAs($admin)->getJson('/api/admin/flash-sale-requests')
            ->assertOk()
            ->assertJsonPath('data.0.vendor.shop_name', $vendor->shop_name);
        $this->actingAs($admin)->patchJson("/api/admin/flash-sale-requests/{$promotionRequest->id}", [
            'status' => 'approved',
        ])->assertOk()->assertJsonPath('data.status', 'approved');
        $campaign = FlashSale::firstOrFail();
        $this->assertSame('enrollment_open', $campaign->status);
        $this->assertTrue(FlashSaleBook::where('flash_sale_id', $campaign->id)->where('book_id', $book->id)->where('status', 'approved')->exists());
        $this->assertTrue(UserNotification::where('user_id', $vendorUser->id)->where('data->flash_sale_id', $campaign->id)->exists());
        $this->assertTrue(UserNotification::where('user_id', $follower->id)->where('data->flash_sale_id', $campaign->id)->exists());

        $couponResponse = $this->actingAs($vendorUser)->postJson('/api/vendor/coupons', [
            'code' => 'VENDOR20',
            'discount_percent' => 20,
            'min_order_value' => 100000,
            'max_discount_amount' => 50000,
            'start_time' => now()->addDay()->toISOString(),
            'end_time' => now()->addWeek()->toISOString(),
            'usage_limit' => 100,
            'scope_type' => 'books',
            'scope_book_ids' => [$book->id],
            'stacking_policy' => 'deny',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
        $coupon = Coupon::findOrFail($couponResponse->json('data.id'));
        $this->assertSame($vendor->id, $coupon->vendor_id);
        $this->actingAs($admin)->putJson("/api/admin/coupons/{$coupon->id}", [
            ...$coupon->only([
                'code', 'discount_percent', 'min_order_value', 'max_discount_amount',
                'start_time', 'end_time', 'usage_limit',
            ]),
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.status', 'active');
        $this->actingAs($otherVendorUser)->patchJson("/api/vendor/coupons/{$coupon->id}", [
            'code' => 'VENDOR20',
            'discount_percent' => 20,
            'start_time' => now()->addDay()->toISOString(),
            'end_time' => now()->addWeek()->toISOString(),
            'stacking_policy' => 'deny',
        ])->assertNotFound();
    }

    public function test_customer_can_open_follow_and_unfollow_a_verified_storefront(): void
    {
        [$vendorUser, $vendor] = $this->vendor('storefront-vendor@example.test', 'storefront-vendor');
        $category = Category::create(['name' => 'Gian hàng', 'slug' => 'gian-hang']);
        $book = $this->book($vendor, $category);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->getJson("/api/vendors/{$vendor->slug}")
            ->assertOk()
            ->assertJsonPath('data.vendor.shop_name', $vendor->shop_name)
            ->assertJsonPath('data.books.0.id', $book->id)
            ->assertJsonPath('data.vendor.followers_count', 0);

        $this->actingAs($customer)->postJson("/api/vendors/{$vendor->id}/follow")
            ->assertOk()
            ->assertJsonPath('following', true)
            ->assertJsonPath('followers_count', 1);
        $this->assertTrue(VendorFollow::where('user_id', $customer->id)->where('vendor_id', $vendor->id)->exists());

        $this->actingAs($customer)->getJson("/api/vendors/{$vendor->id}/follow")
            ->assertOk()
            ->assertJsonPath('following', true)
            ->assertJsonPath('available', true);

        $this->actingAs($customer)->postJson("/api/vendors/{$vendor->id}/follow")
            ->assertOk()
            ->assertJsonPath('following', false)
            ->assertJsonPath('followers_count', 0);
        $this->actingAs($vendorUser)->postJson("/api/vendors/{$vendor->id}/follow")
            ->assertUnprocessable();
    }

    private function vendor(string $email, string $slug): array
    {
        $user = User::factory()->create(['role' => 'vendor', 'email' => $email]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => "Gian hàng {$slug}",
            'slug' => $slug,
            'status' => 'active',
            'onboarding_status' => 'approved',
        ]);

        return [$user, $vendor];
    }

    private function book(Vendor $vendor, Category $category): Book
    {
        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách Newsroom',
            'slug' => 'sach-newsroom-'.$vendor->id,
            'author' => 'KomiBook',
            'price' => 85000,
            'stock' => 10,
            'type' => 'physical',
            'status' => 'published',
        ]);
    }
}

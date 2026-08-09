<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\SellerFulfillmentAddress;
use App\Models\UsedBookListing;
use App\Models\User;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase7UsedBookAdminModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_used_book_submission_starts_as_pending_and_can_be_approved_or_rejected_by_admin(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => 'customer']);
        SellerFulfillmentAddress::create([
            'user_id' => $seller->id,
            'recipient_name' => 'Người bán',
            'phone' => '0900000000',
            'address_line' => 'Địa chỉ gửi',
            'province' => 'Hà Nội',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Sách cũ', 'slug' => 'sach-cu-mod']);

        // 1. Seller submits a used book listing
        $created = $this->actingAs($seller)->postJson('/api/used-book-seller/listings', [
            'title' => 'Komi Nữ thần sợ giao tiếp Tập 31',
            'author_name' => 'Tomohito Oda',
            'category_id' => $category->id,
            'price' => 30000,
            'condition' => 'like_new',
            'defects' => 'Như mới',
            'quantity' => 1,
            'actual_photos' => [UploadedFile::fake()->image('komi.jpg')],
            'authenticity_attested' => true,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $listingId = $created->json('data.id');
        $bookId = $created->json('data.book.id');

        // 2. Public buyer should NOT see pending book on public catalog/home
        $publicBooks = $this->getJson('/api/books')->json('data');
        $this->assertEmpty(collect($publicBooks)->where('id', $bookId));

        // 3. Admin views pending listings
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/admin/used-book-listings?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $listingId)
            ->assertJsonPath('data.0.status', 'pending');

        // 4. Admin approves the listing
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listingId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertSame('published', Book::withoutGlobalScopes()->find($bookId)->status);

        // 5. Public buyer should now see approved book
        $this->getJson('/api/books')
            ->assertOk()
            ->assertJsonPath('data.0.id', $bookId);

        // 6. Admin can reject a listing with reason
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listingId}/reject", [
            'rejection_reason' => 'Ảnh mờ không rõ tình trạng.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Ảnh mờ không rõ tình trạng.');

        $this->assertSame('archived', Book::withoutGlobalScopes()->find($bookId)->status);
    }

    public function test_zero_quantity_approval_remains_sold_out_and_is_not_public(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => 'customer']);
        SellerFulfillmentAddress::create(['user_id' => $seller->id, 'recipient_name' => 'Seller', 'phone' => '0900000000', 'address_line' => 'Verified', 'province' => 'Hue', 'status' => 'verified', 'verified_at' => now()]);
        $category = Category::create(['name' => 'Zero', 'slug' => 'zero-used']);
        $response = $this->actingAs($seller)->postJson('/api/used-book-seller/listings', [
            'title' => 'Zero used book', 'author_name' => 'Author', 'category_id' => $category->id, 'price' => 20000,
            'condition' => 'good', 'quantity' => 1, 'actual_photos' => [UploadedFile::fake()->image('zero.jpg')], 'authenticity_attested' => true,
        ])->assertCreated();
        $listing = UsedBookListing::where('book_id', $response->json('data.book.id'))->firstOrFail();
        WarehouseStock::where('book_id', $listing->book_id)->update(['quantity' => 0]);
        $listing->update(['quantity_available' => 0]);
        Book::withoutGlobalScopes()->whereKey($listing->book_id)->update(['stock' => 0]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listing->id}/approve")->assertOk()->assertJsonPath('data.status', 'sold_out');
        $this->assertSame(0, (int) $listing->fresh()->quantity_available);
        $this->assertSame(0, (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock);
        $this->assertSame(0, (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'));
        $this->assertEmpty(collect($this->getJson('/api/books')->json('data'))->where('id', $listing->book_id));
    }
}

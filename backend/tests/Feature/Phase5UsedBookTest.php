<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\SellerFulfillmentAddress;
use App\Models\UsedBookListing;
use App\Models\UsedBookSellerProfile;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase5UsedBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_lists_used_book_at_verified_private_address_and_customer_can_open_counterfeit_dispute(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $seller = User::factory()->create(['role' => 'customer']);
        $address = SellerFulfillmentAddress::create([
            'user_id' => $seller->id, 'recipient_name' => 'Người gửi', 'phone' => '0900000000',
            'address_line' => 'Địa chỉ bí mật', 'province' => 'Hà Nội', 'status' => 'verified', 'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Sách cũ', 'slug' => 'sach-cu']);

        $response = $this->actingAs($seller)->postJson('/api/used-book-seller/listings', [
            'title' => 'Sách đã đọc một lần', 'author_name' => 'Người viết trên bìa', 'category_id' => $category->id,
            'price' => 30000, 'condition' => 'good', 'defects' => 'Xước nhẹ ở gáy', 'quantity' => 1,
            'actual_photos' => [UploadedFile::fake()->image('actual.jpg')],
            'authenticity_attested' => true,
        ])->assertCreated()
            ->assertJsonPath('data.book.provenance', 'used_resale')
            ->assertJsonMissing(['seller_fulfillment_address_id' => $address->id])
            ->assertJsonMissing(['address_line' => 'Địa chỉ bí mật']);

        $bookId = $response->json('data.book.id');
        $listing = UsedBookListing::where('book_id', $bookId)->firstOrFail();
        $listing->update(['status' => 'active']);
        Book::withoutGlobalScopes()->whereKey($bookId)->update(['status' => 'published', 'publishing_status' => 'published']);
        $buyer = User::factory()->create(['role' => 'customer']);
        $order = Order::withoutGlobalScopes()->create([
            'order_code' => 'ORD-USED-DISPUTE', 'user_id' => $buyer->id,
            'vendor_id' => Book::withoutGlobalScopes()->findOrFail($bookId)->vendor_id,
            'total_amount' => 30000, 'status' => 'completed', 'payment_status' => 'paid',
            'payment_method' => 'online', 'shipping_address' => 'Buyer address', 'phone' => '0911111111',
        ]);
        $item = $order->orderItems()->create([
            'book_id' => $bookId, 'quantity' => 1, 'price' => 30000,
            'product_taxonomy_snapshot' => ['format' => 'physical', 'provenance' => 'used_resale'],
            'return_policy_snapshot' => ['is_returnable' => true, 'return_window_days' => 7],
        ]);

        $dispute = $this->actingAs($buyer)->postJson('/api/used-books/disputes', [
            'order_item_id' => $item->id, 'type' => 'counterfeit',
            'description' => 'Tôi nghi ngờ cuốn sách này là hàng giả do chất lượng in.',
            'evidence' => [UploadedFile::fake()->image('evidence.jpg')],
        ])->assertCreated()
            ->assertJsonPath('data.hold_status', 'active')
            ->json('data.id');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson("/api/admin/used-books/disputes/{$dispute}/resolve", [
            'decision' => 'confirmed', 'resolution' => 'Bằng chứng xác nhận hàng giả.',
            'sanction' => 'suspend_listing',
        ])->assertOk()->assertJsonPath('data.hold_status', 'consumed');

        $this->assertSame('suspended', $listing->fresh()->status);
    }

    public function test_seller_read_endpoints_never_provision_or_switch_warehouse(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $before = [Vendor::count(), UsedBookSellerProfile::count(), Warehouse::count(), WarehouseStock::count()];
        $this->actingAs($user)->getJson('/api/used-book-seller/listings')->assertForbidden();
        $this->actingAs($user)->getJson('/api/used-book-seller/orders')->assertForbidden();
        $this->assertSame($before, [Vendor::count(), UsedBookSellerProfile::count(), Warehouse::count(), WarehouseStock::count()]);
    }
}

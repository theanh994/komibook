<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_real_related_user_information_for_detail_and_print_views(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer', 'phone' => '0900000000']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Nhà bán kiểm thử',
            'slug' => 'nha-ban-kiem-thu',
            'status' => 'active',
        ]);
        $category = Category::create(['name' => 'Danh mục kiểm thử', 'slug' => 'danh-muc-kiem-thu']);
        $book = Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách kiểm thử hồ sơ',
            'slug' => 'sach-kiem-thu-ho-so',
            'author' => 'KomiBook',
            'price' => 120000,
            'stock' => 1,
            'type' => 'physical',
            'status' => 'published',
        ]);
        $order = Order::create([
            'order_code' => 'ORD-USER-DETAIL',
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 120000,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'shipping_address' => 'Hà Nội',
            'phone' => '0900000000',
        ]);
        OrderItem::create(['order_id' => $order->id, 'book_id' => $book->id, 'quantity' => 1, 'price' => 120000]);
        Review::create(['user_id' => $customer->id, 'book_id' => $book->id, 'rating' => 5, 'comment' => 'Tốt']);
        DB::table('wishlists')->insert(['user_id' => $customer->id, 'book_id' => $book->id, 'created_at' => now(), 'updated_at' => now()]);
        UserAddress::create(['user_id' => $customer->id, 'receiver_name' => 'Người nhận', 'phone' => '0900000000', 'address' => 'Hà Nội', 'is_default' => true]);

        $this->actingAs($admin)->getJson("/api/admin/users/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.completed_orders', 1)
            ->assertJsonPath('data.total_spent', 120000)
            ->assertJsonPath('data.purchased_books_count', 1)
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.wishlist_count', 1)
            ->assertJsonPath('data.orders.0.order_code', 'ORD-USER-DETAIL')
            ->assertJsonPath('data.orders.0.vendor.shop_name', 'Nhà bán kiểm thử')
            ->assertJsonPath('data.addresses.0.is_default', true);

        $this->actingAs($customer)->getJson("/api/admin/users/{$customer->id}")->assertForbidden();
    }
}

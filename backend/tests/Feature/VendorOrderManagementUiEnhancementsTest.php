<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOrderManagementUiEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_filter_orders_by_search_keyword_and_status(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'NXB Tuổi Trẻ',
            'slug' => 'nxb-tuoi-tre',
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Văn học', 'slug' => 'van-hoc']);
        $book = Book::create([
            'vendor_id' => $vendor->id,
            'title' => 'Dế Mèn Phiêu Lưu Ký',
            'slug' => 'de-men-phieu-luu-ky',
            'author' => 'Tô Hoài',
            'category_id' => $category->id,
            'price' => 50000,
            'stock' => 50,
            'type' => 'physical',
            'status' => 'published',
        ]);

        $customer = User::factory()->create(['name' => 'Nguyễn Thị Bích']);

        $order1 = Order::create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'order_code' => 'ORD-SEARCH-001',
            'total_amount' => 50000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
            'shipping_address' => 'Hà Nội',
            'phone' => '0912345678',
        ]);

        $order2 = Order::create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'order_code' => 'ORD-SEARCH-002',
            'total_amount' => 100000,
            'status' => 'shipped',
            'payment_status' => 'paid',
            'payment_method' => 'vnpay',
            'shipping_address' => 'Đà Nẵng',
            'phone' => '0912345679',
        ]);

        // Search by order_code
        $res1 = $this->actingAs($vendorUser)->getJson('/api/vendor/orders?search=SEARCH-001');
        $res1->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_code', 'ORD-SEARCH-001');

        // Filter by status
        $res2 = $this->actingAs($vendorUser)->getJson('/api/vendor/orders?status=shipped');
        $res2->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_code', 'ORD-SEARCH-002');
    }

    public function test_vendor_can_bulk_update_orders_to_shipped(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'NXB Kim Đồng',
            'slug' => 'nxb-kim-dong-2',
            'status' => 'active',
        ]);

        $customer = User::factory()->create();

        $order1 = Order::create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'order_code' => 'ORD-BULK-001',
            'total_amount' => 50000,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'vnpay',
            'shipping_address' => 'Hà Nội',
            'phone' => '0987654321',
        ]);

        $order2 = Order::create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'order_code' => 'ORD-BULK-002',
            'total_amount' => 75000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'vnpay',
            'shipping_address' => 'Hồ Chí Minh',
            'phone' => '0987654322',
        ]);

        $res = $this->actingAs($vendorUser)->patchJson('/api/vendor/orders/bulk-status', [
            'order_ids' => [$order1->id, $order2->id],
            'status' => 'shipped',
        ]);

        $res->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order1->id, 'status' => 'shipped']);
        $this->assertDatabaseHas('orders', ['id' => $order2->id, 'status' => 'shipped']);
    }
}

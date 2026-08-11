<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\InvoiceSnapshot;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOrderManagementEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_order_detail_resource_returns_exact_book_info_and_financial_breakdown(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $customer = User::factory()->create(['role' => 'customer']);

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'shop_name' => 'Gian hàng Sách Kim Đồng',
            'slug' => 'nxb-kim-dong',
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Truyện tranh', 'slug' => 'truyen-tranh']);
        $book = Book::create([
            'vendor_id' => $vendor->id,
            'title' => 'Komi - Nữ Thần Sợ Giao Tiếp - Tập 16',
            'slug' => 'komibook-16',
            'author' => 'Tomohito Oda',
            'category_id' => $category->id,
            'price' => 30000,
            'stock' => 100,
            'type' => 'physical',
            'status' => 'published',
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'order_code' => 'ORD-20260806-001',
            'total_amount' => 45000,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'vnpay',
            'shipping_address' => '123 Đường ABC, Hà Nội',
            'phone' => '0987654321',
        ]);

        $order->orderItems()->create([
            'book_id' => $book->id,
            'quantity' => 2,
            'price' => 30000,
        ]);

        InvoiceSnapshot::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-ORD-20260806-001',
            'issued_at' => now(),
            'buyer_snapshot' => ['name' => 'Nguyễn Văn A', 'phone' => '0987654321'],
            'seller_snapshot' => ['shop_name' => 'Gian hàng Sách Kim Đồng'],
            'line_items' => [
                ['order_item_id' => 1, 'title' => 'Komi - Nữ Thần Sợ Giao Tiếp - Tập 16', 'quantity' => 2, 'unit_price' => 30000],
            ],
            'subtotal_amount' => 60000,
            'shipping_fee_amount' => 15000,
            'coupon_discount_amount' => 30000,
            'membership_discount_amount' => 0,
            'total_amount' => 45000,
        ]);

        $response = $this->actingAs($user)->getJson("/api/vendor/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.order_code', 'ORD-20260806-001')
            ->assertJsonPath('data.subtotal_amount', 60000)
            ->assertJsonPath('data.shipping_fee_amount', 15000)
            ->assertJsonPath('data.coupon_discount_amount', 30000)
            ->assertJsonPath('data.discount_amount', 30000)
            ->assertJsonPath('data.total_amount', 45000)
            ->assertJsonPath('data.items.0.book.title', 'Komi - Nữ Thần Sợ Giao Tiếp - Tập 16')
            ->assertJsonPath('data.items.0.book.author', 'Tomohito Oda')
            ->assertJsonPath('data.items.0.book.is_physical', true);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7VendorCoreParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_analytics_books_and_orders_are_tenant_scoped_and_use_real_columns(): void
    {
        [$vendorUserA, $vendorA] = $this->vendor('vendor-a@example.test', 'vendor-a');
        [$vendorUserB, $vendorB] = $this->vendor('vendor-b@example.test', 'vendor-b');
        $customer = User::factory()->create(['role' => 'customer']);
        $category = Category::create(['name' => 'Vendor parity', 'slug' => 'vendor-parity']);
        $bookA = $this->book($vendorA, $category, 'Sách tenant A', 'tenant-a', '/storage/books/a.jpg');
        $bookB = $this->book($vendorB, $category, 'Sách tenant B', 'tenant-b', '/storage/books/b.jpg');
        $orderA = $this->order($customer, $vendorA, 'P7-VENDOR-A', 140000);
        $orderB = $this->order($customer, $vendorB, 'P7-VENDOR-B', 999000);
        OrderItem::create(['order_id' => $orderA->id, 'book_id' => $bookA->id, 'quantity' => 2, 'price' => 70000]);
        OrderItem::create(['order_id' => $orderB->id, 'book_id' => $bookB->id, 'quantity' => 1, 'price' => 999000]);

        $this->actingAs($vendorUserA)->getJson('/api/vendor/dashboard-stats')
            ->assertOk()
            ->assertJsonPath('data.total_revenue', 140000)
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.total_books', 1)
            ->assertJsonPath('data.recent_orders.0.id', $orderA->id)
            ->assertJsonMissing(['order_code' => 'P7-VENDOR-B']);

        $this->actingAs($vendorUserA)->getJson('/api/vendor/analytics')
            ->assertOk()
            ->assertJsonPath('data.top_selling_books.0.id', $bookA->id)
            ->assertJsonPath('data.top_selling_books.0.total_sold', 2)
            ->assertJsonPath('data.top_selling_books.0.total_revenue', 140000)
            ->assertJsonPath('data.top_selling_books.0.cover_image', '/storage/books/a.jpg')
            ->assertJsonMissing(['id' => $bookB->id, 'title' => 'Sách tenant B']);

        $this->actingAs($vendorUserA)->getJson('/api/vendor/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $orderA->id)
            ->assertJsonPath('data.0.items.0.book.cover_image', '/storage/books/a.jpg')
            ->assertJsonMissing(['order_code' => 'P7-VENDOR-B']);

        $this->actingAs($vendorUserA)->getJson('/api/vendor/books?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.id', $bookA->id)
            ->assertJsonMissing(['id' => $bookB->id, 'title' => 'Sách tenant B']);

        $this->actingAs($vendorUserA)->getJson("/api/vendor/orders/{$orderB->id}")->assertNotFound();
        $this->actingAs($vendorUserA)->getJson("/api/vendor/books/{$bookB->id}")->assertNotFound();
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

    private function book(Vendor $vendor, Category $category, string $title, string $slug, string $cover): Book
    {
        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'author' => 'KomiBook',
            'cover_image' => $cover,
            'price' => 70000,
            'stock' => 10,
            'type' => 'physical',
            'status' => 'published',
        ]);
    }

    private function order(User $customer, Vendor $vendor, string $code, int $total): Order
    {
        return Order::withoutGlobalScopes()->create([
            'order_code' => $code,
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total_amount' => $total,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'online',
            'shipping_address' => 'Địa chỉ kiểm thử',
            'phone' => '0900000000',
        ]);
    }
}

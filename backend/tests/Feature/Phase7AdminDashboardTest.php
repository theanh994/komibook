<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\UsedBookSellerProfile;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_returns_real_portable_trends_distributions_and_queues(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $sellerUser = User::factory()->create(['role' => 'customer']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Dashboard Shop',
            'slug' => 'dashboard-shop',
            'status' => 'inactive',
        ]);
        UsedBookSellerProfile::create([
            'user_id' => $sellerUser->id,
            'catalog_vendor_id' => $vendor->id,
            'status' => 'active',
            'capabilities' => ['used_resale'],
            'activated_at' => now(),
        ]);
        $category = Category::create(['name' => 'Dashboard', 'slug' => 'dashboard']);
        $this->book($vendor, $category, 'dashboard-physical', 'physical', 'draft');
        $this->book($vendor, $category, 'dashboard-ebook', 'ebook', 'published');

        $completed = $this->order($customer, $vendor, 'completed', 125000);
        $completed->forceFill(['created_at' => now()->startOfMonth()->addDay()])->saveQuietly();
        $pending = $this->order($customer, $vendor, 'pending', 80000);
        $pending->forceFill(['created_at' => now()->subMonth()->startOfMonth()->addDay()])->saveQuietly();

        $response = $this->actingAs($admin)->getJson('/api/admin/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_users', 4)
            ->assertJsonPath('data.total_used_book_sellers', 1)
            ->assertJsonPath('data.total_vendors', 1)
            ->assertJsonPath('data.total_books', 2)
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.total_revenue', 125000)
            ->assertJsonPath('data.commerce_trend.orders.5', 1)
            ->assertJsonPath('data.commerce_trend.revenue.5', 125000)
            ->assertJsonPath('data.book_distribution.by_type.physical', 1)
            ->assertJsonPath('data.book_distribution.by_type.ebook', 1)
            ->assertJsonPath('data.book_distribution.by_status.draft', 1)
            ->assertJsonPath('data.order_status_distribution.pending', 1)
            ->assertJsonPath('data.order_status_distribution.completed', 1)
            ->assertJsonPath('data.operational_queues.pending_orders', 1)
            ->assertJsonPath('data.operational_queues.pending_vendors', 1)
            ->assertJsonPath('data.operational_queues.draft_books', 1)
            ->assertJsonCount(6, 'data.commerce_trend.labels')
            ->assertJsonCount(6, 'data.account_growth.users');
    }

    public function test_non_admin_cannot_read_system_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->getJson('/api/admin/stats')
            ->assertForbidden();
    }

    private function book(Vendor $vendor, Category $category, string $slug, string $type, string $status): Book
    {
        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => $slug,
            'slug' => $slug,
            'author' => 'KomiBook',
            'price' => 50000,
            'stock' => 1,
            'type' => $type,
            'format' => $type,
            'provenance' => 'publisher_catalog',
            'fulfillment_mode' => $type === 'ebook' ? 'digital' : 'vendor_warehouse',
            'status' => $status,
        ]);
    }

    private function order(User $customer, Vendor $vendor, string $status, int $total): Order
    {
        return Order::withoutGlobalScopes()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total_amount' => $total,
            'status' => $status,
            'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
            'payment_method' => 'cod',
            'shipping_address' => 'KomiBook test address',
            'phone' => '0900000000',
        ]);
    }
}

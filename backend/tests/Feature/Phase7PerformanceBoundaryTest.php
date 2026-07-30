<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase7PerformanceBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_page_batches_stock_queries_instead_of_querying_each_book(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Nhà bán hiệu năng',
            'slug' => 'phase-7-performance',
            'status' => 'active',
            'onboarding_status' => 'approved',
        ]);
        $category = Category::create(['name' => 'Hiệu năng', 'slug' => 'phase-7-performance']);
        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Kho hiệu năng',
            'address' => 'Địa chỉ test',
            'status' => 'Hoạt động',
        ]);
        $this->createBooks($vendor, $category, $warehouse, 1, 1);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->actingAs($user)->getJson('/api/vendor/warehouses')->assertOk();
        $singleBookQueryCount = count($queries);

        $this->createBooks($vendor, $category, $warehouse, 2, 8);
        $queries = [];
        $response = $this->actingAs($user)->getJson('/api/vendor/warehouses')->assertOk();
        $manyBookQueryCount = count($queries);

        $this->assertCount(8, $response->json('stocks'));
        $this->assertLessThanOrEqual($singleBookQueryCount + 1, $manyBookQueryCount);
        $this->assertSame(1, collect($queries)->filter(fn (string $sql) => str_contains($sql, 'from "warehouse_stocks"'))->count());
    }

    public function test_phase_7_indexes_and_role_middleware_are_present(): void
    {
        $this->assertTrue(Schema::hasIndex('books', ['vendor_id', 'status']));
        $this->assertTrue(Schema::hasIndex('books', ['vendor_id', 'type', 'stock']));
        $this->assertTrue(Schema::hasIndex('warehouse_stocks', ['book_id', 'warehouse_id']));
        $this->assertTrue(Schema::hasIndex('inventory_audits', ['vendor_id', 'created_at']));
        $this->assertTrue(Schema::hasIndex('stock_transfers', ['vendor_id', 'created_at']));

        $vendorMiddleware = Route::getRoutes()->getByName('vendor.warehouses.index')->gatherMiddleware();
        $adminMiddleware = Route::getRoutes()->getByName('admin.finance-report.index')->gatherMiddleware();
        $this->assertContains('auth:sanctum', $vendorMiddleware);
        $this->assertContains('role:vendor', $vendorMiddleware);
        $this->assertContains('active-vendor', $vendorMiddleware);
        $this->assertContains('auth:sanctum', $adminMiddleware);
        $this->assertContains('role:admin', $adminMiddleware);
    }

    private function createBooks(Vendor $vendor, Category $category, Warehouse $warehouse, int $from, int $to): void
    {
        foreach (range($from, $to) as $index) {
            $book = Book::withoutGlobalScopes()->create([
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'title' => "Sách hiệu năng {$index}",
                'slug' => "phase-7-performance-book-{$index}",
                'author' => 'KomiBook',
                'price' => 100000,
                'stock' => 2,
                'type' => 'physical',
                'status' => 'published',
            ]);
            WarehouseStock::create([
                'warehouse_id' => $warehouse->id,
                'book_id' => $book->id,
                'quantity' => 2,
            ]);
        }
    }
}

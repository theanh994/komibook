<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CatalogPreservationManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7CatalogPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_detects_normalized_keys_stock_conflicts_and_performs_no_writes(): void
    {
        $category = Category::create(['name' => 'Bảo toàn', 'slug' => 'bao-toan']);
        $vendorA = $this->vendor('catalog-a');
        $vendorB = $this->vendor('catalog-b');
        $bookA = $this->book($vendorA, $category, 'Sach-Trung', '978-1-2345-6789-7', 5);
        $bookB = $this->book($vendorB, $category, 'sach-trung', '9781234567897', 2);
        $bookMissingKey = $this->book($vendorA, $category, '', null, 0);
        $warehouseA = $this->warehouse($vendorA, 'Kho A');
        $warehouseB = $this->warehouse($vendorB, 'Kho B');
        WarehouseStock::create(['warehouse_id' => $warehouseA->id, 'book_id' => $bookA->id, 'quantity' => 4]);
        WarehouseStock::create(['warehouse_id' => $warehouseB->id, 'book_id' => $bookA->id, 'quantity' => 1]);

        $before = $this->fingerprint();
        $manifest = app(CatalogPreservationManifestService::class)->build();
        $after = $this->fingerprint();

        $recordA = collect($manifest['records'])->firstWhere('book_id', $bookA->id);
        $missing = collect($manifest['records'])->firstWhere('book_id', $bookMissingKey->id);
        $this->assertSame('dry_run', $manifest['mode']);
        $this->assertFalse($manifest['writes_performed']);
        $this->assertFalse($manifest['import_source_supplied']);
        $this->assertSame('stop_for_conflict_review_before_import', $manifest['decision']);
        $this->assertContains('duplicate_isbn', $recordA['conflicts']);
        $this->assertContains('duplicate_slug', $recordA['conflicts']);
        $this->assertContains('warehouse_stock_tenant_mismatch', $recordA['conflicts']);
        $this->assertContains('missing_stable_key', $missing['conflicts']);
        $this->assertArrayNotHasKey('address', $manifest['summary']['related_records']);
        $this->assertSame($before, $after);
    }

    public function test_command_outputs_read_only_json_manifest(): void
    {
        $before = $this->fingerprint();
        $this->artisan('catalog:preservation-manifest')
            ->expectsOutputToContain('"mode":"dry_run","writes_performed":false')
            ->assertSuccessful();
        $this->assertSame($before, $this->fingerprint());
    }

    private function vendor(string $slug): Vendor
    {
        $user = User::factory()->create(['role' => 'vendor']);

        return Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => "Nhà bán {$slug}",
            'slug' => $slug,
            'status' => 'active',
            'onboarding_status' => 'approved',
        ]);
    }

    private function book(Vendor $vendor, Category $category, string $slug, ?string $isbn, int $stock): Book
    {
        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => $slug === '' ? 'Sách thiếu khóa' : $slug,
            'slug' => $slug,
            'isbn' => $isbn,
            'author' => 'KomiBook',
            'price' => 100000,
            'stock' => $stock,
            'type' => 'physical',
            'status' => 'published',
        ]);
    }

    private function warehouse(Vendor $vendor, string $name): Warehouse
    {
        return Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'address' => 'Địa chỉ chỉ dùng trong test',
            'status' => 'Hoạt động',
        ]);
    }

    private function fingerprint(): array
    {
        return [
            'books' => Book::withoutGlobalScopes()->count(),
            'books_updated_at' => Book::withoutGlobalScopes()->max('updated_at'),
            'warehouses' => Warehouse::withoutGlobalScopes()->count(),
            'stocks' => WarehouseStock::count(),
            'stocks_updated_at' => WarehouseStock::max('updated_at'),
        ];
    }
}

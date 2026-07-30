<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleBook;
use App\Models\InventoryAudit;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7VendorOperationsParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_audit_and_transfer_use_quantity_and_reject_cross_tenant_inputs(): void
    {
        [$userA, $vendorA] = $this->vendor('ops-a');
        [, $vendorB] = $this->vendor('ops-b');
        $category = Category::create(['name' => 'Kho Phase 7', 'slug' => 'kho-phase-7']);
        $bookA = $this->book($vendorA, $category, 'ops-book-a');
        $bookB = $this->book($vendorB, $category, 'ops-book-b');
        $source = $this->warehouse($vendorA, 'Kho nguồn');
        $target = $this->warehouse($vendorA, 'Kho đích');
        $foreign = $this->warehouse($vendorB, 'Kho ngoài tenant');
        WarehouseStock::create(['warehouse_id' => $source->id, 'book_id' => $bookA->id, 'quantity' => 10]);

        $this->actingAs($userA)->postJson('/api/vendor/inventory/audits', [
            'warehouse_id' => $foreign->id,
            'audit_period' => '07/2026',
            'items' => [['book_id' => $bookA->id, 'physical_qty' => 8]],
        ])->assertNotFound();
        $this->assertDatabaseCount('inventory_audits', 0);

        $auditId = $this->actingAs($userA)->postJson('/api/vendor/inventory/audits', [
            'warehouse_id' => $source->id,
            'audit_period' => '07/2026',
            'items' => [['book_id' => $bookA->id, 'physical_qty' => 8]],
        ])->assertCreated()->json('data.id');
        $this->actingAs($userA)->postJson("/api/vendor/inventory/audits/{$auditId}/complete")->assertOk();
        $this->assertSame(8, WarehouseStock::where('warehouse_id', $source->id)->where('book_id', $bookA->id)->value('quantity'));
        $this->assertSame(8, $bookA->fresh()->stock);

        $this->actingAs($userA)->postJson('/api/vendor/inventory/transfers', [
            'from_warehouse_id' => $source->id,
            'to_warehouse_id' => $foreign->id,
            'items' => [['book_id' => $bookB->id, 'quantity' => 1]],
        ])->assertNotFound();
        $this->assertDatabaseCount('stock_transfers', 0);

        $transferId = $this->actingAs($userA)->postJson('/api/vendor/inventory/transfers', [
            'from_warehouse_id' => $source->id,
            'to_warehouse_id' => $target->id,
            'reason' => 'Cân bằng tồn kho',
            'items' => [['book_id' => $bookA->id, 'quantity' => 3]],
        ])->assertCreated()->json('data.id');
        $this->actingAs($userA)->postJson("/api/vendor/inventory/transfers/{$transferId}/ship")->assertOk();
        $this->assertSame(5, WarehouseStock::where('warehouse_id', $source->id)->where('book_id', $bookA->id)->value('quantity'));
        $this->actingAs($userA)->postJson("/api/vendor/inventory/transfers/{$transferId}/receive")->assertOk();
        $this->assertSame(3, WarehouseStock::where('warehouse_id', $target->id)->where('book_id', $bookA->id)->value('quantity'));
        $this->assertSame(8, $bookA->fresh()->stock);
        $this->assertSame('received', StockTransfer::findOrFail($transferId)->status);
        $this->assertSame('completed', InventoryAudit::findOrFail($auditId)->status);
    }

    public function test_finance_explains_fee_policy_and_flash_sale_registration_is_atomic(): void
    {
        [$userA, $vendorA] = $this->vendor('promo-a');
        [, $vendorB] = $this->vendor('promo-b');
        $category = Category::create(['name' => 'Promo Phase 7', 'slug' => 'promo-phase-7']);
        $bookA = $this->book($vendorA, $category, 'promo-book-a');
        $bookB = $this->book($vendorB, $category, 'promo-book-b');
        $sale = FlashSale::create([
            'title' => 'Chiến dịch Phase 7',
            'start_time' => now()->addHour(),
            'end_time' => now()->addDay(),
            'status' => 'enrollment_open',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'coupon_stacking_policy' => 'deny',
            'priority' => 1,
        ]);

        $this->actingAs($userA)->getJson('/api/vendor/finance')
            ->assertOk()
            ->assertJsonPath('fee_policy.example.seller_gross', 100000)
            ->assertJsonPath('fee_policy.example.commission_amount', 10000)
            ->assertJsonPath('fee_policy.example.seller_net', 90000)
            ->assertJsonPath('fee_policy.example.customer_pays', 100000)
            ->assertJsonPath('fee_policy.example.tax_configured', false);

        $this->actingAs($userA)->postJson("/api/vendor/flash-sales/{$sale->id}/register", [
            'book_ids' => [$bookA->id, $bookB->id],
            'discount_percent' => 20,
            'max_quantity' => 2,
        ])->assertForbidden();
        $this->assertDatabaseCount('flash_sale_books', 0);

        $this->actingAs($userA)->postJson("/api/vendor/flash-sales/{$sale->id}/register", [
            'book_ids' => [$bookA->id],
            'discount_percent' => 20,
            'max_quantity' => 2,
        ])->assertCreated()->assertJsonPath('data.0.status', 'pending');
        $this->assertDatabaseHas('flash_sale_books', [
            'flash_sale_id' => $sale->id,
            'book_id' => $bookA->id,
            'vendor_id' => $vendorA->id,
            'status' => 'pending',
        ]);
        $this->assertSame(1, FlashSaleBook::count());
    }

    private function vendor(string $slug): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => "Gian hàng {$slug}",
            'slug' => $slug,
            'status' => 'active',
            'onboarding_status' => 'approved',
            'balance' => 500000,
        ]);

        return [$user, $vendor];
    }

    private function book(Vendor $vendor, Category $category, string $slug): Book
    {
        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => $slug,
            'slug' => $slug,
            'author' => 'KomiBook',
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'status' => 'published',
        ]);
    }

    private function warehouse(Vendor $vendor, string $name): Warehouse
    {
        return Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'address' => "Địa chỉ {$name}",
            'status' => 'Hoạt động',
        ]);
    }
}

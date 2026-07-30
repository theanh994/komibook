<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseManagerAssignment;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8WarehouseDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_posts_once_and_balances_book_shadow_stock(): void
    {
        [$vendorUser, $vendor] = $this->vendor('phase8-receipt');
        $warehouse = $this->warehouse($vendor, 'Kho nhập');
        $book = $this->book($vendor, 'Sách nhập kho');

        $created = $this->actingAs($vendorUser)->postJson('/api/vendor/warehouse-documents', [
            'type' => 'receipt',
            'destination_warehouse_id' => $warehouse->id,
            'operation_key' => 'phase8-receipt-create',
            'lines' => [['book_id' => $book->id, 'quantity' => 7, 'shelf_location' => 'A-01']],
        ])->assertCreated();
        $documentId = $created->json('data.id');
        $this->transition($vendorUser, $documentId, 'submitted', 'phase8-receipt-submit')->assertOk();
        $this->transition($vendorUser, $documentId, 'approved', 'phase8-receipt-approve')->assertOk();
        $this->transition($vendorUser, $documentId, 'posted', 'phase8-receipt-post')
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $this->assertDatabaseHas('warehouse_stocks', [
            'warehouse_id' => $warehouse->id,
            'book_id' => $book->id,
            'quantity' => 7,
            'shelf_location' => 'A-01',
        ]);
        $this->assertSame(7, $book->fresh()->stock);
        $this->assertDatabaseCount('warehouse_stock_ledgers', 1);

        $this->transition($vendorUser, $documentId, 'posted', 'phase8-receipt-post')
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');
        $this->assertDatabaseCount('warehouse_stock_ledgers', 1);
        $this->assertSame(7, WarehouseStock::where('warehouse_id', $warehouse->id)->value('quantity'));
    }

    public function test_dispatch_cannot_oversell_and_transaction_preserves_balance(): void
    {
        [$vendorUser, $vendor] = $this->vendor('phase8-dispatch');
        $warehouse = $this->warehouse($vendor, 'Kho xuất');
        $book = $this->book($vendor, 'Sách xuất kho');
        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'book_id' => $book->id, 'quantity' => 2]);

        $created = $this->actingAs($vendorUser)->postJson('/api/vendor/warehouse-documents', [
            'type' => 'dispatch',
            'source_warehouse_id' => $warehouse->id,
            'operation_key' => 'phase8-dispatch-create',
            'lines' => [['book_id' => $book->id, 'quantity' => 3]],
        ])->assertCreated();
        $documentId = $created->json('data.id');
        $this->transition($vendorUser, $documentId, 'submitted', 'phase8-dispatch-submit')->assertOk();
        $this->transition($vendorUser, $documentId, 'approved', 'phase8-dispatch-approve')->assertOk();
        $this->transition($vendorUser, $documentId, 'posted', 'phase8-dispatch-post')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stock');

        $this->assertSame(2, WarehouseStock::where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertDatabaseCount('warehouse_stock_ledgers', 0);
        $this->assertDatabaseHas('warehouse_documents', ['id' => $documentId, 'status' => 'approved']);
    }

    public function test_manager_requires_active_capability_for_every_warehouse_in_transfer(): void
    {
        [$vendorUser, $vendor] = $this->vendor('phase8-transfer');
        $source = $this->warehouse($vendor, 'Kho nguồn');
        $destination = $this->warehouse($vendor, 'Kho đích');
        $book = $this->book($vendor, 'Sách điều chuyển');
        $manager = User::factory()->create(['role' => 'customer']);
        WarehouseManagerAssignment::create([
            'user_id' => $manager->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $source->id,
            'invited_by' => $vendorUser->id,
            'capabilities' => ['view_inventory', 'transfer_stock'],
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $this->actingAs($manager)->postJson('/api/warehouse-manager/documents', [
            'type' => 'transfer',
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'operation_key' => 'phase8-manager-transfer',
            'lines' => [['book_id' => $book->id, 'quantity' => 1]],
        ])->assertForbidden();

        WarehouseManagerAssignment::create([
            'user_id' => $manager->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $destination->id,
            'invited_by' => $vendorUser->id,
            'capabilities' => ['view_inventory', 'transfer_stock'],
            'status' => 'active',
            'accepted_at' => now(),
        ]);
        $this->actingAs($manager)->postJson('/api/warehouse-manager/documents', [
            'type' => 'transfer',
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'operation_key' => 'phase8-manager-transfer-allowed',
            'lines' => [['book_id' => $book->id, 'quantity' => 1]],
        ])->assertCreated();
    }

    private function transition(User $user, int $documentId, string $status, string $key)
    {
        return $this->actingAs($user)->patchJson("/api/vendor/warehouse-documents/{$documentId}/transition", [
            'to_status' => $status,
            'operation_key' => $key,
        ]);
    }

    private function vendor(string $slug): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => "Nhà bán {$slug}",
            'slug' => $slug,
            'status' => 'active',
            'onboarding_status' => 'approved',
        ]);

        return [$user, $vendor];
    }

    private function warehouse(Vendor $vendor, string $name): Warehouse
    {
        return Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'address' => 'Địa chỉ riêng tư',
            'status' => 'Hoạt động',
        ]);
    }

    private function book(Vendor $vendor, string $title): Book
    {
        $category = Category::firstOrCreate(['slug' => 'phase8-warehouse'], ['name' => 'Kho Phase 8']);

        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.str()->random(5),
            'author' => 'KomiBook',
            'price' => 100000,
            'stock' => 0,
            'type' => 'physical',
            'status' => 'published',
        ]);
    }
}

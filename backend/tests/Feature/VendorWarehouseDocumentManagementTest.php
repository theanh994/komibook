<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorWarehouseDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createVendorEnvironment(string $suffix = 'wh-mgmt'): array
    {
        $user = User::factory()->create([
            'email' => "vendor-{$suffix}@test.com",
            'role' => 'vendor',
        ]);
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'shop_name' => "Vendor Shop {$suffix}",
            'slug' => "vendor-shop-{$suffix}",
            'status' => 'active',
        ]);
        $warehouse = Warehouse::create([
            'vendor_id' => $vendor->id,
            'name' => "Kho chính {$suffix}",
            'address' => 'Hà Nội',
            'status' => 'active',
        ]);
        $vendor->update(['primary_warehouse_id' => $warehouse->id]);

        $category = Category::create(['name' => 'Kinh tế', 'slug' => "kinh-te-{$suffix}"]);
        $book1 = Book::create([
            'vendor_id' => $vendor->id,
            'title' => 'Sách Tập 1',
            'slug' => "sach-tap-1-{$suffix}",
            'author' => 'Tác giả A',
            'category_id' => $category->id,
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'status' => 'published',
        ]);
        $book2 = Book::create([
            'vendor_id' => $vendor->id,
            'title' => 'Sách Tập 2',
            'slug' => "sach-tap-2-{$suffix}",
            'author' => 'Tác giả A',
            'category_id' => $category->id,
            'price' => 120000,
            'stock' => 15,
            'type' => 'physical',
            'status' => 'published',
        ]);

        return [$user, $vendor, $warehouse, $book1, $book2];
    }

    public function test_vendor_can_update_draft_warehouse_document(): void
    {
        [$user, $vendor, $warehouse, $book1, $book2] = $this->createVendorEnvironment('update-draft');

        $response = $this->actingAs($user)->postJson('/api/vendor/warehouse-documents', [
            'type' => 'receipt',
            'destination_warehouse_id' => $warehouse->id,
            'reason' => 'Nhập ban đầu',
            'operation_key' => 'op-create-draft-1',
            'lines' => [
                ['book_id' => $book1->id, 'quantity' => 5],
            ],
        ]);
        $response->assertCreated();
        $docId = $response->json('data.id');

        // Update draft document with new lines and reason
        $updateResponse = $this->actingAs($user)->putJson("/api/vendor/warehouse-documents/{$docId}", [
            'reason' => 'Nhập bổ sung điều chỉnh',
            'external_counterparty_name' => 'NXB Giáo Dục',
            'lines' => [
                ['book_id' => $book1->id, 'quantity' => 10],
                ['book_id' => $book2->id, 'quantity' => 20],
            ],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.reason', 'Nhập bổ sung điều chỉnh')
            ->assertJsonPath('data.external_counterparty_name', 'NXB Giáo Dục');

        $this->assertDatabaseHas('warehouse_documents', [
            'id' => $docId,
            'reason' => 'Nhập bổ sung điều chỉnh',
            'status' => 'draft',
        ]);

        $this->assertDatabaseCount('warehouse_document_lines', 2);
        $this->assertDatabaseHas('warehouse_document_lines', [
            'warehouse_document_id' => $docId,
            'book_id' => $book2->id,
            'quantity' => 20,
        ]);
    }

    public function test_cannot_update_non_draft_warehouse_document(): void
    {
        [$user, $vendor, $warehouse, $book1] = $this->createVendorEnvironment('update-submitted');

        $doc = WarehouseDocument::create([
            'vendor_id' => $vendor->id,
            'document_code' => 'RCP-TEST-001',
            'type' => 'receipt',
            'destination_warehouse_id' => $warehouse->id,
            'status' => 'submitted',
            'created_by' => $user->id,
            'operation_key' => 'op-submitted-1',
        ]);
        $doc->lines()->create(['book_id' => $book1->id, 'quantity' => 5]);

        $response = $this->actingAs($user)->putJson("/api/vendor/warehouse-documents/{$doc->id}", [
            'reason' => 'Thử sửa phiếu đã gửi duyệt',
            'lines' => [['book_id' => $book1->id, 'quantity' => 10]],
        ]);

        $response->assertUnprocessable();
    }

    public function test_vendor_can_cancel_draft_and_submitted_documents_with_reason(): void
    {
        [$user, $vendor, $warehouse, $book1] = $this->createVendorEnvironment('cancel-doc');

        $doc = WarehouseDocument::create([
            'vendor_id' => $vendor->id,
            'document_code' => 'RCP-CANCEL-001',
            'type' => 'receipt',
            'destination_warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'created_by' => $user->id,
            'operation_key' => 'op-cancel-1',
        ]);
        $doc->lines()->create(['book_id' => $book1->id, 'quantity' => 5]);

        // Cancelling without reason fails
        $failResponse = $this->actingAs($user)->patchJson("/api/vendor/warehouse-documents/{$doc->id}/transition", [
            'to_status' => 'cancelled',
            'operation_key' => 'op-cancel-transition-fail',
        ]);
        $failResponse->assertUnprocessable();

        // Cancelling with reason succeeds
        $successResponse = $this->actingAs($user)->patchJson("/api/vendor/warehouse-documents/{$doc->id}/transition", [
            'to_status' => 'cancelled',
            'reason' => 'Khách hủy đơn nhập',
            'operation_key' => 'op-cancel-transition-success',
        ]);

        $successResponse->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('warehouse_documents', [
            'id' => $doc->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('warehouse_document_events', [
            'warehouse_document_id' => $doc->id,
            'to_status' => 'cancelled',
            'reason' => 'Khách hủy đơn nhập',
        ]);
    }

    public function test_cannot_cancel_posted_warehouse_document(): void
    {
        [$user, $vendor, $warehouse, $book1] = $this->createVendorEnvironment('cancel-posted');

        $doc = WarehouseDocument::create([
            'vendor_id' => $vendor->id,
            'document_code' => 'RCP-POSTED-001',
            'type' => 'receipt',
            'destination_warehouse_id' => $warehouse->id,
            'status' => 'posted',
            'created_by' => $user->id,
            'operation_key' => 'op-posted-1',
        ]);
        $doc->lines()->create(['book_id' => $book1->id, 'quantity' => 5]);

        $response = $this->actingAs($user)->patchJson("/api/vendor/warehouse-documents/{$doc->id}/transition", [
            'to_status' => 'cancelled',
            'reason' => 'Cố gắng hủy phiếu đã ghi sổ',
            'operation_key' => 'op-cancel-posted-fail',
        ]);

        $response->assertUnprocessable();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookPrintEditionWarehouseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_supplied_vendor_creates_search_only_reprint_and_receipt_unlocks_browsing_and_purchase(): void
    {
        [$user, $vendor, $warehouse] = $this->selfSuppliedVendor();
        $category = Category::create(['name' => 'Sách Bản in', 'slug' => 'sach-ban-in']);

        $this->actingAs($user)->getJson('/api/vendor/books/create-scope')
            ->assertOk()
            ->assertJsonPath('data.supply_chain_mode', 'self_supplied')
            ->assertJsonPath('data.primary_warehouse.id', $warehouse->id)
            ->assertJsonPath('data.required_commercial_roles', [])
            ->assertJsonPath('data.can_create_physical_book', true);

        $created = $this->actingAs($user)->postJson('/api/vendor/books', [
            'title' => 'Tựa sách kiểm thử lần in',
            'author' => 'KomiBook',
            'category_ids' => [$category->id],
            'isbn' => '978-604-TEST-02',
            'print_edition' => 2,
            'price' => 120000,
            'stock' => 5,
            'type' => 'physical',
            'warehouse_id' => $warehouse->id,
            'external_counterparty_name' => 'Xưởng in ngoài hệ thống',
            'operation_key' => 'book-print-edition-workflow',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Tựa sách kiểm thử lần in')
            ->assertJsonPath('data.display_title', 'Tựa sách kiểm thử lần in — Tái bản lần 2')
            ->assertJsonPath('data.stock', 0)
            ->assertJsonPath('data.discoverability', 'search_only')
            ->assertJsonPath('data.is_purchasable', false)
            ->assertJsonPath('receipt_document.status', 'draft')
            ->assertJsonPath('receipt_document.lines.0.quantity', 5);

        $bookId = $created->json('data.id');
        $receiptId = $created->json('receipt_document.id');
        $this->getJson('/api/books')->assertOk()->assertJsonMissing(['id' => $bookId]);
        $this->getJson('/api/books?category_id='.$category->id)->assertOk()->assertJsonMissing(['id' => $bookId]);
        $this->getJson('/api/books?search=ki%E1%BB%83m%20th%E1%BB%AD')
            ->assertOk()
            ->assertJsonFragment(['id' => $bookId, 'discoverability' => 'search_only', 'is_purchasable' => false]);
        $this->getJson('/api/books/'.Book::withoutGlobalScopes()->findOrFail($bookId)->slug)
            ->assertOk()
            ->assertJsonPath('data.id', $bookId);

        $this->transition($user, $receiptId, 'submitted', 'book-receipt-submit')->assertOk();
        $this->transition($user, $receiptId, 'approved', 'book-receipt-approve')->assertOk();
        $this->transition($user, $receiptId, 'posted', 'book-receipt-post')->assertOk();

        $this->getJson('/api/books')
            ->assertOk()
            ->assertJsonFragment(['id' => $bookId, 'discoverability' => 'browse_and_search', 'is_purchasable' => true]);
        $this->assertSame(5, Book::withoutGlobalScopes()->findOrFail($bookId)->stock);
        $this->assertDatabaseHas('warehouse_stock_ledgers', [
            'warehouse_document_id' => $receiptId,
            'warehouse_id' => $warehouse->id,
            'book_id' => $bookId,
            'quantity_delta' => 5,
        ]);
        $this->actingAs($user)->get("/api/vendor/warehouse-documents/{$receiptId}/print")
            ->assertOk()
            ->assertSee('Phiếu nhập kho')
            ->assertSee('Tựa sách kiểm thử lần in — Tái bản lần 2');
        $this->actingAs($user)->get("/api/vendor/warehouse-documents/{$receiptId}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->get("/api/vendor/warehouse-documents/{$receiptId}/excel")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_zero_quantity_receipt_is_editable_but_cannot_post_and_existing_book_can_be_restocked(): void
    {
        [$user, , $warehouse] = $this->selfSuppliedVendor('zero-receipt');
        $category = Category::create(['name' => 'Sách chờ nhập', 'slug' => 'sach-cho-nhap']);
        $created = $this->actingAs($user)->postJson('/api/vendor/books', [
            'title' => 'Sách chờ phiếu nhập',
            'author' => 'KomiBook',
            'category_ids' => [$category->id],
            'print_edition' => 1,
            'price' => 90000,
            'stock' => 0,
            'type' => 'physical',
            'warehouse_id' => $warehouse->id,
            'operation_key' => 'zero-receipt-workflow',
        ])->assertCreated();
        $bookId = $created->json('data.id');
        $receiptId = $created->json('receipt_document.id');

        $this->actingAs($user)->putJson("/api/vendor/warehouse-documents/{$receiptId}", [
            'reason' => 'Chờ xác nhận số lượng từ xưởng in',
            'external_counterparty_name' => 'Xưởng in thử nghiệm',
            'lines' => [['book_id' => $bookId, 'quantity' => 0, 'shelf_location' => 'A-01']],
        ])->assertOk()
            ->assertJsonPath('data.lines.0.quantity', 0)
            ->assertJsonPath('data.lines.0.shelf_location', 'A-01');

        $this->transition($user, $receiptId, 'submitted', 'zero-submit')->assertOk();
        $this->transition($user, $receiptId, 'approved', 'zero-approve')->assertOk();
        $this->transition($user, $receiptId, 'posted', 'zero-post')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines');

        $this->actingAs($user)->patchJson("/api/vendor/warehouse-documents/{$receiptId}/transition", [
            'to_status' => 'cancelled',
            'reason' => 'Nhập lại số lượng trên phiếu mới',
            'operation_key' => 'zero-cancel',
        ])->assertOk();

        $restock = $this->actingAs($user)->postJson('/api/vendor/warehouse-documents', [
            'type' => 'receipt',
            'receipt_mode' => 'restock_existing',
            'destination_warehouse_id' => $warehouse->id,
            'operation_key' => 'restock-existing-book',
            'lines' => [['book_id' => $bookId, 'quantity' => 8]],
        ])->assertCreated()->assertJsonPath('data.receipt_mode', 'restock_existing');
        $this->assertSame(1, Book::withoutGlobalScopes()->whereKey($bookId)->count());
        $this->assertSame(0, Book::withoutGlobalScopes()->findOrFail($bookId)->stock);
        $this->assertDatabaseHas('warehouse_document_lines', [
            'warehouse_document_id' => $restock->json('data.id'),
            'book_id' => $bookId,
            'quantity' => 8,
        ]);
    }

    public function test_transfer_is_not_available_when_vendor_has_only_one_active_warehouse(): void
    {
        [$user, $vendor, $warehouse] = $this->selfSuppliedVendor('single-warehouse');
        $category = Category::create(['name' => 'Điều chuyển', 'slug' => 'dieu-chuyen']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách một kho',
            'slug' => 'sach-mot-kho',
            'author' => 'KomiBook',
            'price' => 100000,
            'stock' => 0,
            'type' => 'physical',
            'status' => 'published',
        ]);

        $this->actingAs($user)->getJson('/api/vendor/warehouse-document-scope')
            ->assertOk()
            ->assertJsonPath('data.can_transfer', false);
        $this->actingAs($user)->postJson('/api/vendor/warehouse-documents', [
            'type' => 'transfer',
            'source_warehouse_id' => $warehouse->id,
            'destination_warehouse_id' => $warehouse->id,
            'operation_key' => 'single-warehouse-transfer',
            'lines' => [['book_id' => $book->id, 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Nhà bán chỉ có một kho hoạt động nên không thể tạo phiếu điều chuyển.');
    }

    public function test_vendor_can_save_publication_status_from_edit_form_without_legacy_workflow(): void
    {
        [$user, $vendor] = $this->selfSuppliedVendor('edit-publication-status');
        $category = Category::create(['name' => 'Chỉnh trạng thái', 'slug' => 'chinh-trang-thai']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách chỉnh trạng thái',
            'slug' => 'sach-chinh-trang-thai',
            'author' => 'KomiBook',
            'price' => 100000,
            'stock' => 0,
            'type' => 'physical',
            'status' => 'draft',
            'publishing_status' => 'draft',
        ]);

        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'status' => 'published',
            'publishing_status' => 'published',
        ]);

        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}", ['status' => 'draft'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'status' => 'draft',
            'publishing_status' => 'draft',
        ]);
    }

    private function transition(User $user, int $documentId, string $status, string $key)
    {
        return $this->actingAs($user)->patchJson("/api/vendor/warehouse-documents/{$documentId}/transition", [
            'to_status' => $status,
            'operation_key' => $key,
        ]);
    }

    private function selfSuppliedVendor(string $slug = 'self-supplied'): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $organization = Organization::create([
            'legal_name' => 'Nhà xuất bản tự cung cấp '.$slug,
            'display_name' => 'NXB '.$slug,
            'slug' => 'nxb-'.$slug,
            'organization_types' => ['publisher', 'supplier'],
            'data_mode' => 'demo',
            'status' => 'demo_accepted',
        ]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Nhà bán '.$slug,
            'slug' => $slug,
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'direct_publisher',
            'primary_organization_id' => $organization->id,
            'is_demo' => true,
        ]);
        VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'demo_accepted',
            'is_demo' => true,
            'operation_key' => 'self-supplied-'.$slug,
        ]);
        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Kho tổng '.$slug,
            'address' => 'Hà Nội',
            'province' => 'Hà Nội',
            'status' => 'Hoạt động',
        ]);
        $vendor->update(['primary_warehouse_id' => $warehouse->id]);

        return [$user, $vendor, $warehouse];
    }
}

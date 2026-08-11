<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Models\Warehouse;
use App\Models\WarehouseDocument;
use App\Models\WarehouseManagerAssignment;
use App\Models\WarehouseStock;
use App\Services\OrganizationRelationshipService;
use App\Services\OrganizationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorBookCreationAndWarehouseInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_creates_book_with_cover_warehouse_stock_and_demo_supply_chain_in_one_request(): void
    {
        Storage::fake('public');
        [$vendorUser, $vendor] = $this->vendor('integrated-book');
        $vendor->update(['is_demo' => true]);
        $warehouse = $this->warehouse($vendor);
        $category = Category::create(['name' => 'Sách tích hợp', 'slug' => 'sach-tich-hop']);
        $organization = Organization::create([
            'legal_name' => 'Nhà xuất bản Demo Tích hợp',
            'display_name' => 'NXB Demo Tích hợp',
            'slug' => 'nxb-demo-tich-hop',
            'organization_types' => ['publisher', 'supplier'],
            'data_mode' => 'demo',
            'status' => 'demo_accepted',
        ]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'demo_accepted',
            'is_demo' => true,
            'operation_key' => 'integrated-book-relation',
        ]);
        $organization->update(['status' => 'pending_review', 'submitted_at' => now()->subDay()]);
        $admin = User::factory()->create(['role' => 'admin']);
        app(OrganizationReviewService::class)->transition($organization, 'demo_accepted', $admin, 'Canonical demo organization fixture review.', 'integrated-book-organization-review');
        $relationship->update([
            'status' => 'submitted',
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-INTEGRATED-BOOK',
            'submitted_at' => now()->subDay(),
            'effective_from' => now()->subDay(),
        ]);
        app(OrganizationRelationshipService::class)->transition($relationship, 'demo_accepted', $admin, 'Canonical demo self-legal relationship fixture review.', 'integrated-book-relationship-review');

        $response = $this->actingAs($vendorUser)->post('/api/vendor/books', [
            'title' => 'Sách mới tích hợp',
            'author' => 'Tác giả Demo',
            'category_ids' => [$category->id],
            'price' => 120000,
            'stock' => 17,
            'print_edition' => 2,
            'type' => 'physical',
            'cover_image' => UploadedFile::fake()->image('bia-sach.webp', 600, 900),
            'warehouse_id' => $warehouse->id,
            'publisher_relationship_id' => $relationship->id,
            'supplier_relationship_id' => $relationship->id,
            'responsible_organization_relationship_id' => $relationship->id,
        ])->assertCreated()
            ->assertJsonPath('data.warehouse_stocks.0.warehouse_id', $warehouse->id)
            ->assertJsonPath('data.warehouse_stocks.0.quantity', 0)
            ->assertJsonPath('data.display_title', 'Sách mới tích hợp — Tái bản lần 2')
            ->assertJsonPath('receipt_document.type', 'receipt')
            ->assertJsonPath('receipt_document.status', 'draft')
            ->assertJsonPath('receipt_document.lines.0.quantity', 17)
            ->assertJsonPath('data.commercial_parties.publisher.is_demo', true)
            ->assertJsonPath('data.commercial_parties.supplier.display_name', 'NXB Demo Tích hợp');

        $book = Book::withoutGlobalScopes()->findOrFail($response->json('data.id'));
        Storage::disk('public')->assertExists($book->cover_image);
        $this->assertSame(0, WarehouseStock::where('book_id', $book->id)->value('quantity'));
        $this->assertSame(0, $book->stock);
        $this->assertSame('published', $book->status);
        $this->assertDatabaseHas('warehouse_documents', [
            'id' => $response->json('receipt_document.id'),
            'origin' => 'book_creation',
            'receipt_mode' => 'new_print_edition',
        ]);
        $this->assertCount(3, $book->activeCommercialParties);
    }

    public function test_invalid_foreign_warehouse_rolls_back_book_and_uploaded_cover(): void
    {
        Storage::fake('public');
        [$vendorUser, $vendor] = $this->vendor('rollback-book');
        [, $otherVendor] = $this->vendor('foreign-warehouse');
        $foreignWarehouse = $this->warehouse($otherVendor);
        $category = Category::create(['name' => 'Sách rollback', 'slug' => 'sach-rollback']);
        $organization = Organization::create([
            'legal_name' => 'Nhà xuất bản Xác minh',
            'display_name' => 'NXB Xác minh',
            'slug' => 'nxb-xac-minh-rollback',
            'organization_types' => ['publisher', 'supplier'],
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'verified',
            'verified_at' => now(),
            'operation_key' => 'rollback-book-relation',
        ]);
        $organization->update([
            'status' => 'pending_review',
            'verification_document' => 'organizations/nxb-xac-minh-rollback.pdf',
            'submitted_at' => now()->subDay(),
            'verified_at' => null,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        app(OrganizationReviewService::class)->transition($organization, 'verified', $admin, 'Canonical live organization fixture review.', 'rollback-book-organization-review');
        $relationship->update([
            'status' => 'submitted',
            'is_demo' => false,
            'evidence_mode' => 'real_document',
            'evidence_document' => 'organizations/relationships/nxb-xac-minh-rollback.pdf',
            'submitted_at' => now()->subDay(),
            'verified_at' => null,
            'effective_from' => now()->subDay(),
        ]);
        app(OrganizationRelationshipService::class)->transition($relationship, 'verified', $admin, 'Canonical live self-legal relationship fixture review.', 'rollback-book-relationship-review');

        $this->actingAs($vendorUser)->post('/api/vendor/books', [
            'title' => 'Sách phải rollback',
            'author' => 'Tác giả',
            'category_ids' => [$category->id],
            'price' => 100000,
            'stock' => 3,
            'type' => 'physical',
            'cover_image' => UploadedFile::fake()->image('rollback.webp'),
            'warehouse_id' => $foreignWarehouse->id,
            'publisher_relationship_id' => $relationship->id,
            'supplier_relationship_id' => $relationship->id,
            'responsible_organization_relationship_id' => $relationship->id,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('books', ['title' => 'Sách phải rollback']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_invited_warehouse_manager_receives_targeted_notification_and_responds_without_token(): void
    {
        [$vendorUser, $vendor] = $this->vendor('warehouse-notification');
        $warehouse = $this->warehouse($vendor);
        $manager = User::factory()->create(['role' => 'customer', 'email' => 'manager-notification@example.test']);
        $unrelatedUser = User::factory()->create(['role' => 'customer']);

        $invite = $this->actingAs($vendorUser)->postJson('/api/vendor/warehouse-managers/invite', [
            'email' => $manager->email,
            'warehouse_id' => $warehouse->id,
            'capabilities' => ['view_inventory', 'receive_stock'],
        ])->assertCreated();

        $assignmentId = $invite->json('data.id');
        $notification = UserNotification::where('user_id', $manager->id)->sole();
        $this->assertSame('warehouse_assignment_invitation', $notification->data['action_type']);
        $this->assertSame($assignmentId, $notification->data['assignment_id']);
        $this->assertFalse(UserNotification::where('user_id', $unrelatedUser->id)->exists());

        $this->actingAs($unrelatedUser)->postJson("/api/warehouse-manager/assignments/{$assignmentId}/respond", [
            'decision' => 'accept',
        ])->assertForbidden();

        $this->actingAs($manager)->postJson("/api/warehouse-manager/assignments/{$assignmentId}/respond", [
            'decision' => 'accept',
            'operation_key' => 'notification-accept-assignment',
        ])->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertSame('active', WarehouseManagerAssignment::findOrFail($assignmentId)->status);
        $notification->refresh();
        $this->assertSame('active', $notification->data['invitation_status']);
        $this->assertNotNull($notification->read_at);
    }

    public function test_vendor_can_resend_a_stuck_invitation_without_creating_duplicate_notifications(): void
    {
        [$vendorUser, $vendor] = $this->vendor('warehouse-resend');
        $warehouse = $this->warehouse($vendor);
        $manager = User::factory()->create(['role' => 'customer']);
        $assignment = WarehouseManagerAssignment::create([
            'user_id' => $manager->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'invited_by' => $vendorUser->id,
            'capabilities' => ['view_inventory'],
            'status' => 'invited',
            'invited_at' => now(),
        ]);

        $this->actingAs($vendorUser)
            ->postJson("/api/vendor/warehouse-managers/{$assignment->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.data.assignment_id', $assignment->id);
        $this->actingAs($vendorUser)
            ->postJson("/api/vendor/warehouse-managers/{$assignment->id}/resend")
            ->assertOk();

        $this->assertSame(1, UserNotification::where('user_id', $manager->id)->count());
        $this->assertNull(UserNotification::where('user_id', $manager->id)->sole()->read_at);
    }

    public function test_warehouse_filter_uses_warehouse_stock_quantity_and_stats_include_alert_details(): void
    {
        [$vendorUser, $vendor] = $this->vendor('warehouse-filter');
        $warehouse = $this->warehouse($vendor);
        $category = Category::create(['name' => 'Kho filter', 'slug' => 'kho-filter']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách sắp hết trong kho',
            'slug' => 'sach-sap-het-trong-kho',
            'author' => 'Tác giả',
            'price' => 100000,
            'stock' => 25,
            'type' => 'physical',
            'format' => 'physical',
            'provenance' => 'publisher_catalog',
            'fulfillment_mode' => 'vendor_warehouse',
            'status' => 'draft',
        ]);
        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'book_id' => $book->id, 'quantity' => 4]);

        $this->actingAs($vendorUser)->getJson("/api/vendor/warehouses?warehouse_id={$warehouse->id}&status=S%E1%BA%AFp%20h%E1%BA%BFt")
            ->assertOk()
            ->assertJsonPath('stocks.0.id', $book->id)
            ->assertJsonPath('stocks.0.stock', 4)
            ->assertJsonPath('stocks.0.total_stock', 25)
            ->assertJsonPath('stocks.0.status', 'Sắp hết');

        $book->update(['stock' => 4]);
        $this->actingAs($vendorUser)->getJson('/api/vendor/warehouses/stats')
            ->assertOk()
            ->assertJsonPath('low_stock_books.0.title', 'Sách sắp hết trong kho')
            ->assertJsonPath('low_stock_books.0.stock', 4);
    }

    public function test_create_scope_and_store_require_a_configured_primary_or_exactly_one_active_warehouse(): void
    {
        [$vendorUser, $vendor] = $this->vendor('scope-primary');
        $this->configureAcceptedDemoSelfSupplier($vendor);
        $firstWarehouse = $this->warehouse($vendor);
        $secondWarehouse = $this->warehouse($vendor);
        $category = Category::create(['name' => 'Warehouse scope', 'slug' => 'warehouse-scope']);
        $snapshot = fn () => [
            $vendor->fresh()->primary_warehouse_id,
            Book::withoutGlobalScopes()->count(),
            WarehouseStock::count(),
            WarehouseDocument::count(),
            \DB::table('warehouse_document_lines')->count(),
        ];
        $before = $snapshot();

        $this->actingAs($vendorUser)->getJson('/api/vendor/books/create-scope')
            ->assertOk()
            ->assertJsonPath('data.primary_warehouse', null)
            ->assertJsonPath('data.can_create_physical_book', false)
            ->assertJsonPath('data.blocking_reasons.0', 'Gian hàng có nhiều kho nhưng chưa chọn kho tổng.');
        $this->actingAs($vendorUser)->postJson('/api/vendor/books', [
            'title' => 'Must not choose first warehouse',
            'author' => 'KomiBook',
            'category_id' => $category->id,
            'price' => 20000,
            'stock' => 2,
            'type' => 'physical',
            'warehouse_id' => $firstWarehouse->id,
        ])->assertUnprocessable();
        $this->assertSame($before, $snapshot());

        $secondWarehouse->update(['status' => 'inactive']);
        $this->actingAs($vendorUser)->getJson('/api/vendor/books/create-scope')
            ->assertOk()
            ->assertJsonPath('data.primary_warehouse.id', $firstWarehouse->id)
            ->assertJsonPath('data.can_create_physical_book', true);
        $this->actingAs($vendorUser)->postJson('/api/vendor/books', [
            'title' => 'Exactly one active warehouse is allowed',
            'author' => 'KomiBook',
            'category_id' => $category->id,
            'price' => 20000,
            'stock' => 2,
            'type' => 'physical',
            'warehouse_id' => $firstWarehouse->id,
        ])->assertCreated();
        $this->assertNull($vendor->fresh()->primary_warehouse_id);
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
            'business_model' => 'bookstore',
        ]);

        return [$user, $vendor];
    }

    private function warehouse(Vendor $vendor): Warehouse
    {
        return Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Kho chính '.$vendor->id,
            'address' => 'Địa chỉ kho kiểm thử',
            'capacity' => '1000',
            'status' => 'Hoạt động',
        ]);
    }

    private function configureAcceptedDemoSelfSupplier(Vendor $vendor): void
    {
        $vendor->update(['is_demo' => true]);
        $organization = Organization::create([
            'legal_name' => 'Demo scope organization '.$vendor->id,
            'display_name' => 'Demo scope organization '.$vendor->id,
            'slug' => 'demo-scope-organization-'.$vendor->id,
            'organization_types' => ['publisher', 'supplier'],
            'data_mode' => 'demo',
            'status' => 'demo_accepted',
        ]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'demo_accepted',
            'is_demo' => true,
            'operation_key' => 'scope-primary-relationship-'.$vendor->id,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $organization->update(['status' => 'pending_review', 'submitted_at' => now()->subDay()]);
        app(OrganizationReviewService::class)->transition($organization, 'demo_accepted', $admin, 'Accepted demo organization for warehouse scope.', 'scope-primary-organization-'.$vendor->id);
        $relationship->update([
            'status' => 'submitted',
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-SCOPE-'.$vendor->id,
            'submitted_at' => now()->subDay(),
            'effective_from' => now()->subDay(),
        ]);
        app(OrganizationRelationshipService::class)->transition($relationship, 'demo_accepted', $admin, 'Accepted demo relationship for warehouse scope.', 'scope-primary-relationship-review-'.$vendor->id);
        $vendor->update(['business_model' => 'direct_publisher', 'primary_organization_id' => $organization->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Models\Warehouse;
use App\Services\DistributionAgreementService;
use App\Services\OrganizationRelationshipService;
use App\Services\OrganizationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorBookFormEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function createTestVendor(string $suffix = '1', string $businessModel = 'bookstore'): array
    {
        $user = User::factory()->create([
            'email' => "vendor-{$suffix}@test.com",
            'role' => 'vendor',
        ]);
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'shop_name' => "Vendor Shop {$suffix}",
            'slug' => "vendor-shop-{$suffix}",
            'business_model' => $businessModel,
            'status' => 'active',
        ]);
        $warehouse = Warehouse::create([
            'vendor_id' => $vendor->id,
            'name' => "Kho chính {$suffix}",
            'address' => "123 Đường Test {$suffix}",
            'status' => 'active',
        ]);
        $vendor->update(['primary_warehouse_id' => $warehouse->id]);

        return [$user, $vendor, $warehouse];
    }

    public function test_creating_book_without_optional_fields_stores_null_values(): void
    {
        Storage::fake('public');
        [$user, $vendor, $warehouse] = $this->createTestVendor('no-optionals', 'direct_publisher');

        $organization = Organization::create([
            'legal_name' => 'NXB Chính',
            'display_name' => 'NXB Chính',
            'slug' => 'nxb-chinh',
            'organization_types' => ['publisher', 'supplier'],
            'status' => 'pending_review',
            'data_mode' => 'live',
            'verification_document' => 'organizations/nxb-chinh.pdf',
            'submitted_at' => now()->subDay(),
        ]);
        app(OrganizationReviewService::class)->transition($organization, 'verified', User::factory()->create(['role' => 'admin']), 'Reviewed.', 'vendor-book-nxb-chinh');
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'submitted',
            'evidence_document' => 'organizations/relationships/op-key-test-1.pdf',
            'submitted_at' => now()->subDay(),
            'effective_from' => now()->subDay(),
            'operation_key' => 'op-key-test-1',
        ]);
        app(OrganizationRelationshipService::class)->transition($relationship, 'verified', User::factory()->create(['role' => 'admin']), 'Reviewed.', 'vendor-book-rel-1');
        $vendor->update(['primary_organization_id' => $organization->id]);

        $category = Category::create(['name' => 'Văn học', 'slug' => 'van-hoc']);

        $response = $this->actingAs($user)->postJson('/api/vendor/books', [
            'title' => 'Sách Không Chọn Thuộc Tính Phụ',
            'author' => 'Tác giả A',
            'category_ids' => [$category->id],
            'price' => 150000,
            'stock' => 10,
            'type' => 'physical',
            'warehouse_id' => $warehouse->id,
        ]);

        $response->assertCreated();
        $bookId = $response->json('data.id');

        $book = Book::withoutGlobalScopes()->findOrFail($bookId);
        $this->assertNull($book->cover_image);
        $this->assertNull($book->dimensions);
        $this->assertNull($book->cover_format);
        $this->assertNull($book->release_date);
        $this->assertNull($book->target_age);
    }

    public function test_reseller_can_update_commercial_parties_in_edit_mode(): void
    {
        [$user, $vendor, $warehouse] = $this->createTestVendor('reseller-edit', 'bookstore');

        $category = Category::create(['name' => 'Kinh tế', 'slug' => 'kinh-te']);
        $publisherOrg = Organization::create([
            'legal_name' => 'NXB Giáo Dục',
            'display_name' => 'NXB Giáo Dục',
            'slug' => 'nxb-giao-duc',
            'organization_types' => ['publisher'],
            'status' => 'pending_review', 'data_mode' => 'live', 'verification_document' => 'organizations/nxb-giao-duc.pdf', 'submitted_at' => now()->subDay(),
        ]);
        app(OrganizationReviewService::class)->transition($publisherOrg, 'verified', User::factory()->create(['role' => 'admin']), 'Reviewed.', 'vendor-book-publisher-org');
        $publisherRel = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $publisherOrg->id,
            'role' => 'publisher_partner',
            'status' => 'submitted', 'evidence_document' => 'organizations/relationships/op-key-test-2.pdf', 'submitted_at' => now()->subDay(), 'effective_from' => now()->subDay(),
            'operation_key' => 'op-key-test-2',
        ]);
        app(OrganizationRelationshipService::class)->transition($publisherRel, 'verified', User::factory()->create(['role' => 'admin']), 'Reviewed.', 'vendor-book-publisher-rel');

        $supplierOrg = Organization::create([
            'legal_name' => 'Công Ty Phân Phối Sách',
            'display_name' => 'Công Ty Phân Phối Sách',
            'slug' => 'cong-ty-phan-phoi-sach',
            'organization_types' => ['distributor', 'supplier'],
            'status' => 'pending_review', 'data_mode' => 'live', 'verification_document' => 'organizations/cong-ty-phan-phoi-sach.pdf', 'submitted_at' => now()->subDay(),
        ]);
        app(OrganizationReviewService::class)->transition($supplierOrg, 'verified', User::factory()->create(['role' => 'admin']), 'Reviewed.', 'vendor-book-supplier-org');
        $supplierRel = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $supplierOrg->id,
            'role' => 'supplier_partner',
            'status' => 'submitted', 'evidence_document' => 'organizations/relationships/op-key-test-3.pdf', 'submitted_at' => now()->subDay(), 'effective_from' => now()->subDay(),
            'operation_key' => 'op-key-test-3',
        ]);
        app(OrganizationRelationshipService::class)->transition($supplierRel, 'verified', User::factory()->create(['role' => 'admin']), 'Reviewed.', 'vendor-book-supplier-rel');

        $agreement = OrganizationDistributionAgreement::create([
            'publisher_organization_id' => $publisherOrg->id,
            'distributor_organization_id' => $supplierOrg->id,
            'status' => 'submitted', 'evidence_document' => 'organizations/distribution-agreements/agreement-key-1.pdf', 'submitted_at' => now()->subDay(),
            'operation_key' => 'agreement-key-1',
        ]);
        app(DistributionAgreementService::class)->transition($agreement, 'verified', User::factory()->create(['role' => 'admin']), 'Reviewed.', 'vendor-book-agreement');

        $book = Book::create([
            'vendor_id' => $vendor->id,
            'title' => 'Sách Kinh Tế',
            'slug' => 'sach-kinh-te',
            'author' => 'Tác giả B',
            'category_id' => $category->id,
            'price' => 200000,
            'stock' => 5,
            'type' => 'physical',
            'format' => 'physical',
            'provenance' => 'publisher_catalog',
            'fulfillment_mode' => 'vendor_warehouse',
            'status' => 'published',
        ]);

        $response = $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}", [
            'title' => 'Sách Kinh Tế (Đã sửa)',
            'publisher_relationship_id' => $publisherRel->id,
            'supplier_relationship_id' => $supplierRel->id,
            'responsible_organization_relationship_id' => $publisherRel->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Sách Kinh Tế (Đã sửa)',
        ]);
        $this->assertDatabaseHas('book_commercial_parties', [
            'book_id' => $book->id,
            'role' => 'publisher',
            'organization_id' => $publisherOrg->id,
        ]);
    }
}

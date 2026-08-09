<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Services\CommercialPartyService;
use App\Services\OrganizationRelationshipService;
use App\Services\OrganizationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8CommercialPartiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_publisher_assigns_explicit_roles_and_public_detail_exposes_no_private_fields(): void
    {
        [$user, $vendor] = $this->vendor('phase8-publisher', 'direct_publisher');
        $organization = $this->acceptedOrganization([
            'legal_name' => 'Công ty Xuất bản Riêng',
            'display_name' => 'NXB Bán Trực Tiếp',
            'slug' => 'nxb-ban-truc-tiep',
            'organization_types' => ['publisher', 'supplier'],
            'tax_code' => 'PRIVATE-TAX',
            'license_number' => 'PRIVATE-LICENSE',
            'verification_document' => 'private/document.pdf',
        ]);
        $relationship = $this->relationship($vendor, $organization, 'self_legal_entity');
        $book = $this->book($vendor, 'Sách NXB bán trực tiếp');

        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}/commercial-parties", [
            'publisher_relationship_id' => $relationship->id,
            'supplier_relationship_id' => $relationship->id,
            'responsible_relationship_id' => $relationship->id,
        ])->assertOk()->assertJsonCount(3, 'data.active_commercial_parties');

        $public = $this->getJson("/api/books/{$book->slug}")
            ->assertOk()
            ->assertJsonPath('data.commercial_parties.publisher.display_name', 'NXB Bán Trực Tiếp')
            ->assertJsonPath('data.commercial_parties.supplier.display_name', 'NXB Bán Trực Tiếp');
        $this->assertStringNotContainsString('PRIVATE-TAX', $public->getContent());
        $this->assertStringNotContainsString('PRIVATE-LICENSE', $public->getContent());
        $this->assertStringNotContainsString('private/document.pdf', $public->getContent());

        $snapshot = app(CommercialPartyService::class)->snapshot($book->fresh());
        $this->assertSame('direct_publisher', $snapshot['relationship_label']);
        $relationship->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->assertSame('NXB Bán Trực Tiếp', $snapshot['supplier']['display_name']);
    }

    public function test_bookstore_cannot_use_unverified_or_foreign_relationship(): void
    {
        [$user, $vendor] = $this->vendor('phase8-bookstore-parties', 'bookstore');
        [, $foreignVendor] = $this->vendor('phase8-foreign-parties', 'bookstore');
        $organization = $this->acceptedOrganization([
            'legal_name' => 'NXB Đối tác',
            'display_name' => 'NXB Đối tác',
            'slug' => 'nxb-doi-tac',
            'organization_types' => ['publisher', 'supplier'],
        ]);
        $foreign = $this->relationship($foreignVendor, $organization, 'self_legal_entity');
        $pending = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'publisher_partner',
            'status' => 'submitted',
            'operation_key' => 'phase8-pending-relation',
        ]);
        $book = $this->book($vendor, 'Sách nhà sách đa nguồn');

        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}/commercial-parties", [
            'publisher_relationship_id' => $foreign->id,
            'supplier_relationship_id' => $foreign->id,
            'responsible_relationship_id' => $foreign->id,
        ])->assertUnprocessable();
        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}/commercial-parties", [
            'publisher_relationship_id' => $pending->id,
            'supplier_relationship_id' => $pending->id,
            'responsible_relationship_id' => $pending->id,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('book_commercial_parties', 0);
    }

    private function vendor(string $slug, string $businessModel): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => "Nhà bán {$slug}",
            'slug' => $slug,
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => $businessModel,
        ]);

        return [$user, $vendor];
    }

    private function relationship(Vendor $vendor, Organization $organization, string $role): VendorOrganizationRelationship
    {
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => $role,
            'status' => 'submitted',
            'evidence_document' => 'organizations/relationships/'.$vendor->id.'-'.$organization->id.'.pdf',
            'submitted_at' => now()->subDay(),
            'effective_from' => now()->subDay(),
            'operation_key' => "phase8-relation-{$vendor->id}-{$organization->id}-{$role}",
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        return app(OrganizationRelationshipService::class)->transition($relationship, 'verified', $admin, 'Relationship evidence reviewed.', 'phase8-relation-review-'.$relationship->id);
    }

    private function acceptedOrganization(array $attributes): Organization
    {
        $organization = Organization::create([...$attributes, 'status' => 'pending_review', 'data_mode' => 'live', 'verification_document' => 'organizations/'.$attributes['slug'].'.pdf', 'submitted_at' => now()->subDay()]);

        return app(OrganizationReviewService::class)->transition($organization, 'verified', User::factory()->create(['role' => 'admin']), 'Organization evidence reviewed.', 'phase8-organization-review-'.$organization->id);
    }

    private function book(Vendor $vendor, string $title): Book
    {
        $category = Category::firstOrCreate(['slug' => 'phase8-parties'], ['name' => 'Commercial parties']);

        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.str()->random(5),
            'author' => 'Tác giả metadata',
            'description' => 'Mô tả đầy đủ',
            'cover_image' => 'books/covers/example.webp',
            'price' => 100000,
            'stock' => 5,
            'type' => 'physical',
            'provenance' => 'publisher_catalog',
            'status' => 'published',
        ]);
    }
}

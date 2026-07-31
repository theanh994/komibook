<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\OrganizationMembership;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerCommerceArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_an_organization_without_receiving_vendor_access(): void
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)->post('/api/organization-portal/organizations', [
            'legal_name' => 'Công ty Phân phối Demo',
            'display_name' => 'Nhà phân phối Demo',
            'slug' => 'nha-phan-phoi-demo',
            'organization_types' => ['supplier', 'distributor'],
            'verification_document' => UploadedFile::fake()->create('organization.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.membership.role', 'owner');

        $this->assertDatabaseHas('organization_memberships', [
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.capabilities.organization_manager', true)
            ->assertJsonPath('data.user.capabilities.active_vendor', false);
        $this->assertSame('customer', $user->fresh()->role);
    }

    public function test_distribution_agreement_requires_verified_parties_and_admin_review(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $publisher = $this->organization('publisher-demo', ['publisher'], 'pending_review');
        $distributor = $this->organization('distributor-demo', ['supplier', 'distributor'], 'verified');
        OrganizationMembership::create([
            'user_id' => $owner->id,
            'organization_id' => $distributor->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $payload = [
            'publisher_organization_id' => $publisher->id,
            'distributor_organization_id' => $distributor->id,
            'scope' => ['coverage' => 'catalog'],
            'evidence_document' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ];
        $this->actingAs($owner)->post('/api/organization-portal/distribution-agreements', $payload)
            ->assertUnprocessable();

        $publisher->update(['status' => 'verified', 'verified_at' => now()]);
        $agreementId = $this->actingAs($owner)->post('/api/organization-portal/distribution-agreements', [
            ...$payload,
            'evidence_document' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.status', 'submitted')->json('data.id');
        $this->assertDatabaseHas('organization_distribution_agreement_events', [
            'organization_distribution_agreement_id' => $agreementId,
            'from_status' => 'draft',
            'to_status' => 'submitted',
        ]);

        $this->actingAs($admin)->patchJson("/api/admin/distribution-agreements/{$agreementId}/transition", [
            'to_status' => 'verified',
        ])->assertOk()->assertJsonPath('data.status', 'verified');
        $this->assertTrue(OrganizationDistributionAgreement::findOrFail($agreementId)->isCurrentlyVerified());
    }

    public function test_payout_uses_only_the_verified_vendor_bank_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Distributor Seller',
            'slug' => 'distributor-seller',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'payout_bank_name' => 'Verified Bank',
            'payout_bank_account' => '0123456789',
            'payout_bank_holder' => 'CONG TY DEMO',
            'payout_bank_status' => 'verified',
            'payout_bank_verified_at' => now(),
        ]);
        $vendor->forceFill(['balance' => 500000])->save();

        $this->actingAs($user)->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/api/vendor/finance/payout', [
                'amount' => 100000,
                'bank_name' => 'Attacker Bank',
                'account_number' => '999999',
                'account_name' => 'ATTACKER',
                'idempotency_key' => 'partner-payout-test',
            ])->assertCreated();

        $payout = PayoutRequest::firstOrFail();
        $this->assertSame($vendor->payout_bank_name, $payout->bank_name);
        $this->assertSame($vendor->payout_bank_account, $payout->account_number);
        $this->assertSame($vendor->payout_bank_holder, $payout->account_name);
    }

    public function test_listing_with_external_publisher_requires_a_verified_distribution_agreement(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'IPM Demo',
            'slug' => 'ipm-listing-demo',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'distributor',
        ]);
        $publisher = $this->organization('publisher-listing', ['publisher'], 'verified');
        $distributor = $this->organization('distributor-listing', ['supplier', 'distributor'], 'verified');
        $publisherRelationship = $this->relationship($vendor, $publisher, 'publisher_partner');
        $supplierRelationship = $this->relationship($vendor, $distributor, 'self_legal_entity');
        $category = Category::create(['name' => 'Partner commerce', 'slug' => 'partner-commerce']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách phân phối',
            'slug' => 'sach-phan-phoi',
            'author' => 'Metadata',
            'description' => 'Mô tả',
            'cover_image' => 'books/covers/demo.webp',
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'provenance' => 'publisher_catalog',
            'status' => 'published',
        ]);
        $payload = [
            'publisher_relationship_id' => $publisherRelationship->id,
            'supplier_relationship_id' => $supplierRelationship->id,
            'responsible_relationship_id' => $supplierRelationship->id,
        ];

        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}/commercial-parties", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supplier');
        OrganizationDistributionAgreement::create([
            'publisher_organization_id' => $publisher->id,
            'distributor_organization_id' => $distributor->id,
            'status' => 'verified',
            'scope' => ['coverage' => 'catalog'],
            'verified_at' => now(),
            'effective_from' => now()->subDay(),
            'operation_key' => 'verified-listing-agreement',
        ]);

        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}/commercial-parties", $payload)
            ->assertOk()
            ->assertJsonCount(3, 'data.active_commercial_parties');
    }

    private function organization(string $slug, array $types, string $status): Organization
    {
        return Organization::create([
            'legal_name' => $slug,
            'display_name' => $slug,
            'slug' => $slug,
            'organization_types' => $types,
            'status' => $status,
            'verified_at' => $status === 'verified' ? now() : null,
        ]);
    }

    private function relationship(Vendor $vendor, Organization $organization, string $role): VendorOrganizationRelationship
    {
        return VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => $role,
            'status' => 'verified',
            'verified_at' => now(),
            'effective_from' => now()->subDay(),
            'operation_key' => "partner-commerce-{$vendor->id}-{$organization->id}-{$role}",
        ]);
    }
}

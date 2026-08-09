<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Services\BookSupplyChainRequirementResolver;
use App\Services\DistributionAgreementService;
use App\Services\OrganizationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DistributionAgreementDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_proposal_and_non_authoritative_transitions_do_not_mutate_an_existing_relationship(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $publisher = $this->organization('demo-publisher', ['publisher'], 'demo', 'demo_accepted');
        $distributor = $this->organization('demo-distributor', ['distributor'], 'demo', 'demo_accepted');
        OrganizationMembership::create([
            'user_id' => $user->id,
            'organization_id' => $publisher->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Demo vendor',
            'slug' => 'demo-vendor',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'bookstore',
            'primary_organization_id' => $publisher->id,
            'is_demo' => true,
        ]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $distributor->id,
            'role' => 'distributor',
            'status' => 'submitted',
            'is_demo' => false,
            'evidence_mode' => 'real_document',
            'scope' => ['coverage' => 'catalog'],
            'evidence_document' => 'organizations/relationships/existing.pdf',
            'effective_from' => now()->subDay(),
            'effective_until' => now()->addMonth(),
            'submitted_at' => now()->subDays(2),
            'operation_key' => 'existing-demo-partner-relationship',
        ]);
        $relationship->updateQuietly(['updated_at' => now()->subHour()]);
        $relationshipState = $this->relationshipState($relationship->fresh());

        $response = $this->actingAs($user)->postJson('/api/organization-portal/distribution-agreements', [
            'publisher_organization_id' => $publisher->id,
            'distributor_organization_id' => $distributor->id,
            'scope' => ['coverage' => 'catalog'],
        ])->assertCreated()
            ->assertJsonPath('data.is_demo', true)
            ->assertJsonPath('data.evidence_mode', 'demo_statement');

        $agreement = OrganizationDistributionAgreement::findOrFail($response->json('data.id'));
        $this->assertTrue($agreement->is_demo);
        $this->assertSame('demo_statement', $agreement->evidence_mode);
        $this->assertNotNull($agreement->demo_reference);
        $this->assertLessThanOrEqual(128, strlen($agreement->demo_reference));
        $this->assertSame(null, $agreement->evidence_document);
        $this->assertDatabaseCount('vendor_organization_relationships', 1);
        $this->assertRelationshipState($relationship->id, $relationshipState);
        $this->assertFalse(app(BookSupplyChainRequirementResolver::class)->scope($vendor->fresh())['supply_chain_ready']);

        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(DistributionAgreementService::class);
        $changesRequested = $service->transition(
            $agreement,
            'changes_requested',
            $admin,
            'Additional information is required.',
            'demo-proposal-changes-requested',
        );
        $this->assertRelationshipState($relationship->id, $relationshipState);

        $resubmitted = $service->transition(
            $changesRequested,
            'submitted',
            $user,
            operationKey: 'demo-proposal-resubmitted',
        );
        $this->assertRelationshipState($relationship->id, $relationshipState);

        $service->transition(
            $resubmitted,
            'rejected',
            $admin,
            'The proposal is rejected.',
            'demo-proposal-rejected',
        );
        $this->assertDatabaseCount('vendor_organization_relationships', 1);
        $this->assertRelationshipState($relationship->id, $relationshipState);
        $this->assertFalse(app(BookSupplyChainRequirementResolver::class)->scope($vendor->fresh())['supply_chain_ready']);
    }

    public function test_live_agreement_without_evidence_is_rejected_before_any_write(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $publisher = $this->organization('live-publisher', ['publisher'], 'live', 'verified');
        $distributor = $this->organization('live-distributor', ['distributor'], 'live', 'verified');
        OrganizationMembership::create([
            'user_id' => $user->id,
            'organization_id' => $publisher->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($user)->postJson('/api/organization-portal/distribution-agreements', [
            'publisher_organization_id' => $publisher->id,
            'distributor_organization_id' => $distributor->id,
            'scope' => ['coverage' => 'catalog'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('evidence_document');

        $this->assertDatabaseCount('organization_distribution_agreements', 0);
        $this->assertDatabaseCount('organization_distribution_agreement_events', 0);
        $this->assertDatabaseCount('vendor_organization_relationships', 0);
    }

    public function test_live_agreement_with_evidence_uses_real_document_metadata(): void
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'vendor']);
        $publisher = $this->organization('live-file-publisher', ['publisher'], 'live', 'verified');
        $distributor = $this->organization('live-file-distributor', ['distributor'], 'live', 'verified');
        OrganizationMembership::create([
            'user_id' => $user->id,
            'organization_id' => $publisher->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/organization-portal/distribution-agreements', [
            'publisher_organization_id' => $publisher->id,
            'distributor_organization_id' => $distributor->id,
            'scope' => ['coverage' => 'catalog'],
            'evidence_document' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('data.is_demo', false)
            ->assertJsonPath('data.evidence_mode', 'real_document')
            ->assertJsonPath('data.demo_reference', null);

        $agreement = OrganizationDistributionAgreement::findOrFail($response->json('data.id'));
        $this->assertFalse($agreement->is_demo);
        $this->assertSame('real_document', $agreement->evidence_mode);
        $this->assertNull($agreement->demo_reference);
        $this->assertNotNull($agreement->evidence_document);
        Storage::disk('private')->assertExists($agreement->evidence_document);
    }

    public function test_non_authoritative_parties_and_foreign_books_fail_before_agreement_event_or_file_writes(): void
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'vendor']);
        $publisher = Organization::create([
            'legal_name' => 'Raw publisher', 'display_name' => 'Raw publisher', 'slug' => 'raw-agreement-publisher',
            'organization_types' => ['publisher'], 'status' => 'verified', 'data_mode' => 'live',
        ]);
        $distributor = Organization::create([
            'legal_name' => 'Raw distributor', 'display_name' => 'Raw distributor', 'slug' => 'raw-agreement-distributor',
            'organization_types' => ['distributor'], 'status' => 'verified', 'data_mode' => 'live',
        ]);
        OrganizationMembership::create(['user_id' => $user->id, 'organization_id' => $publisher->id, 'role' => 'owner', 'status' => 'active']);
        $this->actingAs($user)->postJson('/api/organization-portal/distribution-agreements', [
            'publisher_organization_id' => $publisher->id, 'distributor_organization_id' => $distributor->id,
            'scope' => ['coverage' => 'catalog'], 'evidence_document' => UploadedFile::fake()->create('blocked.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('organizations');
        $this->assertDatabaseCount('organization_distribution_agreements', 0);
        $this->assertDatabaseCount('organization_distribution_agreement_events', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());

        $admin = User::factory()->create(['role' => 'admin']);
        $publisher = $this->organization('book-scope-publisher', ['publisher'], 'demo', 'demo_accepted');
        $distributor = $this->organization('book-scope-distributor', ['distributor'], 'demo', 'demo_accepted');
        OrganizationMembership::create(['user_id' => $user->id, 'organization_id' => $publisher->id, 'role' => 'owner', 'status' => 'active']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'shop_name' => 'Book scope vendor', 'slug' => 'book-scope-vendor',
            'status' => 'active', 'onboarding_status' => 'approved', 'primary_organization_id' => $publisher->id,
        ]);
        $foreignUser = User::factory()->create(['role' => 'vendor']);
        $foreignVendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $foreignUser->id, 'shop_name' => 'Foreign book vendor', 'slug' => 'foreign-book-vendor',
            'status' => 'active', 'onboarding_status' => 'approved',
        ]);
        $category = Category::create(['name' => 'Agreement books', 'slug' => 'agreement-books']);
        $foreignBook = Book::withoutGlobalScopes()->create([
            'vendor_id' => $foreignVendor->id, 'category_id' => $category->id, 'title' => 'Foreign book', 'slug' => 'foreign-agreement-book',
            'author' => 'Foreign', 'price' => 10000, 'stock' => 1, 'status' => 'published', 'publishing_status' => 'published',
        ]);
        $this->actingAs($user)->postJson('/api/organization-portal/distribution-agreements', [
            'publisher_organization_id' => $publisher->id, 'distributor_organization_id' => $distributor->id,
            'scope' => ['coverage' => 'books', 'book_ids' => [$foreignBook->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('scope.book_ids');
        $this->assertDatabaseCount('organization_distribution_agreements', 0);
        $this->assertDatabaseCount('organization_distribution_agreement_events', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertDatabaseCount('vendor_organization_relationships', 0);
    }

    public function test_suspended_and_revoked_agreement_do_not_create_an_absent_relationship(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $publisher = $this->organization('suspend-publisher', ['publisher'], 'live', 'verified');
        $distributor = $this->organization('suspend-distributor', ['distributor'], 'live', 'verified');
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Suspension vendor',
            'slug' => 'suspension-vendor',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'bookstore',
            'primary_organization_id' => $publisher->id,
        ]);
        $agreement = OrganizationDistributionAgreement::create([
            'publisher_organization_id' => $publisher->id,
            'distributor_organization_id' => $distributor->id,
            'status' => 'verified',
            'is_demo' => false,
            'evidence_mode' => 'real_document',
            'evidence_document' => 'organizations/distribution-agreements/live.pdf',
            'scope' => ['coverage' => 'catalog'],
            'submitted_at' => now()->subDay(),
            'verified_at' => now()->subHour(),
            'operation_key' => 'suspension-agreement',
        ]);
        $service = app(DistributionAgreementService::class);

        $suspended = $service->transition(
            $agreement,
            'suspended',
            $admin,
            'Suspended for review.',
            'suspension-agreement-suspended',
        );
        $this->assertDatabaseCount('vendor_organization_relationships', 0);

        $service->transition(
            $suspended,
            'revoked',
            $admin,
            'Revoked after review.',
            'suspension-agreement-revoked',
        );
        $this->assertDatabaseCount('vendor_organization_relationships', 0);
        $this->assertFalse(app(BookSupplyChainRequirementResolver::class)->scope($vendor->fresh())['supply_chain_ready']);
    }

    private function organization(string $slug, array $types, string $dataMode, string $status): Organization
    {
        $organization = Organization::create([
            'legal_name' => ucfirst(str_replace('-', ' ', $slug)).' Legal Entity',
            'display_name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'organization_types' => $types,
            'status' => 'pending_review',
            'data_mode' => $dataMode,
            'verification_document' => $status === 'verified' ? 'organizations/'.$slug.'.pdf' : null,
            'submitted_at' => $status === 'verified' ? now()->subDay() : null,
        ]);
        app(OrganizationReviewService::class)->transition(
            $organization,
            $status,
            User::factory()->create(['role' => 'admin']),
            'Organization evidence reviewed.',
            'distribution-organization-review-'.$slug,
        );

        return $organization->fresh();
    }

    private function relationshipState(VendorOrganizationRelationship $relationship): array
    {
        $attributes = $relationship->getRawOriginal();

        return array_intersect_key($attributes, array_flip([
            'status',
            'operation_key',
            'submitted_at',
            'verified_at',
            'revoked_at',
            'effective_from',
            'effective_until',
            'evidence_document',
            'is_demo',
            'evidence_mode',
            'demo_reference',
            'updated_at',
        ]));
    }

    private function assertRelationshipState(int $relationshipId, array $expected): void
    {
        $this->assertSame(
            $expected,
            $this->relationshipState(VendorOrganizationRelationship::findOrFail($relationshipId)),
        );
    }
}

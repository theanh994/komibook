<?php

namespace Tests\Feature;

use App\Enums\VendorOnboardingStatus;
use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\OrganizationMembership;
use App\Models\OrganizationReviewEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Models\Warehouse;
use App\Services\OrganizationRelationshipService;
use App\Services\OrganizationReviewService;
use App\Services\VendorOnboardingService;
use App\Support\AuthorityReviewFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase8IdentityAndCommercialPartiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_registers_organization_and_admin_verifies_without_public_private_fields(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        [$vendorUser] = $this->vendor('phase8-direct');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $response = $this->actingAs($vendorUser)->postJson('/api/vendor/organizations', [
            'legal_name' => 'Công ty TNHH NXB Phase 8',
            'display_name' => 'NXB Phase 8',
            'slug' => 'nxb-phase-8',
            'organization_types' => ['publisher', 'supplier'],
            'tax_code' => 'PRIVATE-TAX-CODE',
            'license_number' => 'PRIVATE-LICENSE',
            'description' => 'Hồ sơ công khai.',
            'website' => 'https://nxb-phase-8.example.test',
            'logo' => UploadedFile::fake()->image('logo.webp'),
            'verification_document' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $organizationId = $response->json('data.organization.id');
        $relationshipId = $response->json('data.relationship.id');
        $organization = Organization::findOrFail($organizationId);
        $this->assertDatabaseHas('organization_memberships', [
            'user_id' => $vendorUser->id,
            'organization_id' => $organizationId,
            'role' => 'owner',
            'status' => 'active',
        ]);
        Storage::disk('public')->assertExists($organization->logo);
        Storage::disk('private')->assertExists($organization->verification_document);
        $this->assertSame('https://nxb-phase-8.example.test', $organization->website);
        $relationship = VendorOrganizationRelationship::findOrFail($relationshipId);
        $this->assertFalse($relationship->is_demo);
        $this->assertSame('real_document', $relationship->evidence_mode);
        $this->assertSame($organization->verification_document, $relationship->evidence_document);
        $this->assertNull($relationship->demo_reference);
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])->patchJson("/api/admin/organizations/{$organizationId}/transition", [
            'to_status' => 'verified', 'reason' => 'Legal document reviewed.', 'operation_key' => 'phase8-org-verify',
        ])->assertOk();
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])->patchJson("/api/admin/organization-relationships/{$relationshipId}/transition", [
            'to_status' => 'verified', 'reason' => 'Relationship evidence reviewed.',
            'operation_key' => 'phase8-verify-self',
        ])->assertOk();

        $public = $this->getJson('/api/organizations/nxb-phase-8')
            ->assertOk()
            ->assertJsonPath('data.display_name', 'NXB Phase 8')
            ->assertJsonPath('data.website', 'https://nxb-phase-8.example.test')
            ->assertJsonMissing(['tax_code' => 'PRIVATE-TAX-CODE'])
            ->assertJsonMissing(['license_number' => 'PRIVATE-LICENSE']);
        $this->assertStringNotContainsString('verification_document', $public->getContent());
    }

    public function test_vendor_organization_submission_requires_evidence_before_any_file_or_database_write(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        [$user] = $this->vendor('phase8-evidence-required');
        $counts = [Organization::count(), OrganizationMembership::count(), VendorOrganizationRelationship::count()];

        $this->actingAs($user)->postJson('/api/vendor/organizations', [
            'legal_name' => 'Evidence Required Legal', 'display_name' => 'Evidence Required',
            'slug' => 'phase8-evidence-required-org', 'organization_types' => ['publisher'],
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('verification_document');

        $this->assertSame($counts[0], Organization::count());
        $this->assertSame($counts[1], OrganizationMembership::count());
        $this->assertSame($counts[2], VendorOrganizationRelationship::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_vendor_cannot_submit_relationship_owned_by_another_vendor(): void
    {
        [$ownerUser, $owner] = $this->vendor('phase8-owner');
        [$attackerUser] = $this->vendor('phase8-attacker');
        $organization = Organization::create([
            'legal_name' => 'Organization A',
            'display_name' => 'Organization A',
            'slug' => 'organization-a',
            'organization_types' => ['publisher'],
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => 'publisher_partner',
            'status' => 'changes_requested',
            'operation_key' => 'phase8-owner-relation',
        ]);

        $this->actingAs($attackerUser)
            ->postJson("/api/vendor/organization-relationships/{$relationship->id}/submit")
            ->assertForbidden();
        $this->assertSame('changes_requested', $relationship->fresh()->status);
    }

    public function test_live_vendor_update_resubmits_new_evidence_without_auto_reacceptance(): void
    {
        Storage::fake('private');
        [$user] = $this->vendor('phase8-rereview');
        $admin = User::factory()->create(['role' => 'admin']);
        $created = $this->actingAs($user)->postJson('/api/vendor/organizations', [
            'legal_name' => 'Re-review Legal', 'display_name' => 'Re-review', 'slug' => 'phase8-rereview-org',
            'organization_types' => ['publisher'], 'verification_document' => UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $organization = Organization::findOrFail($created->json('data.organization.id'));
        $relationship = VendorOrganizationRelationship::findOrFail($created->json('data.relationship.id'));
        app(OrganizationReviewService::class)->transition($organization, 'verified', $admin, 'Initial review.', 'rereview-org-initial');
        app(OrganizationRelationshipService::class)->transition($relationship, 'verified', $admin, 'Initial relationship review.', 'rereview-rel-initial');

        $this->actingAs($user)->post("/api/vendor/organizations/{$organization->id}", [
            '_method' => 'PATCH', 'legal_name' => 'Re-review Legal', 'display_name' => 'Re-review', 'slug' => 'phase8-rereview-org',
            'organization_types' => ['publisher'], 'verification_document' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'),
        ])->assertOk()->assertJsonPath('data.organization.status', 'pending_review')
            ->assertJsonPath('data.relationship.status', 'submitted');
        $organization = $organization->fresh();
        $relationship = $relationship->fresh();
        $this->assertSame($organization->verification_document, $relationship->evidence_document);
        $this->assertSame('real_document', $relationship->evidence_mode);
        $this->assertFalse($relationship->is_demo);
        $this->assertNull($relationship->demo_reference);
        $this->assertFalse($organization->hasAuthoritativeAcceptance());
        $this->assertFalse($relationship->isCurrentlyVerified());
    }

    public function test_organization_review_requires_recent_auth_and_is_audited_idempotently(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $organization = Organization::create([
            'legal_name' => 'Review Legal Entity', 'display_name' => 'Review Entity', 'slug' => 'review-entity',
            'organization_types' => ['publisher'], 'status' => 'pending_review', 'data_mode' => 'real',
            'verification_document' => 'organizations/review.pdf', 'submitted_at' => now()->subDay(),
        ]);
        $payload = ['to_status' => 'verified', 'reason' => 'Legal evidence accepted.', 'operation_key' => 'organization-review-idempotency'];

        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->patchJson("/api/admin/organizations/{$organization->id}/transition", $payload)->assertStatus(423);
        $this->assertDatabaseCount('organization_review_events', 0);
        $this->assertSame('pending_review', $organization->fresh()->status);

        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])->patchJson("/api/admin/organizations/{$organization->id}/transition", $payload)->assertOk();
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])->patchJson("/api/admin/organizations/{$organization->id}/transition", $payload)->assertOk();
        $this->assertDatabaseCount('organization_review_events', 1);
        $this->assertTrue($organization->fresh()->isVerified());
        $fresh = $organization->fresh();
        $this->assertSame($fresh->authority_fingerprint, AuthorityReviewFingerprint::organization($fresh));
        $this->assertSame($fresh->authority_fingerprint, $fresh->latestReviewEvent->reviewed_fingerprint);

        $originalFingerprint = $fresh->authority_fingerprint;
        $fresh->update(['verification_document' => 'organizations/review-replaced.pdf']);
        $mutated = $fresh->fresh();
        $this->assertNotSame($originalFingerprint, $mutated->authority_fingerprint);
        $this->assertSame($mutated->authority_fingerprint, AuthorityReviewFingerprint::organization($mutated));
        $this->assertNotSame($mutated->authority_fingerprint, $mutated->latestReviewEvent->reviewed_fingerprint);
        $this->assertFalse($mutated->isVerified());
        try {
            app(OrganizationReviewService::class)->transition($mutated, 'verified', $admin, $payload['reason'], $payload['operation_key']);
            $this->fail('Fingerprint-stale replay must reject.');
        } catch (ValidationException) {
            $this->assertSame(1, OrganizationReviewEvent::count());
        }

        $eventCount = OrganizationReviewEvent::count();
        foreach ([
            fn () => app(OrganizationReviewService::class)->transition($organization, 'verified', User::factory()->create(['role' => 'admin']), $payload['reason'], $payload['operation_key']),
            fn () => app(OrganizationReviewService::class)->transition($organization, 'verified', $admin, 'Different reason.', $payload['operation_key']),
            fn () => app(OrganizationReviewService::class)->transition(Organization::create(['legal_name' => 'Other', 'display_name' => 'Other', 'slug' => 'other-review-entity', 'organization_types' => ['publisher'], 'status' => 'pending_review', 'verification_document' => 'organizations/other.pdf', 'submitted_at' => now()->subDay()]), 'verified', $admin, $payload['reason'], $payload['operation_key']),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A mismatched review replay must reject.');
            } catch (ValidationException) {
                $this->assertSame($eventCount, OrganizationReviewEvent::count());
            }
        }
        $nonAdmin = User::factory()->create(['role' => 'customer']);
        $organization->update(['verified_by' => $nonAdmin->id]);
        OrganizationReviewEvent::create(['organization_id' => $organization->id, 'actor_id' => $nonAdmin->id, 'from_status' => 'verified', 'to_status' => 'verified', 'reason' => $payload['reason'], 'operation_key' => 'organization-review-non-admin-event']);
        $this->assertFalse($organization->fresh()->isVerified());
        app(OrganizationReviewService::class)->transition($organization->fresh(), 'suspended', $admin, 'Suspended after review.', 'organization-review-suspended');
        try {
            app(OrganizationReviewService::class)->transition($organization, 'verified', $admin, $payload['reason'], $payload['operation_key']);
            $this->fail('A stale review replay must reject.');
        } catch (ValidationException) {
            $this->assertSame($eventCount + 2, OrganizationReviewEvent::count());
        }
    }

    public function test_vendor_approval_does_not_accept_organization_or_relationship(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendorUser = User::factory()->create(['role' => 'customer']);
        $organization = Organization::create(['legal_name' => 'Separated Organization', 'display_name' => 'Separated Organization', 'slug' => 'separated-organization', 'organization_types' => ['publisher'], 'status' => 'pending_review']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $vendorUser->id, 'shop_name' => 'Separated Vendor', 'slug' => 'separated-vendor',
            'legal_name' => 'Separated Vendor Legal', 'tax_code' => 'SEPARATED-TAX', 'terms_accepted_at' => now(),
            'status' => 'inactive', 'onboarding_status' => 'under_review', 'business_model' => 'bookstore',
            'primary_organization_id' => $organization->id, 'is_demo' => true, 'payout_bank_status' => 'demo_disabled',
        ]);
        $relationship = VendorOrganizationRelationship::create(['vendor_id' => $vendor->id, 'organization_id' => $organization->id, 'role' => 'self_legal_entity', 'status' => 'submitted', 'is_demo' => true, 'evidence_mode' => 'demo_statement', 'demo_reference' => 'DEMO-SEPARATED', 'operation_key' => 'vendor-separation-relationship']);

        app(VendorOnboardingService::class)->transition($vendor, VendorOnboardingStatus::Approved, $admin, 'Vendor onboarding approved.', 'vendor-separation-approval');

        $this->assertSame('pending_review', $organization->fresh()->status);
        $this->assertSame('submitted', $relationship->fresh()->status);
        $this->assertDatabaseCount('organization_review_events', 0);
    }

    public function test_historical_null_reviewed_fingerprint_fails_closed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $organization = Organization::create([
            'legal_name' => 'Historical null fingerprint organization',
            'display_name' => 'Historical null fingerprint organization',
            'slug' => 'historical-null-review-fingerprint',
            'organization_types' => ['publisher'],
            'status' => 'verified',
            'data_mode' => 'real',
            'verification_document' => 'organizations/historical-null.pdf',
            'submitted_at' => now()->subDay(),
            'verified_at' => now(),
            'verified_by' => $admin->id,
            'last_review_reason' => 'Historical import review.',
        ]);

        DB::table('organization_review_events')->insert([
            'organization_id' => $organization->id,
            'actor_id' => $admin->id,
            'from_status' => 'pending_review',
            'to_status' => 'verified',
            'reason' => 'Historical import review.',
            'operation_key' => 'historical-null-review-fingerprint-event',
            'reviewed_fingerprint' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse($organization->fresh()->isVerified());
    }

    public function test_organization_portal_index_is_read_only_for_vendor_primary_organization(): void
    {
        [$vendorUser, $vendor] = $this->vendor('phase8-portal-read-only');
        $organization = Organization::create([
            'legal_name' => 'Recorded legal entity',
            'display_name' => 'Recorded organization',
            'slug' => 'phase8-portal-recorded',
            'organization_types' => ['supplier'],
            'status' => 'pending_review',
        ]);
        $vendor->update([
            'primary_organization_id' => $organization->id,
            'legal_name' => 'Vendor legal entity that must not overwrite the record',
            'tax_code' => 'VENDOR-TAX-CODE',
        ]);

        $organizationCount = Organization::count();
        $membershipCount = OrganizationMembership::count();
        $relationshipCount = VendorOrganizationRelationship::count();

        $this->actingAs($vendorUser)->getJson('/api/organization-portal')->assertOk();
        $this->actingAs($vendorUser)->getJson('/api/organization-portal')->assertOk();

        $this->assertDatabaseCount('organizations', $organizationCount);
        $this->assertDatabaseCount('organization_memberships', $membershipCount);
        $this->assertDatabaseCount('vendor_organization_relationships', $relationshipCount);
        $this->assertSame('Recorded legal entity', $organization->fresh()->legal_name);
        $this->assertSame('pending_review', $organization->fresh()->status);
        $this->assertSame(['supplier'], $organization->fresh()->organization_types);
        $this->assertSame($organization->id, $vendor->fresh()->primary_organization_id);
    }

    public function test_vendor_organizations_index_does_not_create_a_missing_organization_or_relationship(): void
    {
        [$vendorUser, $vendor] = $this->vendor('phase8-vendor-read-only');
        $vendor->update([
            'legal_name' => 'Vendor legal entity',
            'tax_code' => 'VENDOR-TAX-CODE',
        ]);

        $organizationCount = Organization::count();
        $relationshipCount = VendorOrganizationRelationship::count();

        $this->actingAs($vendorUser)->getJson('/api/vendor/organizations')->assertOk();
        $this->actingAs($vendorUser)->getJson('/api/vendor/organizations')->assertOk();

        $this->assertDatabaseCount('organizations', $organizationCount);
        $this->assertDatabaseCount('vendor_organization_relationships', $relationshipCount);
        $this->assertNull($vendor->fresh()->primary_organization_id);
    }

    public function test_organization_secrets_are_visible_only_to_exact_owner_or_admin_memberships(): void
    {
        [$user, $vendor] = $this->vendor('phase8-secret-owner');
        $own = Organization::create([
            'legal_name' => 'Owned Legal', 'display_name' => 'Owned', 'slug' => 'phase8-owned-secret',
            'organization_types' => ['publisher'], 'tax_code' => 'OWN-TAX', 'license_number' => 'OWN-LICENSE', 'status' => 'draft',
        ]);
        $partner = Organization::create([
            'legal_name' => 'Partner Legal', 'display_name' => 'Partner', 'slug' => 'phase8-partner-secret',
            'organization_types' => ['supplier'], 'tax_code' => 'PARTNER-TAX', 'license_number' => 'PARTNER-LICENSE', 'status' => 'draft',
        ]);
        $agreementOnlyPartner = Organization::create([
            'legal_name' => 'Agreement Legal', 'display_name' => 'Agreement', 'slug' => 'phase8-agreement-secret',
            'organization_types' => ['supplier'], 'tax_code' => 'AGREEMENT-TAX', 'license_number' => 'AGREEMENT-LICENSE', 'status' => 'draft',
        ]);
        $vendor->update(['primary_organization_id' => $own->id]);
        OrganizationMembership::create(['user_id' => $user->id, 'organization_id' => $own->id, 'role' => 'catalog_manager', 'status' => 'active']);
        OrganizationMembership::create(['user_id' => $user->id, 'organization_id' => $partner->id, 'role' => 'owner', 'status' => 'active']);
        VendorOrganizationRelationship::create(['vendor_id' => $vendor->id, 'organization_id' => $own->id, 'role' => 'self_legal_entity', 'status' => 'draft', 'operation_key' => 'secret-self']);
        VendorOrganizationRelationship::create(['vendor_id' => $vendor->id, 'organization_id' => $partner->id, 'role' => 'publisher_partner', 'status' => 'submitted', 'operation_key' => 'secret-partner']);
        OrganizationDistributionAgreement::create([
            'publisher_organization_id' => $own->id, 'distributor_organization_id' => $agreementOnlyPartner->id,
            'status' => 'submitted', 'scope' => ['coverage' => 'catalog'], 'submitted_at' => now(), 'operation_key' => 'secret-agreement-only',
        ]);

        $vendorResponse = $this->actingAs($user)->getJson('/api/vendor/organizations')->assertOk();
        $relationships = collect($vendorResponse->json('data.relationships'))->keyBy('organization_id');
        $this->assertArrayNotHasKey('tax_code', $relationships[$own->id]['organization']);
        $this->assertArrayNotHasKey('tax_code', $relationships[$partner->id]['organization']);

        OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $own->id)->update(['role' => 'owner']);
        $vendorResponse = $this->actingAs($user)->getJson('/api/vendor/organizations')->assertOk();
        $relationships = collect($vendorResponse->json('data.relationships'))->keyBy('organization_id');
        $this->assertSame('OWN-TAX', $relationships[$own->id]['organization']['tax_code']);
        $this->assertArrayNotHasKey('tax_code', $relationships[$partner->id]['organization']);

        $portal = $this->actingAs($user)->getJson('/api/organization-portal')->assertOk();
        $memberships = collect($portal->json('data.memberships'))->keyBy('organization_id');
        $this->assertSame('OWN-TAX', $memberships[$own->id]['organization']['tax_code']);
        $this->assertSame('PARTNER-TAX', $memberships[$partner->id]['organization']['tax_code']);
        $this->assertArrayNotHasKey('tax_code', $portal->json('data.agreements.0.distributor'));

        OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $partner->id)->update(['role' => 'catalog_manager']);
        $portal = $this->actingAs($user)->getJson('/api/organization-portal')->assertOk();
        $memberships = collect($portal->json('data.memberships'))->keyBy('organization_id');
        $this->assertArrayNotHasKey('tax_code', $memberships[$partner->id]['organization']);
    }

    public function test_onboarding_never_claims_foreign_organizations_and_keeps_authoritative_records_unchanged(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Organization::create([
            'legal_name' => 'Foreign Legal', 'display_name' => 'Foreign', 'slug' => 'foreign-onboarding-slug',
            'organization_types' => ['publisher'], 'status' => 'draft',
        ]);
        $counts = ['vendors' => Vendor::withoutGlobalScopes()->count(), 'organizations' => Organization::count(), 'memberships' => OrganizationMembership::count(), 'relationships' => VendorOrganizationRelationship::count()];
        $this->actingAs($user)->patchJson('/api/vendor-onboarding/draft', [
            'shop_name' => 'Foreign collision', 'slug' => 'foreign-onboarding-slug', 'legal_name' => 'Collision Legal', 'tax_code' => 'COLLISION',
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->assertSame($counts['vendors'], Vendor::withoutGlobalScopes()->count());
        $this->assertSame($counts['organizations'], Organization::count());
        $this->assertSame($counts['memberships'], OrganizationMembership::count());
        $this->assertSame($counts['relationships'], VendorOrganizationRelationship::count());

        $foreignVendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'shop_name' => 'Foreign primary', 'slug' => 'foreign-primary-vendor',
            'legal_name' => 'Foreign primary legal', 'tax_code' => 'FOREIGN-PRIMARY', 'status' => 'inactive',
            'onboarding_status' => 'draft', 'primary_organization_id' => Organization::query()->where('slug', 'foreign-onboarding-slug')->value('id'), 'is_demo' => true,
        ]);
        $vendorRaw = $foreignVendor->fresh()->getRawOriginal();
        $this->actingAs($user)->patchJson('/api/vendor-onboarding/draft', ['shop_name' => 'Changed', 'slug' => 'changed-foreign-primary', 'legal_name' => 'Changed legal', 'tax_code' => 'CHANGED'])
            ->assertUnprocessable()->assertJsonValidationErrors('organization');
        $this->assertSame($vendorRaw, $foreignVendor->fresh()->getRawOriginal());

        $admin = User::factory()->create(['role' => 'admin']);
        $ownedUser = User::factory()->create(['role' => 'customer']);
        $organization = Organization::create([
            'legal_name' => 'Protected Demo', 'display_name' => 'Protected Demo', 'slug' => 'protected-demo-bootstrap',
            'organization_types' => ['publisher'], 'status' => 'pending_review', 'data_mode' => 'demo',
        ]);
        app(OrganizationReviewService::class)->transition($organization, 'demo_accepted', $admin, 'Demo accepted.', 'protected-demo-org-review');
        $ownedVendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $ownedUser->id, 'shop_name' => 'Protected vendor', 'slug' => 'protected-vendor', 'legal_name' => 'Protected Legal',
            'tax_code' => 'PROTECTED', 'status' => 'inactive', 'onboarding_status' => 'draft', 'primary_organization_id' => $organization->id, 'is_demo' => true,
        ]);
        OrganizationMembership::create(['user_id' => $ownedUser->id, 'organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $ownedVendor->id, 'organization_id' => $organization->id, 'role' => 'self_legal_entity', 'status' => 'submitted',
            'is_demo' => true, 'evidence_mode' => 'demo_statement', 'demo_reference' => 'PROTECTED-DEMO-REL', 'submitted_at' => now()->subDay(), 'operation_key' => 'protected-demo-rel',
        ]);
        app(OrganizationRelationshipService::class)->transition($relationship, 'demo_accepted', $admin, 'Demo relation accepted.', 'protected-demo-rel-review');
        $organizationRaw = $organization->fresh()->getRawOriginal();
        $relationshipRaw = $relationship->fresh()->getRawOriginal();
        $this->actingAs($ownedUser)->patchJson('/api/vendor-onboarding/draft', ['shop_name' => 'Attempt draft', 'slug' => 'attempt-draft', 'legal_name' => 'Attempt', 'tax_code' => 'ATTEMPT'])
            ->assertOk();
        $this->assertSame('Attempt draft', $ownedVendor->fresh()->shop_name);
        $this->actingAs($ownedUser)->postJson('/api/vendor-onboarding/register', ['shop_name' => 'Attempt register', 'slug' => 'attempt-register', 'legal_name' => 'Attempt', 'tax_code' => 'ATTEMPT', 'terms_accepted' => true])
            ->assertOk();
        $this->assertSame('Attempt register', $ownedVendor->fresh()->shop_name);
        $this->assertSame($organizationRaw, $organization->fresh()->getRawOriginal());
        $this->assertSame($relationshipRaw, $relationship->fresh()->getRawOriginal());
    }

    public function test_onboarding_bootstrap_stays_draft_then_submits_without_duplicate_records(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'status' => 'inactive',
            'onboarding_status' => 'draft',
            'is_demo' => true,
            'payout_bank_status' => 'demo_disabled',
        ]);
        $draft = ['shop_name' => 'Bootstrap Demo', 'slug' => 'bootstrap-demo-vendor', 'legal_name' => 'Bootstrap Demo Legal', 'tax_code' => 'BOOTSTRAP', 'business_model' => 'bookstore'];
        $this->actingAs($user)->patchJson('/api/vendor-onboarding/draft', $draft)->assertOk();
        $vendor = Vendor::withoutGlobalScopes()->where('user_id', $user->id)->sole();
        $organization = Organization::findOrFail($vendor->primary_organization_id);
        $relationship = VendorOrganizationRelationship::where('vendor_id', $vendor->id)->sole();
        $this->assertSame('draft', $organization->status);
        $this->assertSame('draft', $relationship->status);
        $this->assertNull($organization->verified_by);
        $this->assertNull($relationship->reviewed_by);
        $this->assertDatabaseCount('organization_review_events', 0);
        $this->assertDatabaseCount('organization_relationship_events', 0);

        $register = [...$draft, 'terms_accepted' => true, 'operation_key' => 'bootstrap-submit-retry'];
        $this->actingAs($user)->postJson('/api/vendor-onboarding/register', $register)->assertOk();
        $this->assertSame('pending_review', $organization->fresh()->status);
        $this->assertSame('submitted', $relationship->fresh()->status);
        $this->assertNotNull($organization->fresh()->submitted_at);
        $this->assertNotNull($relationship->fresh()->submitted_at);
        $this->actingAs($user)->postJson('/api/vendor-onboarding/submit', ['operation_key' => 'bootstrap-submit-retry'])->assertOk();
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('organization_memberships', 1);
        $this->assertDatabaseCount('vendor_organization_relationships', 1);
    }

    public function test_warehouse_manager_accepts_scoped_invitation_and_cannot_read_foreign_assignment(): void
    {
        [$vendorUser, $vendor] = $this->vendor('phase8-warehouse');
        [$otherVendorUser, $otherVendor] = $this->vendor('phase8-other');
        $warehouse = $this->warehouse($vendor, 'Kho được giao');
        $otherWarehouse = $this->warehouse($otherVendor, 'Kho không được giao');
        $manager = User::factory()->create(['role' => 'customer', 'email' => 'warehouse.manager@example.test']);
        $otherManager = User::factory()->create(['role' => 'customer']);

        $invite = $this->actingAs($vendorUser)->postJson('/api/vendor/warehouse-managers/invite', [
            'email' => $manager->email,
            'warehouse_id' => $warehouse->id,
            'capabilities' => ['view_inventory', 'receive_stock'],
        ])->assertCreated();
        $assignmentId = $invite->json('data.id');
        $token = $invite->json('invitation_token');
        $otherInvite = $this->actingAs($otherVendorUser)->postJson('/api/vendor/warehouse-managers/invite', [
            'email' => $otherManager->email,
            'warehouse_id' => $otherWarehouse->id,
            'capabilities' => ['view_inventory'],
        ])->assertCreated();

        $this->actingAs($vendorUser)->patchJson("/api/vendor/warehouse-managers/{$assignmentId}/transition", [
            'to_status' => 'active',
        ])->assertForbidden();
        $this->actingAs($manager)->postJson("/api/warehouse-manager/assignments/{$assignmentId}/accept", [
            'invitation_token' => $token,
            'operation_key' => 'phase8-manager-accept',
        ])->assertOk();
        $this->actingAs($manager)->getJson('/api/warehouse-manager/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.warehouse_id', $warehouse->id);
        $this->actingAs($manager)
            ->getJson('/api/warehouse-manager/assignments/'.$otherInvite->json('data.id').'/dashboard')
            ->assertForbidden();
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

    private function warehouse(Vendor $vendor, string $name): Warehouse
    {
        return Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'address' => 'Địa chỉ riêng tư chỉ dùng trong test',
            'status' => 'Hoạt động',
        ]);
    }
}

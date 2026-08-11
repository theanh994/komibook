<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCommercialParty;
use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\OrganizationDistributionAgreementEvent;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRelationshipEvent;
use App\Models\OrganizationReviewEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Services\LegacyDemoAuthorityRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegacyDemoAuthorityRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_is_zero_write_and_has_a_stable_digest(): void
    {
        $this->legacySelfRelationship();
        $service = app(LegacyDemoAuthorityRepairService::class);

        $first = $service->inspect();
        $second = $service->inspect();

        $this->assertSame($first['digest'], $second['digest']);
        $this->assertSame(1, $first['candidates']['organization_ids'][0]);
        $this->assertSame(1, $first['candidates']['relationship_ids'][0]);
        $this->assertDatabaseCount('organization_review_events', 0);
        $this->assertDatabaseCount('organization_relationship_events', 0);
    }

    public function test_apply_requires_admin_reason_and_current_digest_then_is_idempotent(): void
    {
        [, $relationship] = $this->legacySelfRelationship();
        $service = app(LegacyDemoAuthorityRepairService::class);
        $manifest = $service->inspect();
        $admin = User::factory()->create(['role' => 'admin']);

        try {
            $service->apply($admin->id, '', $manifest['digest']);
            $this->fail('A reason must be required.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('organization_review_events', 0);
        }

        $service->apply($admin->id, 'Recover repository-proven demo authority.', $service->inspect()['digest']);
        $this->assertTrue(Organization::findOrFail(1)->hasAuthoritativeAcceptance());
        $this->assertTrue($relationship->fresh(['organization'])->isCurrentlyVerified());
        $this->assertSame('demo_accepted', $relationship->fresh()->status);
        $this->assertNull($relationship->fresh()->verified_at);
        $this->assertDatabaseCount('organization_review_events', 1);
        $this->assertDatabaseCount('organization_relationship_events', 1);

        $service->apply($admin->id, 'Recover repository-proven demo authority.', $service->inspect()['digest']);
        $this->assertDatabaseCount('organization_review_events', 1);
        $this->assertDatabaseCount('organization_relationship_events', 1);
    }

    public function test_unproven_mapping_prefix_is_conflict_and_is_not_repaired(): void
    {
        [, $relationship] = $this->legacySelfRelationship('demo-mapping:rel:ipm:hongduc');
        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();

        $this->assertSame([], $manifest['candidates']['relationship_ids']);
        $this->assertSame('unproven_mapping_prefix', $manifest['conflicts'][0]['reason_code']);
        $this->assertSame(0, $manifest['blocking_conflict_count']);
        $this->assertNull($relationship->fresh()->reviewed_by);
    }

    public function test_eligible_source_mutation_invalidates_the_dry_run_digest(): void
    {
        [, $relationship] = $this->legacySelfRelationship();
        $service = app(LegacyDemoAuthorityRepairService::class);
        $digest = $service->inspect()['digest'];
        $relationship->update(['demo_reference' => 'DEMO-SELF-CHANGED']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->expectException(ValidationException::class);
        $service->apply($admin->id, 'Recover repository-proven demo authority.', $digest);
    }

    public function test_raw_fingerprint_input_mutation_invalidates_the_dry_run_digest(): void
    {
        [, $relationship] = $this->legacySelfRelationship();
        $service = app(LegacyDemoAuthorityRepairService::class);
        $digest = $service->inspect()['digest'];
        DB::table('vendor_organization_relationships')->where('id', $relationship->id)->update(['submitted_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->expectException(ValidationException::class);
        $service->apply($admin->id, 'Recover repository-proven demo authority.', $digest);
    }

    public function test_self_key_ids_must_bind_to_the_relationship_rows(): void
    {
        [, $relationship] = $this->legacySelfRelationship('demo-self-organization:999:888');

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();

        $this->assertSame([], $manifest['candidates']['relationship_ids']);
        $this->assertSame($relationship->id, $manifest['conflicts'][0]['id']);
        $this->assertSame('self_relationship_mismatch', $manifest['conflicts'][0]['reason_code']);
    }

    public function test_vendor_user_must_be_the_catalog_owner_membership_user(): void
    {
        [, $relationship, $vendor] = $this->legacySelfRelationship();
        $vendor->update(['user_id' => User::factory()->create(['role' => 'vendor'])->id]);

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();
        $conflict = collect($manifest['conflicts'])->firstWhere('id', $relationship->id);

        $this->assertSame([], $manifest['candidates']['relationship_ids']);
        $this->assertSame('self_relationship_mismatch', $conflict['reason_code']);
    }

    public function test_mapping_key_slugs_must_bind_to_the_actual_organizations(): void
    {
        [, , $vendor] = $this->legacySelfRelationship();
        $publisher = Organization::create([
            'legal_name' => 'Publisher Demo', 'display_name' => 'Publisher Demo', 'slug' => 'publisher-demo',
            'organization_types' => ['publisher'], 'status' => 'verified', 'data_mode' => 'demo', 'verified_at' => now(),
        ]);
        $mapping = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id, 'organization_id' => $publisher->id, 'role' => 'publisher_partner',
            'status' => 'verified', 'is_demo' => true, 'evidence_mode' => 'demo_statement', 'demo_reference' => 'DEMO-REL-IPM-PUBLISHER',
            'scope' => ['coverage' => 'catalog', 'notice' => 'simulated'], 'verified_at' => now(),
            'operation_key' => 'demo-mapping:relationship:ipm-demo:not-the-publisher',
        ]);

        $conflict = collect(app(LegacyDemoAuthorityRepairService::class)->inspect()['conflicts'])->firstWhere('id', $mapping->id);

        $this->assertSame('mapping_relationship_incoherent', $conflict['reason_code']);
    }

    public function test_well_shaped_non_catalog_self_identity_is_not_a_repair_candidate(): void
    {
        $owner = User::factory()->create(['role' => 'vendor', 'email' => 'unlisted.demo@komibook.id.vn']);
        $organization = Organization::create([
            'legal_name' => 'Unlisted Demo', 'display_name' => 'Unlisted Demo', 'slug' => 'unlisted-demo',
            'organization_types' => ['distributor', 'supplier', 'bookstore'], 'status' => 'verified', 'data_mode' => 'demo', 'verified_at' => now(),
        ]);
        OrganizationMembership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $owner->id, 'status' => 'inactive', 'onboarding_status' => 'draft', 'business_model' => 'distributor',
            'primary_organization_id' => $organization->id, 'is_demo' => true, 'payout_bank_status' => 'demo_disabled',
        ]);
        $vendor->update(['demo_wallet_code' => 'DEMO-VENDOR-'.str_pad((string) $vendor->id, 4, '0', STR_PAD_LEFT)]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id, 'organization_id' => $organization->id, 'role' => 'self_legal_entity',
            'status' => 'verified', 'is_demo' => true, 'evidence_mode' => 'demo_statement', 'demo_reference' => 'DEMO-SELF-UNLISTED',
            'verified_at' => now(), 'operation_key' => "demo-self-organization:{$vendor->id}:{$organization->id}",
        ]);

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();
        $conflict = collect($manifest['conflicts'])->firstWhere('id', $relationship->id);

        $this->assertSame([], $manifest['candidates']['relationship_ids']);
        $this->assertSame('self_relationship_mismatch', $conflict['reason_code']);
    }

    public function test_well_shaped_non_catalog_mapping_pair_is_not_a_repair_candidate(): void
    {
        [, , $vendor] = $this->legacySelfRelationship();
        $owner = User::factory()->create(['role' => 'vendor', 'email' => 'unlisted.publisher@komibook.id.vn']);
        $publisher = Organization::create([
            'legal_name' => 'Unlisted Publisher', 'display_name' => 'Unlisted Publisher', 'slug' => 'unlisted-publisher-demo',
            'organization_types' => ['publisher'], 'status' => 'verified', 'data_mode' => 'demo', 'verified_at' => now(),
        ]);
        OrganizationMembership::create(['user_id' => $owner->id, 'organization_id' => $publisher->id, 'role' => 'owner', 'status' => 'active']);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id, 'organization_id' => $publisher->id, 'role' => 'publisher_partner',
            'status' => 'verified', 'is_demo' => true, 'evidence_mode' => 'demo_statement', 'demo_reference' => 'DEMO-REL-IPM-UNLISTED',
            'scope' => ['coverage' => 'catalog', 'notice' => 'simulated'], 'verified_at' => now(),
            'operation_key' => 'demo-mapping:relationship:ipm-demo:unlisted-publisher-demo',
        ]);

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();
        $conflict = collect($manifest['conflicts'])->firstWhere('id', $relationship->id);

        $this->assertNotContains($relationship->id, $manifest['candidates']['relationship_ids']);
        $this->assertSame('mapping_relationship_incoherent', $conflict['reason_code']);
    }

    public function test_exact_mapping_relationship_without_matching_agreement_is_not_a_repair_candidate(): void
    {
        [, , $vendor] = $this->legacySelfRelationship();
        $owner = User::factory()->create(['role' => 'vendor', 'email' => 'nxblaodong.demo@komibook.id.vn']);
        $publisher = Organization::create([
            'legal_name' => 'Lao Dong Demo', 'display_name' => 'Lao Dong Demo', 'slug' => 'nxb-lao-dong-demo',
            'organization_types' => ['publisher'], 'status' => 'verified', 'data_mode' => 'demo', 'verified_at' => now(),
        ]);
        OrganizationMembership::create(['user_id' => $owner->id, 'organization_id' => $publisher->id, 'role' => 'owner', 'status' => 'active']);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id, 'organization_id' => $publisher->id, 'role' => 'publisher_partner',
            'status' => 'verified', 'is_demo' => true, 'evidence_mode' => 'demo_statement', 'demo_reference' => 'DEMO-REL-IPM-LAO-DONG',
            'scope' => ['coverage' => 'catalog', 'notice' => 'simulated'], 'verified_at' => now(),
            'operation_key' => 'demo-mapping:relationship:ipm-demo:nxb-lao-dong-demo',
        ]);

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();
        $conflict = collect($manifest['conflicts'])->firstWhere('id', $relationship->id);

        $this->assertNotContains($relationship->id, $manifest['candidates']['relationship_ids']);
        $this->assertSame([], $manifest['candidates']['agreement_ids']);
        $this->assertSame('mapping_chain_incomplete', $conflict['reason_code']);
    }

    public function test_conflicting_recovery_event_fails_closed_without_partial_write(): void
    {
        [$organization] = $this->legacySelfRelationship();
        $admin = User::factory()->create(['role' => 'admin']);
        OrganizationReviewEvent::create([
            'organization_id' => $organization->id,
            'actor_id' => $admin->id,
            'from_status' => 'demo_accepted',
            'to_status' => 'demo_accepted',
            'reason' => 'Other audit reason.',
            'operation_key' => 'legacy-demo-authority-repair:organization:'.$organization->id,
            'reviewed_fingerprint' => $organization->authority_fingerprint,
        ]);
        $service = app(LegacyDemoAuthorityRepairService::class);

        try {
            $service->apply($admin->id, 'Recover repository-proven demo authority.', $service->inspect()['digest']);
            $this->fail('A conflicting recovery event must fail closed.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('organization_relationship_events', 0);
            $this->assertNull($organization->fresh()->verified_by);
        }
    }

    public function test_linked_parties_are_canonicalized_without_reactivating_inactive_history(): void
    {
        [$organization, $relationship, $vendor] = $this->legacySelfRelationship();
        $category = Category::create(['name' => 'Demo', 'slug' => 'demo']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Demo book',
            'slug' => 'demo-book',
            'author' => 'Demo author',
            'price' => 10000,
            'stock' => 1,
            'status' => 'published',
        ]);
        $active = BookCommercialParty::create([
            'book_id' => $book->id,
            'organization_id' => $organization->id,
            'vendor_organization_relationship_id' => $relationship->id,
            'role' => 'supplier',
            'status' => 'verified',
            'version' => 4,
            'active_slot' => 'active',
            'effective_at' => now()->subDay(),
            'verified_at' => now(),
        ]);
        $inactive = BookCommercialParty::create([
            'book_id' => $book->id,
            'organization_id' => $organization->id,
            'vendor_organization_relationship_id' => $relationship->id,
            'role' => 'responsible_organization',
            'status' => 'verified',
            'version' => 3,
            'ended_at' => now()->subDay(),
            'effective_at' => now()->subDays(2),
            'verified_at' => now(),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(LegacyDemoAuthorityRepairService::class);

        $service->apply($admin->id, 'Recover repository-proven demo authority.', $service->inspect()['digest']);

        $this->assertSame('demo_accepted', $active->fresh()->status);
        $this->assertNull($active->fresh()->verified_at);
        $this->assertSame(4, $active->fresh()->version);
        $this->assertSame('active', $active->fresh()->active_slot);
        $this->assertSame('demo_accepted', $inactive->fresh()->status);
        $this->assertNotNull($inactive->fresh()->ended_at);
        $this->assertNull($inactive->fresh()->active_slot);
    }

    public function test_tenant_mismatch_is_a_blocking_conflict_and_is_excluded(): void
    {
        [$organization, $relationship] = $this->legacySelfRelationship();
        $otherOwner = User::factory()->create(['role' => 'vendor']);
        $otherVendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $otherOwner->id,
            'status' => 'inactive',
            'onboarding_status' => 'draft',
            'business_model' => 'bookstore',
            'is_demo' => true,
            'demo_wallet_code' => 'DEMO-OTHER-001',
            'payout_bank_status' => 'demo_disabled',
        ]);
        $category = Category::create(['name' => 'Mismatch', 'slug' => 'mismatch']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $otherVendor->id,
            'category_id' => $category->id,
            'title' => 'Foreign book',
            'slug' => 'foreign-book',
            'author' => 'Demo author',
            'price' => 10000,
            'stock' => 1,
        ]);
        BookCommercialParty::create([
            'book_id' => $book->id,
            'organization_id' => $organization->id,
            'vendor_organization_relationship_id' => $relationship->id,
            'role' => 'supplier',
            'status' => 'verified',
        ]);

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();

        $this->assertSame([], $manifest['candidates']['book_commercial_party_ids']);
        $this->assertSame('book_party_vendor_mismatch', $manifest['conflicts'][0]['reason_code']);
        $this->assertSame(1, $manifest['blocking_conflict_count']);
    }

    public function test_noncanonical_party_status_is_conflict_and_never_promoted(): void
    {
        [$organization, $relationship, $vendor] = $this->legacySelfRelationship();
        $category = Category::create(['name' => 'Pending', 'slug' => 'pending']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'title' => 'Pending party',
            'slug' => 'pending-party', 'author' => 'Demo author', 'price' => 10000, 'stock' => 1,
        ]);
        $party = BookCommercialParty::create([
            'book_id' => $book->id, 'organization_id' => $organization->id,
            'vendor_organization_relationship_id' => $relationship->id, 'role' => 'supplier', 'status' => 'pending',
        ]);

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();
        $conflict = collect($manifest['conflicts'])->firstWhere('id', $party->id);

        $this->assertSame([], $manifest['candidates']['book_commercial_party_ids']);
        $this->assertSame('book_party_status_ineligible', $conflict['reason_code']);
        $this->assertSame(1, $manifest['blocking_conflict_count']);
    }

    public function test_live_vendor_excludes_even_an_exact_demo_key_and_its_book_party(): void
    {
        [$organization, $relationship, $vendor] = $this->legacySelfRelationship();
        $vendor->update(['is_demo' => false]);
        $category = Category::create(['name' => 'Live vendor', 'slug' => 'live-vendor']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'title' => 'Blocked party',
            'slug' => 'blocked-party', 'author' => 'Demo author', 'price' => 10000, 'stock' => 1,
        ]);
        $party = BookCommercialParty::create([
            'book_id' => $book->id, 'organization_id' => $organization->id,
            'vendor_organization_relationship_id' => $relationship->id, 'role' => 'supplier', 'status' => 'verified',
        ]);

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();

        $this->assertSame([], $manifest['candidates']['relationship_ids']);
        $this->assertSame([], $manifest['candidates']['book_commercial_party_ids']);
        $this->assertSame('self_relationship_mismatch', $manifest['conflicts'][0]['reason_code']);
        $this->assertSame('verified', $party->fresh()->status);
    }

    public function test_vendor_mode_flip_invalidates_demo_relationship_and_hides_active_party(): void
    {
        [$organization, $relationship, $vendor] = $this->legacySelfRelationship();
        $category = Category::create(['name' => 'Visibility', 'slug' => 'visibility']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'title' => 'Visible party',
            'slug' => 'visible-party', 'author' => 'Demo author', 'price' => 10000, 'stock' => 1, 'status' => 'published',
        ]);
        $party = BookCommercialParty::create([
            'book_id' => $book->id, 'organization_id' => $organization->id,
            'vendor_organization_relationship_id' => $relationship->id, 'role' => 'supplier',
            'status' => 'verified', 'active_slot' => 'active', 'effective_at' => now(), 'verified_at' => now(),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(LegacyDemoAuthorityRepairService::class);
        $service->apply($admin->id, 'Recover repository-proven demo authority.', $service->inspect()['digest']);

        $this->assertTrue($relationship->fresh(['organization'])->isCurrentlyVerified());
        $this->assertTrue(Book::withoutGlobalScopes()->findOrFail($book->id)->activeCommercialParties()->whereKey($party->id)->exists());

        $vendor->update(['is_demo' => false]);

        $this->assertFalse($relationship->fresh(['organization'])->isCurrentlyVerified());
        $this->assertFalse(Book::withoutGlobalScopes()->findOrFail($book->id)->activeCommercialParties()->whereKey($party->id)->exists());
    }

    public function test_proven_mapping_agreement_is_recovered_with_a_same_state_event(): void
    {
        [, , $vendor] = $this->legacySelfRelationship();
        $owner = User::factory()->create(['role' => 'vendor', 'email' => 'nxblaodong.demo@komibook.id.vn']);
        $publisher = Organization::create([
            'legal_name' => 'Publisher Demo',
            'display_name' => 'Publisher Demo',
            'slug' => 'nxb-lao-dong-demo',
            'organization_types' => ['publisher'],
            'status' => 'verified',
            'data_mode' => 'demo',
            'verified_at' => now(),
        ]);
        OrganizationMembership::create(['user_id' => $owner->id, 'organization_id' => $publisher->id, 'role' => 'owner', 'status' => 'active']);
        VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $publisher->id,
            'role' => 'publisher_partner',
            'status' => 'verified',
            'is_demo' => true,
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-REL-IPM-LAO-DONG',
            'scope' => ['coverage' => 'catalog', 'notice' => 'simulated'],
            'verified_at' => now(),
            'operation_key' => 'demo-mapping:relationship:ipm-demo:nxb-lao-dong-demo',
        ]);
        $agreement = OrganizationDistributionAgreement::create([
            'publisher_organization_id' => $publisher->id,
            'distributor_organization_id' => $vendor->primary_organization_id,
            'status' => 'verified',
            'is_demo' => true,
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-AGR-IPM-LAO-DONG',
            'scope' => ['coverage' => 'catalog', 'notice' => 'simulated'],
            'verified_at' => now(),
            'operation_key' => 'demo-mapping:agreement:ipm-demo:nxb-lao-dong-demo',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(LegacyDemoAuthorityRepairService::class);

        $service->apply($admin->id, 'Recover repository-proven demo authority.', $service->inspect()['digest']);

        $this->assertSame('demo_accepted', $agreement->fresh()->status);
        $this->assertNull($agreement->fresh()->verified_at);
        $this->assertSame(1, OrganizationDistributionAgreementEvent::where('organization_distribution_agreement_id', $agreement->id)->count());
    }

    public function test_superseded_recovery_authority_blocks_apply_when_a_repaired_mapping_chain_loses_provenance(): void
    {
        [, , , $publisher, $mapping, $agreement] = $this->provenIpmLaoDongMappingChain();
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(LegacyDemoAuthorityRepairService::class);
        $service->apply($admin->id, 'Recover repository-proven demo authority.', $service->inspect()['digest']);
        $mapping->update(['demo_reference' => 'NOT-A-DEMO-REFERENCE']);

        $manifest = $service->inspect();
        $superseded = collect($manifest['conflicts'])->where('reason_code', 'superseded_recovery_authority');
        $eventCounts = [
            OrganizationReviewEvent::count(),
            OrganizationRelationshipEvent::count(),
            OrganizationDistributionAgreementEvent::count(),
        ];

        $this->assertGreaterThan(0, $manifest['blocking_conflict_count']);
        $this->assertTrue($superseded->contains(fn (array $conflict) => $conflict['entity'] === 'organization' && $conflict['id'] === $publisher->id));
        $this->assertTrue($superseded->contains(fn (array $conflict) => $conflict['entity'] === 'relationship' && $conflict['id'] === $mapping->id));
        $this->assertTrue($superseded->contains(fn (array $conflict) => $conflict['entity'] === 'agreement' && $conflict['id'] === $agreement->id));

        try {
            $service->apply($admin->id, 'Recover repository-proven demo authority.', $manifest['digest']);
            $this->fail('Superseded recovery authority must block repair before writes.');
        } catch (ValidationException) {
            $this->assertSame($eventCounts, [
                OrganizationReviewEvent::count(),
                OrganizationRelationshipEvent::count(),
                OrganizationDistributionAgreementEvent::count(),
            ]);
        }
    }

    public function test_known_legacy_kim_dong_type_variant_is_a_repair_candidate(): void
    {
        [, $relationship] = $this->legacyKimDongSelfRelationship(['publisher', 'supplier', 'bookstore']);

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();

        $this->assertSame([$relationship->id], $manifest['candidates']['relationship_ids']);
    }

    public function test_kim_dong_unlisted_extra_type_is_not_a_repair_candidate(): void
    {
        [, $relationship] = $this->legacyKimDongSelfRelationship(['publisher', 'supplier', 'warehouse']);

        $manifest = app(LegacyDemoAuthorityRepairService::class)->inspect();
        $conflict = collect($manifest['conflicts'])->firstWhere('id', $relationship->id);

        $this->assertSame([], $manifest['candidates']['relationship_ids']);
        $this->assertSame('self_relationship_mismatch', $conflict['reason_code']);
    }

    public function test_recovery_event_fails_closed_after_fingerprint_invalidation(): void
    {
        [$organization] = $this->legacySelfRelationship();
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(LegacyDemoAuthorityRepairService::class);
        $reason = 'Recover repository-proven demo authority.';
        $service->apply($admin->id, $reason, $service->inspect()['digest']);
        $organization->update(['display_name' => 'Changed after recovery']);

        try {
            $service->apply($admin->id, $reason, $service->inspect()['digest']);
            $this->fail('An invalidated fingerprint must not replay a recovery event.');
        } catch (ValidationException) {
            $this->assertSame(1, OrganizationReviewEvent::where('organization_id', $organization->id)->count());
        }
    }

    /** @return array{Organization, VendorOrganizationRelationship, Vendor} */
    private function legacySelfRelationship(?string $operationKey = null): array
    {
        $owner = User::factory()->create(['role' => 'vendor', 'email' => 'ipm.demo@komibook.id.vn']);
        $organization = Organization::create([
            'legal_name' => 'IPM Demo',
            'display_name' => 'IPM Demo',
            'slug' => 'ipm-demo',
            'organization_types' => ['distributor', 'supplier', 'bookstore'],
            'status' => 'verified',
            'data_mode' => 'demo',
            'verified_at' => now(),
        ]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'status' => 'inactive',
            'onboarding_status' => 'draft',
            'business_model' => 'distributor',
            'primary_organization_id' => $organization->id,
            'is_demo' => true,
            'payout_bank_status' => 'demo_disabled',
        ]);
        $vendor->update(['demo_wallet_code' => 'DEMO-VENDOR-'.str_pad((string) $vendor->id, 4, '0', STR_PAD_LEFT)]);
        OrganizationMembership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'verified',
            'is_demo' => true,
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-SELF-0001',
            'verified_at' => now(),
            'operation_key' => $operationKey ?? "demo-self-organization:{$vendor->id}:{$organization->id}",
        ]);

        return [$organization, $relationship, $vendor];
    }

    /** @return array{Organization, VendorOrganizationRelationship, Vendor, Organization, VendorOrganizationRelationship, OrganizationDistributionAgreement} */
    private function provenIpmLaoDongMappingChain(): array
    {
        [$organization, $selfRelationship, $vendor] = $this->legacySelfRelationship();
        $owner = User::factory()->create(['role' => 'vendor', 'email' => 'nxblaodong.demo@komibook.id.vn']);
        $publisher = Organization::create([
            'legal_name' => 'Publisher Demo', 'display_name' => 'Publisher Demo', 'slug' => 'nxb-lao-dong-demo',
            'organization_types' => ['publisher'], 'status' => 'verified', 'data_mode' => 'demo', 'verified_at' => now(),
        ]);
        OrganizationMembership::create(['user_id' => $owner->id, 'organization_id' => $publisher->id, 'role' => 'owner', 'status' => 'active']);
        $mapping = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id, 'organization_id' => $publisher->id, 'role' => 'publisher_partner',
            'status' => 'verified', 'is_demo' => true, 'evidence_mode' => 'demo_statement', 'demo_reference' => 'DEMO-REL-IPM-LAO-DONG',
            'scope' => ['coverage' => 'catalog', 'notice' => 'simulated'], 'verified_at' => now(),
            'operation_key' => 'demo-mapping:relationship:ipm-demo:nxb-lao-dong-demo',
        ]);
        $agreement = OrganizationDistributionAgreement::create([
            'publisher_organization_id' => $publisher->id, 'distributor_organization_id' => $organization->id,
            'status' => 'verified', 'is_demo' => true, 'evidence_mode' => 'demo_statement', 'demo_reference' => 'DEMO-AGR-IPM-LAO-DONG',
            'scope' => ['coverage' => 'catalog', 'notice' => 'simulated'], 'verified_at' => now(),
            'operation_key' => 'demo-mapping:agreement:ipm-demo:nxb-lao-dong-demo',
        ]);

        return [$organization, $selfRelationship, $vendor, $publisher, $mapping, $agreement];
    }

    /** @param list<string> $organizationTypes @return array{Organization, VendorOrganizationRelationship, Vendor} */
    private function legacyKimDongSelfRelationship(array $organizationTypes): array
    {
        $owner = User::factory()->create(['role' => 'vendor', 'email' => 'nxbkimdong@gmail.com']);
        $organization = Organization::create([
            'legal_name' => 'Kim Dong Demo', 'display_name' => 'Kim Dong Demo', 'slug' => 'nxb-kim-dong-demo',
            'organization_types' => $organizationTypes, 'status' => 'verified', 'data_mode' => 'demo', 'verified_at' => now(),
        ]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $owner->id, 'status' => 'inactive', 'onboarding_status' => 'draft', 'business_model' => 'direct_publisher',
            'primary_organization_id' => $organization->id, 'is_demo' => true, 'payout_bank_status' => 'demo_disabled',
        ]);
        $vendor->update(['demo_wallet_code' => 'DEMO-VENDOR-'.str_pad((string) $vendor->id, 4, '0', STR_PAD_LEFT)]);
        OrganizationMembership::create(['user_id' => $owner->id, 'organization_id' => $organization->id, 'role' => 'owner', 'status' => 'active']);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id, 'organization_id' => $organization->id, 'role' => 'self_legal_entity',
            'status' => 'verified', 'is_demo' => true, 'evidence_mode' => 'demo_statement', 'demo_reference' => 'DEMO-SELF-KIM-DONG',
            'verified_at' => now(), 'operation_key' => "demo-self-organization:{$vendor->id}:{$organization->id}",
        ]);

        return [$organization, $relationship, $vendor];
    }
}

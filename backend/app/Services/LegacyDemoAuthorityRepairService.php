<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookCommercialParty;
use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\OrganizationDistributionAgreementEvent;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRelationshipEvent;
use App\Models\OrganizationReviewEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Support\AuthorityReviewFingerprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Repairs the narrow set of demo records emitted by the repository's partner
 * commerce provisioning code before Batch 3 made review events authoritative.
 *
 * Operation keys identify the legacy records, but are not provenance by
 * themselves.  Candidate identities and mapping pairs are limited to the
 * repository's ProvisionPartnerCommerceDemo catalog.
 */
class LegacyDemoAuthorityRepairService
{
    private const RECOVERY_PREFIX = 'legacy-demo-authority-repair';

    /**
     * The organization and vendor identities created or normalized by
     * ProvisionPartnerCommerceDemo.  Dynamic database ids are intentionally not
     * catalogued: they are checked against their linked rows and wallet code.
     *
     * @var array<string, array{email: string, business_model: ?string, organization_type_sets: list<list<string>>}>
     */
    private const DEMO_ORGANIZATION_IDENTITIES = [
        'ipm-demo' => ['email' => 'ipm.demo@komibook.id.vn', 'business_model' => 'distributor', 'organization_type_sets' => [['distributor', 'supplier', 'bookstore']]],
        'hikari-thaihabooks-demo' => ['email' => 'hikari.thaihabooks.demo@komibook.id.vn', 'business_model' => 'distributor', 'organization_type_sets' => [['distributor', 'supplier', 'bookstore']]],
        'fahasa-demo' => ['email' => 'fahasa.demo@komibook.id.vn', 'business_model' => 'distributor', 'organization_type_sets' => [['distributor', 'supplier', 'bookstore']]],
        'nxb-lao-dong-demo' => ['email' => 'nxblaodong.demo@komibook.id.vn', 'business_model' => null, 'organization_type_sets' => [['publisher']]],
        'nxb-tre-demo' => ['email' => 'nxbtre.demo@komibook.id.vn', 'business_model' => 'direct_publisher', 'organization_type_sets' => [['publisher', 'supplier']]],
        'nxb-ha-noi-demo' => ['email' => 'nxbhanoi.demo@komibook.id.vn', 'business_model' => null, 'organization_type_sets' => [['publisher']]],
        'nxb-giao-duc-demo' => ['email' => 'nxbgiaoduc.demo@komibook.id.vn', 'business_model' => 'direct_publisher', 'organization_type_sets' => [['publisher', 'supplier']]],
        'nxb-kim-dong-demo' => ['email' => 'nxbkimdong@gmail.com', 'business_model' => 'direct_publisher', 'organization_type_sets' => [['publisher', 'supplier'], ['publisher', 'supplier', 'bookstore']]],
    ];

    /** @var array<string, true> */
    private const DEMO_MAPPING_PAIRS = [
        'ipm-demo:nxb-lao-dong-demo' => true,
        'ipm-demo:nxb-ha-noi-demo' => true,
        'fahasa-demo:nxb-kim-dong-demo' => true,
        'fahasa-demo:nxb-tre-demo' => true,
        'fahasa-demo:nxb-giao-duc-demo' => true,
    ];

    /** @return array<string, mixed> */
    public function inspect(): array
    {
        $organizations = Organization::query()->orderBy('id')->get()->keyBy('id');
        $vendors = Vendor::withoutGlobalScopes()->orderBy('id')->get()->keyBy('id');
        $memberships = OrganizationMembership::query()->orderBy('id')->get()->groupBy('organization_id');
        $users = User::query()->orderBy('id')->get()->keyBy('id');
        $relationships = VendorOrganizationRelationship::query()->orderBy('id')->get();
        $agreements = OrganizationDistributionAgreement::query()->orderBy('id')->get();
        $parties = BookCommercialParty::query()->with(['book' => fn ($query) => $query->withoutGlobalScopes()->select(['id', 'vendor_id'])])->orderBy('id')->get();
        $organizationEvents = OrganizationReviewEvent::query()->orderBy('id')->get();
        $relationshipEvents = OrganizationRelationshipEvent::query()->orderBy('id')->get();
        $agreementEvents = OrganizationDistributionAgreementEvent::query()->orderBy('id')->get();

        $conflicts = [];
        $relationshipCandidates = [];
        $mappingRelationshipCandidates = [];
        foreach ($relationships as $relationship) {
            $key = (string) $relationship->operation_key;
            if (str_starts_with($key, 'demo-mapping:rel:')) {
                $conflicts[] = $this->conflict('relationship', $relationship->id, 'unproven_mapping_prefix');

                continue;
            }
            $selfKey = $this->selfRelationshipKey($key);
            $mappingKey = $this->mappingRelationshipKey($key);
            if ($selfKey === null && $mappingKey === null) {
                if (str_starts_with($key, 'demo-self-organization:') || str_starts_with($key, 'demo-mapping:relationship:')) {
                    $conflicts[] = $this->conflict('relationship', $relationship->id, 'malformed_provenance_key');
                }

                continue;
            }
            if (! $this->isDemoRelationshipShape($relationship)) {
                $conflicts[] = $this->conflict('relationship', $relationship->id, 'provenance_shape_invalid');

                continue;
            }

            $organization = $organizations->get($relationship->organization_id);
            if (! $this->isEligibleOrganization($organization)) {
                $conflicts[] = $this->conflict('relationship', $relationship->id, 'ineligible_organization');

                continue;
            }

            $vendor = $vendors->get($relationship->vendor_id);
            if ($selfKey !== null) {
                if ($relationship->role !== 'self_legal_entity' || ! $vendor
                    || ! $vendor->is_demo
                    || $selfKey['vendor_id'] !== (int) $relationship->vendor_id
                    || $selfKey['organization_id'] !== (int) $relationship->organization_id
                    || (int) $vendor->primary_organization_id !== (int) $relationship->organization_id
                    || ! $this->matchesCatalogVendorOrganization($vendor, $organization, $memberships, $users)) {
                    $conflicts[] = $this->conflict('relationship', $relationship->id, 'self_relationship_mismatch');

                    continue;
                }
            } else {
                $distributor = $vendor ? $organizations->get($vendor->primary_organization_id) : null;
                if (! $this->hasCatalogSimulationScope($relationship->scope)
                    || $relationship->role !== 'publisher_partner'
                    || ! $vendor
                    || ! $vendor->is_demo
                    || ! $this->isEligibleOrganization($distributor)
                    || ! $this->hasType($organization, 'publisher')
                    || ! $this->hasAnyType($distributor, ['distributor', 'supplier', 'publisher'])
                    || $mappingKey['distributor_slug'] !== $distributor?->slug
                    || $mappingKey['publisher_slug'] !== $organization->slug
                    || ! $this->matchesCatalogVendorOrganization($vendor, $distributor, $memberships, $users)
                    || ! $this->matchesCatalogOrganization($organization, $memberships, $users)
                    || ! $this->isCatalogMappingPair($mappingKey)) {
                    $conflicts[] = $this->conflict('relationship', $relationship->id, 'mapping_relationship_incoherent');

                    continue;
                }
            }

            if ($selfKey !== null) {
                $relationshipCandidates[$relationship->id] = $relationship;
            } else {
                $mappingRelationshipCandidates[$this->mappingPairKey($mappingKey)][] = $relationship;
            }
        }

        $agreementCandidates = [];
        $mappingAgreementCandidates = [];
        foreach ($agreements as $agreement) {
            $mappingKey = $this->mappingAgreementKey((string) $agreement->operation_key);
            if ($mappingKey === null) {
                if (str_starts_with((string) $agreement->operation_key, 'demo-mapping:agreement:')) {
                    $conflicts[] = $this->conflict('agreement', $agreement->id, 'malformed_provenance_key');
                }

                continue;
            }
            $publisher = $organizations->get($agreement->publisher_organization_id);
            $distributor = $organizations->get($agreement->distributor_organization_id);
            if (! $this->isDemoAgreementShape($agreement)) {
                $conflicts[] = $this->conflict('agreement', $agreement->id, 'provenance_shape_invalid');

                continue;
            }
            if (! $this->isEligibleOrganization($publisher)
                || ! $this->isEligibleOrganization($distributor)
                || ! $this->hasType($publisher, 'publisher')
                || ! $this->hasAnyType($distributor, ['distributor', 'supplier', 'publisher'])
                || ! $this->hasCatalogSimulationScope($agreement->scope)
                || $mappingKey['distributor_slug'] !== $distributor?->slug
                || $mappingKey['publisher_slug'] !== $publisher?->slug
                || ! $this->matchesCatalogOrganization($publisher, $memberships, $users)
                || ! $this->matchesCatalogOrganization($distributor, $memberships, $users)
                || ! $this->isCatalogMappingPair($mappingKey)) {
                $conflicts[] = $this->conflict('agreement', $agreement->id, 'mapping_agreement_incoherent');

                continue;
            }
            $mappingAgreementCandidates[$this->mappingPairKey($mappingKey)][] = $agreement;
        }

        foreach (array_unique([...array_keys($mappingRelationshipCandidates), ...array_keys($mappingAgreementCandidates)]) as $pairKey) {
            $relationshipRows = $mappingRelationshipCandidates[$pairKey] ?? [];
            $agreementRows = $mappingAgreementCandidates[$pairKey] ?? [];
            if (count($relationshipRows) === 1 && count($agreementRows) === 1) {
                $relationshipCandidates[$relationshipRows[0]->id] = $relationshipRows[0];
                $agreementCandidates[$agreementRows[0]->id] = $agreementRows[0];

                continue;
            }
            foreach ($relationshipRows as $relationship) {
                $conflicts[] = $this->conflict('relationship', $relationship->id, 'mapping_chain_incomplete');
            }
            foreach ($agreementRows as $agreement) {
                $conflicts[] = $this->conflict('agreement', $agreement->id, 'mapping_chain_incomplete');
            }
        }

        $organizationIds = [];
        foreach ($relationshipCandidates as $relationship) {
            $organizationIds[(int) $relationship->organization_id] = true;
            if ($this->mappingRelationshipKey((string) $relationship->operation_key) !== null) {
                $organizationIds[(int) $vendors->get($relationship->vendor_id)->primary_organization_id] = true;
            }
        }
        foreach ($agreementCandidates as $agreement) {
            $organizationIds[(int) $agreement->publisher_organization_id] = true;
            $organizationIds[(int) $agreement->distributor_organization_id] = true;
        }
        $organizationCandidates = [];
        foreach (array_keys($organizationIds) as $id) {
            $organization = $organizations->get($id);
            if ($this->isEligibleOrganization($organization)) {
                $organizationCandidates[$id] = $organization;
            }
        }

        $partyCandidates = [];
        $roleCompleteness = [];
        foreach ($parties as $party) {
            $relationship = $relationshipCandidates[$party->vendor_organization_relationship_id] ?? null;
            if (! $relationship) {
                if (str_starts_with((string) optional($relationships->firstWhere('id', $party->vendor_organization_relationship_id))->operation_key, 'demo-mapping:rel:')) {
                    $conflicts[] = $this->conflict('book_commercial_party', $party->id, 'linked_unproven_relationship');
                }

                continue;
            }
            if (! in_array($party->status, ['verified', 'demo_accepted'], true)) {
                $conflicts[] = $this->conflict('book_commercial_party', $party->id, 'book_party_status_ineligible', $relationship->id);

                continue;
            }
            $reason = $this->partyMismatchReason($party, $relationship);
            if ($reason !== null) {
                $conflicts[] = $this->conflict('book_commercial_party', $party->id, $reason, $relationship->id);

                continue;
            }
            $partyCandidates[$party->id] = $party;
            $roleCompleteness[(int) $party->book_id][$party->role] = true;
        }

        $roleReport = [];
        foreach ($roleCompleteness as $bookId => $roles) {
            $present = array_values(array_intersect(CommercialPartyService::ROLES, array_keys($roles)));
            $roleReport[] = [
                'book_id' => $bookId,
                'present_roles' => $present,
                'complete' => $present === CommercialPartyService::ROLES,
            ];
        }
        usort($roleReport, fn (array $left, array $right) => $left['book_id'] <=> $right['book_id']);

        $candidateRelationshipIds = array_map('intval', array_keys($relationshipCandidates));
        $candidateAgreementIds = array_map('intval', array_keys($agreementCandidates));
        $candidateOrganizationIds = array_map('intval', array_keys($organizationCandidates));
        $candidatePartyIds = array_map('intval', array_keys($partyCandidates));
        sort($candidateRelationshipIds);
        sort($candidateAgreementIds);
        sort($candidateOrganizationIds);
        sort($candidatePartyIds);
        $this->appendSupersededRecoveryConflicts($conflicts, $organizationEvents, 'organization_id', 'organization', $candidateOrganizationIds);
        $this->appendSupersededRecoveryConflicts($conflicts, $relationshipEvents, 'vendor_organization_relationship_id', 'relationship', $candidateRelationshipIds);
        $this->appendSupersededRecoveryConflicts($conflicts, $agreementEvents, 'organization_distribution_agreement_id', 'agreement', $candidateAgreementIds);
        usort($conflicts, fn (array $left, array $right) => [$left['entity'], $left['id'], $left['reason_code']] <=> [$right['entity'], $right['id'], $right['reason_code']]);

        $candidateChainIds = array_fill_keys($candidateRelationshipIds, true);
        $blocking = array_values(array_filter($conflicts, function (array $conflict) use ($candidateChainIds): bool {
            return $conflict['reason_code'] === 'superseded_recovery_authority'
                || ($conflict['related_relationship_id'] !== null && isset($candidateChainIds[(int) $conflict['related_relationship_id']]));
        }));
        // Party mismatches are linked to candidate relationships.  A malformed candidate itself has already been excluded.

        $manifest = [
            'version' => 1,
            'candidates' => [
                'organization_ids' => $candidateOrganizationIds,
                'relationship_ids' => $candidateRelationshipIds,
                'agreement_ids' => $candidateAgreementIds,
                'book_commercial_party_ids' => $candidatePartyIds,
            ],
            'planned_changes' => [
                'organizations' => $candidateOrganizationIds,
                'relationships' => $candidateRelationshipIds,
                'agreements' => $candidateAgreementIds,
                'book_commercial_parties' => $candidatePartyIds,
            ],
            'excluded_live' => [
                'relationship_ids' => $relationships->filter(fn (VendorOrganizationRelationship $row) => ! $row->is_demo || $row->evidence_mode === 'real_document')->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'agreement_ids' => $agreements->filter(fn (OrganizationDistributionAgreement $row) => ! $row->is_demo || $row->evidence_mode === 'real_document')->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'book_commercial_party_count' => $parties->filter(fn (BookCommercialParty $party) => ! isset($partyCandidates[$party->id]))->count(),
            ],
            'conflicts' => $conflicts,
            'blocking_conflict_count' => count($blocking),
            'per_book_role_completeness' => $roleReport,
            'source_state' => [
                'organizations' => $organizations->map(fn (Organization $row) => $this->organizationProjection($row))->values()->all(),
                'vendors' => $vendors->map(fn (Vendor $row) => $this->vendorProjection($row))->values()->all(),
                'relationships' => $relationships->map(fn (VendorOrganizationRelationship $row) => $this->relationshipProjection($row))->values()->all(),
                'agreements' => $agreements->map(fn (OrganizationDistributionAgreement $row) => $this->agreementProjection($row))->values()->all(),
                'organization_memberships' => $memberships->flatten()->sortBy('id')->map(fn (OrganizationMembership $row) => ['id' => $row->id, 'organization_id' => $row->organization_id, 'user_id' => $row->user_id, 'role' => $row->role, 'status' => $row->status])->values()->all(),
                'identity_users' => $users->map(fn (User $row) => ['id' => $row->id, 'email' => $row->email])->values()->all(),
                'book_commercial_parties' => $parties->map(fn (BookCommercialParty $row) => $this->partyProjection($row))->values()->all(),
                'books' => $parties->pluck('book')->filter()->sortBy('id')->map(fn (Book $row) => ['id' => $row->id, 'vendor_id' => $row->vendor_id])->values()->all(),
                'organization_review_events' => $organizationEvents->map(fn (OrganizationReviewEvent $row) => $this->eventProjection($row, 'organization_id'))->values()->all(),
                'relationship_review_events' => $relationshipEvents->map(fn (OrganizationRelationshipEvent $row) => $this->eventProjection($row, 'vendor_organization_relationship_id'))->values()->all(),
                'agreement_review_events' => $agreementEvents->map(fn (OrganizationDistributionAgreementEvent $row) => $this->eventProjection($row, 'organization_distribution_agreement_id'))->values()->all(),
            ],
        ];
        sort($manifest['excluded_live']['relationship_ids']);
        sort($manifest['excluded_live']['agreement_ids']);
        $manifest['digest'] = hash('sha256', $this->canonicalJson($manifest));

        return $manifest;
    }

    /** @return array<string, mixed> */
    public function apply(int $adminId, string $reason, string $manifestDigest): array
    {
        if (blank($reason) || blank($manifestDigest)) {
            throw ValidationException::withMessages(['repair' => 'An admin, nonblank reason, and current manifest digest are required.']);
        }
        $admin = User::query()->find($adminId);
        if (! $admin || $admin->role !== 'admin') {
            throw ValidationException::withMessages(['admin_id' => 'The supplied user is not a current administrator.']);
        }
        $before = $this->inspect();
        if (! hash_equals($before['digest'], $manifestDigest)) {
            throw ValidationException::withMessages(['manifest_digest' => 'The manifest digest is stale or does not match this dry-run.']);
        }
        if ($before['blocking_conflict_count'] > 0) {
            throw ValidationException::withMessages(['conflicts' => 'Candidate-chain conflicts must be resolved before repair.']);
        }

        try {
            return DB::transaction(function () use ($adminId, $reason, $manifestDigest): array {
                $this->lockDiscoveryRows($adminId);
                $admin = User::query()->lockForUpdate()->find($adminId);
                if (! $admin || $admin->role !== 'admin') {
                    throw ValidationException::withMessages(['admin_id' => 'The supplied user is not a current administrator.']);
                }
                $manifest = $this->inspect();
                if (! hash_equals($manifest['digest'], $manifestDigest)) {
                    throw ValidationException::withMessages(['manifest_digest' => 'The records changed while the repair was being prepared.']);
                }
                if ($manifest['blocking_conflict_count'] > 0) {
                    throw ValidationException::withMessages(['conflicts' => 'Candidate-chain conflicts must be resolved before repair.']);
                }

                foreach ($manifest['candidates']['organization_ids'] as $id) {
                    $this->recoverOrganization(Organization::query()->lockForUpdate()->findOrFail($id), $admin, $reason);
                }
                foreach ($manifest['candidates']['relationship_ids'] as $id) {
                    $this->recoverRelationship(VendorOrganizationRelationship::query()->lockForUpdate()->findOrFail($id), $admin, $reason);
                }
                foreach ($manifest['candidates']['agreement_ids'] as $id) {
                    $this->recoverAgreement(OrganizationDistributionAgreement::query()->lockForUpdate()->findOrFail($id), $admin, $reason);
                }
                foreach ($manifest['candidates']['book_commercial_party_ids'] as $id) {
                    $party = BookCommercialParty::query()->lockForUpdate()->findOrFail($id);
                    $party->update(['status' => 'demo_accepted', 'verified_at' => null, 'verified_by' => null]);
                }

                $this->assertPostconditions($manifest);

                return [...$this->inspect(), 'applied' => true];
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('Legacy demo authority repair failed and was rolled back.', 0, $exception);
        }
    }

    private function lockDiscoveryRows(int $adminId): void
    {
        foreach ([
            User::query(),
            Organization::query(),
            OrganizationMembership::query(),
            Vendor::withoutGlobalScopes(),
            VendorOrganizationRelationship::query(),
            OrganizationDistributionAgreement::query(),
            Book::withoutGlobalScopes(),
            BookCommercialParty::query(),
            OrganizationReviewEvent::query(),
            OrganizationRelationshipEvent::query(),
            OrganizationDistributionAgreementEvent::query(),
        ] as $query) {
            $query->orderBy('id')->lockForUpdate()->get();
        }
    }

    private function recoverOrganization(Organization $organization, User $admin, string $reason): void
    {
        $key = $this->eventKey('organization', $organization->id);
        $existing = OrganizationReviewEvent::query()->where('operation_key', $key)->first();
        if ($existing) {
            $this->assertSameOrganizationEvent($existing, $organization, $admin, $reason);

            return;
        }
        $this->assertCrossTypeKeyIsFree($key);
        $organization->update(['status' => 'demo_accepted', 'verified_at' => null, 'suspended_at' => null, 'archived_at' => null, 'verified_by' => $admin->id, 'last_review_reason' => $reason]);
        OrganizationReviewEvent::create(['organization_id' => $organization->id, 'actor_id' => $admin->id, 'from_status' => 'demo_accepted', 'to_status' => 'demo_accepted', 'reason' => $reason, 'operation_key' => $key, 'reviewed_fingerprint' => $organization->authority_fingerprint]);
    }

    private function recoverRelationship(VendorOrganizationRelationship $relationship, User $admin, string $reason): void
    {
        $key = $this->eventKey('relationship', $relationship->id);
        $existing = OrganizationRelationshipEvent::query()->where('operation_key', $key)->first();
        if ($existing) {
            $this->assertSameRelationshipEvent($existing, $relationship, $admin, $reason);

            return;
        }
        $this->assertCrossTypeKeyIsFree($key);
        $relationship->update(['status' => 'demo_accepted', 'verified_at' => null, 'revoked_at' => null, 'reviewed_by' => $admin->id, 'last_review_reason' => $reason]);
        OrganizationRelationshipEvent::create(['vendor_organization_relationship_id' => $relationship->id, 'actor_id' => $admin->id, 'from_status' => 'demo_accepted', 'to_status' => 'demo_accepted', 'reason' => $reason, 'operation_key' => $key, 'reviewed_fingerprint' => $relationship->authority_fingerprint]);
    }

    private function recoverAgreement(OrganizationDistributionAgreement $agreement, User $admin, string $reason): void
    {
        $key = $this->eventKey('agreement', $agreement->id);
        $existing = OrganizationDistributionAgreementEvent::query()->where('operation_key', $key)->first();
        if ($existing) {
            $this->assertSameAgreementEvent($existing, $agreement, $admin, $reason);

            return;
        }
        $this->assertCrossTypeKeyIsFree($key);
        $agreement->update(['status' => 'demo_accepted', 'verified_at' => null, 'revoked_at' => null, 'reviewed_by' => $admin->id, 'last_review_reason' => $reason]);
        OrganizationDistributionAgreementEvent::create(['organization_distribution_agreement_id' => $agreement->id, 'actor_id' => $admin->id, 'from_status' => 'demo_accepted', 'to_status' => 'demo_accepted', 'reason' => $reason, 'operation_key' => $key, 'reviewed_fingerprint' => $agreement->authority_fingerprint]);
    }

    /** @param array<string, mixed> $manifest */
    private function assertPostconditions(array $manifest): void
    {
        foreach ($manifest['candidates']['organization_ids'] as $id) {
            if (! Organization::query()->findOrFail($id)->hasAuthoritativeAcceptance()) {
                throw new RuntimeException('An organization failed the authoritative postcondition.');
            }
        }
        foreach ($manifest['candidates']['relationship_ids'] as $id) {
            if (! VendorOrganizationRelationship::query()->with('organization')->findOrFail($id)->isCurrentlyVerified()) {
                throw new RuntimeException('A relationship failed the authoritative postcondition.');
            }
        }
        foreach ($manifest['candidates']['agreement_ids'] as $id) {
            if (! OrganizationDistributionAgreement::query()->with(['publisher', 'distributor'])->findOrFail($id)->isCurrentlyVerified()) {
                throw new RuntimeException('An agreement failed the authoritative postcondition.');
            }
        }
        foreach ($manifest['candidates']['book_commercial_party_ids'] as $id) {
            $party = BookCommercialParty::query()->findOrFail($id);
            if ($party->status !== 'demo_accepted' || $party->verified_at !== null || $party->verified_by !== null) {
                throw new RuntimeException('A book commercial party failed the visibility postcondition.');
            }
            if ($party->active_slot === 'active' && $party->ended_at === null
                && ! $party->book()->firstOrFail()->activeCommercialParties()->whereKey($party->id)->exists()) {
                throw new RuntimeException('An active book commercial party is not publicly visible after repair.');
            }
        }
    }

    private function isEligibleOrganization(?Organization $organization): bool
    {
        return $organization !== null && $organization->data_mode === 'demo' && in_array($organization->status, ['verified', 'demo_accepted'], true)
            // Batch 3 wrote the legal-looking status/timestamp directly.  The
            // provenance key elsewhere, not this timestamp, is what authorizes
            // a repair; an actual legal document always excludes the record.
            && blank($organization->verification_document)
            && $organization->suspended_at === null && $organization->archived_at === null;
    }

    private function isDemoRelationshipShape(VendorOrganizationRelationship $relationship): bool
    {
        return in_array($relationship->status, ['verified', 'demo_accepted'], true) && $relationship->is_demo && $relationship->evidence_mode === 'demo_statement'
            && filled($relationship->demo_reference) && str_starts_with((string) $relationship->demo_reference, 'DEMO-')
            && blank($relationship->evidence_document) && $relationship->revoked_at === null
            && $this->isCurrent($relationship->effective_from, $relationship->effective_until);
    }

    private function isDemoAgreementShape(OrganizationDistributionAgreement $agreement): bool
    {
        return in_array($agreement->status, ['verified', 'demo_accepted'], true) && $agreement->is_demo && $agreement->evidence_mode === 'demo_statement'
            && filled($agreement->demo_reference) && str_starts_with((string) $agreement->demo_reference, 'DEMO-')
            && blank($agreement->evidence_document) && $agreement->revoked_at === null
            && $this->isCurrent($agreement->effective_from, $agreement->effective_until);
    }

    private function isCurrent(mixed $from, mixed $until): bool
    {
        return ($from === null || $from->lte(today())) && ($until === null || $until->gte(today()));
    }

    private function hasCatalogSimulationScope(?array $scope): bool
    {
        return ($scope['coverage'] ?? null) === 'catalog' && ($scope['notice'] ?? null) === 'simulated';
    }

    private function hasType(?Organization $organization, string $type): bool
    {
        return $organization !== null && in_array($type, $organization->organization_types ?? [], true);
    }

    /** @param list<string> $types */
    private function hasAnyType(?Organization $organization, array $types): bool
    {
        return $organization !== null && array_intersect($types, $organization->organization_types ?? []) !== [];
    }

    /** @param Collection<int, Collection<int, OrganizationMembership>> $memberships */
    private function matchesCatalogVendorOrganization(?Vendor $vendor, ?Organization $organization, Collection $memberships, Collection $users): bool
    {
        if (! $vendor || ! $organization || (int) $vendor->primary_organization_id !== (int) $organization->id
            || ! $vendor->is_demo || $vendor->payout_bank_status !== 'demo_disabled'
            || $vendor->demo_wallet_code !== 'DEMO-VENDOR-'.str_pad((string) $vendor->id, 4, '0', STR_PAD_LEFT)) {
            return false;
        }
        $identity = self::DEMO_ORGANIZATION_IDENTITIES[$organization->slug] ?? null;
        $ownerId = $this->catalogOwnerId($organization, $identity, $memberships, $users);

        return $identity !== null && $identity['business_model'] !== null
            && $vendor->business_model === $identity['business_model']
            && $ownerId !== null && (int) $vendor->user_id === $ownerId;
    }

    /** @param Collection<int, Collection<int, OrganizationMembership>> $memberships */
    private function matchesCatalogOrganization(?Organization $organization, Collection $memberships, Collection $users): bool
    {
        if (! $organization) {
            return false;
        }
        $identity = self::DEMO_ORGANIZATION_IDENTITIES[$organization->slug] ?? null;
        if ($identity === null) {
            return false;
        }

        return $this->catalogOwnerId($organization, $identity, $memberships, $users) !== null;
    }

    /** @param array{email: string, business_model: ?string, organization_type_sets: list<list<string>>}|null $identity */
    private function catalogOwnerId(Organization $organization, ?array $identity, Collection $memberships, Collection $users): ?int
    {
        if ($identity === null || ! $this->matchesCatalogOrganizationTypes($organization, $identity['organization_type_sets'])) {
            return null;
        }

        $membership = $memberships->get($organization->id, collect())->first(function (OrganizationMembership $membership) use ($identity, $users): bool {
            return $membership->role === 'owner' && $membership->status === 'active'
                && $users->get($membership->user_id)?->email === $identity['email'];
        });

        return $membership?->user_id;
    }

    /** @param list<list<string>> $expectedTypeSets */
    private function matchesCatalogOrganizationTypes(Organization $organization, array $expectedTypeSets): bool
    {
        $actualTypes = $organization->organization_types ?? [];
        sort($actualTypes);
        foreach ($expectedTypeSets as $expectedTypes) {
            sort($expectedTypes);
            if ($actualTypes === $expectedTypes) {
                return true;
            }
        }

        return false;
    }

    /** @param array{distributor_slug: string, publisher_slug: string} $mappingKey */
    private function isCatalogMappingPair(array $mappingKey): bool
    {
        return isset(self::DEMO_MAPPING_PAIRS[$this->mappingPairKey($mappingKey)]);
    }

    /** @param array{distributor_slug: string, publisher_slug: string} $mappingKey */
    private function mappingPairKey(array $mappingKey): string
    {
        return $mappingKey['distributor_slug'].':'.$mappingKey['publisher_slug'];
    }

    private function partyMismatchReason(BookCommercialParty $party, VendorOrganizationRelationship $relationship): ?string
    {
        if ((int) $party->organization_id !== (int) $relationship->organization_id) {
            return 'book_party_organization_mismatch';
        }
        if (! $party->book || (int) $party->book->vendor_id !== (int) $relationship->vendor_id) {
            return 'book_party_vendor_mismatch';
        }
        $types = Organization::query()->find($relationship->organization_id)?->organization_types ?? [];
        $compatible = match ($party->role) {
            'publisher' => in_array('publisher', $types, true) && in_array($relationship->role, ['self_legal_entity', 'publisher_partner', 'publisher', 'commercial_partner'], true),
            'supplier' => array_intersect($types, ['supplier', 'publisher', 'distributor']) !== [] && in_array($relationship->role, ['self_legal_entity', 'supplier_partner', 'authorized_distributor', 'supplier', 'distributor', 'commercial_partner'], true),
            'responsible_organization' => in_array($relationship->role, ['self_legal_entity', 'publisher_partner', 'supplier_partner', 'authorized_distributor', 'publisher', 'supplier', 'distributor', 'commercial_partner'], true),
            default => false,
        };

        return $compatible ? null : 'book_party_role_incompatible';
    }

    /** @return array{vendor_id: int, organization_id: int}|null */
    private function selfRelationshipKey(string $key): ?array
    {
        if (preg_match('/^demo-self-organization:(\d+):(\d+)$/', $key, $matches) !== 1) {
            return null;
        }

        return ['vendor_id' => (int) $matches[1], 'organization_id' => (int) $matches[2]];
    }

    /** @return array{distributor_slug: string, publisher_slug: string}|null */
    private function mappingRelationshipKey(string $key): ?array
    {
        return $this->mappingKey($key, 'relationship');
    }

    /** @return array{distributor_slug: string, publisher_slug: string}|null */
    private function mappingAgreementKey(string $key): ?array
    {
        return $this->mappingKey($key, 'agreement');
    }

    /** @return array{distributor_slug: string, publisher_slug: string}|null */
    private function mappingKey(string $key, string $kind): ?array
    {
        if (preg_match('/^demo-mapping:'.preg_quote($kind, '/').':([a-z0-9]+(?:-[a-z0-9]+)*):([a-z0-9]+(?:-[a-z0-9]+)*)$/', $key, $matches) !== 1) {
            return null;
        }

        return ['distributor_slug' => $matches[1], 'publisher_slug' => $matches[2]];
    }

    /** @return array<string, mixed> */
    private function organizationProjection(Organization $row): array
    {
        return [
            'id' => $row->id,
            'slug' => $row->slug,
            'organization_types' => $row->organization_types,
            'status' => $row->status,
            'data_mode' => $row->data_mode,
            'verification_document_digest' => $this->valueDigest($row->verification_document),
            'verified_by' => $row->verified_by,
            'verified_at' => $this->datetimeValue($row->verified_at),
            'suspended_at' => $this->datetimeValue($row->suspended_at),
            'archived_at' => $this->datetimeValue($row->archived_at),
            'last_review_reason' => $row->last_review_reason,
            'authority_fingerprint' => $row->authority_fingerprint,
            'recomputed_authority_fingerprint' => AuthorityReviewFingerprint::organization($row),
        ];
    }

    /** @return array<string, mixed> */
    private function vendorProjection(Vendor $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'primary_organization_id' => $row->primary_organization_id,
            'business_model' => $row->business_model,
            'is_demo' => $row->is_demo,
            'demo_wallet_code' => $row->demo_wallet_code,
            'payout_bank_status' => $row->payout_bank_status,
        ];
    }

    /** @return array<string, mixed> */
    private function relationshipProjection(VendorOrganizationRelationship $row): array
    {
        return [
            'id' => $row->id,
            'vendor_id' => $row->vendor_id,
            'organization_id' => $row->organization_id,
            'role' => $row->role,
            'status' => $row->status,
            'is_demo' => $row->is_demo,
            'evidence_mode' => $row->evidence_mode,
            'scope' => $row->scope,
            'evidence_document_digest' => $this->valueDigest($row->evidence_document),
            'demo_reference' => $row->demo_reference,
            'effective_from' => $this->dateValue($row->effective_from),
            'effective_until' => $this->dateValue($row->effective_until),
            'reviewed_by' => $row->reviewed_by,
            'verified_at' => $this->datetimeValue($row->verified_at),
            'revoked_at' => $this->datetimeValue($row->revoked_at),
            'last_review_reason' => $row->last_review_reason,
            'operation_key' => $row->operation_key,
            'authority_fingerprint' => $row->authority_fingerprint,
            'recomputed_authority_fingerprint' => AuthorityReviewFingerprint::relationship($row),
        ];
    }

    /** @return array<string, mixed> */
    private function agreementProjection(OrganizationDistributionAgreement $row): array
    {
        return [
            'id' => $row->id,
            'publisher_organization_id' => $row->publisher_organization_id,
            'distributor_organization_id' => $row->distributor_organization_id,
            'status' => $row->status,
            'is_demo' => $row->is_demo,
            'evidence_mode' => $row->evidence_mode,
            'scope' => $row->scope,
            'evidence_document_digest' => $this->valueDigest($row->evidence_document),
            'demo_reference' => $row->demo_reference,
            'effective_from' => $this->dateValue($row->effective_from),
            'effective_until' => $this->dateValue($row->effective_until),
            'reviewed_by' => $row->reviewed_by,
            'verified_at' => $this->datetimeValue($row->verified_at),
            'revoked_at' => $this->datetimeValue($row->revoked_at),
            'last_review_reason' => $row->last_review_reason,
            'operation_key' => $row->operation_key,
            'authority_fingerprint' => $row->authority_fingerprint,
            'recomputed_authority_fingerprint' => AuthorityReviewFingerprint::agreement($row),
        ];
    }

    /** @return array<string, mixed> */
    private function partyProjection(BookCommercialParty $row): array
    {
        return [
            'id' => $row->id,
            'book_id' => $row->book_id,
            'organization_id' => $row->organization_id,
            'relationship_id' => $row->vendor_organization_relationship_id,
            'role' => $row->role,
            'status' => $row->status,
            'version' => $row->version,
            'active_slot' => $row->active_slot,
            'effective_at' => $this->datetimeValue($row->effective_at),
            'verified_at' => $this->datetimeValue($row->verified_at),
            'ended_at' => $this->datetimeValue($row->ended_at),
            'verified_by' => $row->verified_by,
        ];
    }

    /** @return array<string, mixed> */
    private function eventProjection(object $row, string $foreignKey): array
    {
        return [
            'id' => $row->id,
            'entity_id' => $row->{$foreignKey},
            'actor_id' => $row->actor_id,
            'from_status' => $row->from_status,
            'to_status' => $row->to_status,
            'reason' => $row->reason,
            'operation_key' => $row->operation_key,
            'reviewed_fingerprint' => $row->reviewed_fingerprint,
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : ($value ?: null);
    }

    private function datetimeValue(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? (clone $value)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z') : ($value ?: null);
    }

    private function valueDigest(mixed $value): ?string
    {
        return blank($value) ? null : hash('sha256', (string) $value);
    }

    /** @return array{entity: string, id: int, reason_code: string, related_relationship_id: ?int} */
    private function conflict(string $entity, int $id, string $reasonCode, ?int $relationshipId = null): array
    {
        return ['entity' => $entity, 'id' => $id, 'reason_code' => $reasonCode, 'related_relationship_id' => $relationshipId];
    }

    /** @param Collection<int, object> $events @param list<int> $candidateIds */
    private function appendSupersededRecoveryConflicts(array &$conflicts, Collection $events, string $foreignKey, string $entity, array $candidateIds): void
    {
        $candidateIdSet = array_fill_keys($candidateIds, true);
        foreach ($events->groupBy($foreignKey) as $entityId => $entityEvents) {
            $latest = $entityEvents->sortBy('id')->last();
            if (str_starts_with((string) $latest->operation_key, self::RECOVERY_PREFIX.':')
                && ! isset($candidateIdSet[(int) $entityId])) {
                $conflicts[] = $this->conflict($entity, (int) $entityId, 'superseded_recovery_authority');
            }
        }
    }

    private function eventKey(string $type, int $id): string
    {
        return self::RECOVERY_PREFIX.':'.$type.':'.$id;
    }

    private function assertCrossTypeKeyIsFree(string $key): void
    {
        if (OrganizationReviewEvent::query()->where('operation_key', $key)->exists()
            || OrganizationRelationshipEvent::query()->where('operation_key', $key)->exists()
            || OrganizationDistributionAgreementEvent::query()->where('operation_key', $key)->exists()) {
            throw ValidationException::withMessages(['operation_key' => 'A recovery operation key is already in use by a conflicting event.']);
        }
    }

    private function assertSameOrganizationEvent(OrganizationReviewEvent $event, Organization $organization, User $admin, string $reason): void
    {
        if ($event->organization_id !== $organization->id || $event->actor_id !== $admin->id || $event->from_status !== 'demo_accepted' || $event->to_status !== 'demo_accepted' || $event->reason !== $reason || $event->reviewed_fingerprint !== $organization->authority_fingerprint || ! $organization->hasAuthoritativeAcceptance()) {
            throw ValidationException::withMessages(['operation_key' => 'A recovery event conflicts with the requested organization state.']);
        }
    }

    private function assertSameRelationshipEvent(OrganizationRelationshipEvent $event, VendorOrganizationRelationship $relationship, User $admin, string $reason): void
    {
        if ($event->vendor_organization_relationship_id !== $relationship->id || $event->actor_id !== $admin->id || $event->from_status !== 'demo_accepted' || $event->to_status !== 'demo_accepted' || $event->reason !== $reason || $event->reviewed_fingerprint !== $relationship->authority_fingerprint || ! $relationship->fresh(['organization'])->isCurrentlyVerified()) {
            throw ValidationException::withMessages(['operation_key' => 'A recovery event conflicts with the requested relationship state.']);
        }
    }

    private function assertSameAgreementEvent(OrganizationDistributionAgreementEvent $event, OrganizationDistributionAgreement $agreement, User $admin, string $reason): void
    {
        if ($event->organization_distribution_agreement_id !== $agreement->id || $event->actor_id !== $admin->id || $event->from_status !== 'demo_accepted' || $event->to_status !== 'demo_accepted' || $event->reason !== $reason || $event->reviewed_fingerprint !== $agreement->authority_fingerprint || ! $agreement->fresh(['publisher', 'distributor'])->isCurrentlyVerified()) {
            throw ValidationException::withMessages(['operation_key' => 'A recovery event conflicts with the requested agreement state.']);
        }
    }

    /** @param array<string, mixed> $value */
    private function canonicalJson(array $value): string
    {
        return json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        if (array_is_list($value)) {
            return $value;
        }
        ksort($value);

        return $value;
    }
}

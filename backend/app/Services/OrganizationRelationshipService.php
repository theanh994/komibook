<?php

namespace App\Services;

use App\Models\OrganizationDistributionAgreementEvent;
use App\Models\OrganizationRelationshipEvent;
use App\Models\OrganizationReviewEvent;
use App\Models\User;
use App\Models\VendorOrganizationRelationship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationRelationshipService
{
    private const TRANSITIONS = ['draft' => ['submitted'], 'submitted' => ['verified', 'demo_accepted', 'changes_requested', 'rejected'], 'changes_requested' => ['submitted'], 'verified' => ['suspended', 'revoked'], 'demo_accepted' => ['suspended', 'revoked'], 'suspended' => ['verified', 'demo_accepted', 'revoked'], 'rejected' => [], 'revoked' => []];

    public function transition(VendorOrganizationRelationship $relationship, string $target, User $actor, ?string $reason = null, ?string $operationKey = null): VendorOrganizationRelationship
    {
        $operationKey ??= 'organization-relationship:'.Str::uuid();

        return DB::transaction(function () use ($relationship, $target, $actor, $reason, $operationKey): VendorOrganizationRelationship {
            $this->assertNoCrossTypeKey($operationKey);
            $existing = OrganizationRelationshipEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                return $this->replay($existing, $relationship, $target, $actor, $reason);
            }
            $locked = VendorOrganizationRelationship::query()->lockForUpdate()->findOrFail($relationship->id);
            $this->assertTargetAccessAndEvidence($locked, $target, $actor, $reason);
            $from = $locked->status;
            if (! in_array($target, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Invalid relationship transition: {$from} -> {$target}."]);
            }
            $updates = ['status' => $target, 'last_review_reason' => $reason];
            if ($target === 'submitted') {
                $updates['submitted_at'] = now();
            }
            if (in_array($target, ['verified', 'demo_accepted'], true)) {
                $updates += ['verified_at' => $target === 'verified' ? now() : null, 'revoked_at' => null, 'reviewed_by' => $actor->id];
            }
            if ($target === 'revoked') {
                $updates += ['revoked_at' => now(), 'reviewed_by' => $actor->id];
            }
            $locked->update($updates);
            OrganizationRelationshipEvent::create(['vendor_organization_relationship_id' => $locked->id, 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $target, 'reason' => $reason, 'operation_key' => $operationKey, 'reviewed_fingerprint' => $locked->authority_fingerprint]);

            return $locked->fresh(['organization']);
        });
    }

    private function replay(OrganizationRelationshipEvent $event, VendorOrganizationRelationship $relationship, string $target, User $actor, ?string $reason): VendorOrganizationRelationship
    {
        if ($event->vendor_organization_relationship_id !== $relationship->id || $event->to_status !== $target || $event->actor_id !== $actor->id || $event->reason !== $reason) {
            throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for another transition.']);
        }
        $locked = VendorOrganizationRelationship::query()->lockForUpdate()->findOrFail($relationship->id);
        if ($locked->status !== $target || $locked->latestEvent()->value('id') !== $event->id) {
            throw ValidationException::withMessages(['operation_key' => 'Operation key no longer represents the current relationship state.']);
        }
        $this->assertTargetAccessAndEvidence($locked, $target, $actor, $reason);
        if (in_array($target, ['verified', 'demo_accepted'], true) && ! $locked->fresh(['organization'])->isCurrentlyVerified()) {
            throw ValidationException::withMessages(['status' => 'Relationship is no longer canonically authoritative.']);
        }

        return $locked->fresh(['organization']);
    }

    private function assertTargetAccessAndEvidence(VendorOrganizationRelationship $relationship, string $target, User $actor, ?string $reason): void
    {
        $adminTarget = ['verified', 'demo_accepted', 'changes_requested', 'rejected', 'suspended', 'revoked'];
        if (in_array($target, $adminTarget, true) && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for this transition.']);
        }
        if (in_array($target, $adminTarget, true) && $actor->role !== 'admin') {
            abort(403, 'Only administrators can review organization relationships.');
        }
        if ($target === 'demo_accepted' && ! $relationship->is_demo) {
            throw ValidationException::withMessages(['status' => 'Only demo relationships can receive demo acceptance.']);
        }
        if ($target === 'verified' && $relationship->is_demo) {
            throw ValidationException::withMessages(['status' => 'Demo relationships cannot receive legal verification.']);
        }
        $organization = $relationship->organization()->first();
        if ($target === 'verified' && ($relationship->evidence_mode !== 'real_document' || blank($relationship->evidence_document) || filled($relationship->demo_reference) || $relationship->submitted_at === null || ! $organization?->isVerified())) {
            throw ValidationException::withMessages(['status' => 'Live relationship verification requires canonical live evidence and a verified organization.']);
        }
        if ($target === 'demo_accepted' && ($relationship->evidence_mode !== 'demo_statement' || blank($relationship->demo_reference) || filled($relationship->evidence_document) || $relationship->verified_at !== null || $organization?->data_mode !== 'demo' || ! $organization->hasAuthoritativeAcceptance())) {
            throw ValidationException::withMessages(['status' => 'Demo relationship acceptance requires canonical demo evidence and a demo organization.']);
        }
    }

    private function assertNoCrossTypeKey(string $operationKey): void
    {
        if (OrganizationReviewEvent::where('operation_key', $operationKey)->exists() || OrganizationDistributionAgreementEvent::where('operation_key', $operationKey)->exists()) {
            throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for another transition type.']);
        }
    }
}

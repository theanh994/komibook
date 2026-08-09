<?php

namespace App\Services;

use App\Models\OrganizationDistributionAgreement;
use App\Models\OrganizationDistributionAgreementEvent;
use App\Models\OrganizationRelationshipEvent;
use App\Models\OrganizationReviewEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DistributionAgreementService
{
    private const TRANSITIONS = ['draft' => ['submitted'], 'submitted' => ['verified', 'demo_accepted', 'changes_requested', 'rejected'], 'changes_requested' => ['submitted'], 'verified' => ['suspended', 'revoked'], 'demo_accepted' => ['suspended', 'revoked'], 'suspended' => ['verified', 'demo_accepted', 'revoked'], 'rejected' => [], 'revoked' => []];

    public function transition(OrganizationDistributionAgreement $agreement, string $target, User $actor, ?string $reason = null, ?string $operationKey = null): OrganizationDistributionAgreement
    {
        $operationKey ??= 'distribution-agreement:'.Str::uuid();

        return DB::transaction(function () use ($agreement, $target, $actor, $reason, $operationKey): OrganizationDistributionAgreement {
            $this->assertNoCrossTypeKey($operationKey);
            $existing = OrganizationDistributionAgreementEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                return $this->replay($existing, $agreement, $target, $actor, $reason);
            }
            $locked = OrganizationDistributionAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            $this->assertTargetAccessAndEvidence($locked, $target, $actor, $reason);
            $from = $locked->status;
            if (! in_array($target, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Invalid distribution agreement transition: {$from} -> {$target}."]);
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
            OrganizationDistributionAgreementEvent::create(['organization_distribution_agreement_id' => $locked->id, 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $target, 'reason' => $reason, 'operation_key' => $operationKey, 'reviewed_fingerprint' => $locked->authority_fingerprint]);

            return $locked->fresh(['publisher', 'distributor']);
        });
    }

    private function replay(OrganizationDistributionAgreementEvent $event, OrganizationDistributionAgreement $agreement, string $target, User $actor, ?string $reason): OrganizationDistributionAgreement
    {
        if ($event->organization_distribution_agreement_id !== $agreement->id || $event->to_status !== $target || $event->actor_id !== $actor->id || $event->reason !== $reason) {
            throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for another transition.']);
        }
        $locked = OrganizationDistributionAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
        if ($locked->status !== $target || $locked->latestEvent()->value('id') !== $event->id) {
            throw ValidationException::withMessages(['operation_key' => 'Operation key no longer represents the current agreement state.']);
        }
        $this->assertTargetAccessAndEvidence($locked, $target, $actor, $reason);
        if (in_array($target, ['verified', 'demo_accepted'], true) && ! $locked->fresh(['publisher', 'distributor'])->isCurrentlyVerified()) {
            throw ValidationException::withMessages(['status' => 'Distribution agreement is no longer canonically authoritative.']);
        }

        return $locked->fresh(['publisher', 'distributor']);
    }

    private function assertTargetAccessAndEvidence(OrganizationDistributionAgreement $agreement, string $target, User $actor, ?string $reason): void
    {
        $adminTarget = ['verified', 'demo_accepted', 'changes_requested', 'rejected', 'suspended', 'revoked'];
        if (in_array($target, $adminTarget, true) && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for this transition.']);
        }
        if (in_array($target, $adminTarget, true) && $actor->role !== 'admin') {
            abort(403, 'Only administrators can review distribution agreements.');
        }
        if ($target === 'demo_accepted' && ! $agreement->is_demo) {
            throw ValidationException::withMessages(['status' => 'Only demo agreements can receive demo acceptance.']);
        }
        if ($target === 'verified' && $agreement->is_demo) {
            throw ValidationException::withMessages(['status' => 'Demo agreements cannot receive legal verification.']);
        }
        $publisher = $agreement->publisher()->first();
        $distributor = $agreement->distributor()->first();
        if ($target === 'verified' && ($agreement->evidence_mode !== 'real_document' || blank($agreement->evidence_document) || filled($agreement->demo_reference) || $agreement->submitted_at === null || ! $publisher?->isVerified() || ! $distributor?->isVerified())) {
            throw ValidationException::withMessages(['status' => 'Live agreement verification requires canonical live evidence and verified parties.']);
        }
        if ($target === 'demo_accepted' && ($agreement->evidence_mode !== 'demo_statement' || blank($agreement->demo_reference) || filled($agreement->evidence_document) || $agreement->verified_at !== null || $publisher?->data_mode !== 'demo' || $distributor?->data_mode !== 'demo' || ! $publisher->hasAuthoritativeAcceptance() || ! $distributor->hasAuthoritativeAcceptance())) {
            throw ValidationException::withMessages(['status' => 'Demo agreement acceptance requires canonical demo evidence and demo parties.']);
        }
    }

    private function assertNoCrossTypeKey(string $operationKey): void
    {
        if (OrganizationReviewEvent::where('operation_key', $operationKey)->exists() || OrganizationRelationshipEvent::where('operation_key', $operationKey)->exists()) {
            throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for another transition type.']);
        }
    }
}

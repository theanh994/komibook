<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationDistributionAgreementEvent;
use App\Models\OrganizationRelationshipEvent;
use App\Models\OrganizationReviewEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationReviewService
{
    private const TRANSITIONS = ['draft' => ['pending_review'], 'pending_review' => ['verified', 'demo_accepted', 'rejected'], 'rejected' => ['pending_review'], 'verified' => ['suspended', 'archived'], 'demo_accepted' => ['suspended', 'archived'], 'suspended' => ['verified', 'demo_accepted', 'archived'], 'archived' => []];

    public function transition(Organization $organization, string $target, User $actor, string $reason, ?string $operationKey = null): Organization
    {
        $operationKey ??= 'organization-review:'.Str::uuid();

        return DB::transaction(function () use ($organization, $target, $actor, $reason, $operationKey): Organization {
            $this->assertNoCrossTypeKey($operationKey);
            $existing = OrganizationReviewEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                return $this->replay($existing, $organization, $target, $actor, $reason);
            }

            $locked = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $this->assertTargetAccessAndEvidence($locked, $target, $actor, $reason);
            $from = $locked->status;
            if (! in_array($target, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Invalid organization transition: {$from} -> {$target}."]);
            }
            $updates = ['status' => $target, 'verified_by' => $actor->id, 'last_review_reason' => $reason];
            if ($target === 'verified') {
                $updates += ['verified_at' => now(), 'suspended_at' => null, 'archived_at' => null];
            } elseif ($target === 'demo_accepted') {
                $updates += ['verified_at' => null, 'suspended_at' => null, 'archived_at' => null];
            } elseif ($target === 'suspended') {
                $updates['suspended_at'] = now();
            } elseif ($target === 'archived') {
                $updates['archived_at'] = now();
            }
            $locked->update($updates);
            OrganizationReviewEvent::create(['organization_id' => $locked->id, 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $target, 'reason' => $reason, 'operation_key' => $operationKey, 'reviewed_fingerprint' => $locked->authority_fingerprint]);

            return $locked->fresh();
        });
    }

    private function replay(OrganizationReviewEvent $event, Organization $organization, string $target, User $actor, string $reason): Organization
    {
        if ($event->organization_id !== $organization->id || $event->to_status !== $target || $event->actor_id !== $actor->id || $event->reason !== $reason) {
            throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for another transition.']);
        }
        $locked = Organization::query()->lockForUpdate()->findOrFail($organization->id);
        if ($locked->status !== $target || $locked->latestReviewEvent()->value('id') !== $event->id) {
            throw ValidationException::withMessages(['operation_key' => 'Operation key no longer represents the current organization state.']);
        }
        $this->assertTargetAccessAndEvidence($locked, $target, $actor, $reason);
        if (in_array($target, ['verified', 'demo_accepted'], true) && ! $locked->fresh()->hasAuthoritativeAcceptance()) {
            throw ValidationException::withMessages(['status' => 'Organization is no longer canonically authoritative.']);
        }

        return $locked->fresh();
    }

    private function assertTargetAccessAndEvidence(Organization $organization, string $target, User $actor, string $reason): void
    {
        if ($actor->role !== 'admin') {
            abort(403, 'Only administrators can review organizations.');
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for this transition.']);
        }
        if ($target === 'verified' && ($organization->data_mode === 'demo' || blank($organization->verification_document) || $organization->submitted_at === null)) {
            throw ValidationException::withMessages(['status' => 'Live verification requires submitted legal-document evidence.']);
        }
        if ($target === 'demo_accepted' && ($organization->data_mode !== 'demo' || filled($organization->verification_document) || $organization->verified_at !== null)) {
            throw ValidationException::withMessages(['status' => 'Demo acceptance requires demo-only evidence without legal verification.']);
        }
    }

    private function assertNoCrossTypeKey(string $operationKey): void
    {
        if (OrganizationRelationshipEvent::where('operation_key', $operationKey)->exists() || OrganizationDistributionAgreementEvent::where('operation_key', $operationKey)->exists()) {
            throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for another transition type.']);
        }
    }
}

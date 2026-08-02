<?php

namespace App\Services;

use App\Enums\VendorOnboardingStatus;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\VendorOnboardingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VendorOnboardingService
{
    public function transition(Vendor $vendor, VendorOnboardingStatus $target, User $actor, ?string $reason = null, ?string $operationKey = null): Vendor
    {
        $operationKey ??= 'vendor-onboarding:'.Str::uuid();

        return DB::transaction(function () use ($vendor, $target, $actor, $reason, $operationKey): Vendor {
            $existing = VendorOnboardingEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                if ($existing->vendor_id !== $vendor->id || $existing->to_status !== $target->value) {
                    throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for another transition.']);
                }

                return $vendor->fresh(['user']);
            }

            $locked = Vendor::withoutGlobalScopes()->lockForUpdate()->findOrFail($vendor->id);
            $from = $locked->onboarding_status instanceof VendorOnboardingStatus
                ? $locked->onboarding_status
                : VendorOnboardingStatus::from($locked->onboarding_status ?? 'draft');
            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages(['onboarding_status' => "Invalid vendor onboarding transition: {$from->value} -> {$target->value}."]);
            }
            if (in_array($target, [VendorOnboardingStatus::ChangesRequested, VendorOnboardingStatus::Rejected, VendorOnboardingStatus::Suspended, VendorOnboardingStatus::Revoked], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required for this transition.']);
            }
            if (in_array($target, [VendorOnboardingStatus::Submitted, VendorOnboardingStatus::Resubmitted, VendorOnboardingStatus::Approved], true)) {
                $this->assertComplete($locked);
            }

            $updates = [
                'onboarding_status' => $target,
                'status' => $this->legacyStatus($target),
                'last_review_reason' => $reason,
            ];
            $timestamp = match ($target) {
                VendorOnboardingStatus::Submitted, VendorOnboardingStatus::Resubmitted => 'submitted_at',
                VendorOnboardingStatus::UnderReview => 'review_started_at',
                VendorOnboardingStatus::Approved => 'approved_at',
                VendorOnboardingStatus::ChangesRequested => 'changes_requested_at',
                VendorOnboardingStatus::Rejected => 'rejected_at',
                VendorOnboardingStatus::Suspended => 'suspended_at',
                VendorOnboardingStatus::Revoked => 'revoked_at',
                default => null,
            };
            if ($timestamp) {
                $updates[$timestamp] = now();
            }
            if ($target === VendorOnboardingStatus::Approved) {
                $updates['payout_bank_status'] = $locked->is_demo ? 'demo_disabled' : 'verified';
                $updates['payout_bank_verified_at'] = $locked->is_demo ? null : now();
                $updates['payout_bank_verified_by'] = $locked->is_demo ? null : $actor->id;
            }
            if ($target === VendorOnboardingStatus::Resubmitted) {
                $updates['application_version'] = $locked->application_version + 1;
            }
            $locked->update($updates);

            if ($target === VendorOnboardingStatus::Approved && $locked->user->role !== 'admin') {
                $locked->user->update(['role' => 'vendor']);
            }

            VendorOnboardingEvent::create([
                'vendor_id' => $locked->id,
                'actor_id' => $actor->id,
                'from_status' => $from->value,
                'to_status' => $target->value,
                'reason' => $reason,
                'operation_key' => $operationKey,
            ]);
            UserNotification::firstOrCreate(
                ['operation_key' => 'notification:'.$operationKey],
                [
                    'user_id' => $locked->user_id,
                    'title' => 'Cập nhật hồ sơ nhà bán',
                    'content' => 'Hồ sơ nhà bán đã chuyển sang trạng thái '.$target->value.($reason ? '. Lý do: '.$reason : '.'),
                    'type' => 'system',
                    'data' => ['vendor_id' => $locked->id, 'onboarding_status' => $target->value],
                ]
            );

            return $locked->fresh(['user']);
        });
    }

    private function assertComplete(Vendor $vendor): void
    {
        $required = ['shop_name', 'slug', 'legal_name', 'tax_code'];
        if (! $vendor->is_demo) {
            $required = [...$required, 'business_registration_document', 'representative_identity_document',
                'payout_bank_account', 'payout_bank_name', 'payout_bank_holder'];
        }
        $missing = collect($required)->filter(fn (string $field): bool => blank($vendor->{$field}))->values();
        if (! $vendor->terms_accepted_at) {
            $missing->push('terms_accepted_at');
        }
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['profile' => 'Vendor profile is incomplete: '.$missing->implode(', ')]);
        }
    }

    private function legacyStatus(VendorOnboardingStatus $status): string
    {
        return match ($status) {
            VendorOnboardingStatus::Approved => 'active',
            VendorOnboardingStatus::Rejected, VendorOnboardingStatus::Suspended, VendorOnboardingStatus::Revoked => 'rejected',
            default => 'inactive',
        };
    }
}

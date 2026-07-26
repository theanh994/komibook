<?php

namespace App\Services;

use App\Enums\CopyrightClaimStatus;
use App\Models\CopyrightClaim;
use App\Models\CopyrightClaimEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CopyrightClaimService
{
    public function transition(CopyrightClaim $claim, CopyrightClaimStatus $target, User $actor, ?string $reason = null, ?string $operationKey = null): CopyrightClaim
    {
        $operationKey ??= 'copyright:'.Str::uuid();

        return DB::transaction(function () use ($claim, $target, $actor, $reason, $operationKey): CopyrightClaim {
            $existing = CopyrightClaimEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                if ($existing->copyright_claim_id !== $claim->id || $existing->to_status !== $target->value) {
                    throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for another transition.']);
                }

                return $claim->fresh(['book', 'ownerAuthor.user', 'participants.user']);
            }

            $locked = CopyrightClaim::lockForUpdate()->findOrFail($claim->id);
            $from = $locked->status;
            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => "Invalid copyright transition: {$from->value} -> {$target->value}."]);
            }
            if (in_array($target, [CopyrightClaimStatus::ChangesRequested, CopyrightClaimStatus::Rejected, CopyrightClaimStatus::Disputed, CopyrightClaimStatus::Revoked], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required for this transition.']);
            }
            if (in_array($target, [CopyrightClaimStatus::Submitted, CopyrightClaimStatus::Resubmitted], true)) {
                $this->assertNoOverlap($locked);
            }
            if ($target === CopyrightClaimStatus::Verified && $locked->participants()->wherePivotNull('accepted_at')->exists()) {
                throw ValidationException::withMessages(['participants' => 'Every copyright participant must accept before verification.']);
            }

            $updates = ['status' => $target, 'last_review_reason' => $reason];
            $timestamp = match ($target) {
                CopyrightClaimStatus::Submitted, CopyrightClaimStatus::Resubmitted => 'submitted_at',
                CopyrightClaimStatus::UnderReview => 'review_started_at',
                CopyrightClaimStatus::Verified => 'verified_at',
                CopyrightClaimStatus::ChangesRequested => 'changes_requested_at',
                CopyrightClaimStatus::Rejected => 'rejected_at',
                CopyrightClaimStatus::Disputed => 'disputed_at',
                CopyrightClaimStatus::Revoked => 'revoked_at',
                default => null,
            };
            if ($timestamp) {
                $updates[$timestamp] = now();
            }
            if ($target === CopyrightClaimStatus::Resubmitted) {
                $updates['application_version'] = $locked->application_version + 1;
            }
            $locked->update($updates);

            CopyrightClaimEvent::create([
                'copyright_claim_id' => $locked->id,
                'actor_id' => $actor->id,
                'from_status' => $from->value,
                'to_status' => $target->value,
                'reason' => $reason,
                'operation_key' => $operationKey,
            ]);

            $recipients = $locked->participants()->with('user')->get()->pluck('user_id')->push($locked->ownerAuthor->user_id)->unique();
            foreach ($recipients as $userId) {
                UserNotification::firstOrCreate(
                    ['operation_key' => "notification:{$operationKey}:{$userId}"],
                    [
                        'user_id' => $userId,
                        'title' => 'Cập nhật hồ sơ bản quyền',
                        'content' => 'Hồ sơ bản quyền đã chuyển sang trạng thái '.$target->value.($reason ? '. Lý do: '.$reason : '.'),
                        'type' => 'system',
                        'data' => ['copyright_claim_id' => $locked->id, 'status' => $target->value],
                    ]
                );
            }

            return $locked->fresh(['book', 'ownerAuthor.user', 'participants.user']);
        });
    }

    private function assertNoOverlap(CopyrightClaim $claim): void
    {
        $live = [
            CopyrightClaimStatus::Submitted->value, CopyrightClaimStatus::Resubmitted->value,
            CopyrightClaimStatus::UnderReview->value, CopyrightClaimStatus::Verified->value,
            CopyrightClaimStatus::Disputed->value,
        ];
        $overlap = CopyrightClaim::where('book_id', $claim->book_id)
            ->whereKeyNot($claim->id)
            ->whereIn('status', $live)
            ->where(function ($query) use ($claim) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $claim->valid_from ?? '1900-01-01');
            })
            ->where(function ($query) use ($claim) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $claim->valid_until ?? '9999-12-31');
            })->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['rights_scope' => 'A live overlapping copyright claim already exists for this book.']);
        }
    }
}

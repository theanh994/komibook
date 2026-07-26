<?php

namespace App\Services;

use App\Enums\AuthorOnboardingStatus;
use App\Models\Author;
use App\Models\AuthorOnboardingEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthorOnboardingService
{
    public function transition(
        Author $author,
        AuthorOnboardingStatus $target,
        User $actor,
        ?string $reason = null,
        ?string $operationKey = null,
        array $metadata = [],
    ): Author {
        $operationKey ??= 'author-onboarding:'.Str::uuid();

        return DB::transaction(function () use ($author, $target, $actor, $reason, $operationKey, $metadata): Author {
            $existing = AuthorOnboardingEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                if ($existing->author_id !== $author->id || $existing->to_status !== $target->value) {
                    throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for another transition.']);
                }

                return $author->fresh();
            }

            $locked = Author::query()->lockForUpdate()->findOrFail($author->id);
            $from = $locked->onboarding_status instanceof AuthorOnboardingStatus
                ? $locked->onboarding_status
                : AuthorOnboardingStatus::from($locked->onboarding_status);

            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'onboarding_status' => "Invalid author onboarding transition: {$from->value} -> {$target->value}.",
                ]);
            }

            if (in_array($target, [AuthorOnboardingStatus::ChangesRequested, AuthorOnboardingStatus::Rejected, AuthorOnboardingStatus::Suspended, AuthorOnboardingStatus::Revoked], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required for this transition.']);
            }

            if ($target === AuthorOnboardingStatus::Submitted || $target === AuthorOnboardingStatus::Resubmitted || $target === AuthorOnboardingStatus::Approved) {
                $this->assertComplete($locked);
            }

            if ($target === AuthorOnboardingStatus::Approved && ! $locked->phone_verified_at) {
                throw ValidationException::withMessages(['phone_verified_at' => 'Tác giả phải xác minh số điện thoại trước khi được phê duyệt.']);
            }

            $updates = [
                'onboarding_status' => $target,
                'status' => $this->legacyStatus($target),
                'last_review_reason' => $reason,
                'rejection_reason' => $target === AuthorOnboardingStatus::Rejected ? $reason : null,
            ];

            $timestampColumn = match ($target) {
                AuthorOnboardingStatus::Submitted, AuthorOnboardingStatus::Resubmitted => 'submitted_at',
                AuthorOnboardingStatus::UnderReview => 'review_started_at',
                AuthorOnboardingStatus::Approved => 'approved_at',
                AuthorOnboardingStatus::ChangesRequested => 'changes_requested_at',
                AuthorOnboardingStatus::Rejected => 'rejected_at',
                AuthorOnboardingStatus::Suspended => 'suspended_at',
                AuthorOnboardingStatus::Revoked => 'revoked_at',
                default => null,
            };
            if ($timestampColumn) {
                $updates[$timestampColumn] = now();
            }
            if ($target === AuthorOnboardingStatus::Resubmitted) {
                $updates['application_version'] = $locked->application_version + 1;
            }

            $locked->update($updates);

            AuthorOnboardingEvent::create([
                'author_id' => $locked->id,
                'actor_id' => $actor->id,
                'from_status' => $from->value,
                'to_status' => $target->value,
                'reason' => $reason,
                'operation_key' => $operationKey,
                'metadata' => $metadata ?: null,
            ]);

            UserNotification::firstOrCreate(
                ['operation_key' => 'notification:'.$operationKey],
                [
                    'user_id' => $locked->user_id,
                    'title' => 'Cập nhật hồ sơ tác giả',
                    'content' => $this->notificationContent($target, $reason),
                    'type' => 'system',
                    'data' => ['author_id' => $locked->id, 'onboarding_status' => $target->value],
                ]
            );

            return $locked->fresh(['user']);
        });
    }

    private function assertComplete(Author $author): void
    {
        $missing = collect(['pen_name', 'identity_document', 'bank_account_number', 'bank_name', 'bank_holder_name'])
            ->filter(fn (string $field): bool => blank($author->{$field}) || $author->{$field} === 'Pending')
            ->values();

        if (! $author->terms_accepted_at) {
            $missing->push('terms_accepted_at');
        }

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['profile' => 'Author profile is incomplete: '.$missing->implode(', ')]);
        }
    }

    private function legacyStatus(AuthorOnboardingStatus $status): string
    {
        return match ($status) {
            AuthorOnboardingStatus::Approved => 'active',
            AuthorOnboardingStatus::Rejected, AuthorOnboardingStatus::Suspended, AuthorOnboardingStatus::Revoked => 'rejected',
            default => 'pending',
        };
    }

    private function notificationContent(AuthorOnboardingStatus $status, ?string $reason): string
    {
        $content = 'Hồ sơ tác giả đã chuyển sang trạng thái '.$status->value.'.';

        return $reason ? $content.' Lý do: '.$reason : $content;
    }
}

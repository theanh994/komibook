<?php

namespace App\Enums;

enum VendorOnboardingStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case ChangesRequested = 'changes_requested';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Revoked = 'revoked';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted, self::Resubmitted => [self::UnderReview],
            self::UnderReview => [self::Approved, self::ChangesRequested, self::Rejected],
            self::ChangesRequested => [self::Resubmitted],
            self::Approved => [self::Suspended, self::Revoked],
            self::Rejected, self::Suspended, self::Revoked => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}

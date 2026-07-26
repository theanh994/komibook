<?php

namespace App\Enums;

enum CopyrightClaimStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case ChangesRequested = 'changes_requested';
    case Resubmitted = 'resubmitted';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Disputed = 'disputed';
    case Revoked = 'revoked';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted, self::Resubmitted => [self::UnderReview],
            self::UnderReview => [self::Verified, self::ChangesRequested, self::Rejected],
            self::ChangesRequested => [self::Resubmitted],
            self::Verified => [self::Disputed, self::Revoked],
            self::Rejected, self::Disputed, self::Revoked => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}

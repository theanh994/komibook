<?php

namespace App\Enums;

enum BookPublicationStatus: string
{
    case Draft = 'draft';
    case SubmittedForReview = 'submitted_for_review';
    case Resubmitted = 'resubmitted';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Draft => [self::Published, self::Scheduled, self::SubmittedForReview, self::Resubmitted],
            self::SubmittedForReview, self::Resubmitted => [self::Draft, self::Published, self::Scheduled, self::ChangesRequested, self::Approved],
            self::ChangesRequested => [self::Draft, self::Published, self::Scheduled],
            self::Approved => [self::Scheduled, self::Published],
            self::Scheduled => [self::Published],
            self::Published => [],
        }, true);
    }
}

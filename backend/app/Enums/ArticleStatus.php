<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Unpublished = 'unpublished';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Draft, self::ChangesRequested => [self::Submitted],
            self::Submitted => [self::UnderReview],
            self::UnderReview => [self::Approved, self::ChangesRequested, self::Rejected],
            self::Approved => [self::Scheduled, self::Published],
            self::Scheduled => [self::Published, self::Archived],
            self::Published => [self::Unpublished, self::Archived],
            self::Unpublished => [self::Published, self::Archived],
            self::Rejected => [self::Draft, self::Archived],
            self::Archived => [],
        }, true);
    }
}

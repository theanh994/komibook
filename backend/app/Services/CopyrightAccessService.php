<?php

namespace App\Services;

use App\Enums\AuthorOnboardingStatus;
use App\Models\AuthorDelegation;
use App\Models\Book;
use App\Models\BookAuthor;
use App\Models\User;

class CopyrightAccessService
{
    public function authorCanManage(User $user, Book $book): bool
    {
        $author = $user->author;
        if ($author && $author->onboarding_status === AuthorOnboardingStatus::Approved) {
            if (BookAuthor::where('book_id', $book->id)->where('author_id', $author->id)->where('status', 'accepted')->exists()) {
                return true;
            }
        }

        return AuthorDelegation::where('delegate_user_id', $user->id)
            ->where(fn ($query) => $query->whereNull('book_id')->orWhere('book_id', $book->id))
            ->get()
            ->contains(fn (AuthorDelegation $delegation) => $delegation->isActiveFor('manage_copyright'));
    }
}

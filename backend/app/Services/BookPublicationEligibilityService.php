<?php

namespace App\Services;

use App\Enums\AuthorOnboardingStatus;
use App\Enums\CopyrightClaimStatus;
use App\Enums\VendorOnboardingStatus;
use App\Models\Book;
use Illuminate\Validation\ValidationException;

class BookPublicationEligibilityService
{
    public function assertEligible(Book $book): void
    {
        $errors = [];
        $book->loadMissing(['vendor', 'authorRelations.author', 'copyrightClaims', 'chapters']);

        if ($book->vendor?->status !== 'active' || $book->vendor?->onboarding_status !== VendorOnboardingStatus::Approved) {
            $errors['vendor'] = 'An active approved vendor is required.';
        }
        $acceptedAuthors = $book->authorRelations->where('status', 'accepted')->whereIn('role', ['primary', 'coauthor']);
        if ($acceptedAuthors->where('role', 'primary')->isEmpty() || $acceptedAuthors->contains(fn ($relation) => $relation->author->onboarding_status !== AuthorOnboardingStatus::Approved)) {
            $errors['authors'] = 'Every publishing author must have an accepted relation and approved profile.';
        }
        $hasVerifiedClaim = $book->copyrightClaims->contains(fn ($claim) => $claim->status === CopyrightClaimStatus::Verified
            && (! $claim->valid_from || $claim->valid_from->startOfDay()->lte(now()))
            && (! $claim->valid_until || $claim->valid_until->endOfDay()->gte(now())));
        if (! $hasVerifiedClaim) {
            $errors['copyright'] = 'A currently valid verified copyright claim is required.';
        }
        if (blank($book->title) || blank($book->description) || blank($book->cover_image) || ! $book->category_id) {
            $errors['metadata'] = 'Title, description, cover and category are required.';
        }
        if ($book->price < 0) {
            $errors['price'] = 'Price must be zero or greater.';
        }
        if ($book->type === 'physical' && $book->stock < 1) {
            $errors['inventory'] = 'A physical book requires sellable stock.';
        }
        if ($book->type === 'ebook' && blank($book->file_path) && $book->chapters->isEmpty()) {
            $errors['content'] = 'An ebook requires a private file or at least one chapter.';
        }
        $agreement = $book->royaltyAgreements()->with('acceptances')->latest('version')->first();
        $shareAuthorIds = collect($agreement?->shares ?? [])->pluck('author_id')->map(fn ($id) => (int) $id)->sort()->values();
        $acceptedIds = $agreement?->acceptances->pluck('author_id')->map(fn ($id) => (int) $id)->sort()->values() ?? collect();
        if (! $agreement || abs((float) collect($agreement->shares)->sum('share_percent') - 100.0) > 0.001 || $shareAuthorIds->all() !== $acceptedIds->all()) {
            $errors['royalty'] = 'Every author must accept a royalty agreement totaling 100 percent.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}

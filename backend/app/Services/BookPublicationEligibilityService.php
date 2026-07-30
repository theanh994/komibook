<?php

namespace App\Services;

use App\Enums\VendorOnboardingStatus;
use App\Models\Book;
use Illuminate\Validation\ValidationException;

class BookPublicationEligibilityService
{
    public function assertEligible(Book $book): void
    {
        $errors = [];
        $book->loadMissing(['vendor', 'chapters', 'activeCommercialParties.organization']);

        if ($book->vendor?->status !== 'active' || $book->vendor?->onboarding_status !== VendorOnboardingStatus::Approved) {
            $errors['vendor'] = 'An active approved vendor is required.';
        }
        if ($book->provenance !== 'used_resale') {
            $roles = $book->activeCommercialParties->pluck('role')->unique();
            if (collect(CommercialPartyService::ROLES)->diff($roles)->isNotEmpty()) {
                $errors['commercial_parties'] = 'Publisher, supplier and responsible organization are required.';
            }
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
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}

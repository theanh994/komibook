<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookPublicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\RoyaltyAgreement;
use App\Models\RoyaltyAgreementAcceptance;
use App\Services\BookPublishingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookPublishingController extends Controller
{
    public function show(Book $book)
    {
        return response()->json(['status' => 'success', 'data' => $book->load([
            'authorRelations.author.user', 'copyrightClaims.ownerAuthor.user', 'royaltyAgreements.acceptances',
            'publishingEvents', 'publishedRevisions',
        ])]);
    }

    public function submit(Request $request, Book $book, BookPublishingService $service)
    {
        $target = ($book->publication_version ?? 1) > 1 ? BookPublicationStatus::Resubmitted : BookPublicationStatus::SubmittedForReview;
        $updated = $service->transition($book, $target, $request->user(), operationKey: $request->input('operation_key') ?? $request->header('Idempotency-Key'));

        return response()->json(['status' => 'success', 'data' => $updated]);
    }

    public function returnToDraft(Request $request, Book $book, BookPublishingService $service)
    {
        $updated = $service->transition($book, BookPublicationStatus::Draft, $request->user(), operationKey: $request->input('operation_key'));

        return response()->json(['status' => 'success', 'data' => $updated]);
    }

    public function publish(Request $request, Book $book, BookPublishingService $service)
    {
        $validated = $request->validate(['scheduled_for' => 'nullable|date|after:now', 'operation_key' => 'nullable|string|max:128']);
        $target = isset($validated['scheduled_for']) ? BookPublicationStatus::Scheduled : BookPublicationStatus::Published;
        $updated = $service->transition($book, $target, $request->user(), operationKey: $validated['operation_key'] ?? null, scheduledFor: $validated['scheduled_for'] ?? null);

        return response()->json(['status' => 'success', 'data' => $updated]);
    }

    public function acceptRoyalty(Request $request, Book $book)
    {
        $validated = $request->validate([
            'shares' => 'required|array|min:1', 'shares.*.author_id' => 'required|integer|distinct',
            'shares.*.share_percent' => 'required|numeric|min:0.01|max:100', 'operation_key' => 'nullable|string|max:128',
        ]);
        $acceptedAuthorIds = $book->authorRelations()->where('status', 'accepted')->pluck('author_id');
        $shareIds = collect($validated['shares'])->pluck('author_id');
        if ($shareIds->diff($acceptedAuthorIds)->isNotEmpty() || $acceptedAuthorIds->diff($shareIds)->isNotEmpty() || abs((float) collect($validated['shares'])->sum('share_percent') - 100.0) > 0.001) {
            throw ValidationException::withMessages(['shares' => 'Shares must cover accepted book authors and total 100 percent.']);
        }
        $operationKey = $validated['operation_key'] ?? 'royalty-agreement:'.Str::uuid();
        $agreement = DB::transaction(function () use ($book, $request, $validated, $operationKey) {
            $existing = RoyaltyAgreement::where('operation_key', $operationKey)->first();
            if ($existing) {
                return $existing;
            }

            return RoyaltyAgreement::create([
                'book_id' => $book->id, 'version' => (int) $book->royaltyAgreements()->max('version') + 1,
                'shares' => $validated['shares'], 'proposed_by' => $request->user()->id,
                'proposed_at' => now(), 'operation_key' => $operationKey,
            ]);
        });

        return response()->json(['status' => 'success', 'data' => $agreement], 201);
    }

    public function acceptRoyaltyAsAuthor(Request $request, RoyaltyAgreement $agreement)
    {
        $author = $request->user()->author;
        abort_unless($author && collect($agreement->shares)->contains(fn ($share) => (int) $share['author_id'] === $author->id), 403);
        abort_unless($agreement->book->authorRelations()->where('author_id', $author->id)->where('status', 'accepted')->exists(), 403);
        $operationKey = $request->input('operation_key') ?? $request->header('Idempotency-Key') ?? 'royalty-accept:'.Str::uuid();
        $acceptance = RoyaltyAgreementAcceptance::firstOrCreate(
            ['royalty_agreement_id' => $agreement->id, 'author_id' => $author->id],
            ['accepted_by' => $request->user()->id, 'accepted_at' => now(), 'operation_key' => $operationKey],
        );

        return response()->json(['status' => 'success', 'data' => $acceptance], 201);
    }

    public function authorRoyaltyAgreements(Request $request)
    {
        $author = $request->user()->author;
        abort_unless($author, 403);
        $bookIds = $author->bookRelations()->where('status', 'accepted')->pluck('book_id');
        $agreements = RoyaltyAgreement::with(['acceptances', 'book'])
            ->whereIn('book_id', $bookIds)->latest()->get()
            ->filter(fn ($agreement) => collect($agreement->shares)->contains(fn ($share) => (int) $share['author_id'] === $author->id))
            ->values();

        return response()->json(['status' => 'success', 'data' => $agreements]);
    }
}

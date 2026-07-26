<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuthorOnboardingStatus;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\AuthorDelegation;
use App\Models\Book;
use App\Models\BookAuthor;
use App\Services\RightsRelationAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookAuthorController extends Controller
{
    public function inviteByVendor(Request $request, Book $book, RightsRelationAuditService $audit)
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor && $book->vendor_id === $vendor->id, 404);
        $validated = $request->validate([
            'author_id' => 'required|integer|exists:authors,id',
            'role' => 'required|in:primary,coauthor,editor,translator',
            'operation_key' => 'nullable|string|max:100',
        ]);
        $author = Author::findOrFail($validated['author_id']);
        if ($author->onboarding_status !== AuthorOnboardingStatus::Approved) {
            throw ValidationException::withMessages(['author_id' => 'Author profile must be approved.']);
        }
        $operationKey = $validated['operation_key'] ?? 'book-author:'.Str::uuid();
        $relation = BookAuthor::firstOrCreate(
            ['book_id' => $book->id, 'author_id' => $author->id],
            [
                'invited_by' => $request->user()->id,
                'role' => $validated['role'],
                'status' => 'pending',
                'operation_key' => $operationKey,
            ]
        );
        if ($relation->wasRecentlyCreated) {
            $audit->record('book_author', $relation->id, $request->user()->id, 'invited', $operationKey, $author->user_id, metadata: ['book_id' => $book->id, 'role' => $validated['role']]);
        }

        return response()->json(['status' => 'success', 'data' => $relation], 201);
    }

    public function respond(Request $request, BookAuthor $bookAuthor, RightsRelationAuditService $audit)
    {
        $validated = $request->validate(['decision' => 'required|in:accepted,rejected', 'reason' => 'nullable|string|max:500', 'operation_key' => 'nullable|string|max:100']);
        abort_unless($request->user()->author?->id === $bookAuthor->author_id, 403);
        if ($bookAuthor->status !== 'pending') {
            throw ValidationException::withMessages(['decision' => 'This invitation is no longer pending.']);
        }
        $bookAuthor->update([
            'status' => $validated['decision'],
            'accepted_at' => $validated['decision'] === 'accepted' ? now() : null,
            'reason' => $validated['reason'] ?? null,
        ]);
        $audit->record('book_author', $bookAuthor->id, $request->user()->id, $validated['decision'], $validated['operation_key'] ?? 'book-author-response:'.Str::uuid(), $bookAuthor->invited_by, $validated['reason'] ?? null);

        return response()->json(['status' => 'success', 'data' => $bookAuthor->fresh()]);
    }

    public function inviteDelegate(Request $request, Book $book, RightsRelationAuditService $audit)
    {
        $author = $request->user()->author;
        abort_unless($author && BookAuthor::where('book_id', $book->id)->where('author_id', $author->id)->where('status', 'accepted')->exists(), 403);
        $validated = $request->validate([
            'delegate_user_id' => 'required|integer|exists:users,id',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'in:manage_copyright,edit_content,submit_for_review,view_royalty',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'operation_key' => 'nullable|string|max:100',
        ]);
        if ((int) $validated['delegate_user_id'] === $request->user()->id) {
            throw ValidationException::withMessages(['delegate_user_id' => 'An author cannot delegate to the same account.']);
        }
        $operationKey = $validated['operation_key'] ?? 'author-delegation:'.Str::uuid();
        $delegation = AuthorDelegation::create([
            'grantor_author_id' => $author->id,
            'delegate_user_id' => $validated['delegate_user_id'],
            'book_id' => $book->id,
            'permissions' => array_values(array_unique($validated['permissions'])),
            'status' => 'pending',
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'operation_key' => $operationKey,
        ]);
        $audit->record('author_delegation', $delegation->id, $request->user()->id, 'invited', $operationKey, $delegation->delegate_user_id, metadata: ['book_id' => $book->id, 'permissions' => $delegation->permissions]);

        return response()->json(['status' => 'success', 'data' => $delegation], 201);
    }

    public function respondDelegation(Request $request, AuthorDelegation $delegation, RightsRelationAuditService $audit)
    {
        $validated = $request->validate(['decision' => 'required|in:accepted,rejected', 'reason' => 'nullable|string|max:500', 'operation_key' => 'nullable|string|max:100']);
        abort_unless($delegation->delegate_user_id === $request->user()->id, 403);
        abort_unless($delegation->status === 'pending', 422);
        $delegation->update([
            'status' => $validated['decision'],
            'accepted_at' => $validated['decision'] === 'accepted' ? now() : null,
            'reason' => $validated['reason'] ?? null,
        ]);
        $audit->record('author_delegation', $delegation->id, $request->user()->id, $validated['decision'], $validated['operation_key'] ?? 'delegation-response:'.Str::uuid(), $delegation->grantor_author_id ? Author::find($delegation->grantor_author_id)?->user_id : null, $validated['reason'] ?? null);

        return response()->json(['status' => 'success', 'data' => $delegation->fresh()]);
    }

    public function revokeDelegation(Request $request, AuthorDelegation $delegation, RightsRelationAuditService $audit)
    {
        abort_unless($request->user()->author?->id === $delegation->grantor_author_id, 403);
        $validated = $request->validate(['reason' => 'required|string|max:500', 'operation_key' => 'nullable|string|max:100']);
        $delegation->update(['status' => 'revoked', 'revoked_at' => now(), 'reason' => $validated['reason']]);
        $audit->record('author_delegation', $delegation->id, $request->user()->id, 'revoked', $validated['operation_key'] ?? 'delegation-revoke:'.Str::uuid(), $delegation->delegate_user_id, $validated['reason']);

        return response()->json(['status' => 'success']);
    }
}

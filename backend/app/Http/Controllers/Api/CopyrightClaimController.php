<?php

namespace App\Http\Controllers\Api;

use App\Enums\CopyrightClaimStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookAuthor;
use App\Models\CopyrightClaim;
use App\Services\CopyrightAccessService;
use App\Services\CopyrightClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CopyrightClaimController extends Controller
{
    public function store(Request $request, Book $book, CopyrightAccessService $access)
    {
        abort_unless($access->authorCanManage($request->user(), $book), 403);
        $validated = $request->validate($this->rules());
        $owner = $request->user()->author;
        if (! $owner || ! BookAuthor::where('book_id', $book->id)->where('author_id', $owner->id)->where('status', 'accepted')->exists()) {
            $owner = BookAuthor::with('author')->where('book_id', $book->id)->where('status', 'accepted')->whereIn('role', ['primary', 'coauthor'])->firstOrFail()->author;
        }
        $claim = CopyrightClaim::create([
            ...collect($validated)->except(['evidence_document', 'operation_key'])->all(),
            'book_id' => $book->id,
            'owner_author_id' => $owner->id,
            'evidence_document' => $request->file('evidence_document')->store('copyright/evidence', 'private'),
            'status' => CopyrightClaimStatus::Draft,
        ]);
        $authors = BookAuthor::where('book_id', $book->id)->where('status', 'accepted')->whereIn('role', ['primary', 'coauthor'])->pluck('author_id');
        foreach ($authors as $authorId) {
            $claim->participants()->attach($authorId, ['role' => $authorId === $owner->id ? 'owner' : 'coauthor', 'accepted_at' => now()]);
        }

        return response()->json(['status' => 'success', 'data' => $claim->load('participants')], 201);
    }

    public function submit(Request $request, CopyrightClaim $claim, CopyrightAccessService $access, CopyrightClaimService $service)
    {
        abort_unless($access->authorCanManage($request->user(), $claim->book), 403);
        $target = $claim->status === CopyrightClaimStatus::ChangesRequested ? CopyrightClaimStatus::Resubmitted : CopyrightClaimStatus::Submitted;
        $updated = $service->transition($claim, $target, $request->user(), operationKey: $request->input('operation_key') ?? $request->header('Idempotency-Key') ?? 'copyright-submit:'.Str::uuid());

        return response()->json(['status' => 'success', 'data' => $updated]);
    }

    public function update(Request $request, CopyrightClaim $claim, CopyrightAccessService $access)
    {
        abort_unless($access->authorCanManage($request->user(), $claim->book), 403);
        abort_unless(in_array($claim->status, [CopyrightClaimStatus::Draft, CopyrightClaimStatus::ChangesRequested], true), 422);

        $validated = $request->validate($this->rules(evidenceRequired: false));
        $updates = collect($validated)->except(['evidence_document', 'operation_key'])->all();
        if ($request->hasFile('evidence_document')) {
            $updates['evidence_document'] = $request->file('evidence_document')->store('copyright/evidence', 'private');
        }
        $claim->update($updates);

        return response()->json(['status' => 'success', 'data' => $claim->fresh(['book', 'ownerAuthor.user', 'participants.user'])]);
    }

    public function show(Request $request, CopyrightClaim $claim, CopyrightAccessService $access)
    {
        abort_unless($request->user()->role === 'admin' || $access->authorCanManage($request->user(), $claim->book), 403);

        return response()->json(['status' => 'success', 'data' => $claim->load(['book', 'ownerAuthor.user', 'participants.user', 'events'])]);
    }

    public function downloadEvidence(Request $request, CopyrightClaim $claim, CopyrightAccessService $access)
    {
        abort_unless($request->user()->role === 'admin' || $access->authorCanManage($request->user(), $claim->book), 403);
        abort_unless(Storage::disk('private')->exists($claim->evidence_document), 404);

        return response()->file(Storage::disk('private')->path($claim->evidence_document), ['X-Content-Type-Options' => 'nosniff']);
    }

    private function rules(bool $evidenceRequired = true): array
    {
        return [
            'registration_type' => 'required|in:original_work,exclusive_license,nonexclusive_license,public_domain',
            'registration_number' => 'nullable|string|max:100',
            'rights_scope' => 'required|array|min:1',
            'rights_scope.*' => 'in:reproduce,distribute,display,adapt,translate,digital,print',
            'territory_scope' => 'required|array|min:1',
            'territory_scope.*' => 'string|max:64',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'evidence_document' => ($evidenceRequired ? 'required' : 'nullable').'|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'operation_key' => 'nullable|string|max:100',
        ];
    }
}

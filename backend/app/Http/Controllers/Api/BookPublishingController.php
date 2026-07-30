<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookPublicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\BookPublishingService;
use Illuminate\Http\Request;

class BookPublishingController extends Controller
{
    public function show(Book $book)
    {
        return response()->json(['status' => 'success', 'data' => $book->load([
            'publishingEvents', 'publishedRevisions', 'activeCommercialParties.organization',
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
}

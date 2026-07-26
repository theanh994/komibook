<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookPublicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\BookPublishingService;
use Illuminate\Http\Request;

class BookPublishingController extends Controller
{
    public function transition(Request $request, Book $book, BookPublishingService $service)
    {
        $validated = $request->validate([
            'to_status' => 'required|in:approved,changes_requested',
            'reason' => 'nullable|string|max:2000', 'operation_key' => 'nullable|string|max:128',
        ]);
        $updated = $service->transition(
            $book, BookPublicationStatus::from($validated['to_status']), $request->user(),
            $validated['reason'] ?? null, $validated['operation_key'] ?? null,
        );

        return response()->json(['status' => 'success', 'data' => $updated]);
    }
}

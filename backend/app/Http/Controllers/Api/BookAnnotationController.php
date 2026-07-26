<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookAnnotation;
use App\Models\BookChapter;
use App\Services\EbookAccessService;
use Illuminate\Http\Request;

class BookAnnotationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $ebookAccessService = app(EbookAccessService::class);

        if ($request->filled('book_id')) {
            $bookId = (int) $request->book_id;
            $order = $ebookAccessService->getValidOrder($user, $bookId);

            if (! $order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền truy cập ebook này.',
                ], 403);
            }

            $annotations = BookAnnotation::where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->with('book')
                ->latest()
                ->get()
                ->map(function ($item) use ($order) {
                    $item->order_id = $order->id;

                    return $item;
                });

            return response()->json([
                'status' => 'success',
                'data' => $annotations,
            ]);
        }

        // Listing all annotations of current user
        $rawAnnotations = BookAnnotation::where('user_id', $user->id)
            ->with('book')
            ->latest()
            ->get();

        $validOrdersByBook = $ebookAccessService
            ->getValidOrdersForBooks($user, $rawAnnotations->pluck('book_id')->unique());

        $filteredAnnotations = $rawAnnotations->filter(function ($item) use ($validOrdersByBook) {
            return isset($validOrdersByBook[$item->book_id]);
        })->values()->map(function ($item) use ($validOrdersByBook) {
            $item->order_id = $validOrdersByBook[$item->book_id]->id;

            return $item;
        });

        return response()->json([
            'status' => 'success',
            'data' => $filteredAnnotations,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        $user = $request->user();
        $bookId = (int) $request->book_id;
        $ebookAccessService = app(EbookAccessService::class);
        $order = $ebookAccessService->getValidOrder($user, $bookId);

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền tạo ghi chú cho ebook này.',
            ], 403);
        }

        $request->validate([
            'type' => 'required|in:highlight,note,bookmark',
            'book_chapter_id' => 'nullable|integer|exists:book_chapters,id|required_without:page_number',
            'chapter' => 'nullable|string|max:255',
            'location_key' => 'nullable|string|max:255',
            'highlighted_text' => 'nullable|string',
            'note_content' => 'nullable|string',
            'color' => 'nullable|string',
            'page_number' => 'nullable|integer|min:1|required_without:book_chapter_id',
            'position_start' => 'nullable|integer|min:0',
            'position_end' => 'nullable|integer|min:0|gte:position_start',
        ]);

        if ($request->filled('book_chapter_id') && ! BookChapter::whereKey($request->integer('book_chapter_id'))->where('book_id', $bookId)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Chương không thuộc ebook này.'], 422);
        }

        if ($request->integer('page_number') > 0) {
            $bookPages = (int) Book::withoutGlobalScopes()->whereKey($bookId)->value('pages');
            if ($bookPages > 0 && $request->integer('page_number') > $bookPages) {
                return response()->json(['status' => 'error', 'message' => 'Trang không tồn tại trong ebook này.'], 422);
            }
        }

        $annotation = BookAnnotation::create([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'book_chapter_id' => $request->book_chapter_id,
            'chapter' => $request->chapter,
            'location_key' => $request->location_key,
            'highlighted_text' => $request->highlighted_text,
            'note_content' => $request->note_content,
            'type' => $request->type,
            'color' => $request->color,
            'page_number' => $request->page_number,
            'position_start' => $request->position_start,
            'position_end' => $request->position_end,
        ]);

        $annotation->order_id = $order->id;

        return response()->json([
            'status' => 'success',
            'message' => 'Annotation saved.',
            'data' => $annotation,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $annotation = BookAnnotation::where('user_id', $request->user()->id)->findOrFail($id);

        if (! app(EbookAccessService::class)->checkAccess($request->user(), (int) $annotation->book_id)) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không còn quyền sửa ghi chú của ebook này.'], 403);
        }

        $request->validate([
            'note_content' => 'nullable|string',
            'color' => 'nullable|string',
        ]);

        $annotation->update($request->only(['note_content', 'color']));

        return response()->json([
            'status' => 'success',
            'message' => 'Annotation updated.',
            'data' => $annotation,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $annotation = BookAnnotation::where('user_id', $request->user()->id)->findOrFail($id);
        if (! app(EbookAccessService::class)->checkAccess($request->user(), (int) $annotation->book_id)) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không còn quyền xóa ghi chú của ebook này.'], 403);
        }
        $annotation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Annotation deleted.',
        ]);
    }

    public function recent(Request $request, $bookId)
    {
        $user = $request->user();
        $ebookAccessService = app(EbookAccessService::class);
        $order = $ebookAccessService->getValidOrder($user, (int) $bookId);

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền truy cập ebook này.',
            ], 403);
        }

        $annotations = BookAnnotation::where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($item) use ($order) {
                $item->order_id = $order->id;

                return $item;
            });

        return response()->json([
            'status' => 'success',
            'data' => $annotations,
        ]);
    }
}

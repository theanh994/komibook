<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookChapter;
use App\Models\ReadingProgress;
use App\Services\EbookAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReadingProgressController extends Controller
{
    public function show(Request $request, Book $book, EbookAccessService $access): JsonResponse
    {
        abort_unless($access->checkAccess($request->user(), $book->id), 403, 'Bạn không có quyền đọc ebook này.');

        return response()->json(['status' => 'success', 'data' => ReadingProgress::where([
            'user_id' => $request->user()->id, 'book_id' => $book->id,
        ])->first()]);
    }

    public function update(Request $request, Book $book, EbookAccessService $access): JsonResponse
    {
        $validated = $request->validate([
            'book_chapter_id' => ['nullable', 'integer', 'exists:book_chapters,id'],
            'location_key' => ['nullable', 'string', 'max:255'],
            'current_page' => ['nullable', 'integer', 'min:1'],
            'total_pages' => ['nullable', 'integer', 'min:1'],
            'progress_percent' => ['nullable', 'numeric', 'between:0,100'],
            'version' => ['nullable', 'integer', 'min:0'],
        ]);
        $order = $access->getValidOrder($request->user(), $book->id);
        abort_unless($order, 403, 'Bạn không có quyền đọc ebook này.');
        abort_if(isset($validated['book_chapter_id']) && ! BookChapter::whereKey($validated['book_chapter_id'])->where('book_id', $book->id)->exists(), 422, 'Chương không thuộc ebook này.');
        abort_if(isset($validated['current_page'], $validated['total_pages']) && $validated['current_page'] > $validated['total_pages'], 422, 'Trang hiện tại vượt quá tổng số trang.');

        $progress = DB::transaction(function () use ($request, $book, $order, $validated) {
            $progress = ReadingProgress::where(['user_id' => $request->user()->id, 'book_id' => $book->id])->lockForUpdate()->first();
            if ($progress && isset($validated['version']) && $validated['version'] !== $progress->version) {
                abort(409, 'Tiến độ đã được cập nhật trên thiết bị khác.');
            }
            $percent = $validated['progress_percent'] ?? null;
            if (isset($validated['current_page'], $validated['total_pages'])) {
                $percent = round($validated['current_page'] / $validated['total_pages'] * 100, 2);
            }
            $attributes = collect($validated)->except('version')->all() + [
                'purchase_order_id' => $order->id, 'progress_percent' => $percent ?? ($progress?->progress_percent ?? 0),
                'version' => ($progress?->version ?? 0) + 1, 'last_read_at' => now(),
            ];
            if ($progress) {
                $progress->update($attributes);

                return $progress;
            }

            return ReadingProgress::create($attributes + ['user_id' => $request->user()->id, 'book_id' => $book->id]);
        });

        return response()->json(['status' => 'success', 'data' => $progress]);
    }
}

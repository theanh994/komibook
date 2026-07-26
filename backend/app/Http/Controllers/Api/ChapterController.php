<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookChapter;
use App\Models\BookChapterRevision;
use App\Services\EbookAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChapterController extends Controller
{
    /**
     * Lấy danh sách chương của cuốn sách (dành cho quản lý vendor).
     */
    public function index($bookId)
    {
        $vendor = Auth::user()->vendor;
        $book = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->findOrFail($bookId);
        $chapters = $book->chapters;

        return response()->json([
            'status' => 'success',
            'data' => $chapters,
        ]);
    }

    /**
     * Thêm chương mới cho sách.
     */
    public function store(Request $request, $bookId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_free' => 'nullable|boolean',
            'status' => 'nullable|in:draft,published',
        ]);

        $vendor = Auth::user()->vendor;
        $book = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->findOrFail($bookId);

        $order = $request->order ?? ($book->chapters()->max('order') + 1);

        $chapter = BookChapter::create([
            'book_id' => $book->id,
            'title' => $request->title,
            'content' => $request->content,
            'order' => $order,
            'is_free' => $request->is_free ?? false,
            'status' => $request->status ?? 'draft',
        ]);
        $this->recordRevision($chapter, $request, 'manual');

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo chương mới thành công.',
            'data' => $chapter,
        ], 201);
    }

    /**
     * Cập nhật chương sách.
     */
    public function update(Request $request, $bookId, $chapterId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_free' => 'nullable|boolean',
            'status' => 'nullable|in:draft,published',
        ]);

        $vendor = Auth::user()->vendor;
        $book = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->findOrFail($bookId);
        $chapter = $book->chapters()->findOrFail($chapterId);

        $chapter->update([
            'title' => $request->title,
            'content' => $request->content,
            'order' => $request->order ?? $chapter->order,
            'is_free' => $request->is_free ?? $chapter->is_free,
            'status' => $request->status ?? $chapter->status,
        ]);
        $this->recordRevision($chapter, $request, 'manual');

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật chương thành công.',
            'data' => $chapter,
        ]);
    }

    /**
     * Xóa chương sách.
     */
    public function destroy($bookId, $chapterId)
    {
        $vendor = Auth::user()->vendor;
        $book = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->findOrFail($bookId);
        $chapter = $book->chapters()->findOrFail($chapterId);

        $chapter->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa chương thành công.',
        ]);
    }

    public function autosave(Request $request, $bookId, $chapterId)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255', 'content' => 'nullable|string',
            'is_free' => 'nullable|boolean', 'expected_revision' => 'required|integer|min:0',
        ]);
        $book = $this->vendorBook($bookId);
        $chapter = $book->chapters()->findOrFail($chapterId);
        if ($chapter->current_revision !== $validated['expected_revision']) {
            throw ValidationException::withMessages(['expected_revision' => 'A newer chapter revision exists.']);
        }

        DB::transaction(function () use ($chapter, $validated, $request) {
            $chapter->update([
                'title' => $validated['title'], 'content' => $validated['content'] ?? null,
                'is_free' => $validated['is_free'] ?? $chapter->is_free, 'autosaved_at' => now(),
            ]);
            $this->recordRevision($chapter, $request, 'autosave');
        });

        return response()->json(['status' => 'success', 'data' => $chapter->fresh('revisions')]);
    }

    public function restore(Request $request, $bookId, $chapterId, $revision)
    {
        $book = $this->vendorBook($bookId);
        $chapter = $book->chapters()->findOrFail($chapterId);
        $snapshot = $chapter->revisions()->where('revision', $revision)->firstOrFail();
        DB::transaction(function () use ($chapter, $snapshot, $request) {
            $chapter->update($snapshot->only(['title', 'content', 'is_free']));
            $this->recordRevision($chapter, $request, 'restore');
        });

        return response()->json(['status' => 'success', 'data' => $chapter->fresh('revisions')]);
    }

    public function reorder(Request $request, $bookId)
    {
        $validated = $request->validate(['chapter_ids' => 'required|array|min:1', 'chapter_ids.*' => 'required|integer|distinct']);
        $book = $this->vendorBook($bookId);
        $ids = collect($validated['chapter_ids']);
        if ($book->chapters()->whereIn('id', $ids)->count() !== $ids->count()) {
            abort(403);
        }
        DB::transaction(fn () => $ids->each(fn ($id, $index) => BookChapter::whereKey($id)->update(['order' => $index + 1])));

        return response()->json(['status' => 'success', 'data' => $book->chapters()->get()]);
    }

    public function import(Request $request, $bookId)
    {
        $validated = $request->validate([
            'chapters' => 'required|array|min:1|max:100', 'chapters.*.title' => 'required|string|max:255',
            'chapters.*.content' => 'nullable|string', 'chapters.*.is_free' => 'nullable|boolean',
        ]);
        $book = $this->vendorBook($bookId);
        DB::transaction(function () use ($book, $validated, $request) {
            $next = (int) $book->chapters()->max('order') + 1;
            foreach ($validated['chapters'] as $item) {
                $chapter = $book->chapters()->create([...$item, 'order' => $next++, 'status' => 'draft']);
                $this->recordRevision($chapter, $request, 'import');
            }
        });

        return response()->json(['status' => 'success', 'data' => $book->chapters()->get()], 201);
    }

    public function preview(Request $request, Book $book, BookChapter $chapter, EbookAccessService $access)
    {
        abort_unless($chapter->book_id === $book->id && $book->isPublished(), 404);
        if (! $chapter->is_free) {
            abort_unless($request->user() && ($request->user()->role === 'admin' || $access->checkAccess($request->user(), $book->id)), 403);
        }

        return response()->json(['status' => 'success', 'data' => $chapter->only(['id', 'title', 'content', 'order', 'is_free'])]);
    }

    private function vendorBook($bookId): Book
    {
        return Book::withoutGlobalScopes()->where('vendor_id', Auth::user()->vendor->id)->findOrFail($bookId);
    }

    private function recordRevision(BookChapter $chapter, Request $request, string $source): BookChapterRevision
    {
        $revision = $chapter->current_revision + 1;
        $snapshot = BookChapterRevision::create([
            'book_chapter_id' => $chapter->id, 'actor_id' => $request->user()->id, 'revision' => $revision,
            'title' => $chapter->title, 'content' => $chapter->content, 'is_free' => $chapter->is_free, 'source' => $source,
        ]);
        $chapter->update(['current_revision' => $revision]);

        return $snapshot;
    }
}

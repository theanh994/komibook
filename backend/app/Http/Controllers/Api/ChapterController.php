<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookChapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'data' => $chapters
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

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo chương mới thành công.',
            'data' => $chapter
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

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật chương thành công.',
            'data' => $chapter
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
            'message' => 'Đã xóa chương thành công.'
        ]);
    }
}

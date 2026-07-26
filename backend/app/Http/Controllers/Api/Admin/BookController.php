<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * Lấy danh sách toàn bộ sách trong hệ thống (Dành cho Admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::with(['vendor', 'categories', 'category'])
            ->withCount(['reviews', 'wishlists']);

        // Tìm kiếm theo từ khóa (Tên sách, tác giả, mã ISBN hoặc tên gian hàng)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%")
                    ->orWhereHas('vendor', function ($vq) use ($search) {
                        $vq->where('shop_name', 'like', "%{$search}%");
                    });
            });
        }

        // Lọc theo danh mục
        if ($request->filled('category_id')) {
            $catId = $request->input('category_id');
            $query->where(function ($q) use ($catId) {
                $q->where('category_id', $catId)
                    ->orWhereHas('categories', function ($cq) use ($catId) {
                        $cq->where('categories.id', $catId);
                    });
            });
        }

        // Lọc theo gian hàng (vendor_id)
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->input('vendor_id'));
        }

        // Lọc theo loại sách (physical/ebook)
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Lọc theo trạng thái (published/draft)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('publishing_status')) {
            $query->where('publishing_status', $request->input('publishing_status'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $books = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $books->items(),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    /**
     * Xem chi tiết sách
     */
    public function show(Book $book): JsonResponse
    {
        $book->load(['vendor', 'categories', 'category', 'series', 'chapters', 'drmSetting']);
        $book->loadCount(['reviews', 'wishlists']);

        return response()->json([
            'status' => 'success',
            'data' => $book,
        ]);
    }

    /**
     * Cập nhật trạng thái sách (Xuất bản / Ẩn / Nháp)
     */
    public function updateStatus(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:draft,hidden',
        ]);

        $book->update(['status' => $validated['status']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật trạng thái sách thành công.',
            'data' => $book,
        ]);
    }

    /**
     * Xóa sách (Quản trị viên)
     */
    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa cuốn sách khỏi hệ thống.',
        ]);
    }
}

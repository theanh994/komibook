<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * Lấy danh sách catalog công cộng (sách published của tất cả vendor).
     */
    public function index(Request $request)
    {
        // 1. Tắt Global Scope MultiVendor (Bản thân Book tự động bị thu gọn list với user đăng nhập là vendor. Nên ta phải huỷ global scope)
        $query = Book::withoutGlobalScopes()
            ->where('status', 'published')
            ->with(['vendor', 'category']); // Eager loading
            
        // 2. Lọc theo category_id nếu có params
        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where('category_id', $request->category_id);
        }

        // 2b. Tìm kiếm theo từ khoá (title hoặc author)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('author', 'LIKE', "%{$search}%");
            });
        }

        // 3. Hỗ trợ sắp xếp (ví dụ: mới nhất)
        $query->orderBy('created_at', 'desc');

        // 4. Phân trang
        $books = $query->paginate(12);

        return BookResource::collection($books)->additional([
            'status'  => 'success',
            'message' => 'Lấy danh sách sách thành công.',
        ]);
    }

    /**
     * Lấy chi tiết một cuốn sách thông qua slug.
     */
    public function show($slug)
    {
        $book = Book::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['vendor', 'category'])
            ->firstOrFail();

        return response()->json([
            'status'  => 'success',
            'message' => 'Lấy chi tiết sách thành công.',
            'data'    => new BookResource($book),
        ]);
    }
}

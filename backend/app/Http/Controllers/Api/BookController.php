<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * Lấy danh sách 8 sách bán chạy nhất
     */
    public function topSelling()
    {
        $books = Book::withoutGlobalScopes()
            ->where('books.status', 'published')
            ->join('order_items', 'books.id', '=', 'order_items.book_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->select('books.*', \Illuminate\Support\Facades\DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('books.id')
            ->orderBy('total_sold', 'desc')
            ->limit(8)
            ->with(['vendor', 'category'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $books,
        ]);
    }

    /**
     * Lấy danh sách catalog công cộng (sách published của tất cả vendor).
     */
    public function index(Request $request)
    {
        // 1. Tắt Global Scope MultiVendor (Bản thân Book tự động bị thu gọn list với user đăng nhập là vendor. Nên ta phải huỷ global scope)
        $query = Book::withoutGlobalScopes()
            ->where('status', 'published')
            ->with(['vendor', 'category', 'series']); // Eager loading
            
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

        // 2c. Lọc theo khoảng giá
        if ($request->filled('min_price')) {
            $query->where(function($q) use ($request) {
                $q->where('sale_price', '>=', $request->min_price)
                  ->orWhere(function($subq) use ($request) {
                      $subq->whereNull('sale_price')->where('price', '>=', $request->min_price);
                  });
            });
        }
        if ($request->filled('max_price')) {
            $query->where(function($q) use ($request) {
                $q->where('sale_price', '<=', $request->max_price)
                  ->orWhere(function($subq) use ($request) {
                      $subq->whereNull('sale_price')->where('price', '<=', $request->max_price);
                  });
            });
        }

        // 2d. Lọc theo Loại sách
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
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
            ->with(['vendor', 'category', 'series', 'reviews.user']) // Tải reviews kèm user + series
            ->firstOrFail();

        return response()->json([
            'status'  => 'success',
            'message' => 'Lấy chi tiết sách thành công.',
            'data'    => new BookResource($book),
        ]);
    }

    /**
     * Lấy danh sách sách cùng Series.
     */
    public function seriesBooks($bookId)
    {
        $book = Book::withoutGlobalScopes()->findOrFail($bookId);

        if (!$book->series_id) {
            return response()->json([
                'status' => 'success',
                'data'   => [],
            ]);
        }

        $seriesBooks = Book::withoutGlobalScopes()
            ->where('series_id', $book->series_id)
            ->where('id', '!=', $book->id)
            ->where('status', 'published')
            ->with(['vendor', 'category'])
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => BookResource::collection($seriesBooks),
        ]);
    }

    /**
     * Kiểm tra user đã sở hữu E-book này chưa.
     * Trả về { owned: true/false, order_id: ..., book_id: ... }
     */
    public function checkOwnership(Request $request, $bookId)
    {
        $userId = $request->user()->id;

        $orderItem = OrderItem::where('book_id', $bookId)
            ->whereHas('order', function ($q) use ($userId) {
                $q->withoutGlobalScopes()
                  ->where('user_id', $userId)
                  ->whereIn('status', ['pending', 'processing', 'shipped', 'completed']);
            })
            ->first();

        if ($orderItem) {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'owned'    => true,
                    'order_id' => $orderItem->order_id,
                    'book_id'  => (int) $bookId,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'owned' => false,
            ],
        ]);
    }

    /**
     * Thêm đánh giá cho sách
     */
    public function addReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $book = Book::withoutGlobalScopes()->findOrFail($id);

        $review = $book->reviews()->create([
            'user_id' => $request->user()->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cảm ơn bạn đã đánh giá!',
            'data' => clone $review->load('user')
        ]);
    }
}

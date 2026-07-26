<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\Series;
use App\Services\EbookAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->select('books.*', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('books.id')
            ->orderBy('total_sold', 'desc')
            ->limit(8)
            ->with(['vendor', 'category'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => BookResource::collection($books),
        ]);
    }

    /**
     * Lấy danh sách catalog công cộng (sách published của tất cả vendor).
     */
    public function index(Request $request)
    {
        // 1. Tắt Global Scope MultiVendor (Bản thân Book tự động bị thu gọn list với user đăng nhập là vendor. Nên ta phải huỷ global scope)
        $query = Book::withoutGlobalScopes()
            ->select('books.*')
            ->where('books.status', 'published')
            ->with(['vendor', 'category', 'categories', 'series']); // Eager loading

        // 2. Lọc theo category_id nếu có params
        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where(function ($q) use ($request) {
                $q->where('books.category_id', $request->category_id)
                    ->orWhereHas('categories', function ($subq) use ($request) {
                        $subq->where('categories.id', $request->category_id);
                    });
            });
        }

        // 2b. Tìm kiếm theo từ khoá (title hoặc author)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('books.title', 'LIKE', "%{$search}%")
                    ->orWhere('books.author', 'LIKE', "%{$search}%");
            });
        }

        // 2c. Lọc theo khoảng giá
        if ($request->filled('min_price')) {
            $query->where(function ($q) use ($request) {
                $q->where('books.sale_price', '>=', $request->min_price)
                    ->orWhere(function ($subq) use ($request) {
                        $subq->whereNull('books.sale_price')->where('books.price', '>=', $request->min_price);
                    });
            });
        }
        if ($request->filled('max_price')) {
            $query->where(function ($q) use ($request) {
                $q->where('books.sale_price', '<=', $request->max_price)
                    ->orWhere(function ($subq) use ($request) {
                        $subq->whereNull('books.sale_price')->where('books.price', '<=', $request->max_price);
                    });
            });
        }

        // 2d. Lọc theo Loại sách
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('books.type', $request->type);
        }

        // 3. Hỗ trợ sắp xếp
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'price_asc':
                $query->orderByRaw('COALESCE(books.sale_price, books.price) ASC');
                break;
            case 'price_desc':
                $query->orderByRaw('COALESCE(books.sale_price, books.price) DESC');
                break;
            case 'popular':
                $query->leftJoin('order_items', 'books.id', '=', 'order_items.book_id')
                    ->leftJoin('orders', 'order_items.order_id', '=', 'orders.id')
                    ->select('books.*', DB::raw('COALESCE(SUM(CASE WHEN orders.status = "completed" THEN order_items.quantity ELSE 0 END), 0) as total_sold'))
                    ->groupBy('books.id')
                    ->orderBy('total_sold', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('books.created_at', 'desc');
                break;
        }

        if ($request->boolean('all')) {
            $books = $query->get();

            return BookResource::collection($books)->additional([
                'status' => 'success',
                'message' => 'Lấy danh sách sách thành công.',
            ]);
        }

        // 4. Phân trang
        $books = $query->paginate($request->integer('per_page', 12));

        return BookResource::collection($books)->additional([
            'status' => 'success',
            'message' => 'Lấy danh sách sách thành công.',
        ]);
    }

    /**
     * Lấy chi tiết một cuốn sách thông qua slug hoặc id.
     */
    public function show($identifier)
    {
        $query = Book::withoutGlobalScopes()
            ->where('status', 'published')
            ->with(['vendor', 'category', 'categories', 'series', 'reviews.user', 'chapters'])
            ->withCount('wishlists');

        if (is_numeric($identifier)) {
            $book = $query->where('id', $identifier)->firstOrFail();
        } else {
            $book = $query->where('slug', $identifier)->firstOrFail();
        }

        // Increment page views counter
        $book->increment('views');

        return response()->json([
            'status' => 'success',
            'message' => 'Lấy chi tiết sách thành công.',
            'data' => new BookResource($book),
        ]);
    }

    /**
     * Lấy danh sách tất cả các bộ sách (Series) hiện có.
     */
    public function allSeries()
    {
        $series = Series::orderBy('title', 'asc')->get(['id', 'title']);

        return response()->json([
            'status' => 'success',
            'data' => $series,
        ]);
    }

    /**
     * Lấy danh sách sách cùng Series / Cùng Bộ (Chỉ khi đã gán series_id).
     */
    public function seriesBooks($bookId)
    {
        $book = Book::withoutGlobalScopes()->findOrFail($bookId);

        if (! $book->series_id) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        $seriesBooks = Book::withoutGlobalScopes()
            ->where('series_id', $book->series_id)
            ->where('id', '!=', $book->id)
            ->where('status', 'published')
            ->with(['vendor', 'category', 'categories'])
            ->orderBy('id', 'asc')
            ->limit(12)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => BookResource::collection($seriesBooks),
        ]);
    }

    /**
     * Lấy danh sách sách liên quan (cùng Thể loại / Danh mục).
     */
    public function relatedBooks($bookId)
    {
        $book = Book::withoutGlobalScopes()->with('categories')->findOrFail($bookId);

        // Lấy tất cả ID danh mục cuốn sách này thuộc về
        $catIds = [];
        if ($book->category_id) {
            $catIds[] = (int) $book->category_id;
        }
        if ($book->categories && $book->categories->isNotEmpty()) {
            $catIds = array_merge($catIds, $book->categories->pluck('id')->toArray());
        }
        $catIds = array_unique(array_filter($catIds));

        $query = Book::withoutGlobalScopes()
            ->where('id', '!=', $book->id)
            ->where('status', 'published');

        if (! empty($catIds)) {
            $query->where(function ($q) use ($catIds) {
                $q->whereIn('category_id', $catIds)
                    ->orWhereHas('categories', function ($cq) use ($catIds) {
                        $cq->whereIn('categories.id', $catIds);
                    });
            });
        }

        $relatedBooks = $query->with(['vendor', 'category', 'categories'])
            ->orderBy('views', 'desc')
            ->limit(5)
            ->get();

        // Nếu số lượng sách cùng thể loại ít hơn 5, bổ sung các sách có lượt khám phá cao khác
        if ($relatedBooks->count() < 5) {
            $excludeIds = $relatedBooks->pluck('id')->push($book->id)->toArray();
            $additionalBooks = Book::withoutGlobalScopes()
                ->whereNotIn('id', $excludeIds)
                ->where('status', 'published')
                ->with(['vendor', 'category', 'categories'])
                ->orderBy('views', 'desc')
                ->limit(5 - $relatedBooks->count())
                ->get();
            $relatedBooks = $relatedBooks->concat($additionalBooks);
        }

        return response()->json([
            'status' => 'success',
            'data' => BookResource::collection($relatedBooks),
        ]);
    }

    /**
     * Lấy danh sách sách cùng Tác giả.
     */
    public function authorBooks($bookId)
    {
        $book = Book::withoutGlobalScopes()->findOrFail($bookId);

        $authorBooks = Book::withoutGlobalScopes()
            ->where('author', $book->author)
            ->where('id', '!=', $book->id)
            ->where('status', 'published')
            ->with(['vendor', 'category', 'categories'])
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => BookResource::collection($authorBooks),
        ]);
    }

    /**
     * Kiểm tra user đã sở hữu E-book này chưa.
     * Trả về { owned: true/false, order_id: ..., book_id: ... }
     */
    public function checkOwnership(Request $request, $bookId)
    {
        $ebookAccessService = app(EbookAccessService::class);
        $ownershipData = $ebookAccessService->getOwnershipData($request->user(), (int) $bookId);

        return response()->json([
            'status' => 'success',
            'data' => $ownershipData,
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
            'data' => clone $review->load('user'),
        ]);
    }
}

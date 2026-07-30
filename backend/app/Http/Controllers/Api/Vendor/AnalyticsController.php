<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\PublicMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * GET /api/vendor/analytics
     *
     * Cung cấp dữ liệu phân tích chi tiết cho người bán (độc giả, sách bán chạy...).
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if (! $vendor) {
            return response()->json(['message' => 'Bạn chưa được cấp quyền gian hàng'], 403);
        }

        // ── 1. Sách bán chạy nhất ──────────────────────────────────────────
        $topSellingBooks = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.status', 'completed')
            ->select('books.id', 'books.title', 'books.cover_image', DB::raw('SUM(order_items.quantity) as total_sold'), DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue'))
            ->groupBy('books.id', 'books.title', 'books.cover_image')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get()
            ->map(function ($book) {
                $book->cover_image = PublicMediaUrl::storage($book->cover_image);
                $book->total_sold = (int) $book->total_sold;
                $book->total_revenue = (int) $book->total_revenue;

                return $book;
            });

        // ── 2. Top độc giả (khách hàng chi tiêu nhiều nhất) ───────────────
        $topReaders = Order::where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', DB::raw('SUM(orders.total_amount) as total_spent'), DB::raw('COUNT(orders.id) as total_orders'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        // ── 3. Thống kê theo danh mục (Category) ───────────────────────────
        $salesByCategory = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->join('categories', 'books.category_id', '=', 'categories.id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.status', 'completed')
            ->select('categories.id', 'categories.name', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_sold')
            ->get();

        // ── 4. Tỉ lệ đánh giá (Reviews) ────────────────────────────────────
        $bookIds = Book::where('vendor_id', $vendor->id)->pluck('id');
        $reviewsStats = DB::table('reviews')
            ->whereIn('book_id', $bookIds)
            ->select('rating', DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'top_selling_books' => $topSellingBooks,
                'top_readers' => $topReaders,
                'sales_by_category' => $salesByCategory,
                'reviews_stats' => $reviewsStats,
            ],
        ]);
    }
}

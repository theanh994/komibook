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
     * Cung cấp dữ liệu phân tích chi tiết cho người bán (độc giả, hành vi mua, sách bán chạy...).
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if (! $vendor) {
            return response()->json(['message' => 'Bạn chưa được cấp quyền gian hàng'], 403);
        }

        // ── 1. Thống kê tổng quan độc giả & doanh số ──────────────────────────
        $completedOrdersQuery = Order::where('vendor_id', $vendor->id)->where('status', 'completed');
        $totalRevenue = (int) $completedOrdersQuery->clone()->sum('total_amount');

        $readerStats = Order::where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->select('user_id', DB::raw('COUNT(id) as order_count'), DB::raw('SUM(total_amount) as user_spent'))
            ->groupBy('user_id')
            ->get();

        $totalReaders = $readerStats->count();
        $repeatReaders = $readerStats->where('order_count', '>', 1)->count();
        $retentionRate = $totalReaders > 0 ? round(($repeatReaders / $totalReaders) * 100, 1) : 0;
        $avgSpentPerReader = $totalReaders > 0 ? (int) round($totalRevenue / $totalReaders) : 0;

        $totalBooksSold = (int) OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.status', 'completed')
            ->sum('order_items.quantity');

        // ── 2. Phân bổ định dạng sách (Physical vs Ebook) ─────────────────
        $formatDistribution = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.status', 'completed')
            ->select('books.type', DB::raw('SUM(order_items.quantity) as total_sold'), DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue'))
            ->groupBy('books.type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type === 'ebook' ? 'ebook' : 'physical',
                    'label' => $item->type === 'ebook' ? 'Sách điện tử (Ebook)' : 'Sách in (Physical)',
                    'total_sold' => (int) $item->total_sold,
                    'total_revenue' => (int) $item->total_revenue,
                ];
            });

        // ── 3. Sách bán chạy nhất ──────────────────────────────────────────
        $topSellingBooks = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.status', 'completed')
            ->select(
                'books.id',
                'books.title',
                'books.author',
                'books.type',
                'books.cover_image',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue')
            )
            ->groupBy('books.id', 'books.title', 'books.author', 'books.type', 'books.cover_image')
            ->orderByDesc('total_sold')
            ->limit(6)
            ->get()
            ->map(function ($book) {
                $book->cover_image = PublicMediaUrl::storage($book->cover_image);
                $book->total_sold = (int) $book->total_sold;
                $book->total_revenue = (int) $book->total_revenue;

                return $book;
            });

        // ── 4. Top độc giả (khách hàng chi tiêu nhiều nhất) ───────────────
        $topReaders = Order::where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                DB::raw('SUM(orders.total_amount) as total_spent'),
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('MAX(orders.created_at) as last_order_at')
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_spent')
            ->limit(8)
            ->get()
            ->map(function ($reader) {
                $spent = (int) $reader->total_spent;
                $orders = (int) $reader->total_orders;
                $reader->total_spent = $spent;
                $reader->total_orders = $orders;

                // Phân loại phân khúc độc giả
                if ($spent >= 500000 || $orders >= 5) {
                    $reader->segment = 'vip';
                    $reader->segment_label = 'Độc giả VIP';
                } elseif ($orders > 1) {
                    $reader->segment = 'loyal';
                    $reader->segment_label = 'Thân thiết';
                } else {
                    $reader->segment = 'new';
                    $reader->segment_label = 'Độc giả Mới';
                }

                return $reader;
            });

        // ── 5. Thống kê theo danh mục (Category) ───────────────────────────
        $salesByCategory = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->join('categories', 'books.category_id', '=', 'categories.id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.status', 'completed')
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_sold')
            ->get()
            ->map(function ($cat) {
                $cat->total_sold = (int) $cat->total_sold;
                $cat->total_revenue = (int) $cat->total_revenue;

                return $cat;
            });

        // ── 6. Tỉ lệ đánh giá (Reviews) & Chi tiết theo số sao ──────────────
        $bookIds = Book::where('vendor_id', $vendor->id)->pluck('id');
        $reviewsStats = DB::table('reviews')
            ->whereIn('book_id', $bookIds)
            ->select('rating', DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->get();

        $totalReviewsCount = $reviewsStats->sum('count');
        $sumRatingPoints = $reviewsStats->reduce(fn ($acc, $curr) => $acc + ($curr->rating * $curr->count), 0);
        $avgRatingScore = $totalReviewsCount > 0 ? round($sumRatingPoints / $totalReviewsCount, 1) : 0;

        $starBreakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $item = $reviewsStats->firstWhere('rating', $star);
            $count = $item ? (int) $item->count : 0;
            $percentage = $totalReviewsCount > 0 ? round(($count / $totalReviewsCount) * 100, 1) : 0;
            $starBreakdown[] = [
                'star' => $star,
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        // ── 7. Xu hướng 6 tháng gần nhất (Monthly Sales Trend - Database Agnostic) ─────────────
        $ordersForTrend = Order::where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->get();

        $monthlyTrend = $ordersForTrend
            ->groupBy(fn ($order) => $order->created_at ? $order->created_at->format('Y-m') : 'unknown')
            ->map(function ($group, $monthKey) {
                $firstOrder = $group->first();
                $label = $firstOrder && $firstOrder->created_at ? 'Tháng '.$firstOrder->created_at->format('m/Y') : $monthKey;

                return [
                    'month_key' => $monthKey,
                    'label' => $label,
                    'revenue' => (int) $group->sum('total_amount'),
                    'active_readers' => (int) $group->pluck('user_id')->unique()->count(),
                    'orders_count' => (int) $group->count(),
                ];
            })
            ->sortKeys()
            ->take(6)
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'total_readers' => $totalReaders,
                    'repeat_readers' => $repeatReaders,
                    'retention_rate' => $retentionRate,
                    'total_revenue' => $totalRevenue,
                    'total_books_sold' => $totalBooksSold,
                    'avg_spent_per_reader' => $avgSpentPerReader,
                    'avg_rating' => $avgRatingScore,
                    'total_reviews' => $totalReviewsCount,
                ],
                'format_distribution' => $formatDistribution,
                'star_breakdown' => $starBreakdown,
                'top_selling_books' => $topSellingBooks,
                'top_readers' => $topReaders,
                'sales_by_category' => $salesByCategory,
                'reviews_stats' => $reviewsStats,
                'monthly_trend' => $monthlyTrend,
            ],
        ]);
    }
}

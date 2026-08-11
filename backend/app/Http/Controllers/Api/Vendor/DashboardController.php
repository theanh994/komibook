<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\VendorEarningLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403, 'Bạn chưa được cấp quyền gian hàng.');

        $orders = Order::query()->where('vendor_id', $vendor->id);
        $books = Book::query()->where('vendor_id', $vendor->id);

        $statusBreakdown = (clone $orders)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);

        $totalCommission = (int) VendorEarningLedger::where('vendor_id', $vendor->id)->sum('commission_amount');
        $totalNetRevenue = (int) VendorEarningLedger::where('vendor_id', $vendor->id)->sum('net_amount');
        $grossMerchandise = (int) VendorEarningLedger::where('vendor_id', $vendor->id)->sum('gross_amount');
        $grossRevenue = (int) (clone $orders)->where('status', 'completed')->sum('total_amount');

        if ($totalNetRevenue === 0 && $grossMerchandise > 0) {
            $totalCommission = (int) round($grossMerchandise * 0.10);
            $totalNetRevenue = $grossMerchandise - $totalCommission;
        }

        // Recent Orders
        $recentOrders = (clone $orders)
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'order_code', 'user_id', 'total_amount', 'status', 'created_at'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'customer_name' => $order->user?->name ?? 'Khách hàng',
                'total_amount' => (int) $order->total_amount,
                'status' => $order->status,
                'created_at' => $order->created_at?->toISOString(),
            ]);

        $lowStockThreshold = max(1, (int) $request->query('low_stock_threshold', 10));

        // Low stock books (stock <= low_stock_threshold)
        $lowStockBooksList = (clone $books)
            ->where('status', 'published')
            ->where('stock', '<=', $lowStockThreshold)
            ->orderBy('stock', 'asc')
            ->limit(5)
            ->get(['id', 'title', 'isbn', 'stock', 'price'])
            ->map(fn (Book $book) => [
                'id' => $book->id,
                'title' => $book->title,
                'isbn' => $book->isbn ?? 'Chưa khai báo',
                'stock_quantity' => (int) $book->stock,
                'price' => (int) $book->price,
            ]);

        // Top Selling Books for this vendor
        $topBooks = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.status', 'completed')
            ->selectRaw('books.id, books.title, books.cover_image, SUM(order_items.quantity) as total_quantity, SUM(order_items.price * order_items.quantity) as total_revenue')
            ->groupBy('books.id', 'books.title', 'books.cover_image')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'cover_image' => $item->cover_image,
                'quantity' => (int) $item->total_quantity,
                'revenue' => (int) $item->total_revenue,
            ]);

        if ($topBooks->isEmpty()) {
            $topBooks = (clone $books)
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'title', 'cover_image', 'price', 'stock'])
                ->map(fn (Book $book) => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'cover_image' => $book->cover_image,
                    'quantity' => 0,
                    'revenue' => (int) $book->price,
                ]);
        }

        // Sales Trend based on period filter ('day', 'month', '6_months', 'year')
        $period = $request->query('period', '6_months');
        $trendLabels = [];
        $trendRevenue = [];
        $trendOrders = [];

        if ($period === 'day') {
            // Standard Current Week: Monday (Thứ 2) to Sunday (Chủ nhật)
            $startOfWeek = now()->startOfWeek(Carbon::MONDAY);
            $dayNames = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
            for ($i = 0; $i < 7; $i++) {
                $day = $startOfWeek->copy()->addDays($i);
                $endDay = $day->copy()->endOfDay();

                $dayOrders = (clone $orders)->whereBetween('created_at', [$day, $endDay]);
                $completedDay = (clone $dayOrders)->where('status', 'completed');

                $trendLabels[] = $dayNames[$i] . ' (' . $day->format('d/m') . ')';
                $trendRevenue[] = (int) $completedDay->sum('total_amount');
                $trendOrders[] = (int) $dayOrders->count();
            }
        } elseif ($period === 'month') {
            // Standard Current Month: Day 1 to Last Day of current month
            $startOfMonth = now()->startOfMonth();
            $daysInMonth = $startOfMonth->daysInMonth;
            for ($i = 0; $i < $daysInMonth; $i++) {
                $day = $startOfMonth->copy()->addDays($i);
                $endDay = $day->copy()->endOfDay();

                $dayOrders = (clone $orders)->whereBetween('created_at', [$day, $endDay]);
                $completedDay = (clone $dayOrders)->where('status', 'completed');

                $trendLabels[] = $day->format('d/m');
                $trendRevenue[] = (int) $completedDay->sum('total_amount');
                $trendOrders[] = (int) $dayOrders->count();
            }
        } elseif ($period === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            // Custom Date Range
            try {
                $start = Carbon::parse($request->query('start_date'))->startOfDay();
                $end = Carbon::parse($request->query('end_date'))->endOfDay();

                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                $diffInDays = $start->diffInDays($end) + 1;

                if ($diffInDays <= 60) {
                    for ($i = 0; $i < $diffInDays; $i++) {
                        $day = $start->copy()->addDays($i);
                        $endDay = $day->copy()->endOfDay();

                        $dayOrders = (clone $orders)->whereBetween('created_at', [$day, $endDay]);
                        $completedDay = (clone $dayOrders)->where('status', 'completed');

                        $trendLabels[] = $day->format('d/m');
                        $trendRevenue[] = (int) $completedDay->sum('total_amount');
                        $trendOrders[] = (int) $dayOrders->count();
                    }
                } else {
                    $cursor = $start->copy()->startOfMonth();
                    while ($cursor->lte($end)) {
                        $monthEnd = $cursor->copy()->endOfMonth();
                        $effectiveEnd = $monthEnd->gt($end) ? $end : $monthEnd;

                        $monthOrders = (clone $orders)->whereBetween('created_at', [$cursor, $effectiveEnd]);
                        $completedMonth = (clone $monthOrders)->where('status', 'completed');

                        $trendLabels[] = $cursor->format('m/Y');
                        $trendRevenue[] = (int) $completedMonth->sum('total_amount');
                        $trendOrders[] = (int) $monthOrders->count();

                        $cursor->addMonthNoOverflow()->startOfMonth();
                    }
                }
            } catch (\Exception $e) {
                // Fallback to 6_months on invalid date
                $period = '6_months';
                $start = now()->subMonthsNoOverflow(5)->startOfMonth();
                for ($i = 0; $i < 6; $i++) {
                    $month = $start->copy()->addMonthsNoOverflow($i);
                    $endMonth = $month->copy()->endOfMonth();

                    $monthOrders = (clone $orders)->whereBetween('created_at', [$month, $endMonth]);
                    $completedMonth = (clone $monthOrders)->where('status', 'completed');

                    $trendLabels[] = $month->format('m/Y');
                    $trendRevenue[] = (int) $completedMonth->sum('total_amount');
                    $trendOrders[] = (int) $monthOrders->count();
                }
            }
        } elseif ($period === 'year') {
            // Standard Current Year: Month 1 (Tháng 1) to Month 12 (Tháng 12) of current year
            $startOfYear = now()->startOfYear();
            for ($i = 0; $i < 12; $i++) {
                $month = $startOfYear->copy()->addMonthsNoOverflow($i);
                $endMonth = $month->copy()->endOfMonth();

                $monthOrders = (clone $orders)->whereBetween('created_at', [$month, $endMonth]);
                $completedMonth = (clone $monthOrders)->where('status', 'completed');

                $trendLabels[] = 'Tháng ' . ($i + 1);
                $trendRevenue[] = (int) $completedMonth->sum('total_amount');
                $trendOrders[] = (int) $monthOrders->count();
            }
        } else {
            // Default: 6_months (rolling 6 months)
            $period = '6_months';
            $start = now()->subMonthsNoOverflow(5)->startOfMonth();
            for ($i = 0; $i < 6; $i++) {
                $month = $start->copy()->addMonthsNoOverflow($i);
                $endMonth = $month->copy()->endOfMonth();

                $monthOrders = (clone $orders)->whereBetween('created_at', [$month, $endMonth]);
                $completedMonth = (clone $monthOrders)->where('status', 'completed');

                $trendLabels[] = $month->format('m/Y');
                $trendRevenue[] = (int) $completedMonth->sum('total_amount');
                $trendOrders[] = (int) $monthOrders->count();
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'shop_name' => $vendor->shop_name,
                'total_revenue' => $grossRevenue,
                'total_net_revenue' => $totalNetRevenue,
                'total_commission' => $totalCommission,
                'pending_orders' => (clone $orders)->whereIn('status', ['pending', 'processing'])->count(),
                'total_orders' => (clone $orders)->count(),
                'completed_orders' => (clone $orders)->where('status', 'completed')->count(),
                'total_books' => (clone $books)->where('status', 'published')->count(),
                'draft_books' => (clone $books)->where('status', 'draft')->count(),
                'low_stock_books_count' => (clone $books)->where('status', 'published')->where('stock', '<=', $lowStockThreshold)->count(),
                'low_stock_threshold' => $lowStockThreshold,
                'order_status_breakdown' => $statusBreakdown,
                'recent_orders' => $recentOrders,
                'low_stock_books' => $lowStockBooksList,
                'top_books' => $topBooks,
                'sales_trend' => [
                    'period' => $period,
                    'labels' => $trendLabels,
                    'revenue' => $trendRevenue,
                    'orders' => $trendOrders,
                ],
            ],
        ]);
    }
}

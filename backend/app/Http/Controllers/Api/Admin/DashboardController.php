<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\UsedBookSellerProfile;
use App\Models\User;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/stats
     *
     * Trả về các con số thống kê tổng quan cho Admin tối cao.
     * Bypass Global Scope vì Admin cần nhìn thấy toàn bộ dữ liệu.
     */
    public function stats(Request $request): JsonResponse
    {
        // Kiểm tra role admin — nếu không phải admin thì 403
        abort_if($request->user()->role !== 'admin', 403, 'Bạn không có quyền truy cập.');

        $totalUsers = User::count();
        $totalVendors = Vendor::withoutGlobalScopes()->count();
        $totalUsedBookSellers = UsedBookSellerProfile::count();
        $totalBooks = Book::withoutGlobalScopes()->count();

        // Tổng doanh thu từ đơn hàng hoàn thành
        $totalRevenue = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->sum('total_amount');

        // Thống kê thêm cho dashboard đẹp hơn
        $pendingOrders = Order::withoutGlobalScopes()
            ->where('status', 'pending')
            ->count();

        $totalOrders = Order::withoutGlobalScopes()->count();

        $months = collect(range(5, 0))->map(
            fn (int $monthsAgo) => CarbonImmutable::now()->startOfMonth()->subMonths($monthsAgo)
        );
        $trendStart = $months->first();

        $ordersForTrend = Order::withoutGlobalScopes()
            ->where('created_at', '>=', $trendStart)
            ->get(['status', 'total_amount', 'created_at']);
        $usersForTrend = User::where('created_at', '>=', $trendStart)->get(['created_at']);
        $usedBookSellersForTrend = UsedBookSellerProfile::where('created_at', '>=', $trendStart)->get(['created_at']);
        $vendorsForTrend = Vendor::withoutGlobalScopes()
            ->where('created_at', '>=', $trendStart)
            ->get(['created_at']);

        $monthLabels = $months->map(fn (CarbonImmutable $month) => $month->format('m/Y'))->values();
        $commerceTrend = [
            'labels' => $monthLabels,
            'orders' => $months->map(fn (CarbonImmutable $month) => $ordersForTrend
                ->where('status', 'completed')
                ->filter(fn (Order $order) => $order->created_at->format('Y-m') === $month->format('Y-m'))
                ->count())->values(),
            'revenue' => $months->map(fn (CarbonImmutable $month) => (int) $ordersForTrend
                ->where('status', 'completed')
                ->filter(fn (Order $order) => $order->created_at->format('Y-m') === $month->format('Y-m'))
                ->sum('total_amount'))->values(),
        ];

        $accountGrowth = [
            'labels' => $monthLabels,
            'users' => $this->monthlyCounts($usersForTrend, $months),
            'used_book_sellers' => $this->monthlyCounts($usedBookSellersForTrend, $months),
            'vendors' => $this->monthlyCounts($vendorsForTrend, $months),
        ];

        $bookDistribution = [
            'by_type' => [
                'physical' => Book::withoutGlobalScopes()->where('type', 'physical')->count(),
                'ebook' => Book::withoutGlobalScopes()->where('type', 'ebook')->count(),
            ],
            'by_status' => Book::withoutGlobalScopes()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ];

        $orderStatusDistribution = Order::withoutGlobalScopes()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_users' => $totalUsers,
                'total_vendors' => $totalVendors,
                'total_used_book_sellers' => $totalUsedBookSellers,
                'total_books' => $totalBooks,
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'commerce_trend' => $commerceTrend,
                'account_growth' => $accountGrowth,
                'book_distribution' => $bookDistribution,
                'order_status_distribution' => $orderStatusDistribution,
                'operational_queues' => [
                    'pending_orders' => $pendingOrders,
                    'pending_vendors' => Vendor::withoutGlobalScopes()->where('status', 'inactive')->count(),
                    'draft_books' => Book::withoutGlobalScopes()->where('status', 'draft')->count(),
                ],
            ],
        ]);
    }

    /**
     * @param  Collection<int, object>  $records
     * @param  Collection<int, CarbonImmutable>  $months
     * @return Collection<int, int>
     */
    private function monthlyCounts($records, $months)
    {
        return $months->map(fn (CarbonImmutable $month) => $records
            ->filter(fn ($record) => $record->created_at->format('Y-m') === $month->format('Y-m'))
            ->count())->values();
    }
}

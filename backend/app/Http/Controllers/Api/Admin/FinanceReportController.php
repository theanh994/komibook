<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Book;
use App\Models\PayoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceReportController extends Controller
{
    /**
     * GET /api/admin/finance-report
     *
     * Trả về dữ liệu báo cáo tài chính tổng hợp cho Admin.
     */
    public function index(Request $request): JsonResponse
    {
        // ── KPI Cards ──────────────────────────────────────────────────────
        $totalRevenue = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->sum('total_amount');

        $monthlyRevenue = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        $totalOrders = Order::withoutGlobalScopes()->count();
        $completedOrders = Order::withoutGlobalScopes()->where('status', 'completed')->count();

        $totalCustomers = User::where('role', 'customer')->count();

        $avgOrderValue = $completedOrders > 0
            ? round($totalRevenue / $completedOrders)
            : 0;

        // ── Doanh thu theo tháng (12 tháng gần nhất) ────────────────────
        $revenueByMonth = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // ── Doanh thu theo phương thức thanh toán ────────────────────────
        $revenueByPaymentMethod = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->select('payment_method', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        // ── Top vendors theo doanh thu ───────────────────────────────────
        $topVendors = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->join('vendors', 'orders.vendor_id', '=', 'vendors.id')
            ->select('vendors.id', 'vendors.shop_name', DB::raw('SUM(orders.total_amount) as revenue'), DB::raw('COUNT(orders.id) as total_orders'))
            ->groupBy('vendors.id', 'vendors.shop_name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // ── Thống kê yêu cầu rút tiền ───────────────────────────────────
        $payoutStats = [
            'pending'  => PayoutRequest::where('status', 'pending')->sum('amount'),
            'approved' => PayoutRequest::where('status', 'approved')->sum('amount'),
            'rejected' => PayoutRequest::where('status', 'rejected')->sum('amount'),
        ];

        return response()->json([
            'status' => 'success',
            'data'   => [
                'kpi' => [
                    'total_revenue'    => (int) $totalRevenue,
                    'monthly_revenue'  => (int) $monthlyRevenue,
                    'total_orders'     => $totalOrders,
                    'completed_orders' => $completedOrders,
                    'total_customers'  => $totalCustomers,
                    'avg_order_value'  => $avgOrderValue,
                ],
                'revenue_by_month'          => $revenueByMonth,
                'revenue_by_payment_method' => $revenueByPaymentMethod,
                'top_vendors'               => $topVendors,
                'payout_stats'              => $payoutStats,
            ],
        ]);
    }
}

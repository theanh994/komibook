<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
        $periodStart = now()->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $monthlyRows = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->where('created_at', '>=', $periodStart)
            ->selectRaw($this->monthBucketExpression().' as month, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $revenueByMonth = $this->completeMonthlySeries($monthlyRows, $periodStart);

        // ── Doanh thu theo phương thức thanh toán ────────────────────────
        $revenueByPaymentMethod = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->select('payment_method', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        // ── Top vendors theo doanh thu ───────────────────────────────────
        $topVendors = Order::withoutGlobalScopes()
            ->where('orders.status', 'completed')
            ->join('vendors', 'orders.vendor_id', '=', 'vendors.id')
            ->select('vendors.id', 'vendors.shop_name', DB::raw('SUM(orders.total_amount) as revenue'), DB::raw('COUNT(orders.id) as total_orders'))
            ->groupBy('vendors.id', 'vendors.shop_name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // ── Thống kê yêu cầu rút tiền ───────────────────────────────────
        $payoutStats = [
            'pending' => PayoutRequest::where('status', 'pending')->sum('amount'),
            'approved' => PayoutRequest::whereIn('status', ['approved', 'processing', 'completed'])->sum('amount'),
            'rejected' => PayoutRequest::where('status', 'rejected')->sum('amount'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'kpi' => [
                    'total_revenue' => (int) $totalRevenue,
                    'monthly_revenue' => (int) $monthlyRevenue,
                    'total_orders' => $totalOrders,
                    'completed_orders' => $completedOrders,
                    'total_customers' => $totalCustomers,
                    'avg_order_value' => $avgOrderValue,
                ],
                'revenue_by_month' => $revenueByMonth,
                'revenue_by_payment_method' => $revenueByPaymentMethod,
                'top_vendors' => $topVendors,
                'payout_stats' => $payoutStats,
            ],
        ]);
    }

    private function monthBucketExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'mysql' => "DATE_FORMAT(created_at, '%Y-%m')",
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            default => throw new RuntimeException('Unsupported database driver for finance reporting.'),
        };
    }

    /**
     * @param  Collection<string, object>  $monthlyRows
     * @return Collection<int, array{month: string, revenue: int, orders: int}>
     */
    private function completeMonthlySeries(Collection $monthlyRows, mixed $periodStart): Collection
    {
        return collect(range(0, 11))->map(function (int $offset) use ($monthlyRows, $periodStart): array {
            $month = $periodStart->copy()->addMonthsNoOverflow($offset)->format('Y-m');
            $row = $monthlyRows->get($month);

            return [
                'month' => $month,
                'revenue' => (int) ($row->revenue ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
        });
    }
}

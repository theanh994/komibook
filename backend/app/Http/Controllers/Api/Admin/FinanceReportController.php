<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\RevenueReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    public function index(Request $request, RevenueReportService $reports): JsonResponse
    {
        $snapshots = $reports->last24Months();
        if ($snapshots->count() !== 24) {
            $snapshots = $reports->refreshLast24Months($request->user());
        }

        $totalRevenue = (int) $snapshots->sum('gross_revenue');
        $completedOrders = (int) $snapshots->sum('completed_orders');

        return response()->json(['status' => 'success', 'data' => [
            'kpi' => [
                'total_revenue' => $totalRevenue,
                'monthly_revenue' => (int) ($snapshots->last()?->gross_revenue ?? 0),
                'total_orders' => Order::withoutGlobalScopes()->count(),
                'completed_orders' => $completedOrders,
                'total_customers' => User::where('role', 'customer')->count(),
                'avg_order_value' => $completedOrders > 0 ? (int) round($totalRevenue / $completedOrders) : 0,
            ],
            'revenue_by_month' => $snapshots->map(fn ($row) => [
                'month' => $row->period_month->format('Y-m'),
                'revenue' => $row->gross_revenue,
                'orders' => $row->completed_orders,
                'commission' => $row->commission_amount,
                'vendor_net' => $row->vendor_net_amount,
                'refunds' => $row->refund_amount,
                'generated_at' => $row->generated_at?->toISOString(),
            ])->values(),
            'revenue_by_payment_method' => Order::withoutGlobalScopes()
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->subMonthsNoOverflow(23)->startOfMonth())
                ->select('payment_method', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as count'))
                ->groupBy('payment_method')->get(),
            'top_vendors' => Order::withoutGlobalScopes()
                ->where('orders.status', 'completed')
                ->where('orders.created_at', '>=', now()->subMonthsNoOverflow(23)->startOfMonth())
                ->join('vendors', 'orders.vendor_id', '=', 'vendors.id')
                ->select('vendors.id', 'vendors.shop_name', DB::raw('SUM(orders.total_amount) as revenue'), DB::raw('COUNT(orders.id) as total_orders'))
                ->groupBy('vendors.id', 'vendors.shop_name')->orderByDesc('revenue')->limit(10)->get(),
            'payout_stats' => [
                'pending' => (int) PayoutRequest::where('status', 'pending')->sum('amount'),
                'approved' => (int) PayoutRequest::whereIn('status', ['approved', 'processing', 'completed'])->sum('amount'),
                'rejected' => (int) PayoutRequest::where('status', 'rejected')->sum('amount'),
            ],
            'reporting_policy' => [
                'retention_months' => 24,
                'tax_calculated_or_withheld' => false,
                'purpose' => 'Hỗ trợ đối soát, kê khai và phối hợp với cơ quan thuế khi có yêu cầu hợp lệ.',
            ],
        ]]);
    }

    public function refresh(Request $request, RevenueReportService $reports): JsonResponse
    {
        $snapshots = $reports->refreshLast24Months($request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Đã làm mới báo cáo doanh thu 24 tháng.',
            'data' => ['months' => $snapshots->count(), 'generated_at' => now()->toISOString()],
        ]);
    }

    public function export(Request $request, RevenueReportService $reports): StreamedResponse
    {
        $snapshots = $reports->last24Months();
        if ($snapshots->count() !== 24) {
            $snapshots = $reports->refreshLast24Months($request->user());
        }

        return response()->streamDownload(function () use ($snapshots): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tháng', 'Doanh thu khách thanh toán', 'Đơn hoàn tất', 'Commission', 'Doanh thu ròng nhà bán', 'Hoàn tiền', 'Tiền tệ']);
            foreach ($snapshots as $row) {
                fputcsv($output, [
                    $row->period_month->format('Y-m'), $row->gross_revenue, $row->completed_orders,
                    $row->commission_amount, $row->vendor_net_amount, $row->refund_amount, $row->currency,
                ]);
            }
            fclose($output);
        }, 'bao-cao-doanh-thu-24-thang.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\VendorEarningLedger;
use App\Models\VendorFinancialHold;
use App\Services\CommerceFeeService;
use App\Services\DemoWalletService;
use App\Services\PayoutService;
use App\Services\RevenueReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FinanceController extends Controller
{
    public function index(CommerceFeeService $fees, DemoWalletService $wallets): JsonResponse
    {
        $vendor = Auth::user()->vendor;
        if (! $vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        $pendingAmount = Order::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'processing'])->where('payment_status', 'unpaid')->sum('total_amount');
        $payoutRequests = PayoutRequest::where('vendor_id', $vendor->id)->latest('id')->get()->map(fn ($payout) => [
            'id' => 'TRX-'.str_pad($payout->id, 4, '0', STR_PAD_LEFT),
            'amount' => $payout->amount,
            'bank_name' => $payout->bank_name,
            'account_number' => $payout->account_number,
            'account_name' => $payout->account_name,
            'status' => $payout->status,
            'created_at' => $payout->created_at->format('d/m/Y H:i'),
        ]);
        $refundHolds = (int) VendorFinancialHold::where('vendor_id', $vendor->id)->where('status', 'active')->sum('amount');
        $feeSchedule = $fees->effective();
        $wallet = $wallets->accountFor(Auth::user());

        return response()->json([
            'balance' => [
                'available' => max(0, (int) $wallet->balance - $refundHolds),
                'wallet_balance' => (int) $wallet->balance,
                'reserved' => (int) $wallet->reserved_balance,
                'pending' => (int) $pendingAmount,
                'totalWithdrawn' => (int) $vendor->total_withdrawn,
                'refundHolds' => $refundHolds,
            ],
            'payout_account' => [
                'bank_name' => $vendor->payout_bank_name,
                'account_holder' => $vendor->payout_bank_holder,
                'masked_account' => $vendor->payout_bank_account
                    ? str_repeat('•', max(0, mb_strlen($vendor->payout_bank_account) - 4)).mb_substr($vendor->payout_bank_account, -4)
                    : null,
                'status' => $vendor->payout_bank_status,
                'is_demo' => (bool) $vendor->is_demo,
                'demo_wallet_code' => $vendor->is_demo ? $vendor->demo_wallet_code : null,
            ],
            'payout_requests' => $payoutRequests,
            'fee_policy' => [
                'schedule' => $feeSchedule,
                'example_base_amount' => 100000,
                'example' => $fees->calculate(100000, $feeSchedule),
                'explanation' => [
                    'commission' => 'Khấu trừ từ doanh thu gộp của Nhà bán.',
                    'service_fee' => 'Cộng vào số tiền khách thanh toán; không khấu trừ thêm từ doanh thu ròng.',
                    'tax' => 'KomiBook không tính hoặc khấu trừ thuế. Báo cáo chỉ hỗ trợ đối soát và kê khai.',
                ],
            ],
            'reporting_policy' => [
                'tax_calculated_or_withheld' => false,
                'retention_months' => 24,
                'explanation' => 'KomiBook lưu báo cáo để hỗ trợ nhà bán và phối hợp với cơ quan thuế khi có yêu cầu hợp lệ.',
            ],
        ]);
    }

    public function revenue(Request $request, RevenueReportService $reports): JsonResponse
    {
        $validated = $request->validate([
            'granularity' => 'required|in:month,quarter,year',
            'period' => 'required|string|max:10',
        ]);
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 404, 'Vendor profile not found');
        try {
            $range = $reports->vendorPeriod($validated['granularity'], $validated['period']);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $earnings = VendorEarningLedger::where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->with('order:id,order_code,status,payment_method')
            ->orderByDesc('created_at')->get();
        $reversals = DB::table('vendor_earning_reversals')
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$range['start'], $range['end']])->get();
        $refunds = DB::table('refund_transactions')
            ->join('return_requests', 'return_requests.id', '=', 'refund_transactions.return_request_id')
            ->where('return_requests.vendor_id', $vendor->id)
            ->where('refund_transactions.status', 'refunded')
            ->whereBetween('refund_transactions.refunded_at', [$range['start'], $range['end']])
            ->select('refund_transactions.*')
            ->orderByDesc('refund_transactions.refunded_at')->get();

        return response()->json(['status' => 'success', 'data' => [
            'period' => ['granularity' => $validated['granularity'], 'value' => $range['label'], 'start' => $range['start']->toDateString(), 'end' => $range['end']->toDateString()],
            'summary' => [
                'gross_revenue' => (int) $earnings->sum('gross_amount'),
                'commission_amount' => (int) $earnings->sum('commission_amount'),
                'net_revenue' => (int) $earnings->sum('net_amount'),
                'customer_refund_amount' => (int) $refunds->sum('amount'),
                'refund_amount' => (int) $refunds->sum('amount'),
                'commission_reversal_amount' => (int) $reversals->sum('commission_amount'),
                'vendor_net_reversal_amount' => (int) $reversals->sum('net_amount'),
                'vendor_net_after_refunds' => (int) $earnings->sum('net_amount') - (int) $reversals->sum('net_amount'),
                'tax_amount' => (int) $earnings->sum('tax_amount'),
                'tax_withheld' => (int) $earnings->sum('tax_amount'),
                'completed_orders' => $earnings->count(),
            ],
            'entries' => $earnings->map(fn ($entry) => [
                'id' => $entry->id,
                'order_code' => $entry->order?->order_code,
                'payment_method' => $entry->order?->payment_method,
                'gross_amount' => $entry->gross_amount,
                'commission_amount' => $entry->commission_amount,
                'net_amount' => $entry->net_amount,
                'recorded_at' => $entry->created_at->toISOString(),
            ]),
            'refund_events' => $refunds->map(fn ($refund) => [
                'id' => $refund->id,
                'customer_refund_amount' => (int) $refund->amount,
                'currency' => $refund->currency,
                'refunded_at' => Carbon::parse($refund->refunded_at)->toISOString(),
            ]),
            'earning_reversal_events' => $reversals->map(fn ($reversal) => [
                'id' => $reversal->id,
                'gross_reversal_amount' => (int) $reversal->gross_amount,
                'commission_reversal_amount' => (int) $reversal->commission_amount,
                'vendor_net_reversal_amount' => (int) $reversal->net_amount,
                'currency' => $reversal->currency,
                'recorded_at' => Carbon::parse($reversal->created_at)->toISOString(),
            ]),
        ]]);
    }

    public function exportRevenue(Request $request, RevenueReportService $reports): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'granularity' => 'required|in:month,quarter,year',
            'period' => 'required|string|max:10',
        ]);
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 404, 'Vendor profile not found');
        try {
            $range = $reports->vendorPeriod($validated['granularity'], $validated['period']);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $rows = VendorEarningLedger::where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->with('order:id,order_code,payment_method')->orderBy('created_at')->get();
        $refunds = DB::table('refund_transactions')
            ->join('return_requests', 'return_requests.id', '=', 'refund_transactions.return_request_id')
            ->where('return_requests.vendor_id', $vendor->id)
            ->where('refund_transactions.status', 'refunded')
            ->whereBetween('refund_transactions.refunded_at', [$range['start'], $range['end']])
            ->select('refund_transactions.*')->get();
        $reversals = DB::table('vendor_earning_reversals')->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$range['start'], $range['end']])->get();

        return response()->streamDownload(function () use ($rows, $refunds, $reversals): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Thời điểm sự kiện', 'Mã đơn', 'Loại sự kiện', 'Doanh thu gộp', 'Hoa hồng hoặc hoàn hoa hồng', 'Thu nhập nhà bán hoặc hoàn thu nhập', 'Hoàn tiền khách hàng', 'Tiền tệ']);
            foreach ($rows as $row) {
                fputcsv($output, [$row->created_at->format('d/m/Y H:i'), $row->order?->order_code, 'earning', $row->gross_amount, $row->commission_amount, $row->net_amount, '', $row->currency]);
            }
            foreach ($refunds as $refund) {
                fputcsv($output, [Carbon::parse($refund->refunded_at)->format('d/m/Y H:i'), '', 'customer_refund', '', '', '', $refund->amount, $refund->currency]);
            }
            foreach ($reversals as $reversal) {
                fputcsv($output, [Carbon::parse($reversal->created_at)->format('d/m/Y H:i'), '', 'earning_reversal', '', $reversal->commission_amount, $reversal->net_amount, '', $reversal->currency]);
            }
            fclose($output);
        }, "doanh-thu-{$validated['granularity']}-{$validated['period']}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function requestPayout(Request $request, PayoutService $payouts): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:50000',
            'idempotency_key' => 'nullable|string|max:100',
        ]);
        $vendor = $request->user()->vendor;
        if (! $vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }
        if ($vendor->is_demo) {
            return response()->json(['message' => 'Ví đối soát demo không được phép tạo yêu cầu rút tiền thật.'], 422);
        }
        if ($vendor->payout_bank_status !== 'verified') {
            return response()->json(['message' => 'Tài khoản ngân hàng nhận doanh thu chưa được xác minh.'], 422);
        }
        $validated += [
            'bank_name' => $vendor->payout_bank_name,
            'account_number' => $vendor->payout_bank_account,
            'account_name' => $vendor->payout_bank_holder,
        ];

        try {
            $payout = $payouts->reserve($vendor, $validated, $request->user(), $validated['idempotency_key'] ?? $request->header('Idempotency-Key'));
            $vendor = $vendor->fresh();

            return response()->json([
                'message' => 'Yêu cầu rút tiền đã được giữ số dư và chuyển sang chờ Admin duyệt.',
                'balance' => ['available' => (int) $vendor->balance, 'totalWithdrawn' => (int) $vendor->total_withdrawn],
                'payout' => $payout,
            ], $payout->wasRecentlyCreated ? 201 : 200);
        } catch (LogicException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Đã xảy ra lỗi khi xử lý yêu cầu rút tiền.'], 500);
        }
    }
}

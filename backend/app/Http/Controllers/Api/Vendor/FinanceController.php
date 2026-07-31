<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\VendorFinancialHold;
use App\Services\CommerceFeeService;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Throwable;

class FinanceController extends Controller
{
    public function index(CommerceFeeService $fees)
    {
        $vendor = Auth::user()->vendor;
        if (! $vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        $pendingAmount = Order::where('vendor_id', $vendor->id)->whereIn('status', ['pending', 'processing'])->where('payment_status', 'unpaid')->sum('total_amount');
        $payoutRequests = PayoutRequest::where('vendor_id', $vendor->id)->latest('id')->get()->map(fn ($payout) => [
            'id' => 'TRX-'.str_pad($payout->id, 4, '0', STR_PAD_LEFT), 'amount' => $payout->amount,
            'bank_name' => $payout->bank_name, 'account_number' => $payout->account_number,
            'account_name' => $payout->account_name, 'status' => $payout->status,
            'created_at' => $payout->created_at->format('d/m/Y H:i'),
        ]);
        $refundHolds = (int) VendorFinancialHold::where('vendor_id', $vendor->id)->where('status', 'active')->sum('amount');
        $feeSchedule = $fees->effective();

        return response()->json([
            'balance' => ['available' => max(0, (int) $vendor->balance - $refundHolds), 'pending' => (int) $pendingAmount, 'totalWithdrawn' => (int) $vendor->total_withdrawn, 'refundHolds' => $refundHolds],
            'payout_account' => [
                'bank_name' => $vendor->payout_bank_name,
                'account_holder' => $vendor->payout_bank_holder,
                'masked_account' => $vendor->payout_bank_account
                    ? str_repeat('•', max(0, mb_strlen($vendor->payout_bank_account) - 4)).mb_substr($vendor->payout_bank_account, -4)
                    : null,
                'status' => $vendor->payout_bank_status,
            ],
            'payout_requests' => $payoutRequests,
            'fee_policy' => [
                'schedule' => $feeSchedule,
                'example_base_amount' => 100000,
                'example' => $fees->calculate(100000, $feeSchedule),
                'explanation' => [
                    'commission' => 'Khấu trừ từ doanh thu gộp của Nhà bán.',
                    'service_fee' => 'Cộng vào số tiền khách thanh toán; không khấu trừ thêm từ seller_net.',
                    'tax' => 'Chưa cấu hình thuế trong fee engine hiện tại.',
                ],
            ],
        ]);
    }

    public function requestPayout(Request $request, PayoutService $payouts)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:50000',
            'idempotency_key' => 'nullable|string|max:100',
        ]);
        $vendor = $request->user()->vendor;
        if (! $vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
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
                'message' => 'Yêu cầu rút tiền đã được giữ số dư và chuyển sang chờ duyệt.',
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

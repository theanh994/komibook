<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /**
     * Lấy thông tin tài chính và lịch sử rút tiền của vendor.
     */
    public function index()
    {
        $vendor = Auth::user()->vendor;
        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        // Tính doanh thu tạm giữ (pending balance) từ các đơn hàng có trạng thái 'pending' hoặc 'processing' nhưng chưa được thanh toán thành công
        $pendingAmount = Order::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'processing'])
            ->where('payment_status', 'unpaid')
            ->sum('total_amount');

        // Danh sách các yêu cầu rút tiền
        $payoutRequests = PayoutRequest::where('vendor_id', $vendor->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($pr) {
                return [
                    'id' => 'TRX-' . str_pad($pr->id, 4, '0', STR_PAD_LEFT),
                    'amount' => $pr->amount,
                    'bank_name' => $pr->bank_name,
                    'account_number' => $pr->account_number,
                    'account_name' => $pr->account_name,
                    'status' => $pr->status,
                    'created_at' => $pr->created_at->format('d/m/Y H:i'),
                ];
            });

        return response()->json([
            'balance' => [
                'available' => (int) $vendor->balance,
                'pending' => (int) $pendingAmount,
                'totalWithdrawn' => (int) $vendor->total_withdrawn,
            ],
            'payout_requests' => $payoutRequests,
        ]);
    }

    /**
     * Gửi yêu cầu rút tiền.
     */
    public function requestPayout(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:50000',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:255',
        ]);

        $vendor = Auth::user()->vendor;
        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        if ($vendor->balance < $request->amount) {
            return response()->json([
                'message' => 'Số dư khả dụng của bạn không đủ để thực hiện yêu cầu này.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Trừ tiền trực tiếp vào số dư khả dụng ngay lập tức
            $vendor->balance -= $request->amount;
            $vendor->save();

            // Tạo yêu cầu rút tiền
            $payoutRequest = PayoutRequest::create([
                'vendor_id' => $vendor->id,
                'amount' => $request->amount,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'account_name' => strtoupper($request->account_name),
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Yêu cầu rút tiền của bạn đã được gửi thành công và đang chờ duyệt.',
                'balance' => [
                    'available' => (int) $vendor->balance,
                    'totalWithdrawn' => (int) $vendor->total_withdrawn,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xử lý yêu cầu: ' . $e->getMessage()
            ], 500);
        }
    }
}

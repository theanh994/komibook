<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconciliationController extends Controller
{
    /**
     * GET /api/admin/reconciliation
     *
     * Danh sách đối soát: các đơn hàng completed chưa thanh toán cho vendor
     * + các yêu cầu rút tiền đang chờ duyệt.
     */
    public function index(Request $request): JsonResponse
    {
        // ── KPI ──────────────────────────────────────────────────────────
        $pendingPayoutAmount = PayoutRequest::where('status', 'pending')->sum('amount');
        $approvedPayoutAmount = PayoutRequest::where('status', 'approved')->sum('amount');
        $totalSettled = PayoutRequest::where('status', 'completed')->sum('amount');

        $unreconciled = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->count();

        // ── Danh sách yêu cầu rút tiền (phân trang) ─────────────────────
        $query = PayoutRequest::with('vendor:id,shop_name,balance,total_withdrawn')
            ->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('vendor', function ($q) use ($search) {
                $q->where('shop_name', 'LIKE', "%{$search}%");
            });
        }

        $payoutRequests = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data'   => [
                'kpi' => [
                    'pending_payout'  => (int) $pendingPayoutAmount,
                    'approved_payout' => (int) $approvedPayoutAmount,
                    'total_settled'   => (int) $totalSettled,
                    'unreconciled'    => $unreconciled,
                ],
                'payout_requests' => $payoutRequests->items(),
                'meta' => [
                    'current_page' => $payoutRequests->currentPage(),
                    'last_page'    => $payoutRequests->lastPage(),
                    'per_page'     => $payoutRequests->perPage(),
                    'total'        => $payoutRequests->total(),
                ],
            ],
        ]);
    }

    /**
     * PATCH /api/admin/reconciliation/{id}/approve
     *
     * Duyệt yêu cầu rút tiền → cộng vào total_withdrawn của vendor.
     */
    public function approve(int $id): JsonResponse
    {
        $payout = PayoutRequest::findOrFail($id);

        if ($payout->status !== 'pending') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Yêu cầu này đã được xử lý trước đó.',
            ], 422);
        }

        DB::transaction(function () use ($payout) {
            $payout->status = 'approved';
            $payout->save();

            // Cộng vào tổng đã rút của vendor
            $vendor = Vendor::withoutGlobalScopes()->findOrFail($payout->vendor_id);
            $vendor->total_withdrawn += $payout->amount;
            $vendor->save();
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã duyệt yêu cầu rút tiền thành công.',
        ]);
    }

    /**
     * PATCH /api/admin/reconciliation/{id}/reject
     *
     * Từ chối yêu cầu rút tiền → hoàn lại số dư cho vendor.
     */
    public function reject(int $id): JsonResponse
    {
        $payout = PayoutRequest::findOrFail($id);

        if ($payout->status !== 'pending') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Yêu cầu này đã được xử lý trước đó.',
            ], 422);
        }

        DB::transaction(function () use ($payout) {
            $payout->status = 'rejected';
            $payout->save();

            // Hoàn lại số dư cho vendor (vì đã trừ khi tạo request)
            $vendor = Vendor::withoutGlobalScopes()->findOrFail($payout->vendor_id);
            $vendor->balance += $payout->amount;
            $vendor->save();
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã từ chối yêu cầu rút tiền. Số dư đã được hoàn lại cho nhà bán.',
        ]);
    }
}

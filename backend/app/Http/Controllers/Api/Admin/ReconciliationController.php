<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

class ReconciliationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PayoutRequest::with('vendor:id,shop_name,balance,total_withdrawn')->latest();
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->whereHas('vendor', fn ($vendor) => $vendor->where('shop_name', 'like', '%'.$request->search.'%'));
        }
        $items = $query->paginate($request->integer('per_page', 15));

        return response()->json(['status' => 'success', 'data' => [
            'kpi' => [
                'pending_payout' => (int) PayoutRequest::where('status', 'pending')->sum('amount'),
                'approved_payout' => (int) PayoutRequest::whereIn('status', ['approved', 'processing'])->sum('amount'),
                'total_settled' => (int) PayoutRequest::where('status', 'completed')->sum('amount'),
                'unreconciled' => Order::withoutGlobalScopes()->where('status', 'completed')->where('payment_status', 'paid')->count(),
            ],
            'payout_requests' => $items->items(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(), 'total' => $items->total()],
        ]]);
    }

    public function transition(Request $request, PayoutRequest $payout, PayoutService $payouts): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'required|in:approved,rejected,processing,completed', 'reason' => 'nullable|string|max:1000',
            'transfer_reference' => 'nullable|string|max:120', 'transfer_evidence' => 'nullable|string|max:500',
            'idempotency_key' => 'required|string|max:120',
        ]);
        try {
            $updated = $payouts->transition($payout, $validated['target'], $request->user(), $validated['idempotency_key'], $validated);

            return response()->json(['status' => 'success', 'data' => $updated]);
        } catch (LogicException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function approve(Request $request, int $id, PayoutService $payouts): JsonResponse
    {
        $request->merge(['target' => 'approved', 'idempotency_key' => $request->input('idempotency_key', (string) Str::uuid())]);

        return $this->transition($request, PayoutRequest::withoutGlobalScopes()->findOrFail($id), $payouts);
    }

    public function reject(Request $request, int $id, PayoutService $payouts): JsonResponse
    {
        $request->merge(['target' => 'rejected', 'idempotency_key' => $request->input('idempotency_key', (string) Str::uuid())]);

        return $this->transition($request, PayoutRequest::withoutGlobalScopes()->findOrFail($id), $payouts);
    }
}

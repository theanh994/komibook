<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnRequestResource;
use App\Models\ReturnRequest;
use App\Services\ReturnRefundService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnRequestController extends Controller
{
    public function customerIndex(Request $request): JsonResponse
    {
        $returns = ReturnRequest::where('user_id', $request->user()->id)
            ->with($this->relations())
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => ReturnRequestResource::collection($returns),
        ]);
    }

    public function store(Request $request, ReturnRefundService $service, int $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $return = $service->createRequest(
                $order,
                $request->user(),
                $validated['items'],
                $validated['reason'],
                $validated['idempotency_key']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Yêu cầu trả hàng đã được tạo.',
                'data' => new ReturnRequestResource($return),
            ], 201);
        } catch (AuthorizationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 403);
        } catch (\LogicException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(Request $request, int $returnRequest): JsonResponse
    {
        $return = ReturnRequest::with($this->relations())->findOrFail($returnRequest);
        $user = $request->user();
        $allowed = (int) $return->user_id === (int) $user->id
            || $user->role === 'admin'
            || ($user->role === 'vendor' && (int) $user->vendor?->id === (int) $return->vendor_id);
        abort_unless($allowed, 403);

        return response()->json([
            'status' => 'success',
            'data' => new ReturnRequestResource($return),
        ]);
    }

    public function vendorIndex(Request $request): JsonResponse
    {
        $vendorId = $request->user()->vendor?->id;
        abort_unless($vendorId, 403);

        $returns = ReturnRequest::where('vendor_id', $vendorId)
            ->with($this->relations())
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => ReturnRequestResource::collection($returns),
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        $returns = ReturnRequest::with($this->relations())->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => ReturnRequestResource::collection($returns),
        ]);
    }

    public function transition(
        Request $request,
        ReturnRefundService $service,
        int $returnRequest
    ): JsonResponse {
        $validated = $request->validate([
            'target' => ['required', Rule::in(['under_review', 'approved', 'rejected', 'item_received'])],
            'reason' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        try {
            $return = $service->transition(
                $returnRequest,
                $validated['target'],
                $request->user(),
                $validated['idempotency_key'],
                $validated['reason'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'data' => new ReturnRequestResource($return),
            ]);
        } catch (AuthorizationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 403);
        } catch (\LogicException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function processRefund(
        Request $request,
        ReturnRefundService $service,
        int $returnRequest
    ): JsonResponse {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:128'],
            'evidence' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $return = $service->processRefund(
                $returnRequest,
                $request->user(),
                $validated['idempotency_key'],
                $request->ip(),
                $validated['evidence'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'data' => new ReturnRequestResource($return),
            ]);
        } catch (AuthorizationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 403);
        } catch (\LogicException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function reconcileRefund(
        Request $request,
        ReturnRefundService $service,
        int $returnRequest
    ): JsonResponse {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        try {
            $return = $service->reconcileRefund(
                $returnRequest,
                $request->user(),
                $validated['idempotency_key'],
                $request->ip()
            );

            return response()->json([
                'status' => 'success',
                'data' => new ReturnRequestResource($return),
            ]);
        } catch (AuthorizationException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 403);
        } catch (\LogicException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'order.user',
            'order.vendor',
            'items.orderItem.book',
            'transitions' => fn ($query) => $query->orderBy('occurred_at'),
            'refundTransaction.attempts',
        ];
    }
}

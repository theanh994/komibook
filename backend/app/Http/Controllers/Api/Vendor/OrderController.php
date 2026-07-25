<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Danh sách đơn hàng của Vendor.
     *
     * MultiVendorScoped tự động filter theo vendor_id.
     */
    public function index(Request $request)
    {
        $query = Order::with('user')->orderByDesc('created_at');

        // Lọc theo trạng thái
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Tìm kiếm theo mã đơn hoặc tên khách
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate($request->get('per_page', 15));

        return OrderResource::collection($orders)->additional([
            'status' => 'success',
            'message' => 'Lấy danh sách đơn hàng thành công.',
        ]);
    }

    /**
     * Chi tiết một đơn hàng (load kèm items.book và user).
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'orderItems.book']);

        return response()->json([
            'status' => 'success',
            'message' => 'Lấy chi tiết đơn hàng thành công.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Cập nhật trạng thái đơn hàng.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $newStatus = $request->validated()['status'];
        $actorId = $request->user()?->id;

        try {
            $fulfillmentService = app(OrderFulfillmentService::class);
            $updatedOrder = $fulfillmentService->updateOrderStatusByVendor($order, $newStatus, 'vendor', $actorId);

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật trạng thái đơn hàng thành công!',
                'data' => new OrderResource($updatedOrder->load('user')),
            ]);
        } catch (\LogicException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cập nhật trạng thái nhiều đơn hàng cùng lúc.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'status' => ['required', 'string', 'in:shipped'],
        ]);

        $newStatus = $request->status;
        $orderIds = collect($request->order_ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
        $actorId = $request->user()?->id;
        $fulfillmentService = app(OrderFulfillmentService::class);

        try {
            DB::transaction(function () use ($orderIds, $newStatus, $actorId, $fulfillmentService) {
                foreach ($orderIds as $orderId) {
                    $fulfillmentService->updateOrderStatusByVendor($orderId, $newStatus, 'vendor', $actorId);
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Đã cập nhật trạng thái thành công cho '.count($orderIds).' đơn hàng!',
                'updated_count' => count($orderIds),
            ]);
        } catch (\LogicException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

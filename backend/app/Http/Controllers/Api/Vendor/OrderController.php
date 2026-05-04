<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'status'  => 'success',
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
            'status'  => 'success',
            'message' => 'Lấy chi tiết đơn hàng thành công.',
            'data'    => new OrderResource($order),
        ]);
    }

    /**
     * Cập nhật trạng thái đơn hàng.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $newStatus = $request->validated()['status'];
        $oldStatus = $order->status;

        // Không cho phép cập nhật đơn đã bị hủy hoặc đã hoàn thành
        if (in_array($oldStatus, ['cancelled', 'completed'])) {
            return response()->json([
                'status'  => 'error',
                'message' => "Không thể cập nhật đơn hàng đã ở trạng thái \"{$oldStatus}\".",
            ], 422);
        }

        $order->update(['status' => $newStatus]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cập nhật trạng thái đơn hàng thành công!',
            'data'    => new OrderResource($order->fresh()->load('user')),
        ]);
    }
}

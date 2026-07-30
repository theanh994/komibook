<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403, 'Bạn chưa được cấp quyền gian hàng.');

        $orders = Order::query()->where('vendor_id', $vendor->id);
        $books = Book::query()->where('vendor_id', $vendor->id);
        $statusBreakdown = (clone $orders)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);
        $recentOrders = (clone $orders)
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'order_code', 'user_id', 'total_amount', 'status', 'created_at'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'customer_name' => $order->user?->name,
                'total_amount' => (int) $order->total_amount,
                'status' => $order->status,
                'created_at' => $order->created_at?->toISOString(),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_revenue' => (int) (clone $orders)->where('status', 'completed')->sum('total_amount'),
                'pending_orders' => (clone $orders)->whereIn('status', ['pending', 'processing'])->count(),
                'total_orders' => (clone $orders)->count(),
                'completed_orders' => (clone $orders)->where('status', 'completed')->count(),
                'total_books' => (clone $books)->where('status', 'published')->count(),
                'draft_books' => (clone $books)->where('status', 'draft')->count(),
                'order_status_breakdown' => $statusBreakdown,
                'recent_orders' => $recentOrders,
            ],
        ]);
    }
}

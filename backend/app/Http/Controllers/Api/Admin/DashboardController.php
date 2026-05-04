<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/stats
     *
     * Trả về các con số thống kê tổng quan cho Admin tối cao.
     * Bypass Global Scope vì Admin cần nhìn thấy toàn bộ dữ liệu.
     */
    public function stats(Request $request): JsonResponse
    {
        // Kiểm tra role admin — nếu không phải admin thì 403
        abort_if($request->user()->role !== 'admin', 403, 'Bạn không có quyền truy cập.');

        $totalUsers   = User::count();
        $totalVendors = Vendor::withoutGlobalScopes()->count();
        $totalBooks   = Book::withoutGlobalScopes()->count();

        // Tổng doanh thu từ đơn hàng hoàn thành
        $totalRevenue = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->sum('total_amount');

        // Thống kê thêm cho dashboard đẹp hơn
        $pendingOrders = Order::withoutGlobalScopes()
            ->where('status', 'pending')
            ->count();

        $totalOrders = Order::withoutGlobalScopes()->count();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'total_users'    => $totalUsers,
                'total_vendors'  => $totalVendors,
                'total_books'    => $totalBooks,
                'total_revenue'  => $totalRevenue,
                'total_orders'   => $totalOrders,
                'pending_orders' => $pendingOrders,
            ],
        ]);
    }
}

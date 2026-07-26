<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /api/admin/users
     *
     * Trả về danh sách toàn bộ users (phân trang).
     */
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->role !== 'admin', 403, 'Bạn không có quyền truy cập.');

        $perPage = $request->input('per_page', 15);

        $users = User::with('vendor:id,user_id,shop_name,status')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * PATCH /api/admin/users/{id}/role
     *
     * Cho phép Admin đổi role của một user.
     * Nếu đổi sang 'vendor' và user chưa có Vendor profile → tự tạo.
     * Nếu đổi từ 'vendor' sang 'customer' → giữ lại Vendor profile (có thể reactivate).
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        abort_if($request->user()->role !== 'admin', 403, 'Bạn không có quyền truy cập.');

        $validated = $request->validate([
            'role' => ['required', Rule::in(['customer', 'vendor'])],
        ]);

        $user = User::findOrFail($id);

        // Không cho phép đổi role của chính mình hoặc user admin khác
        if ($user->role === 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể thay đổi quyền của Admin.',
            ], 422);
        }

        $newRole = $validated['role'];
        $existingVendor = Vendor::withoutGlobalScopes()->where('user_id', $user->id)->first();
        if ($newRole === 'vendor' && (! $existingVendor || ! $existingVendor->isActive())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ có thể gán role vendor sau khi hồ sơ nhà bán được phê duyệt.',
            ], 422);
        }
        if ($newRole !== 'vendor' && $user->role === 'vendor' && $existingVendor?->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hãy đình chỉ hoặc thu hồi hồ sơ nhà bán trước khi bỏ role vendor.',
            ], 422);
        }

        $user->update(['role' => $newRole]);

        // Reload với vendor relationship
        $user->load('vendor:id,user_id,shop_name,status');

        return response()->json([
            'status' => 'success',
            'message' => "Đã cập nhật role thành '{$newRole}'.",
            'data' => $user,
        ]);
    }

    /**
     * GET /api/admin/users/{id}
     *
     * Xem chi tiết một user kèm các thống kê mua hàng / bán hàng.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['addresses' => function ($q) {
            $q->orderBy('is_default', 'desc');
        }, 'membershipTier'])->findOrFail($id);

        $data = $user->toArray();

        // Lấy thêm số lượng đơn hàng và tổng chi tiêu
        $orderStats = DB::table('orders')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->selectRaw('COUNT(id) as total_orders, SUM(total_amount) as total_spent')
            ->first();

        $data['total_orders'] = $orderStats->total_orders ?? 0;
        $data['total_spent'] = $orderStats->total_spent ?? 0;

        if ($user->role === 'vendor') {
            $vendor = Vendor::withoutGlobalScopes()->where('user_id', $user->id)->first();
            if ($vendor) {
                $data['vendor_info'] = $vendor;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}

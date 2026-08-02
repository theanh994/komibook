<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
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

        $perPage = min(max($request->integer('per_page', 15), 10), 100);
        $sortBy = in_array($request->input('sort_by'), ['name', 'email', 'role', 'created_at'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortDirection = $request->input('sort_direction') === 'asc' ? 'asc' : 'desc';

        $users = User::with('vendor:id,user_id,shop_name,status')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('search')).'%';
                $query->where(fn ($users) => $users
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->input('role')))
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('id', 'desc')
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
    public function show(Request $request, int $id): JsonResponse
    {
        abort_if($request->user()->role !== 'admin', 403, 'Bạn không có quyền truy cập.');

        $user = User::with(['addresses' => function ($q) {
            $q->orderBy('is_default', 'desc');
        }, 'membershipTier', 'organizationMemberships.organization:id,display_name,legal_name,slug,status,data_mode',
            'warehouseManagerAssignments.vendor:id,shop_name',
            'warehouseManagerAssignments.warehouse:id,name',
            'usedBookSellerProfile.catalogVendor:id,shop_name',
        ])->findOrFail($id);

        $data = $user->toArray();

        $orderStats = DB::table('orders')
            ->where('user_id', $user->id)
            ->selectRaw("COUNT(id) as total_orders, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders, SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as total_spent")
            ->first();

        $data['total_orders'] = (int) ($orderStats->total_orders ?? 0);
        $data['completed_orders'] = (int) ($orderStats->completed_orders ?? 0);
        $data['total_spent'] = (int) ($orderStats->total_spent ?? 0);
        $data['purchased_books_count'] = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $user->id)
            ->where('orders.status', 'completed')
            ->distinct()
            ->count('order_items.book_id');
        $data['reviews_count'] = DB::table('reviews')->where('user_id', $user->id)->count();
        $data['wishlist_count'] = DB::table('wishlists')->where('user_id', $user->id)->count();
        $lastActivity = DB::table('sessions')->where('user_id', $user->id)->max('last_activity');
        $data['last_login_at'] = $lastActivity
            ? Carbon::createFromTimestamp((int) $lastActivity)->toIso8601String()
            : null;
        $data['orders'] = Order::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->with('vendor:id,shop_name')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'order_code', 'vendor_id', 'total_amount', 'status', 'payment_status', 'payment_method', 'created_at']);

        $vendor = Vendor::withoutGlobalScopes()
            ->with(['primaryWarehouse:id,name,address', 'primaryOrganization:id,display_name,legal_name,slug,status,data_mode'])
            ->where('user_id', $user->id)
            ->first();
        if ($vendor) {
            $data['vendor_info'] = $vendor;
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function terminateSessions(Request $request, int $id): JsonResponse
    {
        abort_if($request->user()->role !== 'admin', 403);
        $user = User::findOrFail($id);
        abort_if($user->role === 'admin', 422, 'Không thể ngắt phiên của tài khoản Admin.');

        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đăng xuất tài khoản khỏi các thiết bị và thu hồi token truy cập.',
        ]);
    }
}

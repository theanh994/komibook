<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'data'   => $users->items(),
            'meta'   => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
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
                'status'  => 'error',
                'message' => 'Không thể thay đổi quyền của Admin.',
            ], 422);
        }

        $newRole = $validated['role'];
        $user->role = $newRole;
        $user->save();

        // Nếu đổi thành vendor → tạo Vendor profile nếu chưa có
        if ($newRole === 'vendor') {
            $existingVendor = Vendor::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->first();

            if (!$existingVendor) {
                Vendor::withoutGlobalScopes()->create([
                    'user_id'     => $user->id,
                    'shop_name'   => 'Shop của ' . $user->name,
                    'slug'        => Str::slug($user->name . '-' . $user->id),
                    'description' => '',
                    'status'      => 'active',
                ]);
            }
        }

        // Reload với vendor relationship
        $user->load('vendor:id,user_id,shop_name,status');

        return response()->json([
            'status'  => 'success',
            'message' => "Đã cập nhật role thành '{$newRole}'.",
            'data'    => $user,
        ]);
    }
}

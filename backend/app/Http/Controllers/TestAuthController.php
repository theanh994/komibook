<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestAuthController extends Controller
{
    /**
     * GET /api/test-auth
     *
     * Trả về thông tin user đang đăng nhập (yêu cầu Sanctum token).
     * Dùng để kiểm tra kết nối Frontend ↔ Backend.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'is_admin'   => $user->isAdmin(),
            'is_vendor'  => $user->isVendor(),
        ];

        // Nếu là vendor, đính kèm thông tin shop
        if ($user->isVendor()) {
            $vendor = $user->vendor; // Relationship HasOne
            $data['vendor_profile'] = $vendor ? [
                'shop_name'   => $vendor->shop_name,
                'slug'        => $vendor->slug,
                'description' => $vendor->description,
                'status'      => $vendor->status,
            ] : null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully',
            'data'    => $data,
        ]);
    }
}

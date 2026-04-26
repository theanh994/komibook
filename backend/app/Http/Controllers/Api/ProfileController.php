<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Cập nhật thông tin cá nhân cơ bản
     */
    public function updateInfo(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        // Chỉ update các trường được phép
        $user->update($request->only(['name', 'phone', 'address']));

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thông tin cá nhân thành công.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Đổi mật khẩu
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đổi mật khẩu thành công.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AccountSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Cập nhật thông tin cá nhân cơ bản & sở thích đọc sách (Cold Start Recommendations)
     */
    public function updateInfo(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        // Chỉ cập nhật thông tin cá nhân, không làm thay đổi hồ sơ nghiệp vụ.
        $user->update($request->only(['name', 'phone', 'gender', 'birthday', 'address']));

        if ($request->has('marketing_consent')) {
            $user->forceFill($request->boolean('marketing_consent')
                ? ['marketing_consent_at' => now(), 'marketing_opt_out_at' => null]
                : ['marketing_consent_at' => null, 'marketing_opt_out_at' => now()]
            )->save();
        }

        // Đồng bộ thể loại sách yêu thích (Cold Start recommendations)
        if ($request->has('favorite_category_ids')) {
            $catIds = (array) $request->input('favorite_category_ids', []);
            $user->favoriteCategories()->sync($catIds);
        }

        $user->load(['membershipTier', 'vendor', 'usedBookSellerProfile', 'favoriteCategories']);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thông tin cá nhân thành công.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Đổi mật khẩu
     */
    public function updatePassword(UpdatePasswordRequest $request, AccountSessionService $sessions): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        $sessions->revokeOtherSessions($user, $request->session()->getId());
        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json([
            'status' => 'success',
            'message' => 'Đổi mật khẩu thành công.',
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => $path]);

            return response()->json([
                'message' => 'Avatar updated successfully',
                'avatar_url' => '/storage/'.$path,
            ]);
        }

        return response()->json(['message' => 'Upload failed'], 400);
    }

    public function getAddresses(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->get();

        return response()->json(['data' => $addresses]);
    }

    public function addAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($validated);

        return response()->json(['message' => 'Address added', 'data' => $address]);
    }

    public function updateAddress(Request $request, $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $validated = $request->validate([
            'receiver_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json(['message' => 'Address updated', 'data' => $address]);
    }

    public function deleteAddress(Request $request, $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return response()->json(['message' => 'Address deleted']);
    }

    public function setDefaultAddress(Request $request, $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json(['message' => 'Set as default address']);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipTier;
use App\Models\User;
use Illuminate\Http\Request;

class MembershipTierController extends Controller
{
    /**
     * Lấy danh sách hạng thành viên.
     */
    public function index()
    {
        $tiers = MembershipTier::orderBy('min_points', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $tiers
        ]);
    }

    /**
     * Thêm hạng thành viên mới.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:membership_tiers,name',
            'min_points' => 'required|integer|min:0',
            'discount_percent' => 'required|integer|min:0|max:100',
            'benefits' => 'nullable|string',
        ]);

        $tier = MembershipTier::create([
            'name' => $request->name,
            'min_points' => $request->min_points,
            'discount_percent' => $request->discount_percent,
            'benefits' => $request->benefits,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã tạo hạng thành viên mới.',
            'data' => $tier
        ], 201);
    }

    /**
     * Cập nhật hạng thành viên.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:membership_tiers,name,' . $id,
            'min_points' => 'required|integer|min:0',
            'discount_percent' => 'required|integer|min:0|max:100',
            'benefits' => 'nullable|string',
        ]);

        $tier = MembershipTier::findOrFail($id);
        $tier->update([
            'name' => $request->name,
            'min_points' => $request->min_points,
            'discount_percent' => $request->discount_percent,
            'benefits' => $request->benefits,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật hạng thành viên thành công.',
            'data' => $tier
        ]);
    }

    /**
     * Xóa hạng thành viên.
     */
    public function destroy($id)
    {
        $tier = MembershipTier::findOrFail($id);
        $tier->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa hạng thành viên thành công.'
        ]);
    }

    /**
     * Gán thủ công hạng thành viên cho User.
     */
    public function updateUserTier(Request $request, $userId)
    {
        $request->validate([
            'membership_tier_id' => 'nullable|exists:membership_tiers,id',
            'points' => 'nullable|integer|min:0',
        ]);

        $user = User::findOrFail($userId);

        if ($request->has('membership_tier_id')) {
            $user->membership_tier_id = $request->membership_tier_id;
        }

        if ($request->has('points')) {
            $user->points = $request->points;
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thông tin tích lũy khách hàng thành công.',
            'data' => $user->load('membershipTier')
        ]);
    }
}

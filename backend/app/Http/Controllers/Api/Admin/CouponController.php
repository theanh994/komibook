<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::with('category')->latest()->get();
        return response()->json(['success' => true, 'data' => $coupons]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'discount_percent' => 'required|numeric|min:0|max:100',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'category_id' => 'nullable|exists:categories,id',
            'usage_limit' => 'nullable|integer|min:0',
        ]);

        $coupon = Coupon::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tạo mã giảm giá thành công!',
            'data' => $coupon
        ], 201);
    }

    public function show(Coupon $coupon)
    {
        $coupon->load('category');
        return response()->json(['success' => true, 'data' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code,' . $coupon->id,
            'discount_percent' => 'required|numeric|min:0|max:100',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'category_id' => 'nullable|exists:categories,id',
            'usage_limit' => 'nullable|integer|min:0',
        ]);

        $coupon->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật mã giảm giá thành công!',
            'data' => $coupon
        ]);
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();
        return response()->json(['success' => true, 'message' => 'Xóa mã giảm giá thành công!']);
    }
}

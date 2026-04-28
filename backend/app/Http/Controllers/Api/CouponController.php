<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function apply(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'total_amount' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json(['success' => false, 'message' => 'Mã giảm giá không tồn tại.'], 404);
        }

        if ($coupon->valid_until && $coupon->valid_until->isPast()) {
            return response()->json(['success' => false, 'message' => 'Mã giảm giá đã hết hạn.'], 400);
        }

        if ($request->total_amount < $coupon->min_order_value) {
            return response()->json(['success' => false, 'message' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã này.'], 400);
        }

        $discountAmount = ($request->total_amount * $coupon->discount_percent) / 100;

        if ($coupon->max_discount_amount && $discountAmount > $coupon->max_discount_amount) {
            $discountAmount = $coupon->max_discount_amount;
        }

        return response()->json([
            'success' => true,
            'message' => 'Áp dụng mã giảm giá thành công!',
            'data' => [
                'discount_amount' => round($discountAmount),
                'code' => $coupon->code
            ]
        ]);
    }
}

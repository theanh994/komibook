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
            'items' => 'nullable|array',
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json(['success' => false, 'message' => 'Mã giảm giá không tồn tại.'], 404);
        }

        // 1. Kiểm tra thời gian
        $now = now();
        if (($coupon->start_time && $now->lt($coupon->start_time)) || ($coupon->end_time && $now->gt($coupon->end_time))) {
            return response()->json(['success' => false, 'message' => 'Mã không trong thời gian sử dụng.'], 400);
        }
        
        // Backward compatibility for valid_until if still used
        if ($coupon->valid_until && $coupon->valid_until->isPast()) {
            return response()->json(['success' => false, 'message' => 'Mã giảm giá đã hết hạn.'], 400);
        }

        // 2. Kiểm tra giới hạn sử dụng
        if ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json(['success' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng.'], 400);
        }

        // 3. Kiểm tra đơn hàng tối thiểu (áp dụng cho tổng đơn hàng trước khi lọc category?)
        // Thường thì min_order_value áp dụng cho tổng đơn. 
        if ($request->total_amount < $coupon->min_order_value) {
            return response()->json(['success' => false, 'message' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã này.'], 400);
        }

        // 4. Tính toán giảm giá theo danh mục
        $baseAmountForDiscount = $request->total_amount;
        
        if ($coupon->category_id && $request->has('items')) {
            $baseAmountForDiscount = 0;
            foreach ($request->items as $item) {
                // Giả sử item có category_id hoặc ta fetch từ DB
                // Để chính xác tuyệt đối, nên fetch từ DB nếu frontend không tin cậy
                $book = \App\Models\Book::find($item['id'] ?? $item['book_id']);
                if ($book && $book->category_id == $coupon->category_id) {
                    $baseAmountForDiscount += ($item['price'] * $item['quantity']);
                }
            }
        }

        $discountAmount = ($baseAmountForDiscount * $coupon->discount_percent) / 100;

        if ($coupon->max_discount_amount && $discountAmount > $coupon->max_discount_amount) {
            $discountAmount = $coupon->max_discount_amount;
        }

        if ($discountAmount <= 0) {
            return response()->json(['success' => false, 'message' => 'Mã này không áp dụng cho các sản phẩm trong giỏ hàng của bạn.'], 400);
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

    public function flashSales()
    {
        $now = now();
        $flashSales = \App\Models\FlashSale::where('is_active', true)
            ->where('end_time', '>', $now)
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $flashSales
        ]);
    }

    public function activeFlashSale()
    {
        $now = now();
        $activeSale = \App\Models\FlashSale::where('is_active', true)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->with(['items.book.category', 'items.book.vendor'])
            ->first();

        return response()->json([
            'success' => true,
            'data' => $activeSale
        ]);
    }
}

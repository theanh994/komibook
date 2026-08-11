<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();

        return response()->json([
            'data' => Coupon::where('vendor_id', $vendor->id)->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        $validated = $request->validate($this->rules());
        $validated = $this->normalizeScope($validated, $vendor->id);

        $coupon = Coupon::create([
            ...$validated,
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'used_count' => 0,
        ]);

        return response()->json([
            'message' => 'Mã giảm giá đã được tạo và gửi kiểm duyệt.',
            'data' => $coupon,
        ], 201);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        abort_unless((int) $coupon->vendor_id === (int) $vendor->id, 404);
        $validated = $request->validate($this->rules($coupon->id));
        $validated = $this->normalizeScope($validated, $vendor->id);
        $coupon->update([...$validated, 'status' => 'pending']);

        return response()->json([
            'message' => 'Mã giảm giá đã được cập nhật và gửi duyệt lại.',
            'data' => $coupon->fresh(),
        ]);
    }

    private function rules(?int $couponId = null): array
    {
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('coupons', 'code')->ignore($couponId)],
            'coupon_type' => 'nullable|in:product,shipping',
            'discount_percent' => 'required|numeric|min:1|max:100',
            'min_order_value' => 'nullable|integer|min:0',
            'max_discount_amount' => 'nullable|integer|min:0',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'usage_limit' => 'nullable|integer|min:1',
            'scope_book_ids' => 'nullable|array|max:100',
            'scope_book_ids.*' => 'integer|distinct',
            'scope_type' => 'nullable|in:store,category,books',
            'category_id' => 'nullable|required_if:scope_type,category|integer|exists:categories,id',
            'stacking_policy' => 'required|in:allow,deny',
        ];
    }

    private function normalizeScope(array $validated, int $vendorId): array
    {
        $validated['coupon_type'] = $validated['coupon_type'] ?? 'product';
        $scopeType = $validated['scope_type'] ?? 'store';
        unset($validated['scope_type']);

        if ($validated['coupon_type'] === 'shipping' || $scopeType === 'store') {
            $validated['category_id'] = null;
            $validated['scope_book_ids'] = null;
        } elseif ($scopeType === 'category') {
            $validated['scope_book_ids'] = null;
            $hasBooks = Book::withoutGlobalScopes()
                ->where('vendor_id', $vendorId)
                ->where(function ($query) use ($validated) {
                    $query->where('category_id', $validated['category_id'])
                        ->orWhereHas('categories', fn ($categories) => $categories->whereKey($validated['category_id']));
                })
                ->exists();
            abort_unless($hasBooks, 422, 'Thể loại đã chọn chưa có sách thuộc gian hàng.');
        } else {
            $validated['category_id'] = null;
            abort_if(empty($validated['scope_book_ids']), 422, 'Hãy chọn ít nhất một sách áp dụng.');
            $this->assertBookScope($validated['scope_book_ids'], $vendorId);
        }

        return $validated;
    }

    private function assertBookScope(array $bookIds, int $vendorId): void
    {
        if ($bookIds === []) {
            return;
        }
        $count = Book::withoutGlobalScopes()->where('vendor_id', $vendorId)->whereIn('id', $bookIds)->count();
        abort_unless($count === count($bookIds), 422, 'Mã giảm giá chỉ được áp dụng cho sách thuộc gian hàng.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Coupon;
use App\Models\FlashSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CouponController extends Controller
{
    public function apply(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'total_amount' => 'required|numeric|min:0',
            'items' => 'nullable|array',
        ]);

        $coupon = Coupon::where('code', $request->code)->where('status', 'active')->first();
        if (! $coupon) {
            return $this->errorResponse('Mã giảm giá không tồn tại.', 404);
        }

        $now = now();
        if (($coupon->start_time && $now->lt($coupon->start_time)) || ($coupon->end_time && $now->gt($coupon->end_time))) {
            return $this->errorResponse('Mã không trong thời gian sử dụng.');
        }
        if ($coupon->valid_until && $coupon->valid_until->isPast()) {
            return $this->errorResponse('Mã giảm giá đã hết hạn.');
        }
        if ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) {
            return $this->errorResponse('Mã giảm giá đã hết lượt sử dụng.');
        }
        if ($request->total_amount < $coupon->min_order_value) {
            return $this->errorResponse('Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã này.');
        }

        $baseAmountForDiscount = $request->total_amount;
        if ($request->has('items') && ($coupon->vendor_id || $coupon->category_id || ! empty($coupon->scope_book_ids))) {
            $itemBookIds = collect($request->items)
                ->map(fn ($item) => (int) ($item['id'] ?? $item['book_id'] ?? 0))
                ->filter()
                ->unique();
            $eligibleQuery = Book::withoutGlobalScopes()->whereIn('id', $itemBookIds);
            if ($coupon->vendor_id) {
                $eligibleQuery->where('vendor_id', $coupon->vendor_id);
            }
            if (! empty($coupon->scope_book_ids)) {
                $eligibleQuery->whereIn('id', array_map('intval', $coupon->scope_book_ids));
            } elseif ($coupon->category_id) {
                $eligibleQuery->where(function ($query) use ($coupon) {
                    $query->where('category_id', $coupon->category_id)
                        ->orWhereHas('categories', fn ($categories) => $categories->whereKey($coupon->category_id));
                });
            }

            $eligibleBookIds = $eligibleQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
            $baseAmountForDiscount = 0;
            foreach ($request->items as $item) {
                $bookId = (int) ($item['id'] ?? $item['book_id'] ?? 0);
                if (in_array($bookId, $eligibleBookIds, true)) {
                    $baseAmountForDiscount += ((int) $item['price'] * (int) $item['quantity']);
                }
            }
        }

        $discountAmount = ($baseAmountForDiscount * $coupon->discount_percent) / 100;
        if ($coupon->max_discount_amount && $discountAmount > $coupon->max_discount_amount) {
            $discountAmount = $coupon->max_discount_amount;
        }
        if ($discountAmount <= 0) {
            return $this->errorResponse('Mã này không áp dụng cho các sản phẩm trong giỏ hàng của bạn.');
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Áp dụng mã giảm giá thành công!',
            'data' => [
                'discount_amount' => round($discountAmount),
                'code' => $coupon->code,
            ],
        ]);
    }

    public function flashSales()
    {
        $now = now();
        $vendorColumns = ['id', 'shop_name', 'slug', 'logo'];
        if (Schema::hasColumn('vendors', 'views_count')) {
            $vendorColumns[] = 'views_count';
        }
        $flashSales = FlashSale::whereIn('status', ['enrollment_open', 'active'])
            ->where('end_time', '>', $now)
            ->orderBy('start_time', 'asc')
            ->with(['items' => fn ($items) => $items
                ->where('status', 'approved')
                ->with([
                    'book:id,vendor_id,title,slug,cover_image',
                    'book.vendor' => fn ($vendors) => $vendors->select($vendorColumns),
                ])])
            ->get()
            ->map(function (FlashSale $sale) {
                $spotlights = $sale->items
                    ->map(fn ($item) => $item->book?->vendor)
                    ->filter()
                    ->unique('id')
                    ->sortByDesc('views_count')
                    ->take(4)
                    ->values()
                    ->map(fn ($vendor) => [
                        'id' => $vendor->id,
                        'shop_name' => $vendor->shop_name,
                        'slug' => $vendor->slug,
                        'logo' => $vendor->logo ? '/storage/'.ltrim($vendor->logo, '/') : null,
                        'views_count' => (int) ($vendor->views_count ?? 0),
                    ]);

                return [
                    ...$sale->toArray(),
                    'time_status' => $sale->start_time->isFuture() ? 'upcoming' : 'active',
                    'vendor_spotlights' => $spotlights,
                ];
            });

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $flashSales,
        ]);
    }

    public function activeFlashSale()
    {
        $now = now();
        $activeSale = FlashSale::where('is_active', true)
            ->where('status', 'active')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->with(['items' => function ($query) {
                $query->where('status', 'approved')->where(function ($items) {
                    $items->where('max_quantity', 0)->orWhereColumn('sold_quantity', '<', 'max_quantity');
                })
                    ->whereHas('book', fn ($books) => $books->withoutGlobalScopes()->where('status', 'published')->whereHas('vendor', fn ($vendors) => $vendors->withoutGlobalScopes()->where('status', 'active')))
                    ->with(['book.category', 'book.vendor']);
            }])
            ->first();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $activeSale,
        ]);
    }

    private function errorResponse(string $message, int $httpStatus = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'success' => false,
            'message' => $message,
        ], $httpStatus);
    }
}

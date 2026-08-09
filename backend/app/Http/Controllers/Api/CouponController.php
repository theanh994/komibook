<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Services\CouponPricingService;
use App\Services\FlashSalePricingService;
use App\Services\ShippingPricingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function apply(Request $request, ShippingPricingPolicy $shippingPricing, CouponPricingService $couponPricing, FlashSalePricingService $flashPricing)
    {
        $request->validate([
            'code' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $normalizedBookIds = collect($request->items)->map(fn ($item) => (int) $item['id']);
        if ($normalizedBookIds->count() !== $normalizedBookIds->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'Duplicate book IDs are not allowed.']);
        }

        $coupon = Coupon::where('code', $request->code)->first();
        if (! $coupon) {
            return $this->errorResponse('Coupon not found.', 404);
        }
        try {
            [$groupedItems, $books, $cartSubtotal] = $this->serverPricedGroups($request->items, $flashPricing);
            $couponPricing->validateCurrent($coupon, $cartSubtotal);
            $quote = $couponPricing->quote($coupon, $groupedItems, $books, $cartSubtotal, $shippingPricing);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->errors()['coupon'][0] ?? 'Coupon is invalid.');
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => $coupon->coupon_type === 'shipping' ? 'Shipping coupon applied.' : 'Coupon applied.',
            'data' => [
                'discount_amount' => $quote['total_discount'],
                'code' => $coupon->code,
                'coupon_type' => $coupon->coupon_type,
                'shipping_policy' => $shippingPricing->publicPayload(),
            ],
        ]);
    }

    public function shippingPolicy(ShippingPricingPolicy $shippingPricing): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $shippingPricing->publicPayload(),
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

                return [...$sale->toArray(), 'time_status' => $sale->start_time->isFuture() ? 'upcoming' : 'active', 'vendor_spotlights' => $spotlights];
            });

        return response()->json(['status' => 'success', 'success' => true, 'data' => $flashSales]);
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
                })->whereHas('book', fn ($books) => $books->withoutGlobalScopes()->where('status', 'published')->whereHas('vendor', fn ($vendors) => $vendors->withoutGlobalScopes()->where('status', 'active')))->with(['book.category', 'book.vendor']);
            }])->first();

        return response()->json(['status' => 'success', 'success' => true, 'data' => $activeSale]);
    }

    /** @return array{0:array<int,array<int,array{book_id:int,quantity:int,price:int}>>,1:Collection,2:int} */
    private function serverPricedGroups(array $items, FlashSalePricingService $flashPricing): array
    {
        $bookIds = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        $books = Book::withoutGlobalScopes()->with(['categories', 'vendor'])->whereIn('id', $bookIds)->get()->keyBy('id');
        $groups = [];
        $subtotal = 0;
        foreach ($items as $item) {
            $book = $books->get((int) $item['id']);
            if (! $book || ! $book->isPublished() || ! $book->vendor || ! $book->vendor->isActive()) {
                throw ValidationException::withMessages(['coupon' => 'Cart contains an unavailable book.']);
            }
            $quantity = (int) $item['quantity'];
            $pricing = $flashPricing->resolve($book, $quantity);
            if (($pricing['promotion_snapshot']['coupon_stacking_policy'] ?? null) === 'deny') {
                throw ValidationException::withMessages(['coupon' => 'This coupon cannot be stacked with the active Flash Sale.']);
            }
            $price = (int) $pricing['unit_price'];
            $groups[(int) $book->vendor_id][] = ['book_id' => (int) $book->id, 'quantity' => $quantity, 'price' => $price];
            $subtotal += $price * $quantity;
        }

        return [$groups, $books, $subtotal];
    }

    private function errorResponse(string $message, int $httpStatus = 400): JsonResponse
    {
        return response()->json(['status' => 'error', 'success' => false, 'message' => $message], $httpStatus);
    }
}

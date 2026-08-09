<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CouponPricingService
{
    /**
     * @param  array<int,array<int,array{book_id:int,quantity:int,price:int}>>  $groupedItems
     * @return array{product_discounts:array<int,int>,shipping_discounts:array<int,int>,total_discount:int}
     */
    public function quote(Coupon $coupon, array $groupedItems, Collection $books, int $cartSubtotal, ShippingPricingPolicy $shippingPricing): array
    {
        if (! in_array($coupon->coupon_type, ['product', 'shipping'], true)) {
            throw ValidationException::withMessages(['coupon' => 'Coupon type is invalid.']);
        }

        $productDiscounts = [];
        $shippingDiscounts = [];
        if ($coupon->coupon_type === 'shipping') {
            foreach ($groupedItems as $vendorId => $vendorItems) {
                if ($coupon->vendor_id && (int) $coupon->vendor_id !== (int) $vendorId) {
                    continue;
                }
                $hasPhysical = collect($vendorItems)->some(fn ($item) => ($books->get($item['book_id'])?->type ?? 'ebook') !== 'ebook');
                $baseFee = $shippingPricing->baseFeeForVendor($hasPhysical, $cartSubtotal);
                if ($baseFee > 0) {
                    $shippingDiscounts[(int) $vendorId] = $this->percentDiscount($baseFee, $coupon);
                }
            }
            $shippingDiscounts = $this->capDiscounts($shippingDiscounts, $coupon->max_discount_amount);
        } else {
            $scopeBookIds = array_map('intval', $coupon->scope_book_ids ?? []);
            foreach ($groupedItems as $vendorId => $vendorItems) {
                if ($coupon->vendor_id && (int) $coupon->vendor_id !== (int) $vendorId) {
                    continue;
                }
                $eligibleBase = 0;
                foreach ($vendorItems as $item) {
                    $book = $books->get($item['book_id']);
                    $inBookScope = empty($scopeBookIds) || in_array((int) $item['book_id'], $scopeBookIds, true);
                    if ($inBookScope && $this->matchesCategory($book, $coupon->category_id)) {
                        $eligibleBase += (int) $item['price'] * (int) $item['quantity'];
                    }
                }
                $productDiscounts[(int) $vendorId] = $this->percentDiscount($eligibleBase, $coupon);
            }
            $productDiscounts = $this->capDiscounts($productDiscounts, $coupon->max_discount_amount);
        }

        $total = array_sum($productDiscounts) + array_sum($shippingDiscounts);
        if ($total <= 0) {
            throw ValidationException::withMessages(['coupon' => 'Coupon has no applicable discount for this checkout.']);
        }

        return [
            'product_discounts' => $productDiscounts,
            'shipping_discounts' => $shippingDiscounts,
            'total_discount' => $total,
        ];
    }

    public function validateCurrent(Coupon $coupon, int $cartSubtotal): void
    {
        $now = now();
        if ($coupon->status !== 'active' || ! in_array($coupon->coupon_type, ['product', 'shipping'], true)) {
            throw ValidationException::withMessages(['coupon' => 'Coupon is unavailable or has an invalid type.']);
        }
        if (($coupon->start_time && $now->lt($coupon->start_time)) || ($coupon->end_time && $now->gt($coupon->end_time)) || ($coupon->valid_until && $coupon->valid_until->isPast()) || ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) || $cartSubtotal < $coupon->min_order_value) {
            throw ValidationException::withMessages(['coupon' => 'Coupon is no longer valid.']);
        }
    }

    private function matchesCategory(mixed $book, mixed $categoryId): bool
    {
        if (! $categoryId) {
            return true;
        }

        return $book && ((int) $book->category_id === (int) $categoryId || $book->categories->contains('id', (int) $categoryId));
    }

    private function percentDiscount(int $baseAmount, Coupon $coupon): int
    {
        return max(0, (int) round(($baseAmount * (float) $coupon->discount_percent) / 100, 0, PHP_ROUND_HALF_UP));
    }

    /** @param array<int,int> $discounts @return array<int,int> */
    private function capDiscounts(array $discounts, mixed $maximum): array
    {
        $discounts = array_map(fn ($amount) => max(0, (int) $amount), $discounts);
        $total = array_sum($discounts);
        $cap = $maximum === null ? null : (int) $maximum;
        if ($total === 0 || $cap === null || $cap <= 0 || $total <= $cap) {
            return $discounts;
        }

        $allocated = [];
        $remainders = [];
        foreach ($discounts as $vendorId => $amount) {
            $scaled = $amount * $cap;
            $allocated[$vendorId] = intdiv($scaled, $total);
            $remainders[$vendorId] = $scaled % $total;
        }
        uksort($remainders, fn ($left, $right) => $remainders[$right] <=> $remainders[$left] ?: ((int) $left <=> (int) $right));
        foreach (array_keys($remainders) as $vendorId) {
            if (array_sum($allocated) >= $cap) {
                break;
            }
            $allocated[$vendorId]++;
        }

        return $allocated;
    }
}

<?php

namespace App\Services;

class ShippingPricingPolicy
{
    /** @return array{version:string,currency:string,free_shipping_threshold:int,base_fee_per_physical_vendor:int} */
    public function publicPayload(): array
    {
        return [
            'version' => (string) config('shipping.pricing_policy.version'),
            'currency' => (string) config('shipping.pricing_policy.currency'),
            'free_shipping_threshold' => $this->integerConfig('free_shipping_threshold'),
            'base_fee_per_physical_vendor' => $this->integerConfig('base_fee_per_physical_vendor'),
        ];
    }

    /** @return array{version:string,currency:string,free_shipping_threshold:int,base_fee_per_physical_vendor:int} */
    public function snapshot(): array
    {
        return $this->publicPayload();
    }

    public function qualifiesForFreeShipping(int $cartSubtotal): bool
    {
        return $cartSubtotal >= $this->integerConfig('free_shipping_threshold');
    }

    public function baseFeeForVendor(bool $hasPhysicalItems, int $cartSubtotal): int
    {
        if (! $hasPhysicalItems || $this->qualifiesForFreeShipping($cartSubtotal)) {
            return 0;
        }

        return $this->integerConfig('base_fee_per_physical_vendor');
    }

    public function feeAfterDiscount(int $baseFee, int $discount): int
    {
        return max(0, $baseFee - max(0, $discount));
    }

    private function integerConfig(string $key): int
    {
        return max(0, (int) config("shipping.pricing_policy.{$key}"));
    }
}

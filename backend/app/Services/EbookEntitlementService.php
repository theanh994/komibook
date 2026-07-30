<?php

namespace App\Services;

use App\Models\EbookEntitlement;
use App\Models\Order;

class EbookEntitlementService
{
    public function grantForOrder(Order $order): void
    {
        $order->loadMissing('orderItems.book');
        foreach ($order->orderItems as $item) {
            if (! $item->book?->isEbook() || ! $item->ebook_version_id) {
                continue;
            }
            EbookEntitlement::updateOrCreate(
                ['user_id' => $order->user_id, 'book_id' => $item->book_id],
                [
                    'order_item_id' => $item->id,
                    'purchase_version_id' => $item->ebook_version_id,
                    'activated_at' => now(),
                    'revoked_at' => null,
                    'revocation_reason' => null,
                ]
            );
        }
    }
}

<?php

namespace App\Http\Resources;

use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'order_id' => $this->order_id,
            'order_code' => $this->whenLoaded('order', fn () => $this->order?->order_code),
            'order_payment_method' => $this->whenLoaded('order', fn () => $this->order?->payment_method),
            'customer' => $this->whenLoaded('order', fn () => $this->order?->relationLoaded('user') && $this->order?->user ? [
                'id' => $this->order->user->id,
                'name' => $this->order->user->name,
                'email' => $this->order->user->email,
            ] : null),
            'vendor' => $this->whenLoaded('order', fn () => $this->order?->relationLoaded('vendor') && $this->order?->vendor ? [
                'id' => $this->order->vendor->id,
                'shop_name' => $this->order->vendor->shop_name,
            ] : null),
            'user_id' => $this->user_id,
            'vendor_id' => $this->vendor_id,
            'status' => $this->status,
            'currency' => $this->currency,
            'refund_amount' => $this->refund_amount,
            'reason' => $this->reason,
            'resolution_reason' => $this->resolution_reason,
            'requested_at' => $this->requested_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'item_received_at' => $this->item_received_at?->toISOString(),
            'refund_started_at' => $this->refund_started_at?->toISOString(),
            'refunded_at' => $this->refunded_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'order_item_id' => $item->order_item_id,
                'quantity' => $item->quantity,
                'unit_amount' => $item->unit_amount,
                'refund_amount' => $item->refund_amount,
                'inventory_restored_at' => $item->inventory_restored_at?->toISOString(),
                'book' => $item->orderItem?->book ? [
                    'id' => $item->orderItem->book->id,
                    'title' => $item->orderItem->book->title,
                    'type' => $item->orderItem->book->type,
                    'cover_image' => PublicMediaUrl::storage($item->orderItem->book->cover_image),
                ] : null,
            ])),
            'transitions' => $this->whenLoaded('transitions', fn () => $this->transitions->map(fn ($transition) => [
                'from' => $transition->from_state,
                'to' => $transition->to_state,
                'actor_type' => $transition->actor_type,
                'actor_id' => $transition->actor_id,
                'reason' => $transition->reason,
                'occurred_at' => $transition->occurred_at?->toISOString(),
            ])),
            'refund_transaction' => $this->whenLoaded('refundTransaction', function () {
                if (! $this->refundTransaction) {
                    return null;
                }

                return [
                    'provider' => $this->refundTransaction->provider,
                    'provider_reference' => $this->refundTransaction->provider_reference,
                    'amount' => $this->refundTransaction->amount,
                    'currency' => $this->refundTransaction->currency,
                    'status' => $this->refundTransaction->status,
                    'evidence' => $this->refundTransaction->evidence,
                    'failure_reason' => $this->refundTransaction->failure_reason,
                    'attempts' => $this->refundTransaction->relationLoaded('attempts')
                        ? $this->refundTransaction->attempts->map(fn ($attempt) => [
                            'attempt_number' => $attempt->attempt_number,
                            'status' => $attempt->status,
                            'failure_reason' => $attempt->failure_reason,
                            'attempted_at' => $attempt->attempted_at?->toISOString(),
                        ])
                        : [],
                ];
            }),
        ];
    }
}

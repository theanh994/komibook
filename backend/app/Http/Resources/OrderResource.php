<?php

namespace App\Http\Resources;

use App\Support\PublicMediaUrl;
use App\Support\ShippingTimeline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cancellationPreview = $this->resource->relationLoaded('buyerCancellationPreview')
            ? $this->resource->getRelation('buyerCancellationPreview')
            : null;
        $canCancel = is_array($cancellationPreview) && ($cancellationPreview['eligible'] ?? false) === true;

        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'refund_status' => $this->refund_status ?? 'none',
            'shipping_status' => $this->shipping_status,
            'shipping_carrier' => $this->shipping_carrier,
            'shipping_tracking_code' => $this->shipping_tracking_code,
            'can_confirm_receipt' => $this->status === 'shipped' && $this->shipping_status === 'awaiting_customer_confirmation',
            'can_cancel' => $canCancel,
            'cancellation_scope' => $canCancel ? ($cancellationPreview['scope'] ?? null) : null,
            'shipping_address' => $this->shipping_address,
            'phone' => $this->phone,
            'created_at' => $this->created_at->toISOString(),
            'shipping_events' => $this->whenLoaded('transitionOperations', fn () => ShippingTimeline::forOrder($this->resource)),

            'subtotal_amount' => $this->invoiceSnapshot?->subtotal_amount ?? ($this->relationLoaded('orderItems') ? (int) $this->orderItems->sum(fn ($i) => (int) $i->price * (int) $i->quantity) : (int) $this->total_amount),
            'shipping_fee_amount' => $this->invoiceSnapshot?->shipping_fee_amount ?? 0,
            'coupon_discount_amount' => $this->invoiceSnapshot?->coupon_discount_amount ?? 0,
            'membership_discount_amount' => $this->invoiceSnapshot?->membership_discount_amount ?? 0,
            'discount_amount' => ($this->invoiceSnapshot?->coupon_discount_amount ?? 0) + ($this->invoiceSnapshot?->membership_discount_amount ?? 0),
            'invoice' => $this->whenLoaded('invoiceSnapshot', function () {
                if (! $this->invoiceSnapshot) {
                    return null;
                }

                return [
                    'invoice_number' => $this->invoiceSnapshot->invoice_number,
                    'issued_at' => $this->invoiceSnapshot->issued_at?->toISOString(),
                    'currency' => $this->invoiceSnapshot->currency,
                    'subtotal_amount' => $this->invoiceSnapshot->subtotal_amount,
                    'shipping_fee_amount' => $this->invoiceSnapshot->shipping_fee_amount,
                    'coupon_discount_amount' => $this->invoiceSnapshot->coupon_discount_amount,
                    'membership_discount_amount' => $this->invoiceSnapshot->membership_discount_amount,
                    'total_amount' => $this->invoiceSnapshot->total_amount,
                    'buyer' => $this->invoiceSnapshot->buyer_snapshot,
                    'seller' => $this->invoiceSnapshot->seller_snapshot,
                    'line_items' => $this->invoiceSnapshot->line_items,
                ];
            }),

            // Thông tin người mua (Eager Loaded)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            'items' => $this->whenLoaded('orderItems', function () {
                return $this->orderItems->map(function ($item) {
                    $bookData = null;
                    if ($item->relationLoaded('book') && $item->book) {
                        $bookData = [
                            'id' => $item->book->id,
                            'title' => $item->book->title,
                            'author' => $item->book->author,
                            'cover_image' => PublicMediaUrl::storage($item->book->cover_image),
                            'type' => $item->book->type,
                            'is_physical' => $item->book->type === 'physical',
                            'provenance' => $item->product_taxonomy_snapshot['provenance'] ?? $item->book->provenance,
                        ];
                    }

                    return [
                        'id' => $item->id,
                        'book_id' => $item->book_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'return_policy' => [
                            'is_returnable' => (bool) ($item->return_policy_snapshot['is_returnable'] ?? false),
                            'return_window_days' => $item->return_policy_snapshot['return_window_days'] ?? null,
                            'terms' => $item->return_policy_snapshot['terms'] ?? null,
                        ],
                        'book' => $bookData,
                    ];
                });
            }),
        ];
    }
}

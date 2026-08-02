<?php

namespace App\Http\Resources;

use App\Support\PublicMediaUrl;
use App\Support\ShippingTimeline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'shipping_status' => $this->shipping_status,
            'shipping_carrier' => $this->shipping_carrier,
            'shipping_tracking_code' => $this->shipping_tracking_code,
            'can_confirm_receipt' => $this->status === 'shipped' && $this->shipping_status === 'awaiting_customer_confirmation',
            'shipping_address' => $this->shipping_address,
            'phone' => $this->phone,
            'created_at' => $this->created_at?->toISOString(),
            'refund_status' => $this->refund_status ?? 'none',
            'shipping_events' => ShippingTimeline::forOrder($this->resource),

            // Customer information
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->phone ?? $this->user?->phone,
            ],
            'customer_name' => $this->user?->name,
            'customer_email' => $this->user?->email,
            'customer_phone' => $this->phone ?? $this->user?->phone,

            // Order items (key: items)
            'items' => $this->orderItems->map(function ($item) {
                $bookData = null;
                if ($item->relationLoaded('book') && $item->book) {
                    $bookData = [
                        'id' => $item->book->id,
                        'title' => $item->book->title,
                        'cover_image' => PublicMediaUrl::storage($item->book->cover_image),
                        'type' => $item->book->type,
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
            }),
            'invoice' => $this->whenLoaded('invoiceSnapshot', function () {
                if (! $this->invoiceSnapshot) {
                    return null;
                }

                return [
                    'invoice_number' => $this->invoiceSnapshot->invoice_number,
                    'issued_at' => $this->invoiceSnapshot->issued_at?->toISOString(),
                    'currency' => $this->invoiceSnapshot->currency,
                    'buyer' => $this->invoiceSnapshot->buyer_snapshot,
                    'seller' => $this->invoiceSnapshot->seller_snapshot,
                    'line_items' => $this->invoiceSnapshot->line_items,
                    'subtotal_amount' => $this->invoiceSnapshot->subtotal_amount,
                    'coupon_discount_amount' => $this->invoiceSnapshot->coupon_discount_amount,
                    'membership_discount_amount' => $this->invoiceSnapshot->membership_discount_amount,
                    'shipping_fee_amount' => $this->invoiceSnapshot->shipping_fee_amount,
                    'service_fee_amount' => $this->invoiceSnapshot->service_fee_amount,
                    'tax_rate' => $this->invoiceSnapshot->tax_rate,
                    'tax_amount' => $this->invoiceSnapshot->tax_amount,
                    'total_amount' => $this->invoiceSnapshot->total_amount,
                ];
            }),
        ];
    }
}

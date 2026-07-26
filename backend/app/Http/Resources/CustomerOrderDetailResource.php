<?php

namespace App\Http\Resources;

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
            'shipping_address' => $this->shipping_address,
            'phone' => $this->phone,
            'created_at' => $this->created_at?->toISOString(),

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
                        'cover_image' => $item->book->cover_image
                            ? (str_starts_with($item->book->cover_image, 'http') || str_starts_with($item->book->cover_image, '/storage/')
                                ? $item->book->cover_image
                                : '/storage/'.$item->book->cover_image)
                            : null,
                        'type' => $item->book->type,
                    ];
                }

                return [
                    'id' => $item->id,
                    'book_id' => $item->book_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'book' => $bookData,
                ];
            }),
        ];
    }
}

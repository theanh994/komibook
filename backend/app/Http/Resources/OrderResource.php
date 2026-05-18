<?php

namespace App\Http\Resources;

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
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'shipping_address' => $this->shipping_address,
            'phone' => $this->phone,
            'created_at' => $this->created_at->toISOString(),

            // Thông tin người mua (Eager Loaded)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id'    => $this->user->id,
                    'name'  => $this->user->name,
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
                            'cover_image' => $item->book->cover_image
                                ? '/storage/' . $item->book->cover_image
                                : null,
                            'type' => $item->book->type,
                        ];
                    }
                    
                    return [
                        'id' => $item->id,
                        'book_id' => $item->book_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'book' => $bookData
                    ];
                });
            }),
        ];
    }
}

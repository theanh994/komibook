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
            'created_at' => $this->created_at->toISOString(),
            'items' => $this->whenLoaded('orderItems', function () {
                return $this->orderItems->map(function ($item) {
                    $bookData = null;
                    if ($item->relationLoaded('book') && $item->book) {
                        $bookData = [
                            'id' => $item->book->id,
                            'title' => $item->book->title,
                            'cover_image' => $item->book->cover_image,
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

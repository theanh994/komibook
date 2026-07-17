<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'slug'        => $this->slug,
            'author'      => $this->author,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? '/storage/' . $this->cover_image : null,
            'isbn'        => $this->isbn,
            'price'       => $this->price,
            'sale_price'  => $this->sale_price,
            'stock'       => $this->stock,
            'type'        => $this->type,
            'status'      => $this->status,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,

            // Quan hệ (Eager Loaded)
            'vendor'   => $this->whenLoaded('vendor', function () {
                return [
                    'id'   => $this->vendor->id,
                    'name' => $this->vendor->shop_name,
                    'slug' => $this->vendor->slug,
                ];
            }),
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id'   => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),
            'series' => $this->whenLoaded('series', function () {
                return [
                    'id'    => $this->series->id,
                    'title' => $this->series->title,
                ];
            }),
            'reviews'  => $this->whenLoaded('reviews', function () {
                return $this->reviews->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at,
                        'user' => [
                            'name' => $review->user->name ?? 'Người dùng ẩn danh',
                        ]
                    ];
                });
            }),
            'chapters' => $this->chapters ? $this->chapters->map(function ($chapter) {
                return [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'order' => $chapter->order,
                    'is_free' => (bool) $chapter->is_free,
                    'content' => $chapter->is_free ? $chapter->content : null,
                ];
            }) : [],
        ];
    }
}

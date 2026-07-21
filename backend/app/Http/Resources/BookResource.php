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
            'id'             => $this->id,
            'title'          => $this->title,
            'slug'           => $this->slug,
            'author'         => $this->author,
            'translator'     => $this->translator,
            'description'    => $this->description,
            'cover_image'    => $this->cover_image ? (filter_var($this->cover_image, FILTER_VALIDATE_URL) ? $this->cover_image : '/storage/' . $this->cover_image) : null,
            'gallery_images' => is_array($this->gallery_images) ? array_map(function ($img) {
                return filter_var($img, FILTER_VALIDATE_URL) ? $img : '/storage/' . $img;
            }, $this->gallery_images) : [],
            'isbn'           => $this->isbn,
            'dimensions'     => $this->dimensions,
            'cover_format'   => $this->cover_format,
            'weight'         => $this->weight,
            'language'       => $this->language,
            'target_age'     => $this->target_age,
            'pages'          => $this->pages,
            'release_date'   => $this->release_date,
            'price'          => $this->price,
            'sale_price'     => $this->sale_price,
            'stock'          => $this->stock,
            'views'          => $this->views ?? 0,
            'wishlists_count'=> $this->wishlists_count ?? ($this->relationLoaded('wishlists') ? $this->wishlists->count() : $this->wishlists()->count()),
            'type'           => $this->type,
            'status'         => $this->status,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,

            // Quan hệ (Eager Loaded)
            'vendor'   => $this->whenLoaded('vendor', function () {
                return [
                    'id'   => $this->vendor->id,
                    'name' => $this->vendor->shop_name,
                    'slug' => $this->vendor->slug,
                ];
            }),
            'categories' => $this->relationLoaded('categories') && $this->categories->isNotEmpty() ? $this->categories->map(function ($cat) {
                return [
                    'id'   => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ];
            }) : ($this->category ? [[
                'id'   => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]] : []),
            'category' => $this->category ? [
                'id'   => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : ($this->relationLoaded('categories') && $this->categories->isNotEmpty() ? [
                'id'   => $this->categories->first()->id,
                'name' => $this->categories->first()->name,
                'slug' => $this->categories->first()->slug,
            ] : null),
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

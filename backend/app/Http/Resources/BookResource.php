<?php

namespace App\Http\Resources;

use App\Services\HtmlSanitizer;
use App\Support\PublicMediaUrl;
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
        $mediaVersion = $this->updated_at?->getTimestamp();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'display_title' => $this->display_title,
            'slug' => $this->slug,
            'author' => $this->author,
            'translator' => $this->translator,
            'description' => HtmlSanitizer::sanitize($this->description),
            'cover_image' => PublicMediaUrl::versionedStorage($this->cover_image, $mediaVersion),
            'gallery_images' => is_array($this->gallery_images) ? array_map(function ($img) use ($mediaVersion) {
                return PublicMediaUrl::versionedStorage($img, $mediaVersion);
            }, $this->gallery_images) : [],
            'isbn' => $this->isbn,
            'dimensions' => $this->dimensions,
            'cover_format' => $this->cover_format,
            'weight' => $this->weight,
            'language' => $this->language,
            'target_age' => $this->target_age,
            'pages' => $this->pages,
            'release_date' => $this->release_date,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'stock' => $this->stock,
            'print_edition' => max(1, (int) ($this->print_edition ?? 1)),
            'discoverability' => $this->status !== 'published'
                ? 'hidden'
                : ((int) $this->stock > 0 ? 'browse_and_search' : 'search_only'),
            'is_purchasable' => $this->isPurchasable(),
            'views' => $this->views ?? 0,
            'wishlists_count' => $this->wishlists_count ?? ($this->relationLoaded('wishlists') ? $this->wishlists->count() : $this->wishlists()->count()),
            'type' => $this->type,
            'format' => $this->format ?? $this->type,
            'provenance' => $this->provenance,
            'condition' => $this->condition,
            'fulfillment_mode' => $this->fulfillment_mode,
            'return_policy_version_id' => $this->return_policy_version_id,
            'latest_ebook_version' => $this->whenLoaded('latestEbookVersion', function () {
                if (! $this->latestEbookVersion) {
                    return $this->type === 'ebook' ? [
                        'id' => null,
                        'version' => 1,
                        'release_notes' => null,
                        'published_at' => $this->created_at?->toISOString(),
                    ] : null;
                }

                return [
                    'id' => $this->latestEbookVersion->id,
                    'version' => $this->latestEbookVersion->version,
                    'release_notes' => $this->latestEbookVersion->release_notes,
                    'published_at' => $this->latestEbookVersion->published_at?->toISOString(),
                ];
            }),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Quan hệ (Eager Loaded)
            'vendor' => $this->whenLoaded('vendor', function () {
                return [
                    'id' => $this->vendor->id,
                    'name' => $this->vendor->shop_name,
                    'slug' => $this->vendor->slug,
                ];
            }),
            'commercial_parties' => $this->whenLoaded('activeCommercialParties', function () {
                return $this->activeCommercialParties->mapWithKeys(function ($party) {
                    $organization = $party->organization;

                    return [$party->role => [
                        'organization_id' => $organization?->id,
                        'display_name' => $organization?->display_name,
                        'slug' => $organization?->slug,
                        'organization_types' => $organization?->organization_types,
                        'acceptance_status' => $organization?->status,
                        'data_mode' => $organization?->data_mode,
                        'is_demo' => $organization?->data_mode === 'demo',
                        'verified_at' => $party->verified_at?->toISOString(),
                        'party_version' => $party->version,
                    ]];
                });
            }),
            'warehouse_stocks' => $this->whenLoaded('warehouseStocks', function () {
                return $this->warehouseStocks->map(fn ($stock) => [
                    'warehouse_id' => $stock->warehouse_id,
                    'warehouse_name' => $stock->warehouse?->name,
                    'quantity' => $stock->quantity,
                    'shelf_location' => $stock->shelf_location,
                ])->values();
            }),
            'categories' => $this->relationLoaded('categories') && $this->categories->isNotEmpty() ? $this->categories->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ];
            }) : ($this->category ? [[
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]] : []),
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : ($this->relationLoaded('categories') && $this->categories->isNotEmpty() ? [
                'id' => $this->categories->first()->id,
                'name' => $this->categories->first()->name,
                'slug' => $this->categories->first()->slug,
            ] : null),
            'series' => $this->whenLoaded('series', function () {
                return [
                    'id' => $this->series->id,
                    'title' => $this->series->title,
                ];
            }),
            'reviews' => $this->whenLoaded('reviews', function () {
                return $this->reviews->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at,
                        'user' => [
                            'name' => $review->user->name ?? 'Người dùng ẩn danh',
                        ],
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

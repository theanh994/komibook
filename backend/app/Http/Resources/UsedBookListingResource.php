<?php

namespace App\Http\Resources;

use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsedBookListingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'book' => $this->relationLoaded('book') && $this->book ? [
                'id' => $this->book->id,
                'title' => $this->book->title,
                'author' => $this->book->author,
                'price' => $this->book->price,
                'cover_image' => PublicMediaUrl::storage($this->book->cover_image),
                'provenance' => $this->book->provenance,
                'category' => $this->book->category ? [
                    'id' => $this->book->category->id,
                    'name' => $this->book->category->name,
                    'slug' => $this->book->category->slug,
                ] : null,
            ] : null,
            'condition' => $this->condition,
            'defects' => $this->defects,
            'actual_photos' => collect($this->actual_photos ?? [])
                ->map(fn (string $path) => PublicMediaUrl::storage($path))
                ->values()
                ->all(),
            'quantity_available' => $this->quantity_available,
            'quantity_reserved' => $this->quantity_reserved,
            'quantity_sold' => $this->quantity_sold,
            'quantity_returned' => $this->quantity_returned,
            'authenticity_attested_at' => $this->authenticity_attested_at?->toISOString(),
            'seller' => $this->relationLoaded('seller') && $this->seller ? [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'email' => $this->seller->email,
            ] : null,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

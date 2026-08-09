<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Organization;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::query()
            ->authoritativelyAccepted()
            ->orderBy('display_name')
            ->paginate(24);

        return response()->json(['status' => 'success', 'data' => $organizations]);
    }

    public function show(string $slug)
    {
        $organization = Organization::query()
            ->authoritativelyAccepted()
            ->where('slug', $slug)
            ->firstOrFail();
        $relationships = $organization->vendorRelationships()
            ->whereIn('status', ['verified', 'demo_accepted'])
            ->with('vendor:id,shop_name,slug,logo,status')
            ->get()
            ->filter(fn ($relationship) => $relationship->isCurrentlyVerified())
            ->values();

        $publishedBooks = Book::query()
            ->sellable()
            ->whereHas('activeCommercialParties', fn ($parties) => $parties->where('organization_id', $organization->id))
            ->latest()
            ->take(16)
            ->get()
            ->map(fn (Book $book) => [
                'id' => $book->id,
                'title' => $book->title,
                'slug' => $book->slug,
                'author' => $book->author,
                'cover_image' => $book->cover_image ? '/storage/'.$book->cover_image : null,
                'price' => (float) $book->price,
                'original_price' => $book->original_price ? (float) $book->original_price : null,
                'rating' => (float) $book->rating,
                'reviews_count' => (int) $book->reviews_count,
            ]);

        return response()->json(['status' => 'success', 'data' => [
            'id' => $organization->id,
            'display_name' => $organization->display_name,
            'legal_name' => $organization->legal_name,
            'slug' => $organization->slug,
            'organization_types' => $organization->organization_types,
            'description' => $organization->description,
            'logo' => $organization->logo ? '/storage/'.$organization->logo : null,
            'website' => $organization->website,
            'status' => $organization->status,
            'data_mode' => $organization->data_mode,
            'public_source_url' => $organization->public_source_url,
            'public_source_checked_at' => $organization->public_source_checked_at?->toDateString(),
            'verified_at' => $organization->verified_at?->toISOString(),
            'published_books' => $publishedBooks,
            'partner_shops' => $relationships->map(fn ($relationship) => [
                'role' => $relationship->role,
                'shop_name' => $relationship->vendor?->shop_name,
                'shop_slug' => $relationship->vendor?->slug,
                'shop_logo' => $relationship->vendor?->logo ? '/storage/'.$relationship->vendor->logo : null,
                'is_demo' => $relationship->is_demo,
            ]),
        ]]);
    }
}

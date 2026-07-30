<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::query()
            ->where('status', 'verified')
            ->orderBy('display_name')
            ->paginate(24);

        return response()->json(['status' => 'success', 'data' => $organizations]);
    }

    public function show(string $slug)
    {
        $organization = Organization::query()
            ->where('status', 'verified')
            ->where('slug', $slug)
            ->firstOrFail();
        $relationships = $organization->vendorRelationships()
            ->where('status', 'verified')
            ->with('vendor:id,shop_name,slug,logo,status')
            ->get()
            ->filter(fn ($relationship) => $relationship->isCurrentlyVerified())
            ->values();

        return response()->json(['status' => 'success', 'data' => [
            'id' => $organization->id,
            'display_name' => $organization->display_name,
            'slug' => $organization->slug,
            'organization_types' => $organization->organization_types,
            'description' => $organization->description,
            'logo' => $organization->logo ? '/storage/'.$organization->logo : null,
            'website' => $organization->website,
            'status' => $organization->status,
            'verified_at' => $organization->verified_at?->toISOString(),
            'partner_shops' => $relationships->map(fn ($relationship) => [
                'role' => $relationship->role,
                'shop_name' => $relationship->vendor?->shop_name,
                'shop_slug' => $relationship->vendor?->slug,
                'shop_logo' => $relationship->vendor?->logo ? '/storage/'.$relationship->vendor->logo : null,
            ]),
        ]]);
    }
}

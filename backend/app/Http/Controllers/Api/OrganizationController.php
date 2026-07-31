<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::query()
            ->whereIn('status', ['verified', 'demo_accepted'])
            ->orderBy('display_name')
            ->paginate(24);

        return response()->json(['status' => 'success', 'data' => $organizations]);
    }

    public function show(string $slug)
    {
        $organization = Organization::query()
            ->whereIn('status', ['verified', 'demo_accepted'])
            ->where('slug', $slug)
            ->firstOrFail();
        $relationships = $organization->vendorRelationships()
            ->whereIn('status', ['verified', 'demo_accepted'])
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
            'data_mode' => $organization->data_mode,
            'public_source_url' => $organization->public_source_url,
            'public_source_checked_at' => $organization->public_source_checked_at?->toDateString(),
            'verified_at' => $organization->verified_at?->toISOString(),
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

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Luôn trả về các trường cơ bản của user.
     * Nếu user có role 'vendor', load thêm vendor_profile.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vendorStatus = $this->vendor?->onboarding_status instanceof \BackedEnum
            ? $this->vendor->onboarding_status->value
            : $this->vendor?->onboarding_status;
        $activeVendor = $this->vendor?->isActive() ?? false;
        $activeWarehouseManager = $this->warehouseManagerAssignments
            ? $this->warehouseManagerAssignments->contains(fn ($assignment) => $assignment->isActive())
            : false;
        $activeUsedBookSeller = $this->usedBookSellerProfile?->isActive() ?? false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'birthday' => $this->birthday,
            'address' => $this->address,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role,
            'points' => $this->points ?? 0,
            'created_at' => $this->created_at?->toISOString(),
            'marketing_consent' => $this->marketing_consent_at !== null && $this->marketing_opt_out_at === null,
            'capabilities' => [
                'vendor_profile' => $this->vendor !== null,
                'active_vendor' => $activeVendor,
                'warehouse_manager' => $activeWarehouseManager,
                'used_book_seller' => $activeUsedBookSeller,
                'review_partner_onboarding' => $this->role === 'admin',
            ],

            'favorite_categories' => $this->favoriteCategories ? $this->favoriteCategories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'icon' => $cat->icon,
            ]) : [],

            'membership_tier' => $this->membershipTier ? [
                'id' => $this->membershipTier->id,
                'name' => $this->membershipTier->name,
                'discount_percent' => $this->membershipTier->discount_percent,
                'benefits' => $this->membershipTier->benefits,
            ] : null,

            'vendor_profile' => $this->vendor ? [
                'id' => $this->vendor->id,
                'shop_name' => $this->vendor->shop_name,
                'slug' => $this->vendor->slug,
                'logo' => $this->vendor->logo ? '/storage/'.$this->vendor->logo : null,
                'description' => $this->vendor->description,
                'status' => $this->vendor->status,
                'onboarding_status' => $vendorStatus,
            ] : null,
        ];
    }
}

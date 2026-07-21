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
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'gender'     => $this->gender,
            'birthday'   => $this->birthday,
            'google_id'  => $this->google_id,
            'address'    => $this->address,
            'avatar'     => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'role'       => $this->role,
            'points'     => $this->points ?? 0,
            'created_at' => $this->created_at?->toISOString(),

            'membership_tier' => $this->membershipTier ? [
                'id' => $this->membershipTier->id,
                'name' => $this->membershipTier->name,
                'discount_percent' => $this->membershipTier->discount_percent,
                'benefits' => $this->membershipTier->benefits,
            ] : null,

            'author_profile' => $this->author ? [
                'id' => $this->author->id,
                'pen_name' => $this->author->pen_name,
                'bio' => $this->author->bio,
                'status' => $this->author->status,
            ] : null,

            // Chỉ include vendor_profile khi user là vendor
            // whenLoaded() đảm bảo không gây thêm query N+1 nếu chưa eager-load
            'vendor_profile' => $this->when(
                $this->role === 'vendor' || $this->role === 'author',
                fn () => $this->whenLoaded('vendor', fn () => [
                    'shop_name'   => $this->vendor->shop_name,
                    'slug'        => $this->vendor->slug,
                    'logo'        => $this->vendor->logo ? '/storage/' . $this->vendor->logo : null,
                    'description' => $this->vendor->description,
                    'status'      => $this->vendor->status,
                ])
            ),
        ];
    }
}

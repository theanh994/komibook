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
            'address'    => $this->address,
            'role'       => $this->role,
            'created_at' => $this->created_at?->toISOString(),

            // Chỉ include vendor_profile khi user là vendor
            // whenLoaded() đảm bảo không gây thêm query N+1 nếu chưa eager-load
            'vendor_profile' => $this->when(
                $this->role === 'vendor',
                fn () => $this->whenLoaded('vendor', fn () => [
                    'shop_name'   => $this->vendor->shop_name,
                    'slug'        => $this->vendor->slug,
                    'logo'        => $this->vendor->logo,
                    'description' => $this->vendor->description,
                    'status'      => $this->vendor->status,
                ])
            ),
        ];
    }
}

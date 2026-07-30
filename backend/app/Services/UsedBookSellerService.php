<?php

namespace App\Services;

use App\Models\UsedBookSellerProfile;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsedBookSellerService
{
    public function profileFor(User $user): UsedBookSellerProfile
    {
        return DB::transaction(function () use ($user) {
            $profile = UsedBookSellerProfile::where('user_id', $user->id)->lockForUpdate()->first();
            if ($profile) {
                abort_unless($profile->isActive(), 403, 'Quyền Người bán sách cũ đang bị tạm ngưng.');

                return $profile;
            }

            $vendor = Vendor::withoutGlobalScopes()->where('user_id', $user->id)->first();
            if (! $vendor) {
                $vendor = Vendor::withoutGlobalScopes()->create([
                    'user_id' => $user->id,
                    'shop_name' => 'Góc sách cũ của '.$user->name,
                    'slug' => 'used-book-seller-'.$user->id.'-'.Str::lower(Str::random(5)),
                    'description' => 'Gian catalog giới hạn cho các listing sách cũ.',
                    'status' => 'active',
                    'onboarding_status' => 'approved',
                    'business_model' => 'bookstore',
                ]);
            }

            return UsedBookSellerProfile::create([
                'user_id' => $user->id,
                'catalog_vendor_id' => $vendor->id,
                'status' => 'active',
                'capabilities' => ['used_resale'],
                'activated_at' => now(),
            ]);
        });
    }
}

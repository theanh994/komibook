<?php

namespace App\Services;

use App\Models\SellerFulfillmentAddress;
use App\Models\UsedBookSellerProfile;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsedBookSellerService
{
    /** Read path: never provisions a vendor, profile, or warehouse. */
    public function readProfileFor(User $user): UsedBookSellerProfile
    {
        $profile = UsedBookSellerProfile::where('user_id', $user->id)->first();
        abort_unless($profile && $profile->isActive(), 403, 'Used-book seller profile is not active.');

        return $profile;
    }

    /** Explicit mutation-only seller-profile provisioning. */
    public function profileFor(User $user): UsedBookSellerProfile
    {
        return DB::transaction(function () use ($user) {
            $profile = UsedBookSellerProfile::where('user_id', $user->id)->lockForUpdate()->first();
            if ($profile) {
                abort_unless($profile->isActive(), 403, 'Used-book seller profile is not active.');

                return $profile;
            }
            $vendor = Vendor::withoutGlobalScopes()->where('user_id', $user->id)->first();
            if (! $vendor) {
                $vendor = Vendor::withoutGlobalScopes()->create([
                    'user_id' => $user->id,
                    'shop_name' => 'Used books - '.$user->name,
                    'slug' => 'used-book-seller-'.$user->id.'-'.Str::lower(Str::random(5)),
                    'description' => 'Restricted catalog for used-book listings.',
                    'status' => 'active', 'onboarding_status' => 'approved', 'business_model' => 'bookstore',
                ]);
            }

            return UsedBookSellerProfile::create([
                'user_id' => $user->id, 'catalog_vendor_id' => $vendor->id, 'status' => 'active',
                'capabilities' => ['used_resale'], 'activated_at' => now(),
            ]);
        });
    }

    /** Explicit listing mutation only; no placeholder address is ever created. */
    public function ensureVendorWarehouse(Vendor $vendor, SellerFulfillmentAddress|User $address): Warehouse
    {
        if ($address instanceof User) {
            $address = SellerFulfillmentAddress::where('user_id', $address->id)->where('status', 'verified')->whereNull('retired_at')->latest('verified_at')->first();
        }
        abort_unless($address && $address->status === 'verified' && ! $address->retired_at, 422, 'A verified fulfillment address is required.');
        if ($vendor->primary_warehouse_id) {
            $warehouse = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->find($vendor->primary_warehouse_id);
            abort_unless($warehouse && in_array($warehouse->status, ['active', 'Hoạt động'], true), 422, 'Primary warehouse is invalid.');

            return $warehouse;
        }
        $warehouses = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)
            ->whereIn('status', ['active', 'Hoạt động'])->orderBy('id')->get();
        abort_if($warehouses->count() > 1, 422, 'Multiple usable warehouses require an explicit primary warehouse.');
        $warehouse = $warehouses->first();
        if (! $warehouse) {
            $warehouse = Warehouse::withoutGlobalScopes()->create([
                'vendor_id' => $vendor->id, 'name' => 'Used books - '.$address->recipient_name,
                'address' => $address->address_line, 'province' => $address->province,
                'district' => $address->district, 'status' => 'active',
            ]);
        }
        $vendor->update(['primary_warehouse_id' => $warehouse->id]);

        return $warehouse;
    }
}

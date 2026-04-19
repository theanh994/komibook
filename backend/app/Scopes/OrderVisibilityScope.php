<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * OrderVisibilityScope — Global Scope áp dụng vào tất cả query của Model Order.
 *
 * Logic hiển thị:
 *  - Guest (chưa đăng nhập) : không thấy đơn hàng nào
 *  - Customer               : chỉ thấy đơn hàng của chính mình (WHERE user_id = ?)
 *  - Vendor                 : chỉ thấy đơn hàng thuộc gian hàng của mình (WHERE vendor_id = ?)
 *  - Admin                  : thấy tất cả đơn hàng (bypass scope)
 *
 * Để bypass scope trong admin controller:
 *   Order::withoutGlobalScope(OrderVisibilityScope::class)->get();
 */
class OrderVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Guest: không được xem đơn hàng
        if (! Auth::check()) {
            $builder->whereRaw('0 = 1');
            return;
        }

        $user = Auth::user();

        if ($user->isAdmin()) {
            // Admin thấy tất cả — không thêm điều kiện
            return;
        }

        if ($user->isVendor()) {
            // Vendor chỉ thấy đơn hàng thuộc gian hàng mình
            $vendor = $user->vendor;

            if ($vendor) {
                $builder->where('vendor_id', $vendor->id);
            } else {
                // Chưa có hồ sơ vendor → không thấy gì
                $builder->whereRaw('0 = 1');
            }
            return;
        }

        // Customer: chỉ thấy đơn của chính mình
        $builder->where('user_id', $user->id);
    }
}

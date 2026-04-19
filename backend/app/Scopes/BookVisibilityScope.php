<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * BookVisibilityScope — Global Scope áp dụng vào tất cả query của Model Book.
 *
 * Logic hiển thị:
 *  - Guest (chưa đăng nhập)  : chỉ thấy sách 'published'
 *  - Customer                 : chỉ thấy sách 'published'
 *  - Vendor                   : thấy toàn bộ sách của gian hàng mình (mọi status)
 *  - Admin                    : thấy tất cả sách (bypass scope)
 *
 * Để bypass scope trong controller admin, dùng:
 *   Book::withoutGlobalScope(BookVisibilityScope::class)->get();
 */
class BookVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Guest: chỉ thấy published
        if (! Auth::check()) {
            $builder->where('status', 'published');
            return;
        }

        $user = Auth::user();

        if ($user->isAdmin()) {
            // Admin thấy toàn bộ — không thêm điều kiện gì
            return;
        }

        if ($user->isVendor()) {
            // Vendor chỉ thấy sách thuộc gian hàng của mình
            $vendor = $user->vendor;

            if ($vendor) {
                $builder->where('vendor_id', $vendor->id);
            } else {
                // Tài khoản vendor nhưng chưa có hồ sơ gian hàng → không hiện sách nào
                $builder->whereRaw('0 = 1');
            }
            return;
        }

        // Customer (và mọi role khác): chỉ thấy published
        $builder->where('status', 'published');
    }
}

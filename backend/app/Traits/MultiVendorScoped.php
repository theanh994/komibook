<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait MultiVendorScoped
{
    /**
     * Boot the trait.
     */
    protected static function bootMultiVendorScoped(): void
    {
        // 1. Áp dụng Global Scope
        static::addGlobalScope('vendor_scope', function (Builder $builder) {
            if (Auth::check() && Auth::user()->role === 'vendor' && Auth::user()->vendor) {
                $model = new static;
                $table = $model->getTable();
                $vendorId = Auth::user()->vendor->id;

                // Đối với Vendor, kiểm tra theo cột id. Ngược lại dùng cột vendor_id
                if ($table === 'vendors') {
                    $builder->where($table . '.id', $vendorId);
                } else {
                    $builder->where($table . '.vendor_id', $vendorId);
                }
            }
        });

        // 2. Tự động gán vendor_id khi thêm mới
        static::creating(function (Model $model) {
            if (Auth::check() && Auth::user()->role === 'vendor' && Auth::user()->vendor) {
                // Không gán vendor_id nếu model là Vendor
                if ($model->getTable() !== 'vendors') {
                    // Chỉ gán nếu chưa được set sẵn
                    if (empty($model->vendor_id)) {
                        $model->vendor_id = Auth::user()->vendor->id;
                    }
                }
            }
        });
    }
}

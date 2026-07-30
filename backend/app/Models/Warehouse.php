<?php

namespace App\Models;

use App\Traits\MultiVendorScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory, MultiVendorScoped;

    protected $fillable = [
        'vendor_id',
        'name',
        'address',
        'capacity',
        'status',
    ];

    /**
     * Mỗi kho thuộc về một Vendor.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Tồn kho sách trong kho này.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function managerAssignments(): HasMany
    {
        return $this->hasMany(WarehouseManagerAssignment::class);
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(WarehouseStockLedger::class);
    }
}

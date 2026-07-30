<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsedBookSellerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'catalog_vendor_id',
        'status',
        'capabilities',
        'activated_at',
        'suspended_at',
        'last_reason',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function catalogVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'catalog_vendor_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

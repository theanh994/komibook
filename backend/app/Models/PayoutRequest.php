<?php

namespace App\Models;

use App\Traits\MultiVendorScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRequest extends Model
{
    use HasFactory, MultiVendorScoped;

    protected $fillable = [
        'vendor_id',
        'amount',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    /**
     * Thuộc về Vendor.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}

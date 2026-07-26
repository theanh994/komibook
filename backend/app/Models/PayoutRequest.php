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
        'operation_key',
        'amount',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'notes',
        'reviewed_by', 'reviewed_at', 'review_reason', 'processing_at', 'completed_at', 'rejected_at',
        'transfer_reference', 'transfer_evidence',
    ];

    protected $casts = [
        'amount' => 'integer', 'reviewed_at' => 'datetime', 'processing_at' => 'datetime',
        'completed_at' => 'datetime', 'rejected_at' => 'datetime',
    ];

    /**
     * Thuộc về Vendor.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}

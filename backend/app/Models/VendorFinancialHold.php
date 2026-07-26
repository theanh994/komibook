<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorFinancialHold extends Model
{
    protected $fillable = [
        'vendor_id',
        'return_request_id',
        'operation_key',
        'amount',
        'currency',
        'status',
        'released_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'released_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}

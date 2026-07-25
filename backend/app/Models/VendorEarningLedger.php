<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorEarningLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'order_id',
        'operation_key',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'currency',
    ];

    protected $casts = [
        'gross_amount' => 'integer',
        'commission_amount' => 'integer',
        'net_amount' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

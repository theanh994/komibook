<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnRequest extends Model
{
    protected $fillable = [
        'code',
        'order_id',
        'user_id',
        'vendor_id',
        'status',
        'currency',
        'refund_amount',
        'reason',
        'resolution_reason',
        'requested_at',
        'approved_at',
        'item_received_at',
        'refund_started_at',
        'refunded_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'integer',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'item_received_at' => 'datetime',
            'refund_started_at' => 'datetime',
            'refunded_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(ReturnRequestTransition::class);
    }

    public function refundTransaction(): HasOne
    {
        return $this->hasOne(RefundTransaction::class);
    }
}

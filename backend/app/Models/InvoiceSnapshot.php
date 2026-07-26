<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InvoiceSnapshot extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Invoice snapshots are immutable.'));
        static::deleting(fn () => throw new LogicException('Invoice snapshots are immutable.'));
    }

    protected $fillable = [
        'order_id',
        'invoice_number',
        'currency',
        'issued_at',
        'buyer_snapshot',
        'seller_snapshot',
        'line_items',
        'subtotal_amount',
        'coupon_discount_amount',
        'membership_discount_amount',
        'shipping_fee_amount',
        'service_fee_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'buyer_snapshot' => 'array',
            'seller_snapshot' => 'array',
            'line_items' => 'array',
            'subtotal_amount' => 'integer',
            'coupon_discount_amount' => 'integer',
            'membership_discount_amount' => 'integer',
            'shipping_fee_amount' => 'integer',
            'service_fee_amount' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

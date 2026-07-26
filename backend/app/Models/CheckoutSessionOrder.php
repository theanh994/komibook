<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSessionOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'checkout_session_id',
        'order_id',
        'vendor_id',
        'commerce_fee_schedule_id',
        'subtotal_amount',
        'discount_amount',
        'fee_amount',
        'service_fee_rate',
        'commission_rate',
        'commission_amount',
        'total_amount',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'fee_amount' => 'integer',
            'service_fee_rate' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Checkout session tổng hợp.
     */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    /**
     * Đơn hàng tương ứng.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Vendor tương ứng.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}

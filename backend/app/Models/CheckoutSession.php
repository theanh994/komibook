<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CheckoutSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'checkout_code',
        'user_id',
        'currency',
        'subtotal_amount',
        'discount_amount',
        'fee_amount',
        'total_amount',
        'expires_at',
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
            'total_amount' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (CheckoutSession $session) {
            if (empty($session->checkout_code)) {
                $session->checkout_code = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Người mua gắn liền với checkout session này.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Các order snapshot gắn liền với checkout session này.
     */
    public function checkoutSessionOrders(): HasMany
    {
        return $this->hasMany(CheckoutSessionOrder::class);
    }

    /**
     * Các giao dịch thanh toán gắn liền với checkout session này.
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Các bản ghi giữ chỗ tồn kho gắn liền với checkout session này.
     */
    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }
}

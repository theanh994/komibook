<?php

namespace App\Models;

use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'checkout_session_id',
        'provider',
        'provider_reference',
        'provider_transaction_id',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'request_payload',
        'response_payload',
        'provider_occurred_at',
        'paid_at',
        'failed_at',
        'expires_at',
        'refund_started_at',
        'refunded_at',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentTransactionStatus::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
            'provider_occurred_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
            'refund_started_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Checkout session của giao dịch này.
     */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }
}

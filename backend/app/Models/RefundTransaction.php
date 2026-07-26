<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefundTransaction extends Model
{
    protected $fillable = [
        'return_request_id',
        'payment_transaction_id',
        'provider',
        'idempotency_key',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'evidence',
        'failure_reason',
        'processing_at',
        'refunded_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processing_at' => 'datetime',
            'refunded_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(RefundTransactionAttempt::class);
    }
}

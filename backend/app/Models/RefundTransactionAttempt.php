<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundTransactionAttempt extends Model
{
    protected $fillable = [
        'refund_transaction_id',
        'operation_key',
        'attempt_number',
        'status',
        'request_payload',
        'response_payload',
        'failure_reason',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    public function refundTransaction(): BelongsTo
    {
        return $this->belongsTo(RefundTransaction::class);
    }
}

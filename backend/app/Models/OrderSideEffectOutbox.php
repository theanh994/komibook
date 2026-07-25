<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderSideEffectOutbox extends Model
{
    use HasFactory;

    protected $table = 'order_side_effect_outboxes';

    protected $fillable = [
        'order_id',
        'operation_key',
        'effect_type',
        'payload',
        'status',
        'attempt_count',
        'available_at',
        'locked_at',
        'processed_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt_count' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Order relationship.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

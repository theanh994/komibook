<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CommerceFeeSchedule extends Model
{
    protected $fillable = ['commission_rate', 'service_fee_rate', 'effective_at', 'actor_id', 'reason'];

    protected function casts(): array
    {
        return ['commission_rate' => 'decimal:2', 'service_fee_rate' => 'decimal:2', 'effective_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Fee schedules are immutable.'));
        static::deleting(fn () => throw new LogicException('Fee schedules are immutable.'));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CommerceFeeScheduleEvent extends Model
{
    protected $fillable = ['commerce_fee_schedule_id', 'actor_id', 'action', 'snapshot', 'operation_key'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Fee schedule events are append-only.'));
        static::deleting(fn () => throw new LogicException('Fee schedule events are append-only.'));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class FlashSaleEvent extends Model
{
    protected $fillable = ['flash_sale_id', 'flash_sale_book_id', 'actor_id', 'action', 'from_status', 'to_status', 'reason', 'snapshot', 'operation_key'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Flash sale events are append-only.'));
        static::deleting(fn () => throw new LogicException('Flash sale events are append-only.'));
    }
}

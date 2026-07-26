<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class BookPublishingEvent extends Model
{
    protected $fillable = ['book_id', 'actor_id', 'from_status', 'to_status', 'reason', 'operation_key', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Publishing events are append-only.'));
        static::deleting(fn () => throw new LogicException('Publishing events are append-only.'));
    }
}

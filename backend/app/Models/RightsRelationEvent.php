<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RightsRelationEvent extends Model
{
    use HasFactory;

    protected $fillable = ['subject_type', 'subject_id', 'actor_id', 'action', 'reason', 'operation_key', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Rights relation events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Rights relation events are append-only.'));
    }
}

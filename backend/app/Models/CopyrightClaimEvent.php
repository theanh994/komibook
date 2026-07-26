<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CopyrightClaimEvent extends Model
{
    use HasFactory;

    protected $fillable = ['copyright_claim_id', 'actor_id', 'from_status', 'to_status', 'reason', 'operation_key', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Copyright claim events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Copyright claim events are append-only.'));
    }
}

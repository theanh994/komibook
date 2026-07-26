<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class RoyaltyAgreementAcceptance extends Model
{
    protected $fillable = ['royalty_agreement_id', 'author_id', 'accepted_by', 'accepted_at', 'operation_key'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Royalty acceptances are append-only.'));
        static::deleting(fn () => throw new LogicException('Royalty acceptances are append-only.'));
    }
}

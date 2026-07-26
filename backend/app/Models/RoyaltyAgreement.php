<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class RoyaltyAgreement extends Model
{
    protected $fillable = ['book_id', 'version', 'shares', 'proposed_by', 'proposed_at', 'operation_key'];

    protected function casts(): array
    {
        return ['shares' => 'array', 'proposed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Royalty agreements are versioned and immutable.'));
        static::deleting(fn () => throw new LogicException('Royalty agreements are versioned and immutable.'));
    }

    public function acceptances()
    {
        return $this->hasMany(RoyaltyAgreementAcceptance::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class RoyaltyLedgerEntry extends Model
{
    protected $fillable = ['book_id', 'royalty_agreement_id', 'author_id', 'order_item_id', 'gross_amount', 'share_percent', 'royalty_amount', 'operation_key', 'earned_at'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Royalty ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Royalty ledger entries are immutable.'));
    }
}

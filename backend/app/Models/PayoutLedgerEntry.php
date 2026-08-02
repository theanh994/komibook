<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutLedgerEntry extends Model
{
    protected $fillable = ['payout_request_id', 'vendor_id', 'user_id', 'demo_wallet_account_id', 'actor_id', 'entry_type', 'amount', 'balance_before', 'balance_after', 'operation_key', 'metadata'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_before' => 'integer', 'balance_after' => 'integer', 'metadata' => 'array'];
    }
}

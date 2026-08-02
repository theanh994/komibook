<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoWalletLedgerEntry extends Model
{
    protected $fillable = [
        'demo_wallet_account_id', 'payment_transaction_id', 'order_id', 'vendor_id',
        'payout_request_id', 'return_request_id', 'entry_type', 'amount',
        'balance_before', 'balance_after', 'operation_key', 'metadata',
    ];

    protected $casts = [
        'amount' => 'integer', 'balance_before' => 'integer', 'balance_after' => 'integer',
        'metadata' => 'array',
    ];
}

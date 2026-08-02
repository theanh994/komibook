<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoWalletAccount extends Model
{
    protected $fillable = ['user_id', 'balance', 'reserved_balance', 'currency', 'status'];

    protected $casts = ['balance' => 'integer', 'reserved_balance' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DemoWalletLedgerEntry::class);
    }
}

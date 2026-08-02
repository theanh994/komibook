<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletPayoutAccount extends Model
{
    protected $fillable = [
        'user_id', 'bank_name', 'account_number', 'account_name', 'status',
        'verified_by', 'verified_at', 'review_reason',
    ];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use App\Traits\MultiVendorScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayoutRequest extends Model
{
    use HasFactory, MultiVendorScoped;

    protected $fillable = [
        'vendor_id', 'user_id', 'wallet_payout_account_id',
        'operation_key',
        'amount',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'notes',
        'reviewed_by', 'reviewed_at', 'review_reason', 'processing_at', 'completed_at', 'rejected_at',
        'transfer_reference', 'transfer_evidence',
    ];

    protected $casts = [
        'amount' => 'integer', 'reviewed_at' => 'datetime', 'processing_at' => 'datetime',
        'completed_at' => 'datetime', 'rejected_at' => 'datetime',
    ];

    /**
     * Thuộc về Vendor.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletPayoutAccount(): BelongsTo
    {
        return $this->belongsTo(WalletPayoutAccount::class);
    }

    public function walletEntries(): HasMany
    {
        return $this->hasMany(DemoWalletLedgerEntry::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(PayoutLedgerEntry::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(PayoutTransition::class);
    }

    public function latestTransition(): HasOne
    {
        return $this->hasOne(PayoutTransition::class)->latestOfMany();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerFulfillmentAddress extends Model
{
    protected $fillable = [
        'user_id',
        'recipient_name',
        'phone',
        'address_line',
        'ward',
        'district',
        'province',
        'postal_code',
        'status',
        'verified_at',
        'verified_by',
        'retired_at',
    ];

    protected $hidden = ['phone', 'address_line'];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'address_line' => 'encrypted',
            'verified_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function formattedAddress(): string
    {
        return collect([
            $this->address_line,
            $this->ward,
            $this->district,
            $this->province,
            $this->postal_code,
        ])->filter()->implode(', ');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseManagerAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_id',
        'warehouse_id',
        'invited_by',
        'capabilities',
        'status',
        'invitation_token_hash',
        'invited_at',
        'accepted_at',
        'suspended_at',
        'revoked_at',
        'expires_at',
        'last_reason',
    ];

    protected $hidden = ['invitation_token_hash'];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(WarehouseAssignmentEvent::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function can(string $capability): bool
    {
        return $this->isActive() && in_array($capability, $this->capabilities ?? [], true);
    }
}

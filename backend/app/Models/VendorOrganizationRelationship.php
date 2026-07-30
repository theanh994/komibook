<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorOrganizationRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'organization_id',
        'role',
        'status',
        'scope',
        'evidence_document',
        'effective_from',
        'effective_until',
        'reviewed_by',
        'submitted_at',
        'verified_at',
        'revoked_at',
        'last_review_reason',
        'operation_key',
    ];

    protected $hidden = ['evidence_document'];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrganizationRelationshipEvent::class);
    }

    public function isCurrentlyVerified(): bool
    {
        return $this->status === 'verified'
            && $this->verified_at !== null
            && $this->revoked_at === null
            && ($this->effective_from === null || $this->effective_from->isPast())
            && ($this->effective_until === null || $this->effective_until->endOfDay()->isFuture());
    }
}

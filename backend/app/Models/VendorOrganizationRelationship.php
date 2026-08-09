<?php

namespace App\Models;

use App\Support\AuthorityReviewFingerprint;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VendorOrganizationRelationship extends Model
{
    use HasFactory;

    protected $attributes = ['is_demo' => false, 'evidence_mode' => 'real_document'];

    protected static function booted(): void
    {
        static::saving(fn (self $model) => $model->authority_fingerprint = AuthorityReviewFingerprint::relationship($model));
    }

    protected $fillable = [
        'vendor_id',
        'organization_id',
        'role',
        'status',
        'is_demo',
        'evidence_mode',
        'scope',
        'evidence_document',
        'demo_reference',
        'effective_from',
        'effective_until',
        'reviewed_by',
        'submitted_at',
        'verified_at',
        'revoked_at',
        'last_review_reason',
        'operation_key',
        'authority_fingerprint',
    ];

    protected $hidden = ['evidence_document'];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'is_demo' => 'boolean',
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

    public function latestEvent(): HasOne
    {
        return $this->hasOne(OrganizationRelationshipEvent::class)->latestOfMany();
    }

    public function isCurrentlyVerified(): bool
    {
        $isLive = $this->status === 'verified'
            && ! $this->is_demo
            && $this->evidence_mode === 'real_document'
            && filled($this->evidence_document)
            && blank($this->demo_reference)
            && $this->submitted_at !== null
            && $this->verified_at !== null
            && $this->reviewed_by !== null
            && filled($this->last_review_reason)
            && $this->organization?->isVerified()
            && ($event = $this->latestEvent()->with('actor')->first()) !== null
            && $event->to_status === 'verified'
            && $event->actor_id === $this->reviewed_by
            && $this->authority_fingerprint === AuthorityReviewFingerprint::relationship($this)
            && $event->reason === $this->last_review_reason
            && $event->reviewed_fingerprint === $this->authority_fingerprint
            && $event->actor?->role === 'admin';
        $isDemo = $this->status === 'demo_accepted'
            && $this->is_demo
            && $this->evidence_mode === 'demo_statement'
            && filled($this->demo_reference)
            && blank($this->evidence_document)
            && $this->verified_at === null
            && $this->reviewed_by !== null
            && filled($this->last_review_reason)
            && $this->organization?->data_mode === 'demo'
            && $this->organization?->hasAuthoritativeAcceptance()
            && ($event = $this->latestEvent()->with('actor')->first()) !== null
            && $event->to_status === 'demo_accepted'
            && $event->actor_id === $this->reviewed_by
            && $this->authority_fingerprint === AuthorityReviewFingerprint::relationship($this)
            && $event->reason === $this->last_review_reason
            && $event->reviewed_fingerprint === $this->authority_fingerprint
            && $event->actor?->role === 'admin';

        return ($isLive || $isDemo)
            && $this->revoked_at === null
            && ($this->effective_from === null || $this->effective_from->isPast())
            && ($this->effective_until === null || $this->effective_until->endOfDay()->isFuture());
    }
}

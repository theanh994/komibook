<?php

namespace App\Models;

use App\Support\AuthorityReviewFingerprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory;

    protected $attributes = ['data_mode' => 'real'];

    protected static function booted(): void
    {
        static::saving(fn (self $model) => $model->authority_fingerprint = AuthorityReviewFingerprint::organization($model));
    }

    protected $fillable = [
        'legal_name',
        'display_name',
        'slug',
        'organization_types',
        'tax_code',
        'license_number',
        'verification_document',
        'description',
        'logo',
        'website',
        'status',
        'data_mode',
        'public_source_url',
        'public_source_checked_at',
        'verified_by',
        'submitted_at',
        'verified_at',
        'suspended_at',
        'archived_at',
        'last_review_reason',
        'authority_fingerprint',
    ];

    protected $hidden = ['tax_code', 'license_number', 'verification_document'];

    protected function casts(): array
    {
        return [
            'organization_types' => 'array',
            'public_source_checked_at' => 'date',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function vendorRelationships(): HasMany
    {
        return $this->hasMany(VendorOrganizationRelationship::class);
    }

    public function commercialParties(): HasMany
    {
        return $this->hasMany(BookCommercialParty::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function reviewEvents(): HasMany
    {
        return $this->hasMany(OrganizationReviewEvent::class);
    }

    public function latestReviewEvent(): HasOne
    {
        return $this->hasOne(OrganizationReviewEvent::class)->latestOfMany();
    }

    public function isOperationallyAccepted(): bool
    {
        return ! in_array($this->status, ['suspended', 'archived'], true)
            && ($this->status === 'verified' || ($this->data_mode === 'demo' && $this->status === 'demo_accepted'));
    }

    public function hasAuthoritativeAcceptance(): bool
    {
        return $this->exists
            && static::query()->whereKey($this->getKey())->authoritativelyAccepted()->exists();
    }

    /**
     * Only records whose current state is backed by the current admin review may
     * be used as an authoritative organization.  Keep this SQL predicate in
     * lockstep with hasAuthoritativeAcceptance(), which deliberately delegates
     * here so collection and single-record reads fail closed in the same way.
     */
    public function scopeAuthoritativelyAccepted(Builder $query): Builder
    {
        return $query
            ->whereNull('organizations.suspended_at')
            ->whereNull('organizations.archived_at')
            ->whereNotNull('organizations.verified_by')
            ->whereNotNull('organizations.last_review_reason')
            ->whereNotNull('organizations.authority_fingerprint')
            ->where(function (Builder $accepted): void {
                $accepted->where(function (Builder $live): void {
                    $live->where('organizations.status', 'verified')
                        ->where('organizations.data_mode', '!=', 'demo')
                        ->whereNotNull('organizations.verification_document')
                        ->whereNotNull('organizations.submitted_at')
                        ->whereNotNull('organizations.verified_at')
                        ->whereHas('latestReviewEvent', function (Builder $event): void {
                            $event->where('to_status', 'verified')
                                ->whereColumn('actor_id', 'organizations.verified_by')
                                ->whereColumn('reason', 'organizations.last_review_reason')
                                ->whereColumn('reviewed_fingerprint', 'organizations.authority_fingerprint')
                                ->whereHas('actor', fn (Builder $actor) => $actor->where('role', 'admin'));
                        });
                })->orWhere(function (Builder $demo): void {
                    $demo->where('organizations.data_mode', 'demo')
                        ->where('organizations.status', 'demo_accepted')
                        ->whereNull('organizations.verification_document')
                        ->whereNull('organizations.verified_at')
                        ->whereHas('latestReviewEvent', function (Builder $event): void {
                            $event->where('to_status', 'demo_accepted')
                                ->whereColumn('actor_id', 'organizations.verified_by')
                                ->whereColumn('reason', 'organizations.last_review_reason')
                                ->whereColumn('reviewed_fingerprint', 'organizations.authority_fingerprint')
                                ->whereHas('actor', fn (Builder $actor) => $actor->where('role', 'admin'));
                        });
                });
            });
    }

    public function publishingAgreements(): HasMany
    {
        return $this->hasMany(OrganizationDistributionAgreement::class, 'publisher_organization_id');
    }

    public function distributionAgreements(): HasMany
    {
        return $this->hasMany(OrganizationDistributionAgreement::class, 'distributor_organization_id');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified' && $this->hasAuthoritativeAcceptance();
    }
}

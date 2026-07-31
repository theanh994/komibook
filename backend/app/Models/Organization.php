<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

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

    public function isOperationallyAccepted(): bool
    {
        return $this->status === 'verified'
            || ($this->data_mode === 'demo' && $this->status === 'demo_accepted');
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
        return $this->status === 'verified' && $this->verified_at !== null;
    }
}

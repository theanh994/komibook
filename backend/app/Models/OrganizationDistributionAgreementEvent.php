<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDistributionAgreementEvent extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->reviewed_fingerprint ??= OrganizationDistributionAgreement::find($event->organization_distribution_agreement_id)?->authority_fingerprint;
        });
    }

    use HasFactory;

    protected $fillable = [
        'organization_distribution_agreement_id', 'actor_id', 'from_status', 'to_status',
        'reason', 'operation_key', 'reviewed_fingerprint',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(OrganizationDistributionAgreement::class, 'organization_distribution_agreement_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

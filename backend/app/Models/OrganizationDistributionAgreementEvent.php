<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDistributionAgreementEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_distribution_agreement_id', 'actor_id', 'from_status', 'to_status',
        'reason', 'operation_key',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(OrganizationDistributionAgreement::class, 'organization_distribution_agreement_id');
    }
}

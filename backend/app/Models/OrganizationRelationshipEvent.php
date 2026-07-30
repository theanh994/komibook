<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationRelationshipEvent extends Model
{
    protected $fillable = [
        'vendor_organization_relationship_id',
        'actor_id',
        'from_status',
        'to_status',
        'reason',
        'operation_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(VendorOrganizationRelationship::class, 'vendor_organization_relationship_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookCommercialParty extends Model
{
    protected $fillable = [
        'book_id',
        'organization_id',
        'vendor_organization_relationship_id',
        'role',
        'status',
        'version',
        'active_slot',
        'effective_at',
        'verified_at',
        'ended_at',
        'verified_by',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'verified_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(VendorOrganizationRelationship::class, 'vendor_organization_relationship_id');
    }
}

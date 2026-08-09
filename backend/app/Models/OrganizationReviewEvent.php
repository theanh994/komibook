<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationReviewEvent extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->reviewed_fingerprint ??= Organization::find($event->organization_id)?->authority_fingerprint;
        });
    }

    protected $fillable = [
        'organization_id',
        'actor_id',
        'from_status',
        'to_status',
        'reason',
        'operation_key',
        'reviewed_fingerprint',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

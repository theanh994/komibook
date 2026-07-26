<?php

namespace App\Models;

use App\Enums\AuthorOnboardingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pen_name',
        'bio',
        'identity_document',
        'phone_verified_at',
        'bank_account_number',
        'bank_name',
        'bank_holder_name',
        'status',
        'rejection_reason',
        'onboarding_status',
        'application_version',
        'terms_accepted_at',
        'submitted_at',
        'review_started_at',
        'approved_at',
        'changes_requested_at',
        'rejected_at',
        'suspended_at',
        'revoked_at',
        'last_review_reason',
    ];

    protected $hidden = [
        'identity_document',
    ];

    protected $appends = [
        'has_identity_document',
        'identity_document_url',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'onboarding_status' => AuthorOnboardingStatus::class,
            'application_version' => 'integer',
            'terms_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'approved_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getHasIdentityDocumentAttribute(): bool
    {
        return ! empty($this->attributes['identity_document']);
    }

    public function getIdentityDocumentUrlAttribute(): ?string
    {
        if (empty($this->attributes['identity_document'])) {
            return null;
        }

        return "/api/authors/{$this->id}/identity-document";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function onboardingEvents(): HasMany
    {
        return $this->hasMany(AuthorOnboardingEvent::class);
    }

    public function bookRelations(): HasMany
    {
        return $this->hasMany(BookAuthor::class);
    }

    public function grantedDelegations(): HasMany
    {
        return $this->hasMany(AuthorDelegation::class, 'grantor_author_id');
    }
}

<?php

namespace App\Models;

use App\Enums\CopyrightClaimStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CopyrightClaim extends Model
{
    use HasFactory;

    protected $fillable = ['book_id', 'owner_author_id', 'registration_type', 'registration_number', 'rights_scope', 'territory_scope', 'valid_from', 'valid_until', 'evidence_document', 'status', 'application_version', 'submitted_at', 'review_started_at', 'verified_at', 'changes_requested_at', 'rejected_at', 'disputed_at', 'revoked_at', 'last_review_reason'];

    protected $hidden = ['evidence_document'];

    protected function casts(): array
    {
        return ['rights_scope' => 'array', 'territory_scope' => 'array', 'valid_from' => 'date', 'valid_until' => 'date', 'status' => CopyrightClaimStatus::class, 'submitted_at' => 'datetime', 'review_started_at' => 'datetime', 'verified_at' => 'datetime', 'changes_requested_at' => 'datetime', 'rejected_at' => 'datetime', 'disputed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function ownerAuthor(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'owner_author_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'copyright_claim_authors')->withPivot(['role', 'share_percent', 'accepted_at'])->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CopyrightClaimEvent::class);
    }
}

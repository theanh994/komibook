<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EbookVersion extends Model
{
    protected $fillable = ['book_id', 'version', 'file_path', 'chapter_snapshot', 'release_notes', 'published_by', 'published_at'];
    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return ['chapter_snapshot' => 'array', 'version' => 'integer', 'published_at' => 'datetime'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(EbookEntitlement::class, 'purchase_version_id');
    }
}

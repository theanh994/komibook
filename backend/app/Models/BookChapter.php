<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookChapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'title',
        'content',
        'order',
        'is_free',
        'status',
        'current_revision',
        'autosaved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'order' => 'integer',
            'current_revision' => 'integer',
            'autosaved_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function revisions()
    {
        return $this->hasMany(BookChapterRevision::class)->orderByDesc('revision');
    }
}

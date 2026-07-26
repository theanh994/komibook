<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class BookPublishedRevision extends Model
{
    protected $fillable = ['book_id', 'published_by', 'version', 'book_snapshot', 'chapter_snapshot', 'published_at'];

    protected function casts(): array
    {
        return ['book_snapshot' => 'array', 'chapter_snapshot' => 'array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Published revisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Published revisions are immutable.'));
    }
}

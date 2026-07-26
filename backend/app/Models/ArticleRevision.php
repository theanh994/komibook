<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ArticleRevision extends Model
{
    protected $fillable = ['article_id', 'actor_id', 'revision', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Article revisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Article revisions are immutable.'));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ArticleEvent extends Model
{
    protected $fillable = ['article_id', 'actor_id', 'from_status', 'to_status', 'reason', 'operation_key'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Article events are append-only.'));
        static::deleting(fn () => throw new LogicException('Article events are append-only.'));
    }
}

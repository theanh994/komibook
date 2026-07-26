<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookChapterRevision extends Model
{
    protected $fillable = ['book_chapter_id', 'actor_id', 'revision', 'title', 'content', 'is_free', 'source'];

    protected function casts(): array
    {
        return ['is_free' => 'boolean'];
    }
}

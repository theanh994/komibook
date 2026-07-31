<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleSubmission extends Model
{
    protected $fillable = ['user_id', 'book_id', 'converted_article_id', 'title', 'body', 'status', 'verified_purchase', 'word_count', 'moderated_by', 'moderation_reason', 'moderated_at'];

    protected $hidden = ['moderation_reason'];

    protected function casts(): array
    {
        return ['verified_purchase' => 'boolean', 'moderated_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}

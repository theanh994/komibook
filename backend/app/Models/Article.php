<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = ['author_id', 'article_category_id', 'title', 'slug', 'excerpt', 'body', 'cover_image', 'status', 'revision', 'home_featured', 'seo_title', 'seo_description', 'scheduled_at', 'published_at', 'review_reason'];

    protected $hidden = ['review_reason'];

    protected function casts(): array
    {
        return ['status' => ArticleStatus::class, 'home_featured' => 'boolean', 'scheduled_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function category()
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(ArticleTag::class, 'article_tag');
    }

    public function books()
    {
        return $this->belongsToMany(Book::class, 'article_book');
    }

    public function revisions()
    {
        return $this->hasMany(ArticleRevision::class);
    }

    public function events()
    {
        return $this->hasMany(ArticleEvent::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

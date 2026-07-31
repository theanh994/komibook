<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleMedia extends Model
{
    protected $fillable = ['article_id', 'uploaded_by', 'disk', 'path', 'mime_type', 'alt_text', 'size_bytes', 'width', 'height'];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}

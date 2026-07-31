<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleCommentEvent extends Model
{
    protected $fillable = ['article_comment_id', 'actor_id', 'from_status', 'to_status', 'reason', 'operation_key'];
}

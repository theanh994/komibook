<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleComment extends Model
{
    protected $fillable = ['article_id', 'user_id', 'parent_id', 'guest_name', 'guest_email_hash', 'body', 'status', 'moderated_by', 'moderation_reason', 'moderated_at'];

    protected $hidden = ['guest_email_hash', 'moderation_reason'];

    protected function casts(): array
    {
        return ['moderated_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'parent_id')->where('status', 'approved')->oldest();
    }
}

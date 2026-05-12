<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookAnnotation extends Model
{
    protected $fillable = [
        'user_id',
        'book_id',
        'chapter',
        'highlighted_text',
        'note_content',
        'type',
        'color',
        'page_number'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}

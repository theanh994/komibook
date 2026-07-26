<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingProgress extends Model
{
    protected $table = 'reading_progress';

    protected $fillable = [
        'user_id', 'book_id', 'purchase_order_id', 'book_chapter_id', 'location_key',
        'current_page', 'total_pages', 'progress_percent', 'version', 'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'current_page' => 'integer', 'total_pages' => 'integer',
            'progress_percent' => 'decimal:2', 'version' => 'integer', 'last_read_at' => 'datetime',
        ];
    }
}

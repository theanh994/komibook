<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_name',
        'title',
        'content',
        'views_count',
        'helpful_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'views_count' => 'integer',
            'helpful_count' => 'integer',
        ];
    }
}

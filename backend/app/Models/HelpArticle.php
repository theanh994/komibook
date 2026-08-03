<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    use HasFactory;

    public const HIDDEN_PUBLIC_CATEGORIES = [
        'Đối tác & Tác giả',
        'Đối tác & Nhà bán',
    ];

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

    public function scopePublicKnowledge(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotIn('category_name', self::HIDDEN_PUBLIC_CATEGORIES);
    }
}

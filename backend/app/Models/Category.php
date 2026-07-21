<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Danh mục cha (nếu có).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Các danh mục con trực tiếp.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Tất cả danh mục con (đệ quy, dùng eager loading).
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Các cuốn sách thuộc danh mục này (Many-to-Many).
     */
    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_category');
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }
}

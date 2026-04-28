<?php

namespace App\Models;

use App\Traits\MultiVendorScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory, MultiVendorScoped;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vendor_id',
        'category_id',
        'title',
        'slug',
        'author',
        'description',
        'cover_image',
        'isbn',
        'price',
        'sale_price',
        'stock',
        'type',
        'status',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price'      => 'integer',
            'sale_price' => 'integer',
            'stock'      => 'integer',
        ];
    }

    /**
     * Đăng ký logic khởi tạo khác (nếu có).
     */
    protected static function booted(): void
    {
        // ...
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Sách thuộc về một Vendor.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Sách thuộc về một Category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Đánh giá của sách.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isEbook(): bool
    {
        return $this->type === 'ebook';
    }

    /**
     * Trả về giá hiện tại (ưu tiên sale_price nếu có).
     */
    public function currentPrice(): int
    {
        return $this->sale_price ?? $this->price;
    }
}

<?php

namespace App\Models;

use App\Traits\MultiVendorScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'series_id',
        'title',
        'slug',
        'author',
        'translator',
        'description',
        'cover_image',
        'gallery_images',
        'dimensions',
        'cover_format',
        'weight',
        'language',
        'target_age',
        'pages',
        'release_date',
        'isbn',
        'price',
        'sale_price',
        'stock',
        'views',
        'type',
        'status',
        'file_path',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price'          => 'integer',
            'sale_price'     => 'integer',
            'stock'          => 'integer',
            'pages'          => 'integer',
            'views'          => 'integer',
            'gallery_images' => 'array',
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
     * Sách thuộc về nhiều Danh mục (Categories).
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'book_category');
    }

    /**
     * Danh sách lượt yêu thích.
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Sách thuộc về một Series (nullable).
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * Đánh giá của sách.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Danh sách các chương sách (E-book soạn thảo trực tuyến).
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(BookChapter::class)->orderBy('order', 'asc');
    }

    /**
     * Cài đặt DRM & bảo mật bản quyền.
     */
    public function drmSetting(): HasOne
    {
        return $this->hasOne(BookDrmSetting::class);
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

    protected static $activeFlashSaleBooks = null;

    protected static function getActiveFlashSaleItem($bookId)
    {
        if (self::$activeFlashSaleBooks === null) {
            $now = now();
            $activeSale = \App\Models\FlashSale::where('is_active', true)
                ->where('start_time', '<=', $now)
                ->where('end_time', '>', $now)
                ->first();

            if ($activeSale) {
                self::$activeFlashSaleBooks = $activeSale->items()->where('status', 'approved')->get()->keyBy('book_id');
            } else {
                self::$activeFlashSaleBooks = collect();
            }
        }

        return self::$activeFlashSaleBooks->get($bookId);
    }

    public function getSalePriceAttribute($value)
    {
        $activeFlashSaleItem = self::getActiveFlashSaleItem($this->id);
        if ($activeFlashSaleItem) {
            $discountAmount = $this->price * ($activeFlashSaleItem->discount_percent / 100);
            return max(0, (int)($this->price - $discountAmount));
        }

        return $value;
    }
}

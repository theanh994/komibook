<?php

namespace App\Models;

use App\Enums\BookPublicationStatus;
use App\Traits\MultiVendorScoped;
use Illuminate\Database\Eloquent\Builder;
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
        'publishing_status',
        'publication_version',
        'submitted_for_review_at',
        'approved_at',
        'scheduled_for',
        'published_at',
        'publication_feedback',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'sale_price' => 'integer',
            'stock' => 'integer',
            'pages' => 'integer',
            'views' => 'integer',
            'publication_version' => 'integer',
            'gallery_images' => 'array',
            'publishing_status' => BookPublicationStatus::class,
            'submitted_for_review_at' => 'datetime',
            'approved_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
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

    /**
     * Các bản ghi giữ chỗ tồn kho của sách này.
     */
    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    public function authorRelations(): HasMany
    {
        return $this->hasMany(BookAuthor::class);
    }

    public function copyrightClaims(): HasMany
    {
        return $this->hasMany(CopyrightClaim::class);
    }

    public function publishingEvents(): HasMany
    {
        return $this->hasMany(BookPublishingEvent::class);
    }

    public function publishedRevisions(): HasMany
    {
        return $this->hasMany(BookPublishedRevision::class);
    }

    public function royaltyAgreements(): HasMany
    {
        return $this->hasMany(RoyaltyAgreement::class);
    }

    /**
     * Tồn kho thực tế tại các kho của sách này.
     */
    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('books.status', 'published')
            ->where(fn (Builder $statusQuery) => $statusQuery->whereNull('books.publishing_status')->orWhere('books.publishing_status', BookPublicationStatus::Published->value))
            ->whereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->withoutGlobalScopes()->where('status', 'active'));
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
            $activeSale = FlashSale::where('is_active', true)
                ->where('status', 'active')
                ->where('start_time', '<=', $now)
                ->where('end_time', '>', $now)
                ->first();

            if ($activeSale) {
                self::$activeFlashSaleBooks = $activeSale->items()->where('status', 'approved')
                    ->where(function ($query) {
                        $query->where('max_quantity', 0)->orWhereColumn('sold_quantity', '<', 'max_quantity');
                    })
                    ->get()->keyBy('book_id');
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
            return (int) ($activeFlashSaleItem->sale_price ?? round($this->price * (100 - $activeFlashSaleItem->discount_percent) / 100));
        }

        return $value;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'book_id',
        'quantity',
        'price',
        'list_unit_price',
        'promotion_discount_amount',
        'flash_sale_book_id',
        'promotion_snapshot',
        'ebook_version_id',
        'product_taxonomy_snapshot',
        'commercial_parties_snapshot',
        'return_policy_snapshot',
        'ebook_consent_snapshot',
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
            'quantity' => 'integer',
            'list_unit_price' => 'integer',
            'promotion_discount_amount' => 'integer',
            'promotion_snapshot' => 'array',
            'product_taxonomy_snapshot' => 'array',
            'commercial_parties_snapshot' => 'array',
            'return_policy_snapshot' => 'array',
            'ebook_consent_snapshot' => 'array',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Đơn hàng chứa item này.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Sách được mua trong item này.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withoutGlobalScopes();
    }

    /**
     * Bản ghi giữ chỗ tồn kho cho item này (nếu có).
     */
    public function inventoryReservation(): HasOne
    {
        return $this->hasOne(InventoryReservation::class);
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    /**
     * Tổng tiền của dòng item này (price * quantity).
     */
    public function subtotal(): int
    {
        return $this->price * $this->quantity;
    }
}

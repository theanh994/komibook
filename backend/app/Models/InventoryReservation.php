<?php

namespace App\Models;

use App\Enums\InventoryReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReservation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'checkout_session_id',
        'order_item_id',
        'book_id',
        'quantity',
        'status',
        'operation_key',
        'expires_at',
        'committed_at',
        'released_at',
        'expired_at',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => InventoryReservationStatus::class,
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
            'released_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Checkout session liên quan.
     */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    /**
     * Item trong đơn hàng liên quan.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Sách được giữ chỗ.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withoutGlobalScopes();
    }

    /**
     * Chi tiết phân bổ kho hàng.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryReservationAllocation::class);
    }
}

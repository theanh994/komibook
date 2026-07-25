<?php

namespace App\Models;

use App\Traits\MultiVendorScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory, MultiVendorScoped;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_code',
        'user_id',
        'vendor_id',
        'total_amount',
        'status',
        'payment_status',
        'payment_method',
        'shipping_address',
        'phone',
        'shipping_carrier',
        'shipping_tracking_code',
        'shipping_status',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
        ];
    }

    /**
     * Đăng ký Global Scope — tự động áp dụng vào mọi query.
     */
    protected static function booted(): void
    {
        // Tự động sinh order_code khi tạo mới
        static::creating(function (Order $order) {
            if (empty($order->order_code)) {
                $order->order_code = self::generateOrderCode();
            }
        });
    }

    /**
     * Sinh order_code theo định dạng: ORD-YYYYMMDD-XXXXXX
     */
    public static function generateOrderCode(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "ORD-{$date}-{$random}";
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Người mua đơn hàng này.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Gian hàng xử lý đơn hàng này.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Danh sách sản phẩm trong đơn hàng.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Bản ghi liên kết checkout session của đơn hàng này.
     */
    public function checkoutSessionOrder(): HasOne
    {
        return $this->hasOne(CheckoutSessionOrder::class);
    }

    public function transitionOperations(): HasMany
    {
        return $this->hasMany(OrderTransitionOperation::class);
    }

    public function loyaltyPointLedger(): HasOne
    {
        return $this->hasOne(LoyaltyPointLedger::class);
    }

    public function vendorEarningLedger(): HasOne
    {
        return $this->hasOne(VendorEarningLedger::class);
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}

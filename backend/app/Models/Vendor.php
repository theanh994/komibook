<?php

namespace App\Models;

use App\Traits\MultiVendorScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory, MultiVendorScoped;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'shop_name',
        'slug',
        'description',
        'logo',
        'status',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Get the user that owns this vendor profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tất cả sách thuộc gian hàng này.
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * Tất cả sách (bao gồm draft/out_of_stock) — bypass Global Scope.
     * Dùng trong trang quản lý của vendor.
     */
    public function allBooks(): HasMany
    {
        return $this->hasMany(Book::class)->withoutGlobalScopes();
    }

    /**
     * Tất cả đơn hàng thuộc gian hàng này.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Tất cả đơn hàng — bypass Global Scope (dùng cho admin).
     */
    public function allOrders(): HasMany
    {
        return $this->hasMany(Order::class)->withoutGlobalScopes();
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

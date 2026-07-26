<?php

namespace App\Models;

use App\Enums\VendorOnboardingStatus;
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
        'rejection_reason',
        'onboarding_status',
        'application_version',
        'legal_name',
        'tax_code',
        'business_registration_document',
        'representative_identity_document',
        'payout_bank_account',
        'payout_bank_name',
        'payout_bank_holder',
        'terms_accepted_at',
        'submitted_at',
        'review_started_at',
        'approved_at',
        'changes_requested_at',
        'rejected_at',
        'suspended_at',
        'revoked_at',
        'last_review_reason',
    ];

    protected $hidden = [
        'business_registration_document',
        'representative_identity_document',
        'payout_bank_account',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_status' => VendorOnboardingStatus::class,
            'application_version' => 'integer',
            'terms_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'approved_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

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

    /**
     * Tất cả kho hàng của vendor.
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * Tất cả yêu cầu rút tiền của vendor.
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /**
     * Các phiếu kiểm kê kho của vendor.
     */
    public function inventoryAudits(): HasMany
    {
        return $this->hasMany(InventoryAudit::class);
    }

    /**
     * Các phiếu điều chuyển kho của vendor.
     */
    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }

    public function vendorEarningLedgers(): HasMany
    {
        return $this->hasMany(VendorEarningLedger::class);
    }

    public function onboardingEvents(): HasMany
    {
        return $this->hasMany(VendorOnboardingEvent::class);
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->onboarding_status === null || $this->onboarding_status === VendorOnboardingStatus::Approved);
    }
}

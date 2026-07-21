<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordQueued;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'points',
        'membership_tier_id',
        'phone',
        'gender',
        'birthday',
        'google_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Get the vendor profile associated with this user.
     */
    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    /**
     * Thông tin tác giả liên kết với tài khoản này.
     */
    public function author(): HasOne
    {
        return $this->hasOne(Author::class);
    }

    /**
     * Tất cả yêu cầu hỗ trợ của tài khoản này.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Hạng thành viên.
     */
    public function membershipTier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class);
    }

    /**
     * Tất cả đơn hàng của người dùng này.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Sổ địa chỉ.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    /**
     * Danh sách sách yêu thích.
     */
    public function wishlistBooks()
    {
        return $this->belongsToMany(Book::class, 'wishlists')->withTimestamps();
    }

    // ─── Role Helper Methods ──────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isVendor(): bool
    {
        return $this->role === 'vendor';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Gửi notification đặt lại mật khẩu qua Queue.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordQueued($token));
    }

    /**
     * Get the full URL for the avatar.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? '/storage/' . $this->avatar : null;
    }
}

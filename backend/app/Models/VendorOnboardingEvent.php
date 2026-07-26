<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorOnboardingEvent extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id', 'actor_id', 'from_status', 'to_status', 'reason', 'operation_key', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Vendor onboarding events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Vendor onboarding events are append-only.'));
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

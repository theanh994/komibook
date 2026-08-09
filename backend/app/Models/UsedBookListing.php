<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsedBookListing extends Model
{
    protected $fillable = ['book_id', 'warehouse_id', 'seller_user_id', 'seller_fulfillment_address_id', 'condition', 'actual_photos', 'defects', 'quantity_available', 'quantity_reserved', 'quantity_sold', 'quantity_returned', 'authenticity_attested_at', 'status', 'rejection_reason'];

    protected $hidden = ['seller_user_id', 'seller_fulfillment_address_id'];

    protected function casts(): array
    {
        return ['actual_photos' => 'array', 'quantity_available' => 'integer', 'quantity_reserved' => 'integer', 'quantity_sold' => 'integer', 'quantity_returned' => 'integer', 'authenticity_attested_at' => 'datetime'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $listing): void {
            if ($listing->isDirty('warehouse_id') && $listing->getOriginal('warehouse_id') !== null) {
                throw new \LogicException('Used-book warehouse binding is immutable.');
            }
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function fulfillmentAddress(): BelongsTo
    {
        return $this->belongsTo(SellerFulfillmentAddress::class, 'seller_fulfillment_address_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookEntitlement extends Model
{
    protected $fillable = ['user_id', 'book_id', 'order_item_id', 'purchase_version_id', 'activated_at', 'revoked_at', 'revocation_reason'];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function purchaseVersion(): BelongsTo
    {
        return $this->belongsTo(EbookVersion::class, 'purchase_version_id');
    }
}

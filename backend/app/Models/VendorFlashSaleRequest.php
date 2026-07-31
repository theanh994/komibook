<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorFlashSaleRequest extends Model
{
    protected $fillable = [
        'vendor_id', 'campaign_key', 'book_id', 'groups', 'title', 'preferred_start_time', 'preferred_end_time',
        'discount_percent', 'max_quantity', 'status', 'vendor_note',
        'decision_reason', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'preferred_start_time' => 'datetime',
            'preferred_end_time' => 'datetime',
            'groups' => 'array',
            'discount_percent' => 'float',
            'max_quantity' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}

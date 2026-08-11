<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'code',
        'coupon_type',
        'discount_percent',
        'min_order_value',
        'max_discount_amount',
        'valid_until',
        'start_time',
        'end_time',
        'category_id',
        'usage_limit',
        'used_count',
        'scope_book_ids',
        'stacking_policy',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'datetime',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'discount_percent' => 'float',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'scope_book_ids' => 'array',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}

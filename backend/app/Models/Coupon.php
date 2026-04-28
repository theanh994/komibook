<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'discount_percent',
        'min_order_value',
        'max_discount_amount',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'datetime',
            'discount_percent' => 'float',
        ];
    }
}

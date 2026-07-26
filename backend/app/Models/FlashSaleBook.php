<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashSaleBook extends Model
{
    protected $fillable = [
        'flash_sale_id',
        'book_id',
        'vendor_id',
        'discount_percent',
        'sale_price',
        'max_quantity',
        'sold_quantity',
        'status',
        'decided_by',
        'decision_reason',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'float',
            'sale_price' => 'integer',
            'max_quantity' => 'integer',
            'sold_quantity' => 'integer',
        ];
    }

    public function flashSale()
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}

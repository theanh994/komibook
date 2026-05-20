<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashSaleBook extends Model
{
    protected $fillable = [
        'flash_sale_id',
        'book_id',
        'discount_percent',
        'max_quantity',
        'sold_quantity',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'float',
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

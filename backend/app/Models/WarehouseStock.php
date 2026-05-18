<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'book_id',
        'quantity',
        'shelf_location',
    ];

    /**
     * Thuộc về kho hàng.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Thuộc về cuốn sách.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}

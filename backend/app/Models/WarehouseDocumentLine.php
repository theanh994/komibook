<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseDocumentLine extends Model
{
    protected $fillable = [
        'warehouse_document_id',
        'book_id',
        'quantity',
        'expected_quantity',
        'actual_quantity',
        'shelf_location',
        'notes',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(WarehouseDocument::class, 'warehouse_document_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withoutGlobalScopes();
    }
}

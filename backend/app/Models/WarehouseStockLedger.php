<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStockLedger extends Model
{
    protected $fillable = [
        'warehouse_document_id',
        'warehouse_document_line_id',
        'warehouse_id',
        'book_id',
        'quantity_delta',
        'balance_after',
        'actor_id',
        'operation_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(WarehouseDocument::class, 'warehouse_document_id');
    }
}

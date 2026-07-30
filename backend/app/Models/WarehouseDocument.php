<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseDocument extends Model
{
    protected $fillable = [
        'vendor_id',
        'document_code',
        'type',
        'source_warehouse_id',
        'destination_warehouse_id',
        'order_id',
        'status',
        'reason',
        'notes',
        'created_by',
        'approved_by',
        'posted_by',
        'submitted_at',
        'approved_at',
        'posted_at',
        'cancelled_at',
        'operation_key',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseDocumentLine::class);
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(WarehouseStockLedger::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(WarehouseDocumentEvent::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservationAllocation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inventory_reservation_id',
        'warehouse_stock_id',
        'quantity',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Reservation sở hữu phân bổ này.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'inventory_reservation_id');
    }

    /**
     * Tồn kho của kho tương ứng.
     */
    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class, 'warehouse_stock_id');
    }
}

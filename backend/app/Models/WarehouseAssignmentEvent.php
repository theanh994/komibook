<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseAssignmentEvent extends Model
{
    protected $fillable = [
        'warehouse_manager_assignment_id',
        'actor_id',
        'from_status',
        'to_status',
        'reason',
        'operation_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(WarehouseManagerAssignment::class, 'warehouse_manager_assignment_id');
    }
}

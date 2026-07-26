<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewModerationEvent extends Model
{
    protected $fillable = [
        'review_id', 'actor_id', 'action', 'from_status', 'to_status', 'reason', 'metadata', 'operation_key',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutTransition extends Model
{
    protected $fillable = ['payout_request_id', 'actor_id', 'from_status', 'to_status', 'reason', 'operation_key', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsedBookDispute extends Model
{
    protected $fillable = ['order_item_id', 'reporter_id', 'used_book_listing_id', 'type', 'description', 'evidence', 'status', 'held_amount', 'hold_status', 'resolution', 'sanction', 'resolved_by', 'resolved_at'];
    protected function casts(): array
    {
        return ['evidence' => 'array', 'held_amount' => 'integer', 'resolved_at' => 'datetime'];
    }
}

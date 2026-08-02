<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorTaxSchedule extends Model
{
    protected $fillable = ['tax_year', 'brackets', 'effective_at', 'actor_id', 'reason', 'operation_key'];

    protected $casts = ['tax_year' => 'integer', 'brackets' => 'array', 'effective_at' => 'datetime'];
}

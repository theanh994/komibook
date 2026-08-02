<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorTaxLedgerEntry extends Model
{
    protected $fillable = [
        'vendor_id', 'order_id', 'vendor_tax_schedule_id', 'tax_year', 'entry_type', 'taxable_revenue',
        'tax_amount', 'calculation_snapshot', 'operation_key',
    ];

    protected $casts = [
        'tax_year' => 'integer', 'taxable_revenue' => 'integer', 'tax_amount' => 'integer',
        'calculation_snapshot' => 'array',
    ];
}

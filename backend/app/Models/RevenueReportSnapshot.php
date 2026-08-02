<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueReportSnapshot extends Model
{
    protected $fillable = [
        'period_month', 'gross_revenue', 'completed_orders', 'commission_amount',
        'vendor_net_amount', 'refund_amount', 'currency', 'generated_at',
        'generated_by', 'metadata',
    ];

    protected $casts = [
        'period_month' => 'date',
        'gross_revenue' => 'integer',
        'completed_orders' => 'integer',
        'commission_amount' => 'integer',
        'vendor_net_amount' => 'integer',
        'refund_amount' => 'integer',
        'generated_at' => 'datetime',
        'metadata' => 'array',
    ];
}

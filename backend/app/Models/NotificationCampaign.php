<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'image_url',
        'target_audience',
        'scheduled_at',
        'status',
        'sent_count',
        'opened_count',
        'click_count',
        'dispatch_status', 'dispatch_key', 'audience_count', 'failed_count', 'chunk_count',
        'completed_chunk_count', 'failed_chunk_count', 'dispatch_started_at', 'dispatch_completed_at',
        'last_error', 'telemetry_available',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'dispatch_started_at' => 'datetime', 'dispatch_completed_at' => 'datetime',
        'telemetry_available' => 'boolean',
    ];
}

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
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

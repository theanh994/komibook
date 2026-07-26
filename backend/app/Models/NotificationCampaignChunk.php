<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationCampaignChunk extends Model
{
    protected $fillable = ['notification_campaign_id', 'chunk_number', 'user_ids', 'status', 'attempt_count', 'success_count', 'failure_count', 'last_error', 'processed_at'];

    protected function casts(): array
    {
        return ['user_ids' => 'array', 'processed_at' => 'datetime'];
    }
}

<?php

namespace App\Jobs;

use App\Mail\CampaignNotificationMail;
use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignChunk;
use App\Models\UserNotification;
use App\Services\NotificationCampaignDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;
use Throwable;

class DispatchNotificationCampaignChunk implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $uniqueFor = 600;

    public function __construct(public int $chunkId)
    {
        $this->queue = 'default';
    }

    public function uniqueId(): string
    {
        return "campaign-chunk:{$this->chunkId}";
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(NotificationCampaignDispatchService $dispatch): void
    {
        $chunk = DB::transaction(function () {
            $locked = NotificationCampaignChunk::whereKey($this->chunkId)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'succeeded') {
                return null;
            }
            $locked->update(['status' => 'processing', 'attempt_count' => $locked->attempt_count + 1, 'last_error' => null]);

            return $locked;
        });
        if (! $chunk) {
            return;
        }
        $campaign = NotificationCampaign::findOrFail($chunk->notification_campaign_id);
        $eligible = $dispatch->audienceQuery($campaign)->whereIn('id', $chunk->user_ids)->get();
        $success = 0;
        $failure = 0;
        foreach ($eligible as $user) {
            $notification = null;
            try {
                $notification = UserNotification::firstOrCreate(['operation_key' => "campaign:{$campaign->id}:user:{$user->id}"], [
                    'user_id' => $user->id, 'title' => $campaign->title, 'content' => $campaign->message, 'type' => 'marketing',
                    'data' => ['campaign_id' => $campaign->id, 'image_url' => $campaign->image_url, 'icon' => 'campaign', 'colorClass' => 'bg-indigo-100 text-indigo-600'],
                ]);
                if ($notification->wasRecentlyCreated && $user->email) {
                    Mail::to($user->email)->queue(new CampaignNotificationMail($user, $campaign->title, $campaign->message, $campaign->image_url));
                }
                $success++;
            } catch (Throwable) {
                if ($notification?->wasRecentlyCreated) {
                    $notification->delete();
                }
                $failure++;
            }
        }
        $chunk->update(['status' => $failure ? 'failed' : 'succeeded', 'success_count' => $success, 'failure_count' => $failure, 'last_error' => $failure ? 'Một hoặc nhiều recipient không còn đủ điều kiện hoặc gửi thất bại.' : null, 'processed_at' => now()]);
        $dispatch->refreshAggregate($campaign->id);
    }

    public function failed(?Throwable $exception): void
    {
        $chunk = NotificationCampaignChunk::find($this->chunkId);
        if (! $chunk) {
            return;
        }
        $name = $exception ? (new ReflectionClass($exception))->getShortName() : 'MaxAttemptsExhausted';
        $chunk->update(['status' => 'failed', 'last_error' => "Campaign chunk failed: {$name}", 'processed_at' => now()]);
        app(NotificationCampaignDispatchService::class)->refreshAggregate($chunk->notification_campaign_id);
    }
}

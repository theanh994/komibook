<?php

namespace App\Services;

use App\Jobs\DispatchNotificationCampaignChunk;
use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignChunk;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class NotificationCampaignDispatchService
{
    public function start(NotificationCampaign $campaign, ?string $dispatchKey = null): NotificationCampaign
    {
        $dispatchKey ??= (string) Str::uuid();
        $campaign = DB::transaction(function () use ($campaign, $dispatchKey) {
            $locked = NotificationCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->dispatch_status, ['queued', 'processing', 'succeeded'], true)) {
                return $locked;
            }
            if ($locked->target_audience === 'fiction_enthusiasts') {
                throw new LogicException('Audience fiction_enthusiasts chưa có nguồn dữ liệu đồng ý hợp lệ.');
            }
            $locked->update(['dispatch_status' => 'queued', 'dispatch_key' => $locked->dispatch_key ?: $dispatchKey, 'dispatch_started_at' => now(), 'dispatch_completed_at' => null, 'last_error' => null]);

            return $locked;
        });

        if ($campaign->chunk_count > 0) {
            $this->retryFailed($campaign);

            return $campaign->fresh();
        }

        $chunkNumber = 0;
        $audienceCount = 0;
        $chunkIds = [];
        $this->audienceQuery($campaign)->select('users.id')->chunkById(200, function ($users) use ($campaign, &$chunkNumber, &$audienceCount, &$chunkIds) {
            $chunkNumber++;
            $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
            $audienceCount += count($ids);
            $chunk = NotificationCampaignChunk::create(['notification_campaign_id' => $campaign->id, 'chunk_number' => $chunkNumber, 'user_ids' => $ids]);
            $chunkIds[] = $chunk->id;
        });

        $campaign->update(['audience_count' => $audienceCount, 'chunk_count' => $chunkNumber, 'dispatch_status' => $chunkNumber ? 'processing' : 'succeeded', 'status' => $chunkNumber ? $campaign->status : 'sent', 'dispatch_completed_at' => $chunkNumber ? null : now()]);
        foreach ($chunkIds as $chunkId) {
            DispatchNotificationCampaignChunk::dispatch($chunkId);
        }

        return $campaign->fresh();
    }

    public function retryFailed(NotificationCampaign $campaign): void
    {
        $campaign->update(['dispatch_status' => 'processing', 'last_error' => null]);
        NotificationCampaignChunk::where('notification_campaign_id', $campaign->id)->whereIn('status', ['pending', 'failed'])->each(fn ($chunk) => DispatchNotificationCampaignChunk::dispatch($chunk->id));
    }

    public function audienceQuery(NotificationCampaign $campaign): Builder
    {
        $query = User::query()->where('role', 'customer')->whereNotNull('marketing_consent_at')->whereNull('marketing_opt_out_at');
        if ($campaign->target_audience === 'active_readers') {
            $query->whereHas('orders');
        }
        if ($campaign->target_audience === 'lapsed_users') {
            $query->whereDoesntHave('orders', fn ($orders) => $orders->where('created_at', '>=', now()->subDays(30)));
        }

        return $query;
    }

    public function refreshAggregate(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId) {
            $campaign = NotificationCampaign::whereKey($campaignId)->lockForUpdate()->firstOrFail();
            $chunks = NotificationCampaignChunk::where('notification_campaign_id', $campaignId)->get();
            $completed = $chunks->where('status', 'succeeded')->count();
            $failed = $chunks->where('status', 'failed')->count();
            $done = $completed + $failed >= $campaign->chunk_count;
            $campaign->update([
                'sent_count' => $chunks->sum('success_count'), 'failed_count' => $chunks->sum('failure_count'),
                'completed_chunk_count' => $completed, 'failed_chunk_count' => $failed,
                'dispatch_status' => $done ? ($failed ? 'partial_failed' : 'succeeded') : 'processing',
                'status' => $done && $completed > 0 ? 'sent' : $campaign->status, 'dispatch_completed_at' => $done ? now() : null,
                'last_error' => $failed ? 'Một hoặc nhiều chunk gửi thất bại.' : null,
            ]);
        });
    }
}

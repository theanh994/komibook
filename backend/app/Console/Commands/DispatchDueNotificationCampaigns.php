<?php

namespace App\Console\Commands;

use App\Models\NotificationCampaign;
use App\Services\NotificationCampaignDispatchService;
use Illuminate\Console\Command;

class DispatchDueNotificationCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-due {--limit=25}';

    protected $description = 'Queue due notification campaigns in bounded audience chunks';

    public function handle(NotificationCampaignDispatchService $dispatch): int
    {
        NotificationCampaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->whereIn('dispatch_status', ['idle', 'failed', 'partial_failed'])->orderBy('id')->limit((int) $this->option('limit'))->get()
            ->each(fn ($campaign) => $dispatch->start($campaign, "campaign:{$campaign->id}:scheduled"));

        return self::SUCCESS;
    }
}

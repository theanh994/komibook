<?php

use App\Services\ChatSessionAutomationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('chat:resume-idle-ai', function (ChatSessionAutomationService $automationService) {
    $this->info("Đã chuyển {$automationService->resumeAllIdle()} phiên hỗ trợ hết thời gian chờ về Trợ lý AI.");
})->purpose('Tự động trả các phiên hỗ trợ không hoạt động về Trợ lý AI');

Schedule::command('checkout-sessions:expire')->everyMinute()->withoutOverlapping();
Schedule::command('order-side-effects:dispatch --limit=100')->everyMinute()->withoutOverlapping();
Schedule::command('campaigns:dispatch-due --limit=25')->everyMinute()->withoutOverlapping();
Schedule::command('articles:publish-due --limit=50')->everyMinute()->withoutOverlapping();
Schedule::command('chat:resume-idle-ai')->everyMinute()->withoutOverlapping();

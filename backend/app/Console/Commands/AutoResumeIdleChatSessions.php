<?php

namespace App\Console\Commands;

use App\Models\ChatSession;
use App\Services\ChatSessionLifecycleService;
use Illuminate\Console\Command;

class AutoResumeIdleChatSessions extends Command
{
    protected $signature = 'chat:auto-resume-idle {--limit=100}';

    protected $description = 'Return eligible idle human-support sessions to AI mode';

    public function handle(ChatSessionLifecycleService $lifecycle): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $resumed = 0;
        $skipped = 0;

        ChatSession::query()
            ->where('responder_mode', ChatSession::MODE_HUMAN)
            ->where('status', ChatSession::STATUS_WAITING_CUSTOMER)
            ->whereNotNull('assigned_user_id')
            ->whereNotNull('auto_resume_anchor_message_id')
            ->whereNotNull('auto_resume_at')
            ->where('auto_resume_at', '<=', now())
            ->orderBy('id')
            ->lazyById(100, 'id')
            ->each(function (ChatSession $session) use ($lifecycle, $limit, &$resumed, &$skipped): bool {
                $lifecycle->autoResumeIdle($session->id) ? $resumed++ : $skipped++;

                return $resumed < $limit;
            });

        $this->info("Resumed: {$resumed}; skipped: {$skipped}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Services\ArticleWorkflowService;
use Illuminate\Console\Command;

class PublishDueArticles extends Command
{
    protected $signature = 'articles:publish-due {--limit=50}';

    protected $description = 'Publish approved editorial articles whose schedule is due';

    public function handle(ArticleWorkflowService $workflow): int
    {
        Article::where('status', ArticleStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(fn (Article $article) => $workflow->transition(
                $article,
                ArticleStatus::Published,
                null,
                null,
                "article:{$article->id}:scheduled:{$article->scheduled_at?->timestamp}",
            ));

        return self::SUCCESS;
    }
}

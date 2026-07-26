<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArticleWorkflowService
{
    public function transition(Article $article, ArticleStatus $target, ?User $actor, ?string $reason = null, ?string $operationKey = null, mixed $scheduledAt = null): Article
    {
        $operationKey ??= 'article:'.Str::uuid();

        return DB::transaction(function () use ($article, $target, $actor, $reason, $operationKey, $scheduledAt) {
            $existing = ArticleEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                if ($existing->article_id !== $article->id || $existing->to_status !== $target->value) {
                    throw ValidationException::withMessages(['operation_key' => 'Operation key was already used for a different transition.']);
                }

                return $article->fresh();
            }
            $locked = Article::lockForUpdate()->findOrFail($article->id);
            $from = $locked->status;
            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => "Invalid article transition: {$from->value} -> {$target->value}."]);
            }
            if (in_array($target, [ArticleStatus::ChangesRequested, ArticleStatus::Rejected, ArticleStatus::Unpublished, ArticleStatus::Archived], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required.']);
            }
            if ($target === ArticleStatus::Scheduled && (! $scheduledAt || now()->gte($scheduledAt))) {
                throw ValidationException::withMessages(['scheduled_at' => 'Schedule must be in the future.']);
            }
            $updates = ['status' => $target, 'review_reason' => $reason];
            if ($target === ArticleStatus::Scheduled) {
                $updates['scheduled_at'] = $scheduledAt;
            }
            if ($target === ArticleStatus::Published) {
                $updates += ['published_at' => now(), 'scheduled_at' => null];
            }
            $locked->update($updates);
            ArticleEvent::create(['article_id' => $locked->id, 'actor_id' => $actor?->id, 'from_status' => $from->value, 'to_status' => $target->value, 'reason' => $reason, 'operation_key' => $operationKey]);
            UserNotification::firstOrCreate(
                ['operation_key' => "notification:{$operationKey}:{$locked->author_id}"],
                ['user_id' => $locked->author_id, 'title' => 'Cập nhật bài viết', 'content' => 'Bài viết đã chuyển sang '.$target->value.($reason ? '. Lý do: '.$reason : '.'), 'type' => 'system', 'data' => ['article_id' => $locked->id, 'status' => $target->value]],
            );

            return $locked->fresh(['category', 'tags', 'books', 'events']);
        });
    }
}

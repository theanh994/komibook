<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleEvent;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4EditorialCmsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_only_admin_can_create_and_content_is_sanitized_and_revisioned(): void
    {
        $payload = [
            'title' => 'KomiBook Editorial',
            'body' => '<p>Nội dung hợp lệ</p><script>alert(1)</script>',
            'category' => 'Tin sách',
            'tags' => ['Ra mắt', 'KomiBook'],
            'seo_title' => 'KomiBook Editorial',
            'home_featured' => true,
        ];

        $this->actingAs(User::factory()->create())->postJson('/api/admin/articles', $payload)->assertForbidden();
        $response = $this->actingAs($this->admin)->postJson('/api/admin/articles', $payload)
            ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.revision', 1);

        $article = Article::findOrFail($response->json('data.id'));
        $this->assertStringNotContainsString('<script', $article->body);
        $this->assertSame(2, $article->tags()->count());
        $this->assertSame(1, ArticleRevision::where('article_id', $article->id)->count());

        $this->actingAs($this->admin)->patchJson("/api/admin/articles/{$article->id}", [
            'title' => 'KomiBook Editorial cập nhật',
            'body' => '<p>Phiên bản hai</p>',
        ])->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertSame(2, ArticleRevision::where('article_id', $article->id)->count());
    }

    public function test_publication_lifecycle_is_audited_idempotent_and_public_is_fail_closed(): void
    {
        $article = $this->createArticle();
        $this->getJson("/api/articles/{$article->slug}")->assertNotFound();
        $this->getJson('/api/articles?home_featured=1')->assertJsonCount(0, 'data.data');

        $this->transition($article, 'submitted', 'cms-submit');
        $this->transition($article, 'under_review', 'cms-review');
        $this->transition($article, 'approved', 'cms-approve');
        $this->transition($article, 'published', 'cms-publish')
            ->assertJsonPath('data.status', 'published');

        $this->getJson("/api/articles/{$article->slug}")
            ->assertOk()->assertJsonMissingPath('data.review_reason');
        $this->getJson('/api/articles?home_featured=1')->assertJsonCount(1, 'data.data');

        $this->transition($article, 'published', 'cms-publish')->assertOk();
        $this->assertSame(4, ArticleEvent::where('article_id', $article->id)->count());
        $this->actingAs($this->admin)->patchJson("/api/admin/articles/{$article->id}/transition", [
            'to_status' => 'archived', 'reason' => 'Lưu trữ', 'operation_key' => 'cms-publish',
        ])->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    }

    public function test_changes_requested_requires_reason_and_scheduled_article_is_published_by_command(): void
    {
        $article = $this->createArticle();
        $this->transition($article, 'submitted', 'schedule-submit');
        $this->transition($article, 'under_review', 'schedule-review');
        $this->actingAs($this->admin)->patchJson("/api/admin/articles/{$article->id}/transition", [
            'to_status' => 'changes_requested', 'operation_key' => 'missing-reason',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->transition($article, 'changes_requested', 'request-changes', 'Bổ sung nguồn.');
        $this->actingAs($this->admin)->patchJson("/api/admin/articles/{$article->id}", ['body' => '<p>Đã bổ sung nguồn.</p>'])->assertOk();
        $this->transition($article, 'submitted', 'resubmit');
        $this->transition($article, 'under_review', 'rereview');
        $this->transition($article, 'approved', 'reapprove');
        $this->actingAs($this->admin)->patchJson("/api/admin/articles/{$article->id}/transition", [
            'to_status' => 'scheduled', 'scheduled_at' => now()->addHour()->toISOString(), 'operation_key' => 'schedule',
        ])->assertOk()->assertJsonPath('data.status', 'scheduled');

        $this->getJson("/api/articles/{$article->slug}")->assertNotFound();
        $article->update(['scheduled_at' => now()->subMinute()]);
        $this->artisan('articles:publish-due')->assertSuccessful();
        $this->assertSame(ArticleStatus::Published, $article->fresh()->status);
        $this->getJson("/api/articles/{$article->slug}")->assertOk();
    }

    public function test_unpublished_and_archived_articles_are_not_public(): void
    {
        $article = $this->createArticle();
        foreach ([['submitted', 'u1', null], ['under_review', 'u2', null], ['approved', 'u3', null], ['published', 'u4', null], ['unpublished', 'u5', 'Tạm gỡ']] as [$status, $key, $reason]) {
            $this->transition($article, $status, $key, $reason)->assertOk();
        }
        $this->getJson("/api/articles/{$article->slug}")->assertNotFound();
        $this->transition($article, 'archived', 'u6', 'Hết hiệu lực')->assertOk();
        $this->getJson("/api/articles/{$article->slug}")->assertNotFound();
    }

    private function createArticle(): Article
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/articles', [
            'title' => 'Bản tin '.fake()->unique()->numberBetween(1000, 9999),
            'body' => '<p>Nội dung đã xác minh.</p>',
            'home_featured' => true,
        ])->assertCreated();

        return Article::findOrFail($response->json('data.id'));
    }

    private function transition(Article $article, string $status, string $key, ?string $reason = null)
    {
        return $this->actingAs($this->admin)->patchJson("/api/admin/articles/{$article->id}/transition", array_filter([
            'to_status' => $status,
            'operation_key' => $key,
            'reason' => $reason,
        ], fn ($value) => $value !== null));
    }
}

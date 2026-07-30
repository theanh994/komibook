<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleRevision;
use App\Models\ArticleTag;
use App\Services\ArticleWorkflowService;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['status' => 'success', 'data' => Article::with(['category', 'tags', 'books'])->latest()->paginate(20)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $article = DB::transaction(function () use ($request, $validated) {
            $article = Article::create([
                ...collect($validated)->except(['category', 'tags', 'book_ids', 'cover_image'])->all(),
                'created_by' => $request->user()->id, 'slug' => $this->uniqueSlug($validated['title']),
                'body' => HtmlSanitizer::sanitize($validated['body']), 'status' => ArticleStatus::Draft,
                'cover_image' => $request->file('cover_image')?->store('articles/covers', 'public'),
                'article_category_id' => $this->category($validated['category'] ?? null)?->id,
            ]);
            $article->refresh();
            $this->syncLinks($article, $validated);
            $this->snapshot($article, $request->user()->id);

            return $article;
        });

        return response()->json(['status' => 'success', 'data' => $article->load(['category', 'tags', 'books', 'revisions'])], 201);
    }

    public function update(Request $request, Article $article)
    {
        abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::ChangesRequested], true), 422);
        $validated = $request->validate($this->rules(false));
        DB::transaction(function () use ($request, $article, $validated) {
            $updates = collect($validated)->except(['category', 'tags', 'book_ids', 'cover_image'])->all();
            if (isset($updates['body'])) {
                $updates['body'] = HtmlSanitizer::sanitize($updates['body']);
            }
            if (isset($updates['title']) && $updates['title'] !== $article->title) {
                $updates['slug'] = $this->uniqueSlug($updates['title'], $article->id);
            }
            if ($request->hasFile('cover_image')) {
                $updates['cover_image'] = $request->file('cover_image')->store('articles/covers', 'public');
            }
            if (array_key_exists('category', $validated)) {
                $updates['article_category_id'] = $this->category($validated['category'])?->id;
            }
            $updates['revision'] = $article->revision + 1;
            $article->update($updates);
            $this->syncLinks($article, $validated);
            $this->snapshot($article, $request->user()->id);
        });

        return response()->json(['status' => 'success', 'data' => $article->fresh(['category', 'tags', 'books', 'revisions'])]);
    }

    public function transition(Request $request, Article $article, ArticleWorkflowService $service)
    {
        $validated = $request->validate(['to_status' => 'required|in:submitted,under_review,approved,changes_requested,rejected,scheduled,published,unpublished,archived,draft', 'reason' => 'nullable|string|max:2000', 'scheduled_at' => 'nullable|date', 'operation_key' => 'nullable|string|max:128']);
        $updated = $service->transition($article, ArticleStatus::from($validated['to_status']), $request->user(), $validated['reason'] ?? null, $validated['operation_key'] ?? null, $validated['scheduled_at'] ?? null);

        return response()->json(['status' => 'success', 'data' => $updated]);
    }

    private function rules(bool $required = true): array
    {
        $prefix = $required ? 'required' : 'sometimes|required';

        return ['title' => "$prefix|string|max:255", 'excerpt' => 'nullable|string|max:1000', 'body' => "$prefix|string|max:200000", 'category' => 'nullable|string|max:100', 'tags' => 'nullable|array|max:20', 'tags.*' => 'string|max:50', 'book_ids' => 'nullable|array|max:20', 'book_ids.*' => 'integer|exists:books,id', 'cover_image' => 'nullable|image|max:10240', 'home_featured' => 'nullable|boolean', 'seo_title' => 'nullable|string|max:255', 'seo_description' => 'nullable|string|max:320'];
    }

    private function category(?string $name): ?ArticleCategory
    {
        return blank($name) ? null : ArticleCategory::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
    }

    private function syncLinks(Article $article, array $validated): void
    {
        if (array_key_exists('tags', $validated)) {
            $article->tags()->sync(collect($validated['tags'] ?? [])->map(fn ($name) => ArticleTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id));
        }
        if (array_key_exists('book_ids', $validated)) {
            $article->books()->sync($validated['book_ids'] ?? []);
        }
    }

    private function snapshot(Article $article, int $actorId): void
    {
        ArticleRevision::create(['article_id' => $article->id, 'actor_id' => $actorId, 'revision' => $article->revision, 'snapshot' => $article->fresh(['category', 'tags', 'books'])->toArray()]);
    }

    private function uniqueSlug(string $title, ?int $ignore = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 2;
        while (Article::where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}

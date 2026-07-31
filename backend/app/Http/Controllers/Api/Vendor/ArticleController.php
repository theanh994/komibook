<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleMedia;
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
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        $articles = Article::with(['category', 'tags', 'books:id,title,slug'])
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('article_type', $request->string('type')))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%'))
            ->latest('updated_at')->paginate($request->integer('per_page', 20));

        return response()->json(['status' => 'success', 'data' => $articles]);
    }

    public function show(Request $request, Article $article)
    {
        $this->authorizeOwner($request, $article);

        return response()->json(['status' => 'success', 'data' => $article->load(['category', 'tags', 'books:id,title,slug', 'revisions', 'events'])]);
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        $validated = $request->validate($this->rules());
        $article = DB::transaction(function () use ($request, $vendor, $validated) {
            $article = Article::create([
                ...collect($validated)->except(['category', 'tags', 'book_ids', 'cover_image'])->all(),
                'created_by' => $request->user()->id,
                'vendor_id' => $vendor->id,
                'slug' => $this->uniqueSlug($validated['title']),
                'body' => HtmlSanitizer::sanitize($validated['body']),
                'status' => ArticleStatus::Draft,
                'cover_image' => $request->file('cover_image')?->store('articles/covers', 'public'),
                'article_category_id' => $this->category($validated['category'] ?? null)?->id,
                'reading_minutes' => $this->readingMinutes($validated['body']),
            ]);
            $article->refresh();
            $this->syncLinks($article, $validated, $vendor->id);
            $this->snapshot($article, $request->user()->id);

            return $article;
        });

        return response()->json(['status' => 'success', 'data' => $article->load(['category', 'tags', 'books', 'revisions'])], 201);
    }

    public function update(Request $request, Article $article)
    {
        $vendor = $this->authorizeOwner($request, $article);
        abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::ChangesRequested], true), 422);
        $validated = $request->validate($this->rules(false));
        DB::transaction(function () use ($request, $article, $validated, $vendor) {
            $updates = collect($validated)->except(['category', 'tags', 'book_ids', 'cover_image'])->all();
            if (isset($updates['body'])) {
                $updates['body'] = HtmlSanitizer::sanitize($updates['body']);
                $updates['reading_minutes'] = $this->readingMinutes($updates['body']);
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
            $this->syncLinks($article, $validated, $vendor->id);
            $this->snapshot($article, $request->user()->id);
        });

        return response()->json(['status' => 'success', 'data' => $article->fresh(['category', 'tags', 'books', 'revisions'])]);
    }

    public function submit(Request $request, Article $article, ArticleWorkflowService $workflow)
    {
        $this->authorizeOwner($request, $article);
        abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::ChangesRequested], true), 422);
        $updated = $workflow->transition($article, ArticleStatus::Submitted, $request->user(), operationKey: $request->input('operation_key'));

        return response()->json(['status' => 'success', 'data' => $updated]);
    }

    public function media(Request $request, Article $article)
    {
        $this->authorizeOwner($request, $article);
        $validated = $request->validate(['image' => 'required|image|max:10240', 'alt_text' => 'required|string|max:500']);
        $file = $validated['image'];
        $path = $file->store("articles/{$article->id}", 'public');
        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];
        $media = ArticleMedia::create([
            'article_id' => $article->id,
            'uploaded_by' => $request->user()->id,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'alt_text' => $validated['alt_text'],
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ]);

        return response()->json(['status' => 'success', 'data' => [...$media->toArray(), 'url' => '/storage/'.$path]], 201);
    }

    private function authorizeOwner(Request $request, Article $article)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        abort_unless($article->vendor_id === $vendor->id, 404);

        return $vendor;
    }

    private function rules(bool $required = true): array
    {
        $prefix = $required ? 'required' : 'sometimes|required';

        return ['title' => "$prefix|string|max:255", 'excerpt' => 'nullable|string|max:1000', 'body' => "$prefix|string|max:200000", 'article_type' => 'nullable|in:news,review,book_introduction,event,vendor_announcement', 'category' => 'nullable|string|max:100', 'tags' => 'nullable|array|max:20', 'tags.*' => 'string|max:50', 'book_ids' => 'required|array|min:1|max:20', 'book_ids.*' => 'integer|exists:books,id', 'cover_image' => 'nullable|image|max:10240', 'allow_comments' => 'nullable|boolean', 'seo_title' => 'nullable|string|max:255', 'seo_description' => 'nullable|string|max:320'];
    }

    private function category(?string $name): ?ArticleCategory
    {
        return blank($name) ? null : ArticleCategory::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
    }

    private function syncLinks(Article $article, array $validated, int $vendorId): void
    {
        if (array_key_exists('tags', $validated)) {
            $article->tags()->sync(collect($validated['tags'] ?? [])->map(fn ($name) => ArticleTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id));
        }
        if (array_key_exists('book_ids', $validated)) {
            $allowedIds = DB::table('books')->where('vendor_id', $vendorId)->whereIn('id', $validated['book_ids'] ?? [])->pluck('id');
            abort_unless($allowedIds->count() === count($validated['book_ids'] ?? []), 422, 'Nhà bán chỉ được liên kết sách thuộc gian hàng của mình.');
            $article->books()->sync($allowedIds);
            $this->deriveTaxonomy($article, $allowedIds);
        }
    }

    private function deriveTaxonomy(Article $article, $bookIds): void
    {
        $categoryNames = DB::table('categories')
            ->join('book_category', 'book_category.category_id', '=', 'categories.id')
            ->whereIn('book_category.book_id', $bookIds)
            ->orderBy('categories.name')
            ->pluck('categories.name')
            ->unique()
            ->values();
        if ($categoryNames->isEmpty()) {
            $categoryNames = DB::table('categories')
                ->join('books', 'books.category_id', '=', 'categories.id')
                ->whereIn('books.id', $bookIds)
                ->pluck('categories.name')
                ->unique()
                ->values();
        }
        if ($categoryNames->isEmpty()) {
            return;
        }
        $article->update(['article_category_id' => $this->category($categoryNames->first())?->id]);
        $article->tags()->sync($categoryNames->take(20)->map(
            fn ($name) => ArticleTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id
        ));
    }

    private function snapshot(Article $article, int $actorId): void
    {
        ArticleRevision::create(['article_id' => $article->id, 'actor_id' => $actorId, 'revision' => $article->revision, 'snapshot' => $article->fresh(['category', 'tags', 'books'])->toArray()]);
    }

    private function uniqueSlug(string $title, ?int $ignore = null): string
    {
        $base = Str::slug($title) ?: 'bai-viet';
        $slug = $base;
        $counter = 2;
        while (Article::where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function readingMinutes(string $body): int
    {
        $words = preg_split('/\s+/u', trim(strip_tags($body)), -1, PREG_SPLIT_NO_EMPTY);

        return max(1, (int) ceil(count($words ?: []) / 220));
    }
}

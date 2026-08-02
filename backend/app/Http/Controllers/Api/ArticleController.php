<?php

namespace App\Http\Controllers\Api;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleMetricDaily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $articles = Article::with([
            'category',
            'tags',
            'books:id,vendor_id,title,slug,cover_image,price,sale_price,type',
            'creator:id,name,role',
            'creator.vendor:id,user_id,shop_name',
            'vendor:id,shop_name,slug,logo',
        ])
            ->where('status', ArticleStatus::Published)->where('published_at', '<=', now())
            ->when($request->boolean('home_featured'), fn ($query) => $query->where('home_featured', true))
            ->when($request->filled('type'), fn ($query) => $query->where('article_type', $request->string('type')))
            ->when($request->filled('category'), fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $request->string('category'))))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($search) => $search
                ->where('title', 'like', '%'.$request->string('search').'%')
                ->orWhere('excerpt', 'like', '%'.$request->string('search').'%')))
            ->latest('published_at')->paginate($request->integer('per_page', 12));

        return response()->json(['status' => 'success', 'data' => $articles]);
    }

    public function show(string $slug)
    {
        $article = Article::with([
            'category',
            'tags',
            'books:id,vendor_id,title,slug,cover_image,price,sale_price,type',
            'creator:id,name,role',
            'creator.vendor:id,user_id,shop_name',
            'vendor:id,shop_name,slug,logo',
            'comments' => fn ($query) => $query->where('status', 'approved')->whereNull('parent_id')->with(['user:id,name,avatar', 'replies.user:id,name,avatar'])->oldest(),
        ])
            ->where('slug', $slug)->where('status', ArticleStatus::Published)->where('published_at', '<=', now())->firstOrFail();

        ArticleMetricDaily::query()->upsert(
            [['article_id' => $article->id, 'metric_date' => now()->toDateString(), 'views' => 0, 'book_clicks' => 0, 'shop_clicks' => 0, 'comments' => 0, 'created_at' => now(), 'updated_at' => now()]],
            ['article_id', 'metric_date'],
            ['updated_at']
        );
        ArticleMetricDaily::where('article_id', $article->id)->whereDate('metric_date', today())->increment('views');

        $related = Article::with(['category', 'vendor:id,shop_name,slug'])
            ->whereKeyNot($article->id)
            ->where('status', ArticleStatus::Published)
            ->where('published_at', '<=', now())
            ->when($article->article_category_id, fn ($query) => $query->where('article_category_id', $article->article_category_id))
            ->latest('published_at')->limit(4)->get();

        $latest = Article::with(['category', 'vendor:id,shop_name,slug', 'creator:id,name'])
            ->whereKeyNot($article->id)
            ->where('status', ArticleStatus::Published)
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $article,
            'related' => $related,
            'latest' => $latest,
        ]);
    }

    public function track(Request $request, string $slug)
    {
        $validated = $request->validate(['event' => 'required|in:book_click,shop_click']);
        $article = Article::where('slug', $slug)->where('status', ArticleStatus::Published)->firstOrFail();
        $column = $validated['event'] === 'book_click' ? 'book_clicks' : 'shop_clicks';
        DB::table('article_metrics_daily')->upsert(
            [['article_id' => $article->id, 'metric_date' => now()->toDateString(), 'views' => 0, 'book_clicks' => 0, 'shop_clicks' => 0, 'comments' => 0, 'created_at' => now(), 'updated_at' => now()]],
            ['article_id', 'metric_date'],
            ['updated_at']
        );
        DB::table('article_metrics_daily')->where('article_id', $article->id)->where('metric_date', now()->toDateString())->increment($column);

        return response()->json(['status' => 'success']);
    }
}

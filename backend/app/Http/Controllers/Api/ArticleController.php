<?php

namespace App\Http\Controllers\Api;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $articles = Article::with([
            'category',
            'tags',
            'books:id,title,slug',
            'creator:id,name,role',
            'creator.vendor:id,user_id,shop_name',
        ])
            ->where('status', ArticleStatus::Published)->where('published_at', '<=', now())
            ->when($request->boolean('home_featured'), fn ($query) => $query->where('home_featured', true))
            ->latest('published_at')->paginate($request->integer('per_page', 12));

        return response()->json(['status' => 'success', 'data' => $articles]);
    }

    public function show(string $slug)
    {
        $article = Article::with([
            'category',
            'tags',
            'books:id,title,slug',
            'creator:id,name,role',
            'creator.vendor:id,user_id,shop_name',
        ])
            ->where('slug', $slug)->where('status', ArticleStatus::Published)->where('published_at', '<=', now())->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $article]);
    }
}

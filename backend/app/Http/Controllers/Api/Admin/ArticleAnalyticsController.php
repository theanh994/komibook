<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\ArticleMetricDaily;
use App\Models\ArticleSubmission;

class ArticleAnalyticsController extends Controller
{
    public function __invoke()
    {
        $totals = ArticleMetricDaily::selectRaw('COALESCE(SUM(views), 0) as views, COALESCE(SUM(book_clicks), 0) as book_clicks, COALESCE(SUM(shop_clicks), 0) as shop_clicks')->first();
        $byDay = ArticleMetricDaily::selectRaw('metric_date, SUM(views) as views, SUM(book_clicks) as book_clicks, SUM(shop_clicks) as shop_clicks')
            ->where('metric_date', '>=', now()->subDays(29)->toDateString())
            ->groupBy('metric_date')->orderBy('metric_date')->get();

        return response()->json(['status' => 'success', 'data' => [
            'published' => Article::where('status', 'published')->count(),
            'pending_review' => Article::whereIn('status', ['submitted', 'under_review'])->count(),
            'pending_comments' => ArticleComment::where('status', 'pending')->count(),
            'pending_submissions' => ArticleSubmission::where('status', 'pending')->count(),
            'views' => (int) $totals->views,
            'book_clicks' => (int) $totals->book_clicks,
            'shop_clicks' => (int) $totals->shop_clicks,
            'daily' => $byDay,
        ]]);
    }
}

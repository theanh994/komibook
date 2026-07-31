<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleMetricDaily;
use Illuminate\Http\Request;

class ArticleAnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        $articleIds = Article::where('vendor_id', $vendor->id)->pluck('id');
        $totals = ArticleMetricDaily::whereIn('article_id', $articleIds)
            ->selectRaw('COALESCE(SUM(views), 0) as views, COALESCE(SUM(book_clicks), 0) as book_clicks, COALESCE(SUM(shop_clicks), 0) as shop_clicks')->first();

        return response()->json(['status' => 'success', 'data' => [
            'published' => Article::where('vendor_id', $vendor->id)->where('status', 'published')->count(),
            'pending_review' => Article::where('vendor_id', $vendor->id)->whereIn('status', ['submitted', 'under_review'])->count(),
            'views' => (int) $totals->views,
            'book_clicks' => (int) $totals->book_clicks,
            'shop_clicks' => (int) $totals->shop_clicks,
        ]]);
    }
}

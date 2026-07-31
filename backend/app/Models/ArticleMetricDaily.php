<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleMetricDaily extends Model
{
    protected $table = 'article_metrics_daily';

    protected $fillable = ['article_id', 'metric_date', 'views', 'book_clicks', 'shop_clicks', 'comments'];

    protected function casts(): array
    {
        return ['metric_date' => 'date'];
    }
}

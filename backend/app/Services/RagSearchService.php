<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\ChatSession;
use App\Models\Coupon;
use App\Models\HelpArticle;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RagSearchService
{
    /** @var list<string> */
    private array $stopWords = [
        'tôi', 'mình', 'bạn', 'ơi', 'có', 'không', 'là', 'gì', 'về', 'cho', 'với', 'của',
        'các', 'những', 'này', 'đó', 'thế', 'nào', 'giúp', 'xem', 'tìm', 'komibook',
        'hiện', 'đang', 'bao', 'nhiêu', 'ạ', 'vậy', 'thì', 'trong', 'trên', 'hệ', 'thống',
    ];

    /**
     * Builds a fail-closed, locally grounded envelope. The supplied session is only
     * an identifier; all authority is resolved again from persisted records.
     *
     * @return array<string, mixed>
     */
    public function buildKnowledge(ChatSession $session, string $query, ?int $contextBookId = null): array
    {
        $scope = $this->resolveScope($session);
        if ($scope === null) {
            return $this->emptyEnvelope('denied', 'invalid_session_scope', 'generic');
        }

        $intent = $this->primaryIntent($query);
        if ($intent === 'human_support') {
            return $this->envelope([], [], [], [], null, null, $scope, 'matched', 'human_support', $intent);
        }

        $books = [];
        $articles = [];
        $help = [];
        $tiers = [];
        $coupons = [];
        $orders = [];
        $contextBook = null;
        $catalogSummary = null;
        $state = 'no_match';
        $reason = 'no_relevant_source';

        switch ($intent) {
            case 'order':
                if (! $scope['user'] instanceof User || $scope['user']->role !== 'customer') {
                    return $this->envelope([], [], [], [], null, null, $scope, 'denied', 'owner_required', $intent);
                }
                $orders = $this->searchOrders($scope, $query);
                [$state, $reason] = $orders === [] ? ['no_match', 'order_not_found'] : ['matched', 'matched'];
                break;

            case 'coupon':
                $coupons = $this->searchCoupons($scope, $query);
                [$state, $reason] = $coupons === [] ? ['no_match', 'coupon_not_found'] : ['matched', 'matched'];
                break;

            case 'catalog':
                $books = $this->searchBooks($scope, $query, 6);
                $catalogSummary = $this->catalogSummary($scope);
                [$state, $reason] = $books === [] && $catalogSummary['total_titles'] === 0
                    ? ['no_match', 'book_not_found']
                    : ['matched', 'matched'];
                break;

            case 'book':
                $contextBook = $contextBookId ? $this->findContextBook($scope, $contextBookId) : null;
                $books = $this->searchBooks($scope, $query, 6);
                if ($contextBook && ! collect($books)->contains('id', $contextBook['id'])) {
                    array_unshift($books, $contextBook);
                    $books = array_slice($books, 0, 6);
                }
                [$state, $reason] = $books === [] ? ['no_match', 'book_not_found'] : ['matched', 'matched'];
                break;

            case 'article':
                $articles = $this->searchArticles($scope, $query, 3);
                [$state, $reason] = $articles === [] ? ['no_match', 'article_not_found'] : ['matched', 'matched'];
                break;

            case 'help':
                $help = $this->searchHelpArticles($query, 3);
                [$state, $reason] = $help === [] ? ['no_match', 'help_not_found'] : ['matched', 'matched'];
                break;

            case 'membership':
                $tiers = $this->searchMembershipTiers($query);
                [$state, $reason] = $tiers === [] ? ['no_match', 'membership_not_found'] : ['matched', 'matched'];
                break;
        }

        return $this->envelope($books, $articles, $help, $tiers, $coupons, $orders, $scope, $state, $reason, $intent, $contextBook, $catalogSummary);
    }

    public function primaryIntent(string $query): string
    {
        if ($this->detectHumanSupportIntent($query)) {
            return 'human_support';
        }
        if ($this->detectOrderIntent($query)) {
            return 'order';
        }
        if ($this->detectCouponIntent($query)) {
            return 'coupon';
        }
        if ($this->isCatalogSummaryIntent($query)) {
            return 'catalog';
        }
        if ($this->detectBookIntent($query)) {
            return 'book';
        }
        if ($this->detectArticleIntent($query)) {
            return 'article';
        }
        if ($this->detectHelpIntent($query)) {
            return 'help';
        }
        if ($this->detectMembershipIntent($query)) {
            return 'membership';
        }

        return 'generic';
    }

    /** @return array<string, mixed>|null */
    private function resolveScope(ChatSession $provided): ?array
    {
        if (! $provided->exists || ! $provided->getKey()) {
            return null;
        }

        $session = ChatSession::query()->with('user:id,role')->find($provided->getKey());
        if (! $session) {
            return null;
        }

        if ($session->target_type === ChatSession::TARGET_PLATFORM && $session->vendor_id === null) {
            return ['session' => $session, 'vendor' => null, 'user' => $session->user];
        }

        if ($session->target_type !== ChatSession::TARGET_VENDOR || $session->vendor_id === null) {
            return null;
        }

        $vendor = Vendor::withoutGlobalScopes()->whereKey($session->vendor_id)->where('status', 'active')->first();
        if (! $vendor) {
            return null;
        }

        return ['session' => $session, 'vendor' => $vendor, 'user' => $session->user];
    }

    /** @return array<string, mixed> */
    private function emptyEnvelope(string $state, string $reason, string $intent): array
    {
        return [
            'context' => '', 'sources' => [], 'entries' => [], 'recommended_books' => [], 'recommended_coupons' => [], 'recommended_orders' => [],
            'context_book' => null, 'catalog_summary' => null, 'session_user_id' => null, 'scope_vendor_id' => null,
            'match_state' => $state, 'match_reason' => $reason, 'primary_intent' => $intent,
        ];
    }

    /** @param array<string, mixed> $scope */
    private function envelope(array $books, array $articles, array $help, array $tiers, ?array $coupons, ?array $orders, array $scope, string $state, string $reason, string $intent, ?array $contextBook = null, ?array $catalogSummary = null): array
    {
        $coupons ??= [];
        $orders ??= [];
        $sources = [];
        if ($catalogSummary && $state === 'matched') {
            $sources[] = ['type' => 'catalog_summary', 'id' => $scope['vendor']?->id ?? 0, 'title' => $catalogSummary['scope_name'], 'url' => $scope['vendor'] ? "/shops/{$scope['vendor']->slug}" : '/catalog', 'content' => "Có {$catalogSummary['total_titles']} đầu sách đã công bố; {$catalogSummary['physical_available']} sách giấy đang còn hàng; {$catalogSummary['ebook_titles']} ebook. Một số sách tiêu biểu: ".implode(', ', $catalogSummary['sample_titles']).'.'];
        }
        foreach ($books as $book) {
            $series = $book['series_title']
                ? " Thuộc bộ {$book['series_title']} với {$book['series_book_count']} đầu sách đang công bố: ".implode(', ', $book['series_books']).'.'
                : '';
            $rating = " · Đánh giá {$book['rating_avg']}/5★ ({$book['review_count']} nhận xét)";
            $wishlist = ! empty($book['wishlist_count']) ? " · {$book['wishlist_count']} lượt Yêu thích" : '';
            $views = ! empty($book['views']) ? " · {$book['views']} lượt xem" : '';
            $sources[] = ['type' => 'book', 'id' => $book['id'], 'title' => $book['title'], 'url' => "/book/{$book['slug']}", 'content' => "{$book['title']} — Tác giả: {$book['author']}; giá ".number_format($book['display_price'])."đ; loại {$book['type']}; tồn kho {$book['stock']}; Thể loại: {$book['category_name']}; gian hàng {$book['vendor_name']}{$rating}{$wishlist}{$views}. Mô tả: {$book['description']}{$series}"];
        }
        foreach ($articles as $article) {
            $sources[] = ['type' => 'article', 'id' => $article['id'], 'title' => $article['title'], 'url' => "/blog/{$article['slug']}", 'content' => $article['content']];
        }
        foreach ($help as $article) {
            $sources[] = ['type' => 'help', 'id' => $article['id'], 'title' => $article['title'], 'url' => '/help-center', 'content' => $article['content']];
        }
        foreach ($tiers as $tier) {
            $sources[] = ['type' => 'membership', 'id' => $tier['id'], 'title' => "Hạng {$tier['name']}", 'url' => '/profile?tab=membership', 'content' => "Từ {$tier['min_points']} điểm; giảm {$tier['discount_percent']}% khi checkout. Người dùng nhận 1 KomiPoint cho mỗi 10.000đ giá trị đơn hoàn tất."];
        }
        foreach ($coupons as $coupon) {
            $minVal = $coupon['min_order_value'] ? number_format($coupon['min_order_value']).'đ' : 'không giới hạn';
            $maxDiscount = $coupon['max_discount_amount'] ? ' giảm tối đa '.number_format($coupon['max_discount_amount']).'đ' : '';
            $sources[] = ['type' => 'coupon', 'id' => $coupon['id'], 'title' => "Mã giảm giá {$coupon['code']} — Giảm {$coupon['discount_percent']}%", 'url' => '/cart', 'content' => "Mã [{$coupon['code']}]: Giảm {$coupon['discount_percent']}% cho đơn từ {$minVal}{$maxDiscount}. Áp dụng tại {$coupon['vendor_name']}."];
        }
        foreach ($orders as $order) {
            $sources[] = ['type' => 'order', 'id' => $order['id'], 'title' => "Đơn hàng #{$order['order_code']} — {$order['status_label']}", 'url' => '/profile?tab=orders', 'content' => "Đơn hàng #{$order['order_code']} của {$order['vendor_name']} tổng giá trị ".number_format($order['total_amount'])."đ. Trạng thái: {$order['status_label']}. Sản phẩm: {$order['items_summary']}. Vận chuyển: {$order['shipping_info']}. Đặt ngày: {$order['created_at']}."];
        }

        if ($state !== 'matched') {
            $sources = [];
        }
        $entries = collect($sources)->values()->map(function (array $source, int $index) {
            $source['citation'] = 'S'.($index + 1);

            return $source;
        })->all();

        return [
            'context' => collect($entries)->map(fn (array $source) => "[{$source['citation']}] {$source['title']}\n{$source['content']}")->implode("\n\n"),
            'sources' => collect($entries)->map(function (array $source): array {
                unset($source['content']);

                return $source;
            })->all(),
            'entries' => $entries, 'recommended_books' => $state === 'matched' ? $books : [], 'recommended_coupons' => $state === 'matched' ? $coupons : [], 'recommended_orders' => $state === 'matched' ? $orders : [],
            'context_book' => $state === 'matched' ? $contextBook : null, 'catalog_summary' => $state === 'matched' ? $catalogSummary : null,
            'session_user_id' => $scope['user']?->id, 'match_state' => $state, 'match_reason' => $reason, 'primary_intent' => $intent,
            'scope_target_type' => $scope['session']->target_type,
            'scope_vendor_id' => $scope['session']->vendor_id,
        ];
    }

    /** @param array<string, mixed> $scope */
    private function searchBooks(array $scope, string $query, int $limit): array
    {
        $keywords = $this->keywords($query);
        $builder = $this->publicBooks($scope)->with(['category:id,name', 'vendor:id,shop_name,slug', 'series:id,title'])->withCount(['reviews', 'wishlists'])->withAvg('reviews', 'rating');
        if ($this->isTrendingIntent($query) || $this->isCatalogSummaryIntent($query) && $keywords === []) {
            $books = $builder->get()->sortByDesc(fn (Book $book) => ($book->views ?? 0) + (($book->wishlists_count ?? 0) * 50) + (($book->reviews_count ?? 0) * 100))->take($limit);
        } else {
            if ($keywords === []) {
                return [];
            }
            $builder->where(function (Builder $search) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $like = '%'.$this->escapeLike($keyword).'%';
                    $search->orWhere('books.title', 'like', $like)->orWhere('books.author', 'like', $like)->orWhere('books.description', 'like', $like)->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
                }
            });
            $books = $builder->get()->sortByDesc('views')->take($limit);
        }

        return $books->values()->map(fn (Book $book) => $this->bookPayload($scope, $book))->all();
    }

    /** @param array<string, mixed> $scope */
    private function findContextBook(array $scope, int $bookId): ?array
    {
        $book = $this->publicBooks($scope)->with(['category:id,name', 'vendor:id,shop_name,slug', 'series:id,title'])->withCount(['reviews', 'wishlists'])->withAvg('reviews', 'rating')->find($bookId);

        return $book ? $this->bookPayload($scope, $book) : null;
    }

    /** @param array<string, mixed> $scope */
    private function publicBooks(array $scope): Builder
    {
        return Book::withoutGlobalScopes()->purchasable()->when($scope['vendor'], fn (Builder $query, Vendor $vendor) => $query->where('books.vendor_id', $vendor->id));
    }

    /** @param array<string, mixed> $scope */
    private function bookPayload(array $scope, Book $book): array
    {
        $seriesBooks = $book->series_id ? $this->publicBooks($scope)->where('series_id', $book->series_id)->orderBy('title')->pluck('title')->all() : [];

        return ['id' => $book->id, 'slug' => $book->slug, 'title' => $book->title, 'author' => $book->author, 'price' => $book->price, 'sale_price' => $book->sale_price, 'display_price' => $book->currentPrice(), 'cover_image' => $book->cover_image, 'type' => $book->type, 'stock' => $book->stock, 'category_name' => $book->category?->name ?? 'Chưa phân loại', 'vendor_name' => $book->vendor?->shop_name ?? 'KomiBook', 'description' => Str::limit(trim(strip_tags((string) $book->description)), 240), 'rating_avg' => round((float) ($book->reviews_avg_rating ?? 5), 1), 'review_count' => (int) ($book->reviews_count ?? 0), 'wishlist_count' => (int) ($book->wishlists_count ?? 0), 'views' => (int) ($book->views ?? 0), 'series_title' => $book->series?->title, 'series_book_count' => count($seriesBooks), 'series_books' => $seriesBooks];
    }

    /** @param array<string, mixed> $scope */
    private function catalogSummary(array $scope): array
    {
        $published = Book::withoutGlobalScopes()->sellable()->when($scope['vendor'], fn (Builder $query, Vendor $vendor) => $query->where('books.vendor_id', $vendor->id));
        $visible = (clone $published)->browseVisible();

        return ['scope_name' => $scope['vendor'] ? 'Danh mục của '.$scope['vendor']->shop_name : 'Danh mục toàn hệ thống KomiBook', 'total_titles' => (clone $published)->count(), 'physical_available' => (clone $visible)->where('type', 'physical')->count(), 'ebook_titles' => (clone $visible)->where('type', 'ebook')->count(), 'sample_titles' => (clone $visible)->orderByDesc('views')->limit(5)->pluck('title')->all()];
    }

    /** @param array<string, mixed> $scope */
    private function searchArticles(array $scope, string $query, int $limit): array
    {
        $keywords = $this->keywords($query);
        if ($keywords === []) {
            return [];
        }

        return Article::query()->where('status', ArticleStatus::Published->value)->whereNotNull('published_at')->where('published_at', '<=', now())->when($scope['vendor'], fn (Builder $query, Vendor $vendor) => $query->where(fn (Builder $articles) => $articles->whereNull('vendor_id')->orWhere('vendor_id', $vendor->id)))->where(function (Builder $query) use ($keywords) {
            foreach ($keywords as $keyword) {
                $like = '%'.$this->escapeLike($keyword).'%';
                $query->orWhere('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body', 'like', $like);
            }
        })->latest('published_at')->limit($limit)->get()->map(fn (Article $article) => ['id' => $article->id, 'slug' => $article->slug, 'title' => $article->title, 'content' => Str::limit(trim(strip_tags((string) ($article->body ?: $article->excerpt))), 1200)])->all();
    }

    private function searchHelpArticles(string $query, int $limit): array
    {
        $keywords = $this->keywords($query);
        if ($keywords === []) {
            return [];
        }

        return HelpArticle::publicKnowledge()->where(function (Builder $query) use ($keywords) {
            foreach ($keywords as $keyword) {
                $like = '%'.$this->escapeLike($keyword).'%';
                $query->orWhere('title', 'like', $like)->orWhere('content', 'like', $like)->orWhere('category_name', 'like', $like);
            }
        })->orderByDesc('helpful_count')->limit($limit)->get()->map(fn (HelpArticle $article) => ['id' => $article->id, 'title' => $article->title, 'content' => Str::limit(trim(strip_tags((string) $article->content)), 900)])->all();
    }

    private function searchMembershipTiers(string $query): array
    {
        return MembershipTier::query()->orderBy('min_points')->get()->map(fn (MembershipTier $tier) => ['id' => $tier->id, 'name' => $tier->name, 'min_points' => $tier->min_points, 'discount_percent' => $tier->discount_percent])->all();
    }

    /** @param array<string, mixed> $scope */
    private function searchCoupons(array $scope, string $query): array
    {
        $code = $this->couponCodeFromQuery($query);
        $coupons = Coupon::query()->where('status', 'active')->whereIn('coupon_type', ['product', 'shipping'])->where(fn (Builder $q) => $q->whereNull('start_time')->orWhere('start_time', '<=', now()))->where(fn (Builder $q) => $q->whereNull('end_time')->orWhere('end_time', '>', now()))->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))->where(fn (Builder $q) => $q->whereNull('usage_limit')->orWhere('usage_limit', 0)->orWhereColumn('used_count', '<', 'usage_limit'))->when($scope['vendor'], fn (Builder $q, Vendor $vendor) => $q->where(fn (Builder $inner) => $inner->whereNull('vendor_id')->orWhere('vendor_id', $vendor->id)), fn (Builder $q) => $q->whereNull('vendor_id'))->when($code, fn (Builder $q, string $code) => $q->whereRaw('LOWER(code) = ?', [mb_strtolower($code)]))->with('vendor:id,shop_name')->orderByDesc('discount_percent')->limit(4)->get();

        return $coupons->map(fn (Coupon $coupon) => ['id' => $coupon->id, 'code' => $coupon->code, 'discount_percent' => $coupon->discount_percent, 'min_order_value' => $coupon->min_order_value, 'max_discount_amount' => $coupon->max_discount_amount, 'coupon_type' => $coupon->coupon_type, 'vendor_name' => $coupon->vendor?->shop_name ?? 'KomiBook'])->all();
    }

    /** @param array<string, mixed> $scope */
    private function searchOrders(array $scope, string $query): array
    {
        $user = $scope['user'];
        if (! $user instanceof User || $user->role !== 'customer') {
            return [];
        }
        $code = $this->orderCodeFromQuery($query);
        $orders = Order::withoutGlobalScopes()->where('user_id', $user->id)->when($scope['vendor'], fn (Builder $q, Vendor $vendor) => $q->where('vendor_id', $vendor->id))->when($code, fn (Builder $q, string $code) => $q->whereRaw('LOWER(order_code) = ?', [mb_strtolower($code)]))->with(['vendor:id,shop_name', 'orderItems.book:id,title'])->latest('created_at')->limit($code ? 1 : 3)->get();
        $labels = ['pending' => 'Chờ xác nhận', 'processing' => 'Đang xử lý/Đóng gói', 'shipped' => 'Đang vận chuyển', 'delivered' => 'Đã giao hàng', 'completed' => 'Đã hoàn tất', 'cancelled' => 'Đã hủy'];

        return $orders->map(fn (Order $order) => ['id' => $order->id, 'order_code' => $order->order_code, 'status' => $order->status, 'status_label' => $labels[$order->status] ?? $order->status, 'total_amount' => $order->total_amount, 'items_summary' => $order->orderItems->map(fn ($item) => ($item->book?->title ?? 'Sách').' (x'.$item->quantity.')')->implode(', '), 'shipping_info' => trim((string) $order->shipping_status).($order->shipping_carrier ? " qua ĐVVC {$order->shipping_carrier}" : '').($order->shipping_tracking_code ? " (mã VĐ: {$order->shipping_tracking_code})" : ''), 'vendor_name' => $order->vendor?->shop_name ?? 'KomiBook', 'created_at' => $order->created_at?->format('d/m/Y H:i')])->all();
    }

    private function orderCodeFromQuery(string $query): ?string
    {
        return preg_match('/\b(ORD-[A-Z0-9-]+)\b/i', $query, $matches) === 1 ? $matches[1] : null;
    }

    private function couponCodeFromQuery(string $query): ?string
    {
        return preg_match('/(?:mã(?:\s+giảm(?:\s+giá)?)?|voucher)\s*[:#]?\s*([A-Z0-9][A-Z0-9_-]{2,})\b/iu', $query, $matches) === 1 ? $matches[1] : null;
    }

    /** @return list<string> */
    private function keywords(string $query): array
    {
        return collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($query)) ?: [])->filter(fn (string $word) => mb_strlen($word) >= 2 && ! in_array($word, $this->stopWords, true))->unique()->take(8)->values()->all();
    }

    private function isTrendingIntent(string $query): bool
    {
        return $this->containsAny($query, ['quan tâm', 'xem nhiều', 'lượt xem', 'hot', 'bán chạy', 'yêu thích', 'thịnh hành', 'top', 'xu hướng']);
    }

    private function isCatalogSummaryIntent(string $query): bool
    {
        return $this->isTrendingIntent($query) || $this->containsAny($query, ['bao nhiêu sách', 'bao nhiêu cuốn', 'số sách', 'có cuốn nào', 'có sách gì', 'gian hàng có bao nhiêu', 'shop có bao nhiêu', 'hệ thống có bao nhiêu']);
    }

    private function detectHumanSupportIntent(string $text): bool
    {
        return $this->containsAny($text, ['tư vấn viên', 'nhân viên', 'người thật', 'gặp shop', 'gặp admin', 'hỗ trợ trực tiếp']);
    }

    private function detectBookIntent(string $text): bool
    {
        return $this->containsAny($text, ['gợi ý', 'tìm sách', 'mua sách', 'sách hay', 'sách này', 'cuốn', 'tập', 'tác giả', 'thể loại', 'ebook', 'sách giấy', 'quan tâm', 'xem nhiều', 'hot', 'bán chạy']);
    }

    private function detectCouponIntent(string $text): bool
    {
        return $this->couponCodeFromQuery($text) !== null || $this->containsAny($text, ['voucher', 'mã giảm', 'khuyến mại', 'ưu đãi', 'gợi ý mã', 'mã hội', 'mã shop', 'mã sàn']);
    }

    private function detectOrderIntent(string $text): bool
    {
        return $this->orderCodeFromQuery($text) !== null || $this->containsAny($text, ['tra cứu đơn', 'mã đơn', 'đơn của tôi', 'đơn hàng của tôi', 'đơn gần đây', 'đơn mới nhất', 'trạng thái đơn', 'vận đơn của tôi', 'bao giờ giao', 'đang giao']);
    }

    private function detectArticleIntent(string $text): bool
    {
        return $this->containsAny($text, ['bài viết', 'tin tức', 'tóm tắt', 'bản tin']);
    }

    private function detectHelpIntent(string $text): bool
    {
        return $this->containsAny($text, ['hỗ trợ', 'chính sách', 'đổi trả', 'trả hàng', 'hoàn tiền', 'giao hàng', 'thanh toán', 'tài khoản', 'đăng nhập', 'ngoại tuyến', 'bản quyền', 'in ấn', 'dịch vụ']);
    }

    private function detectMembershipIntent(string $text): bool
    {
        return $this->containsAny($text, ['vip', 'hạng', 'thành viên', 'komipoint', 'điểm', 'quyền lợi']);
    }

    /** @param list<string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        $haystack = mb_strtolower($haystack);

        return collect($needles)->contains(fn (string $needle) => str_contains($haystack, $needle));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}

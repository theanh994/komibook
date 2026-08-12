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
        $contextBook = null;
        if ($intent === 'generic' && $contextBookId) {
            $contextBook = $this->findContextBook($scope, $contextBookId);
            if ($contextBook !== null) {
                $intent = 'book';
            }
        }
        if ($intent === 'human_support') {
            return $this->envelope([], [], [], [], null, null, $scope, 'matched', 'human_support', $intent);
        }

        $books = [];
        $articles = [];
        $help = [];
        $tiers = [];
        $coupons = [];
        $orders = [];
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
                $contextBook ??= $contextBookId ? $this->findContextBook($scope, $contextBookId) : null;
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

            case 'generic':
                // A bare public title, author, category, ISBN, or organization name
                // should still be searchable without guessing from unrelated records.
                $books = $this->searchBooks($scope, $query, 6);
                if ($books !== []) {
                    $intent = 'book';
                    [$state, $reason] = ['matched', 'matched'];
                }
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
        if ($this->detectStrongBookIntent($query)) {
            return 'book';
        }
        if ($this->detectArticleIntent($query)) {
            return 'article';
        }
        if ($this->detectHelpIntent($query)) {
            return 'help';
        }
        if ($this->detectWeakBookFacetIntent($query)) {
            return 'book';
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
            $rating = $book['rating_avg'] === null
                ? ' · Đánh giá: chưa có nhận xét công khai'
                : " · Đánh giá {$book['rating_avg']}/5★ ({$book['review_count']} nhận xét)";
            $wishlist = ! empty($book['wishlist_count']) ? " · {$book['wishlist_count']} lượt Yêu thích" : '';
            $views = ! empty($book['views']) ? " · {$book['views']} lượt xem" : '';
            $detail = $this->bookSourceContent($book);
            $sources[] = ['type' => 'book', 'id' => $book['id'], 'title' => $book['display_title'], 'url' => "/book/{$book['slug']}", 'content' => "{$detail}{$rating}{$wishlist}{$views}{$series}"];
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
        $builder = $this->bookDetailsQuery($scope);
        if ($this->isTrendingIntent($query) || $this->isCatalogSummaryIntent($query) && $keywords === []) {
            $books = $builder->get()->sortByDesc(fn (Book $book) => ($book->views ?? 0) + (($book->wishlists_count ?? 0) * 50) + (($book->public_reviews_count ?? 0) * 100))->take($limit);
        } else {
            if ($keywords === []) {
                return [];
            }
            $builder->where(function (Builder $search) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $like = '%'.$this->escapeLike($keyword).'%';
                    $search->orWhere('books.title', 'like', $like)
                        ->orWhere('books.author', 'like', $like)
                        ->orWhere('books.translator', 'like', $like)
                        ->orWhere('books.isbn', 'like', $like)
                        ->orWhere('books.description', 'like', $like)
                        ->orWhereHas('series', fn (Builder $series) => $series->where('title', 'like', $like))
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like))
                        ->orWhereHas('categories', fn (Builder $category) => $category->where('name', 'like', $like))
                        ->orWhereHas('activeCommercialParties', fn (Builder $parties) => $parties
                            ->whereIn('role', ['publisher', 'supplier', 'responsible_organization'])
                            ->whereHas('organization', fn (Builder $organization) => $organization->where('display_name', 'like', $like)));
                }
            });
            $books = $builder->get()->sortByDesc('views')->take($limit);
        }

        return $books->values()->map(fn (Book $book) => $this->bookPayload($scope, $book))->all();
    }

    /** @param array<string, mixed> $scope */
    private function findContextBook(array $scope, int $bookId): ?array
    {
        $book = $this->bookDetailsQuery($scope)->find($bookId);

        return $book ? $this->bookPayload($scope, $book) : null;
    }

    /** @param array<string, mixed> $scope */
    private function publicBooks(array $scope): Builder
    {
        return Book::withoutGlobalScopes()->purchasable()->when($scope['vendor'], fn (Builder $query, Vendor $vendor) => $query->where('books.vendor_id', $vendor->id));
    }

    /** @param array<string, mixed> $scope */
    private function bookDetailsQuery(array $scope): Builder
    {
        return $this->publicBooks($scope)
            ->with([
                'category:id,name',
                'categories:id,name',
                'vendor:id,shop_name,slug',
                'series:id,title',
                'activeCommercialParties' => fn ($parties) => $parties
                    ->select(['id', 'book_id', 'organization_id', 'role'])
                    ->whereIn('role', ['publisher', 'supplier', 'responsible_organization']),
                'activeCommercialParties.organization:id,display_name',
            ])
            ->withCount([
                'reviews as public_reviews_count' => fn (Builder $reviews) => $reviews
                    ->where('active_key', 1)
                    ->where('moderation_status', 'published'),
                'wishlists',
            ])
            ->withAvg([
                'reviews as public_rating_avg' => fn (Builder $reviews) => $reviews
                    ->where('active_key', 1)
                    ->where('moderation_status', 'published'),
            ], 'rating');
    }

    /** @param array<string, mixed> $scope */
    private function bookPayload(array $scope, Book $book): array
    {
        $seriesBooks = $book->series_id ? $this->publicBooks($scope)->where('series_id', $book->series_id)->orderBy('title')->pluck('title')->all() : [];
        $categoryNames = collect([$book->category?->name])
            ->merge($book->categories->pluck('name'))
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->unique()
            ->values()
            ->all();
        $commercialParties = $book->activeCommercialParties
            ->mapWithKeys(function ($party): array {
                $name = $party->organization?->display_name;

                return is_string($name) && trim($name) !== '' ? [$party->role => trim($name)] : [];
            })
            ->all();

        return [
            'id' => $book->id,
            'slug' => $book->slug,
            'title' => $book->title,
            'display_title' => $book->display_title,
            'print_edition' => max(1, (int) ($book->print_edition ?? 1)),
            'edition_label' => $this->editionLabel($book->print_edition),
            'author' => $book->author,
            'translator' => $book->translator,
            'description' => Str::limit(trim(strip_tags((string) $book->description)), 700),
            'isbn' => $book->isbn,
            'dimensions' => $book->dimensions,
            'cover_format' => $book->cover_format,
            'weight' => $book->weight,
            'language' => $book->language,
            'target_age' => $book->target_age,
            'pages' => $book->pages,
            'release_date' => $book->release_date,
            'price' => $book->price,
            'sale_price' => $book->sale_price,
            'display_price' => $book->currentPrice(),
            'cover_image' => $book->cover_image,
            'type' => $book->type,
            'format' => $book->format ?? $book->type,
            'stock' => $book->stock,
            'category_name' => $categoryNames[0] ?? null,
            'category_names' => $categoryNames,
            'vendor_name' => $book->vendor?->shop_name,
            'commercial_parties' => $commercialParties,
            'rating_avg' => $book->public_rating_avg === null ? null : round((float) $book->public_rating_avg, 1),
            'review_count' => (int) ($book->public_reviews_count ?? 0),
            'wishlist_count' => (int) ($book->wishlists_count ?? 0),
            'views' => (int) ($book->views ?? 0),
            'series_title' => $book->series?->title,
            'series_book_count' => count($seriesBooks),
            'series_books' => $seriesBooks,
        ];
    }

    /** @param array<string, mixed> $book */
    private function bookSourceContent(array $book): string
    {
        $parts = [
            "{$book['display_title']} — Tác giả: {$book['author']}",
            "Ấn bản: {$book['edition_label']}",
            'Giá niêm yết: '.number_format((int) $book['price']).'đ',
            'Giá đang áp dụng: '.number_format((int) $book['display_price']).'đ',
            "Loại: {$book['type']}",
            'Tồn kho hiển thị: '.((int) $book['stock']),
        ];

        foreach ([
            'translator' => 'Dịch giả', 'isbn' => 'ISBN', 'format' => 'Định dạng', 'dimensions' => 'Kích thước',
            'cover_format' => 'Bìa', 'weight' => 'Trọng lượng', 'language' => 'Ngôn ngữ', 'target_age' => 'Độ tuổi',
            'pages' => 'Số trang', 'release_date' => 'Ngày phát hành',
        ] as $field => $label) {
            if ($book[$field] !== null && $book[$field] !== '') {
                $parts[] = "{$label}: {$book[$field]}";
            }
        }

        if ($book['category_names'] !== []) {
            $parts[] = 'Thể loại: '.implode(', ', $book['category_names']);
        }
        foreach (['publisher' => 'Nhà xuất bản', 'supplier' => 'Nhà cung cấp', 'responsible_organization' => 'Đơn vị chịu trách nhiệm'] as $role => $label) {
            if (isset($book['commercial_parties'][$role])) {
                $parts[] = "{$label}: {$book['commercial_parties'][$role]}";
            }
        }
        if ($book['description'] !== null && $book['description'] !== '') {
            $parts[] = "Mô tả: {$book['description']}";
        }

        return implode('; ', $parts).'.';
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

    private function detectStrongBookIntent(string $text): bool
    {
        return $this->containsAny($text, ['gợi ý', 'tìm sách', 'mua sách', 'sách hay', 'sách này', 'tập', 'tác giả', 'thể loại', 'ebook', 'sách giấy', 'quan tâm', 'xem nhiều', 'hot', 'bán chạy', 'isbn', 'dịch giả', 'người dịch', 'số trang', 'kích thước', 'khổ sách', 'trọng lượng', 'ngôn ngữ', 'độ tuổi', 'ngày phát hành', 'tái bản', 'ấn bản', 'nhà xuất bản', 'nxb', 'nhà cung cấp', 'đơn vị chịu trách nhiệm', 'tồn kho', 'thông tin sách']);
    }

    private function detectWeakBookFacetIntent(string $text): bool
    {
        return $this->containsAny($text, ['mô tả', 'nội dung', 'giới thiệu', 'giá', 'đánh giá', 'nhận xét', 'bìa'])
            && $this->hasBookAnchor($text);
    }

    private function hasBookAnchor(string $text): bool
    {
        $text = str_replace('chính sách', '', mb_strtolower($text));

        return preg_match('/(?:^|[^\p{L}\p{N}])(?:sách|cuốn|tựa)(?:$|[^\p{L}\p{N}])/u', $text) === 1;
    }

    private function editionLabel(mixed $edition): string
    {
        $edition = max(1, (int) $edition);

        return $edition === 1 ? 'Ấn bản đầu tiên' : "Tái bản lần {$edition}";
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

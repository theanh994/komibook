<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\ChatSession;
use App\Models\HelpArticle;
use App\Models\MembershipTier;
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
     * @return array{context: string, sources: array<int, array<string, mixed>>, entries: array<int, array<string, mixed>>, recommended_books: array<int, array<string, mixed>>, context_book: array<string, mixed>|null, catalog_summary: array<string, mixed>|null}
     */
    public function buildKnowledge(ChatSession $session, string $query, ?int $contextBookId = null): array
    {
        $contextBook = $contextBookId ? $this->findContextBook($session, $contextBookId) : null;
        $books = $this->searchBooks($session, $query, 6);
        if ($contextBook && collect($books)->doesntContain('id', $contextBook['id'])) {
            array_unshift($books, $contextBook);
            $books = array_slice($books, 0, 6);
        }
        $articles = $this->searchArticles($query, 3);
        $help = $this->searchHelpArticles($query, 3);
        $tiers = $this->searchMembershipTiers($query);
        $catalogSummary = ($contextBook || $this->isCatalogIntent($query))
            ? $this->catalogSummary($session)
            : null;

        $sources = [];
        if ($catalogSummary) {
            $sources[] = [
                'type' => 'catalog_summary',
                'id' => $session->target_type === ChatSession::TARGET_VENDOR ? $session->vendor_id : 0,
                'title' => $catalogSummary['scope_name'],
                'url' => $session->target_type === ChatSession::TARGET_VENDOR && $session->vendor?->slug
                    ? "/shops/{$session->vendor->slug}"
                    : '/catalog',
                'content' => "Có {$catalogSummary['total_titles']} đầu sách đã công bố; {$catalogSummary['physical_available']} sách giấy đang còn hàng; {$catalogSummary['ebook_titles']} ebook. Một số sách tiêu biểu: ".implode(', ', $catalogSummary['sample_titles']).'.',
            ];
        }

        foreach ($books as $book) {
            $series = $book['series_title']
                ? " Thuộc bộ {$book['series_title']} với {$book['series_book_count']} đầu sách đang công bố: ".implode(', ', $book['series_books']).'.'
                : '';
            $sources[] = [
                'type' => 'book',
                'id' => $book['id'],
                'title' => $book['title'],
                'url' => "/book/{$book['slug']}",
                'content' => "{$book['title']} — {$book['author']}; giá {$book['display_price']}đ; loại {$book['type']}; tồn kho hiển thị {$book['stock']}; {$book['category_name']}; gian hàng {$book['vendor_name']}. {$book['description']}{$series}",
            ];
        }

        foreach ($articles as $article) {
            $sources[] = [
                'type' => 'article',
                'id' => $article['id'],
                'title' => $article['title'],
                'url' => "/blog/{$article['slug']}",
                'content' => $article['content'],
            ];
        }

        foreach ($help as $article) {
            $sources[] = [
                'type' => 'help',
                'id' => $article['id'],
                'title' => $article['title'],
                'url' => '/help-center',
                'content' => $article['content'],
            ];
        }

        foreach ($tiers as $tier) {
            $sources[] = [
                'type' => 'membership',
                'id' => $tier['id'],
                'title' => "Hạng {$tier['name']}",
                'url' => '/profile?tab=membership',
                'content' => "Từ {$tier['min_points']} điểm; giảm {$tier['discount_percent']}% khi checkout. Người dùng nhận 1 KomiPoint cho mỗi 10.000đ giá trị đơn hoàn tất.",
            ];
        }

        $entries = collect($sources)->values()->map(function (array $source, int $index) {
            $source['citation'] = 'S'.($index + 1);

            return $source;
        })->all();

        $context = collect($entries)->map(fn (array $source) => "[{$source['citation']}] {$source['title']}\n{$source['content']}")
            ->implode("\n\n");

        return [
            'context' => $context,
            'sources' => collect($entries)->map(function (array $source) {
                unset($source['content']);

                return $source;
            })->all(),
            'entries' => $entries,
            'recommended_books' => $books,
            'context_book' => $contextBook,
            'catalog_summary' => $catalogSummary,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function searchBooks(ChatSession $session, string $query, int $limit = 6): array
    {
        $keywords = collect($this->keywords($query))
            ->reject(fn (string $keyword) => in_array($keyword, ['gợi', 'ý', 'sách', 'hay', 'mua', 'tìm', 'đọc'], true))
            ->values()
            ->all();
        $genericRecommendation = $keywords === [] && $this->isCatalogIntent($query);
        if ($keywords === [] && ! $genericRecommendation) {
            return [];
        }

        $books = Book::withoutGlobalScopes()
            ->sellable()
            ->with(['category:id,name', 'vendor:id,shop_name,slug'])
            ->when($session->target_type === ChatSession::TARGET_VENDOR, fn (Builder $builder) => $builder->where('books.vendor_id', $session->vendor_id))
            ->when($keywords !== [], function (Builder $builder) use ($keywords) {
                $builder->where(function (Builder $search) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $like = '%'.$this->escapeLike($keyword).'%';
                        $search->orWhere('books.title', 'like', $like)
                            ->orWhere('books.author', 'like', $like)
                            ->orWhere('books.description', 'like', $like)
                            ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
                    }
                });
            })
            ->orderByDesc('books.views')
            ->limit($limit)
            ->get();

        return $books->map(fn (Book $book) => $this->bookPayload($session, $book))->all();
    }

    /** @return array<string, mixed>|null */
    private function findContextBook(ChatSession $session, int $bookId): ?array
    {
        $book = Book::withoutGlobalScopes()
            ->sellable()
            ->with(['category:id,name', 'vendor:id,shop_name,slug', 'series:id,title'])
            ->when($session->target_type === ChatSession::TARGET_VENDOR, fn (Builder $builder) => $builder->where('books.vendor_id', $session->vendor_id))
            ->find($bookId);

        return $book ? $this->bookPayload($session, $book) : null;
    }

    /** @return array<string, mixed> */
    private function bookPayload(ChatSession $session, Book $book): array
    {
        $seriesBooks = [];
        if ($book->series_id) {
            $seriesBooks = Book::withoutGlobalScopes()
                ->sellable()
                ->where('series_id', $book->series_id)
                ->when($session->target_type === ChatSession::TARGET_VENDOR, fn (Builder $builder) => $builder->where('vendor_id', $session->vendor_id))
                ->orderBy('title')
                ->pluck('title')
                ->all();
        }

        return [
            'id' => $book->id,
            'slug' => $book->slug,
            'title' => $book->title,
            'author' => $book->author,
            'price' => $book->price,
            'sale_price' => $book->sale_price,
            'display_price' => $book->currentPrice(),
            'cover_image' => $book->cover_image,
            'type' => $book->type,
            'stock' => $book->stock,
            'category_name' => $book->category?->name ?? 'Chưa phân loại',
            'vendor_name' => $book->vendor?->shop_name ?? 'KomiBook',
            'description' => Str::limit(trim(strip_tags((string) $book->description)), 240),
            'series_title' => $book->series?->title,
            'series_book_count' => count($seriesBooks),
            'series_books' => $seriesBooks,
        ];
    }

    /** @return array<string, mixed> */
    private function catalogSummary(ChatSession $session): array
    {
        $base = Book::withoutGlobalScopes()
            ->sellable()
            ->when($session->target_type === ChatSession::TARGET_VENDOR, fn (Builder $builder) => $builder->where('books.vendor_id', $session->vendor_id));
        $sampleTitles = (clone $base)->browseVisible()->orderByDesc('views')->limit(5)->pluck('title')->all();
        $scopeName = $session->target_type === ChatSession::TARGET_VENDOR
            ? 'Danh mục của '.($session->vendor?->shop_name ?? 'gian hàng này')
            : 'Danh mục toàn hệ thống KomiBook';

        return [
            'scope_name' => $scopeName,
            'total_titles' => (clone $base)->count(),
            'physical_available' => (clone $base)->where('type', 'physical')->where('stock', '>', 0)->count(),
            'ebook_titles' => (clone $base)->where('type', 'ebook')->count(),
            'sample_titles' => $sampleTitles,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function searchArticles(string $query, int $limit = 3): array
    {
        $keywords = $this->keywords($query);
        if ($keywords === [] || ! $this->containsAny($query, ['bài viết', 'tin tức', 'tóm tắt', 'bản tin'])) {
            return [];
        }

        return Article::query()
            ->where('status', ArticleStatus::Published->value)
            ->where(function (Builder $builder) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $like = '%'.$this->escapeLike($keyword).'%';
                    $builder->orWhere('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body', 'like', $like);
                }
            })
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Article $article) => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'content' => Str::limit(trim(strip_tags((string) ($article->body ?: $article->excerpt))), 1200),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchHelpArticles(string $query, int $limit = 3): array
    {
        if (! $this->containsAny($query, ['hỗ trợ', 'chính sách', 'đổi trả', 'trả hàng', 'hoàn tiền', 'giao hàng', 'thanh toán', 'tài khoản', 'đăng nhập', 'ngoại tuyến', 'bản quyền', 'in ấn', 'dịch vụ'])) {
            return [];
        }

        $keywords = $this->keywords($query);
        if ($keywords === []) {
            return [];
        }

        return HelpArticle::publicKnowledge()
            ->where(function (Builder $builder) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $like = '%'.$this->escapeLike($keyword).'%';
                    $builder->orWhere('title', 'like', $like)->orWhere('content', 'like', $like)->orWhere('category_name', 'like', $like);
                }
            })
            ->orderByDesc('helpful_count')
            ->limit($limit)
            ->get()
            ->map(fn (HelpArticle $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'content' => Str::limit(trim(strip_tags((string) $article->content)), 900),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchMembershipTiers(string $query): array
    {
        if (! $this->containsAny($query, ['vip', 'hạng', 'thành viên', 'komipoint', 'điểm', 'quyền lợi'])) {
            return [];
        }

        return MembershipTier::query()->orderBy('min_points')->get()->map(fn (MembershipTier $tier) => [
            'id' => $tier->id,
            'name' => $tier->name,
            'min_points' => $tier->min_points,
            'discount_percent' => $tier->discount_percent,
        ])->all();
    }

    /** @return list<string> */
    private function keywords(string $query): array
    {
        return collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($query)) ?: [])
            ->filter(fn (string $word) => mb_strlen($word) >= 2 && ! in_array($word, $this->stopWords, true))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function isCatalogIntent(string $query): bool
    {
        return $this->containsAny($query, [
            'sách', 'cuốn', 'tập', 'ebook', 'tác giả', 'thể loại', 'gian hàng', 'shop',
            'catalog', 'còn hàng', 'tồn kho', 'bao nhiêu', 'gợi ý', 'mua', 'đọc',
        ]);
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

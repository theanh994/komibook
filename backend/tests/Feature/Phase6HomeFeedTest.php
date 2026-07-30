<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase6HomeFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_recommendations_use_popular_fallback_and_only_return_sellable_books(): void
    {
        $vendor = $this->activeVendor();
        $category = Category::create(['name' => 'Văn học', 'slug' => 'van-hoc']);
        $visible = $this->book($vendor, $category, 'visible', 40);
        $this->book($vendor, $category, 'less-popular', 10);
        $this->book($vendor, $category, 'draft', 100)->update(['status' => 'draft']);

        $this->getJson('/api/books/recommendations')
            ->assertOk()
            ->assertJsonPath('recommendation.mode', 'popular_fallback')
            ->assertJsonPath('recommendation.explanation', 'Phổ biến với độc giả KomiBook')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_authenticated_user_recommendations_follow_explicit_favorite_categories(): void
    {
        $vendor = $this->activeVendor();
        $favorite = Category::create(['name' => 'Kỹ năng', 'slug' => 'ky-nang']);
        $other = Category::create(['name' => 'Kinh tế', 'slug' => 'kinh-te']);
        $preferredBook = $this->book($vendor, $favorite, 'preferred', 1);
        $this->book($vendor, $other, 'popular-other', 500);

        $user = User::factory()->create(['role' => 'customer']);
        $user->favoriteCategories()->attach($favorite->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/books/recommendations')
            ->assertOk()
            ->assertJsonPath('recommendation.mode', 'favorite_categories')
            ->assertJsonPath('recommendation.explanation', 'Dựa trên thể loại bạn đã chọn')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $preferredBook->id);
    }

    public function test_home_recommendations_are_limited_to_five_books(): void
    {
        $vendor = $this->activeVendor();
        $category = Category::create(['name' => 'Giới hạn gợi ý', 'slug' => 'gioi-han-goi-y']);

        foreach (range(1, 7) as $index) {
            $this->book($vendor, $category, 'recommendation-'.$index, $index);
        }

        $this->getJson('/api/books/recommendations')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_vendor_feed_only_exposes_published_articles_and_safe_creator_identity(): void
    {
        $author = User::factory()->create([
            'name' => 'Nhà xuất bản An Toàn',
            'email' => 'private-publisher@example.test',
            'role' => 'vendor',
        ]);
        $category = ArticleCategory::create(['name' => 'Sách mới', 'slug' => 'sach-moi']);

        Article::create([
            'created_by' => $author->id,
            'article_category_id' => $category->id,
            'title' => 'Bài đã duyệt',
            'slug' => 'bai-da-duyet',
            'excerpt' => 'Bản tin sách mới.',
            'body' => '<p>Nội dung</p>',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        Article::create([
            'created_by' => $author->id,
            'article_category_id' => $category->id,
            'title' => 'Bản nháp',
            'slug' => 'ban-nhap',
            'body' => '<p>Không được lộ</p>',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/articles?per_page=6');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Bài đã duyệt')
            ->assertJsonPath('data.data.0.creator.name', 'Nhà xuất bản An Toàn')
            ->assertJsonMissing(['email' => 'private-publisher@example.test']);
    }

    public function test_sample_filter_only_returns_sellable_books_with_free_chapters(): void
    {
        $vendor = $this->activeVendor();
        $category = Category::create(['name' => 'Ebook', 'slug' => 'ebook']);
        $withSample = $this->book($vendor, $category, 'with-sample', 5);
        $withoutSample = $this->book($vendor, $category, 'without-sample', 10);
        $withSample->update(['type' => 'ebook', 'format' => 'ebook', 'stock' => 0]);
        $withoutSample->update(['type' => 'ebook', 'format' => 'ebook', 'stock' => 0]);
        $withSample->chapters()->create([
            'title' => 'Đọc thử',
            'content' => '<p>Nội dung đọc thử</p>',
            'order' => 1,
            'is_free' => true,
        ]);

        $this->getJson('/api/books?type=ebook&has_sample=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withSample->id);
    }

    private function activeVendor(): Vendor
    {
        $user = User::factory()->create(['role' => 'vendor']);

        return Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Home Feed Shop',
            'slug' => 'home-feed-shop',
            'status' => 'active',
        ]);
    }

    private function book(Vendor $vendor, Category $category, string $slug, int $views): Book
    {
        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => $slug,
            'slug' => $slug,
            'author' => 'KomiBook',
            'price' => 10000,
            'stock' => 1,
            'views' => $views,
            'type' => 'physical',
            'format' => 'physical',
            'provenance' => 'publisher_catalog',
            'fulfillment_mode' => 'vendor_warehouse',
            'status' => 'published',
        ]);
    }
}

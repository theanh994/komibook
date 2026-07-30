<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\ReturnPolicyVersion;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_popular_categories_count_only_sellable_books_from_legacy_and_pivot_relations(): void
    {
        $vendor = $this->activeVendor();
        $popular = Category::create(['name' => 'Phổ biến', 'slug' => 'pho-bien']);
        $other = Category::create(['name' => 'Khác', 'slug' => 'khac']);

        $this->book($vendor, 'legacy-popular', 'publisher_catalog', $popular->id);
        $pivotBook = $this->book($vendor, 'pivot-popular', 'publisher_catalog');
        $pivotBook->categories()->attach($popular->id);
        $this->book($vendor, 'other', 'publisher_catalog', $other->id);

        $draft = $this->book($vendor, 'draft', 'publisher_catalog', $popular->id);
        $draft->update(['status' => 'draft']);

        $response = $this->getJson('/api/categories?popular=1&limit=10');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $popular->id)
            ->assertJsonPath('data.0.published_books_count', 2);
    }

    public function test_catalog_can_filter_used_resale_books(): void
    {
        $vendor = $this->activeVendor();
        $used = $this->book($vendor, 'used-book', 'used_resale');
        $this->book($vendor, 'publisher-book', 'publisher_catalog');

        $response = $this->getJson('/api/books?provenance=used_resale');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $used->id)
            ->assertJsonPath('data.0.provenance', 'used_resale');
    }

    public function test_catalog_can_combine_target_age_and_effective_price_filters(): void
    {
        $vendor = $this->activeVendor();
        $matching = $this->book($vendor, 'matching-filter', 'publisher_catalog');
        $matching->update(['target_age' => '12-17', 'price' => 150000, 'sale_price' => 90000]);
        $this->book($vendor, 'wrong-age', 'publisher_catalog')
            ->update(['target_age' => '18+', 'price' => 90000]);
        $this->book($vendor, 'wrong-price', 'publisher_catalog')
            ->update(['target_age' => '12-17', 'price' => 220000]);

        $this->getJson('/api/books?target_age=12-17&min_price=50000&max_price=100000')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_public_return_policies_expose_latest_active_versions_without_mutation(): void
    {
        ReturnPolicyVersion::create([
            'policy_key' => 'ebook_non_returnable',
            'version' => 2,
            'applies_to' => 'ebook',
            'is_returnable' => false,
            'return_window_days' => null,
            'terms' => 'Điều khoản ebook phiên bản kiểm thử.',
            'active_from' => now()->subMinute(),
        ]);
        ReturnPolicyVersion::create([
            'policy_key' => 'ebook_non_returnable',
            'version' => 3,
            'applies_to' => 'ebook',
            'is_returnable' => false,
            'return_window_days' => null,
            'terms' => 'Phiên bản chưa có hiệu lực.',
            'active_from' => now()->addDay(),
        ]);

        $before = ReturnPolicyVersion::count();

        $this->getJson('/api/policies/returns')
            ->assertOk()
            ->assertJsonPath('data.ebook_non_returnable.version', 2)
            ->assertJsonPath('data.ebook_non_returnable.is_returnable', false)
            ->assertJsonPath('data.used_book_return.version', 1)
            ->assertJsonPath('data.used_book_return.return_window_days', 7);

        $this->assertSame($before, ReturnPolicyVersion::count());
    }

    private function activeVendor(): Vendor
    {
        $user = User::factory()->create(['role' => 'vendor']);

        return Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Phase 6 Shop',
            'slug' => 'phase-6-shop',
            'status' => 'active',
        ]);
    }

    private function book(
        Vendor $vendor,
        string $slug,
        string $provenance,
        ?int $categoryId = null,
    ): Book {
        $categoryId ??= Category::create([
            'name' => "Fallback {$slug}",
            'slug' => "fallback-{$slug}",
        ])->id;

        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $categoryId,
            'title' => $slug,
            'slug' => $slug,
            'author' => 'KomiBook',
            'price' => 10000,
            'stock' => 1,
            'type' => 'physical',
            'format' => 'physical',
            'provenance' => $provenance,
            'condition' => $provenance === 'used_resale' ? 'good' : null,
            'fulfillment_mode' => $provenance === 'used_resale'
                ? 'seller_verified_address'
                : 'vendor_warehouse',
            'status' => 'published',
        ]);
    }
}

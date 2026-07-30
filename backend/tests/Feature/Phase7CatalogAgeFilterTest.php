<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7CatalogAgeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_restored_age_groups_find_canonical_and_legacy_book_values(): void
    {
        $vendor = $this->activeVendor();
        $groups = [
            'Nhà trẻ - mẫu giáo (0 - 6)' => '0-5',
            'Nhi đồng (6 - 11)' => '6-11',
            'Thiếu niên (11 - 15)' => '11-15',
            'Tuổi mới lớn (15 - 18)' => '12-17',
            'Tuổi trưởng thành (Trên 18 tuổi)' => '18+',
        ];

        $index = 0;
        foreach ($groups as $canonicalValue => $legacyValue) {
            $canonical = $this->book($vendor, "canonical-{$index}", $canonicalValue);
            $legacy = $this->book($vendor, "legacy-{$index}", $legacyValue);

            $this->getJson('/api/books?'.http_build_query(['target_age' => $canonicalValue]))
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonFragment(['id' => $canonical->id])
                ->assertJsonFragment(['id' => $legacy->id]);

            $index++;
        }
    }

    public function test_unknown_custom_age_value_keeps_exact_match_behavior(): void
    {
        $vendor = $this->activeVendor();
        $custom = $this->book($vendor, 'custom-age', 'Độc giả chuyên ngành');
        $this->book($vendor, 'adult-age', '18+');

        $this->getJson('/api/books?'.http_build_query(['target_age' => 'Độc giả chuyên ngành']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $custom->id);
    }

    private function activeVendor(): Vendor
    {
        $user = User::factory()->create(['role' => 'vendor']);

        return Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Phase 7 Catalog Shop',
            'slug' => 'phase-7-catalog-shop',
            'status' => 'active',
        ]);
    }

    private function book(Vendor $vendor, string $slug, string $targetAge): Book
    {
        $category = Category::create([
            'name' => "Category {$slug}",
            'slug' => "category-{$slug}",
        ]);

        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => $slug,
            'slug' => $slug,
            'author' => 'KomiBook',
            'price' => 10000,
            'stock' => 1,
            'type' => 'physical',
            'format' => 'physical',
            'provenance' => 'publisher_catalog',
            'fulfillment_mode' => 'vendor_warehouse',
            'target_age' => $targetAge,
            'status' => 'published',
        ]);
    }
}

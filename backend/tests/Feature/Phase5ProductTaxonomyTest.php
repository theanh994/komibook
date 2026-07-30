<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ProductTaxonomyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase5ProductTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unspecified_provenance_defaults_safely_to_publisher_catalog(): void
    {
        $service = app(ProductTaxonomyService::class);
        $physical = Book::withoutGlobalScopes()->create($service->normalize($this->bookData('physical')));
        $ebook = Book::withoutGlobalScopes()->create($service->normalize($this->bookData('ebook', 'ebook-legacy')));

        $this->assertSame('publisher_catalog', $physical->fresh()->provenance);
        $this->assertSame('vendor_warehouse', $physical->fresh()->fulfillment_mode);
        $this->assertSame('publisher_catalog', $ebook->fresh()->provenance);
        $this->assertSame('digital', $ebook->fresh()->fulfillment_mode);
    }

    public function test_taxonomy_service_assigns_policy_for_ebook_and_used_book(): void
    {
        $service = app(ProductTaxonomyService::class);

        $ebook = $service->normalize([
            'type' => 'ebook',
            'provenance' => 'self_published',
        ]);
        $used = $service->normalize([
            'type' => 'physical',
            'provenance' => 'used_resale',
            'condition' => 'good',
            'fulfillment_mode' => 'seller_verified_address',
        ]);

        $this->assertSame('digital', $ebook['fulfillment_mode']);
        $this->assertNotNull($ebook['return_policy_version_id']);
        $this->assertSame('seller_verified_address', $used['fulfillment_mode']);
        $this->assertNotSame($ebook['return_policy_version_id'], $used['return_policy_version_id']);
    }

    public function test_invalid_taxonomy_combinations_are_rejected(): void
    {
        $service = app(ProductTaxonomyService::class);

        foreach ([
            ['type' => 'ebook', 'provenance' => 'used_resale', 'condition' => 'good'],
            ['type' => 'physical', 'provenance' => 'publisher_catalog', 'fulfillment_mode' => 'digital'],
            ['type' => 'physical', 'provenance' => 'used_resale', 'condition' => null],
        ] as $payload) {
            try {
                $service->normalize($payload);
                $this->fail('Taxonomy không hợp lệ phải bị chặn.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function bookData(string $type, string $slug = 'physical-legacy'): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => "Shop {$slug}",
            'slug' => "shop-{$slug}",
            'status' => 'active',
        ]);
        $category = Category::create([
            'name' => "Danh mục {$slug}",
            'slug' => "danh-muc-{$slug}",
        ]);

        return [
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Legacy',
            'slug' => $slug,
            'author' => 'Legacy',
            'price' => 10000,
            'stock' => 1,
            'type' => $type,
            'status' => 'draft',
        ];
    }
}

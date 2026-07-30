<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Phase8ReadinessManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8ReadinessManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_stops_automatic_vendor_to_supplier_backfill_and_performs_no_writes(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Nhà sách đa nguồn',
            'slug' => 'phase-8-bookstore',
            'status' => 'active',
            'onboarding_status' => 'approved',
        ]);
        $category = Category::create(['name' => 'Phase 8', 'slug' => 'phase-8']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách chưa có hồ sơ chuỗi cung ứng',
            'slug' => 'phase-8-missing-parties',
            'author' => 'KomiBook',
            'price' => 100000,
            'stock' => 1,
            'type' => 'physical',
            'status' => 'published',
        ]);

        $before = $book->fresh()->updated_at?->toISOString();
        $manifest = app(Phase8ReadinessManifestService::class)->build();

        $this->assertSame('dry_run', $manifest['mode']);
        $this->assertFalse($manifest['writes_performed']);
        $this->assertSame('stop_automatic_backfill_and_review', $manifest['decision']);
        $this->assertSame('insufficient_evidence', $manifest['records'][0]['classification']);
        $this->assertSame('collect_publisher_supplier_responsible_evidence', $manifest['records'][0]['recommended_action']);
        $this->assertSame($before, $book->fresh()->updated_at?->toISOString());
    }

    public function test_command_returns_json_without_private_vendor_fields(): void
    {
        $this->artisan('phase8:readiness-manifest')
            ->expectsOutputToContain('"mode":"dry_run","writes_performed":false')
            ->assertSuccessful();
    }
}

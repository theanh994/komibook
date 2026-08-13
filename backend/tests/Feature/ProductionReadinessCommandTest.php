<?php

namespace Tests\Feature;

use App\Console\Commands\CheckProductionReadiness;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\InventoryReservation;
use App\Models\InventoryReservationAllocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerFulfillmentAddress;
use App\Models\UsedBookListing;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\ProductionMediaIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class ProductionReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_passes_when_every_runtime_contract_is_satisfied(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.public_media_references' => []]);

        try {
            $this->artisan('production:readiness', ['--json' => true])
                ->expectsOutputToContain('"status": "ready"')
                ->assertSuccessful();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_json_mode_is_valid_and_ready(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.public_media_references' => []]);

        try {
            $this->assertSame(0, Artisan::call('production:readiness', ['--json' => true]));
            $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('ready', $payload['status']);
            $this->assertIsArray($payload['checks']);
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_table_mode_renders_nested_media_and_inventory_arrays(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.public_media_references' => [
            ['table' => 'users', 'columns' => ['avatar']],
        ]]);
        DB::table('users')->insert([
            'name' => 'Nested Table',
            'email' => 'nested-table@example.test',
            'password' => 'not-used',
            'avatar' => 'avatars/missing-nested.webp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->artisan('production:readiness')
                ->expectsOutputToContain('{"checked":1,"missing_count":1,"missing":["users.avatar#')
                ->expectsOutputToContain('{"unsafe_count":0,"safe_legacy_count":0,"unsafe":[],"safe_legacy":[]}')
                ->assertFailed();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_blocks_cutover_when_expected_data_is_missing(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.public_media_references' => []]);
        config(['production_safety.minimum_counts.users' => 1]);

        try {
            $this->artisan('production:readiness', ['--json' => true])
                ->expectsOutputToContain('"status": "blocked"')
                ->assertFailed();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_blocks_cutover_when_a_canonical_schema_column_is_missing(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.required_columns.vendors' => ['missing_vendor_contract_column']]);

        try {
            $this->artisan('production:readiness', ['--json' => true])
                ->expectsOutputToContain('"status": "blocked"')
                ->assertFailed();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_blocks_cutover_when_database_media_is_missing(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.public_media_references' => [
            ['table' => 'users', 'columns' => ['avatar']],
        ]]);
        DB::table('users')->insert([
            'name' => 'Media Gate',
            'email' => 'media-gate@example.test',
            'password' => 'not-used',
            'avatar' => 'avatars/missing.webp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $media = app(ProductionMediaIntegrityService::class)->inspect();
            $this->assertSame(1, $media['missing_count']);
            $this->assertStringContainsString('avatars/missing.webp', $media['missing'][0]);

            $this->artisan('production:readiness', ['--json' => true])
                ->expectsOutputToContain('"status": "blocked"')
                ->assertFailed();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_accepts_database_media_present_on_public_disk(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.public_media_references' => [
            ['table' => 'users', 'columns' => ['avatar']],
        ]]);
        Storage::disk('public')->put('avatars/present.webp', 'image');
        DB::table('users')->insert([
            'name' => 'Media Gate',
            'email' => 'media-present@example.test',
            'password' => 'not-used',
            'avatar' => '/storage/avatars/present.webp?version=1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->artisan('production:readiness', ['--json' => true])
                ->expectsOutputToContain('"status": "ready"')
                ->assertSuccessful();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_unrelated_database_all_privileges_do_not_block_production_database(): void
    {
        $method = new ReflectionMethod(CheckProductionReadiness::class, 'grantsContainDestructivePrivilege');
        $command = app(CheckProductionReadiness::class);
        $grants = [
            'GRANT USAGE ON *.* TO `komibook_app`@`127.0.0.1`',
            'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, REFERENCES, INDEX, ALTER ON `komibook`.* TO `komibook_app`@`127.0.0.1`',
            'GRANT ALL PRIVILEGES ON `komibook_restore_check_d284cd4`.* TO `komibook_app`@`127.0.0.1`',
        ];

        $this->assertFalse($method->invoke($command, $grants, 'komibook'));
        $this->assertTrue($method->invoke($command, [
            'GRANT SELECT, DROP ON `komibook`.* TO `komibook_app`@`127.0.0.1`',
        ], 'komibook'));
        $this->assertTrue($method->invoke($command, [
            'GRANT ALL PRIVILEGES ON *.* TO `komibook_app`@`127.0.0.1`',
        ], 'komibook'));
    }

    public function test_readiness_accepts_canonical_bound_and_exact_safe_legacy_null_row(): void
    {
        $listing = $this->usedListing();
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.public_media_references' => []]);
        config(['production_safety.public_media_references' => []]);
        try {
            $this->artisan('production:readiness', ['--json' => true])->expectsOutputToContain('"status": "ready"')->assertSuccessful();
            WarehouseStock::where('book_id', $listing->book_id)->update(['quantity' => 0]);
            $listing->update(['quantity_available' => 0, 'status' => 'sold_out']);
            Book::withoutGlobalScopes()->whereKey($listing->book_id)->update(['stock' => 0]);
            FacadesDB::table('used_book_listings')->where('id', $listing->id)->update(['warehouse_id' => null]);
            $this->artisan('production:readiness', ['--json' => true])->expectsOutputToContain('"safe_legacy_count": 1')->assertSuccessful();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_blocks_sellable_unbound_and_projection_mismatch_with_reason_codes(): void
    {
        $listing = $this->usedListing();
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.public_media_references' => []]);
        config(['production_safety.public_media_references' => []]);
        try {
            FacadesDB::table('used_book_listings')->where('id', $listing->id)->update(['warehouse_id' => null]);
            $this->artisan('production:readiness', ['--json' => true])->expectsOutputToContain('warehouse_binding_missing')->assertFailed();
            FacadesDB::table('used_book_listings')->where('id', $listing->id)->update(['warehouse_id' => WarehouseStock::where('book_id', $listing->book_id)->value('warehouse_id')]);
            Book::withoutGlobalScopes()->whereKey($listing->book_id)->update(['stock' => 9]);
            $this->artisan('production:readiness', ['--json' => true])->expectsOutputToContain('inventory_projection_mismatch')->assertFailed();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_blocks_ambiguous_used_book_stock(): void
    {
        $listing = $this->usedListing();
        $stock = WarehouseStock::where('book_id', $listing->book_id)->firstOrFail();
        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail($stock->warehouse_id);
        $other = Warehouse::withoutGlobalScopes()->create(['vendor_id' => $warehouse->vendor_id, 'name' => 'Second', 'address' => 'Other', 'province' => 'Hue', 'status' => 'active']);
        WarehouseStock::create(['warehouse_id' => $other->id, 'book_id' => $listing->book_id, 'quantity' => 0]);
        $this->assertReadinessBlocked('warehouse_stock_ambiguous');
    }

    public function test_readiness_blocks_cross_vendor_used_book_stock(): void
    {
        $listing = $this->usedListing();
        $stock = WarehouseStock::where('book_id', $listing->book_id)->firstOrFail();
        $vendor = Vendor::withoutGlobalScopes()->create(['user_id' => User::factory()->create()->id, 'shop_name' => 'Foreign', 'slug' => 'foreign-'.$listing->id, 'status' => 'active', 'onboarding_status' => 'approved', 'business_model' => 'bookstore']);
        $warehouse = Warehouse::withoutGlobalScopes()->create(['vendor_id' => $vendor->id, 'name' => 'Foreign', 'address' => 'Other', 'province' => 'Hue', 'status' => 'active']);
        DB::table('used_book_listings')->where('id', $listing->id)->update(['warehouse_id' => null]);
        $stock->update(['warehouse_id' => $warehouse->id]);
        $this->assertReadinessBlocked('warehouse_vendor_mismatch');
    }

    public function test_readiness_blocks_terminal_wrong_allocation_without_writes(): void
    {
        $listing = $this->usedListing();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listing->id}/approve")->assertOk();
        $buyer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($buyer)->postJson('/api/checkout', ['items' => [['book_id' => $listing->book_id, 'quantity' => 1]], 'shipping_address' => 'Buyer', 'phone' => '0911111111', 'payment_method' => 'COD'])->assertCreated();
        $reservation = InventoryReservation::where('book_id', $listing->book_id)->firstOrFail();
        $otherListing = $this->usedListing();
        $reservation->update(['status' => 'released']);
        $reservation->allocations()->firstOrFail()->update(['warehouse_stock_id' => WarehouseStock::where('book_id', $otherListing->book_id)->value('id')]);
        $before = [DB::table('used_book_listings')->count(), DB::table('warehouse_stocks')->count(), DB::table('inventory_reservation_allocations')->count(), $listing->fresh()->warehouse_id];
        $this->assertReadinessBlocked('reservation_allocation_mismatch');
        $this->assertSame($before, [DB::table('used_book_listings')->count(), DB::table('warehouse_stocks')->count(), DB::table('inventory_reservation_allocations')->count(), $listing->fresh()->warehouse_id]);
    }

    public function test_readiness_blocks_committed_missing_or_short_allocations_without_writes(): void
    {
        foreach (['missing', 'short'] as $case) {
            $listing = $this->usedListing();
            $admin = User::factory()->create(['role' => 'admin']);
            $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listing->id}/approve")->assertOk();
            $buyer = User::factory()->create(['role' => 'customer']);
            $this->actingAs($buyer)->postJson('/api/checkout', ['items' => [['book_id' => $listing->book_id, 'quantity' => 1]], 'shipping_address' => 'Buyer', 'phone' => '0911111111', 'payment_method' => 'COD'])->assertCreated();
            $reservation = InventoryReservation::where('book_id', $listing->book_id)->firstOrFail();
            $reservation->update(['status' => 'committed']);
            if ($case === 'missing') {
                $reservation->allocations()->delete();
            } else {
                $reservation->update(['quantity' => 2]);
            }

            $before = [
                $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
                (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'),
                (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock,
                $reservation->fresh()->only(['status', 'quantity']),
                DB::table('inventory_reservation_allocations')->where('inventory_reservation_id', $reservation->id)->count(),
            ];
            $this->assertReadinessBlocked('reservation_allocation_mismatch');
            $this->assertSame($before, [
                $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
                (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'),
                (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock,
                $reservation->fresh()->only(['status', 'quantity']),
                DB::table('inventory_reservation_allocations')->where('inventory_reservation_id', $reservation->id)->count(),
            ]);
        }
    }

    public function test_readiness_blocks_cross_book_reservation_without_writes(): void
    {
        $listing = $this->usedListing();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listing->id}/approve")->assertOk();
        $buyer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($buyer)->postJson('/api/checkout', ['items' => [['book_id' => $listing->book_id, 'quantity' => 1]], 'shipping_address' => 'Buyer', 'phone' => '0911111111', 'payment_method' => 'COD'])->assertCreated();
        $reservation = InventoryReservation::where('book_id', $listing->book_id)->firstOrFail();
        $otherListing = $this->usedListing();
        $reservation->update(['book_id' => $otherListing->book_id]);

        $before = [
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            $otherListing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'),
            (int) WarehouseStock::where('book_id', $otherListing->book_id)->value('quantity'),
            $reservation->fresh()->only(['book_id', 'status']),
            DB::table('inventory_reservation_allocations')->where('inventory_reservation_id', $reservation->id)->count(),
        ];
        $this->assertReadinessBlocked('reservation_allocation_mismatch');
        $this->assertSame($before, [
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            $otherListing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'),
            (int) WarehouseStock::where('book_id', $otherListing->book_id)->value('quantity'),
            $reservation->fresh()->only(['book_id', 'status']),
            DB::table('inventory_reservation_allocations')->where('inventory_reservation_id', $reservation->id)->count(),
        ]);
    }

    public function test_readiness_blocks_when_reservation_allocation_schema_is_missing_without_writes(): void
    {
        $listing = $this->usedListing();
        $before = [
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'),
            (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock,
            DB::table('inventory_reservation_allocations')->count(),
        ];
        $renamedTable = 'inventory_reservation_allocations_readiness_missing';

        try {
            Schema::rename('inventory_reservation_allocations', $renamedTable);
            $this->assertReadinessBlocked('reservation_allocation_mismatch');
        } finally {
            if (Schema::hasTable($renamedTable) && ! Schema::hasTable('inventory_reservation_allocations')) {
                Schema::rename($renamedTable, 'inventory_reservation_allocations');
            }
        }

        $this->assertSame($before, [
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'),
            (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock,
            DB::table('inventory_reservation_allocations')->count(),
        ]);
    }

    public function test_readiness_blocks_foreign_book_allocation_targeting_used_stock_without_writes(): void
    {
        $listing = $this->usedListing();
        $otherListing = $this->usedListing();
        $buyer = User::factory()->create(['role' => 'customer']);
        $session = CheckoutSession::create([
            'user_id' => $buyer->id,
            'currency' => 'VND',
            'subtotal_amount' => 20000,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 20000,
        ]);
        $otherWarehouse = Warehouse::withoutGlobalScopes()->findOrFail($otherListing->warehouse_id);
        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $buyer->id,
            'vendor_id' => $otherWarehouse->vendor_id,
            'total_amount' => 20000,
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'refund_status' => 'none',
            'payment_method' => 'cod',
            'shipping_address' => 'Buyer',
            'phone' => '0911111111',
            'shipping_status' => 'cancelled',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $otherListing->book_id,
            'quantity' => 1,
            'price' => 20000,
        ]);
        $reservation = InventoryReservation::create([
            'checkout_session_id' => $session->id,
            'order_item_id' => $item->id,
            'book_id' => $otherListing->book_id,
            'quantity' => 1,
            'status' => 'released',
            'operation_key' => 'foreign-stock-allocation-'.$item->id,
            'released_at' => now(),
        ]);
        InventoryReservationAllocation::create([
            'inventory_reservation_id' => $reservation->id,
            'warehouse_stock_id' => WarehouseStock::where('book_id', $listing->book_id)->value('id'),
            'quantity' => 1,
        ]);

        $before = [
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            $otherListing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'),
            (int) WarehouseStock::where('book_id', $otherListing->book_id)->value('quantity'),
            $reservation->fresh()->only(['book_id', 'status', 'order_item_id']),
            DB::table('inventory_reservation_allocations')->where('inventory_reservation_id', $reservation->id)->count(),
        ];
        $this->assertReadinessBlocked('reservation_allocation_mismatch');
        $this->assertSame($before, [
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            $otherListing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'),
            (int) WarehouseStock::where('book_id', $otherListing->book_id)->value('quantity'),
            $reservation->fresh()->only(['book_id', 'status', 'order_item_id']),
            DB::table('inventory_reservation_allocations')->where('inventory_reservation_id', $reservation->id)->count(),
        ]);
    }

    public function test_readiness_blocks_projection_below_live_reservation_capacity_without_writes(): void
    {
        $listing = $this->usedListing();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listing->id}/approve")->assertOk();
        $buyer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($buyer)->postJson('/api/checkout', ['items' => [['book_id' => $listing->book_id, 'quantity' => 1]], 'shipping_address' => 'Buyer', 'phone' => '0911111111', 'payment_method' => 'COD'])->assertCreated();
        $stock = WarehouseStock::where('book_id', $listing->book_id)->firstOrFail();
        $stock->update(['quantity' => 0]);
        Book::withoutGlobalScopes()->whereKey($listing->book_id)->update(['stock' => 0]);
        $listing->update(['quantity_available' => 0, 'status' => 'sold_out']);
        $reservation = InventoryReservation::where('book_id', $listing->book_id)->firstOrFail();
        $reservation->update(['status' => 'reserved', 'committed_at' => null, 'expires_at' => now()->addMinutes(30)]);

        $before = [
            (int) $stock->fresh()->quantity,
            (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock,
            $listing->fresh()->only(['quantity_available', 'status', 'warehouse_id']),
            [$reservation->fresh()->status->value, (int) $reservation->fresh()->quantity, $reservation->fresh()->expires_at?->toDateTimeString()],
        ];
        $this->assertReadinessBlocked('reservation_capacity_mismatch');
        $this->assertSame($before, [
            (int) $stock->fresh()->quantity,
            (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock,
            $listing->fresh()->only(['quantity_available', 'status', 'warehouse_id']),
            [$reservation->fresh()->status->value, (int) $reservation->fresh()->quantity, $reservation->fresh()->expires_at?->toDateTimeString()],
        ]);
    }

    public function test_readiness_blocks_bound_active_zero_and_sold_out_positive_without_writes(): void
    {
        foreach ([['active', 0], ['sold_out', 2]] as [$status, $quantity]) {
            $listing = $this->usedListing();
            $stock = WarehouseStock::where('book_id', $listing->book_id)->firstOrFail();
            $stock->update(['quantity' => $quantity]);
            $listing->update(['status' => $status, 'quantity_available' => $quantity]);
            Book::withoutGlobalScopes()->whereKey($listing->book_id)->update(['stock' => $quantity]);
            $before = [$listing->fresh()->only(['status', 'quantity_available', 'warehouse_id']), (int) WarehouseStock::whereKey($stock->id)->value('quantity'), (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock];
            $this->assertReadinessBlocked('inventory_status_mismatch');
            $this->assertSame($before, [$listing->fresh()->only(['status', 'quantity_available', 'warehouse_id']), (int) WarehouseStock::whereKey($stock->id)->value('quantity'), (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock]);
        }
    }

    private function assertReadinessBlocked(string $reason): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.public_media_references' => []]);
        try {
            $this->artisan('production:readiness', ['--json' => true])->expectsOutputToContain($reason)->assertFailed();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    private function usedListing(): UsedBookListing
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => 'customer']);
        SellerFulfillmentAddress::create(['user_id' => $seller->id, 'recipient_name' => 'Seller', 'phone' => '0900000000', 'address_line' => 'Verified', 'province' => 'Hue', 'status' => 'verified', 'verified_at' => now()]);
        $category = Category::create(['name' => 'Readiness '.$seller->id, 'slug' => 'readiness-'.$seller->id]);
        $response = $this->actingAs($seller)->postJson('/api/used-book-seller/listings', ['title' => 'Readiness '.$seller->id, 'author_name' => 'Author', 'category_id' => $category->id, 'price' => 20000, 'condition' => 'good', 'quantity' => 1, 'actual_photos' => [UploadedFile::fake()->image('book.jpg')], 'authenticity_attested' => true])->assertCreated();

        return UsedBookListing::where('book_id', $response->json('data.book.id'))->firstOrFail();
    }

    private function configureHealthyRuntime(): void
    {
        Storage::fake('public');
        $sharedRoot = dirname((string) config('filesystems.disks.public.root'));

        config([
            'app.url' => 'https://komibook.id.vn',
            'production_safety.expected_database' => DB::connection()->getDatabaseName(),
            'production_safety.expected_host' => 'komibook.id.vn',
            'production_safety.shared_root' => $sharedRoot,
            'production_safety.minimum_counts' => [
                'users' => 0,
                'books' => 0,
                'vendors' => 0,
                'organizations' => 0,
            ],
            'production_safety.required_columns' => [
                'vendors' => [
                    'onboarding_status',
                    'business_model',
                    'is_demo',
                    'submitted_at',
                    'last_review_reason',
                ],
            ],
            'session.domain' => 'komibook.id.vn',
            'session.secure' => true,
            'sanctum.stateful' => ['komibook.id.vn'],
            'filesystems.disks.local.root' => $sharedRoot.'/local',
            'filesystems.disks.private.root' => $sharedRoot.'/private',
        ]);
    }
}

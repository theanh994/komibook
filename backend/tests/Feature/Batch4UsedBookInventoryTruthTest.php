<?php

namespace Tests\Feature;

use App\Enums\InventoryReservationStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\InventoryReservation;
use App\Models\InventoryReservationAllocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\SellerFulfillmentAddress;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\UsedBookListing;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseDocument;
use App\Models\WarehouseStock;
use App\Services\BookInventoryOnboardingService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\ReturnRefundService;
use App\Services\UsedBookInventoryReconciliationService;
use App\Services\WarehouseDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Batch4UsedBookInventoryTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_is_read_only_apply_binds_once_and_rerun_is_idempotent(): void
    {
        $listing = $this->canonicalListing();
        DB::table('used_book_listings')->where('id', $listing->id)->update(['warehouse_id' => null]);
        $before = DB::table('used_book_listings')->where('id', $listing->id)->first();
        $dryRun = app(UsedBookInventoryReconciliationService::class)->reconcile();
        $this->assertSame('bindable', $dryRun['rows'][0]['reason_code']);
        $this->assertEquals($before, DB::table('used_book_listings')->where('id', $listing->id)->first());

        $this->artisan('used-books:reconcile-inventory', ['--apply' => true, '--json' => true])->assertSuccessful();
        $bound = $listing->fresh();
        $this->assertNotNull($bound->warehouse_id);
        $this->artisan('used-books:reconcile-inventory', ['--apply' => true, '--json' => true])->assertSuccessful();
        $this->assertSame($bound->warehouse_id, $listing->fresh()->warehouse_id);
    }

    public function test_ambiguous_cross_vendor_and_projection_mismatch_are_not_repaired(): void
    {
        $listing = $this->canonicalListing();
        DB::table('used_book_listings')->where('id', $listing->id)->update(['warehouse_id' => null]);
        $stock = WarehouseStock::where('book_id', $listing->book_id)->firstOrFail();
        $secondWarehouse = Warehouse::withoutGlobalScopes()->create(['vendor_id' => Warehouse::withoutGlobalScopes()->findOrFail($stock->warehouse_id)->vendor_id, 'name' => 'Second', 'address' => 'Elsewhere', 'province' => 'Da Nang', 'status' => 'active']);
        WarehouseStock::create(['warehouse_id' => $secondWarehouse->id, 'book_id' => $listing->book_id, 'quantity' => 1, 'shelf_location' => 'second']);
        $result = app(UsedBookInventoryReconciliationService::class)->reconcile(true);
        $this->assertSame('warehouse_stock_ambiguous', $result['rows'][0]['reason_code']);
        $this->assertNull($listing->fresh()->warehouse_id);

        WarehouseStock::where('book_id', $listing->book_id)->where('id', '!=', $stock->id)->delete();
        $foreignVendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => User::factory()->create()->id, 'shop_name' => 'Foreign', 'slug' => 'foreign-'.$listing->id,
            'status' => 'active', 'onboarding_status' => 'approved', 'business_model' => 'bookstore',
        ]);
        $foreignWarehouse = Warehouse::withoutGlobalScopes()->create(['vendor_id' => $foreignVendor->id, 'name' => 'Foreign', 'address' => 'Elsewhere', 'province' => 'Hue', 'status' => 'active']);
        $stock->update(['warehouse_id' => $foreignWarehouse->id]);
        $result = app(UsedBookInventoryReconciliationService::class)->reconcile(true);
        $this->assertSame('warehouse_vendor_mismatch', $result['rows'][0]['reason_code']);
        $this->assertNull($listing->fresh()->warehouse_id);
    }

    public function test_new_migration_down_and_up_are_reversible_and_legacy_evidence_is_unchanged(): void
    {
        $migration = require base_path('database/migrations/2026_08_09_000002_add_warehouse_binding_to_used_book_listings.php');
        $this->assertTrue(Schema::hasColumn('used_book_listings', 'warehouse_id'));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('used_book_listings', 'warehouse_id'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('used_book_listings', 'warehouse_id'));
        $this->assertSame('542CD84A122BBCF6F856132E6F6F63B905A2F359A22C4553A081A75F7E0AB5C4', strtoupper(hash_file('sha256', base_path('database/migrations/2026_08_07_000002_backfill_used_book_warehouse_stocks_table.php'))));
    }

    public function test_terminal_wrong_allocation_blocks_dry_run_apply_and_preserves_bound_listing(): void
    {
        $listing = $this->canonicalListing();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listing->id}/approve")->assertOk();
        $buyer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($buyer)->postJson('/api/checkout', [
            'items' => [['book_id' => $listing->book_id, 'quantity' => 1]],
            'shipping_address' => 'Buyer address', 'phone' => '0911111111', 'payment_method' => 'COD',
        ])->assertCreated();
        $reservation = InventoryReservation::where('book_id', $listing->book_id)->firstOrFail();
        $allocation = $reservation->allocations()->firstOrFail();
        $otherListing = $this->canonicalListing();
        $otherStock = WarehouseStock::where('book_id', $otherListing->book_id)->firstOrFail();
        $reservation->update(['status' => 'released']);
        $allocation->update(['warehouse_stock_id' => $otherStock->id]);
        $before = $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']);

        $dry = app(UsedBookInventoryReconciliationService::class)->reconcile();
        $this->assertSame('reservation_allocation_mismatch', $dry['rows'][0]['reason_code']);
        $apply = app(UsedBookInventoryReconciliationService::class)->reconcile(true);
        $this->assertSame('reservation_allocation_mismatch', $apply['rows'][0]['reason_code']);
        $this->assertSame($before, $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']));
        $reservation->update(['status' => 'reserved']);
        $runtimeBefore = [DB::table('inventory_reservations')->where('id', $reservation->id)->value('status'), DB::table('inventory_reservation_allocations')->where('inventory_reservation_id', $reservation->id)->value('warehouse_stock_id'), $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']), (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock];
        try {
            app(InventoryReservationService::class)->commit($reservation);
            $this->fail('Expected corrupted reservation rejection.');
        } catch (\RuntimeException) {
        }
        $this->assertSame($runtimeBefore, [DB::table('inventory_reservations')->where('id', $reservation->id)->value('status'), DB::table('inventory_reservation_allocations')->where('inventory_reservation_id', $reservation->id)->value('warehouse_stock_id'), $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']), (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock]);
    }

    public function test_update_inventory_rejects_ambiguous_used_book_stock_without_writes(): void
    {
        $listing = $this->canonicalListing();
        $stock = WarehouseStock::where('book_id', $listing->book_id)->firstOrFail();
        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail($stock->warehouse_id);
        $other = Warehouse::withoutGlobalScopes()->create(['vendor_id' => $warehouse->vendor_id, 'name' => 'Extra', 'address' => 'Extra', 'province' => 'Hue', 'status' => 'active']);
        WarehouseStock::create(['warehouse_id' => $other->id, 'book_id' => $listing->book_id, 'quantity' => 0]);
        $before = [$listing->fresh()->only(['quantity_available', 'warehouse_id']), (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock, WarehouseStock::where('book_id', $listing->book_id)->orderBy('id')->pluck('quantity')->all()];
        $this->actingAs(User::findOrFail($listing->seller_user_id))->patchJson("/api/used-book-seller/listings/{$listing->id}/inventory", ['quantity_available' => 0])->assertStatus(422);
        $this->assertSame($before, [$listing->fresh()->only(['quantity_available', 'warehouse_id']), (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock, WarehouseStock::where('book_id', $listing->book_id)->orderBy('id')->pluck('quantity')->all()]);
    }

    public function test_store_without_current_verified_address_does_not_provision_or_store_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'customer']);
        $category = Category::create(['name' => 'No address', 'slug' => 'no-address']);
        $before = [DB::table('vendors')->count(), DB::table('used_book_seller_profiles')->count(), DB::table('warehouses')->count(), DB::table('warehouse_stocks')->count(), DB::table('books')->count(), DB::table('used_book_listings')->count(), Storage::disk('public')->allFiles()];
        $this->actingAs($user)->postJson('/api/used-book-seller/listings', ['title' => 'No address', 'author_name' => 'Author', 'category_id' => $category->id, 'price' => 20000, 'condition' => 'good', 'quantity' => 1, 'actual_photos' => [UploadedFile::fake()->image('no-address.jpg')], 'authenticity_attested' => true])->assertStatus(422);
        $this->assertSame($before, [DB::table('vendors')->count(), DB::table('used_book_seller_profiles')->count(), DB::table('warehouses')->count(), DB::table('warehouse_stocks')->count(), DB::table('books')->count(), DB::table('used_book_listings')->count(), Storage::disk('public')->allFiles()]);
    }

    public function test_invalid_address_upsert_does_not_provision(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $before = [DB::table('vendors')->count(), DB::table('used_book_seller_profiles')->count(), DB::table('seller_fulfillment_addresses')->count(), DB::table('warehouses')->count()];
        $this->actingAs($user)->putJson('/api/used-book-seller/fulfillment-address', ['phone' => 'bad'])->assertStatus(422);
        $this->assertSame($before, [DB::table('vendors')->count(), DB::table('used_book_seller_profiles')->count(), DB::table('seller_fulfillment_addresses')->count(), DB::table('warehouses')->count()]);
    }

    public function test_update_inventory_transitions_only_active_and_sold_out_states(): void
    {
        $listing = $this->canonicalListing();
        $listing->update(['status' => 'active']);
        $seller = User::findOrFail($listing->seller_user_id);
        $this->actingAs($seller)->patchJson("/api/used-book-seller/listings/{$listing->id}/inventory", ['quantity_available' => 0])->assertOk();
        $this->assertSame(['sold_out', 0, 0, 0], [$listing->fresh()->status, (int) $listing->fresh()->quantity_available, (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'), (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock]);
        $this->actingAs($seller)->patchJson("/api/used-book-seller/listings/{$listing->id}/inventory", ['quantity_available' => 2])->assertOk();
        $this->assertSame(['active', 2, 2, 2], [$listing->fresh()->status, (int) $listing->fresh()->quantity_available, (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'), (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock]);
        $listing->update(['status' => 'pending']);
        $this->actingAs($seller)->patchJson("/api/used-book-seller/listings/{$listing->id}/inventory", ['quantity_available' => 1])->assertOk();
        $this->assertSame('pending', $listing->fresh()->status);
        $listing->update(['status' => 'rejected']);
        $this->actingAs($seller)->patchJson("/api/used-book-seller/listings/{$listing->id}/inventory", ['quantity_available' => 1])->assertOk();
        $this->assertSame('rejected', $listing->fresh()->status);
    }

    public function test_general_inventory_endpoints_reject_used_book_before_writes(): void
    {
        $listing = $this->canonicalListing();
        $seller = User::findOrFail($listing->seller_user_id);
        $seller->update(['role' => 'vendor']);
        $source = Warehouse::withoutGlobalScopes()->findOrFail($listing->warehouse_id);
        $target = Warehouse::withoutGlobalScopes()->create(['vendor_id' => $source->vendor_id, 'name' => 'Target', 'address' => 'Target', 'province' => 'Hue', 'status' => 'active']);
        $snapshot = fn () => [DB::table('inventory_audits')->count(), DB::table('stock_transfers')->count(), (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'), (int) $listing->fresh()->quantity_available, (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock];
        $before = $snapshot();
        $this->actingAs($seller)->postJson('/api/vendor/inventory/audits', ['warehouse_id' => $source->id, 'audit_period' => 'today', 'items' => [['book_id' => $listing->book_id, 'physical_qty' => 0]]])->assertStatus(422);
        $this->assertSame($before, $snapshot());
        $this->actingAs($seller)->postJson('/api/vendor/inventory/transfers', ['from_warehouse_id' => $source->id, 'to_warehouse_id' => $target->id, 'items' => [['book_id' => $listing->book_id, 'quantity' => 1]]])->assertStatus(422);
        $this->assertSame($before, $snapshot());
        $this->actingAs($seller)->postJson('/api/vendor/warehouses/adjust', ['type' => 'adjust', 'book_id' => $listing->book_id, 'source_warehouse_id' => $source->id, 'quantity' => 1])->assertStatus(422);
        $this->assertSame($before, $snapshot());
    }

    public function test_shipped_transfer_receive_rejects_used_book_before_writes(): void
    {
        $listing = $this->canonicalListing();
        $seller = User::findOrFail($listing->seller_user_id);
        $seller->update(['role' => 'vendor']);
        $source = Warehouse::withoutGlobalScopes()->findOrFail($listing->warehouse_id);
        $target = Warehouse::withoutGlobalScopes()->create(['vendor_id' => $source->vendor_id, 'name' => 'Receive target', 'address' => 'Target', 'province' => 'Hue', 'status' => 'active']);
        $transfer = StockTransfer::create(['vendor_id' => $source->vendor_id, 'from_warehouse_id' => $source->id, 'to_warehouse_id' => $target->id, 'transfer_code' => 'USED-RCV-'.uniqid(), 'status' => 'shipped']);
        StockTransferItem::create(['stock_transfer_id' => $transfer->id, 'book_id' => $listing->book_id, 'quantity' => 1]);
        $snapshot = fn () => [$transfer->fresh()->status, (int) WarehouseStock::where('warehouse_id', $source->id)->where('book_id', $listing->book_id)->value('quantity'), WarehouseStock::where('warehouse_id', $target->id)->where('book_id', $listing->book_id)->value('quantity'), (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock, $listing->fresh()->only(['quantity_available', 'warehouse_id']), DB::table('stock_transfers')->count(), DB::table('warehouse_stocks')->count()];
        $before = $snapshot();
        $this->actingAs($seller)->postJson("/api/vendor/inventory/transfers/{$transfer->id}/receive")->assertStatus(422);
        $this->assertSame($before, $snapshot());
    }

    public function test_vendor_book_update_rejects_used_book_stock_before_media_or_metadata_write(): void
    {
        Storage::fake('public');
        $listing = $this->canonicalListing();
        $seller = User::findOrFail($listing->seller_user_id);
        $seller->update(['role' => 'vendor']);
        $book = Book::withoutGlobalScopes()->findOrFail($listing->book_id);
        $before = [$book->only(['title', 'stock', 'cover_image']), $listing->fresh()->only(['quantity_available', 'warehouse_id']), (int) WarehouseStock::where('book_id', $book->id)->value('quantity'), DB::table('books')->count(), DB::table('used_book_listings')->count(), Storage::disk('public')->allFiles()];
        $this->actingAs($seller)->postJson("/api/vendor/books/{$book->id}", ['_method' => 'PUT', 'title' => 'Should not save', 'stock' => 99, 'cover_image' => UploadedFile::fake()->image('blocked-cover.jpg')])->assertStatus(422);
        $this->assertSame($before, [$book->fresh()->only(['title', 'stock', 'cover_image']), $listing->fresh()->only(['quantity_available', 'warehouse_id']), (int) WarehouseStock::where('book_id', $book->id)->value('quantity'), DB::table('books')->count(), DB::table('used_book_listings')->count(), Storage::disk('public')->allFiles()]);
    }

    public function test_used_book_available_to_sell_fails_closed_with_extra_stock(): void
    {
        $listing = $this->canonicalListing();
        $stock = WarehouseStock::where('book_id', $listing->book_id)->firstOrFail();
        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail($stock->warehouse_id);
        $other = Warehouse::withoutGlobalScopes()->create(['vendor_id' => $warehouse->vendor_id, 'name' => 'ATS extra', 'address' => 'Extra', 'province' => 'Hue', 'status' => 'active']);
        WarehouseStock::create(['warehouse_id' => $other->id, 'book_id' => $listing->book_id, 'quantity' => 99]);
        $this->assertSame(0, app(InventoryReservationService::class)->getAvailableToSell($listing->book_id));
    }

    /** SQLite proves deterministic query order here; this is not a MySQL concurrency/deadlock test. */
    public function test_sqlite_reserve_queries_used_listing_before_warehouse_stock_for_used_book(): void
    {
        $listing = $this->canonicalListing();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listing->id}/approve")->assertOk();
        $buyer = User::factory()->create(['role' => 'customer']);
        $session = CheckoutSession::create([
            'user_id' => $buyer->id,
            'currency' => 'VND',
            'subtotal_amount' => 20000,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 20000,
        ]);
        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail($listing->warehouse_id);
        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $buyer->id,
            'vendor_id' => $warehouse->vendor_id,
            'total_amount' => 20000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'refund_status' => 'none',
            'payment_method' => 'cod',
            'shipping_address' => 'Buyer',
            'phone' => '0911111111',
            'shipping_status' => 'pending',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $listing->book_id,
            'quantity' => 1,
            'price' => 20000,
        ]);
        CheckoutSessionOrder::create([
            'checkout_session_id' => $session->id,
            'order_id' => $order->id,
            'vendor_id' => $warehouse->vendor_id,
            'subtotal_amount' => 20000,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'total_amount' => 20000,
        ]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        app(InventoryReservationService::class)->reserve($session, now()->addMinutes(15), 'batch4-reserve-lock-order-'.$session->id);
        // SQLite omits FOR UPDATE and emits Schema PRAGMA metadata after stock access.
        // Assert only listing-row SQL, which is the lock/query contract under test.
        $isListingRowQuery = fn (string $sql) => str_contains($sql, 'from "used_book_listings"')
            || str_starts_with($sql, 'update "used_book_listings"');
        $listingIndex = collect($queries)->search($isListingRowQuery);
        $stockIndex = collect($queries)->search(fn ($sql) => str_contains($sql, 'warehouse_stocks'));
        $this->assertNotFalse($listingIndex);
        $this->assertNotFalse($stockIndex);
        $this->assertLessThan($stockIndex, $listingIndex);
        $this->assertMatchesRegularExpression('/order by\\s+["`]?id["`]?\\s+asc/', $queries[$listingIndex]);
        $this->assertFalse(
            collect($queries)->slice($stockIndex + 1)->contains($isListingRowQuery)
        );
    }

    /** SQLite proves deterministic query order here; this is not a MySQL concurrency/deadlock test. */
    public function test_sqlite_multi_item_restore_locks_used_listings_before_warehouse_stocks(): void
    {
        $firstListing = $this->canonicalListing();
        $secondListing = $this->canonicalListing();
        $buyer = User::factory()->create(['role' => 'customer']);
        $session = CheckoutSession::create([
            'user_id' => $buyer->id,
            'currency' => 'VND',
            'subtotal_amount' => 40000,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 40000,
        ]);
        $firstWarehouse = Warehouse::withoutGlobalScopes()->findOrFail($firstListing->warehouse_id);
        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $buyer->id,
            'vendor_id' => $firstWarehouse->vendor_id,
            'total_amount' => 40000,
            'status' => 'processing',
            'payment_status' => 'paid',
            'refund_status' => 'none',
            'payment_method' => 'cod',
            'shipping_address' => 'Buyer',
            'phone' => '0911111111',
            'shipping_status' => 'shipped',
        ]);

        // Create order items in reverse listing-ID order. The lock query must still order by listing ID.
        foreach ([$secondListing, $firstListing] as $listing) {
            $stock = WarehouseStock::where('book_id', $listing->book_id)->firstOrFail();
            $stock->update(['quantity' => 0]);
            Book::withoutGlobalScopes()->whereKey($listing->book_id)->update(['stock' => 0]);
            $listing->update(['quantity_available' => 0, 'status' => 'sold_out']);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'book_id' => $listing->book_id,
                'quantity' => 1,
                'price' => 20000,
            ]);
            $reservation = InventoryReservation::create([
                'checkout_session_id' => $session->id,
                'order_item_id' => $item->id,
                'book_id' => $listing->book_id,
                'quantity' => 1,
                'status' => InventoryReservationStatus::COMMITTED,
                'operation_key' => 'batch4-restore-'.$item->id,
                'committed_at' => now(),
            ]);
            InventoryReservationAllocation::create([
                'inventory_reservation_id' => $reservation->id,
                'warehouse_stock_id' => $stock->id,
                'quantity' => 1,
            ]);
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        app(InventoryReservationService::class)->restoreCommittedOrder($order, 'batch4-multi-item-restore-'.$order->id);

        $listingIndex = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from "used_book_listings"'));
        $stockIndex = collect($queries)->search(fn (string $sql) => str_contains($sql, 'warehouse_stocks'));
        $this->assertNotFalse($listingIndex);
        $this->assertNotFalse($stockIndex);
        $this->assertLessThan($stockIndex, $listingIndex);
        $this->assertMatchesRegularExpression('/order by\\s+["`]?id["`]?\\s+asc/', $queries[$listingIndex]);
    }

    public function test_restore_committed_order_rejects_missing_allocations_without_writes(): void
    {
        ['listing' => $listing, 'stock' => $stock, 'order' => $order, 'reservation' => $reservation] = $this->committedUsedBookRestorationFixture(null);
        $snapshot = fn () => [
            (int) WarehouseStock::whereKey($stock->id)->value('quantity'),
            (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock,
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            $reservation->fresh()->status->value,
            DB::table('inventory_cancellation_restorations')->count(),
        ];
        $before = $snapshot();

        try {
            app(InventoryReservationService::class)->restoreCommittedOrder($order, 'batch4-missing-allocation-'.$order->id);
            $this->fail('Expected missing allocation rejection.');
        } catch (\RuntimeException) {
        }

        $this->assertSame($before, $snapshot());
    }

    public function test_restore_committed_order_rejects_partial_allocations_without_writes(): void
    {
        ['listing' => $listing, 'stock' => $stock, 'order' => $order, 'reservation' => $reservation] = $this->committedUsedBookRestorationFixture(1);
        $snapshot = fn () => [
            (int) WarehouseStock::whereKey($stock->id)->value('quantity'),
            (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock,
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            $reservation->fresh()->status->value,
            DB::table('inventory_cancellation_restorations')->count(),
        ];
        $before = $snapshot();

        try {
            app(InventoryReservationService::class)->restoreCommittedOrder($order, 'batch4-partial-allocation-'.$order->id);
            $this->fail('Expected partial allocation rejection.');
        } catch (\RuntimeException) {
        }

        $this->assertSame($before, $snapshot());
    }

    public function test_restore_committed_order_rejects_cross_book_reservation_without_writes(): void
    {
        ['listing' => $listing, 'stock' => $stock, 'order' => $order, 'reservation' => $reservation] = $this->committedUsedBookRestorationFixture(2);
        $otherListing = $this->canonicalListing();
        $reservation->update(['book_id' => $otherListing->book_id]);
        $snapshot = fn () => [
            (int) WarehouseStock::whereKey($stock->id)->value('quantity'),
            (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock,
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            $reservation->fresh()->only(['book_id', 'status']),
            DB::table('inventory_cancellation_restorations')->count(),
        ];
        $before = $snapshot();

        try {
            app(InventoryReservationService::class)->restoreCommittedOrder($order, 'batch4-cross-book-reservation-'.$order->id);
            $this->fail('Expected cross-book reservation rejection.');
        } catch (\RuntimeException) {
        }

        $this->assertSame($before, $snapshot());
    }

    public function test_return_restore_rejects_cross_book_same_warehouse_allocation_without_writes(): void
    {
        ['listing' => $listing, 'stock' => $stock, 'order' => $order, 'reservation' => $reservation] = $this->committedUsedBookRestorationFixture(2);
        $book = Book::withoutGlobalScopes()->findOrFail($listing->book_id);
        $otherBook = Book::withoutGlobalScopes()->create([
            'vendor_id' => $book->vendor_id,
            'category_id' => $book->category_id,
            'title' => 'Cross-book return allocation',
            'slug' => 'cross-book-return-allocation-'.$book->id,
            'author' => 'KomiBook',
            'price' => 20000,
            'stock' => 0,
            'type' => 'physical',
            'format' => 'physical',
            'provenance' => 'publisher_catalog',
            'fulfillment_mode' => 'vendor_warehouse',
            'status' => 'published',
        ]);
        $otherStock = WarehouseStock::create([
            'warehouse_id' => $stock->warehouse_id,
            'book_id' => $otherBook->id,
            'quantity' => 0,
        ]);
        $reservation->allocations()->sole()->update(['warehouse_stock_id' => $otherStock->id]);
        $return = ReturnRequest::create([
            'code' => (string) Str::uuid(),
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'vendor_id' => $order->vendor_id,
            'status' => 'approved',
            'currency' => 'VND',
            'refund_amount' => 20000,
            'reason' => 'Cross-book allocation regression',
            'requested_at' => now(),
            'approved_at' => now(),
        ]);
        $returnItem = ReturnRequestItem::create([
            'return_request_id' => $return->id,
            'order_item_id' => $reservation->order_item_id,
            'quantity' => 1,
            'unit_amount' => 20000,
            'refund_amount' => 20000,
        ]);
        $snapshot = fn () => [
            (int) WarehouseStock::whereKey($stock->id)->value('quantity'),
            (int) WarehouseStock::whereKey($otherStock->id)->value('quantity'),
            (int) Book::withoutGlobalScopes()->findOrFail($book->id)->stock,
            (int) Book::withoutGlobalScopes()->findOrFail($otherBook->id)->stock,
            $listing->fresh()->only(['warehouse_id', 'quantity_available', 'status']),
            DB::table('inventory_return_restorations')->count(),
            $returnItem->fresh()->inventory_restored_at?->toDateTimeString(),
            $return->fresh()->only(['status', 'item_received_at']),
            DB::table('return_request_transitions')->count(),
        ];
        $before = $snapshot();

        try {
            app(ReturnRefundService::class)->transition($return->id, 'item_received', User::factory()->create(['role' => 'admin']), 'batch4-return-cross-book-'.$return->id);
            $this->fail('Expected cross-book return allocation rejection.');
        } catch (\LogicException) {
        }

        $this->assertSame($before, $snapshot());
    }

    public function test_document_posting_and_onboarding_reject_used_book_without_inventory_writes(): void
    {
        $listing = $this->canonicalListing();
        $seller = User::findOrFail($listing->seller_user_id);
        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail($listing->warehouse_id);
        $document = WarehouseDocument::create(['vendor_id' => $warehouse->vendor_id, 'document_code' => 'USED-'.uniqid(), 'type' => 'receipt', 'origin' => 'manual', 'destination_warehouse_id' => $warehouse->id, 'status' => 'approved', 'created_by' => $seller->id, 'approved_by' => $seller->id, 'approved_at' => now(), 'operation_key' => 'used-doc-'.uniqid()]);
        $document->lines()->create(['book_id' => $listing->book_id, 'quantity' => 1]);
        $snapshot = fn () => [DB::table('warehouse_documents')->count(), DB::table('warehouse_document_lines')->count(), (int) WarehouseStock::where('book_id', $listing->book_id)->value('quantity'), (int) $listing->fresh()->quantity_available, (int) Book::withoutGlobalScopes()->findOrFail($listing->book_id)->stock];
        $before = $snapshot();
        try {
            app(WarehouseDocumentService::class)->transition($document, 'posted', $seller, null, 'used-doc-post-'.uniqid());
            $this->fail('Expected document rejection.');
        } catch (ValidationException) {
        }
        $this->assertSame($before, $snapshot());
        try {
            app(BookInventoryOnboardingService::class)->createReceiptDraft(Book::withoutGlobalScopes()->findOrFail($listing->book_id), $warehouse->vendor, $warehouse, $seller, 1, null, 'used-onboard-'.uniqid());
            $this->fail('Expected onboarding rejection.');
        } catch (\LogicException) {
        }
        $this->assertSame($before, $snapshot());
    }

    /** @return array{listing: UsedBookListing, stock: WarehouseStock, order: Order, reservation: InventoryReservation} */
    private function committedUsedBookRestorationFixture(?int $allocationQuantity): array
    {
        $listing = $this->canonicalListing();
        $stock = WarehouseStock::where('book_id', $listing->book_id)->firstOrFail();
        $stock->update(['quantity' => 0]);
        Book::withoutGlobalScopes()->whereKey($listing->book_id)->update(['stock' => 0]);
        $listing->update(['quantity_available' => 0, 'status' => 'sold_out']);

        $buyer = User::factory()->create(['role' => 'customer']);
        $session = CheckoutSession::create([
            'user_id' => $buyer->id,
            'currency' => 'VND',
            'subtotal_amount' => 40000,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 40000,
        ]);
        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $buyer->id,
            'vendor_id' => Warehouse::withoutGlobalScopes()->findOrFail($listing->warehouse_id)->vendor_id,
            'total_amount' => 40000,
            'status' => 'processing',
            'payment_status' => 'paid',
            'refund_status' => 'none',
            'payment_method' => 'cod',
            'shipping_address' => 'Buyer',
            'phone' => '0911111111',
            'shipping_status' => 'shipped',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $listing->book_id,
            'quantity' => 2,
            'price' => 20000,
        ]);
        $reservation = InventoryReservation::create([
            'checkout_session_id' => $session->id,
            'order_item_id' => $item->id,
            'book_id' => $listing->book_id,
            'quantity' => 2,
            'status' => InventoryReservationStatus::COMMITTED,
            'operation_key' => 'batch4-restore-fixture-'.$item->id,
            'committed_at' => now(),
        ]);
        if ($allocationQuantity !== null) {
            InventoryReservationAllocation::create([
                'inventory_reservation_id' => $reservation->id,
                'warehouse_stock_id' => $stock->id,
                'quantity' => $allocationQuantity,
            ]);
        }

        return compact('listing', 'stock', 'order', 'reservation');
    }

    private function canonicalListing(): UsedBookListing
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => 'customer']);
        SellerFulfillmentAddress::create(['user_id' => $seller->id, 'recipient_name' => 'Seller', 'phone' => '0900000000', 'address_line' => 'Verified address', 'province' => 'Da Nang', 'status' => 'verified', 'verified_at' => now()]);
        $category = Category::create(['name' => 'Used '.$seller->id, 'slug' => 'used-'.$seller->id]);
        $response = $this->actingAs($seller)->postJson('/api/used-book-seller/listings', [
            'title' => 'Canonical used book '.$seller->id, 'author_name' => 'Author', 'category_id' => $category->id,
            'price' => 20000, 'condition' => 'good', 'quantity' => 1,
            'actual_photos' => [UploadedFile::fake()->image('book.jpg')], 'authenticity_attested' => true,
        ])->assertCreated();

        return UsedBookListing::where('book_id', $response->json('data.book.id'))->firstOrFail();
    }
}

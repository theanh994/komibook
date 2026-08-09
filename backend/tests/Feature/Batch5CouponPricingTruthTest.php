<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleBook;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Batch5CouponPricingTruthTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'Batch5', 'slug' => 'batch5-'.uniqid()]);
    }

    public function test_shipping_policy_get_is_public_and_read_only(): void
    {
        $before = DB::table('checkout_session_orders')->count();

        $this->getJson('/api/commerce/shipping-policy')
            ->assertOk()
            ->assertJsonPath('data.version', 'compatibility-v1')
            ->assertJsonPath('data.currency', 'VND')
            ->assertJsonPath('data.free_shipping_threshold', 200000)
            ->assertJsonPath('data.base_fee_per_physical_vendor', 15000);

        $this->getJson('/api/commerce/shipping-policy')->assertOk();
        $this->assertSame($before, DB::table('checkout_session_orders')->count());
    }

    public function test_pricing_truth_columns_leave_legacy_rows_explicitly_unknown(): void
    {
        $user = User::factory()->create();
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create(['user_id' => $vendorUser->id, 'shop_name' => 'Legacy vendor', 'slug' => 'legacy-vendor', 'status' => 'active']);
        $session = CheckoutSession::create(['user_id' => $user->id, 'currency' => 'VND', 'subtotal_amount' => 1, 'discount_amount' => 0, 'fee_amount' => 0, 'total_amount' => 1]);
        $order = Order::create(['user_id' => $user->id, 'vendor_id' => $vendor->id, 'order_code' => 'ORD-LEGACY-PRICING', 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod', 'total_amount' => 1, 'phone' => '0900000000', 'shipping_address' => 'Legacy']);
        $legacy = CheckoutSessionOrder::create(['checkout_session_id' => $session->id, 'order_id' => $order->id, 'vendor_id' => $vendor->id, 'subtotal_amount' => 1, 'discount_amount' => 0, 'fee_amount' => 0, 'commission_rate' => 0, 'commission_amount' => 0, 'total_amount' => 1]);

        $this->assertNull($legacy->coupon_id);
        $this->assertNull($legacy->coupon_discount_amount);
        $this->assertNull($legacy->membership_discount_amount);
        $this->assertNull($legacy->shipping_fee_amount);
        $this->assertNull($legacy->pricing_policy_snapshot);
    }

    public function test_pricing_truth_migration_down_and_up_only_round_trips_its_own_columns(): void
    {
        $migration = require database_path('migrations/2026_08_09_000003_add_pricing_truth_to_checkout_session_orders.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('checkout_session_orders', 'pricing_policy_snapshot'));
        $this->assertTrue(Schema::hasColumn('checkout_session_orders', 'commerce_fee_schedule_id'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('checkout_session_orders', 'coupon_id'));
        $this->assertTrue(Schema::hasColumn('checkout_session_orders', 'pricing_policy_snapshot'));
    }

    public function test_ship_like_code_with_invalid_canonical_type_is_rejected_atomically(): void
    {
        [$user, $book] = $this->checkoutFixture();
        $coupon = $this->coupon(['code' => 'SHIP-LEGACY', 'coupon_type' => 'legacy']);

        $this->assertCouponRejected($user, $book, $coupon);
    }

    public function test_inactive_exhausted_zero_and_inapplicable_coupons_are_rejected_before_writes(): void
    {
        [$user, $book] = $this->checkoutFixture();

        foreach ([
            $this->coupon(['code' => 'INACTIVE', 'status' => 'inactive']),
            $this->coupon(['code' => 'EXHAUSTED', 'usage_limit' => 1, 'used_count' => 1]),
            $this->coupon(['code' => 'ZERO', 'discount_percent' => 0]),
            $this->coupon(['code' => 'OTHER-VENDOR', 'vendor_id' => $this->createVendor()->id]),
        ] as $coupon) {
            $this->assertCouponRejected($user, $book, $coupon);
        }
    }

    public function test_positive_coupon_is_snapshotted_and_increments_once_per_successful_session(): void
    {
        Queue::fake();
        [$user, $book] = $this->checkoutFixture();
        $coupon = $this->coupon(['code' => 'PRODUCT-10', 'discount_percent' => 10]);

        app(CheckoutService::class)->processCheckout([['book_id' => $book->id, 'quantity' => 1]], $this->shipping(), $user->id, $coupon->code);

        $snapshot = CheckoutSessionOrder::sole();
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertSame($coupon->id, $snapshot->coupon_id);
        $this->assertSame('product', $snapshot->coupon_type);
        $this->assertSame('PRODUCT-10', $snapshot->coupon_code);
        $this->assertSame(10000, $snapshot->coupon_discount_amount);
        $this->assertSame(15000, $snapshot->shipping_fee_amount);
        $this->assertSame('compatibility-v1', $snapshot->pricing_policy_snapshot['version']);
    }

    public function test_preview_ignores_forged_client_total_price_and_category(): void
    {
        [$user, $book] = $this->checkoutFixture();
        $coupon = $this->coupon(['code' => 'SERVER-PRICE', 'discount_percent' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/coupons/apply', [
            'code' => $coupon->code,
            'total_amount' => 999999999,
            'items' => [['id' => $book->id, 'quantity' => 1, 'price' => 1, 'category_id' => 999999]],
        ])->assertOk()->assertJsonPath('data.discount_amount', 10000);
    }

    public function test_preview_rejects_numeric_equivalent_duplicate_book_ids_without_writes(): void
    {
        [$user, $book] = $this->checkoutFixture();
        $coupon = $this->coupon(['code' => 'DUPLICATE-PREVIEW']);
        Sanctum::actingAs($user);
        $before = CheckoutSessionOrder::count();

        $this->postJson('/api/coupons/apply', ['code' => $coupon->code, 'items' => [
            ['id' => (string) $book->id, 'quantity' => 1],
            ['id' => $book->id, 'quantity' => 1],
        ]])->assertStatus(422);
        $this->assertSame($before, CheckoutSessionOrder::count());
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_checkout_rejects_duplicate_book_ids_before_any_write_or_promotion_mutation(): void
    {
        Queue::fake();
        [$user, $book] = $this->checkoutFixture();
        $coupon = $this->coupon(['code' => 'DUPLICATE-CHECKOUT']);

        try {
            app(CheckoutService::class)->processCheckout([
                ['book_id' => (string) $book->id, 'quantity' => 1],
                ['book_id' => $book->id, 'quantity' => 1],
            ], $this->shipping(), $user->id, $coupon->code);
            $this->fail('Expected duplicate book validation failure.');
        } catch (ValidationException) {
            $this->assertSame(0, CheckoutSession::count());
            $this->assertSame(0, CheckoutSessionOrder::count());
            $this->assertSame(0, Order::count());
            $this->assertSame(0, $coupon->fresh()->used_count);
        }
    }

    public function test_preview_rejects_unavailable_books_without_writes(): void
    {
        [$user, $book] = $this->checkoutFixture();
        $coupon = $this->coupon(['code' => 'UNAVAILABLE', 'discount_percent' => 10]);
        $book->update(['status' => 'draft']);
        Sanctum::actingAs($user);
        $before = CheckoutSessionOrder::count();

        $this->postJson('/api/coupons/apply', ['code' => $coupon->code, 'items' => [['id' => $book->id, 'quantity' => 1]]])
            ->assertStatus(400);
        $this->assertSame($before, CheckoutSessionOrder::count());
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_preview_rejects_flash_sale_coupon_stacking_deny(): void
    {
        [$user, $book] = $this->checkoutFixture();
        $coupon = $this->coupon(['code' => 'DENIED-STACK', 'discount_percent' => 10]);
        $sale = FlashSale::create(['title' => 'Deny '.uniqid(), 'start_time' => now()->subMinute(), 'end_time' => now()->addMinute(), 'is_active' => true, 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'coupon_stacking_policy' => 'deny']);
        FlashSaleBook::create(['flash_sale_id' => $sale->id, 'book_id' => $book->id, 'vendor_id' => $book->vendor_id, 'discount_percent' => 10, 'max_quantity' => 0, 'sold_quantity' => 0, 'status' => 'approved']);
        Sanctum::actingAs($user);

        $this->postJson('/api/coupons/apply', ['code' => $coupon->code, 'items' => [['id' => $book->id, 'quantity' => 1]]])
            ->assertStatus(400);
    }

    public function test_secondary_category_preview_and_checkout_use_the_same_quote_engine(): void
    {
        [$user, $book] = $this->checkoutFixture();
        $secondary = Category::create(['name' => 'Secondary '.uniqid(), 'slug' => 'secondary-'.uniqid()]);
        $book->categories()->attach($secondary->id);
        $coupon = $this->coupon(['code' => 'SECONDARY', 'category_id' => $secondary->id, 'discount_percent' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/coupons/apply', ['code' => $coupon->code, 'items' => [['id' => $book->id, 'quantity' => 1]]])
            ->assertOk()->assertJsonPath('data.discount_amount', 10000);
        app(CheckoutService::class)->processCheckout([['book_id' => $book->id, 'quantity' => 1]], $this->shipping(), $user->id, $coupon->code);
        $this->assertSame(10000, CheckoutSessionOrder::sole()->coupon_discount_amount);
    }

    public function test_shipping_coupon_cap_allocation_matches_preview_and_persists_total_coupon_benefit(): void
    {
        Queue::fake();
        [$user, $firstBook] = $this->checkoutFixture(50000);
        $secondBook = $this->bookForVendor($this->createVendor(), 50000);
        $coupon = $this->coupon(['code' => 'SHIP-3333', 'coupon_type' => 'shipping', 'discount_percent' => 33.33, 'max_discount_amount' => 9999]);
        Sanctum::actingAs($user);

        $this->postJson('/api/coupons/apply', ['code' => $coupon->code, 'items' => [
            ['id' => $firstBook->id, 'quantity' => 1],
            ['id' => $secondBook->id, 'quantity' => 1],
        ]])->assertOk()->assertJsonPath('data.discount_amount', 9999);
        app(CheckoutService::class)->processCheckout([
            ['book_id' => $firstBook->id, 'quantity' => 1],
            ['book_id' => $secondBook->id, 'quantity' => 1],
        ], $this->shipping(), $user->id, $coupon->code);

        $snapshots = CheckoutSessionOrder::orderBy('vendor_id')->get();
        $this->assertSame(9999, $snapshots->sum('coupon_discount_amount'));
        $this->assertSame(20001, $snapshots->sum('shipping_fee_amount'));
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertSame([5000, 4999], $snapshots->pluck('coupon_discount_amount')->all());
    }

    public function test_downstream_failure_rolls_back_coupon_usage_and_checkout_rows(): void
    {
        Queue::fake();
        [$user, $book] = $this->checkoutFixture();
        $coupon = $this->coupon(['code' => 'ROLLBACK-10', 'discount_percent' => 10]);
        DB::statement("CREATE TRIGGER fail_batch5_snapshot BEFORE INSERT ON checkout_session_orders BEGIN SELECT RAISE(ABORT, 'batch5 failure'); END;");

        try {
            app(CheckoutService::class)->processCheckout([['book_id' => $book->id, 'quantity' => 1]], $this->shipping(), $user->id, $coupon->code);
            $this->fail('Expected injected downstream failure.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('batch5 failure', $exception->getMessage());
        }

        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertSame(0, CheckoutSession::count());
        $this->assertSame(0, CheckoutSessionOrder::count());
    }

    public function test_online_rejection_does_not_expire_existing_unpaid_session_before_locked_coupon_validation(): void
    {
        [$user, $book] = $this->checkoutFixture();
        $oldSession = CheckoutSession::create(['user_id' => $user->id, 'currency' => 'VND', 'subtotal_amount' => 1, 'discount_amount' => 0, 'fee_amount' => 0, 'total_amount' => 1, 'expires_at' => now()->subMinute()]);
        $oldOrder = Order::create(['user_id' => $user->id, 'vendor_id' => $book->vendor_id, 'order_code' => 'ORD-B5-OLD', 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'online', 'total_amount' => 1, 'phone' => '0900000000', 'shipping_address' => 'Old']);
        CheckoutSessionOrder::create(['checkout_session_id' => $oldSession->id, 'order_id' => $oldOrder->id, 'vendor_id' => $book->vendor_id, 'subtotal_amount' => 1, 'discount_amount' => 0, 'fee_amount' => 0, 'commission_rate' => 0, 'commission_amount' => 0, 'total_amount' => 1]);
        $coupon = $this->coupon(['code' => 'STALE', 'usage_limit' => 1, 'used_count' => 1]);

        $coupon->update(['used_count' => 0]);
        Sanctum::actingAs($user);
        $this->postJson('/api/coupons/apply', [
            'code' => $coupon->code,
            'total_amount' => 100000,
            'items' => [['id' => $book->id, 'price' => 100000, 'quantity' => 1]],
        ])->assertOk();
        $coupon->update(['used_count' => 1]);

        $this->expectException(ValidationException::class);
        try {
            app(CheckoutService::class)->processCheckout([['book_id' => $book->id, 'quantity' => 1]], [...$this->shipping(), 'payment_method' => 'online'], $user->id, $coupon->code);
        } finally {
            $this->assertSame('pending', $oldOrder->fresh()->status);
            $this->assertSame(1, CheckoutSession::count());
        }
    }

    private function assertCouponRejected(User $user, Book $book, Coupon $coupon): void
    {
        $initialUsedCount = $coupon->used_count;
        try {
            app(CheckoutService::class)->processCheckout([['book_id' => $book->id, 'quantity' => 1]], $this->shipping(), $user->id, $coupon->code);
            $this->fail('Expected coupon validation failure.');
        } catch (ValidationException) {
            $this->assertSame(0, CheckoutSession::count());
            $this->assertSame(0, CheckoutSessionOrder::count());
            $this->assertSame($initialUsedCount, $coupon->fresh()->used_count);
        }
    }

    /** @return array{0:User,1:Book} */
    private function checkoutFixture(int $price = 100000): array
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->bookForVendor($vendor, $price);

        return [$user, $book];
    }

    private function bookForVendor(Vendor $vendor, int $price): Book
    {
        $book = Book::create(['vendor_id' => $vendor->id, 'category_id' => $this->category->id, 'title' => 'Batch5 '.uniqid(), 'slug' => 'batch5-book-'.uniqid(), 'author' => 'Author', 'price' => $price, 'stock' => 10, 'type' => 'physical', 'status' => 'published']);
        $warehouse = Warehouse::create(['vendor_id' => $vendor->id, 'name' => 'Batch5 warehouse '.uniqid(), 'address' => 'Address', 'capacity' => 100, 'status' => 'active']);
        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'book_id' => $book->id, 'quantity' => 10]);

        return $book;
    }

    private function createVendor(): Vendor
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);

        return Vendor::create(['user_id' => $vendorUser->id, 'shop_name' => 'Batch5 vendor '.uniqid(), 'slug' => 'batch5-vendor-'.uniqid(), 'status' => 'active']);
    }

    private function coupon(array $overrides = []): Coupon
    {
        return Coupon::create([...['code' => 'B5-'.uniqid(), 'coupon_type' => 'product', 'discount_percent' => 10, 'min_order_value' => 1, 'usage_limit' => 10, 'used_count' => 0, 'status' => 'active'], ...$overrides]);
    }

    private function shipping(): array
    {
        return ['shipping_address' => 'Batch5 address', 'phone' => '0900000000'];
    }
}

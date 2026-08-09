<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\Coupon;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Phase2CheckoutSessionWritePathTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('ping')->andThrow(new Exception('Redis disabled in test environment'));
        File::shouldReceive('exists')->byDefault()->andReturn(false);

        $this->category = Category::create([
            'name' => 'General Category',
            'slug' => 'general-category-'.uniqid(),
        ]);
    }

    private function createVendor(): Vendor
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $uniqueId = uniqid();

        return Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Store '.$uniqueId,
            'slug' => 'store-'.$uniqueId,
            'status' => 'active',
        ]);
    }

    private function createBook(Vendor $vendor, int $price = 100000, int $stock = 50): Book
    {
        $uniqueId = uniqid();

        $book = Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $this->category->id,
            'title' => 'Book '.$uniqueId,
            'slug' => 'book-'.$uniqueId,
            'author' => 'Author Name',
            'price' => $price,
            'stock' => $stock,
            'type' => 'physical',
            'status' => 'published',
        ]);

        $warehouse = Warehouse::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => 'Warehouse '.$vendor->id, 'address' => '123 St', 'capacity' => 1000, 'status' => 'active']
        );

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'book_id' => $book->id,
            'quantity' => $stock,
        ]);

        return $book;
    }

    /**
     * 1. Checkout một vendor: tạo 1 session, 1 order link, snapshot/relationships đúng và trả về mảng order.
     */
    public function test_checkout_single_vendor_creates_one_session_one_order_and_link(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 150000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => '123 Main St', 'phone' => '0901234567'],
            $user->id
        );

        $this->assertIsArray($orders);
        $this->assertCount(1, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);

        $this->assertEquals(1, CheckoutSession::count());
        $this->assertEquals(1, CheckoutSessionOrder::count());
        $this->assertEquals(1, Order::count());

        $session = CheckoutSession::first();
        $sessionOrder = CheckoutSessionOrder::first();
        $order = Order::first();

        $this->assertEquals($user->id, $session->user_id);
        $this->assertEquals(300000, $session->subtotal_amount);
        $this->assertEquals(0, $session->discount_amount);
        $this->assertEquals(0, $session->fee_amount);
        $this->assertEquals(300000, $session->total_amount);
        $this->assertEquals('confirmed', $order->status);

        $this->assertEquals($session->id, $sessionOrder->checkout_session_id);
        $this->assertEquals($order->id, $sessionOrder->order_id);
        $this->assertEquals($vendor->id, $sessionOrder->vendor_id);
        $this->assertEquals(300000, $sessionOrder->subtotal_amount);
        $this->assertEquals(300000, $sessionOrder->total_amount);

        // Verify relationships
        $this->assertEquals($session->id, $order->checkoutSessionOrder->checkout_session_id);
        $this->assertEquals($order->id, $session->checkoutSessionOrders->first()->order_id);

        Queue::assertPushed(ProcessOrder::class, 1);
    }

    /**
     * 2. Checkout ba vendor: tạo 1 session, 3 orders và 3 links, tổng session bằng tổng snapshots.
     */
    public function test_checkout_multiple_vendors_creates_one_session_multiple_orders_and_links(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $vendor1 = $this->createVendor();
        $vendor2 = $this->createVendor();
        $vendor3 = $this->createVendor();

        $book1 = $this->createBook($vendor1, 100000);
        $book2 = $this->createBook($vendor2, 200000);
        $book3 = $this->createBook($vendor3, 300000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [
                ['book_id' => $book1->id, 'quantity' => 1],
                ['book_id' => $book2->id, 'quantity' => 1],
                ['book_id' => $book3->id, 'quantity' => 1],
            ],
            ['shipping_address' => '456 Multi St', 'phone' => '0909999999'],
            $user->id
        );

        $this->assertCount(3, $orders);
        $this->assertEquals(1, CheckoutSession::count());
        $this->assertEquals(3, CheckoutSessionOrder::count());
        $this->assertEquals(3, Order::count());

        $session = CheckoutSession::first();
        $sessionOrders = CheckoutSessionOrder::all();

        $this->assertEquals(600000, $session->subtotal_amount);
        $this->assertEquals(600000, $session->total_amount);

        $this->assertEquals($session->subtotal_amount, $sessionOrders->sum('subtotal_amount'));
        $this->assertEquals($session->total_amount, $sessionOrders->sum('total_amount'));
        $this->assertEquals($session->discount_amount, $sessionOrders->sum('discount_amount'));
        $this->assertEquals($session->fee_amount, $sessionOrders->sum('fee_amount'));

        foreach ($sessionOrders as $so) {
            $matchingOrder = Order::find($so->order_id);
            $this->assertNotNull($matchingOrder);
            $this->assertEquals($matchingOrder->vendor_id, $so->vendor_id);
        }

        Queue::assertPushed(ProcessOrder::class, 3);
    }

    /**
     * 3. Coupon và Membership discount được cộng dồn và tính toán chính xác số nguyên.
     */
    public function test_coupon_and_membership_discount_calculation_and_integers(): void
    {
        Queue::fake();

        $membershipTier = MembershipTier::create([
            'name' => 'VIP Gold',
            'min_points' => 100,
            'discount_percent' => 10,
        ]);

        $user = User::factory()->create([
            'membership_tier_id' => $membershipTier->id,
        ]);

        $coupon = Coupon::create([
            'code' => 'DISCOUNT20',
            'discount_percent' => 20,
            'min_order_value' => 50000,
            'usage_limit' => 10,
            'used_count' => 0,
        ]);

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => '789 VIP Rd', 'phone' => '0901111111'],
            $user->id,
            'DISCOUNT20'
        );

        $session = CheckoutSession::first();
        $sessionOrder = CheckoutSessionOrder::first();

        $this->assertIsInt($session->discount_amount);
        $this->assertIsInt($session->total_amount);
        $this->assertEquals(28000, $session->discount_amount);
        $this->assertEquals(87000, $session->total_amount);

        $this->assertIsInt($sessionOrder->discount_amount);
        $this->assertIsInt($sessionOrder->total_amount);
        $this->assertEquals(28000, $sessionOrder->discount_amount);
        $this->assertEquals(87000, $sessionOrder->total_amount);

        // Verify coupon used_count incremented
        $this->assertEquals(1, $coupon->fresh()->used_count);
        $this->assertSame($coupon->id, $sessionOrder->coupon_id);
        $this->assertSame('product', $sessionOrder->coupon_type);
        $this->assertSame('DISCOUNT20', $sessionOrder->coupon_code);
        $this->assertSame(20000, $sessionOrder->coupon_discount_amount);
        $this->assertSame(8000, $sessionOrder->membership_discount_amount);
        $this->assertSame(15000, $sessionOrder->shipping_fee_amount);
        $this->assertSame('compatibility-v1', $sessionOrder->pricing_policy_snapshot['version']);
    }

    /**
     * 4. Data provider test cho parser commission rate mà không truy cập filesystem thật.
     */
    #[DataProvider('commissionParserCasesProvider')]
    public function test_commission_rate_parser_cases(?string $fileContent, bool $fileExists, float $expectedRate): void
    {
        $configPath = storage_path('app/private/system_config.json');

        File::shouldReceive('exists')->with($configPath)->andReturn($fileExists);
        if ($fileExists) {
            File::shouldReceive('get')->with($configPath)->andReturn($fileContent);
        }

        $service = new class extends CheckoutService
        {
            public function test_get_commission_rate(): float
            {
                return $this->getCommissionRate();
            }
        };

        $this->assertEquals($expectedRate, $service->test_get_commission_rate());
    }

    public static function commissionParserCasesProvider(): array
    {
        return [
            'file does not exist' => [null, false, 10.0],
            'invalid json' => ['{invalid_json', true, 10.0],
            'missing commission_rate key' => ['{"other_key": 50}', true, 10.0],
            'non numeric commission_rate' => ['{"commission_rate": "invalid"}', true, 10.0],
            'valid rate' => ['{"commission_rate": 15.5}', true, 15.5],
            'negative rate clamped to 0' => ['{"commission_rate": -10}', true, 0.0],
            'rate above 100 clamped to 100' => ['{"commission_rate": 150}', true, 100.0],
        ];
    }

    /**
     * 5. Transaction rollback: SQLite trigger làm insert checkout_session_orders thứ hai thất bại bên trong transaction.
     */
    public function test_transaction_rollback_on_failure_leaves_no_records_and_dispatches_no_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $coupon = Coupon::create([
            'code' => 'FAILTEST',
            'discount_percent' => 10,
            'min_order_value' => 10000,
            'usage_limit' => 5,
            'used_count' => 0,
        ]);

        $vendor1 = $this->createVendor();
        $vendor2 = $this->createVendor();

        $book1 = $this->createBook($vendor1, 100000);
        $book2 = $this->createBook($vendor2, 100000);

        DB::statement("
            CREATE TRIGGER fail_second_checkout_session_order
            BEFORE INSERT ON checkout_session_orders
            WHEN (SELECT COUNT(*) FROM checkout_session_orders) >= 1
            BEGIN
                SELECT RAISE(ABORT, 'Simulated database failure on second checkout_session_order');
            END;
        ");

        $checkoutService = new CheckoutService;

        try {
            $checkoutService->processCheckout(
                [
                    ['book_id' => $book1->id, 'quantity' => 1],
                    ['book_id' => $book2->id, 'quantity' => 1],
                ],
                ['shipping_address' => 'Fail Rd', 'phone' => '0900000000'],
                $user->id,
                'FAILTEST'
            );
            $this->fail('Expected QueryException was not thrown');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Simulated database failure on second checkout_session_order', $e->getMessage());
        } catch (Exception $e) {
            $this->assertStringContainsString('Simulated database failure on second checkout_session_order', $e->getMessage());
        }

        $this->assertEquals(0, CheckoutSession::count());
        $this->assertEquals(0, Order::count());
        $this->assertEquals(0, OrderItem::count());
        $this->assertEquals(0, CheckoutSessionOrder::count());
        $this->assertEquals(0, $coupon->fresh()->used_count);

        Queue::assertNothingPushed();
    }

    /**
     * 6. Legacy compatibility: order được tạo trước đó không có link vẫn truy vấn bình thường, relationship trả null.
     */
    public function test_legacy_order_without_checkout_session_order_link_returns_null(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();

        $legacyOrder = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
            'phone' => '0900000000',
            'shipping_address' => 'Old Address',
        ]);

        $this->assertNull($legacyOrder->checkoutSessionOrder);
    }

    /**
     * 7. Không có PaymentTransaction nào được tạo trong luồng checkout.
     */
    public function test_no_payment_transactions_are_created_during_checkout(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => '123 Street', 'phone' => '0901234567'],
            $user->id
        );

        $this->assertEquals(0, PaymentTransaction::count());
    }

    /**
     * 8. Multi-vendor session snapshot stores correct vendor and rejects invalid vendor reference.
     */
    public function test_multi_vendor_snapshot_stores_correct_vendor_and_rejects_invalid_vendor_reference(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor1 = $this->createVendor();
        $vendor2 = $this->createVendor();

        $book1 = $this->createBook($vendor1, 100000);
        $book2 = $this->createBook($vendor2, 200000);

        $checkoutService = new CheckoutService;
        $checkoutService->processCheckout(
            [
                ['book_id' => $book1->id, 'quantity' => 1],
                ['book_id' => $book2->id, 'quantity' => 1],
            ],
            ['shipping_address' => '123 Test St', 'phone' => '0901234567'],
            $user->id
        );

        $session = CheckoutSession::first();
        $snapshots = CheckoutSessionOrder::where('checkout_session_id', $session->id)->get();

        $this->assertCount(2, $snapshots);
        $snapVendorIds = $snapshots->pluck('vendor_id')->sort()->values()->toArray();
        $expectedVendorIds = collect([$vendor1->id, $vendor2->id])->sort()->values()->toArray();
        $this->assertEquals($expectedVendorIds, $snapVendorIds);

        foreach ($snapshots as $snap) {
            $order = Order::find($snap->order_id);
            $this->assertEquals($order->vendor_id, $snap->vendor_id);
        }

        // Test invalid vendor foreign key rejection
        $dummyOrder = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $vendor1->id,
            'order_code' => 'ORD-FK-TEST',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'online',
            'total_amount' => 100000,
            'phone' => '0901234567',
            'shipping_address' => '123 St',
        ]);

        $this->expectException(QueryException::class);
        CheckoutSessionOrder::create([
            'checkout_session_id' => $session->id,
            'order_id' => $dummyOrder->id,
            'vendor_id' => 999999, // Non-existent vendor
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ]);
    }
}

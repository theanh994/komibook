<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryReservationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Jobs\DeliverOrderSideEffect;
use App\Jobs\ProcessOrder;
use App\Mail\OrderSuccessMail;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\InventoryReservation;
use App\Models\LoyaltyPointLedger;
use App\Models\Order;
use App\Models\OrderSideEffectOutbox;
use App\Models\OrderTransitionOperation;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\VendorEarningLedger;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutSessionLifecycleService;
use App\Services\OrderFulfillmentService;
use App\Services\Payments\VnpayGateway;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase2CriticalJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private string $vnpayHashSecret = 'SECRETKEY1234567890ABCDEF123456';

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('ping')->andThrow(new Exception('Redis disabled in test environment'));

        config([
            'services.vnpay.tmn_code' => 'KOMITEST',
            'services.vnpay.hash_secret' => $this->vnpayHashSecret,
            'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'app.frontend_url' => 'http://localhost:5173',
        ]);

        $this->category = Category::create([
            'name' => 'Journey Category',
            'slug' => 'journey-category-'.uniqid(),
        ]);
    }

    private function createVendor(): Vendor
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $uniqueId = uniqid();

        return Vendor::create([
            'user_id' => $user->id,
            'shop_name' => 'Store '.$uniqueId,
            'slug' => 'store-'.$uniqueId,
            'status' => 'active',
        ]);
    }

    private function createBook(Vendor $vendor, string $type = 'physical', int $price = 100000, int $stock = 50): Book
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
            'type' => $type,
            'status' => 'published',
        ]);

        if ($type === 'physical') {
            $warehouse = Warehouse::firstOrCreate(
                ['vendor_id' => $vendor->id],
                ['name' => 'Warehouse '.$vendor->id, 'address' => '123 St', 'capacity' => 1000, 'status' => 'active']
            );

            WarehouseStock::create([
                'warehouse_id' => $warehouse->id,
                'book_id' => $book->id,
                'quantity' => $stock,
            ]);
        }

        return $book;
    }

    private function createSignedVnpayIpnParams(PaymentTransaction $paymentTxn, string $responseCode = '00'): array
    {
        $gateway = new VnpayGateway;
        $params = [
            'vnp_Amount' => (string) ((int) $paymentTxn->amount * 100),
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => '20260725190000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Payment for session',
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => 'https://komibook.id.vn/return',
            'vnp_TmnCode' => 'KOMITEST',
            'vnp_TxnRef' => $paymentTxn->provider_reference,
            'vnp_Version' => '2.1.0',
            'vnp_ResponseCode' => $responseCode,
            'vnp_TransactionNo' => '14112233',
            'vnp_TransactionStatus' => $responseCode,
            'vnp_PayDate' => '20260725193000',
        ];

        $vnpParams = [];
        foreach ($params as $k => $v) {
            if (str_starts_with($k, 'vnp_') && $k !== 'vnp_SecureHash' && $k !== 'vnp_SecureHashType') {
                $vnpParams[$k] = $v;
            }
        }

        $canonical = $gateway->buildCanonicalQuery($vnpParams);
        $params['vnp_SecureHash'] = $gateway->generateSignature($canonical, $this->vnpayHashSecret);

        return $params;
    }

    /**
     * Journey 1: COD đa nhà bán — hoàn tất xuyên suốt và replay idempotency.
     */
    public function test_journey_1_cod_multi_vendor_end_to_end_and_replay_idempotency(): void
    {
        Queue::fake();
        Mail::fake();

        $buyer = User::factory()->create(['email' => 'buyer_journey1@example.com']);
        Sanctum::actingAs($buyer);

        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();

        $bookA = $this->createBook($vendorA, 'physical', 150000, 50);
        $bookB = $this->createBook($vendorB, 'physical', 200000, 30);

        // 1. API Checkout COD multi-vendor
        $checkoutResponse = $this->postJson('/api/checkout', [
            'items' => [
                ['book_id' => $bookA->id, 'quantity' => 2],
                ['book_id' => $bookB->id, 'quantity' => 1],
            ],
            'shipping_address' => '456 Journey St',
            'phone' => '0908888888',
            'payment_method' => 'COD',
        ]);

        $checkoutResponse->assertStatus(201);
        $createdOrders = $checkoutResponse->json('data');
        $this->assertCount(2, $createdOrders);

        $orderAId = $createdOrders[0]['id'];
        $orderBId = $createdOrders[1]['id'];

        $orderA = Order::findOrFail($orderAId);
        $orderB = Order::findOrFail($orderBId);

        // 2. Verify Session & Reservations
        $session = CheckoutSession::where('user_id', $buyer->id)->firstOrFail();
        $this->assertEquals(2, CheckoutSessionOrder::where('checkout_session_id', $session->id)->count());

        $reservation = InventoryReservation::where('checkout_session_id', $session->id)->firstOrFail();
        $this->assertEquals(InventoryReservationStatus::RESERVED, $reservation->status);

        // On-hand stock not deducted yet
        $stockA = WarehouseStock::where('book_id', $bookA->id)->firstOrFail();
        $stockB = WarehouseStock::where('book_id', $bookB->id)->firstOrFail();
        $this->assertEquals(50, $stockA->quantity);
        $this->assertEquals(30, $stockB->quantity);

        // 3. Controlled ProcessOrder Job Execution
        (new ProcessOrder($orderA->id))->handle();
        (new ProcessOrder($orderB->id))->handle();

        $this->assertEquals('processing', $orderA->fresh()->status);
        $this->assertEquals('processing', $orderB->fresh()->status);
        $this->assertEquals(InventoryReservationStatus::COMMITTED, $reservation->fresh()->status);

        // Stock deducted by exact ordered quantities
        $this->assertEquals(48, $stockA->fresh()->quantity);
        $this->assertEquals(29, $stockB->fresh()->quantity);

        // Replay ProcessOrder while processing (idempotency check)
        (new ProcessOrder($orderA->id))->handle();
        (new ProcessOrder($orderB->id))->handle();
        $this->assertEquals(48, $stockA->fresh()->quantity);
        $this->assertEquals(29, $stockB->fresh()->quantity);

        // 4. Verify Outbox & Controlled Delivery Execution
        $outboxes = OrderSideEffectOutbox::whereIn('order_id', [$orderA->id, $orderB->id])->get();
        $this->assertCount(4, $outboxes); // 2 effects per order (notification + email)
        $this->assertCount(4, $outboxes->pluck('operation_key')->unique());

        foreach ($outboxes as $outbox) {
            $this->assertEquals('pending', $outbox->status);
            (new DeliverOrderSideEffect($outbox->id))->handle();
            $this->assertEquals('succeeded', $outbox->fresh()->status);
        }

        $this->assertEquals(2, UserNotification::where('user_id', $buyer->id)->count());
        Mail::assertSent(OrderSuccessMail::class, 2);

        // 5. State Machine Progress to Completed via OrderFulfillmentService
        $fulfillmentService = app(OrderFulfillmentService::class);

        // Order A fulfillment
        $fulfillmentService->updateOrderStatusByVendor($orderA->id, 'shipped', 'vendor', $vendorA->user_id);
        $fulfillmentService->updateShippingStatus($orderA->id, 'picked_up', 'GHN', 'GHN111', 'vendor', $vendorA->user_id);
        $fulfillmentService->updateShippingStatus($orderA->id, 'delivering', 'GHN', 'GHN111', 'vendor', $vendorA->user_id);
        $fulfillmentService->updateShippingStatus($orderA->id, 'delivered', 'GHN', 'GHN111', 'vendor', $vendorA->user_id);

        // Order B fulfillment
        $fulfillmentService->updateOrderStatusByVendor($orderB->id, 'shipped', 'vendor', $vendorB->user_id);
        $fulfillmentService->updateShippingStatus($orderB->id, 'picked_up', 'GHTK', 'GHTK222', 'vendor', $vendorB->user_id);
        $fulfillmentService->updateShippingStatus($orderB->id, 'delivering', 'GHTK', 'GHTK222', 'vendor', $vendorB->user_id);
        $fulfillmentService->updateShippingStatus($orderB->id, 'delivered', 'GHTK', 'GHTK222', 'vendor', $vendorB->user_id);

        $this->assertEquals('completed', $orderA->fresh()->status);
        $this->assertEquals('completed', $orderB->fresh()->status);

        // Ledgers created (2 vendor earnings, 2 loyalty point ledgers for 2 completed orders)
        $this->assertEquals(2, VendorEarningLedger::count());
        $this->assertEquals(2, LoyaltyPointLedger::where('user_id', $buyer->id)->count());

        foreach ([$orderA, $orderB] as $order) {
            $snapshot = CheckoutSessionOrder::where('order_id', $order->id)->firstOrFail();
            $grossAmount = (int) $snapshot->total_amount;
            $commissionAmount = (int) $snapshot->commission_amount;
            $expectedPoints = (int) floor($grossAmount / 10000);

            $earning = VendorEarningLedger::where('order_id', $order->id)->firstOrFail();
            $this->assertSame((int) $order->vendor_id, (int) $earning->vendor_id);
            $this->assertSame($grossAmount, (int) $earning->gross_amount);
            $this->assertSame($commissionAmount, (int) $earning->commission_amount);
            $this->assertSame($grossAmount - $commissionAmount, (int) $earning->net_amount);
            $this->assertSame('VND', $earning->currency);

            $points = LoyaltyPointLedger::where('order_id', $order->id)->firstOrFail();
            $this->assertSame($expectedPoints, (int) $points->points);
        }

        $initialOpCount = OrderTransitionOperation::count();
        $initialEarningCount = VendorEarningLedger::count();

        // 6. Replay Idempotency Verification for Outbox delivery & Fulfillment completion
        foreach ($outboxes as $outbox) {
            (new DeliverOrderSideEffect($outbox->id))->handle();
        }

        $fulfillmentService->updateShippingStatus($orderA->id, 'delivered', 'GHN', 'GHN111', 'vendor', $vendorA->user_id);
        $fulfillmentService->updateShippingStatus($orderB->id, 'delivered', 'GHTK', 'GHTK222', 'vendor', $vendorB->user_id);

        // Assert zero duplicate side effects or mutations
        $this->assertEquals('completed', $orderA->fresh()->status);
        $this->assertEquals('completed', $orderB->fresh()->status);
        $this->assertEquals(48, $stockA->fresh()->quantity);
        $this->assertEquals(29, $stockB->fresh()->quantity);
        $this->assertEquals(2, UserNotification::where('user_id', $buyer->id)->count());
        Mail::assertSent(OrderSuccessMail::class, 2);
        $this->assertEquals($initialEarningCount, VendorEarningLedger::count());
        $this->assertEquals(2, LoyaltyPointLedger::where('user_id', $buyer->id)->count());
        $this->assertEquals($initialOpCount, OrderTransitionOperation::count());
    }

    /**
     * Journey 2: VNPAY đa nhà bán — IPN là nguồn xác nhận và replay idempotency.
     */
    public function test_journey_2_vnpay_multi_vendor_ipn_confirmation_and_replay_idempotency(): void
    {
        Queue::fake();
        Mail::fake();

        $buyer = User::factory()->create(['email' => 'buyer_journey2@example.com']);
        Sanctum::actingAs($buyer);

        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();

        $bookA = $this->createBook($vendorA, 'physical', 100000, 20);
        $bookB = $this->createBook($vendorB, 'physical', 100000, 20);

        // 1. API Online Checkout
        $checkoutResponse = $this->postJson('/api/checkout', [
            'items' => [
                ['book_id' => $bookA->id, 'quantity' => 1],
                ['book_id' => $bookB->id, 'quantity' => 1],
            ],
            'shipping_address' => '789 Online St',
            'phone' => '0907777777',
            'payment_method' => 'VNPAY',
        ]);

        $checkoutResponse->assertStatus(201);
        $createdOrders = $checkoutResponse->json('data');

        $session = CheckoutSession::where('user_id', $buyer->id)->firstOrFail();

        // Orders created in pending status, ProcessOrder NOT queued yet
        Queue::assertNothingPushed();

        // 2. Initiate VNPAY Payment Attempt via order_id
        $paymentResponse = $this->postJson('/api/vnpay/create', [
            'order_id' => $createdOrders[0]['id'],
        ]);
        $paymentResponse->assertStatus(200);

        $paymentTxn = PaymentTransaction::where('checkout_session_id', $session->id)->firstOrFail();
        $this->assertEquals(PaymentTransactionStatus::PENDING, $paymentTxn->status);

        // 3. Send Signed IPN Callback (Success Code '00')
        $ipnParams = $this->createSignedVnpayIpnParams($paymentTxn, '00');
        $ipnResponse = $this->getJson('/api/vnpay/ipn?'.http_build_query($ipnParams));

        $ipnResponse->assertStatus(200);
        $ipnResponse->assertJson(['RspCode' => '00']);

        $paymentTxn->refresh();
        $this->assertEquals(PaymentTransactionStatus::PAID, $paymentTxn->status);

        $orderA = Order::findOrFail($createdOrders[0]['id']);
        $orderB = Order::findOrFail($createdOrders[1]['id']);
        $this->assertEquals('confirmed', $orderA->status);
        $this->assertEquals('confirmed', $orderB->status);

        Queue::assertPushed(ProcessOrder::class, 2);

        // 4. Controlled Execution of Processing, Outbox, and Fulfillment
        (new ProcessOrder($orderA->id))->handle();
        (new ProcessOrder($orderB->id))->handle();

        $outboxes = OrderSideEffectOutbox::whereIn('order_id', [$orderA->id, $orderB->id])->get();
        $this->assertCount(4, $outboxes);
        $this->assertCount(4, $outboxes->pluck('operation_key')->unique());
        $this->assertEquals(19, WarehouseStock::where('book_id', $bookA->id)->firstOrFail()->quantity);
        $this->assertEquals(19, WarehouseStock::where('book_id', $bookB->id)->firstOrFail()->quantity);

        // Replay processing before fulfillment must not deduct stock or create outboxes again.
        (new ProcessOrder($orderA->id))->handle();
        (new ProcessOrder($orderB->id))->handle();
        $this->assertCount(4, OrderSideEffectOutbox::whereIn('order_id', [$orderA->id, $orderB->id])->get());
        $this->assertEquals(19, WarehouseStock::where('book_id', $bookA->id)->firstOrFail()->quantity);
        $this->assertEquals(19, WarehouseStock::where('book_id', $bookB->id)->firstOrFail()->quantity);

        foreach ($outboxes as $outbox) {
            (new DeliverOrderSideEffect($outbox->id))->handle();
        }

        $fulfillmentService = app(OrderFulfillmentService::class);

        $fulfillmentService->updateOrderStatusByVendor($orderA->id, 'shipped', 'vendor', $vendorA->user_id);
        $fulfillmentService->updateShippingStatus($orderA->id, 'picked_up', 'GHN', 'GHN333', 'vendor', $vendorA->user_id);
        $fulfillmentService->updateShippingStatus($orderA->id, 'delivering', 'GHN', 'GHN333', 'vendor', $vendorA->user_id);
        $fulfillmentService->updateShippingStatus($orderA->id, 'delivered', 'GHN', 'GHN333', 'vendor', $vendorA->user_id);

        $fulfillmentService->updateOrderStatusByVendor($orderB->id, 'shipped', 'vendor', $vendorB->user_id);
        $fulfillmentService->updateShippingStatus($orderB->id, 'picked_up', 'GHTK', 'GHTK444', 'vendor', $vendorB->user_id);
        $fulfillmentService->updateShippingStatus($orderB->id, 'delivering', 'GHTK', 'GHTK444', 'vendor', $vendorB->user_id);
        $fulfillmentService->updateShippingStatus($orderB->id, 'delivered', 'GHTK', 'GHTK444', 'vendor', $vendorB->user_id);

        $this->assertEquals('completed', $orderA->fresh()->status);
        $this->assertEquals('completed', $orderB->fresh()->status);

        // 5. Replay IPN Request & Outbox Jobs
        $ipnReplayResponse = $this->getJson('/api/vnpay/ipn?'.http_build_query($ipnParams));
        $ipnReplayResponse->assertStatus(200);
        $ipnReplayResponse->assertJson(['RspCode' => '00']);
        Queue::assertPushed(ProcessOrder::class, 2);

        foreach ($outboxes as $outbox) {
            (new DeliverOrderSideEffect($outbox->id))->handle();
        }

        $this->assertEquals(1, PaymentTransaction::where('checkout_session_id', $session->id)->count());
        $this->assertEquals(PaymentTransactionStatus::PAID, $paymentTxn->fresh()->status);
        $this->assertEquals(19, WarehouseStock::where('book_id', $bookA->id)->first()->quantity);
        $this->assertEquals(19, WarehouseStock::where('book_id', $bookB->id)->first()->quantity);
        $this->assertEquals(2, UserNotification::where('user_id', $buyer->id)->count());
        Mail::assertSent(OrderSuccessMail::class, 2);
        $this->assertEquals(2, VendorEarningLedger::count());
        $this->assertEquals(2, LoyaltyPointLedger::where('user_id', $buyer->id)->count());
    }

    /**
     * Journey 3: Đường thất bại trước commit.
     */
    public function test_journey_3_failure_path_before_commit_and_cleanup_convergence(): void
    {
        Queue::fake();
        Mail::fake();

        $buyer = User::factory()->create(['email' => 'buyer_journey3@example.com']);
        Sanctum::actingAs($buyer);

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->firstOrFail();

        // 1. API Online Checkout creates reservation
        $checkoutResponse = $this->postJson('/api/checkout', [
            'items' => [
                ['book_id' => $book->id, 'quantity' => 2],
            ],
            'shipping_address' => 'Fail Path St',
            'phone' => '0906666666',
            'payment_method' => 'VNPAY',
        ]);
        $checkoutResponse->assertStatus(201);

        $session = CheckoutSession::where('user_id', $buyer->id)->firstOrFail();
        $reservation = InventoryReservation::where('checkout_session_id', $session->id)->firstOrFail();

        $this->assertEquals(InventoryReservationStatus::RESERVED, $reservation->status);
        $this->assertEquals(10, $stock->fresh()->quantity);

        // 2. Simulate Expiry / Termination before commit (session reached expires_at)
        $session->expires_at = now()->subMinute();
        $session->save();

        $lifecycleService = app(CheckoutSessionLifecycleService::class);
        $lifecycleService->expireSession($session->id);

        $this->assertEquals(1, Order::where('status', 'cancelled')->count());
        $this->assertEquals(InventoryReservationStatus::EXPIRED, $reservation->fresh()->status);
        $this->assertEquals(10, $stock->fresh()->quantity); // Stock remains untouched

        // Assert NO outboxes, notifications, or ledgers created
        $this->assertEquals(0, OrderSideEffectOutbox::count());
        $this->assertEquals(0, UserNotification::count());
        $this->assertEquals(0, LoyaltyPointLedger::count());
        $this->assertEquals(0, VendorEarningLedger::count());
        Mail::assertNothingSent();

        // 3. Replay Expiry Operation (Convergence & Idempotency)
        $lifecycleService->expireSession($session->id);

        $this->assertEquals(InventoryReservationStatus::EXPIRED, $reservation->fresh()->status);
        $this->assertEquals(10, $stock->fresh()->quantity);
        $this->assertEquals(0, OrderSideEffectOutbox::count());
        $this->assertEquals(0, UserNotification::count());
        $this->assertEquals(0, LoyaltyPointLedger::count());
        $this->assertEquals(0, VendorEarningLedger::count());
    }
}

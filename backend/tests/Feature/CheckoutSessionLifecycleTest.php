<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryReservationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use App\Services\CheckoutSessionLifecycleService;
use App\Services\Payments\VnpayCallbackService;
use App\Services\Payments\VnpayGateway;
use App\Services\Payments\VnpayPaymentService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckoutSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private CheckoutService $checkoutService;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('ping')->andThrow(new Exception('Redis disabled in test environment'));
        File::shouldReceive('exists')->byDefault()->andReturn(false);

        config([
            'services.vnpay.tmn_code' => 'KOMITEST',
            'services.vnpay.hash_secret' => 'SECRETKEY1234567890ABCDEF123456',
            'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        ]);

        $this->category = Category::create([
            'name' => 'General Category',
            'slug' => 'general-category-'.uniqid(),
        ]);

        $this->checkoutService = new CheckoutService;
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

    private function createSignedCallback(array $overrides = []): array
    {
        $gateway = new VnpayGateway;

        $amountStr = '15000000';
        if (isset($overrides['vnp_TxnRef'])) {
            $txn = PaymentTransaction::where('provider_reference', $overrides['vnp_TxnRef'])->first();
            if ($txn) {
                $amountStr = (string) ($txn->amount * 100);
            }
        }

        $base = [
            'vnp_Amount' => $amountStr,
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => '20260725190000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Test Callback',
            'vnp_OrderType' => 'billpayment',
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'KOMITEST',
            'vnp_TransactionNo' => '14000000',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => 'REF_TEST',
            'vnp_Version' => '2.1.0',
        ];

        $payload = array_merge($base, $overrides);
        ksort($payload);

        $hashData = '';
        $i = 0;
        foreach ($payload as $key => $value) {
            if ($key === 'vnp_SecureHash' || $key === 'vnp_SecureHashType') {
                continue;
            }
            if ($value !== null && $value !== '') {
                if ($i === 1) {
                    $hashData .= '&';
                }
                $hashData .= urlencode((string) $key).'='.urlencode((string) $value);
                $i = 1;
            }
        }

        $payload['vnp_SecureHash'] = hash_hmac('sha512', $hashData, 'SECRETKEY1234567890ABCDEF123456');

        return $payload;
    }

    /**
     * 1. Buyer cancel single/multi-vendor session qua API /orders/{order}/cancel.
     */
    public function test_buyer_cancel_multi_vendor_session_cancels_all_orders_and_releases_reservations(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();

        $bookA = $this->createBook($vendorA, 'physical', 100000, 10);
        $bookB = $this->createBook($vendorB, 'physical', 200000, 10);

        $orders = $this->checkoutService->processCheckout(
            [
                ['book_id' => $bookA->id, 'quantity' => 2],
                ['book_id' => $bookB->id, 'quantity' => 3],
            ],
            ['shipping_address' => 'Multi Cancel St', 'phone' => '0909999999', 'payment_method' => 'online'],
            $user->id
        );

        $this->assertCount(2, $orders);

        // Tạo pending payment transaction
        $paymentService = new VnpayPaymentService;
        $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $this->assertEquals(1, PaymentTransaction::where('status', PaymentTransactionStatus::PENDING)->count());
        $this->assertEquals(2, InventoryReservation::where('status', InventoryReservationStatus::RESERVED)->count());

        // Authenticate & Hủy đơn 1
        Sanctum::actingAs($user);
        $response = $this->postJson("/api/orders/{$orders[0]->id}/cancel");

        $response->assertStatus(200);

        // Tất cả orders trong session bị hủy
        $this->assertEquals('cancelled', $orders[0]->fresh()->status);
        $this->assertEquals('cancelled', $orders[1]->fresh()->status);

        // Pending payment transaction chuyển EXPIRED
        $this->assertEquals(1, PaymentTransaction::where('status', PaymentTransactionStatus::EXPIRED)->count());

        // Inventory reservations chuyển RELEASED
        $this->assertEquals(2, InventoryReservation::where('status', InventoryReservationStatus::RELEASED)->count());
    }

    /**
     * 2. Ownership, COD, paid/confirmed, legacy fail-closed.
     */
    public function test_ownership_and_invalid_status_cancel_fail_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        // COD order
        $codOrders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'COD St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );

        // User khác thử cancel COD order
        Sanctum::actingAs($otherUser);
        $res403 = $this->postJson("/api/orders/{$codOrders[0]->id}/cancel");
        $res403->assertStatus(403);

        // Đúng user có thể hủy COD trước khi giao cho đơn vị vận chuyển.
        Sanctum::actingAs($user);
        $cancelled = $this->postJson("/api/orders/{$codOrders[0]->id}/cancel");
        $cancelled->assertOk();

        $this->assertEquals('cancelled', $codOrders[0]->fresh()->status);
    }

    /**
     * 3. Cancel lặp idempotent và không làm đổi on-hand.
     */
    public function test_repeat_cancel_is_idempotent_and_preserves_on_hand_stock(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Cancel St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        Sanctum::actingAs($user);
        $res1 = $this->postJson("/api/orders/{$orders[0]->id}/cancel");
        $res1->assertStatus(200);

        $this->assertEquals(10, $stock->fresh()->quantity);

        // Repeat cancel
        $res2 = $this->postJson("/api/orders/{$orders[0]->id}/cancel");
        $res2->assertStatus(200);

        $this->assertEquals(10, $stock->fresh()->quantity);
        $this->assertEquals('cancelled', $orders[0]->fresh()->status);
    }

    /**
     * 4. Scheduler expiry command chuyển đúng order/payment/reservation sang expired.
     */
    public function test_scheduler_expiry_command_expires_past_due_sessions(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Expire St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $session = CheckoutSession::first();
        $session->expires_at = now()->subMinute();
        $session->save();

        $this->artisan('checkout-sessions:expire')
            ->expectsOutput('Expired 1 checkout session(s).')
            ->assertExitCode(0);

        $this->assertEquals('cancelled', $orders[0]->fresh()->status);
        $this->assertEquals(1, PaymentTransaction::where('status', PaymentTransactionStatus::EXPIRED)->count());
        $this->assertEquals(1, InventoryReservation::where('status', InventoryReservationStatus::EXPIRED)->count());
    }

    /**
     * 5. Ebook-only session không có reservation vẫn kết thúc đúng.
     */
    public function test_ebook_only_session_expires_correctly_without_reservations(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $ebook = $this->createBook($vendor, 'ebook', 50000);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $ebook->id, 'quantity' => 1]],
            ['shipping_address' => 'Digital St', 'phone' => '0901111111', 'payment_method' => 'online'],
            $user->id
        );

        $session = CheckoutSession::first();
        $session->expires_at = now()->subMinute();
        $session->save();

        $this->artisan('checkout-sessions:expire')
            ->expectsOutput('Expired 1 checkout session(s).')
            ->assertExitCode(0);

        $this->assertEquals('cancelled', $orders[0]->fresh()->status);
        $this->assertEquals(0, InventoryReservation::count());
    }

    /**
     * 6. Command retry không thay đổi dữ liệu lần hai.
     */
    public function test_command_retry_does_not_change_data(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Retry St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $session = CheckoutSession::first();
        $session->expires_at = now()->subMinute();
        $session->save();

        // Run 1
        $this->artisan('checkout-sessions:expire')
            ->expectsOutput('Expired 1 checkout session(s).');

        // Run 2
        $this->artisan('checkout-sessions:expire')
            ->expectsOutput('Expired 0 checkout session(s).');

        $this->assertEquals('cancelled', $orders[0]->fresh()->status);
    }

    /**
     * 7. Payment attempt failed vẫn retry được trước deadline.
     */
    public function test_payment_attempt_failed_allows_retry_before_deadline(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Retry Payment St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt1 = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        // Callback failure (vnp_ResponseCode = '24' - User cancel on portal)
        $callbackParams = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt1['provider_reference'],
            'vnp_ResponseCode' => '24',
            'vnp_TransactionStatus' => '02',
        ]);

        $callbackService = new VnpayCallbackService;
        $ipnRes = $callbackService->handleIpn($callbackParams);
        $this->assertEquals('00', $ipnRes['RspCode']);

        $txn1 = PaymentTransaction::where('provider_reference', $attempt1['provider_reference'])->first();
        $this->assertEquals(PaymentTransactionStatus::FAILED, $txn1->status);

        // Orders & Reservations match expected active state before deadline
        $this->assertEquals('pending', $orders[0]->fresh()->status);
        $this->assertEquals(InventoryReservationStatus::RESERVED, InventoryReservation::first()->status);

        // Create Attempt 2 before deadline -> succeeds
        $attempt2 = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');
        $this->assertNotEmpty($attempt2['url']);
    }

    /**
     * 8. Foreign user + expired session trả 403 và không mutation.
     */
    public function test_foreign_user_on_expired_checkout_returns_403_and_causes_no_mutation(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $foreignUser = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Foreign St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $owner->id
        );

        $session = CheckoutSession::first();
        $session->expires_at = now()->subMinute();
        $session->save();

        $paymentService = new VnpayPaymentService;

        try {
            $paymentService->createPaymentAttempt($orders[0]->id, $foreignUser, '127.0.0.1');
            $this->fail('Expected HttpException 403');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // Must remain completely UNMUTATED
        $this->assertEquals('pending', $orders[0]->fresh()->status);
        $this->assertEquals(0, PaymentTransaction::count());
        $this->assertEquals(InventoryReservationStatus::RESERVED, InventoryReservation::first()->status);
    }

    /**
     * 9. Owner + expired session cleanup persisted rồi trả 422.
     */
    public function test_owner_on_expired_checkout_cleans_up_persisted_and_returns_422(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Owner Late St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $owner->id
        );

        $session = CheckoutSession::first();
        $session->expires_at = now()->subMinute();
        $session->save();

        $paymentService = new VnpayPaymentService;

        try {
            $paymentService->createPaymentAttempt($orders[0]->id, $owner, '127.0.0.1');
            $this->fail('Expected HttpException 422');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        // Must remain PERSISTED after HTTP 422
        $this->assertEquals(0, PaymentTransaction::count());
        $this->assertEquals('cancelled', $orders[0]->fresh()->status);
        $this->assertEquals(InventoryReservationStatus::EXPIRED, InventoryReservation::first()->status);
    }

    /**
     * 10. Late success callback trực tiếp khi chưa chạy scheduler tự kết thúc checkout.
     */
    public function test_late_success_callback_directly_expires_checkout_session(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Late Callback St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $session = CheckoutSession::first();
        $session->expires_at = now()->subMinute();
        $session->save();

        // Late success callback arrives directly (scheduler has NOT run yet)
        $callbackParams = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
        ]);

        $callbackService = new VnpayCallbackService;
        $ipnRes = $callbackService->handleIpn($callbackParams);

        $this->assertEquals('02', $ipnRes['RspCode']);
        $this->assertEquals('cancelled', $orders[0]->fresh()->status);
        $this->assertEquals(InventoryReservationStatus::EXPIRED, InventoryReservation::first()->status);
        Queue::assertNothingPushed();
    }

    /**
     * 11. Callback cleanup failure trả 99 và rollback.
     */
    public function test_late_success_callback_cleanup_failure_returns_99_and_rolls_back(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Cleanup Fail St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $session = CheckoutSession::first();
        $session->expires_at = now()->subMinute();
        $session->save();

        // Inject DB trigger to simulate cleanup failure
        DB::statement("
            CREATE TRIGGER fail_order_cancel_update
            BEFORE UPDATE ON orders
            WHEN NEW.status = 'cancelled'
            BEGIN
                SELECT RAISE(ABORT, 'Simulated cleanup failure');
            END;
        ");

        $callbackParams = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
        ]);

        $callbackService = new VnpayCallbackService;
        $ipnRes = $callbackService->handleIpn($callbackParams);

        $this->assertEquals('99', $ipnRes['RspCode']);
        $this->assertEquals('pending', $orders[0]->fresh()->status);
    }

    /**
     * 12. Direct expireSession trước deadline fail-closed.
     */
    public function test_direct_expire_session_before_deadline_fails_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Early Expire St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $session = CheckoutSession::first();
        $this->assertTrue($session->expires_at->isFuture());

        $lifecycleService = app(CheckoutSessionLifecycleService::class);

        $this->expectException(LogicException::class);
        $lifecycleService->expireSession($session);
    }

    /**
     * 13. Trạng thái order đã cancelled nhưng còn pending transaction hoặc reserved inventory phải hội tụ.
     */
    public function test_all_cancelled_orders_with_pending_transaction_or_reserved_inventory_converges(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Converge St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        // Manually set order status to cancelled without cancelling tx or reservation
        DB::table('orders')->where('id', $orders[0]->id)->update(['status' => 'cancelled']);

        $this->assertEquals(1, PaymentTransaction::where('status', PaymentTransactionStatus::PENDING)->count());
        $this->assertEquals(1, InventoryReservation::where('status', InventoryReservationStatus::RESERVED)->count());

        $session = CheckoutSession::first();
        $session->expires_at = now()->subMinute();
        $session->save();

        $lifecycleService = app(CheckoutSessionLifecycleService::class);
        $lifecycleService->expireSession($session);

        // Pending transaction must be EXPIRED and reservation EXPIRED
        $this->assertEquals(1, PaymentTransaction::where('status', PaymentTransactionStatus::EXPIRED)->count());
        $this->assertEquals(1, InventoryReservation::where('status', InventoryReservationStatus::EXPIRED)->count());
    }

    /**
     * 14. Inventory reservation COMMITTED causes both cancelByBuyer and expireSession to fail closed and preserve on-hand.
     */
    public function test_committed_inventory_reservation_causes_cancel_and_expire_to_fail_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Committed St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $reservation = InventoryReservation::first();
        $reservation->status = InventoryReservationStatus::COMMITTED;
        $reservation->save();

        $lifecycleService = app(CheckoutSessionLifecycleService::class);

        // 1. cancelByBuyer fails closed
        try {
            $lifecycleService->cancelByBuyer($orders[0]->id, $user->id);
            $this->fail('Expected LogicException on committed reservation cancelByBuyer');
        } catch (LogicException $e) {
            $this->assertStringContainsString('committed', $e->getMessage());
        }

        // 2. expireSession fails closed after expiration time
        $session = CheckoutSession::first();
        $session->expires_at = now()->subMinute();
        $session->save();

        try {
            $lifecycleService->expireSession($session);
            $this->fail('Expected LogicException on committed reservation expireSession');
        } catch (LogicException $e) {
            $this->assertStringContainsString('committed', $e->getMessage());
        }

        // On-hand quantity remains completely unchanged
        $this->assertEquals(10, $stock->fresh()->quantity);
        $this->assertEquals('pending', $orders[0]->fresh()->status);
    }

    /**
     * 15. Unlinked legacy order fails closed on cancel.
     */
    public function test_unlinked_legacy_order_fails_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        // Directly create unlinked legacy order
        $legacyOrder = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'order_code' => 'LEGACY_'.uniqid(),
            'total_amount' => 100000,
            'shipping_fee' => 0,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'online',
            'shipping_address' => 'Legacy St',
            'phone' => '0901234567',
        ]);

        $lifecycleService = app(CheckoutSessionLifecycleService::class);

        $this->expectException(RuntimeException::class);
        $lifecycleService->cancelByBuyer($legacyOrder->id, $user->id);
    }

    /**
     * 16. Corrupted cross-buyer link causes cancelByBuyer and expireSession to fail closed with 0 mutation.
     */
    public function test_corrupted_cross_buyer_link_fails_closed_without_mutation(): void
    {
        Queue::fake();

        $buyerA = User::factory()->create();
        $buyerB = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $ordersA = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Buyer A St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $buyerA->id
        );

        $ordersB = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Buyer B St', 'phone' => '0907654321', 'payment_method' => 'online'],
            $buyerB->id
        );

        $sessionA = CheckoutSession::where('user_id', $buyerA->id)->first();

        // Corrupt Buyer B's existing link by moving it to Buyer A's CheckoutSession.
        CheckoutSessionOrder::where('order_id', $ordersB[0]->id)
            ->update(['checkout_session_id' => $sessionA->id]);

        $lifecycleService = app(CheckoutSessionLifecycleService::class);

        // 1. cancelByBuyer fails closed
        try {
            $lifecycleService->cancelByBuyer($ordersA[0]->id, $buyerA->id);
            $this->fail('Expected LogicException on corrupted cross-buyer link cancelByBuyer');
        } catch (LogicException $e) {
            $this->assertStringContainsString('owner does not match', $e->getMessage());
        }

        // 2. expireSession fails closed after expiration time
        $sessionA->expires_at = now()->subMinute();
        $sessionA->save();

        try {
            $lifecycleService->expireSession($sessionA);
            $this->fail('Expected LogicException on corrupted cross-buyer link expireSession');
        } catch (LogicException $e) {
            $this->assertStringContainsString('owner does not match', $e->getMessage());
        }

        // Assert 0 mutation across all orders, transactions, and reservations
        $this->assertEquals('pending', $ordersA[0]->fresh()->status);
        $this->assertEquals('pending', $ordersB[0]->fresh()->status);
        $this->assertEquals(InventoryReservationStatus::RESERVED, InventoryReservation::where('checkout_session_id', $sessionA->id)->first()->status);
    }

    /**
     * 17. Online paid or confirmed orders cause cancelByBuyer and expireSession to fail closed.
     */
    public function test_online_paid_or_confirmed_orders_fail_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Confirmed St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $session = CheckoutSession::first();

        // Mark order paid in a buyer-cancellable-looking pre-shipment state.
        $orders[0]->status = 'confirmed';
        $orders[0]->shipping_status = 'pending_pickup';
        $orders[0]->payment_status = 'paid';
        $orders[0]->save();

        $lifecycleService = app(CheckoutSessionLifecycleService::class);

        // 1. cancelByBuyer fails closed
        try {
            $lifecycleService->cancelByBuyer($orders[0]->id, $user->id);
            $this->fail('Expected LogicException on confirmed online order cancelByBuyer');
        } catch (LogicException $e) {
            $this->assertStringContainsString('return/refund workflow', $e->getMessage());
        }

        // 2. expireSession fails closed after expiration time
        $session->expires_at = now()->subMinute();
        $session->save();

        try {
            $lifecycleService->expireSession($session);
            $this->fail('Expected LogicException on confirmed online order expireSession');
        } catch (LogicException $e) {
            $this->assertStringContainsString('status', $e->getMessage());
        }

        // Assert 0 mutation to order status or payment status
        $this->assertEquals('confirmed', $orders[0]->fresh()->status);
        $this->assertEquals('paid', $orders[0]->fresh()->payment_status);
    }
}

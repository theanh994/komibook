<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentTransactionStatus;
use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use App\Services\Payments\VnpayGateway;
use App\Services\Payments\VnpayPaymentService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class VnpayCallbackProcessingTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('ping')->andThrow(new Exception('Redis disabled in test environment'));
        File::shouldReceive('exists')->byDefault()->andReturn(false);

        config([
            'services.vnpay.tmn_code' => 'KOMITEST',
            'services.vnpay.hash_secret' => 'SECRETKEY1234567890ABCDEF123456',
            'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'app.frontend_url' => 'http://localhost:5173',
        ]);

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

    private function createSignedCallback(array $overrides = []): array
    {
        $gateway = new VnpayGateway;
        $base = [
            'vnp_Amount' => '15000000',
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => '20260725190000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Test Callback',
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => 'https://komibook.id.vn/return',
            'vnp_TmnCode' => 'KOMITEST',
            'vnp_TxnRef' => 'REF-TEST',
            'vnp_Version' => '2.1.0',
            'vnp_ResponseCode' => '00',
            'vnp_SecureHashType' => 'SHA512',
            'vnp_TransactionNo' => '14112233',
            'vnp_TransactionStatus' => '00',
            'vnp_PayDate' => '20260725193000',
        ];

        $params = array_merge($base, $overrides);
        unset($params['vnp_SecureHash']);

        $vnpParams = [];
        foreach ($params as $k => $v) {
            if (str_starts_with($k, 'vnp_') && $k !== 'vnp_SecureHash') {
                $vnpParams[$k] = $v;
            }
        }

        $canonical = $gateway->buildCanonicalQuery($vnpParams);
        $params['vnp_SecureHash'] = $gateway->generateSignature($canonical, 'SECRETKEY1234567890ABCDEF123456');

        return $params;
    }

    /**
     * 1. Success IPN một vendor cập nhật transaction/order (status: confirmed) và dispatch sau commit.
     */
    public function test_success_ipn_single_vendor_updates_transaction_and_order_to_confirmed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 150000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => '123 Main St', 'phone' => '0901234567', 'payment_method' => 'vnpay'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '30000000',
            'vnp_TransactionNo' => 'TXN-VNPAY-999',
        ]);

        $response = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        $response->assertStatus(200)
            ->assertJson(['RspCode' => '00', 'Message' => 'Confirm Success']);

        $txn = PaymentTransaction::first();
        $this->assertEquals(PaymentTransactionStatus::PAID, $txn->status);
        $this->assertEquals('TXN-VNPAY-999', $txn->provider_transaction_id);
        $this->assertNotNull($txn->paid_at);

        $order = $orders[0]->fresh();
        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals('paid', $order->payment_status);

        Queue::assertPushed(ProcessOrder::class, 1);
    }

    /**
     * 2. Success IPN ba vendor cập nhật toàn bộ session (status: confirmed), đúng một job mỗi order.
     */
    public function test_success_ipn_multi_vendor_updates_all_orders_and_dispatches_one_job_per_order(): void
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
            ['shipping_address' => '456 Multi St', 'phone' => '0909999999', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '60000000',
            'vnp_TransactionNo' => 'TXN-MULTI-888',
        ]);

        $response = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        $response->assertStatus(200)->assertJson(['RspCode' => '00']);

        foreach ($orders as $ord) {
            $freshOrder = $ord->fresh();
            $this->assertEquals('confirmed', $freshOrder->status);
            $this->assertEquals('paid', $freshOrder->payment_status);
        }

        Queue::assertPushed(ProcessOrder::class, 3);
    }

    /**
     * 3. Success callback lặp 2-5 lần trả '00', không thêm dispatch/update.
     */
    public function test_repeated_success_callbacks_are_idempotent(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Repeat St', 'phone' => '0900000000', 'payment_method' => 'vnpay'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_TransactionNo' => 'TXN-REPEAT-111',
        ]);

        // Lần 1
        $res1 = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));
        $res1->assertStatus(200)->assertJson(['RspCode' => '00']);
        Queue::assertPushed(ProcessOrder::class, 1);

        // Lặp 3 lần sau
        for ($i = 0; $i < 3; $i++) {
            Queue::fake();
            $resLoop = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));
            $resLoop->assertStatus(200)->assertJson(['RspCode' => '00']);
            Queue::assertNothingPushed();
        }
    }

    /**
     * 4. Failure IPN chuyển transaction failed, order giữ pending/unpaid, không job.
     */
    public function test_failure_ipn_marks_transaction_failed_and_keeps_order_pending(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Fail St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_ResponseCode' => '24',
            'vnp_TransactionStatus' => '02',
            'vnp_TransactionNo' => 'TXN-FAIL-000',
        ]);

        $response = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        $response->assertStatus(200)->assertJson(['RspCode' => '00']);

        $txn = PaymentTransaction::first();
        $this->assertEquals(PaymentTransactionStatus::FAILED, $txn->status);

        $order = $orders[0]->fresh();
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('unpaid', $order->payment_status);

        Queue::assertNothingPushed();
    }

    /**
     * 5. Duplicate failure: null/null hợp lệ; null/non-null và ID khác nhau trả 02.
     */
    public function test_duplicate_failure_identity_rules(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Fail Rules St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        // Case A: null stored / null incoming -> returns 00 (idempotent)
        $cbNullNo = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_ResponseCode' => '24',
            'vnp_TransactionNo' => '',
        ]);
        $this->getJson('/api/vnpay/ipn?'.http_build_query($cbNullNo))->assertJson(['RspCode' => '00']);

        // Case B: null stored / non-null incoming -> returns 02 (conflict)
        $cbWithNo = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_ResponseCode' => '24',
            'vnp_TransactionNo' => 'TXN-FAIL-NEW',
        ]);
        $this->getJson('/api/vnpay/ipn?'.http_build_query($cbWithNo))->assertJson(['RspCode' => '02']);
    }

    /**
     * Duplicate failure có matching stored ID vs incoming ID trả 00, mismatched ID trả 02.
     */
    public function test_duplicate_failure_matching_and_mismatched_ids(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Fail Match St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        // Fail with stored ID 'TXN-FAIL-1'
        $cb1 = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_ResponseCode' => '24',
            'vnp_TransactionNo' => 'TXN-FAIL-1',
        ]);
        $this->getJson('/api/vnpay/ipn?'.http_build_query($cb1))->assertJson(['RspCode' => '00']);

        // Same ID 'TXN-FAIL-1' -> 00
        $this->getJson('/api/vnpay/ipn?'.http_build_query($cb1))->assertJson(['RspCode' => '00']);

        // Different ID 'TXN-FAIL-2' -> 02
        $cb2 = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_ResponseCode' => '24',
            'vnp_TransactionNo' => 'TXN-FAIL-2',
        ]);
        $this->getJson('/api/vnpay/ipn?'.http_build_query($cb2))->assertJson(['RspCode' => '02']);
    }

    /**
     * Success IPN thiếu provider transaction ID trả 97.
     */
    public function test_success_ipn_missing_provider_transaction_id_returns_97(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Missing TxnId St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_TransactionNo' => '',
        ]);

        $response = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));
        $response->assertStatus(200)->assertJson(['RspCode' => '97']);
    }

    /**
     * 6. Retry create-payment sau failure tạo attempt mới.
     */
    public function test_retry_create_payment_after_failure_creates_new_attempt(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Retry St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt1 = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt1['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_ResponseCode' => '24',
        ]);
        $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        // Retry create payment attempt
        $attempt2 = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $this->assertEquals(2, PaymentTransaction::count());
        $this->assertNotEquals($attempt1['provider_reference'], $attempt2['provider_reference']);
        $this->assertEquals(PaymentTransactionStatus::PENDING, PaymentTransaction::latest('id')->first()->status);
    }

    /**
     * 7. Invalid/tampered signature, merchant, currency/format trả 97.
     */
    public function test_invalid_signature_merchant_currency_returns_97(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Tamper St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        // Tampered hash
        $badHashCallback = $this->createSignedCallback(['vnp_TxnRef' => $attempt['provider_reference'], 'vnp_Amount' => '10000000']);
        $badHashCallback['vnp_SecureHash'] = 'BADHASH123456';
        $resHash = $this->getJson('/api/vnpay/ipn?'.http_build_query($badHashCallback));
        $resHash->assertStatus(200)->assertJson(['RspCode' => '97']);

        // Wrong merchant
        $wrongMerchantCallback = $this->createSignedCallback(['vnp_TxnRef' => $attempt['provider_reference'], 'vnp_Amount' => '10000000', 'vnp_TmnCode' => 'WRONGTMN']);
        $resMerchant = $this->getJson('/api/vnpay/ipn?'.http_build_query($wrongMerchantCallback));
        $resMerchant->assertStatus(200)->assertJson(['RspCode' => '97']);

        // Wrong currency
        $wrongCurrencyCallback = $this->createSignedCallback(['vnp_TxnRef' => $attempt['provider_reference'], 'vnp_Amount' => '10000000', 'vnp_CurrCode' => 'USD']);
        $resCurr = $this->getJson('/api/vnpay/ipn?'.http_build_query($wrongCurrencyCallback));
        $resCurr->assertStatus(200)->assertJson(['RspCode' => '97']);
    }

    /**
     * 8. Unknown reference trả 01.
     */
    public function test_unknown_reference_returns_01(): void
    {
        $callback = $this->createSignedCallback(['vnp_TxnRef' => 'UNKNOWN_REF_999']);

        $response = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        $response->assertStatus(200)->assertJson(['RspCode' => '01']);
    }

    /**
     * 9. Local amount mismatch trả 04.
     */
    public function test_local_amount_mismatch_returns_04(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Amount St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '99000000',
        ]);

        $response = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        $response->assertStatus(200)->assertJson(['RspCode' => '04']);
    }

    /**
     * 10. Provider transaction ID thuộc transaction khác fail-closed (trả 02).
     */
    public function test_conflicting_provider_transaction_id_returns_02(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book1 = $this->createBook($vendor, 100000);
        $book2 = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders1 = $checkoutService->processCheckout(
            [['book_id' => $book1->id, 'quantity' => 1]],
            ['shipping_address' => 'Txn 1 St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );
        $orders2 = $checkoutService->processCheckout(
            [['book_id' => $book2->id, 'quantity' => 1]],
            ['shipping_address' => 'Txn 2 St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt1 = $paymentService->createPaymentAttempt($orders1[0]->id, $user, '127.0.0.1');
        $attempt2 = $paymentService->createPaymentAttempt($orders2[0]->id, $user, '127.0.0.1');

        $cb1 = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt1['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_TransactionNo' => 'DUP_TXN_ID',
        ]);
        $this->getJson('/api/vnpay/ipn?'.http_build_query($cb1))->assertJson(['RspCode' => '00']);

        $cb2 = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt2['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_TransactionNo' => 'DUP_TXN_ID',
        ]);
        $res2 = $this->getJson('/api/vnpay/ipn?'.http_build_query($cb2));

        $res2->assertStatus(200)->assertJson(['RspCode' => '02']);
    }

    /**
     * 11. Late success cho failed/expired trả 02, không hồi sinh.
     */
    public function test_late_success_for_failed_or_expired_returns_02_and_does_not_resurrect(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Late St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $txn = PaymentTransaction::first();
        $txn->status = PaymentTransactionStatus::FAILED;
        $txn->save();

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
        ]);

        $response = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        $response->assertStatus(200)->assertJson(['RspCode' => '02']);
        $this->assertEquals(PaymentTransactionStatus::FAILED, $txn->fresh()->status);
    }

    /**
     * 12. Order state conflict trả 99 và rollback.
     */
    public function test_order_state_conflict_returns_99_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Conflict St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $orders[0]->status = 'completed';
        $orders[0]->save();

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
        ]);

        $response = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        $response->assertStatus(200)->assertJson(['RspCode' => '99']);
        $this->assertEquals(PaymentTransactionStatus::PENDING, PaymentTransaction::first()->status);
    }

    /**
     * 13. Mô phỏng DB failure giữa cập nhật nhiều order: transaction/order rollback, không dispatch.
     */
    public function test_db_failure_during_multi_order_update_rolls_back_completely(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor1 = $this->createVendor();
        $vendor2 = $this->createVendor();

        $book1 = $this->createBook($vendor1, 100000);
        $book2 = $this->createBook($vendor2, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [
                ['book_id' => $book1->id, 'quantity' => 1],
                ['book_id' => $book2->id, 'quantity' => 1],
            ],
            ['shipping_address' => 'DB Fail St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        DB::statement('
            CREATE TRIGGER fail_second_order_update
            BEFORE UPDATE ON orders
            WHEN (SELECT COUNT(*) FROM orders WHERE payment_status = "paid") >= 1
            BEGIN
                SELECT RAISE(ABORT, "Simulated failure on second order update");
            END;
        ');

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '20000000',
        ]);

        $response = $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        $response->assertStatus(200)->assertJson(['RspCode' => '99']);

        $this->assertEquals(PaymentTransactionStatus::PENDING, PaymentTransaction::first()->status);
        $this->assertEquals('unpaid', $orders[0]->fresh()->payment_status);
        $this->assertEquals('unpaid', $orders[1]->fresh()->payment_status);

        Queue::assertNothingPushed();
    }

    /**
     * 14. Stored response payload không có signature/secret.
     */
    public function test_stored_response_payload_does_not_contain_signature_or_secret(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Sanit St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
        ]);

        $this->getJson('/api/vnpay/ipn?'.http_build_query($callback));

        $txn = PaymentTransaction::first();
        $payload = $txn->response_payload;

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('vnp_SecureHash', $payload);
        $this->assertSame('SHA512', $payload['vnp_SecureHashType']);
        $this->assertArrayNotHasKey('hash_secret', $payload);
    }

    /**
     * 15. Return URL identity mismatch trả invalid_transaction, pending trả pending và read-only.
     */
    public function test_return_url_redirects_correctly_and_checks_identity(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Return St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $paymentService = new VnpayPaymentService;
        $attempt = $paymentService->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        // Pending Return -> pending
        $cbPending = $this->createSignedCallback(['vnp_TxnRef' => $attempt['provider_reference'], 'vnp_Amount' => '10000000']);
        $resPending = $this->get('/api/vnpay/return?'.http_build_query($cbPending));
        $resPending->assertRedirect('http://localhost:5173/orders?payment=pending');

        // Confirm IPN with ID 'TXN-SUCCESS-1'
        $cbSuccess = $this->createSignedCallback(['vnp_TxnRef' => $attempt['provider_reference'], 'vnp_Amount' => '10000000', 'vnp_TransactionNo' => 'TXN-SUCCESS-1']);
        $this->getJson('/api/vnpay/ipn?'.http_build_query($cbSuccess))->assertJson(['RspCode' => '00']);

        // Return with matching ID 'TXN-SUCCESS-1' -> success
        $resPaidSuccess = $this->get('/api/vnpay/return?'.http_build_query($cbSuccess));
        $resPaidSuccess->assertRedirect('http://localhost:5173/orders?payment=success');

        // Return with mismatched ID 'TXN-SUCCESS-2' -> invalid_transaction
        $cbMismatch = $this->createSignedCallback(['vnp_TxnRef' => $attempt['provider_reference'], 'vnp_Amount' => '10000000', 'vnp_TransactionNo' => 'TXN-SUCCESS-2']);
        $resPaidMismatch = $this->get('/api/vnpay/return?'.http_build_query($cbMismatch));
        $resPaidMismatch->assertRedirect('http://localhost:5173/orders?payment=invalid_transaction');

        Queue::assertPushed(ProcessOrder::class, 1);
    }

    public function test_local_return_can_confirm_a_signed_callback_when_explicitly_enabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);
        $orders = (new CheckoutService)->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Local Return St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );
        $attempt = (new VnpayPaymentService)->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');
        $callback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
            'vnp_TransactionNo' => 'LOCAL-RETURN-1',
        ]);

        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'local');
        config(['services.vnpay.confirm_on_return' => true]);

        try {
            $this->get('/api/vnpay/return?'.http_build_query($callback))
                ->assertRedirect('http://localhost:5173/orders?payment=success');
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }

        $transaction = PaymentTransaction::where('provider_reference', $attempt['provider_reference'])->firstOrFail();
        $this->assertSame(PaymentTransactionStatus::PAID, $transaction->status);
        Queue::assertPushed(ProcessOrder::class, 1);
    }

    public function test_local_return_reconciles_with_signed_query_when_browser_callback_checksum_is_rejected(): void
    {
        Queue::fake();
        config(['services.vnpay.refund_url' => 'https://sandbox.example/query']);

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);
        $orders = (new CheckoutService)->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Query Return St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );
        $attempt = (new VnpayPaymentService)->createPaymentAttempt($orders[0]->id, $user, '127.0.0.1');

        Http::fake(function ($request) use ($attempt) {
            $query = $request->data();
            $body = [
                'vnp_ResponseId' => 'QUERY-RESPONSE-1',
                'vnp_Command' => 'querydr',
                'vnp_ResponseCode' => '00',
                'vnp_Message' => 'QueryDR success',
                'vnp_TmnCode' => 'KOMITEST',
                'vnp_TxnRef' => $attempt['provider_reference'],
                'vnp_Amount' => '10000000',
                'vnp_BankCode' => 'NCB',
                'vnp_PayDate' => '20260803015108',
                'vnp_TransactionNo' => 'QUERY-TXN-1',
                'vnp_TransactionType' => '01',
                'vnp_TransactionStatus' => '00',
                'vnp_OrderInfo' => (string) ($query['vnp_OrderInfo'] ?? ''),
                'vnp_PromotionCode' => '',
                'vnp_PromotionAmount' => '',
            ];
            $body['vnp_SecureHash'] = hash_hmac('sha512', implode('|', array_values($body)), 'SECRETKEY1234567890ABCDEF123456');

            return Http::response($body);
        });

        $invalidCallback = $this->createSignedCallback([
            'vnp_TxnRef' => $attempt['provider_reference'],
            'vnp_Amount' => '10000000',
        ]);
        $invalidCallback['vnp_SecureHash'] = 'invalid-browser-callback';

        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'local');
        config(['services.vnpay.confirm_on_return' => true]);

        try {
            $this->get('/api/vnpay/return?'.http_build_query($invalidCallback))
                ->assertRedirect('http://localhost:5173/orders?payment=success');
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }

        $transaction = PaymentTransaction::where('provider_reference', $attempt['provider_reference'])->firstOrFail();
        $this->assertSame(PaymentTransactionStatus::PAID, $transaction->status);
        $this->assertSame('QUERY-TXN-1', $transaction->provider_transaction_id);
        Queue::assertPushed(ProcessOrder::class, 1);
    }
}

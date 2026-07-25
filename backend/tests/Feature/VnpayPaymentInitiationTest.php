<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentTransactionStatus;
use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSessionOrder;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VnpayPaymentInitiationTest extends TestCase
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

    /**
     * 1. Guest bị từ chối khi gọi API create payment.
     */
    public function test_guest_cannot_initiate_payment(): void
    {
        $response = $this->postJson('/api/vnpay/create', ['order_id' => 1]);

        $response->assertStatus(401);
    }

    /**
     * Order ID không tồn tại trả 422.
     */
    public function test_non_existent_order_id_returns_422(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/vnpay/create', ['order_id' => 999999]);

        $response->assertStatus(422);
    }

    /**
     * 2. Owner tạo payment cho checkout một vendor.
     */
    public function test_owner_can_initiate_payment_for_single_vendor_checkout(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 150000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => '123 Main St', 'phone' => '0901234567', 'payment_method' => 'vnpay'],
            $user->id
        );

        $order = $orders[0];

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/vnpay/create', ['order_id' => $order->id]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'url',
                'provider_reference',
                'checkout_code',
            ]);

        $this->assertEquals('success', $response->json('status'));
        $this->assertStringContainsString('vnp_SecureHash=', $response->json('url'));

        $this->assertEquals(1, PaymentTransaction::count());
        $txn = PaymentTransaction::first();
        $this->assertEquals(PaymentTransactionStatus::PENDING, $txn->status);
        $this->assertEquals(300000, $txn->amount);
    }

    /**
     * 3. Checkout nhiều vendor: khởi tạo từ bất kỳ order con nào đều ký đúng tổng session.
     */
    public function test_multi_vendor_checkout_initiates_payment_for_total_session_amount(): void
    {
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

        $order2 = $orders[1];

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/vnpay/create', ['order_id' => $order2->id]);

        $response->assertStatus(200);

        $txn = PaymentTransaction::first();
        $this->assertEquals(600000, $txn->amount);
        $this->assertStringContainsString('vnp_Amount=60000000', $response->json('url'));
    }

    /**
     * 4. Order của user khác trả 403 và không tạo transaction.
     */
    public function test_initiating_payment_for_another_users_order_returns_403(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'User A St', 'phone' => '0900000000', 'payment_method' => 'vnpay'],
            $userA->id
        );

        Sanctum::actingAs($userB);
        $response = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);

        $response->assertStatus(403);
        $this->assertEquals(0, PaymentTransaction::count());
    }

    /**
     * Session user_id không khớp với user bị từ chối 403.
     */
    public function test_session_user_mismatch_returns_403(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Mismatch St', 'phone' => '0900000000', 'payment_method' => 'vnpay'],
            $userA->id
        );

        $sessionOrder = CheckoutSessionOrder::where('order_id', $orders[0]->id)->first();
        $session = $sessionOrder->checkoutSession;
        $session->user_id = $userB->id;
        $session->save();

        Sanctum::actingAs($userA);
        $response = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);

        $response->assertStatus(403);
    }

    /**
     * 5. Legacy order không có session link trả 422.
     */
    public function test_legacy_order_without_session_link_returns_422(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();

        $legacyOrder = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'online',
            'shipping_address' => 'Old St',
            'phone' => '0900000000',
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/vnpay/create', ['order_id' => $legacyOrder->id]);

        $response->assertStatus(422);
        $this->assertEquals(0, PaymentTransaction::count());
    }

    /**
     * 6. COD, session expired, order non-pending/paid/cancelled hoặc currency sai đều fail-closed.
     */
    public function test_fail_closed_on_cod_expired_session_paid_order_or_wrong_currency(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;

        // Test COD order
        $codOrders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'COD St', 'phone' => '0900000000', 'payment_method' => 'cod'],
            $user->id
        );

        Sanctum::actingAs($user);
        $resCod = $this->postJson('/api/vnpay/create', ['order_id' => $codOrders[0]->id]);
        $resCod->assertStatus(422);

        // Test Expired Session
        $onlineOrders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Online St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );
        $sessionOrder = CheckoutSessionOrder::where('order_id', $onlineOrders[0]->id)->first();
        $session = $sessionOrder->checkoutSession;
        $session->expires_at = now()->subMinute();
        $session->save();

        $resExpired = $this->postJson('/api/vnpay/create', ['order_id' => $onlineOrders[0]->id]);
        $resExpired->assertStatus(422);

        // Test Non-pending order statuses
        $statuses = ['processing', 'shipped', 'completed', 'cancelled'];
        foreach ($statuses as $st) {
            $testOrders = $checkoutService->processCheckout(
                [['book_id' => $book->id, 'quantity' => 1]],
                ['shipping_address' => 'Status St', 'phone' => '0900000000', 'payment_method' => 'online'],
                $user->id
            );
            $testOrders[0]->status = $st;
            $testOrders[0]->save();

            $resStatus = $this->postJson('/api/vnpay/create', ['order_id' => $testOrders[0]->id]);
            $resStatus->assertStatus(422);
        }

        // Test Paid Order
        $onlineOrders2 = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Online St 2', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );
        $onlineOrders2[0]->payment_status = 'paid';
        $onlineOrders2[0]->save();

        $resPaid = $this->postJson('/api/vnpay/create', ['order_id' => $onlineOrders2[0]->id]);
        $resPaid->assertStatus(422);

        // Test Wrong Currency
        $onlineOrders3 = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Online St 3', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );
        $session3 = CheckoutSessionOrder::where('order_id', $onlineOrders3[0]->id)->first()->checkoutSession;
        $session3->currency = 'USD';
        $session3->save();

        $resCurrency = $this->postJson('/api/vnpay/create', ['order_id' => $onlineOrders3[0]->id]);
        $resCurrency->assertStatus(422);
    }

    /**
     * 7. Hai request liên tiếp trong TTL chỉ có một pending row, cùng reference/URL.
     */
    public function test_consecutive_requests_within_ttl_reuse_same_pending_attempt(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Reuse St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        Sanctum::actingAs($user);
        $res1 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res1->assertStatus(200);

        $res2 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res2->assertStatus(200);

        $this->assertEquals(1, PaymentTransaction::count());
        $this->assertEquals($res1->json('provider_reference'), $res2->json('provider_reference'));
        $this->assertEquals($res1->json('url'), $res2->json('url'));
    }

    /**
     * 8. Pending hết hạn hoặc có expires_at = null được chuyển `expired` và tạo attempt mới.
     */
    public function test_expired_pending_attempt_is_marked_expired_and_new_attempt_is_created(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Expiry St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        Sanctum::actingAs($user);
        $res1 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res1->assertStatus(200);

        $firstTxn = PaymentTransaction::first();
        $firstTxn->expires_at = now()->subMinutes(5);
        $firstTxn->save();

        $res2 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res2->assertStatus(200);

        $this->assertEquals(2, PaymentTransaction::count());
        $this->assertEquals(PaymentTransactionStatus::EXPIRED, $firstTxn->fresh()->status);

        $newTxn = PaymentTransaction::latest('id')->first();
        $this->assertEquals(PaymentTransactionStatus::PENDING, $newTxn->status);
        $this->assertNotEquals($firstTxn->provider_reference, $newTxn->provider_reference);
    }

    /**
     * Pending attempt có expires_at = null không được tái sử dụng.
     */
    public function test_pending_attempt_without_expiry_is_not_reused(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Null Expiry St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        Sanctum::actingAs($user);
        $res1 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res1->assertStatus(200);

        $firstTxn = PaymentTransaction::first();
        $firstTxn->expires_at = null;
        $firstTxn->save();

        $res2 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res2->assertStatus(200);

        $this->assertEquals(2, PaymentTransaction::count());
        $this->assertEquals(PaymentTransactionStatus::EXPIRED, $firstTxn->fresh()->status);
    }

    /**
     * Pending attempt của provider khác (ví dụ: stripe) bị bỏ qua và tạo attempt VNPAY mới.
     */
    public function test_pending_attempt_of_another_provider_is_ignored(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Stripe St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        $sessionOrder = CheckoutSessionOrder::where('order_id', $orders[0]->id)->first();

        PaymentTransaction::create([
            'checkout_session_id' => $sessionOrder->checkout_session_id,
            'provider' => 'stripe',
            'provider_reference' => 'STRIPE_REF_123',
            'idempotency_key' => 'IDEM_STRIPE_123',
            'amount' => 100000,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PENDING,
            'expires_at' => now()->addMinutes(15),
            'request_payload' => ['some' => 'data'],
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);

        $response->assertStatus(200);
        $this->assertEquals(2, PaymentTransaction::count());
        $this->assertEquals(1, PaymentTransaction::where('provider', 'vnpay')->count());
    }

    /**
     * Retry pending khi gateway config bị thiếu trả 503, không tạo row mới và không sửa attempt cũ.
     */
    public function test_retry_pending_when_gateway_config_missing_returns_503_and_leaves_attempt_unchanged(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Config Missing St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        Sanctum::actingAs($user);
        $res1 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res1->assertStatus(200);

        // Hỏng config
        config(['services.vnpay.tmn_code' => '']);

        $res2 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res2->assertStatus(503);

        $this->assertEquals(1, PaymentTransaction::count());
        $this->assertEquals(PaymentTransactionStatus::PENDING, PaymentTransaction::first()->status);
    }

    /**
     * Retry pending khi merchant code thay đổi trả 503, không tạo row mới và giữ nguyên attempt cũ.
     */
    public function test_retry_pending_when_merchant_code_changes_returns_503_and_leaves_attempt_unchanged(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Merchant Change St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        Sanctum::actingAs($user);
        $res1 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res1->assertStatus(200);

        // Thay đổi merchant code trong config
        config(['services.vnpay.tmn_code' => 'DIFFERENT_TMN']);

        $res2 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res2->assertStatus(503);

        $this->assertEquals(1, PaymentTransaction::count());
        $this->assertEquals(PaymentTransactionStatus::PENDING, PaymentTransaction::first()->status);
    }

    /**
     * 9. Attempt `failed` tạo attempt mới, không được hồi sinh.
     */
    public function test_failed_attempt_creates_new_attempt_and_is_not_resurrected(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Failed St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        Sanctum::actingAs($user);
        $res1 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);

        $firstTxn = PaymentTransaction::first();
        $firstTxn->status = PaymentTransactionStatus::FAILED;
        $firstTxn->failed_at = now();
        $firstTxn->save();

        $res2 = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);
        $res2->assertStatus(200);

        $this->assertEquals(2, PaymentTransaction::count());
        $this->assertEquals(PaymentTransactionStatus::FAILED, $firstTxn->fresh()->status);

        $newTxn = PaymentTransaction::latest('id')->first();
        $this->assertEquals(PaymentTransactionStatus::PENDING, $newTxn->status);
    }

    /**
     * 10. Request payload lưu trong DB không chứa secure hash hoặc secret.
     */
    public function test_stored_request_payload_does_not_contain_secure_hash_or_secret(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Payload St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        Sanctum::actingAs($user);
        $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);

        $txn = PaymentTransaction::first();
        $payload = $txn->request_payload;

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('vnp_SecureHash', $payload);
        $this->assertArrayNotHasKey('vnp_SecureHashType', $payload);
        $this->assertArrayNotHasKey('hash_secret', $payload);
    }

    /**
     * 11. Gateway config lỗi trả 503 chung và rollback, không để row mồ côi.
     */
    public function test_gateway_config_error_returns_503_generic_and_rolls_back_transaction(): void
    {
        config(['services.vnpay.tmn_code' => '']);

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;
        $orders = $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Config Error St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/vnpay/create', ['order_id' => $orders[0]->id]);

        $response->assertStatus(503);
        $this->assertEquals('Payment gateway service is currently unavailable.', $response->json('message'));
        $this->assertEquals(0, PaymentTransaction::count());
    }

    /**
     * 12. Checkout VNPAY không dispatch ProcessOrder, còn COD vẫn giữ hành vi hiện tại.
     */
    public function test_checkout_online_does_not_dispatch_process_order_while_cod_does(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 100000);

        $checkoutService = new CheckoutService;

        // Online checkout -> Queue assert Nothing Pushed
        $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Online St', 'phone' => '0900000000', 'payment_method' => 'online'],
            $user->id
        );
        Queue::assertNothingPushed();

        // COD checkout -> Queue assert Pushed ProcessOrder
        $checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'COD St', 'phone' => '0900000000', 'payment_method' => 'cod'],
            $user->id
        );
        Queue::assertPushed(ProcessOrder::class, 1);
    }
}

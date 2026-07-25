<?php

namespace Tests\Feature;

use App\Enums\PaymentTransactionStatus;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Phase2CheckoutPaymentSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tạo vendor trợ giúp cho test.
     */
    private function createVendor(): Vendor
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $uniqueId = uniqid();

        return Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Test Vendor Store '.$uniqueId,
            'slug' => 'test-vendor-store-'.$uniqueId,
            'status' => 'active',
        ]);
    }

    /**
     * 1. Test xác nhận tạo checkout session, liên kết order, snapshot tiền, payment transaction, relationships và casts.
     */
    public function test_creates_checkout_session_and_payment_transaction_with_correct_casts_and_relationships(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();

        $order = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 150000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'online',
            'shipping_address' => '123 Street',
            'phone' => '0901234567',
        ]);

        $checkoutSession = CheckoutSession::create([
            'user_id' => $user->id,
            'currency' => 'VND',
            'subtotal_amount' => 150000,
            'discount_amount' => 10000,
            'fee_amount' => 5000,
            'total_amount' => 145000,
            'expires_at' => now()->addMinutes(30),
        ]);

        $checkoutSessionOrder = CheckoutSessionOrder::create([
            'checkout_session_id' => $checkoutSession->id,
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'subtotal_amount' => 150000,
            'discount_amount' => 10000,
            'fee_amount' => 5000,
            'commission_rate' => 10.00,
            'commission_amount' => 14000,
            'total_amount' => 145000,
        ]);

        $paymentTransaction = PaymentTransaction::create([
            'checkout_session_id' => $checkoutSession->id,
            'provider' => 'vnpay',
            'provider_reference' => 'VNP123456',
            'provider_transaction_id' => 'TXN7890',
            'idempotency_key' => 'IDEM-001',
            'amount' => 145000,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PENDING,
            'request_payload' => ['foo' => 'bar'],
            'response_payload' => ['code' => '00'],
            'paid_at' => now(),
        ]);

        // Relationships assertions
        $this->assertTrue($user->checkoutSessions->contains($checkoutSession));
        $this->assertEquals($user->id, $checkoutSession->user->id);
        $this->assertTrue($checkoutSession->checkoutSessionOrders->contains($checkoutSessionOrder));
        $this->assertTrue($checkoutSession->paymentTransactions->contains($paymentTransaction));
        $this->assertEquals($checkoutSession->id, $checkoutSessionOrder->checkoutSession->id);
        $this->assertEquals($order->id, $checkoutSessionOrder->order->id);
        $this->assertEquals($vendor->id, $checkoutSessionOrder->vendor->id);
        $this->assertEquals($checkoutSessionOrder->id, $order->checkoutSessionOrder->id);
        $this->assertEquals($checkoutSession->id, $paymentTransaction->checkoutSession->id);

        // Casts assertions
        $this->assertIsInt($checkoutSession->subtotal_amount);
        $this->assertIsInt($checkoutSession->discount_amount);
        $this->assertIsInt($checkoutSession->fee_amount);
        $this->assertIsInt($checkoutSession->total_amount);
        $this->assertInstanceOf(Carbon::class, $checkoutSession->expires_at);

        $this->assertIsInt($checkoutSessionOrder->subtotal_amount);
        $this->assertIsInt($checkoutSessionOrder->discount_amount);
        $this->assertIsInt($checkoutSessionOrder->fee_amount);
        $this->assertEquals('10.00', $checkoutSessionOrder->commission_rate);
        $this->assertIsInt($checkoutSessionOrder->commission_amount);
        $this->assertIsInt($checkoutSessionOrder->total_amount);

        $this->assertIsInt($paymentTransaction->amount);
        $this->assertInstanceOf(PaymentTransactionStatus::class, $paymentTransaction->status);
        $this->assertIsArray($paymentTransaction->request_payload);
        $this->assertIsArray($paymentTransaction->response_payload);
        $this->assertInstanceOf(Carbon::class, $paymentTransaction->paid_at);
    }

    /**
     * 2. Test data provider cho các unique constraints:
     * - Một order không thuộc 2 checkout sessions.
     * - provider_reference không trùng trong cùng provider.
     * - idempotency_key không trùng trong cùng provider.
     * - provider_transaction_id không trùng trong cùng provider.
     */
    #[DataProvider('uniqueConstraintsProvider')]
    public function test_unique_constraints(string $constraintType): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();

        $session1 = CheckoutSession::create([
            'user_id' => $user->id,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ]);

        $session2 = CheckoutSession::create([
            'user_id' => $user->id,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ]);

        $order1 = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'online',
            'shipping_address' => '123 St',
            'phone' => '0900000000',
        ]);

        if ($constraintType === 'order_unique_to_checkout') {
            CheckoutSessionOrder::create([
                'checkout_session_id' => $session1->id,
                'order_id' => $order1->id,
                'vendor_id' => $vendor->id,
                'subtotal_amount' => 100000,
                'total_amount' => 100000,
            ]);

            $this->expectException(QueryException::class);

            // Attempt to assign same order_id to another checkout session
            CheckoutSessionOrder::create([
                'checkout_session_id' => $session2->id,
                'order_id' => $order1->id,
                'vendor_id' => $vendor->id,
                'subtotal_amount' => 100000,
                'total_amount' => 100000,
            ]);
        } elseif ($constraintType === 'provider_reference') {
            PaymentTransaction::create([
                'checkout_session_id' => $session1->id,
                'provider' => 'vnpay',
                'provider_reference' => 'REF-DUP-1',
                'idempotency_key' => 'IDEM-1',
                'amount' => 100000,
            ]);

            $this->expectException(QueryException::class);

            PaymentTransaction::create([
                'checkout_session_id' => $session2->id,
                'provider' => 'vnpay',
                'provider_reference' => 'REF-DUP-1',
                'idempotency_key' => 'IDEM-2',
                'amount' => 100000,
            ]);
        } elseif ($constraintType === 'idempotency_key') {
            PaymentTransaction::create([
                'checkout_session_id' => $session1->id,
                'provider' => 'vnpay',
                'provider_reference' => 'REF-1',
                'idempotency_key' => 'IDEM-DUP-1',
                'amount' => 100000,
            ]);

            $this->expectException(QueryException::class);

            PaymentTransaction::create([
                'checkout_session_id' => $session2->id,
                'provider' => 'vnpay',
                'provider_reference' => 'REF-2',
                'idempotency_key' => 'IDEM-DUP-1',
                'amount' => 100000,
            ]);
        } elseif ($constraintType === 'provider_transaction_id') {
            PaymentTransaction::create([
                'checkout_session_id' => $session1->id,
                'provider' => 'vnpay',
                'provider_reference' => 'REF-1',
                'provider_transaction_id' => 'TXN-DUP-1',
                'idempotency_key' => 'IDEM-1',
                'amount' => 100000,
            ]);

            $this->expectException(QueryException::class);

            PaymentTransaction::create([
                'checkout_session_id' => $session2->id,
                'provider' => 'vnpay',
                'provider_reference' => 'REF-2',
                'provider_transaction_id' => 'TXN-DUP-1',
                'idempotency_key' => 'IDEM-2',
                'amount' => 100000,
            ]);
        }
    }

    public static function uniqueConstraintsProvider(): array
    {
        return [
            'order unique to checkout' => ['order_unique_to_checkout'],
            'provider reference unique in provider' => ['provider_reference'],
            'idempotency key unique in provider' => ['idempotency_key'],
            'provider transaction id unique in provider' => ['provider_transaction_id'],
        ];
    }

    /**
     * 3. Test foreign-key restrict bảo vệ lịch sử tài chính.
     */
    public function test_foreign_key_restrict_prevents_deleting_referenced_records(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();

        $order = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'online',
            'shipping_address' => '123 St',
            'phone' => '0900000000',
        ]);

        $session = CheckoutSession::create([
            'user_id' => $user->id,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ]);

        CheckoutSessionOrder::create([
            'checkout_session_id' => $session->id,
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ]);

        PaymentTransaction::create([
            'checkout_session_id' => $session->id,
            'provider' => 'vnpay',
            'provider_reference' => 'REF-RESTRICT-1',
            'idempotency_key' => 'IDEM-RESTRICT-1',
            'amount' => 100000,
        ]);

        // Attempting to delete user referenced by checkout_session must fail due to restrictOnDelete
        try {
            $user->delete();
            $this->fail('Expected QueryException when deleting user referenced by checkout_session');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }

        // Attempting to delete order referenced by checkout_session_order must fail
        try {
            $order->delete();
            $this->fail('Expected QueryException when deleting order referenced by checkout_session_order');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }

        // Attempting to delete checkout_session referenced by checkout_session_order / payment_transaction must fail
        try {
            $session->delete();
            $this->fail('Expected QueryException when deleting checkout_session referenced by payment_transaction');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * 4. Test UUID được sinh và payment status cast đúng enum.
     */
    public function test_checkout_code_auto_generation_and_payment_status_enum_cast(): void
    {
        $user = User::factory()->create();

        // 1. Automatic UUID generation when checkout_code is null/empty
        $sessionAutoUuid = CheckoutSession::create([
            'user_id' => $user->id,
            'subtotal_amount' => 50000,
            'total_amount' => 50000,
        ]);

        $this->assertNotEmpty($sessionAutoUuid->checkout_code);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $sessionAutoUuid->checkout_code
        );

        // 2. Allow passing explicit UUID for backfill scenarios
        $customUuid = '123e4567-e89b-12d3-a456-426614174000';
        $sessionCustomUuid = CheckoutSession::create([
            'checkout_code' => $customUuid,
            'user_id' => $user->id,
            'subtotal_amount' => 50000,
            'total_amount' => 50000,
        ]);

        $this->assertEquals($customUuid, $sessionCustomUuid->checkout_code);

        // 3. Payment status enum cast
        $transaction = PaymentTransaction::create([
            'checkout_session_id' => $sessionAutoUuid->id,
            'provider' => 'vnpay',
            'provider_reference' => 'REF-ENUM-1',
            'idempotency_key' => 'IDEM-ENUM-1',
            'amount' => 50000,
            'status' => PaymentTransactionStatus::PAID,
        ]);

        $this->assertInstanceOf(PaymentTransactionStatus::class, $transaction->status);
        $this->assertEquals(PaymentTransactionStatus::PAID, $transaction->status);
        $this->assertEquals('paid', $transaction->status->value);
    }
}

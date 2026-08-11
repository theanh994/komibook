<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryReservationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\DemoWalletAccount;
use App\Models\DemoWalletLedgerEntry;
use App\Models\InventoryReservation;
use App\Models\InventoryReservationAllocation;
use App\Models\InvoiceSnapshot;
use App\Models\LoyaltyPointLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTransitionOperation;
use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;
use App\Models\RefundTransactionAttempt;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEarningLedger;
use App\Models\VendorFinancialHold;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\DemoWalletService;
use App\Services\Refunds\RefundGatewayInterface;
use App\Services\ReturnRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Tests\TestCase;

class Phase4ReturnRefundInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(SanctumServiceProvider::class);
    }

    public function test_cod_return_is_rejected_before_refund_preparation_and_preserves_received_inventory(): void
    {
        [$buyer, $vendorUser, $vendor, $order, $item, $stock] = $this->completedPhysicalOrder();

        Sanctum::actingAs($buyer);
        $created = $this->postJson("/api/orders/{$order->id}/returns", [
            'reason' => 'Sản phẩm bị hư hỏng khi nhận hàng.',
            'idempotency_key' => 'create-return-'.$order->id,
            'items' => [
                ['order_item_id' => $item->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $returnId = $created->json('data.id');
        $this->assertDatabaseHas('return_requests', [
            'id' => $returnId,
            'status' => 'requested',
            'refund_amount' => 100000,
        ]);

        $this->actingAs($vendorUser)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);
        foreach (['under_review', 'approved', 'item_received'] as $target) {
            $this->patchJson("/api/vendor/returns/{$returnId}/transition", [
                'target' => $target,
                'reason' => 'Vendor processing',
                'idempotency_key' => "return-{$returnId}-{$target}",
            ])->assertOk()->assertJsonPath('data.status', $target);
        }

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertDatabaseHas('vendor_financial_holds', [
            'return_request_id' => $returnId,
            'status' => 'active',
            'amount' => 90000,
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "return-{$returnId}-refund-a",
            'evidence' => 'BANK-TRANSFER-001',
        ])->assertStatus(422);
        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "return-{$returnId}-refund-b",
            'evidence' => 'BANK-TRANSFER-001',
        ])->assertStatus(422);
        $this->patchJson("/api/vendor/returns/{$returnId}/transition", [
            'target' => 'refund_processing',
            'idempotency_key' => "return-{$returnId}-processing",
        ])->assertStatus(422);

        $this->assertSame('none', $order->fresh()->refund_status);
        $this->assertSame(90000, $vendor->fresh()->balance);
        $this->assertSame(10, $buyer->fresh()->points);
        $this->assertDatabaseCount('inventory_return_restorations', 1);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);
        $this->assertDatabaseHas('vendor_financial_holds', [
            'return_request_id' => $returnId,
            'status' => 'active',
        ]);
        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
    }

    public function test_invoice_api_uses_immutable_snapshot_after_live_records_change(): void
    {
        [$buyer, , $vendor, $order] = $this->completedPhysicalOrder();

        $buyer->update(['name' => 'Changed Buyer']);
        $vendor->update(['shop_name' => 'Changed Shop']);
        $order->orderItems->first()->book->update(['title' => 'Changed Book']);

        Sanctum::actingAs($buyer);
        $this->getJson("/api/my-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.invoice.buyer.name', 'Original Buyer')
            ->assertJsonPath('data.invoice.seller.shop_name', 'Original Shop')
            ->assertJsonPath('data.invoice.line_items.0.title', 'Original Book')
            ->assertJsonPath('data.invoice.total_amount', 100000);
    }

    public function test_return_eligibility_and_amount_use_invoice_snapshot_not_mutable_catalog_data(): void
    {
        [$buyer, , , $order, $item] = $this->completedPhysicalOrder();
        $item->update(['price' => 1]);
        $item->book->update(['type' => 'ebook', 'price' => 1]);

        Sanctum::actingAs($buyer);
        $this->postJson("/api/orders/{$order->id}/returns", [
            'reason' => 'Sản phẩm bị hư hỏng khi nhận hàng.',
            'idempotency_key' => 'snapshot-return-'.$order->id,
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.refund_amount', 100000);
        $this->assertDatabaseHas('return_request_items', [
            'order_item_id' => $item->id,
            'unit_amount' => 100000,
            'refund_amount' => 100000,
        ]);
    }

    public function test_standard_physical_book_can_use_its_returnable_snapshot_policy(): void
    {
        [$buyer, , , $order, $item] = $this->completedPhysicalOrder();
        $item->update([
            'product_taxonomy_snapshot' => ['format' => 'physical', 'provenance' => 'publisher_catalog'],
            'return_policy_snapshot' => ['is_returnable' => true, 'return_window_days' => 7],
        ]);

        Sanctum::actingAs($buyer);
        $this->postJson("/api/orders/{$order->id}/returns", [
            'reason' => 'Sách mới bị móp góc và rách bìa khi nhận.',
            'idempotency_key' => 'standard-physical-return-'.$order->id,
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.refund_amount', 100000);
    }

    public function test_invoice_snapshot_model_rejects_mutation(): void
    {
        [, , , $order] = $this->completedPhysicalOrder();

        $this->expectException(\LogicException::class);
        $order->invoiceSnapshot->update(['total_amount' => 1]);
    }

    public function test_vnpay_failure_is_idempotent_and_can_be_retried_with_a_new_operation(): void
    {
        $gateway = new class implements RefundGatewayInterface
        {
            public int $calls = 0;

            public function refund(
                RefundTransaction $refund,
                PaymentTransaction $originalPayment,
                string $actorReference,
                string $clientIp
            ): array {
                $this->calls++;
                $successful = $this->calls > 1;

                return [
                    'successful' => $successful,
                    'pending' => false,
                    'provider_reference' => $successful ? 'VNPAY-REFUND-001' : null,
                    'request' => ['amount' => $refund->amount],
                    'response' => ['vnp_ResponseCode' => $successful ? '00' : '99'],
                    'failure_reason' => $successful ? null : 'Temporary provider failure',
                ];
            }

            public function queryRefund(
                RefundTransaction $refund,
                PaymentTransaction $originalPayment,
                string $requestReference,
                string $clientIp
            ): array {
                return [
                    'successful' => true,
                    'pending' => false,
                    'provider_reference' => 'VNPAY-REFUND-001',
                    'request' => ['command' => 'querydr'],
                    'response' => ['vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00'],
                    'failure_reason' => null,
                ];
            }
        };
        $this->app->instance(RefundGatewayInterface::class, $gateway);

        [$buyer, $vendorUser, , $order, $item] = $this->completedPhysicalOrder();
        $order->update(['payment_method' => 'online']);
        $payment = PaymentTransaction::create([
            'checkout_session_id' => $order->checkoutSessionOrder->checkout_session_id,
            'provider' => 'vnpay',
            'provider_reference' => 'ORDER-'.$order->id,
            'provider_transaction_id' => 'VNPAY-TXN-'.$order->id,
            'idempotency_key' => 'paid-'.$order->id,
            'amount' => 100000,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PAID,
            'paid_at' => now(),
        ]);
        $paymentSnapshot = $payment->fresh()->getAttributes();

        Sanctum::actingAs($buyer);
        $returnId = $this->postJson("/api/orders/{$order->id}/returns", [
            'reason' => 'Sản phẩm bị hư hỏng khi nhận hàng.',
            'idempotency_key' => 'online-return-'.$order->id,
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $this->actingAs($vendorUser)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);
        foreach (['under_review', 'approved', 'item_received'] as $target) {
            $this->patchJson("/api/vendor/returns/{$returnId}/transition", [
                'target' => $target,
                'idempotency_key' => "online-{$returnId}-{$target}",
            ])->assertOk();
        }

        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "online-{$returnId}-attempt-1",
            'evidence' => 'INTERNAL-WALLET-AUDIT-001',
        ])->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertSame(0, $gateway->calls);
        $this->assertDatabaseCount('refund_transaction_attempts', 1);
        $this->assertSame($paymentSnapshot, $payment->fresh()->getAttributes());
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseHas('demo_wallet_ledger_entries', [
            'payment_transaction_id' => $payment->id,
            'order_id' => $order->id,
            'return_request_id' => $returnId,
            'entry_type' => 'refund_credit',
            'amount' => 100000,
        ]);
        $this->assertDatabaseHas('demo_wallet_ledger_entries', [
            'vendor_id' => $order->vendor_id,
            'order_id' => $order->id,
            'return_request_id' => $returnId,
            'entry_type' => 'vendor_refund_debit',
            'amount' => 90000,
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "online-{$returnId}-attempt-1",
        ])->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertSame(0, $gateway->calls);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "online-{$returnId}-attempt-2",
        ])->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertSame(0, $gateway->calls);
        $this->assertDatabaseCount('refund_transaction_attempts', 1);
        $this->assertDatabaseHas('refund_transactions', [
            'return_request_id' => $returnId,
            'status' => 'refunded',
            'provider_reference' => 'KOMIBOOK-WALLET-REFUND-1',
            'evidence' => 'INTERNAL-WALLET-AUDIT-001',
        ]);
    }

    public function test_cod_cancel_restores_committed_inventory_once_and_is_blocked_after_pickup(): void
    {
        [$buyer, , , $order, , $stock] = $this->completedPhysicalOrder();
        $order->transitionOperations()->delete();
        $order->update([
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'shipping_status' => null,
        ]);

        Sanctum::actingAs($buyer);
        $this->postJson("/api/orders/{$order->id}/cancel")->assertOk();
        $this->postJson("/api/orders/{$order->id}/cancel")->assertOk();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertDatabaseCount('inventory_cancellation_restorations', 1);

        [$secondBuyer, , , $shippedOrder, , $secondStock] = $this->completedPhysicalOrder();
        $shippedOrder->transitionOperations()->delete();
        $shippedOrder->update([
            'status' => 'processing',
            'payment_status' => 'unpaid',
            'shipping_status' => 'picked_up',
        ]);

        Sanctum::actingAs($secondBuyer);
        $this->postJson("/api/orders/{$shippedOrder->id}/cancel")->assertStatus(422);
        $this->assertSame('processing', $shippedOrder->fresh()->status);
        $this->assertSame(9, $secondStock->fresh()->quantity);
    }

    public function test_crafted_external_refund_processing_reconciliation_fails_closed_without_gateway_or_mutation(): void
    {
        $gateway = new class implements RefundGatewayInterface
        {
            public int $refundCalls = 0;

            public int $queryCalls = 0;

            public function refund(
                RefundTransaction $refund,
                PaymentTransaction $originalPayment,
                string $actorReference,
                string $clientIp
            ): array {
                $this->refundCalls++;

                return [
                    'successful' => false,
                    'pending' => true,
                    'provider_reference' => 'VNPAY-PENDING-001',
                    'request' => ['command' => 'refund'],
                    'response' => ['vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '05'],
                    'failure_reason' => null,
                ];
            }

            public function queryRefund(
                RefundTransaction $refund,
                PaymentTransaction $originalPayment,
                string $requestReference,
                string $clientIp
            ): array {
                $this->queryCalls++;

                return [
                    'successful' => true,
                    'pending' => false,
                    'provider_reference' => 'VNPAY-REFUND-FINAL-001',
                    'request' => ['command' => 'querydr'],
                    'response' => ['vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00'],
                    'failure_reason' => null,
                ];
            }
        };
        $this->app->instance(RefundGatewayInterface::class, $gateway);

        [$buyer, $vendorUser, , $order, $item] = $this->completedPhysicalOrder();
        $order->update(['payment_method' => 'online']);
        PaymentTransaction::create([
            'checkout_session_id' => $order->checkoutSessionOrder->checkout_session_id,
            'provider' => 'vnpay',
            'provider_reference' => 'PENDING-ORDER-'.$order->id,
            'provider_transaction_id' => '123456789',
            'idempotency_key' => 'pending-paid-'.$order->id,
            'amount' => 100000,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PAID,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($buyer);
        $returnId = $this->postJson("/api/orders/{$order->id}/returns", [
            'reason' => 'Sản phẩm bị hư hỏng khi nhận hàng.',
            'idempotency_key' => 'pending-return-'.$order->id,
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $this->actingAs($vendorUser)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);
        foreach (['under_review', 'approved', 'item_received'] as $target) {
            $this->patchJson("/api/vendor/returns/{$returnId}/transition", [
                'target' => $target,
                'idempotency_key' => "pending-{$returnId}-{$target}",
            ])->assertOk();
        }
        ReturnRequest::whereKey($returnId)->update(['status' => 'refund_processing']);
        RefundTransaction::create([
            'return_request_id' => $returnId,
            'payment_transaction_id' => PaymentTransaction::firstOrFail()->id,
            'provider' => 'vnpay',
            'idempotency_key' => "crafted-refund-{$returnId}",
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'processing',
            'processing_at' => now(),
        ]);
        $before = [
            'attempts' => RefundTransactionAttempt::count(),
            'wallet' => DemoWalletLedgerEntry::count(),
            'reversals' => \DB::table('vendor_earning_reversals')->count(),
            'points' => \DB::table('loyalty_point_reversals')->count(),
        ];

        $this->postJson("/api/vendor/returns/{$returnId}/refund/reconcile", [
            'idempotency_key' => "pending-{$returnId}-query",
        ])->assertStatus(422);
        $this->assertSame(0, $gateway->refundCalls);
        $this->assertSame(0, $gateway->queryCalls);
        $this->assertSame($before['attempts'], RefundTransactionAttempt::count());
        $this->assertSame($before['wallet'], DemoWalletLedgerEntry::count());
        $this->assertSame($before['reversals'], \DB::table('vendor_earning_reversals')->count());
        $this->assertSame($before['points'], \DB::table('loyalty_point_reversals')->count());
        $this->assertDatabaseHas('refund_transactions', [
            'return_request_id' => $returnId,
            'provider_reference' => null,
            'status' => 'processing',
        ]);
    }

    public function test_insufficient_vendor_funds_roll_back_wallet_credit_reversals_attempt_and_projection(): void
    {
        [$buyer, $vendorUser, $vendor, $order, $item, $stock] = $this->completedPhysicalOrder();
        $order->update(['payment_method' => 'online']);
        PaymentTransaction::create([
            'checkout_session_id' => $order->checkoutSessionOrder->checkout_session_id,
            'provider' => 'vnpay',
            'provider_reference' => 'INSUFFICIENT-'.$order->id,
            'provider_transaction_id' => 'INSUFFICIENT-TXN-'.$order->id,
            'idempotency_key' => 'insufficient-paid-'.$order->id,
            'amount' => 100000,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PAID,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($buyer);
        $returnId = $this->postJson("/api/orders/{$order->id}/returns", [
            'reason' => 'Sản phẩm hư hỏng khi nhận hàng.',
            'idempotency_key' => 'insufficient-return-'.$order->id,
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');
        $this->actingAs($vendorUser)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);
        foreach (['under_review', 'approved', 'item_received'] as $target) {
            $this->patchJson("/api/vendor/returns/{$returnId}/transition", [
                'target' => $target,
                'idempotency_key' => "insufficient-{$returnId}-{$target}",
            ])->assertOk();
        }
        $vendor->forceFill(['balance' => 1])->save();

        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "insufficient-{$returnId}-refund",
        ])->assertStatus(422);

        $this->assertSame(1, $vendor->fresh()->balance);
        $this->assertSame(0, DemoWalletLedgerEntry::count());
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
        $this->assertDatabaseHas('vendor_financial_holds', ['return_request_id' => $returnId, 'status' => 'active']);
        $this->assertSame('none', $order->fresh()->refund_status);
        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertDatabaseCount('inventory_return_restorations', 1);
    }

    public function test_direct_refund_processing_transition_is_fail_closed_without_creating_refund_state(): void
    {
        [, $vendorUser, , $order, , $stock, $returnId] = $this->onlineReturnAtItemReceived();

        try {
            app(ReturnRefundService::class)->transition($returnId, 'refund_processing', $vendorUser, 'direct-processing');
            $this->fail('Direct refund_processing transition must fail closed.');
        } catch (\LogicException) {
            // Expected: processRefund is the only settlement entrypoint.
        }
        \DB::table('return_request_transitions')->insert([
            'return_request_id' => $returnId,
            'operation_key' => 'legacy-direct-processing',
            'from_state' => 'item_received',
            'to_state' => 'refund_processing',
            'actor_type' => 'vendor',
            'actor_id' => $vendorUser->id,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            app(ReturnRefundService::class)->transition($returnId, 'refund_processing', $vendorUser, 'legacy-direct-processing');
            $this->fail('Legacy direct refund_processing evidence must not be replayed.');
        } catch (\LogicException) {
            // Expected before the idempotent-transition lookup.
        }

        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertSame('none', $order->fresh()->refund_status);
        $this->assertSame(10, $stock->fresh()->quantity);
    }

    public function test_ambiguous_paid_payment_transactions_fail_before_settlement_mutation(): void
    {
        [, , , $order, , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        PaymentTransaction::create([
            'checkout_session_id' => $payment->checkout_session_id,
            'provider' => 'vnpay',
            'provider_reference' => 'AMBIGUOUS-'.$order->id,
            'provider_transaction_id' => 'AMBIGUOUS-TXN-'.$order->id,
            'idempotency_key' => 'ambiguous-paid-'.$order->id,
            'amount' => 100000,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PAID,
            'paid_at' => now(),
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'ambiguous-refund'])
            ->assertStatus(422);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
        $this->assertSame('none', $order->fresh()->refund_status);
    }

    public function test_coherent_existing_processing_refund_is_settled_without_double_reserving_its_amount(): void
    {
        [, , , $order, , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        $return = ReturnRequest::findOrFail($returnId);
        $return->update(['status' => 'refund_processing']);
        RefundTransaction::create([
            'return_request_id' => $returnId,
            'payment_transaction_id' => $payment->id,
            'provider' => $payment->provider,
            'idempotency_key' => "refund:{$return->code}",
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'processing',
            'processing_at' => now(),
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'recover-processing'])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertDatabaseCount('refund_transactions', 1);
        $this->assertDatabaseCount('refund_transaction_attempts', 1);
        $this->assertSame('refunded', $order->fresh()->refund_status);
    }

    public function test_incoherent_historical_refund_status_or_provider_reference_fails_without_settlement_mutation(): void
    {
        [, , , , , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        $return = ReturnRequest::findOrFail($returnId);
        $return->update(['status' => 'refund_processing']);
        $refund = RefundTransaction::create([
            'return_request_id' => $returnId,
            'payment_transaction_id' => $payment->id,
            'provider' => $payment->provider,
            'idempotency_key' => "refund:{$return->code}",
            'provider_reference' => 'HISTORICAL-PROVIDER-SUCCESS',
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'processing',
            'processing_at' => now(),
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'historical-provider-ref'])
            ->assertStatus(422);
        $this->assertSame('refund_processing', $return->fresh()->status);
        $this->assertSame('processing', $refund->fresh()->status);
        $this->assertSame('HISTORICAL-PROVIDER-SUCCESS', $refund->fresh()->provider_reference);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);

        [, , , , , , $returnId2, $payment2] = $this->onlineReturnAtItemReceived();
        $return2 = ReturnRequest::findOrFail($returnId2);
        $return2->update(['status' => 'refund_processing']);
        $refund2 = RefundTransaction::create([
            'return_request_id' => $returnId2,
            'payment_transaction_id' => $payment2->id,
            'provider' => $payment2->provider,
            'idempotency_key' => "refund:{$return2->code}",
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'refunded',
            'processing_at' => now(),
        ]);
        $this->postJson("/api/vendor/returns/{$returnId2}/refund", ['idempotency_key' => 'historical-refunded-status'])
            ->assertStatus(422);
        $this->assertSame('refund_processing', $return2->fresh()->status);
        $this->assertSame('refunded', $refund2->fresh()->status);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
    }

    public function test_different_key_pending_attempt_blocks_competing_settlement_attempt(): void
    {
        [, , , , , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        $return = ReturnRequest::findOrFail($returnId);
        $return->update(['status' => 'refund_processing']);
        $refund = RefundTransaction::create([
            'return_request_id' => $returnId,
            'payment_transaction_id' => $payment->id,
            'provider' => $payment->provider,
            'idempotency_key' => "refund:{$return->code}",
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'processing',
            'processing_at' => now(),
        ]);
        RefundTransactionAttempt::create([
            'refund_transaction_id' => $refund->id,
            'operation_key' => 'historical-pending-attempt',
            'attempt_number' => 1,
            'status' => 'pending',
            'request_payload' => [],
            'response_payload' => [],
            'attempted_at' => now(),
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'new-competing-attempt'])
            ->assertStatus(422);
        $this->assertDatabaseCount('refund_transaction_attempts', 1);
        $this->assertSame('refund_processing', $return->fresh()->status);
        $this->assertSame('processing', $refund->fresh()->status);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);
    }

    public function test_current_processing_refund_with_a_failed_historical_attempt_fails_closed_without_new_settlement_effects(): void
    {
        [, , , , , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        $return = ReturnRequest::findOrFail($returnId);
        $return->update(['status' => 'refund_processing']);
        $refund = RefundTransaction::create([
            'return_request_id' => $returnId,
            'payment_transaction_id' => $payment->id,
            'provider' => $payment->provider,
            'idempotency_key' => "refund:{$return->code}",
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'processing',
            'processing_at' => now(),
        ]);
        RefundTransactionAttempt::create([
            'refund_transaction_id' => $refund->id,
            'operation_key' => 'historical-failed-processing-attempt',
            'attempt_number' => 1,
            'status' => 'failed',
            'request_payload' => [],
            'response_payload' => [],
            'failure_reason' => 'Historical internal failure',
            'attempted_at' => now(),
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'failed-processing-history'])
            ->assertStatus(422);
        $this->assertSame('refund_processing', $return->fresh()->status);
        $this->assertSame('processing', $refund->fresh()->status);
        $this->assertDatabaseCount('refund_transaction_attempts', 1);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);
    }

    public function test_coherent_failed_refund_with_only_failed_attempt_can_retry_under_a_new_key(): void
    {
        [, , , , , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        $return = ReturnRequest::findOrFail($returnId);
        $return->update(['status' => 'refund_failed']);
        $refund = RefundTransaction::create([
            'return_request_id' => $returnId,
            'payment_transaction_id' => $payment->id,
            'provider' => $payment->provider,
            'idempotency_key' => "refund:{$return->code}",
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => 'Historical internal failure',
        ]);
        RefundTransactionAttempt::create([
            'refund_transaction_id' => $refund->id,
            'operation_key' => 'historical-failed-attempt',
            'attempt_number' => 1,
            'status' => 'failed',
            'request_payload' => [],
            'response_payload' => [],
            'failure_reason' => 'Historical internal failure',
            'attempted_at' => now(),
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'retry-after-failed-attempt'])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertDatabaseCount('refund_transaction_attempts', 2);
        $this->assertDatabaseHas('refund_transactions', ['id' => $refund->id, 'status' => 'refunded']);
    }

    public function test_failed_refund_without_a_prior_attempt_fails_closed(): void
    {
        [, , , , , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        $return = ReturnRequest::findOrFail($returnId);
        $return->update(['status' => 'refund_failed']);
        RefundTransaction::create([
            'return_request_id' => $returnId,
            'payment_transaction_id' => $payment->id,
            'provider' => $payment->provider,
            'idempotency_key' => "refund:{$return->code}",
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'failed',
            'failed_at' => now(),
        ]);
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'zero-failed-retry'])
            ->assertStatus(422);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'refund_failed']);
    }

    public function test_long_operation_keys_use_bounded_internal_transition_keys_and_converge(): void
    {
        [, , , , , , $returnId] = $this->onlineReturnAtItemReceived();
        $sameKey = str_repeat('a', 128);
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => $sameKey])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => $sameKey])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => str_repeat('b', 128)])
            ->assertOk()->assertJsonPath('data.status', 'refunded');

        $this->assertDatabaseCount('refund_transaction_attempts', 1);
        foreach (\DB::table('return_request_transitions')->where('return_request_id', $returnId)->get() as $transition) {
            $this->assertLessThanOrEqual(128, strlen($transition->operation_key));
        }
    }

    public function test_legacy_wallet_credit_key_is_reused_once_and_dual_keys_fail_closed(): void
    {
        [$buyer, , , $order, , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        $account = DemoWalletAccount::create([
            'user_id' => $buyer->id,
            'balance' => 100000,
            'reserved_balance' => 0,
            'currency' => 'VND',
            'status' => 'active',
        ]);
        DemoWalletLedgerEntry::create([
            'demo_wallet_account_id' => $account->id,
            'payment_transaction_id' => $payment->id,
            'order_id' => $order->id,
            'return_request_id' => $returnId,
            'entry_type' => 'refund_credit',
            'amount' => 100000,
            'balance_before' => 0,
            'balance_after' => 100000,
            'operation_key' => "komibook-wallet:refund:{$returnId}:credit",
        ]);
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'legacy-credit-reuse'])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 3);
        $this->assertDatabaseMissing('demo_wallet_ledger_entries', [
            'operation_key' => "komibook-wallet:refund:return:{$returnId}:credit",
        ]);
        $this->assertSame(100000, $account->fresh()->balance);

        [$buyer2, , , $order2, , , $returnId2, $payment2] = $this->onlineReturnAtItemReceived();
        $account2 = DemoWalletAccount::create([
            'user_id' => $buyer2->id,
            'balance' => 100000,
            'reserved_balance' => 0,
            'currency' => 'VND',
            'status' => 'active',
        ]);
        foreach (["komibook-wallet:refund:{$returnId2}:credit", "komibook-wallet:refund:return:{$returnId2}:credit"] as $key) {
            DemoWalletLedgerEntry::create([
                'demo_wallet_account_id' => $account2->id,
                'payment_transaction_id' => $payment2->id,
                'order_id' => $order2->id,
                'return_request_id' => $returnId2,
                'entry_type' => 'refund_credit',
                'amount' => 100000,
                'balance_before' => 0,
                'balance_after' => 100000,
                'operation_key' => $key,
            ]);
        }
        $this->postJson("/api/vendor/returns/{$returnId2}/refund", ['idempotency_key' => 'dual-credit-conflict'])
            ->assertStatus(422);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId2, 'status' => 'item_received']);
        $this->assertDatabaseCount('refund_transactions', 1);
        $this->assertDatabaseCount('refund_transaction_attempts', 1);
    }

    public function test_orphan_other_order_reversal_fails_before_current_wallet_effects(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        $orphan = ReturnRequest::create([
            'code' => (string) Str::uuid(),
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'vendor_id' => $vendor->id,
            'status' => 'requested',
            'currency' => 'VND',
            'refund_amount' => 1,
            'reason' => 'orphan evidence',
            'requested_at' => now(),
        ]);
        \DB::table('vendor_earning_reversals')->insert([
            'vendor_id' => $vendor->id, 'order_id' => $order->id, 'return_request_id' => $orphan->id,
            'operation_key' => "vendor-refund:{$orphan->id}", 'gross_amount' => 1, 'commission_amount' => 0,
            'tax_amount' => 0, 'net_amount' => 1, 'currency' => 'VND', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'orphan-other-reversal'])
            ->assertStatus(422);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
    }

    public function test_conflicting_existing_hold_blocks_canonical_approval(): void
    {
        [$buyer, $vendorUser, $vendor, $order] = $this->completedPhysicalOrder();
        $return = ReturnRequest::create([
            'code' => (string) Str::uuid(), 'order_id' => $order->id, 'user_id' => $buyer->id,
            'vendor_id' => $vendor->id, 'status' => 'under_review', 'currency' => 'VND', 'refund_amount' => 100000,
            'reason' => 'hold conflict', 'requested_at' => now(),
        ]);
        VendorFinancialHold::create([
            'vendor_id' => $vendor->id, 'return_request_id' => $return->id, 'operation_key' => "refund-hold:{$return->id}",
            'amount' => 1, 'currency' => 'VND', 'status' => 'active',
        ]);
        $this->expectException(\LogicException::class);
        app(ReturnRefundService::class)->transition($return->id, 'approved', $vendorUser, 'conflicting-hold-approval');
    }

    public function test_other_return_lifecycle_matrix_rejects_missing_hold_refund_only_and_early_hold_evidence(): void
    {
        $makeOther = function (User $buyer, Vendor $vendor, Order $order, string $status): ReturnRequest {
            return ReturnRequest::create(['code' => (string) Str::uuid(), 'order_id' => $order->id, 'user_id' => $buyer->id, 'vendor_id' => $vendor->id, 'status' => $status, 'currency' => 'VND', 'refund_amount' => 1, 'reason' => 'matrix', 'requested_at' => now()]);
        };
        [$buyer, , $vendor, $order, , , $currentId, $payment] = $this->onlineReturnAtItemReceived();
        $other = $makeOther($buyer, $vendor, $order, 'refunded');
        RefundTransaction::create(['return_request_id' => $other->id, 'payment_transaction_id' => $payment->id, 'provider' => 'vnpay', 'idempotency_key' => "refund:{$other->code}", 'amount' => 1, 'currency' => 'VND', 'status' => 'refunded', 'refunded_at' => now()]);
        $this->postJson("/api/vendor/returns/{$currentId}/refund", ['idempotency_key' => 'matrix-missing-hold'])->assertStatus(422);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);

        [$buyer4, , $vendor4, $order4, , , $currentId4] = $this->onlineReturnAtItemReceived();
        $other4 = $makeOther($buyer4, $vendor4, $order4, 'requested');
        $account4 = DemoWalletAccount::create(['user_id' => $buyer4->id, 'balance' => 0, 'reserved_balance' => 0, 'currency' => 'VND', 'status' => 'active']);
        DemoWalletLedgerEntry::create(['demo_wallet_account_id' => $account4->id, 'order_id' => $order4->id, 'return_request_id' => $other4->id, 'entry_type' => 'refund_credit', 'amount' => 1, 'balance_before' => 0, 'balance_after' => 1, 'operation_key' => 'premature-sibling-wallet']);
        $this->postJson("/api/vendor/returns/{$currentId4}/refund", ['idempotency_key' => 'matrix-premature-wallet'])->assertStatus(422);
        $this->assertDatabaseMissing('refund_transactions', ['return_request_id' => $currentId4]);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 1);
        $this->assertDatabaseHas('return_requests', ['id' => $currentId4, 'status' => 'item_received']);

        [$buyer2, , $vendor2, $order2, , , $currentId2, $payment2] = $this->onlineReturnAtItemReceived();
        $other2 = $makeOther($buyer2, $vendor2, $order2, 'requested');
        RefundTransaction::create(['return_request_id' => $other2->id, 'payment_transaction_id' => $payment2->id, 'provider' => 'vnpay', 'idempotency_key' => "refund:{$other2->code}", 'amount' => 1, 'currency' => 'VND', 'status' => 'processing', 'processing_at' => now()]);
        $this->postJson("/api/vendor/returns/{$currentId2}/refund", ['idempotency_key' => 'matrix-refund-only'])->assertStatus(422);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 1);

        [$buyer3, , $vendor3, $order3, , , $currentId3] = $this->onlineReturnAtItemReceived();
        $other3 = $makeOther($buyer3, $vendor3, $order3, 'requested');
        VendorFinancialHold::create(['vendor_id' => $vendor3->id, 'return_request_id' => $other3->id, 'operation_key' => "refund-hold:{$other3->id}", 'amount' => 1, 'currency' => 'VND', 'status' => 'active']);
        $this->postJson("/api/vendor/returns/{$currentId3}/refund", ['idempotency_key' => 'matrix-early-hold'])->assertStatus(422);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 1);
    }

    public function test_processing_sibling_with_an_arbitrary_payment_binding_blocks_current_settlement_atomically(): void
    {
        [, $vendorUser, , $order, , $lowerId, $higherId] = $this->twoPartialReturnFixture();
        $this->actingAs($vendorUser)->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);
        foreach ([$lowerId, $higherId] as $id) {
            $this->patchJson("/api/vendor/returns/{$id}/transition", [
                'target' => 'under_review', 'idempotency_key' => "wrong-sibling-payment-{$id}-review",
            ])->assertOk();
            $this->patchJson("/api/vendor/returns/{$id}/transition", [
                'target' => 'approved', 'idempotency_key' => "wrong-sibling-payment-{$id}-approve",
            ])->assertOk();
            $this->patchJson("/api/vendor/returns/{$id}/transition", [
                'target' => 'item_received', 'idempotency_key' => "wrong-sibling-payment-{$id}-received",
            ])->assertOk();
        }

        $other = ReturnRequest::findOrFail($lowerId);
        $other->update(['status' => 'refund_processing']);
        $arbitraryPayment = PaymentTransaction::create([
            'checkout_session_id' => $order->checkoutSessionOrder->checkout_session_id,
            'provider' => 'vnpay',
            'provider_reference' => 'ARBITRARY-'.$order->id,
            'provider_transaction_id' => 'ARBITRARY-TXN-'.$order->id,
            'idempotency_key' => 'arbitrary-pending-'.$order->id,
            'amount' => 200000,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PENDING,
        ]);
        RefundTransaction::create([
            'return_request_id' => $lowerId,
            'payment_transaction_id' => $arbitraryPayment->id,
            'provider' => $arbitraryPayment->provider,
            'idempotency_key' => "refund:{$other->code}",
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'processing',
            'processing_at' => now(),
        ]);

        $this->postJson("/api/vendor/returns/{$higherId}/refund", ['idempotency_key' => 'wrong-sibling-payment-current'])
            ->assertStatus(422);
        $this->assertDatabaseHas('return_requests', ['id' => $lowerId, 'status' => 'refund_processing']);
        $this->assertDatabaseHas('return_requests', ['id' => $higherId, 'status' => 'item_received']);
        $this->assertDatabaseCount('refund_transactions', 1);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);
        $this->assertSame('none', $order->fresh()->refund_status);
    }

    public function test_two_partial_returns_allocate_odd_components_canonically_in_reverse_and_forward_settlement_order(): void
    {
        foreach ([true, false] as $reverse) {
            [$buyer, $vendorUser, $vendor, $order, $stock, $lowerId, $higherId] = $this->twoPartialReturnFixture();
            $this->actingAs($vendorUser)->withHeader('Origin', 'https://komibook.id.vn')
                ->withSession(['auth.password_confirmed_at' => time()]);
            foreach ([$lowerId, $higherId] as $id) {
                $this->patchJson("/api/vendor/returns/{$id}/transition", [
                    'target' => 'under_review', 'idempotency_key' => "partial-{$id}-review",
                ])->assertOk();
            }
            $this->patchJson("/api/vendor/returns/{$higherId}/transition", [
                'target' => 'approved', 'idempotency_key' => "partial-{$higherId}-approve-early",
            ])->assertStatus(422);
            $this->assertDatabaseMissing('vendor_financial_holds', ['return_request_id' => $higherId]);
            foreach ([$lowerId, $higherId] as $id) {
                $this->patchJson("/api/vendor/returns/{$id}/transition", [
                    'target' => 'approved', 'idempotency_key' => "partial-{$id}-approve",
                ])->assertOk();
                $this->patchJson("/api/vendor/returns/{$id}/transition", [
                    'target' => 'item_received', 'idempotency_key' => "partial-{$id}-received",
                ])->assertOk();
            }
            $holds = \DB::table('vendor_financial_holds')->whereIn('return_request_id', [$lowerId, $higherId])->get();
            $this->assertSame(2, $holds->count());
            $this->assertSame(188998, (int) $holds->sum('amount'));
            $this->assertNotSame((int) $holds[0]->amount, (int) $holds[1]->amount);
            $settlementIds = $reverse ? [$higherId, $lowerId] : [$lowerId, $higherId];
            foreach ($settlementIds as $id) {
                $this->postJson("/api/vendor/returns/{$id}/refund", ['idempotency_key' => "partial-{$id}-refund"])
                    ->assertOk()->assertJsonPath('data.status', 'refunded');
            }
            $sum = fn (string $table, string $column) => (int) \DB::table($table)->where('order_id', $order->id)->sum($column);
            $this->assertSame(200000, $sum('vendor_earning_reversals', 'gross_amount'));
            $this->assertSame(10001, $sum('vendor_earning_reversals', 'commission_amount'));
            $this->assertSame(1001, $sum('vendor_earning_reversals', 'tax_amount'));
            $this->assertSame(188998, $sum('vendor_earning_reversals', 'net_amount'));
            $this->assertSame(200000, (int) \DB::table('demo_wallet_ledger_entries')->where('order_id', $order->id)->where('entry_type', 'refund_credit')->sum('amount'));
            $this->assertSame(188998, (int) \DB::table('demo_wallet_ledger_entries')->where('order_id', $order->id)->where('entry_type', 'vendor_refund_debit')->sum('amount'));
            $this->assertSame(10, $sum('loyalty_point_reversals', 'points'));
            $this->assertSame(0, $vendor->fresh()->balance);
            $this->assertSame('refunded', $order->fresh()->refund_status);
            $this->assertSame(2, RefundTransactionAttempt::whereIn('refund_transaction_id', RefundTransaction::whereIn('return_request_id', [$lowerId, $higherId])->pluck('id'))->count());
            $this->assertSame(2, \DB::table('inventory_return_restorations')->whereIn('return_request_item_id', \DB::table('return_request_items')->whereIn('return_request_id', [$lowerId, $higherId])->pluck('id'))->count());
            $this->assertSame(10, $stock->fresh()->quantity);
        }
    }

    public function test_two_partial_refunds_can_replay_and_reconcile_the_first_refund_after_the_second_without_new_effects(): void
    {
        [$buyer, $vendorUser, $vendor, $order, , $lowerId, $higherId] = $this->twoPartialReturnFixture();
        $this->actingAs($vendorUser)->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);
        foreach ([$lowerId, $higherId] as $id) {
            $this->patchJson("/api/vendor/returns/{$id}/transition", [
                'target' => 'under_review', 'idempotency_key' => "replay-partial-{$id}-review",
            ])->assertOk();
            $this->patchJson("/api/vendor/returns/{$id}/transition", [
                'target' => 'approved', 'idempotency_key' => "replay-partial-{$id}-approve",
            ])->assertOk();
            $this->patchJson("/api/vendor/returns/{$id}/transition", [
                'target' => 'item_received', 'idempotency_key' => "replay-partial-{$id}-received",
            ])->assertOk();
        }
        foreach ([$lowerId, $higherId] as $id) {
            $this->postJson("/api/vendor/returns/{$id}/refund", ['idempotency_key' => "replay-partial-{$id}-refund"])
                ->assertOk()->assertJsonPath('data.status', 'refunded');
        }
        $before = [
            'buyer_balance' => DemoWalletAccount::where('user_id', $buyer->id)->value('balance'),
            'vendor_balance' => $vendor->fresh()->balance,
            'attempts' => RefundTransactionAttempt::count(),
            'wallet_entries' => DemoWalletLedgerEntry::count(),
            'earning_reversals' => \DB::table('vendor_earning_reversals')->count(),
            'point_reversals' => \DB::table('loyalty_point_reversals')->count(),
        ];

        $this->postJson("/api/vendor/returns/{$lowerId}/refund", ['idempotency_key' => 'replay-first-after-second'])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->postJson("/api/vendor/returns/{$lowerId}/refund/reconcile", ['idempotency_key' => 'reconcile-first-after-second'])
            ->assertOk()->assertJsonPath('data.status', 'refunded');

        $this->assertSame($before['buyer_balance'], DemoWalletAccount::where('user_id', $buyer->id)->value('balance'));
        $this->assertSame($before['vendor_balance'], $vendor->fresh()->balance);
        $this->assertSame($before['attempts'], RefundTransactionAttempt::count());
        $this->assertSame($before['wallet_entries'], DemoWalletLedgerEntry::count());
        $this->assertSame($before['earning_reversals'], \DB::table('vendor_earning_reversals')->count());
        $this->assertSame($before['point_reversals'], \DB::table('loyalty_point_reversals')->count());
        $this->assertSame('refunded', $order->fresh()->refund_status);
    }

    public function test_tampered_current_vendor_balance_blocks_replay_and_reconcile_without_new_effects(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'vendor-balance-tamper-first'])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $vendor->forceFill(['balance' => 1])->save();
        $before = [
            'buyer_balance' => DemoWalletAccount::where('user_id', $buyer->id)->value('balance'),
            'attempts' => RefundTransactionAttempt::count(),
            'wallet_entries' => DemoWalletLedgerEntry::count(),
            'earning_reversals' => \DB::table('vendor_earning_reversals')->count(),
            'point_reversals' => \DB::table('loyalty_point_reversals')->count(),
        ];

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'vendor-balance-tamper-replay'])
            ->assertStatus(422);
        $this->postJson("/api/vendor/returns/{$returnId}/refund/reconcile", ['idempotency_key' => 'vendor-balance-tamper-reconcile'])
            ->assertStatus(422);

        $this->assertSame($before['buyer_balance'], DemoWalletAccount::where('user_id', $buyer->id)->value('balance'));
        $this->assertSame(1, $vendor->fresh()->balance);
        $this->assertSame($before['attempts'], RefundTransactionAttempt::count());
        $this->assertSame($before['wallet_entries'], DemoWalletLedgerEntry::count());
        $this->assertSame($before['earning_reversals'], \DB::table('vendor_earning_reversals')->count());
        $this->assertSame($before['point_reversals'], \DB::table('loyalty_point_reversals')->count());
        $this->assertSame('refunded', $order->fresh()->refund_status);
    }

    public function test_refunded_sibling_payment_and_wallet_identity_tampering_blocks_current_settlement(): void
    {
        $prepare = function (): array {
            [, $vendorUser, , $order, , $lowerId, $higherId] = $this->twoPartialReturnFixture();
            $this->actingAs($vendorUser)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()]);
            foreach ([$lowerId, $higherId] as $id) {
                $this->patchJson("/api/vendor/returns/{$id}/transition", ['target' => 'under_review', 'idempotency_key' => "identity-{$id}-review"])->assertOk();
            }
            foreach ([$lowerId, $higherId] as $id) {
                $this->patchJson("/api/vendor/returns/{$id}/transition", ['target' => 'approved', 'idempotency_key' => "identity-{$id}-approve"])->assertOk();
                $this->patchJson("/api/vendor/returns/{$id}/transition", ['target' => 'item_received', 'idempotency_key' => "identity-{$id}-received"])->assertOk();
            }
            $this->postJson("/api/vendor/returns/{$lowerId}/refund", ['idempotency_key' => "identity-{$lowerId}-refund"])->assertOk();

            return [$order, $lowerId, $higherId];
        };
        [$order, $lowerId, $higherId] = $prepare();
        $refund = RefundTransaction::where('return_request_id', $lowerId)->firstOrFail();
        PaymentTransaction::whereKey($refund->payment_transaction_id)->update(['provider' => 'momo']);
        $this->postJson("/api/vendor/returns/{$higherId}/refund", ['idempotency_key' => 'identity-payment-tamper'])->assertStatus(422);
        $this->assertDatabaseHas('return_requests', ['id' => $higherId, 'status' => 'item_received']);
        $this->assertSame(1, RefundTransactionAttempt::whereIn('refund_transaction_id', RefundTransaction::whereIn('return_request_id', [$lowerId, $higherId])->pluck('id'))->count());

        [, $lowerId2, $higherId2] = $prepare();
        DemoWalletLedgerEntry::where('operation_key', "komibook-wallet:refund:return:{$lowerId2}:credit")->update(['payment_transaction_id' => null]);
        $this->postJson("/api/vendor/returns/{$higherId2}/refund", ['idempotency_key' => 'identity-wallet-tamper'])->assertStatus(422);
        $this->assertDatabaseHas('return_requests', ['id' => $higherId2, 'status' => 'item_received']);
        $this->assertSame(1, RefundTransactionAttempt::whereIn('refund_transaction_id', RefundTransaction::whereIn('return_request_id', [$lowerId2, $higherId2])->pluck('id'))->count());

        [, $lowerId3, $higherId3] = $prepare();
        $credit = DemoWalletLedgerEntry::where('return_request_id', $lowerId3)->where('entry_type', 'refund_credit')->firstOrFail();
        DemoWalletLedgerEntry::create([
            'demo_wallet_account_id' => $credit->demo_wallet_account_id,
            'payment_transaction_id' => $credit->payment_transaction_id,
            'order_id' => $credit->order_id,
            'return_request_id' => $lowerId3,
            'entry_type' => 'refund_credit',
            'amount' => $credit->amount,
            'balance_before' => $credit->balance_after,
            'balance_after' => $credit->balance_after,
            'operation_key' => "arbitrary-refunded-sibling-credit-{$lowerId3}",
        ]);
        $this->postJson("/api/vendor/returns/{$higherId3}/refund", ['idempotency_key' => 'identity-arbitrary-duplicate'])
            ->assertStatus(422);
        $this->assertDatabaseHas('return_requests', ['id' => $higherId3, 'status' => 'item_received']);
        $this->assertDatabaseMissing('refund_transactions', ['return_request_id' => $higherId3]);
        $this->assertSame(1, RefundTransactionAttempt::whereIn('refund_transaction_id', RefundTransaction::whereIn('return_request_id', [$lowerId3, $higherId3])->pluck('id'))->count());
    }

    public function test_checkout_session_and_paid_payment_currency_mismatches_fail_closed_before_refund_mutation(): void
    {
        [, , , $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        $order->checkoutSessionOrder->checkoutSession->update(['currency' => 'USD']);
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'checkout-session-currency-mismatch'])
            ->assertStatus(422);
        $this->assertDatabaseMissing('refund_transactions', ['return_request_id' => $returnId]);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);

        [, , , $order2, , , $returnId2, $payment2] = $this->onlineReturnAtItemReceived();
        $payment2->update(['currency' => 'USD']);
        $this->postJson("/api/vendor/returns/{$returnId2}/refund", ['idempotency_key' => 'paid-payment-currency-mismatch'])
            ->assertStatus(422);
        $this->assertDatabaseMissing('refund_transactions', ['return_request_id' => $returnId2]);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId2, 'status' => 'item_received']);
    }

    public function test_paid_payment_amount_and_paid_at_corruption_fail_before_refund_mutation(): void
    {
        [, , , , , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        $payment->update(['amount' => 99999]);
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'paid-amount-corruption'])
            ->assertStatus(422);
        $this->assertDatabaseMissing('refund_transactions', ['return_request_id' => $returnId]);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);

        [, , , , , , $returnId2, $payment2] = $this->onlineReturnAtItemReceived();
        $payment2->update(['paid_at' => null]);
        $this->postJson("/api/vendor/returns/{$returnId2}/refund", ['idempotency_key' => 'paid-at-corruption'])
            ->assertStatus(422);
        $this->assertDatabaseMissing('refund_transactions', ['return_request_id' => $returnId2]);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId2, 'status' => 'item_received']);
    }

    public function test_existing_buyer_credit_with_false_wallet_projection_blocks_settlement_without_new_effects(): void
    {
        [$buyer, , $vendor, $order, , , $returnId, $payment] = $this->onlineReturnAtItemReceived();
        $account = DemoWalletAccount::create([
            'user_id' => $buyer->id,
            'balance' => 99999,
            'reserved_balance' => 0,
            'currency' => 'VND',
            'status' => 'active',
        ]);
        DemoWalletLedgerEntry::create([
            'demo_wallet_account_id' => $account->id,
            'payment_transaction_id' => $payment->id,
            'order_id' => $order->id,
            'return_request_id' => $returnId,
            'entry_type' => 'refund_credit',
            'amount' => 100000,
            'balance_before' => 0,
            'balance_after' => 100000,
            'operation_key' => "komibook-wallet:refund:return:{$returnId}:credit",
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'false-buyer-credit-projection'])
            ->assertStatus(422);
        $this->assertSame(99999, $account->fresh()->balance);
        $this->assertSame(90000, $vendor->fresh()->balance);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 1);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
    }

    public function test_existing_vendor_debit_without_projection_metadata_blocks_settlement_without_new_effects(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        $account = DemoWalletAccount::create([
            'user_id' => $vendor->user_id,
            'balance' => 0,
            'reserved_balance' => 0,
            'currency' => 'VND',
            'status' => 'active',
        ]);
        DemoWalletLedgerEntry::create([
            'demo_wallet_account_id' => $account->id,
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'return_request_id' => $returnId,
            'entry_type' => 'vendor_refund_debit',
            'amount' => 90000,
            'balance_before' => 90000,
            'balance_after' => 0,
            'operation_key' => "komibook-wallet:vendor-refund:{$returnId}:debit",
            'metadata' => [],
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'missing-vendor-debit-projection'])
            ->assertStatus(422);
        $this->assertSame(0, $account->fresh()->balance);
        $this->assertSame(90000, $vendor->fresh()->balance);
        $this->assertSame(10, $buyer->fresh()->points);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_accounts', 1);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 1);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
    }

    public function test_tampered_order_refund_projection_is_rejected_by_replay_and_reconcile_without_new_effects(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'projection-tamper-first'])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        Order::withoutGlobalScopes()->whereKey($order->id)->update(['refund_status' => 'none']);
        $this->assertSame('none', $order->fresh()->refund_status);
        $before = [
            'buyer_balance' => DemoWalletAccount::where('user_id', $buyer->id)->value('balance'),
            'vendor_balance' => $vendor->fresh()->balance,
            'wallet_entries' => DemoWalletLedgerEntry::count(),
            'attempts' => RefundTransactionAttempt::count(),
        ];

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'projection-tamper-replay'])
            ->assertStatus(422);
        $this->postJson("/api/vendor/returns/{$returnId}/refund/reconcile", ['idempotency_key' => 'projection-tamper-reconcile'])
            ->assertStatus(422);
        $this->assertSame($before['buyer_balance'], DemoWalletAccount::where('user_id', $buyer->id)->value('balance'));
        $this->assertSame($before['vendor_balance'], $vendor->fresh()->balance);
        $this->assertSame($before['wallet_entries'], DemoWalletLedgerEntry::count());
        $this->assertSame($before['attempts'], RefundTransactionAttempt::count());
        $this->assertSame('none', $order->fresh()->refund_status);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'refunded']);
    }

    public function test_current_canonical_identity_wallet_and_loyalty_tampering_fail_without_settlement_effects(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        ReturnRequest::whereKey($returnId)->update(['user_id' => User::factory()->create()->id]);
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'wrong-return-buyer'])->assertStatus(422);
        $this->assertDatabaseCount('refund_transactions', 0);

        [$buyer2, , $vendor2, $order2, , , $returnId2] = $this->onlineReturnAtItemReceived();
        ReturnRequest::whereKey($returnId2)->update(['vendor_id' => Vendor::create(['user_id' => User::factory()->create(['role' => 'vendor'])->id, 'shop_name' => 'Wrong Vendor', 'slug' => 'wrong-vendor-'.uniqid(), 'status' => 'active'])->id]);
        $this->postJson("/api/vendor/returns/{$returnId2}/refund", ['idempotency_key' => 'wrong-return-vendor'])->assertStatus(422);
        $this->assertDatabaseCount('refund_transactions', 0);

        [$buyer3, , , $order3, , , $returnId3] = $this->onlineReturnAtItemReceived();
        \DB::table('invoice_snapshots')->where('order_id', $order3->id)->update(['currency' => 'USD']);
        $this->postJson("/api/vendor/returns/{$returnId3}/refund", ['idempotency_key' => 'wrong-session-currency'])->assertStatus(422);
        $this->assertDatabaseCount('refund_transactions', 0);

        [$buyer4, , , $order4, , , $returnId4] = $this->onlineReturnAtItemReceived();
        $account = DemoWalletAccount::create(['user_id' => $buyer4->id, 'balance' => 0, 'reserved_balance' => 0, 'currency' => 'VND', 'status' => 'active']);
        DemoWalletLedgerEntry::create(['demo_wallet_account_id' => $account->id, 'order_id' => $order4->id, 'return_request_id' => $returnId4, 'entry_type' => 'refund_credit', 'amount' => 1, 'balance_before' => 0, 'balance_after' => 1, 'operation_key' => 'arbitrary-refund-credit-key']);
        $this->postJson("/api/vendor/returns/{$returnId4}/refund", ['idempotency_key' => 'arbitrary-wallet-key'])->assertStatus(422);
        $this->assertDatabaseCount('refund_transactions', 0);

        [$buyer5, , , $order5, , , $returnId5] = $this->onlineReturnAtItemReceived();
        $buyer5->update(['points' => 0]);
        $this->postJson("/api/vendor/returns/{$returnId5}/refund", ['idempotency_key' => 'insufficient-loyalty'])->assertStatus(422);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 1);
        $this->assertSame('none', $order5->fresh()->refund_status);
    }

    public function test_malformed_refunded_replay_fails_closed_without_new_effects(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'malformed-replay-first'])->assertOk();
        $before = [
            'buyer_balance' => DemoWalletAccount::where('user_id', $buyer->id)->value('balance'),
            'vendor_balance' => $vendor->fresh()->balance,
            'wallet_entries' => DemoWalletLedgerEntry::count(),
            'attempts' => RefundTransactionAttempt::count(),
            'order_status' => $order->fresh()->refund_status,
        ];
        \DB::table('vendor_earning_reversals')->where('return_request_id', $returnId)->delete();
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'malformed-replay-second'])->assertStatus(422);
        $this->assertSame($before['buyer_balance'], DemoWalletAccount::where('user_id', $buyer->id)->value('balance'));
        $this->assertSame($before['vendor_balance'], $vendor->fresh()->balance);
        $this->assertSame($before['wallet_entries'], DemoWalletLedgerEntry::count());
        $this->assertSame($before['attempts'], RefundTransactionAttempt::count());
        $this->assertSame($before['order_status'], $order->fresh()->refund_status);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'refunded']);
    }

    public function test_canonical_and_arbitrary_vendor_debit_evidence_block_initial_settlement_without_new_effects(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        $account = DemoWalletAccount::create([
            'user_id' => $vendor->user_id,
            'balance' => 90000,
            'reserved_balance' => 0,
            'currency' => 'VND',
            'status' => 'active',
        ]);
        foreach (["komibook-wallet:vendor-refund:{$returnId}:debit", "arbitrary-vendor-refund-debit-{$returnId}"] as $key) {
            DemoWalletLedgerEntry::create([
                'demo_wallet_account_id' => $account->id,
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'return_request_id' => $returnId,
                'entry_type' => 'vendor_refund_debit',
                'amount' => 90000,
                'balance_before' => 90000,
                'balance_after' => 0,
                'operation_key' => $key,
            ]);
        }

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'canonical-and-arbitrary-vendor-debit'])
            ->assertStatus(422);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_accounts', 1);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 2);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);
        $this->assertSame(90000, $vendor->fresh()->balance);
        $this->assertSame(10, $buyer->fresh()->points);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
    }

    public function test_reconcile_rejects_tampered_refunded_evidence_without_new_mutation(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'reconcile-tamper-first'])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $before = [
            'buyer_balance' => DemoWalletAccount::where('user_id', $buyer->id)->value('balance'),
            'vendor_balance' => $vendor->fresh()->balance,
            'wallet_entries' => DemoWalletLedgerEntry::count(),
            'attempts' => RefundTransactionAttempt::count(),
            'order_status' => $order->fresh()->refund_status,
        ];
        RefundTransaction::where('return_request_id', $returnId)->update(['provider_reference' => 'TAMPERED-REFUND-REFERENCE']);

        $this->postJson("/api/vendor/returns/{$returnId}/refund/reconcile", ['idempotency_key' => 'reconcile-tamper-second'])
            ->assertStatus(422);
        $this->assertSame($before['buyer_balance'], DemoWalletAccount::where('user_id', $buyer->id)->value('balance'));
        $this->assertSame($before['vendor_balance'], $vendor->fresh()->balance);
        $this->assertSame($before['wallet_entries'], DemoWalletLedgerEntry::count());
        $this->assertSame($before['attempts'], RefundTransactionAttempt::count());
        $this->assertSame($before['order_status'], $order->fresh()->refund_status);
        $this->assertDatabaseHas('refund_transactions', [
            'return_request_id' => $returnId,
            'provider_reference' => 'TAMPERED-REFUND-REFERENCE',
            'status' => 'refunded',
        ]);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'refunded']);
    }

    public function test_wallet_ledger_and_vendor_reversal_conflicts_fail_without_additional_settlement_effects(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        $account = DemoWalletAccount::create([
            'user_id' => $buyer->id,
            'balance' => 0,
            'reserved_balance' => 0,
            'currency' => 'VND',
            'status' => 'active',
        ]);
        DemoWalletLedgerEntry::create([
            'demo_wallet_account_id' => $account->id,
            'order_id' => $order->id,
            'return_request_id' => $returnId,
            'entry_type' => 'refund_credit',
            'amount' => 1,
            'balance_before' => 0,
            'balance_after' => 1,
            'operation_key' => "komibook-wallet:refund:return:{$returnId}:credit",
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'wallet-conflict'])
            ->assertStatus(422);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 1);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertSame(90000, $vendor->fresh()->balance);

        [$buyer2, , $vendor2, $order2, , , $returnId2] = $this->onlineReturnAtItemReceived();
        \DB::table('vendor_earning_reversals')->insert([
            'vendor_id' => $vendor2->id,
            'order_id' => $order2->id,
            'return_request_id' => $returnId2,
            'operation_key' => "vendor-refund:{$returnId2}",
            'gross_amount' => 1,
            'commission_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => 1,
            'currency' => 'VND',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->postJson("/api/vendor/returns/{$returnId2}/refund", ['idempotency_key' => 'reversal-conflict'])
            ->assertStatus(422);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 1);
        $this->assertSame(90000, $vendor2->fresh()->balance);
        $this->assertSame(10, $buyer2->fresh()->points);
    }

    public function test_cross_return_attempt_key_is_rejected_even_after_current_return_is_refunded(): void
    {
        [, $firstVendorUser, , $firstOrder, , , $firstReturnId] = $this->onlineReturnAtItemReceived();
        $this->postJson("/api/vendor/returns/{$firstReturnId}/refund", ['idempotency_key' => 'first-settlement'])
            ->assertOk();
        [, , , , , , $secondReturnId, $secondPayment] = $this->onlineReturnAtItemReceived();
        $secondReturn = ReturnRequest::findOrFail($secondReturnId);
        $secondRefund = RefundTransaction::create([
            'return_request_id' => $secondReturnId,
            'payment_transaction_id' => $secondPayment->id,
            'provider' => 'vnpay',
            'idempotency_key' => "refund:{$secondReturn->code}",
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'processing',
            'processing_at' => now(),
        ]);
        RefundTransactionAttempt::create([
            'refund_transaction_id' => $secondRefund->id,
            'operation_key' => 'cross-return-attempt-key',
            'attempt_number' => 1,
            'status' => 'processing',
            'request_payload' => [],
            'response_payload' => [],
            'attempted_at' => now(),
        ]);

        $this->actingAs($firstVendorUser)->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);
        $this->postJson("/api/vendor/returns/{$firstReturnId}/refund", ['idempotency_key' => 'cross-return-attempt-key'])
            ->assertStatus(422);
        $this->assertDatabaseCount('refund_transaction_attempts', 2);
        $this->assertDatabaseHas('return_requests', ['id' => $firstReturnId, 'status' => 'refunded']);
        $this->assertDatabaseHas('return_requests', ['id' => $secondReturnId, 'status' => 'item_received']);
    }

    public function test_conflicting_loyalty_reversal_rolls_back_the_vendor_debit_that_preceded_it(): void
    {
        [$buyer, , $vendor, $order, , , $returnId] = $this->onlineReturnAtItemReceived();
        \DB::table('vendor_earning_reversals')->insert([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'return_request_id' => $returnId,
            'operation_key' => "vendor-refund:{$returnId}",
            'gross_amount' => 100000,
            'commission_amount' => 10000,
            'tax_amount' => 0,
            'net_amount' => 90000,
            'currency' => 'VND',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('loyalty_point_reversals')->insert([
            'user_id' => $buyer->id,
            'order_id' => $order->id,
            'return_request_id' => $returnId,
            'operation_key' => "loyalty-refund:{$returnId}",
            'points' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'loyalty-conflict'])
            ->assertStatus(422);
        $this->assertSame(90000, $vendor->fresh()->balance);
        $this->assertSame(10, $buyer->fresh()->points);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
        $this->assertDatabaseHas('vendor_financial_holds', ['return_request_id' => $returnId, 'status' => 'active']);
    }

    public function test_failure_injected_after_vendor_debit_rolls_back_every_settlement_effect(): void
    {
        [, , $vendor, $order, , $stock, $returnId] = $this->onlineReturnAtItemReceived();
        $this->app->instance(DemoWalletService::class, new class extends DemoWalletService
        {
            public function debitVendorRefund(Vendor $vendor, Order $order, int $amount, int $returnRequestId): DemoWalletLedgerEntry
            {
                $entry = parent::debitVendorRefund($vendor, $order, $amount, $returnRequestId);
                throw new \LogicException('Injected failure after vendor debit.');
            }
        });

        $this->postJson("/api/vendor/returns/{$returnId}/refund", ['idempotency_key' => 'after-debit-failure'])
            ->assertStatus(422);
        $this->assertSame(90000, $vendor->fresh()->balance);
        $this->assertSame('none', $order->fresh()->refund_status);
        $this->assertDatabaseCount('demo_wallet_accounts', 0);
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('refund_transactions', 0);
        $this->assertDatabaseCount('refund_transaction_attempts', 0);
        $this->assertDatabaseCount('vendor_earning_reversals', 0);
        $this->assertDatabaseCount('loyalty_point_reversals', 0);
        $this->assertDatabaseHas('vendor_financial_holds', ['return_request_id' => $returnId, 'status' => 'active']);
        $this->assertDatabaseHas('return_requests', ['id' => $returnId, 'status' => 'item_received']);
        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertDatabaseCount('inventory_return_restorations', 1);
    }

    public function test_return_window_and_duplicate_quantity_fail_closed(): void
    {
        [$buyer, , , $order, $item] = $this->completedPhysicalOrder();
        $order->transitionOperations()->update(['occurred_at' => now()->subDays(8)]);
        Sanctum::actingAs($buyer);
        $payload = [
            'reason' => 'Sản phẩm bị hư hỏng khi nhận hàng.',
            'idempotency_key' => 'expired-return-'.$order->id,
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ];
        $this->postJson("/api/orders/{$order->id}/returns", $payload)->assertStatus(422);

        $order->transitionOperations()->update(['occurred_at' => now()]);
        $payload['idempotency_key'] = 'valid-return-'.$order->id;
        $this->postJson("/api/orders/{$order->id}/returns", $payload)->assertCreated();
        $payload['idempotency_key'] = 'duplicate-return-'.$order->id;
        $this->postJson("/api/orders/{$order->id}/returns", $payload)->assertStatus(422);
    }

    public function test_idempotency_key_cannot_replay_another_buyers_return(): void
    {
        [$firstBuyer, , , $firstOrder, $firstItem] = $this->completedPhysicalOrder();
        [$secondBuyer, , , $secondOrder, $secondItem] = $this->completedPhysicalOrder();
        $sharedKey = 'shared-customer-return-key';

        Sanctum::actingAs($firstBuyer);
        $this->postJson("/api/orders/{$firstOrder->id}/returns", [
            'reason' => 'Sản phẩm đầu tiên bị hư hỏng khi nhận.',
            'idempotency_key' => $sharedKey,
            'items' => [['order_item_id' => $firstItem->id, 'quantity' => 1]],
        ])->assertCreated();

        Sanctum::actingAs($secondBuyer);
        $this->postJson("/api/orders/{$secondOrder->id}/returns", [
            'reason' => 'Sản phẩm thứ hai bị hư hỏng khi nhận.',
            'idempotency_key' => $sharedKey,
            'items' => [['order_item_id' => $secondItem->id, 'quantity' => 1]],
        ])->assertStatus(422);
        $this->assertDatabaseCount('return_requests', 1);
    }

    public function test_other_vendor_cannot_process_return(): void
    {
        [$buyer, , , $order, $item] = $this->completedPhysicalOrder();
        Sanctum::actingAs($buyer);
        $returnId = $this->postJson("/api/orders/{$order->id}/returns", [
            'reason' => 'Sản phẩm không đúng mô tả ban đầu.',
            'idempotency_key' => 'foreign-vendor-return',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $otherVendorUser = User::factory()->create(['role' => 'vendor']);
        Vendor::create([
            'user_id' => $otherVendorUser->id,
            'shop_name' => 'Other Shop',
            'slug' => 'other-shop',
            'status' => 'active',
        ]);
        $this->actingAs($otherVendorUser)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);

        $this->patchJson("/api/vendor/returns/{$returnId}/transition", [
            'target' => 'under_review',
            'idempotency_key' => 'foreign-vendor-transition',
        ])->assertForbidden();
    }

    /**
     * @return array{User, User, Vendor, Order, OrderItem, WarehouseStock, int, PaymentTransaction}
     */
    private function onlineReturnAtItemReceived(): array
    {
        [$buyer, $vendorUser, $vendor, $order, $item, $stock] = $this->completedPhysicalOrder();
        $order->update(['payment_method' => 'online']);
        $payment = PaymentTransaction::create([
            'checkout_session_id' => $order->checkoutSessionOrder->checkout_session_id,
            'provider' => 'vnpay',
            'provider_reference' => 'ONLINE-'.$order->id,
            'provider_transaction_id' => 'ONLINE-TXN-'.$order->id,
            'idempotency_key' => 'online-paid-'.$order->id,
            'amount' => 100000,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PAID,
            'paid_at' => now(),
        ]);
        Sanctum::actingAs($buyer);
        $returnId = $this->postJson("/api/orders/{$order->id}/returns", [
            'reason' => 'Sản phẩm hư hỏng khi nhận hàng.',
            'idempotency_key' => 'online-return-'.$order->id,
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');
        $this->actingAs($vendorUser)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);
        foreach (['under_review', 'approved', 'item_received'] as $target) {
            $this->patchJson("/api/vendor/returns/{$returnId}/transition", [
                'target' => $target,
                'idempotency_key' => "online-{$returnId}-{$target}",
            ])->assertOk();
        }

        return [$buyer, $vendorUser, $vendor, $order, $item, $stock, $returnId, $payment];
    }

    /** @return array{User, User, Vendor, Order, WarehouseStock, int, int} */
    private function twoPartialReturnFixture(): array
    {
        [$buyer, $vendorUser, $vendor, $order, $item, $stock] = $this->completedPhysicalOrder();
        $order->forceFill(['total_amount' => 200000, 'payment_method' => 'online'])->save();
        $item->update(['quantity' => 2, 'price' => 100000]);
        $stock->update(['quantity' => 8]);
        $item->book->update(['stock' => 8]);
        $reservation = InventoryReservation::where('order_item_id', $item->id)->firstOrFail();
        $reservation->update(['quantity' => 2]);
        InventoryReservationAllocation::where('inventory_reservation_id', $reservation->id)->update(['quantity' => 2]);
        \DB::table('invoice_snapshots')->where('order_id', $order->id)->update([
            'line_items' => json_encode([['order_item_id' => $item->id, 'book_id' => $item->book_id, 'title' => 'Original Book', 'type' => 'physical', 'provenance' => 'used_resale', 'return_policy_snapshot' => ['is_returnable' => true, 'return_window_days' => 7], 'quantity' => 2, 'unit_price' => 100000, 'line_total' => 200000]]),
            'subtotal_amount' => 200000, 'total_amount' => 200000,
        ]);
        $order->checkoutSessionOrder->update(['subtotal_amount' => 200000, 'total_amount' => 200000, 'commission_amount' => 10001]);
        $order->checkoutSessionOrder->checkoutSession->update(['subtotal_amount' => 200000, 'total_amount' => 200000]);
        $order->vendorEarningLedger()->update(['gross_amount' => 200000, 'commission_amount' => 10001, 'tax_amount' => 1001, 'net_amount' => 188998]);
        $vendor->forceFill(['balance' => 188998])->save();
        PaymentTransaction::create(['checkout_session_id' => $order->checkoutSessionOrder->checkout_session_id, 'provider' => 'vnpay', 'provider_reference' => 'PARTIAL-'.$order->id, 'provider_transaction_id' => 'PARTIAL-TXN-'.$order->id, 'idempotency_key' => 'partial-paid-'.$order->id, 'amount' => 200000, 'currency' => 'VND', 'status' => PaymentTransactionStatus::PAID, 'paid_at' => now()]);
        Sanctum::actingAs($buyer);
        $create = fn (string $key) => $this->postJson("/api/orders/{$order->id}/returns", ['reason' => 'Partial return', 'idempotency_key' => $key, 'items' => [['order_item_id' => $item->id, 'quantity' => 1]]])->assertCreated()->json('data.id');
        $lowerId = $create('partial-return-a-'.$order->id);
        $higherId = $create('partial-return-b-'.$order->id);

        return [$buyer, $vendorUser, $vendor, $order, $stock, $lowerId, $higherId];
    }

    /**
     * @return array{User, User, Vendor, Order, OrderItem, WarehouseStock}
     */
    private function completedPhysicalOrder(): array
    {
        $buyer = User::factory()->create([
            'name' => 'Original Buyer',
            'points' => 10,
            'email_verified_at' => now(),
        ]);
        $vendorUser = User::factory()->create([
            'name' => 'Original Seller',
            'role' => 'vendor',
        ]);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Original Shop',
            'slug' => 'original-shop-'.uniqid(),
            'status' => 'active',
        ]);
        $vendor->forceFill(['balance' => 90000])->save();

        $category = Category::create(['name' => 'Returns', 'slug' => 'returns-'.uniqid()]);
        $book = Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Original Book',
            'slug' => 'original-book-'.uniqid(),
            'author' => 'Author',
            'price' => 100000,
            'stock' => 9,
            'type' => 'physical',
            'status' => 'published',
        ]);
        $warehouse = Warehouse::create([
            'vendor_id' => $vendor->id,
            'name' => 'Main Warehouse',
            'address' => 'Warehouse address',
            'capacity' => 100,
            'status' => 'active',
        ]);
        $stock = WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'book_id' => $book->id,
            'quantity' => 9,
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $buyer->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 100000,
            'status' => 'completed',
            'payment_status' => 'paid',
            'refund_status' => 'none',
            'payment_method' => 'cod',
            'shipping_address' => 'Original address',
            'phone' => '0900000000',
            'shipping_status' => 'delivered',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'quantity' => 1,
            'price' => 100000,
            'product_taxonomy_snapshot' => ['format' => 'physical', 'provenance' => 'used_resale'],
            'return_policy_snapshot' => ['is_returnable' => true, 'return_window_days' => 7],
        ]);
        $session = CheckoutSession::create([
            'user_id' => $buyer->id,
            'currency' => 'VND',
            'subtotal_amount' => 100000,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 100000,
        ]);
        CheckoutSessionOrder::create([
            'checkout_session_id' => $session->id,
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'subtotal_amount' => 100000,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'commission_rate' => 10,
            'commission_amount' => 10000,
            'total_amount' => 100000,
        ]);
        $reservation = InventoryReservation::create([
            'checkout_session_id' => $session->id,
            'order_item_id' => $item->id,
            'book_id' => $book->id,
            'quantity' => 1,
            'status' => InventoryReservationStatus::COMMITTED,
            'operation_key' => 'reservation-'.$order->id,
            'committed_at' => now(),
        ]);
        InventoryReservationAllocation::create([
            'inventory_reservation_id' => $reservation->id,
            'warehouse_stock_id' => $stock->id,
            'quantity' => 1,
        ]);
        InvoiceSnapshot::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-'.$order->order_code,
            'currency' => 'VND',
            'issued_at' => now(),
            'buyer_snapshot' => [
                'name' => 'Original Buyer',
                'email' => $buyer->email,
                'phone' => '0900000000',
                'shipping_address' => 'Original address',
            ],
            'seller_snapshot' => [
                'shop_name' => 'Original Shop',
                'contact_name' => 'Original Seller',
            ],
            'line_items' => [[
                'order_item_id' => $item->id,
                'book_id' => $book->id,
                'title' => 'Original Book',
                'type' => 'physical',
                'provenance' => 'used_resale',
                'return_policy_snapshot' => ['is_returnable' => true, 'return_window_days' => 7],
                'quantity' => 1,
                'unit_price' => 100000,
                'line_total' => 100000,
            ]],
            'subtotal_amount' => 100000,
            'coupon_discount_amount' => 0,
            'membership_discount_amount' => 0,
            'shipping_fee_amount' => 0,
            'service_fee_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total_amount' => 100000,
        ]);
        OrderTransitionOperation::create([
            'order_id' => $order->id,
            'operation_key' => 'delivered-'.$order->id,
            'actor_type' => 'vendor',
            'actor_id' => $vendorUser->id,
            'transition_kind' => 'shipping',
            'from_state' => 'delivering',
            'to_state' => 'delivered',
            'occurred_at' => now(),
        ]);
        LoyaltyPointLedger::create([
            'user_id' => $buyer->id,
            'order_id' => $order->id,
            'operation_key' => 'loyalty-'.$order->id,
            'type' => 'order_completed',
            'points' => 10,
        ]);
        VendorEarningLedger::create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'operation_key' => 'earning-'.$order->id,
            'gross_amount' => 100000,
            'commission_amount' => 10000,
            'net_amount' => 90000,
            'currency' => 'VND',
        ]);

        return [$buyer, $vendorUser, $vendor, $order->fresh('orderItems.book'), $item, $stock];
    }
}

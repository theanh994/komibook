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
use App\Models\InventoryReservationAllocation;
use App\Models\InvoiceSnapshot;
use App\Models\LoyaltyPointLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTransitionOperation;
use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEarningLedger;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Refunds\RefundGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_cod_return_refund_restores_inventory_and_reverses_financial_effects_once(): void
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
            'idempotency_key' => "return-{$returnId}-refund",
            'evidence' => 'BANK-TRANSFER-001',
        ])->assertOk()->assertJsonPath('data.status', 'refunded');

        $this->assertSame('refunded', $order->fresh()->refund_status);
        $this->assertSame(0, $vendor->fresh()->balance);
        $this->assertSame(0, $buyer->fresh()->points);
        $this->assertDatabaseCount('inventory_return_restorations', 1);
        $this->assertDatabaseCount('vendor_earning_reversals', 1);
        $this->assertDatabaseCount('loyalty_point_reversals', 1);
        $this->assertDatabaseHas('vendor_financial_holds', [
            'return_request_id' => $returnId,
            'status' => 'consumed',
        ]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "return-{$returnId}-refund",
            'evidence' => 'BANK-TRANSFER-001',
        ])->assertOk()->assertJsonPath('data.status', 'refunded');

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertDatabaseCount('inventory_return_restorations', 1);
        $this->assertDatabaseCount('vendor_earning_reversals', 1);
        $this->assertDatabaseCount('loyalty_point_reversals', 1);
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
        PaymentTransaction::create([
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
        ])->assertOk()->assertJsonPath('data.status', 'refund_failed');
        $this->assertSame(1, $gateway->calls);
        $this->assertDatabaseCount('refund_transaction_attempts', 1);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "online-{$returnId}-attempt-1",
        ])->assertOk()->assertJsonPath('data.status', 'refund_failed');
        $this->assertSame(1, $gateway->calls);

        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "online-{$returnId}-attempt-2",
        ])->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertSame(2, $gateway->calls);
        $this->assertDatabaseCount('refund_transaction_attempts', 2);
        $this->assertDatabaseHas('refund_transactions', [
            'return_request_id' => $returnId,
            'status' => 'refunded',
            'provider_reference' => 'VNPAY-REFUND-001',
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

    public function test_pending_vnpay_refund_is_finalized_only_after_signed_status_reconciliation(): void
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
        $this->postJson("/api/vendor/returns/{$returnId}/refund", [
            'idempotency_key' => "pending-{$returnId}-refund",
        ])->assertOk()->assertJsonPath('data.status', 'refund_processing');
        $this->assertSame(1, $gateway->refundCalls);
        $this->assertDatabaseMissing('vendor_earning_reversals', ['return_request_id' => $returnId]);

        $this->postJson("/api/vendor/returns/{$returnId}/refund/reconcile", [
            'idempotency_key' => "pending-{$returnId}-query",
        ])->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertSame(1, $gateway->queryCalls);
        $this->assertDatabaseCount('refund_transaction_attempts', 2);
        $this->assertDatabaseHas('refund_transactions', [
            'return_request_id' => $returnId,
            'provider_reference' => 'VNPAY-REFUND-FINAL-001',
            'status' => 'refunded',
        ]);
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

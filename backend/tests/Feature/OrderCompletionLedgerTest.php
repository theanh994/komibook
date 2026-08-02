<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\LoyaltyPointLedger;
use App\Models\Order;
use App\Models\OrderTransitionOperation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEarningLedger;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use App\Services\OrderFulfillmentService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class OrderCompletionLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private CheckoutService $checkoutService;

    private OrderFulfillmentService $fulfillmentService;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('ping')->andThrow(new Exception('Redis disabled in test environment'));
        File::shouldReceive('exists')->byDefault()->andReturn(false);

        $this->category = Category::create([
            'name' => 'Completion Category',
            'slug' => 'completion-category-'.uniqid(),
        ]);

        $this->checkoutService = new CheckoutService;
        $this->fulfillmentService = new OrderFulfillmentService;
    }

    private function createVendor(int $balance = 0): Vendor
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $uniqueId = uniqid();

        return Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Store '.$uniqueId,
            'slug' => 'store-'.$uniqueId,
            'status' => 'active',
            'balance' => $balance,
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

    private function deliverAndConfirm(
        Order $order,
        Vendor $vendor,
        string $carrier = 'GHTK',
        string $trackingCode = 'TRK',
        ?string $completionKey = null
    ): Order {
        if ($order->fresh()->shipping_status === 'delivering') {
            $this->fulfillmentService->updateShippingStatus(
                $order->id,
                'awaiting_customer_confirmation',
                $carrier,
                $trackingCode,
                'vendor',
                $vendor->user_id
            );
        }

        return $this->fulfillmentService->confirmReceivedByCustomer(
            $order->id,
            (int) $order->user_id,
            $completionKey
        );
    }

    /**
     * 1. BOLA Protection: Single status update endpoint.
     */
    public function test_vendor_cannot_update_another_vendors_order_status_single(): void
    {
        Queue::fake();

        $vendorAUser = User::factory()->create(['role' => 'vendor']);
        $vendorA = Vendor::create(['user_id' => $vendorAUser->id, 'shop_name' => 'Store A', 'slug' => 'store-a', 'status' => 'active']);

        $vendorB = $this->createVendor();
        $bookB = $this->createBook($vendorB, 'physical', 100000, 10);

        $ordersB = $this->checkoutService->processCheckout(
            [['book_id' => $bookB->id, 'quantity' => 1]],
            ['shipping_address' => 'BOLA St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $orderB = $ordersB[0];
        $initialStatus = $orderB->status;

        Sanctum::actingAs($vendorAUser);

        $response = $this->patchJson("/api/vendor/orders/{$orderB->id}/status", [
            'status' => 'shipped',
        ]);

        $this->assertContains($response->status(), [404, 422]);
        $this->assertEquals($initialStatus, $orderB->fresh()->status);
        $this->assertEquals(0, OrderTransitionOperation::count());
    }

    public function test_vendor_handoff_response_keeps_order_details_and_assigns_demo_carrier(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $order = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Demo Shipping St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        )[0];
        $order->forceFill(['status' => 'processing'])->save();

        Sanctum::actingAs($vendor->user);

        $response = $this->patchJson("/api/vendor/orders/{$order->id}/status", [
            'status' => 'shipped',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.shipping_carrier', 'KomiBook Express (mô phỏng)')
            ->assertJsonPath('data.shipping_tracking_code', 'KBX-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT))
            ->assertJsonCount(1, 'data.items');
    }

    public function test_only_order_customer_can_confirm_receipt_and_complete_physical_order(): void
    {
        Queue::fake();

        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $order = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Customer Confirm St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $customer->id
        )[0];
        $order->forceFill(['status' => 'processing'])->save();

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', null, null, 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', null, null, 'vendor', $vendor->user_id);

        Sanctum::actingAs($vendor->user);
        $this->patchJson("/api/vendor/orders/{$order->id}/shipping", [
            'shipping_status' => 'delivered',
        ])->assertUnprocessable();
        $this->assertSame('delivering', $order->fresh()->shipping_status);

        $this->fulfillmentService->updateShippingStatus($order->id, 'awaiting_customer_confirmation', null, null, 'vendor', $vendor->user_id);

        Sanctum::actingAs($otherCustomer);
        $this->postJson("/api/my-orders/{$order->id}/confirm-received", [
            'idempotency_key' => 'wrong-customer-confirm',
        ])->assertForbidden();
        $this->assertSame('shipped', $order->fresh()->status);

        Sanctum::actingAs($customer);
        $this->postJson("/api/my-orders/{$order->id}/confirm-received", [
            'idempotency_key' => 'right-customer-confirm',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.shipping_status', 'delivered')
            ->assertJsonPath('data.can_confirm_receipt', false);

        $this->assertDatabaseHas('order_transition_operations', [
            'order_id' => $order->id,
            'operation_key' => 'right-customer-confirm',
            'actor_type' => 'customer',
            'actor_id' => $customer->id,
            'from_state' => 'awaiting_customer_confirmation',
            'to_state' => 'delivered',
        ]);
    }

    /**
     * 2. BOLA Protection: Bulk status update endpoint.
     */
    public function test_vendor_cannot_update_another_vendors_order_status_bulk(): void
    {
        Queue::fake();

        $vendorAUser = User::factory()->create(['role' => 'vendor']);
        $vendorA = Vendor::create(['user_id' => $vendorAUser->id, 'shop_name' => 'Store A', 'slug' => 'store-a', 'status' => 'active']);
        $bookA = $this->createBook($vendorA, 'physical', 100000, 10);

        $vendorB = $this->createVendor();
        $bookB = $this->createBook($vendorB, 'physical', 100000, 10);

        $ordersA = $this->checkoutService->processCheckout(
            [['book_id' => $bookA->id, 'quantity' => 1]],
            ['shipping_address' => 'Bulk A', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $statusABefore = $ordersA[0]->status;

        $ordersB = $this->checkoutService->processCheckout(
            [['book_id' => $bookB->id, 'quantity' => 1]],
            ['shipping_address' => 'Bulk B', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $statusBBefore = $ordersB[0]->status;

        Sanctum::actingAs($vendorAUser);

        $response = $this->patchJson('/api/vendor/orders/bulk-status', [
            'order_ids' => [$ordersA[0]->id, $ordersB[0]->id],
            'status' => 'shipped',
        ]);

        $response->assertStatus(422);
        $this->assertEquals($statusABefore, $ordersA[0]->fresh()->status);
        $this->assertEquals($statusBBefore, $ordersB[0]->fresh()->status);
        $this->assertEquals(0, OrderTransitionOperation::count());
    }

    /**
     * 3. BOLA Protection: Shipping status endpoint.
     */
    public function test_vendor_cannot_update_another_vendors_order_shipping(): void
    {
        Queue::fake();

        $vendorAUser = User::factory()->create(['role' => 'vendor']);
        $vendorA = Vendor::create(['user_id' => $vendorAUser->id, 'shop_name' => 'Store A', 'slug' => 'store-a', 'status' => 'active']);

        $vendorB = $this->createVendor();
        $bookB = $this->createBook($vendorB, 'physical', 100000, 10);

        $ordersB = $this->checkoutService->processCheckout(
            [['book_id' => $bookB->id, 'quantity' => 1]],
            ['shipping_address' => 'Ship BOLA', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $orderB = $ordersB[0];
        $orderB->status = 'processing';
        $orderB->save();

        $this->fulfillmentService->updateOrderStatusByVendor($orderB->id, 'shipped', 'vendor', $vendorB->user_id);
        $initialShippingStatus = $orderB->fresh()->shipping_status;

        Sanctum::actingAs($vendorAUser);

        $response = $this->patchJson("/api/vendor/orders/{$orderB->id}/shipping", [
            'shipping_status' => 'picked_up',
        ]);

        $response->assertStatus(422);
        $this->assertEquals($initialShippingStatus, $orderB->fresh()->shipping_status);
        $this->assertEquals(1, OrderTransitionOperation::count());
    }

    /**
     * 4. Unsupported actor type is rejected.
     */
    public function test_unsupported_actor_type_is_rejected(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Actor St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        try {
            $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'hack_actor', 123);
            $this->fail('Expected LogicException on unsupported actor type');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Unsupported actor type', $e->getMessage());
        }

        $this->assertEquals('processing', $order->fresh()->status);
    }

    /**
     * 5. `confirmed -> shipped` is rejected without mutation.
     */
    public function test_confirmed_to_shipped_transition_is_rejected(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Confirmed St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $this->assertEquals('confirmed', $order->status);

        try {
            $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
            $this->fail('Expected LogicException on confirmed -> shipped transition');
        } catch (LogicException $e) {
            $this->assertStringContainsString("Cannot transition order ID {$order->id} from status 'confirmed' to 'shipped'", $e->getMessage());
        }

        $this->assertEquals('confirmed', $order->fresh()->status);
        $this->assertEquals(0, OrderTransitionOperation::count());
    }

    /**
     * 6. Real Fulfillment Graph Completion with Immutable Snapshot Earning.
     */
    public function test_real_fulfillment_graph_calculates_net_earning_from_snapshot(): void
    {
        Queue::fake();

        $user = User::factory()->create(['points' => 0]);
        $vendor = $this->createVendor(0);
        $book = $this->createBook($vendor, 'physical', 200000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Graph St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        // Step 1: processing -> shipped
        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->assertEquals('shipped', $order->fresh()->status);
        $this->assertEquals('pending_pickup', $order->fresh()->shipping_status);
        $this->assertEquals('KomiBook Express (mô phỏng)', $order->fresh()->shipping_carrier);
        $this->assertEquals('KBX-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT), $order->fresh()->shipping_tracking_code);

        // Step 2: pending_pickup -> picked_up
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);
        $this->assertEquals('picked_up', $order->fresh()->shipping_status);

        // Step 3: picked_up -> delivering
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);
        $this->assertEquals('delivering', $order->fresh()->shipping_status);

        // Step 4: carrier marks delivered, then customer confirms receipt.
        $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK1');

        $order->refresh();
        $user->refresh();
        $vendor->refresh();

        $this->assertEquals('completed', $order->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('delivered', $order->shipping_status);

        $this->assertDatabaseHas('vendor_earning_ledgers', [
            'order_id' => $order->id,
            'gross_amount' => 200000,
            'commission_amount' => 20000,
            'net_amount' => 180000,
        ]);

        $this->assertEquals(180000, $vendor->balance);
        $this->assertEquals(20, $user->points);
    }

    /**
     * 7. Shipping transition rules and forbidden jumps.
     */
    public function test_forbidden_shipping_jumps_fail_closed(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Jump St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);

        try {
            $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK');
            $this->fail('Expected LogicException on direct jump to delivered');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Chỉ có thể xác nhận sau khi đơn vị vận chuyển đã giao hàng tới khách', $e->getMessage());
        }

        $this->assertEquals('pending_pickup', $order->fresh()->shipping_status);
    }

    /**
     * 8. Terminal failed shipping status.
     */
    public function test_failed_shipping_is_terminal_and_preserves_stock(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Fail Terminal St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);

        $stockBefore = $stock->fresh()->quantity;

        $this->fulfillmentService->updateShippingStatus($order->id, 'failed', 'GHTK', 'TRK', 'vendor', $vendor->user_id);

        $this->assertEquals('shipped', $order->fresh()->status);
        $this->assertEquals('failed', $order->fresh()->shipping_status);
        $this->assertEquals($stockBefore, $stock->fresh()->quantity);

        try {
            $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK', 'vendor', $vendor->user_id);
            $this->fail('Expected LogicException on transition from failed');
        } catch (LogicException $e) {
            $this->assertStringContainsString('failed', $e->getMessage());
        }
    }

    /**
     * 9. Physical/mixed order cannot use ebook completion.
     */
    public function test_physical_or_mixed_order_cannot_use_ebook_completion(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Ebook Fail St', 'phone' => '0901234567', 'payment_method' => 'online'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $order->payment_status = 'paid';
        $order->status = 'processing';
        $order->save();

        try {
            $this->fulfillmentService->completeEbookOrder($order->id, 'system', null);
            $this->fail('Expected LogicException on physical order ebook completion');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Physical or mixed order', $e->getMessage());
        }
    }

    /**
     * 10. Missing checkout session customer is rejected.
     */
    public function test_missing_checkout_session_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'No Session St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK', 'vendor', $vendor->user_id);

        $otherUser = User::factory()->create();
        $snap = CheckoutSessionOrder::where('order_id', $order->id)->first();
        $session = CheckoutSession::where('id', $snap->checkout_session_id)->first();
        $session->user_id = $otherUser->id;
        $session->save();

        try {
            $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK');
            $this->fail('Expected LogicException on mismatched checkout session customer');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Inconsistent customer in snapshot', $e->getMessage());
        }
    }

    /**
     * 11. Non-VND checkout session is rejected.
     */
    public function test_non_vnd_checkout_session_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'USD St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK', 'vendor', $vendor->user_id);

        $snap = CheckoutSessionOrder::where('order_id', $order->id)->first();
        $session = CheckoutSession::where('id', $snap->checkout_session_id)->first();
        $session->currency = 'USD';
        $session->save();

        try {
            $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK');
            $this->fail('Expected LogicException on USD checkout session');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Only VND supported', $e->getMessage());
        }
    }

    /**
     * 12. Null or mismatched snapshot vendor is rejected.
     */
    public function test_null_or_mismatched_snapshot_vendor_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Bad Snap Vendor St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK', 'vendor', $vendor->user_id);

        $otherVendor = $this->createVendor();
        $order->vendor_id = $otherVendor->id;
        $order->save();

        try {
            $this->deliverAndConfirm($order, $otherVendor, 'GHTK', 'TRK');
            $this->fail('Expected LogicException on mismatched snapshot vendor_id');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Inconsistent vendor in snapshot', $e->getMessage());
        }
    }

    /**
     * 13. Invalid commission amount in snapshot fails closed.
     */
    public function test_invalid_commission_amount_in_snapshot_fails_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Bad Commission St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK', 'vendor', $vendor->user_id);

        $snap = CheckoutSessionOrder::where('order_id', $order->id)->first();
        $snap->commission_amount = 200000;
        $snap->save();

        try {
            $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK');
            $this->fail('Expected LogicException on invalid commission amount');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Invalid snapshot commission', $e->getMessage());
        }
    }

    /**
     * 14. 64-bit Unsigned Monetary Ledger Support (> 2^31-1).
     */
    public function test_64_bit_unsigned_monetary_ledger_support(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor(0);
        $book = $this->createBook($vendor, 'physical', 3000000000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => '64bit St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $snap = CheckoutSessionOrder::where('order_id', $order->id)->first();
        $snap->total_amount = 3000000000;
        $snap->commission_amount = 300000000;
        $snap->save();

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK', 'vendor', $vendor->user_id);

        $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK');

        $this->assertDatabaseHas('vendor_earning_ledgers', [
            'order_id' => $order->id,
            'gross_amount' => 3000000000,
            'commission_amount' => 300000000,
            'net_amount' => 2700000000,
        ]);

        $vendor->refresh();
        $this->assertEquals(2700000000, $vendor->balance);
    }

    /**
     * 15. Conflicting key reuse with differing payload or from_state is rejected.
     */
    public function test_conflicting_key_reuse_with_differing_payload_or_from_state_is_rejected(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Key Reuse St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $opKey = 'unique-vendor-op-key';

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id, $opKey);

        // Reuse opKey on another order
        $orders2 = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'St2', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order2 = $orders2[0];
        $order2->status = 'processing';
        $order2->save();

        try {
            $this->fulfillmentService->updateOrderStatusByVendor($order2->id, 'shipped', 'vendor', $vendor->user_id, $opKey);
            $this->fail('Expected LogicException on conflicting key reuse');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Conflicting operation key', $e->getMessage());
        }
    }

    /**
     * 16. Same-state retry unbacked by operation fails closed.
     */
    public function test_same_state_retry_unbacked_by_operation_fails_closed(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Unbacked St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];

        $order->status = 'shipped';
        $order->save();

        try {
            $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
            $this->fail('Expected LogicException on unbacked same-state transition');
        } catch (LogicException $e) {
            $this->assertStringContainsString('not backed by a valid transition operation', $e->getMessage());
        }
    }

    /**
     * 17. Completion retry with corrupted ledger state or projection fails closed.
     */
    public function test_completion_retry_with_corrupted_ledger_state_or_projection_fails_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Corrupt Ledger St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $opKey = 'completion-op-key';

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK', 'vendor', $vendor->user_id);

        OrderTransitionOperation::create([
            'order_id' => $order->id,
            'operation_key' => $opKey,
            'actor_type' => 'customer',
            'actor_id' => $order->user_id,
            'transition_kind' => 'shipping',
            'from_state' => 'awaiting_customer_confirmation',
            'to_state' => 'delivered',
            'metadata' => [
                'order_status' => 'completed',
                'payment_status' => 'paid',
                'shipping_carrier' => 'GHTK',
                'shipping_tracking_code' => 'TRK',
                'gross_amount' => 100000,
                'commission_amount' => 10000,
                'net_amount' => 90000,
                'points' => 10,
            ],
            'occurred_at' => now(),
        ]);

        try {
            $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK', $opKey);
            $this->fail('Expected LogicException on corrupted ledger state completion');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Corrupted vendor earning ledger state', $e->getMessage());
        }
    }

    /**
     * 18. Valid identical retries succeed idempotently without duplicate ledgers or projection increments.
     */
    public function test_valid_identical_retries_succeed_idempotently_without_duplicates(): void
    {
        Queue::fake();

        $user = User::factory()->create(['points' => 0]);
        $vendor = $this->createVendor(0);
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Retry St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $opKey = 'unique-delivery-completion-key';

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);

        // First call
        $completed1 = $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK1', $opKey);

        $balance1 = $vendor->fresh()->balance;
        $points1 = $user->fresh()->points;
        $earningLedgers1 = VendorEarningLedger::where('order_id', $order->id)->count();
        $pointLedgers1 = LoyaltyPointLedger::where('order_id', $order->id)->count();

        // Identical retry
        $completed2 = $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK1', $opKey);

        $this->assertEquals($completed1->id, $completed2->id);
        $this->assertEquals($balance1, $vendor->fresh()->balance);
        $this->assertEquals($points1, $user->fresh()->points);
        $this->assertEquals(1, $earningLedgers1);
        $this->assertEquals(1, VendorEarningLedger::where('order_id', $order->id)->count());
        $this->assertEquals(1, $pointLedgers1);
        $this->assertEquals(1, LoyaltyPointLedger::where('order_id', $order->id)->count());
    }

    /**
     * 19. Rollback injection leaves no partial records.
     */
    public function test_rollback_injection_leaves_no_partial_records(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor(0);
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Rollback St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK', 'vendor', $vendor->user_id);

        DB::statement("
            CREATE TRIGGER fail_vendor_earning_insert
            BEFORE INSERT ON vendor_earning_ledgers
            BEGIN
                SELECT RAISE(ABORT, 'Simulated rollback injection');
            END;
        ");

        try {
            $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRKR');
            $this->fail('Expected Exception on injected DB failure');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Simulated rollback injection', $e->getMessage());
        }

        $this->assertEquals('shipped', $order->fresh()->status);
        $this->assertEquals(0, LoyaltyPointLedger::count());
        $this->assertEquals(0, VendorEarningLedger::count());
        $this->assertEquals(4, OrderTransitionOperation::count());
        $this->assertEquals(0, $vendor->fresh()->balance);
    }

    /**
     * 20. Migration SQLite up/down isolated.
     */
    public function test_migration_up_and_down_isolated(): void
    {
        $migration = include database_path('migrations/2026_07_25_130000_create_order_completion_ledgers.php');

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('order_transition_operations'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('loyalty_point_ledgers'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('vendor_earning_ledgers'));

        $migration->down();

        $this->assertFalse(DB::getSchemaBuilder()->hasTable('vendor_earning_ledgers'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('loyalty_point_ledgers'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('order_transition_operations'));

        $migration->up();

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('order_transition_operations'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('loyalty_point_ledgers'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('vendor_earning_ledgers'));
    }

    /**
     * 21. Direct model save to completed has no financial or points side effect.
     */
    public function test_direct_model_save_to_completed_has_no_financial_or_points_side_effect(): void
    {
        Queue::fake();

        $user = User::factory()->create(['points' => 0]);
        $vendor = $this->createVendor(0);

        $order = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'order_code' => 'DIRECT_'.uniqid(),
            'total_amount' => 500000,
            'shipping_fee' => 0,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
            'shipping_address' => 'Direct St',
            'phone' => '0901234567',
        ]);

        $order->status = 'completed';
        $order->save();

        $this->assertEquals(0, $vendor->fresh()->balance);
        $this->assertEquals(0, $user->fresh()->points);
        $this->assertEquals(0, LoyaltyPointLedger::count());
        $this->assertEquals(0, VendorEarningLedger::count());
    }

    /**
     * 22. System actor cannot call vendor order status or shipping update methods.
     */
    public function test_system_actor_cannot_call_vendor_methods(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Sys Block St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        try {
            $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'system', null);
            $this->fail('Expected LogicException when system actor calls updateOrderStatusByVendor');
        } catch (LogicException $e) {
            $this->assertStringContainsString("Unsupported actor type 'system'", $e->getMessage());
        }

        try {
            $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'system', null);
            $this->fail('Expected LogicException when system actor calls updateShippingStatus');
        } catch (LogicException $e) {
            $this->assertStringContainsString("Unsupported actor type 'system'", $e->getMessage());
        }
    }

    /**
     * 23. Complete Ebook Order accepts system/null or vendor/id actor.
     */
    public function test_complete_ebook_order_accepts_system_and_vendor_actors(): void
    {
        Queue::fake();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $vendor = $this->createVendor();
        $book1 = $this->createBook($vendor, 'ebook', 100000, 10);
        $book2 = $this->createBook($vendor, 'ebook', 100000, 10);

        // Test system/null actor
        $orders1 = $this->checkoutService->processCheckout(
            [['book_id' => $book1->id, 'quantity' => 1]],
            ['shipping_address' => 'Ebook Sys', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user1->id
        );
        $order1 = $orders1[0];
        $order1->payment_status = 'paid';
        $order1->status = 'processing';
        $order1->save();

        $completed1 = $this->fulfillmentService->completeEbookOrder($order1->id, 'system', null);
        $this->assertEquals('completed', $completed1->status);

        // Test vendor/id actor
        $orders2 = $this->checkoutService->processCheckout(
            [['book_id' => $book2->id, 'quantity' => 1]],
            ['shipping_address' => 'Ebook Vendor', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user2->id
        );
        $order2 = $orders2[0];
        $order2->payment_status = 'paid';
        $order2->status = 'processing';
        $order2->save();

        $completed2 = $this->fulfillmentService->completeEbookOrder($order2->id, 'vendor', $vendor->user_id);
        $this->assertEquals('completed', $completed2->status);
    }

    /**
     * 24. Physical delivery completion canonical metadata includes carrier and tracking code; retry with mismatch fails.
     */
    public function test_delivery_completion_canonical_metadata_includes_carrier_tracking_and_mismatch_fails(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Meta Delivery St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $opKey = 'delivery-meta-canonical-key';

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);

        $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK1', $opKey);

        $op = OrderTransitionOperation::where('operation_key', $opKey)->first();
        $this->assertNotNull($op);
        $this->assertEquals('GHTK', $op->metadata['shipping_carrier']);
        $this->assertEquals('TRK1', $op->metadata['shipping_tracking_code']);

        // Retry same opKey after carrier metadata was changed fails closed.
        $order->forceFill(['shipping_carrier' => 'ViettelPost'])->save();
        try {
            $this->fulfillmentService->confirmReceivedByCustomer($order->id, (int) $order->user_id, $opKey);
            $this->fail('Expected LogicException on retrying delivery completion with different carrier');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Conflicting operation key', $e->getMessage());
        }
    }

    /**
     * 25. Idempotent retries for shipping destinations and customer receipt confirmation.
     */
    public function test_idempotent_retries_for_all_four_shipping_destinations(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        // Destination 1: picked_up
        $orders1 = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Dest 1', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $o1 = $orders1[0];
        $o1->status = 'processing';
        $o1->save();

        $this->fulfillmentService->updateOrderStatusByVendor($o1->id, 'shipped', 'vendor', $vendor->user_id);
        $r1a = $this->fulfillmentService->updateShippingStatus($o1->id, 'picked_up', 'GHTK', 'T1', 'vendor', $vendor->user_id);
        $opCount1 = OrderTransitionOperation::where('order_id', $o1->id)->count();
        $r1b = $this->fulfillmentService->updateShippingStatus($o1->id, 'picked_up', 'GHTK', 'T1', 'vendor', $vendor->user_id);
        $this->assertEquals($r1a->id, $r1b->id);
        $this->assertEquals($opCount1, OrderTransitionOperation::where('order_id', $o1->id)->count());

        // Destination 2: delivering
        $r2a = $this->fulfillmentService->updateShippingStatus($o1->id, 'delivering', 'GHTK', 'T1', 'vendor', $vendor->user_id);
        $opCount2 = OrderTransitionOperation::where('order_id', $o1->id)->count();
        $r2b = $this->fulfillmentService->updateShippingStatus($o1->id, 'delivering', 'GHTK', 'T1', 'vendor', $vendor->user_id);
        $this->assertEquals($r2a->id, $r2b->id);
        $this->assertEquals($opCount2, OrderTransitionOperation::where('order_id', $o1->id)->count());

        // Destination 3: failed
        $orders2 = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Dest 3', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $o2 = $orders2[0];
        $o2->status = 'processing';
        $o2->save();

        $this->fulfillmentService->updateOrderStatusByVendor($o2->id, 'shipped', 'vendor', $vendor->user_id);
        $r3a = $this->fulfillmentService->updateShippingStatus($o2->id, 'failed', 'GHTK', 'T2', 'vendor', $vendor->user_id);
        $opCount3 = OrderTransitionOperation::where('order_id', $o2->id)->count();
        $r3b = $this->fulfillmentService->updateShippingStatus($o2->id, 'failed', 'GHTK', 'T2', 'vendor', $vendor->user_id);
        $this->assertEquals($r3a->id, $r3b->id);
        $this->assertEquals('failed', $r3b->shipping_status);
        $this->assertEquals($opCount3, OrderTransitionOperation::where('order_id', $o2->id)->count());

        // Destination 4: carrier delivered to customer; Destination 5: customer confirmed receipt.
        $r4a = $this->deliverAndConfirm($o1, $vendor, 'GHTK', 'T1');
        $opCount4 = OrderTransitionOperation::where('order_id', $o1->id)->count();
        $r4b = $this->deliverAndConfirm($o1, $vendor, 'GHTK', 'T1');
        $this->assertEquals($r4a->id, $r4b->id);
        $this->assertEquals('completed', $r4b->status);
        $this->assertEquals($opCount4, OrderTransitionOperation::where('order_id', $o1->id)->count());
    }

    /**
     * 26. Mutating stored operation to invalid from_state causes retry to fail closed.
     */
    public function test_mutating_stored_operation_from_state_causes_retry_to_fail_closed(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Mutate St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $opKey = 'op-mutate-from-state-test';

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'vendor', $vendor->user_id, $opKey);

        // Mutate stored operation from_state to invalid value
        $op = OrderTransitionOperation::where('operation_key', $opKey)->first();
        $op->from_state = 'invalid_state';
        $op->save();

        try {
            $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK', 'vendor', $vendor->user_id, $opKey);
            $this->fail('Expected LogicException when stored operation contains invalid graph edge');
        } catch (LogicException $e) {
            $this->assertStringContainsString('contains an invalid transition edge', $e->getMessage());
        }
    }

    /**
     * 27. Corrupted cumulative vendor balance across multiple ledgers fails closed on retry.
     */
    public function test_corrupted_cumulative_vendor_balance_fails_closed_on_retry(): void
    {
        Queue::fake();

        $user = User::factory()->create(['points' => 0]);
        $vendor = $this->createVendor(0);
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        // Order 1
        $orders1 = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Cum V1', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $o1 = $orders1[0];
        $o1->status = 'processing';
        $o1->save();

        $this->fulfillmentService->updateOrderStatusByVendor($o1->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($o1->id, 'picked_up', 'GHTK', 'T1', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($o1->id, 'delivering', 'GHTK', 'T1', 'vendor', $vendor->user_id);
        $this->deliverAndConfirm($o1, $vendor, 'GHTK', 'T1', 'op-o1');

        // Order 2
        $orders2 = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Cum V2', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $o2 = $orders2[0];
        $o2->status = 'processing';
        $o2->save();

        $this->fulfillmentService->updateOrderStatusByVendor($o2->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($o2->id, 'picked_up', 'GHTK', 'T2', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($o2->id, 'delivering', 'GHTK', 'T2', 'vendor', $vendor->user_id);
        $this->deliverAndConfirm($o2, $vendor, 'GHTK', 'T2', 'op-o2');

        $vendorBalanceCumulative = $vendor->fresh()->balance;
        $this->assertEquals(180000, $vendorBalanceCumulative);

        // Corrupt vendor balance to 150000 (less than cumulative 180000, but higher than single 90000)
        $vendor->balance = 150000;
        $vendor->save();

        try {
            $this->fulfillmentService->confirmReceivedByCustomer($o2->id, (int) $o2->user_id, 'op-o2');
            $this->fail('Expected LogicException on cumulative vendor balance corruption');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Vendor balance projection is below cumulative durable ledger contribution', $e->getMessage());
        }
    }

    /**
     * 28. Corrupted cumulative user points across multiple ledgers fails closed on retry.
     */
    public function test_corrupted_cumulative_user_points_fails_closed_on_retry(): void
    {
        Queue::fake();

        $user = User::factory()->create(['points' => 0]);
        $vendor = $this->createVendor(0);
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        // Order 1
        $orders1 = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Cum P1', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $o1 = $orders1[0];
        $o1->status = 'processing';
        $o1->save();

        $this->fulfillmentService->updateOrderStatusByVendor($o1->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($o1->id, 'picked_up', 'GHTK', 'T1', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($o1->id, 'delivering', 'GHTK', 'T1', 'vendor', $vendor->user_id);
        $this->deliverAndConfirm($o1, $vendor, 'GHTK', 'T1', 'op-p1');

        // Order 2
        $orders2 = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Cum P2', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $o2 = $orders2[0];
        $o2->status = 'processing';
        $o2->save();

        $this->fulfillmentService->updateOrderStatusByVendor($o2->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($o2->id, 'picked_up', 'GHTK', 'T2', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($o2->id, 'delivering', 'GHTK', 'T2', 'vendor', $vendor->user_id);
        $this->deliverAndConfirm($o2, $vendor, 'GHTK', 'T2', 'op-p2');

        $userPointsCumulative = $user->fresh()->points;
        $this->assertEquals(20, $userPointsCumulative);

        // Corrupt user points to 15 (less than cumulative 20, but higher than single 10)
        $user->points = 15;
        $user->save();

        try {
            $this->fulfillmentService->confirmReceivedByCustomer($o2->id, (int) $o2->user_id, 'op-p2');
            $this->fail('Expected LogicException on cumulative user points corruption');
        } catch (LogicException $e) {
            $this->assertStringContainsString('User points projection is below cumulative durable ledger contribution', $e->getMessage());
        }
    }

    /**
     * 29. Ebook completion retry is idempotent without duplicate operations, ledgers, balance, or points.
     */
    public function test_ebook_completion_retry_is_idempotent(): void
    {
        Queue::fake();

        $user = User::factory()->create(['points' => 0]);
        $vendor = $this->createVendor(0);
        $book = $this->createBook($vendor, 'ebook', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Ebook Retry', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );
        $order = $orders[0];
        $order->payment_status = 'paid';
        $order->status = 'processing';
        $order->save();

        $opKey = 'unique-ebook-completion-key';

        // Call 1
        $res1 = $this->fulfillmentService->completeEbookOrder($order->id, 'system', null, $opKey);
        $this->assertEquals('completed', $res1->status);

        $opCountBefore = OrderTransitionOperation::where('order_id', $order->id)->count();
        $earningLedgerCountBefore = VendorEarningLedger::where('order_id', $order->id)->count();
        $pointLedgerCountBefore = LoyaltyPointLedger::where('order_id', $order->id)->count();
        $balanceBefore = $vendor->fresh()->balance;
        $pointsBefore = $user->fresh()->points;

        // Call 2 (Idempotent Retry)
        $res2 = $this->fulfillmentService->completeEbookOrder($order->id, 'system', null, $opKey);

        $this->assertEquals($res1->id, $res2->id);
        $this->assertEquals('completed', $res2->status);
        $this->assertEquals($opCountBefore, OrderTransitionOperation::where('order_id', $order->id)->count());
        $this->assertEquals($earningLedgerCountBefore, VendorEarningLedger::where('order_id', $order->id)->count());
        $this->assertEquals($pointLedgerCountBefore, LoyaltyPointLedger::where('order_id', $order->id)->count());
        $this->assertEquals($balanceBefore, $vendor->fresh()->balance);
        $this->assertEquals($pointsBefore, $user->fresh()->points);
    }

    /**
     * 30. Strict type comparison in metadata fails closed when integer is changed to numeric string.
     */
    public function test_strict_type_comparison_in_metadata_fails_closed_when_integer_is_changed_to_string(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Strict Type St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $opKey = 'strict-type-meta-key';

        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);
        $this->deliverAndConfirm($order, $vendor, 'GHTK', 'TRK1', $opKey);

        // Manually mutate metadata gross_amount integer 100000 to string "100000" in DB
        $op = OrderTransitionOperation::where('operation_key', $opKey)->first();
        $meta = $op->metadata;
        $meta['gross_amount'] = '100000';
        $op->metadata = $meta;
        $op->save();

        try {
            $this->fulfillmentService->confirmReceivedByCustomer($order->id, (int) $order->user_id, $opKey);
            $this->fail('Expected LogicException when metadata integer type is mutated to string');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Conflicting operation key', $e->getMessage());
        }
    }

    /**
     * 31. Late vendor ship retry after shipping has progressed returns idempotently without error.
     */
    public function test_late_vendor_ship_retry_after_shipping_progressed_returns_idempotently(): void
    {
        Queue::fake();

        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Late Ship St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            User::factory()->create()->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $shipOpKey = 'ship-op-late-key';

        // 1. Initial ship call
        $shipOrder1 = $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id, $shipOpKey);
        $this->assertEquals('shipped', $shipOrder1->status);
        $this->assertEquals('pending_pickup', $shipOrder1->shipping_status);

        // 2. Shipping progresses
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);
        $this->fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);

        $opCountBefore = OrderTransitionOperation::where('order_id', $order->id)->count();

        // 3. Retry vendor ship after shipping has progressed to 'delivering'
        $shipOrder2 = $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id, $shipOpKey);

        $this->assertEquals($shipOrder1->id, $shipOrder2->id);
        $this->assertEquals('shipped', $shipOrder2->status);
        $this->assertEquals('delivering', $shipOrder2->shipping_status);
        $this->assertEquals($opCountBefore, OrderTransitionOperation::where('order_id', $order->id)->count());

        // Prove that retrying with a wrong vendor ID still fails closed!
        $otherVendor = $this->createVendor();
        try {
            $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $otherVendor->user_id, $shipOpKey);
            $this->fail('Expected LogicException when retrying ship operation with unauthorized vendor');
        } catch (LogicException $e) {
            $this->assertStringContainsString('is not authorized to manage order ID', $e->getMessage());
        }
    }

    /**
     * 32. Corrupted vendor ship operation metadata fails closed on late retry.
     */
    public function test_corrupted_vendor_ship_operation_metadata_fails_closed_on_late_retry(): void
    {
        Queue::fake();

        $user = User::factory()->create(['points' => 0]);
        $vendor = $this->createVendor(0);
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Corrupt Ship Meta St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();

        $shipOpKey = 'ship-op-corrupt-meta-key';

        // 1. Perform vendor ship operation
        $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id, $shipOpKey);

        // 2. Progress shipping to picked_up
        $this->fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK1', 'vendor', $vendor->user_id);

        $orderStatusBefore = $order->fresh()->status;
        $shippingStatusBefore = $order->fresh()->shipping_status;
        $opCountBefore = OrderTransitionOperation::count();
        $vendorEarningLedgerCountBefore = VendorEarningLedger::count();
        $loyaltyPointLedgerCountBefore = LoyaltyPointLedger::count();
        $vendorBalanceBefore = $vendor->fresh()->balance;
        $userPointsBefore = $user->fresh()->points;

        // 3. Corrupt original order ship operation metadata in DB to another shipping_status (e.g. 'picked_up')
        $op = OrderTransitionOperation::where('operation_key', $shipOpKey)->first();
        $op->metadata = ['shipping_status' => 'picked_up'];
        $op->save();

        // 4. Retry vendor ship operation
        try {
            $this->fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id, $shipOpKey);
            $this->fail('Expected LogicException on retrying corrupted vendor ship operation metadata');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Conflicting operation key', $e->getMessage());
        }

        // 5. Prove retry failed closed without changing order, shipping, operation/ledger counts, vendor balance, or user points
        $this->assertEquals($orderStatusBefore, $order->fresh()->status);
        $this->assertEquals($shippingStatusBefore, $order->fresh()->shipping_status);
        $this->assertEquals($opCountBefore, OrderTransitionOperation::count());
        $this->assertEquals($vendorEarningLedgerCountBefore, VendorEarningLedger::count());
        $this->assertEquals($loyaltyPointLedgerCountBefore, LoyaltyPointLedger::count());
        $this->assertEquals($vendorBalanceBefore, $vendor->fresh()->balance);
        $this->assertEquals($userPointsBefore, $user->fresh()->points);
    }
}

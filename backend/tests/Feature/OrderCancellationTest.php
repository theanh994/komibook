<?php

namespace Tests\Feature;

use App\Enums\InventoryReservationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Http\Resources\OrderResource;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSessionOrder;
use App\Models\DemoWalletAccount;
use App\Models\DemoWalletLedgerEntry;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderTransitionOperation;
use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use App\Services\DemoWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private CheckoutService $checkoutService;

    private DemoWalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checkoutService = app(CheckoutService::class);
        $this->walletService = app(DemoWalletService::class);
    }

    private function createVendor(): Vendor
    {
        $user = User::factory()->create(['role' => 'vendor']);

        return Vendor::create([
            'user_id' => $user->id,
            'shop_name' => 'Cancel Store '.rand(100, 999),
            'slug' => 'cancel-store-'.rand(100, 999),
            'status' => 'active',
        ]);
    }

    private function createBook(Vendor $vendor, int $price = 100000): Book
    {
        $category = Category::firstOrCreate(
            ['slug' => 'sample-category'],
            ['name' => 'Sample Category']
        );

        $book = Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sample Book '.rand(100, 999),
            'slug' => 'sample-book-'.rand(100, 999),
            'author' => 'Test Author',
            'price' => $price,
            'stock_quantity' => 10,
            'type' => 'physical',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $warehouse = Warehouse::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => 'Test Warehouse', 'address' => 'Test Address', 'status' => 'active']
        );

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'book_id' => $book->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        return $book;
    }

    public function test_api_returns_can_cancel_boolean_correctly(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 200000);

        // 1. COD Order (confirmed) -> can_cancel = true
        $codOrders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Cancel St 1', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $codOrder = $codOrders[0];

        $res = $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$codOrder->id}");
        $res->assertOk()
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.cancellation_scope.type', 'single_order')
            ->assertJsonPath('data.cancellation_scope.count', 1)
            ->assertJsonPath('data.cancellation_scope.order_ids.0', $codOrder->id)
            ->assertJsonPath('data.cancellation_scope.order_codes.0', $codOrder->order_code);

        // 2. Shipped Order -> can_cancel = false
        $codOrder->status = 'shipped';
        $codOrder->shipping_status = 'delivering';
        $codOrder->save();

        $res2 = $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$codOrder->id}");
        $res2->assertOk();
        $this->assertFalse($res2->json('data.can_cancel'));
    }

    public function test_buyer_can_cancel_cod_order_and_restores_stock(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 200000);
        $initialStock = $book->stock_quantity;

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Cancel St 2', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        $res = $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$order->id}/cancel");
        $res->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals($initialStock, $book->fresh()->stock_quantity);
    }

    public function test_my_orders_eager_loads_invoice_snapshots_once_for_multiple_orders(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 200000);
        $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Invoice Query St 1', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Invoice Query St 2', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
            $invoiceSnapshotQueries = collect(DB::getQueryLog())
                ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'invoice_snapshots'));
        } finally {
            DB::disableQueryLog();
        }

        $this->assertCount(1, $invoiceSnapshotQueries);
    }

    public function test_buyer_cannot_cancel_paid_online_order_and_leaves_everything_unchanged(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 200000);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Cancel St 3', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );
        $order = $orders[0];
        $order->status = 'confirmed';
        $order->payment_status = 'paid';
        $order->shipping_status = 'pending_pickup';
        $order->save();

        $link = CheckoutSessionOrder::where('order_id', $order->id)->firstOrFail();
        InventoryReservation::where('checkout_session_id', $link->checkout_session_id)
            ->update(['status' => InventoryReservationStatus::COMMITTED]);

        $this->walletService->accountFor($user);
        PaymentTransaction::create([
            'checkout_session_id' => $link->checkout_session_id,
            'provider' => 'vnpay',
            'provider_reference' => 'PAID-CANCEL-'.uniqid(),
            'idempotency_key' => 'paid-cancel-'.uniqid(),
            'amount' => $order->total_amount,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PAID,
            'paid_at' => now(),
        ]);

        $before = $this->cancellationSnapshot([$order->id], $link->checkout_session_id, $user->id);

        $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')
            ->assertOk()
            ->assertJsonPath('data.0.can_cancel', false);
        $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false);

        $res = $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$order->id}/cancel");
        $res->assertStatus(422)
            ->assertJsonPath('status', 'error');
        $this->assertStringContainsString('return/refund workflow', $res->json('message'));
        $this->assertSame($before, $this->cancellationSnapshot([$order->id], $link->checkout_session_id, $user->id));

        $retry = $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$order->id}/cancel");
        $retry->assertStatus(422)
            ->assertJsonPath('status', 'error');
        $this->assertStringContainsString('return/refund workflow', $retry->json('message'));
        $this->assertSame($before, $this->cancellationSnapshot([$order->id], $link->checkout_session_id, $user->id));
    }

    public function test_paid_cod_cancellation_is_rejected_by_the_locked_cod_path_without_mutation(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 200000);
        $order = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Paid COD St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        )[0];
        $order->payment_status = 'paid';
        $order->shipping_status = 'pending_pickup';
        $order->save();

        $link = CheckoutSessionOrder::where('order_id', $order->id)->firstOrFail();
        InventoryReservation::where('checkout_session_id', $link->checkout_session_id)
            ->update(['status' => InventoryReservationStatus::COMMITTED]);
        $this->walletService->accountFor($user);
        PaymentTransaction::create([
            'checkout_session_id' => $link->checkout_session_id,
            'provider' => 'vnpay',
            'provider_reference' => 'PAID-COD-CANCEL-'.uniqid(),
            'idempotency_key' => 'paid-cod-cancel-'.uniqid(),
            'amount' => $order->total_amount,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PAID,
            'paid_at' => now(),
        ]);

        $before = $this->cancellationSnapshot([$order->id], $link->checkout_session_id, $user->id);

        foreach (range(1, 2) as $_) {
            $response = $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$order->id}/cancel");
            $response->assertStatus(422)
                ->assertJsonPath('status', 'error');
            $this->assertStringContainsString('return/refund workflow', $response->json('message'));
            $this->assertSame($before, $this->cancellationSnapshot([$order->id], $link->checkout_session_id, $user->id));
        }
    }

    public function test_draft_and_pending_cod_orders_have_list_detail_and_endpoint_cancellation_parity(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 200000);

        $draftOrder = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Draft St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        )[0];
        $draftOrder->status = 'draft';
        $draftOrder->save();

        $pendingCodOrder = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Pending COD St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        )[0];
        $pendingCodOrder->status = 'pending';
        $pendingCodOrder->save();

        // Draft orders are intentionally excluded from /my-orders, so directly
        // resolve its list resource without changing the established list contract.
        $this->assertFalse((new OrderResource($draftOrder->fresh()))->resolve()['can_cancel']);
        $draftList = $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
        $this->assertNull(collect($draftList->json('data'))->firstWhere('id', $draftOrder->id));
        $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$draftOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false);
        $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$draftOrder->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
        $this->assertSame('draft', $draftOrder->fresh()->status);

        $pendingCodList = $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
        $listedCodOrder = collect($pendingCodList->json('data'))->firstWhere('id', $pendingCodOrder->id);
        $this->assertNotNull($listedCodOrder);
        $this->assertFalse($listedCodOrder['can_cancel']);
        $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$pendingCodOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false);
        $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$pendingCodOrder->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
        $this->assertSame('pending', $pendingCodOrder->fresh()->status);
    }

    public function test_paid_sibling_rejects_unpaid_online_session_cancellation_without_any_mutation(): void
    {
        $user = User::factory()->create();
        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();
        $bookA = $this->createBook($vendorA, 100000);
        $bookB = $this->createBook($vendorB, 200000);

        $orders = $this->checkoutService->processCheckout(
            [
                ['book_id' => $bookA->id, 'quantity' => 1],
                ['book_id' => $bookB->id, 'quantity' => 1],
            ],
            ['shipping_address' => 'Mixed Session St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );
        $unpaidOrder = $orders[0];
        $paidOrder = $orders[1];
        $paidOrder->status = 'confirmed';
        $paidOrder->payment_status = 'paid';
        $paidOrder->shipping_status = 'pending_pickup';
        $paidOrder->save();

        $sessionId = CheckoutSessionOrder::where('order_id', $unpaidOrder->id)->value('checkout_session_id');
        $this->walletService->accountFor($user);
        PaymentTransaction::create([
            'checkout_session_id' => $sessionId,
            'provider' => 'vnpay',
            'provider_reference' => 'MIXED-SESSION-'.uniqid(),
            'idempotency_key' => 'mixed-session-'.uniqid(),
            'amount' => $unpaidOrder->total_amount + $paidOrder->total_amount,
            'currency' => 'VND',
            'status' => PaymentTransactionStatus::PAID,
            'paid_at' => now(),
        ]);

        $orderIds = [$unpaidOrder->id, $paidOrder->id];
        $before = $this->cancellationSnapshot($orderIds, $sessionId, $user->id);

        foreach (range(1, 2) as $_) {
            $response = $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$unpaidOrder->id}/cancel");
            $response->assertStatus(422)
                ->assertJsonPath('status', 'error');
            $this->assertStringContainsString('return/refund workflow', $response->json('message'));
            $this->assertSame($before, $this->cancellationSnapshot($orderIds, $sessionId, $user->id));
        }
    }

    public function test_valid_multi_vendor_online_session_exposes_one_identical_scope_and_cancels_exactly_that_scope(): void
    {
        $user = User::factory()->create();
        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();
        $orders = $this->checkoutService->processCheckout(
            [
                ['book_id' => $this->createBook($vendorA)->id, 'quantity' => 1],
                ['book_id' => $this->createBook($vendorB)->id, 'quantity' => 1],
            ],
            ['shipping_address' => 'Scope St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );
        $expectedIds = collect($orders)->pluck('id')->sort()->values()->all();
        $expectedCodes = collect($orders)
            ->sortBy('id')
            ->map(fn (Order $order) => (string) ($order->order_code ?: $order->id))
            ->values()
            ->all();

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
        $listOrders = collect($list->json('data'))->keyBy('id');
        $expectedScope = [
            'type' => 'checkout_session',
            'count' => 2,
            'order_ids' => $expectedIds,
            'order_codes' => $expectedCodes,
        ];

        foreach ($expectedIds as $orderId) {
            $this->assertTrue($listOrders[$orderId]['can_cancel']);
            $this->assertSame($expectedScope, $listOrders[$orderId]['cancellation_scope']);
            $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$orderId}")
                ->assertOk()
                ->assertJsonPath('data.can_cancel', true)
                ->assertJsonPath('data.cancellation_scope', $expectedScope);
        }

        $cancelled = $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$expectedIds[0]}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'success');
        $this->assertSame($expectedIds, collect($cancelled->json('data'))->pluck('id')->sort()->values()->all());
        $this->assertSame(['cancelled', 'cancelled'], Order::withoutGlobalScopes()
            ->whereIn('id', $expectedIds)
            ->orderBy('id')
            ->pluck('status')
            ->all());
    }

    public function test_pending_target_with_cancelled_sibling_exposes_session_scope_and_converges(): void
    {
        $user = User::factory()->create();
        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();
        $orders = $this->checkoutService->processCheckout(
            [
                ['book_id' => $this->createBook($vendorA)->id, 'quantity' => 1],
                ['book_id' => $this->createBook($vendorB)->id, 'quantity' => 1],
            ],
            ['shipping_address' => 'Partial Pending Target St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );
        $target = $orders[0];
        $sibling = $orders[1];
        $sibling->status = 'cancelled';
        $sibling->save();
        $expectedScope = $this->sessionScope($orders);

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
        $listedTarget = collect($list->json('data'))->firstWhere('id', $target->id);
        $this->assertTrue($listedTarget['can_cancel']);
        $this->assertSame($expectedScope, $listedTarget['cancellation_scope']);
        $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.cancellation_scope', $expectedScope);

        $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$target->id}/cancel")
            ->assertOk();
        $this->assertSame(['cancelled', 'cancelled'], Order::withoutGlobalScopes()
            ->whereIn('id', $expectedScope['order_ids'])
            ->orderBy('id')
            ->pluck('status')
            ->all());
    }

    public function test_cancelled_target_with_pending_sibling_exposes_session_scope_and_converges(): void
    {
        $user = User::factory()->create();
        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();
        $orders = $this->checkoutService->processCheckout(
            [
                ['book_id' => $this->createBook($vendorA)->id, 'quantity' => 1],
                ['book_id' => $this->createBook($vendorB)->id, 'quantity' => 1],
            ],
            ['shipping_address' => 'Partial Cancelled Target St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );
        $target = $orders[0];
        $target->status = 'cancelled';
        $target->save();
        $expectedScope = $this->sessionScope($orders);

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
        $listedTarget = collect($list->json('data'))->firstWhere('id', $target->id);
        $this->assertTrue($listedTarget['can_cancel']);
        $this->assertSame($expectedScope, $listedTarget['cancellation_scope']);
        $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.cancellation_scope', $expectedScope);

        $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$target->id}/cancel")
            ->assertOk();
        $this->assertSame(['cancelled', 'cancelled'], Order::withoutGlobalScopes()
            ->whereIn('id', $expectedScope['order_ids'])
            ->orderBy('id')
            ->pluck('status')
            ->all());
    }

    public function test_all_cancelled_session_hides_scope_while_repeat_post_remains_idempotent(): void
    {
        $user = User::factory()->create();
        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();
        $orders = $this->checkoutService->processCheckout(
            [
                ['book_id' => $this->createBook($vendorA)->id, 'quantity' => 1],
                ['book_id' => $this->createBook($vendorB)->id, 'quantity' => 1],
            ],
            ['shipping_address' => 'Converged Scope St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );
        foreach ($orders as $order) {
            $order->status = 'cancelled';
            $order->save();
        }

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
        $listedTarget = collect($list->json('data'))->firstWhere('id', $orders[0]->id);
        $this->assertFalse($listedTarget['can_cancel']);
        $this->assertNull($listedTarget['cancellation_scope']);
        $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$orders[0]->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.cancellation_scope', null);
        $this->actingAs($user, 'sanctum')->postJson("/api/orders/{$orders[0]->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_paid_or_non_pending_sibling_hides_session_scope_from_list_and_detail(): void
    {
        $user = User::factory()->create();
        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();
        $orders = $this->checkoutService->processCheckout(
            [
                ['book_id' => $this->createBook($vendorA)->id, 'quantity' => 1],
                ['book_id' => $this->createBook($vendorB)->id, 'quantity' => 1],
            ],
            ['shipping_address' => 'Ineligible Scope St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );
        $target = $orders[0];
        $sibling = $orders[1];
        $sibling->status = 'confirmed';
        $sibling->payment_status = 'paid';
        $sibling->save();

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
        $listedTarget = collect($list->json('data'))->firstWhere('id', $target->id);
        $this->assertFalse($listedTarget['can_cancel']);
        $this->assertNull($listedTarget['cancellation_scope']);
        $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.cancellation_scope', null);

        $sibling->payment_status = 'unpaid';
        $sibling->save();
        $nonPendingList = $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
        $nonPendingTarget = collect($nonPendingList->json('data'))->firstWhere('id', $target->id);
        $this->assertFalse($nonPendingTarget['can_cancel']);
        $this->assertNull($nonPendingTarget['cancellation_scope']);
        $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.cancellation_scope', null);
    }

    public function test_committed_reservation_hides_cancellation_scope_from_list_and_detail(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $order = $this->checkoutService->processCheckout(
            [['book_id' => $this->createBook($vendor)->id, 'quantity' => 1]],
            ['shipping_address' => 'Committed Scope St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        )[0];
        $sessionId = CheckoutSessionOrder::where('order_id', $order->id)->value('checkout_session_id');
        InventoryReservation::where('checkout_session_id', $sessionId)
            ->update(['status' => InventoryReservationStatus::COMMITTED]);

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/my-orders')->assertOk();
        $listedOrder = collect($list->json('data'))->firstWhere('id', $order->id);
        $this->assertFalse($listedOrder['can_cancel']);
        $this->assertNull($listedOrder['cancellation_scope']);
        $this->actingAs($user, 'sanctum')->getJson("/api/my-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.cancellation_scope', null);
    }

    private function cancellationSnapshot(array $orderIds, int $sessionId, int $userId): array
    {
        $orderItemIds = DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id');
        $bookIds = DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('book_id')->unique();
        $accountIds = DemoWalletAccount::where('user_id', $userId)->pluck('id');
        $returnRequestIds = ReturnRequest::whereIn('order_id', $orderIds)->pluck('id');

        return [
            'orders' => Order::withoutGlobalScopes()->whereIn('id', $orderIds)->orderBy('id')->get()->map(fn (Order $order) => $order->getAttributes())->all(),
            'payment_transactions' => PaymentTransaction::where('checkout_session_id', $sessionId)->orderBy('id')->get()->map(fn (PaymentTransaction $transaction) => $transaction->getAttributes())->all(),
            'reservations' => InventoryReservation::where('checkout_session_id', $sessionId)
                ->orderBy('id')
                ->get()
                ->map(fn (InventoryReservation $reservation) => $reservation->getAttributes())
                ->all(),
            'inventory' => WarehouseStock::whereIn('book_id', $bookIds)
                ->orderBy('id')
                ->get()
                ->map(fn (WarehouseStock $stock) => $stock->getAttributes())
                ->all(),
            'wallet_accounts' => DemoWalletAccount::whereIn('id', $accountIds)->orderBy('id')->get()->map(fn (DemoWalletAccount $account) => $account->getAttributes())->all(),
            'wallet_ledger' => DemoWalletLedgerEntry::whereIn('demo_wallet_account_id', $accountIds)->orderBy('id')
                ->get()
                ->map(fn (DemoWalletLedgerEntry $entry) => $entry->getAttributes())
                ->all(),
            'return_requests' => ReturnRequest::whereIn('id', $returnRequestIds)->orderBy('id')
                ->get()
                ->map(fn (ReturnRequest $returnRequest) => $returnRequest->getAttributes())
                ->all(),
            'refund_transactions' => RefundTransaction::whereIn('return_request_id', $returnRequestIds)->orderBy('id')
                ->get()
                ->map(fn (RefundTransaction $refundTransaction) => $refundTransaction->getAttributes())
                ->all(),
            'order_transition_operations' => OrderTransitionOperation::whereIn('order_id', $orderIds)->orderBy('id')->get()->map(fn (OrderTransitionOperation $operation) => $operation->getAttributes())->all(),
            'inventory_cancellation_restorations' => DB::table('inventory_cancellation_restorations')->whereIn('order_item_id', $orderItemIds)->orderBy('id')->get()->map(fn (object $restoration) => (array) $restoration)->all(),
        ];
    }

    /**
     * @param array<int, Order> $orders
     * @return array<string, mixed>
     */
    private function sessionScope(array $orders): array
    {
        $orders = collect($orders)->sortBy('id')->values();

        return [
            'type' => 'checkout_session',
            'count' => $orders->count(),
            'order_ids' => $orders->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'order_codes' => $orders->map(fn (Order $order) => (string) ($order->order_code ?: $order->id))->all(),
        ];
    }

    public function test_vendor_cannot_manually_refund_or_change_status_to_refunded(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Vendor Shop '.rand(100, 999),
            'slug' => 'vendor-shop-'.rand(100, 999),
            'status' => 'active',
        ]);
        $book = $this->createBook($vendor, 200000);
        $buyer = User::factory()->create();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Vendor Test St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $buyer->id
        );
        $order = $orders[0];

        // Vendor attempts to set status to 'cancelled' or anything other than 'shipped' -> rejected with 422
        $res = $this->actingAs($vendorUser, 'sanctum')->patchJson("/api/vendor/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ]);
        $res->assertStatus(422);
    }
}

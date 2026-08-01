<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryReservationStatus;
use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\InventoryReservation;
use App\Models\InventoryReservationAllocation;
use App\Models\Order;
use App\Models\OrderItem;
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
use LogicException;
use RuntimeException;
use Tests\TestCase;

class InventoryReservationCheckoutIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private CheckoutService $checkoutService;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('ping')->andThrow(new Exception('Redis disabled in test environment'));
        File::shouldReceive('exists')->byDefault()->andReturn(false);

        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-'.uniqid(),
        ]);

        $this->checkoutService = new CheckoutService;
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

    /**
     * 1. Physical checkout tạo reservation/allocation nhưng chưa trừ on-hand stock.
     */
    public function test_physical_checkout_creates_reservation_and_allocation_without_deducting_on_hand_stock(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => '123 Test St', 'phone' => '0901234567'],
            $user->id
        );

        $this->assertCount(1, $orders);
        $this->assertEquals(1, InventoryReservation::count());
        $this->assertEquals(1, InventoryReservationAllocation::count());

        $reservation = InventoryReservation::first();
        $this->assertEquals(InventoryReservationStatus::RESERVED, $reservation->status);
        $this->assertEquals(2, $reservation->quantity);

        // On-hand stock chưa bị trừ
        $this->assertEquals(10, $stock->fresh()->quantity);
        $this->assertEquals(10, $book->fresh()->stock);
    }

    /**
     * 2. Multi-vendor checkout reserve toàn bộ session.
     */
    public function test_multi_vendor_checkout_reserves_entire_session(): void
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
            ['shipping_address' => '456 Multi St', 'phone' => '0909999999'],
            $user->id
        );

        $this->assertCount(2, $orders);
        $this->assertEquals(1, CheckoutSession::count());
        $this->assertEquals(2, InventoryReservation::count());

        $session = CheckoutSession::first();
        $reservations = InventoryReservation::all();

        $this->assertEquals($session->id, $reservations[0]->checkout_session_id);
        $this->assertEquals($session->id, $reservations[1]->checkout_session_id);
        $this->assertEquals(InventoryReservationStatus::RESERVED, $reservations[0]->status);
        $this->assertEquals(InventoryReservationStatus::RESERVED, $reservations[1]->status);
    }

    /**
     * 3. Ebook-only checkout không cần warehouse stock và không tạo reservation.
     */
    public function test_ebook_only_checkout_does_not_require_warehouse_stock_or_create_reservations(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $ebook = $this->createBook($vendor, 'ebook', 50000);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $ebook->id, 'quantity' => 1]],
            ['shipping_address' => 'Digital St', 'phone' => '0901111111'],
            $user->id
        );

        $this->assertCount(1, $orders);
        $this->assertEquals(1, CheckoutSession::count());
        $this->assertEquals(0, InventoryReservation::count());
        $this->assertEquals(0, InventoryReservationAllocation::count());
    }

    /**
     * 4. Không đủ physical stock rollback toàn bộ session, orders, reservations và coupon usage.
     */
    public function test_insufficient_stock_rolls_back_entire_checkout_and_coupon_usage(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 2);

        $coupon = Coupon::create([
            'code' => 'TESTSTOCK',
            'discount_percent' => 10,
            'min_order_value' => 10000,
            'usage_limit' => 5,
            'used_count' => 0,
        ]);

        $this->expectException(RuntimeException::class);

        try {
            $this->checkoutService->processCheckout(
                [['book_id' => $book->id, 'quantity' => 5]],
                ['shipping_address' => 'Fail Rd', 'phone' => '0900000000'],
                $user->id,
                'TESTSTOCK'
            );
        } finally {
            $this->assertEquals(0, CheckoutSession::count());
            $this->assertEquals(0, Order::count());
            $this->assertEquals(0, OrderItem::count());
            $this->assertEquals(0, InventoryReservation::count());
            $this->assertEquals(0, $coupon->fresh()->used_count);
        }
    }

    /**
     * 5. Checkout không gọi Redis.
     */
    public function test_checkout_does_not_call_redis(): void
    {
        Queue::fake();

        Redis::shouldReceive('ping')->never();
        Redis::shouldReceive('get')->never();
        Redis::shouldReceive('set')->never();
        Redis::shouldReceive('decrBy')->never();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'No Redis St', 'phone' => '0901234567'],
            $user->id
        );

        $this->assertCount(1, $orders);
    }

    /**
     * 6. Online checkout không dispatch ProcessOrder và giữ order ở trạng thái pending/unpaid.
     */
    public function test_online_checkout_does_not_dispatch_process_order_and_remains_pending(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Online St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $this->assertCount(1, $orders);
        $order = $orders[0];

        $this->assertEquals('pending', $order->status);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals('online', $order->payment_method);

        Queue::assertNothingPushed();
    }

    /**
     * 7. COD checkout chuyển order sang confirmed trong transaction và dispatch ProcessOrder sau commit.
     */
    public function test_cod_checkout_sets_status_confirmed_and_dispatches_process_order_after_commit(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'COD St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );

        $this->assertCount(1, $orders);
        $order = $orders[0];

        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals('cod', $order->payment_method);

        Queue::assertPushed(ProcessOrder::class, 1);
    }

    /**
     * 8. Online payment success + ProcessOrder commit inventory đúng một lần và chuyển confirmed -> processing.
     */
    public function test_online_payment_success_and_process_order_commits_inventory_once(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'VNPAY St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $order = $orders[0];
        $this->assertEquals('pending', $order->status);

        $order->status = 'confirmed';
        $order->payment_status = 'paid';
        $order->save();

        $job = new ProcessOrder($order->id);
        $job->handle();

        $this->assertEquals('processing', $order->fresh()->status);

        $res = InventoryReservation::first();
        $this->assertEquals(InventoryReservationStatus::COMMITTED, $res->status);

        $this->assertEquals(8, $stock->fresh()->quantity);
        $this->assertEquals(8, $book->fresh()->stock);
        $this->assertDatabaseHas('warehouse_documents', [
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'source_warehouse_id' => $stock->warehouse_id,
            'type' => 'dispatch',
            'origin' => 'order_fulfillment',
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('warehouse_stock_ledgers', [
            'warehouse_id' => $stock->warehouse_id,
            'book_id' => $book->id,
            'quantity_delta' => -2,
            'balance_after' => 8,
        ]);
        $this->assertDatabaseCount('warehouse_documents', 1);
    }

    /**
     * 9. Multi-order jobs trong cùng session không trừ lại inventory của session.
     */
    public function test_multi_order_jobs_in_same_session_do_not_double_decrement(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();

        $bookA = $this->createBook($vendorA, 'physical', 100000, 10);
        $bookB = $this->createBook($vendorB, 'physical', 200000, 10);

        $stockA = WarehouseStock::where('book_id', $bookA->id)->first();
        $stockB = WarehouseStock::where('book_id', $bookB->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [
                ['book_id' => $bookA->id, 'quantity' => 2],
                ['book_id' => $bookB->id, 'quantity' => 3],
            ],
            ['shipping_address' => 'Multi COD St', 'phone' => '0909999999', 'payment_method' => 'cod'],
            $user->id
        );

        $this->assertCount(2, $orders);

        (new ProcessOrder($orders[0]->id))->handle();

        $this->assertEquals('processing', $orders[0]->fresh()->status);
        $this->assertEquals(8, $stockA->fresh()->quantity);
        $this->assertEquals(7, $stockB->fresh()->quantity);

        (new ProcessOrder($orders[1]->id))->handle();

        $this->assertEquals('processing', $orders[1]->fresh()->status);
        $this->assertEquals(8, $stockA->fresh()->quantity);
        $this->assertEquals(7, $stockB->fresh()->quantity);
    }

    /**
     * 10. ProcessOrder retry khi order đã processing không double-decrement.
     */
    public function test_process_order_retry_does_not_double_decrement(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Retry St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );

        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();
        $this->assertEquals('processing', $order->fresh()->status);
        $this->assertEquals(8, $stock->fresh()->quantity);

        (new ProcessOrder($order->id))->handle();
        $this->assertEquals('processing', $order->fresh()->status);
        $this->assertEquals(8, $stock->fresh()->quantity);
    }

    /**
     * 11. Legacy order hoặc invalid order status fail closed.
     */
    public function test_legacy_order_or_invalid_status_fails_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();

        $legacyOrder = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 100000,
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
            'phone' => '0900000000',
            'shipping_address' => 'Legacy Address',
        ]);

        $this->expectException(RuntimeException::class);
        (new ProcessOrder($legacyOrder->id))->handle();
    }

    /**
     * 11b. Invalid order status (pending/cancelled) fails closed.
     */
    public function test_invalid_order_status_fails_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Invalid St', 'phone' => '0901234567', 'payment_method' => 'online'],
            $user->id
        );

        $order = $orders[0];

        $this->expectException(LogicException::class);
        (new ProcessOrder($order->id))->handle();
    }

    /**
     * 12. Commit failure trong ProcessOrder rollback cả inventory và order status.
     */
    public function test_commit_failure_in_process_order_rolls_back_inventory_and_order(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 5]],
            ['shipping_address' => 'Fail Commit St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );

        $order = $orders[0];

        $stock->quantity = 2;
        $stock->save();

        $this->expectException(RuntimeException::class);

        try {
            (new ProcessOrder($order->id))->handle();
        } finally {
            $this->assertEquals('confirmed', $order->fresh()->status);

            $res = InventoryReservation::first();
            $this->assertEquals(InventoryReservationStatus::RESERVED, $res->status);
        }
    }

    /**
     * 13. Duplicate book lines vượt tồn kho trong checkout bị rollback toàn bộ.
     */
    public function test_duplicate_book_lines_in_checkout_exceeding_stock_fails_and_rolls_back(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $this->expectException(RuntimeException::class);

        try {
            $this->checkoutService->processCheckout(
                [
                    ['book_id' => $book->id, 'quantity' => 5],
                    ['book_id' => $book->id, 'quantity' => 6],
                ],
                ['shipping_address' => 'Dup Over St', 'phone' => '0901234567'],
                $user->id
            );
        } finally {
            $this->assertEquals(0, CheckoutSession::count());
            $this->assertEquals(0, Order::count());
            $this->assertEquals(0, InventoryReservation::count());
        }
    }

    /**
     * 14. Duplicate book lines hợp lệ trong checkout reserve và commit thành công trừ đúng 1 lần.
     */
    public function test_duplicate_book_lines_in_checkout_within_stock_reserves_and_commits_once(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [
                ['book_id' => $book->id, 'quantity' => 5],
                ['book_id' => $book->id, 'quantity' => 4],
            ],
            ['shipping_address' => 'Dup Valid St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );

        $this->assertCount(1, $orders);
        $this->assertEquals(2, OrderItem::count());
        $this->assertEquals(2, InventoryReservation::count());
        $this->assertEquals(10, $stock->fresh()->quantity);
        $snapshotItemIds = collect($orders[0]->invoiceSnapshot->line_items)->pluck('order_item_id');
        $this->assertCount(2, $snapshotItemIds);
        $this->assertSame(2, $snapshotItemIds->unique()->count());
        $this->assertEqualsCanonicalizing(
            $orders[0]->orderItems()->pluck('id')->all(),
            $snapshotItemIds->all()
        );

        // Run ProcessOrder job
        (new ProcessOrder($orders[0]->id))->handle();

        $this->assertEquals('processing', $orders[0]->fresh()->status);
        // On-hand stock deducted 9: 10 - 9 = 1
        $this->assertEquals(1, $stock->fresh()->quantity);
        $this->assertEquals(1, $book->fresh()->stock);
    }
}

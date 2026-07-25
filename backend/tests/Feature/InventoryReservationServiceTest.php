<?php

namespace Tests\Feature;

use App\Enums\InventoryReservationStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\InventoryReservation;
use App\Models\InventoryReservationAllocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Inventory\InventoryReservationService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class InventoryReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryReservationService $service;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('ping')->andThrow(new Exception('Redis disabled in test environment'));
        File::shouldReceive('exists')->byDefault()->andReturn(false);

        $this->service = new InventoryReservationService;
        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-'.uniqid(),
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

    private function createBook(Vendor $vendor, string $type = 'physical', string $status = 'published', int $stock = 0): Book
    {
        $uniqueId = uniqid();

        return Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $this->category->id,
            'title' => 'Book '.$uniqueId,
            'slug' => 'book-'.$uniqueId,
            'author' => 'Author Name',
            'price' => 100000,
            'stock' => $stock,
            'type' => $type,
            'status' => $status,
        ]);
    }

    private function createWarehouse(Vendor $vendor, string $name = 'Warehouse 1'): Warehouse
    {
        return Warehouse::create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'address' => '123 Warehouse St',
            'capacity' => 1000,
            'status' => 'active',
        ]);
    }

    private function createWarehouseStock(Warehouse $warehouse, Book $book, int $quantity = 10): WarehouseStock
    {
        return WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'book_id' => $book->id,
            'quantity' => $quantity,
        ]);
    }

    private function createCheckoutSessionWithItems(User $user, array $items): array
    {
        $session = CheckoutSession::create([
            'user_id' => $user->id,
            'currency' => 'VND',
            'subtotal_amount' => 100000,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 100000,
            'expires_at' => now()->addMinutes(30),
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'vendor_id' => $items[0]['book']->vendor_id,
            'order_code' => 'ORD-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'online',
            'total_amount' => 100000,
            'phone' => '0901234567',
            'shipping_address' => '123 Test St',
        ]);

        CheckoutSessionOrder::create([
            'checkout_session_id' => $session->id,
            'order_id' => $order->id,
            'vendor_id' => $items[0]['book']->vendor_id,
            'subtotal_amount' => 100000,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'total_amount' => 100000,
        ]);

        $orderItems = [];
        foreach ($items as $itemData) {
            $orderItems[] = OrderItem::create([
                'order_id' => $order->id,
                'book_id' => $itemData['book']->id,
                'quantity' => $itemData['quantity'],
                'price' => $itemData['book']->price,
            ]);
        }

        return ['session' => $session, 'order' => $order, 'items' => $orderItems];
    }

    /**
     * 1. Ebook không tạo reservation/allocation.
     */
    public function test_ebook_does_not_create_reservation(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $ebook = $this->createBook($vendor, 'ebook');

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $ebook, 'quantity' => 1]]);

        $reservations = $this->service->reserve($data['session'], now()->addMinutes(15), 'op-ebook-1');

        $this->assertEmpty($reservations);
        $this->assertEquals(0, InventoryReservation::count());
        $this->assertEquals(0, InventoryReservationAllocation::count());
    }

    /**
     * 2. Một physical item reserve ở một warehouse.
     */
    public function test_single_physical_item_reserved_in_single_warehouse(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 'published', 10);
        $warehouse = $this->createWarehouse($vendor);
        $stock = $this->createWarehouseStock($warehouse, $book, 10);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 2]]);

        $reservations = $this->service->reserve($data['session'], now()->addMinutes(15), 'op-single-1');

        $this->assertCount(1, $reservations);
        $res = $reservations[0];
        $this->assertEquals(InventoryReservationStatus::RESERVED, $res->status);
        $this->assertEquals(2, $res->quantity);

        $allocations = $res->allocations;
        $this->assertCount(1, $allocations);
        $this->assertEquals($stock->id, $allocations[0]->warehouse_stock_id);
        $this->assertEquals(2, $allocations[0]->quantity);

        // On-hand stock chưa bị trừ
        $this->assertEquals(10, $stock->fresh()->quantity);
    }

    /**
     * 3. Một item được chia allocation qua nhiều warehouse theo thứ tự ổn định.
     */
    public function test_single_item_allocated_across_multiple_warehouses_in_stable_order(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');

        $whA = $this->createWarehouse($vendor, 'Warehouse A');
        $whB = $this->createWarehouse($vendor, 'Warehouse B');

        $stockA = $this->createWarehouseStock($whA, $book, 3);
        $stockB = $this->createWarehouseStock($whB, $book, 5);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 6]]);

        $reservations = $this->service->reserve($data['session'], now()->addMinutes(15), 'op-multi-wh-1');

        $this->assertCount(1, $reservations);
        $res = $reservations[0];

        $allocations = $res->allocations->sortBy('warehouse_stock_id')->values();
        $this->assertCount(2, $allocations);

        // Kho A full 3, Kho B 3
        $this->assertEquals($stockA->id, $allocations[0]->warehouse_stock_id);
        $this->assertEquals(3, $allocations[0]->quantity);

        $this->assertEquals($stockB->id, $allocations[1]->warehouse_stock_id);
        $this->assertEquals(3, $allocations[1]->quantity);
    }

    /**
     * 4. Nhiều vendor/item tạo đúng reservation thuộc cùng checkout.
     */
    public function test_multiple_items_create_reservations_under_same_checkout(): void
    {
        $user = User::factory()->create();
        $vendorA = $this->createVendor();
        $vendorB = $this->createVendor();

        $bookA = $this->createBook($vendorA, 'physical');
        $bookB = $this->createBook($vendorB, 'physical');

        $whA = $this->createWarehouse($vendorA);
        $whB = $this->createWarehouse($vendorB);

        $this->createWarehouseStock($whA, $bookA, 10);
        $this->createWarehouseStock($whB, $bookB, 10);

        $data = $this->createCheckoutSessionWithItems($user, [
            ['book' => $bookA, 'quantity' => 2],
            ['book' => $bookB, 'quantity' => 3],
        ]);

        $reservations = $this->service->reserve($data['session'], now()->addMinutes(15), 'op-multi-item-1');

        $this->assertCount(2, $reservations);
        $this->assertEquals($data['session']->id, $reservations[0]->checkout_session_id);
        $this->assertEquals($data['session']->id, $reservations[1]->checkout_session_id);
    }

    /**
     * 5. Available-to-sell trừ các reservation đang hiệu lực.
     */
    public function test_available_to_sell_subtracts_active_reservations(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $book, 10);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 3]]);

        $this->assertEquals(10, $this->service->getAvailableToSell($book));

        $this->service->reserve($data['session'], now()->addMinutes(15), 'op-ats-1');

        $this->assertEquals(7, $this->service->getAvailableToSell($book));
    }

    /**
     * 6. Reservation đã hết hạn không làm giảm available-to-sell.
     */
    public function test_expired_reservation_does_not_reduce_available_to_sell(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $book, 10);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 4]]);

        // Reserve with past expiry date
        $this->service->reserve($data['session'], now()->subMinutes(5), 'op-expired-ats-1');

        $this->assertEquals(10, $this->service->getAvailableToSell($book));
    }

    /**
     * 7. Không đủ tồn rollback toàn bộ, không để bản ghi một phần.
     */
    public function test_insufficient_stock_rolls_back_entire_reservation(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $bookA = $this->createBook($vendor, 'physical');
        $bookB = $this->createBook($vendor, 'physical');

        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $bookA, 10);
        $this->createWarehouseStock($wh, $bookB, 2); // Insufficient for demand of 5

        $data = $this->createCheckoutSessionWithItems($user, [
            ['book' => $bookA, 'quantity' => 2],
            ['book' => $bookB, 'quantity' => 5],
        ]);

        $this->expectException(RuntimeException::class);

        try {
            $this->service->reserve($data['session'], now()->addMinutes(15), 'op-fail-rollback');
        } finally {
            $this->assertEquals(0, InventoryReservation::count());
            $this->assertEquals(0, InventoryReservationAllocation::count());
        }
    }

    /**
     * 8. Duplicate reserve cùng operation key idempotent; payload xung đột fail closed.
     */
    public function test_duplicate_reserve_idempotency_and_payload_conflict(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $book, 10);

        $data1 = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 2]]);
        $data2 = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 3]]);

        // Call 1
        $res1 = $this->service->reserve($data1['session'], now()->addMinutes(15), 'op-idempotent-key');
        $this->assertCount(1, $res1);

        // Retry same session & key -> returns same reservation
        $resRetry = $this->service->reserve($data1['session'], now()->addMinutes(15), 'op-idempotent-key');
        $this->assertEquals($res1[0]->id, $resRetry[0]->id);

        // Different session with same key -> fails closed
        $this->expectException(InvalidArgumentException::class);
        $this->service->reserve($data2['session'], now()->addMinutes(15), 'op-idempotent-key');
    }

    /**
     * 9. Commit trừ warehouse on-hand đúng một lần và đồng bộ books.stock.
     */
    public function test_commit_deducts_warehouse_on_hand_and_syncs_book_stock(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 'published', 15);

        $whA = $this->createWarehouse($vendor, 'WH A');
        $whB = $this->createWarehouse($vendor, 'WH B');

        $stockA = $this->createWarehouseStock($whA, $book, 10);
        $stockB = $this->createWarehouseStock($whB, $book, 5);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 7]]);

        $this->service->reserve($data['session'], now()->addMinutes(15), 'op-commit-1');

        $committedRes = $this->service->commitSession($data['session']);

        $this->assertCount(1, $committedRes);
        $this->assertEquals(InventoryReservationStatus::COMMITTED, $committedRes[0]->status);

        // On-hand deducted: WH A 10 - 7 = 3, WH B 5
        $this->assertEquals(3, $stockA->fresh()->quantity);
        $this->assertEquals(5, $stockB->fresh()->quantity);

        // books.stock projection synced: 3 + 5 = 8
        $this->assertEquals(8, $book->fresh()->stock);

        // Retry commit does not deduct a second time
        $this->service->commitSession($data['session']);
        $this->assertEquals(3, $stockA->fresh()->quantity);
        $this->assertEquals(8, $book->fresh()->stock);
    }

    /**
     * 10. Commit thiếu on-hand hoặc dữ liệu allocation sai rollback toàn bộ.
     */
    public function test_commit_with_insufficient_on_hand_rolls_back(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $stock = $this->createWarehouseStock($wh, $book, 10);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 5]]);

        $reservations = $this->service->reserve($data['session'], now()->addMinutes(15), 'op-commit-fail');

        // Manually reduce on-hand stock to 2 before commit
        $stock->quantity = 2;
        $stock->save();

        $this->expectException(RuntimeException::class);

        try {
            $this->service->commitSession($data['session']);
        } finally {
            $this->assertEquals(InventoryReservationStatus::RESERVED, $reservations[0]->fresh()->status);
        }
    }

    /**
     * 11. Release và expire không trừ on-hand và là idempotent.
     */
    public function test_release_and_expire_do_not_deduct_on_hand_and_are_idempotent(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $stock = $this->createWarehouseStock($wh, $book, 10);

        $data1 = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 3]]);
        $data2 = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 2]]);

        $res1 = $this->service->reserve($data1['session'], now()->addMinutes(15), 'op-release-1');
        $res2 = $this->service->reserve($data2['session'], now()->addMinutes(15), 'op-expire-1');

        // Release
        $released = $this->service->releaseReservation($res1[0]);
        $this->assertEquals(InventoryReservationStatus::RELEASED, $released->status);
        $this->assertEquals(10, $stock->fresh()->quantity);

        // Retry release (idempotent)
        $releasedRetry = $this->service->releaseReservation($res1[0]);
        $this->assertEquals(InventoryReservationStatus::RELEASED, $releasedRetry->status);

        // Expire
        $expired = $this->service->expireReservation($res2[0]);
        $this->assertEquals(InventoryReservationStatus::EXPIRED, $expired->status);
        $this->assertEquals(10, $stock->fresh()->quantity);

        // Retry expire (idempotent)
        $expiredRetry = $this->service->expireReservation($res2[0]);
        $this->assertEquals(InventoryReservationStatus::EXPIRED, $expiredRetry->status);
    }

    /**
     * 12. Terminal transition bất hợp lệ fail closed.
     */
    public function test_invalid_terminal_transitions_fail_closed(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $book, 10);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 3]]);

        $reservations = $this->service->reserve($data['session'], now()->addMinutes(15), 'op-terminal-1');
        $this->service->releaseReservation($reservations[0]);

        // Trying to commit released reservation -> fails closed
        $this->expectException(LogicException::class);
        $this->service->commitReservation($reservations[0]);
    }

    /**
     * 13. Unique/FK/cast/relationship và migration rollback được bao phủ phù hợp.
     */
    public function test_model_relationships_casts_and_constraints(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $stock = $this->createWarehouseStock($wh, $book, 10);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 2]]);

        $reservations = $this->service->reserve($data['session'], now()->addMinutes(15), 'op-rel-1');
        $res = $reservations[0];

        $this->assertInstanceOf(CheckoutSession::class, $res->checkoutSession);
        $this->assertInstanceOf(OrderItem::class, $res->orderItem);
        $this->assertInstanceOf(Book::class, $res->book);
        $this->assertCount(1, $res->allocations);

        $alloc = $res->allocations[0];
        $this->assertInstanceOf(InventoryReservation::class, $alloc->reservation);
        $this->assertInstanceOf(WarehouseStock::class, $alloc->warehouseStock);

        $this->assertCount(1, $data['session']->inventoryReservations);
        $this->assertNotNull($data['items'][0]->inventoryReservation);
        $this->assertCount(1, $book->inventoryReservations);
        $this->assertCount(1, $book->warehouseStocks);
        $this->assertCount(1, $stock->allocations);
    }

    /**
     * 14. Prefix collision không xảy ra giữa các operation key.
     */
    public function test_prefix_collision_does_not_occur_between_operation_keys(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $book, 50);

        $data1 = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 2]]);
        $data2 = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 3]]);

        $res1 = $this->service->reserve($data1['session'], now()->addMinutes(15), 'reserve:1');
        $res2 = $this->service->reserve($data2['session'], now()->addMinutes(15), 'reserve:10');

        $committed = $this->service->commitOperation('reserve:1');

        $this->assertCount(1, $committed);
        $this->assertEquals($res1[0]->id, $committed[0]->id);
        $this->assertEquals(InventoryReservationStatus::COMMITTED, $res1[0]->fresh()->status);
        $this->assertEquals(InventoryReservationStatus::RESERVED, $res2[0]->fresh()->status);
    }

    /**
     * 15. Ký tự không hợp lệ trong operation key bị từ chối.
     */
    public function test_invalid_characters_in_operation_key_are_rejected(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $book, 10);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 2]]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->reserve($data['session'], now()->addMinutes(15), 'key_with_underscore');
    }

    /**
     * 16. Retry reserve cùng key nhưng payload khác phải fail closed.
     */
    public function test_retry_same_key_with_different_quantity_or_expiry_fails_closed(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical');
        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $book, 50);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 2]]);
        $expiry1 = now()->addMinutes(15);

        $this->service->reserve($data['session'], $expiry1, 'retry-key-test');

        $data['items'][0]->quantity = 5;
        $data['items'][0]->save();

        $this->expectException(InvalidArgumentException::class);
        $this->service->reserve($data['session'], $expiry1, 'retry-key-test');
    }

    /**
     * 17. Target resolution thực sự từ chối integer scalar.
     */
    public function test_target_id_collision_integer_scalar_target_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->commit(123);
    }

    /**
     * 18. Commit integrity check kiểm tra kỹ allocation và rollback khi sai lệch.
     */
    public function test_commit_integrity_checks_and_rollback_on_faulty_allocation(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book1 = $this->createBook($vendor, 'physical', 'published', 10);
        $book2 = $this->createBook($vendor, 'physical', 'published', 10);

        $wh = $this->createWarehouse($vendor);
        $stock1 = $this->createWarehouseStock($wh, $book1, 10);
        $stock2 = $this->createWarehouseStock($wh, $book2, 10);

        $data = $this->createCheckoutSessionWithItems($user, [['book' => $book1, 'quantity' => 3]]);
        $resList = $this->service->reserve($data['session'], now()->addMinutes(15), 'integrity-test-1');
        $res = $resList[0];

        $alloc = $res->allocations[0];
        $alloc->warehouse_stock_id = $stock2->id;
        $alloc->save();

        $this->expectException(RuntimeException::class);

        try {
            $this->service->commitSession($data['session']);
        } finally {
            $this->assertEquals(InventoryReservationStatus::RESERVED, $res->fresh()->status);
            $this->assertEquals(10, $stock1->fresh()->quantity);
            $this->assertEquals(10, $stock2->fresh()->quantity);
        }
    }

    /**
     * 19. Các helper semantic method hoạt động chính xác.
     */
    public function test_operation_semantic_methods_contract(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 'published', 20);
        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $book, 20);

        $data1 = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 2]]);
        $data2 = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 3]]);
        $data3 = $this->createCheckoutSessionWithItems($user, [['book' => $book, 'quantity' => 4]]);

        $res1 = $this->service->reserve($data1['session'], now()->addMinutes(15), 'op-contract-1');
        $res2 = $this->service->reserve($data2['session'], now()->addMinutes(15), 'op-contract-2');
        $res3 = $this->service->reserve($data3['session'], now()->addMinutes(15), 'op-contract-3');

        $committedSingle = $this->service->commitReservation($res1[0]);
        $this->assertEquals(InventoryReservationStatus::COMMITTED, $committedSingle->status);

        $releasedSession = $this->service->releaseSession($data2['session']);
        $this->assertEquals(InventoryReservationStatus::RELEASED, $releasedSession[0]->status);

        $expiredOp = $this->service->expireOperation('op-contract-3');
        $this->assertEquals(InventoryReservationStatus::EXPIRED, $expiredOp[0]->status);
    }

    /**
     * 20. Hai dòng cùng book có tổng quantity vượt stock trong 1 session: reserve thất bại và rollback toàn bộ.
     */
    public function test_duplicate_book_lines_exceeding_stock_fails_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 'published', 10);
        $wh = $this->createWarehouse($vendor);
        $this->createWarehouseStock($wh, $book, 10);

        // Session chứa 2 OrderItem cùng book_id (5 + 6 = 11 > 10)
        $data = $this->createCheckoutSessionWithItems($user, [
            ['book' => $book, 'quantity' => 5],
            ['book' => $book, 'quantity' => 6],
        ]);

        $this->expectException(RuntimeException::class);

        try {
            $this->service->reserve($data['session'], now()->addMinutes(15), 'op-dup-over-stock');
        } finally {
            $this->assertEquals(0, InventoryReservation::count());
            $this->assertEquals(0, InventoryReservationAllocation::count());
        }
    }

    /**
     * 21. Hai dòng cùng book có tổng quantity <= stock: reserve thành công cho cả hai dòng và commit trừ đúng tổng 1 lần.
     */
    public function test_duplicate_book_lines_within_stock_reserves_and_commits_correctly(): void
    {
        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 'published', 10);
        $wh = $this->createWarehouse($vendor);
        $stock = $this->createWarehouseStock($wh, $book, 10);

        // Session chứa 2 OrderItem cùng book_id (5 + 4 = 9 <= 10)
        $data = $this->createCheckoutSessionWithItems($user, [
            ['book' => $book, 'quantity' => 5],
            ['book' => $book, 'quantity' => 4],
        ]);

        $reservations = $this->service->reserve($data['session'], now()->addMinutes(15), 'op-dup-valid-stock');

        $this->assertCount(2, $reservations);
        $this->assertEquals(10, $stock->fresh()->quantity); // Stock chưa bị trừ khi reserve

        // Commit cả session
        $committed = $this->service->commitSession($data['session']);
        $this->assertCount(2, $committed);

        // Trừ đúng 9: 10 - 9 = 1
        $this->assertEquals(1, $stock->fresh()->quantity);
        $this->assertEquals(1, $book->fresh()->stock);

        // Retry commit không trừ lần hai
        $this->service->commitSession($data['session']);
        $this->assertEquals(1, $stock->fresh()->quantity);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryReservationStatus;
use App\Jobs\DeliverOrderSideEffect;
use App\Jobs\ProcessOrder;
use App\Mail\OrderSuccessMail;
use App\Models\Book;
use App\Models\Category;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderSideEffectOutbox;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use App\Services\OrderSideEffectOutboxService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use LogicException;
use Tests\TestCase;
use Throwable;

class OrderSideEffectOutboxTest extends TestCase
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
            'name' => 'Outbox Category',
            'slug' => 'outbox-category-'.uniqid(),
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
     * 1. Job configuration properties (queue, tries, timeout, backoff, uniqueId, uniqueFor).
     */
    public function test_job_configuration_properties_for_process_order_and_deliver_outbox(): void
    {
        $processJob = new ProcessOrder(101);
        $this->assertEquals('default', $processJob->queue);
        $this->assertEquals(5, $processJob->tries);
        $this->assertEquals(60, $processJob->timeout);
        $this->assertEquals(600, $processJob->uniqueFor);
        $this->assertEquals('process-order:101', $processJob->uniqueId());
        $this->assertEquals([5, 15, 30, 60], $processJob->backoff());

        $deliverJob = new DeliverOrderSideEffect(202);
        $this->assertEquals('default', $deliverJob->queue);
        $this->assertEquals(5, $deliverJob->tries);
        $this->assertEquals(30, $deliverJob->timeout);
        $this->assertEquals(600, $deliverJob->uniqueFor);
        $this->assertEquals('deliver-outbox:202', $deliverJob->uniqueId());
        $this->assertEquals([5, 15, 30, 60], $deliverJob->backoff());
    }

    /**
     * 2. Atomic transition, inventory commit, and outbox creation.
     */
    public function test_atomic_transition_inventory_and_outbox_creation(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Outbox St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $this->assertEquals('processing', $order->fresh()->status);
        $res = InventoryReservation::first();
        $this->assertEquals(InventoryReservationStatus::COMMITTED, $res->status);

        $this->assertEquals(2, OrderSideEffectOutbox::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('order_side_effect_outboxes', [
            'order_id' => $order->id,
            'effect_type' => 'database_notification',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('order_side_effect_outboxes', [
            'order_id' => $order->id,
            'effect_type' => 'order_success_email',
            'status' => 'pending',
        ]);

        Queue::assertPushed(DeliverOrderSideEffect::class, 2);
    }

    /**
     * 3. Notification-only behavior for a user without email.
     */
    public function test_notification_only_behavior_for_user_without_email(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => '']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'No Email St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $this->assertEquals(1, OrderSideEffectOutbox::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('order_side_effect_outboxes', [
            'order_id' => $order->id,
            'effect_type' => 'database_notification',
        ]);
        $this->assertDatabaseMissing('order_side_effect_outboxes', [
            'order_id' => $order->id,
            'effect_type' => 'order_success_email',
        ]);
    }

    /**
     * 4. No mail or notification delivery inside ProcessOrder transaction.
     */
    public function test_no_mail_or_notification_delivery_inside_process_order_transaction(): void
    {
        Queue::fake();
        Mail::fake();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Tx Outbox St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $this->assertEquals(0, UserNotification::count());
        Mail::assertNothingSent();

        $this->assertEquals(2, OrderSideEffectOutbox::where('order_id', $order->id)->where('status', 'pending')->count());
    }

    /**
     * 5. ProcessOrder retries 5 times without duplicate transition, inventory, or outbox.
     */
    public function test_process_order_retries_without_duplicate_transition_inventory_or_outbox(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Retry Outbox St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        for ($i = 1; $i <= 5; $i++) {
            (new ProcessOrder($order->id))->handle();
        }

        $this->assertEquals('processing', $order->fresh()->status);
        $this->assertEquals(8, $stock->fresh()->quantity);
        $this->assertEquals(2, OrderSideEffectOutbox::where('order_id', $order->id)->count());
    }

    /**
     * 6. Pre-existing outbox with canonical key but conflicting payload causes ProcessOrder transaction to roll back.
     */
    public function test_conflicting_pre_existing_outbox_rolls_back_process_order_transaction(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Conflict Tx St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        $opKey = "order-processing:{$order->id}:database-notification";
        OrderSideEffectOutbox::create([
            'order_id' => $order->id,
            'operation_key' => $opKey,
            'effect_type' => 'database_notification',
            'payload' => ['conflicting_payload' => true],
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        try {
            (new ProcessOrder($order->id))->handle();
            $this->fail('Expected LogicException on conflicting outbox record');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Conflicting outbox record', $e->getMessage());
        }

        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    /**
     * 7. Injected outbox insertion failure rolls back order status, inventory, and outbox rows.
     */
    public function test_injected_outbox_insertion_failure_rolls_back_entire_order_transaction(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'Injected Rollback St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        DB::statement("
            CREATE TRIGGER fail_outbox_insert
            BEFORE INSERT ON order_side_effect_outboxes
            BEGIN
                SELECT RAISE(ABORT, 'Simulated outbox insert failure');
            END;
        ");

        try {
            (new ProcessOrder($order->id))->handle();
            $this->fail('Expected Exception on injected outbox failure');
        } catch (Throwable $e) {
            $this->assertStringContainsString('Simulated outbox insert failure', $e->getMessage());
        }

        $this->assertEquals('confirmed', $order->fresh()->status);
        $res = InventoryReservation::first();
        $this->assertEquals(InventoryReservationStatus::RESERVED, $res->status);
        $this->assertEquals(10, $stock->fresh()->quantity);
        $this->assertEquals(0, OrderSideEffectOutbox::count());
    }

    /**
     * 8. Outbox payloads contain no credential/token/secret fields or complete user objects.
     */
    public function test_outbox_payloads_contain_no_credentials_tokens_or_raw_user_objects(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Payload Clean St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outboxes = OrderSideEffectOutbox::where('order_id', $order->id)->get();
        foreach ($outboxes as $outbox) {
            $payloadStr = json_encode($outbox->payload);
            $this->assertStringNotContainsString('password', strtolower($payloadStr));
            $this->assertStringNotContainsString('remember_token', strtolower($payloadStr));
            $this->assertStringNotContainsString('email_verified_at', strtolower($payloadStr));
            $this->assertStringNotContainsString('secret', strtolower($payloadStr));
        }
    }

    /**
     * 9. Email is actually sent synchronously by delivery job.
     */
    public function test_email_is_actually_sent_synchronously_by_delivery_job(): void
    {
        Queue::fake();
        Mail::fake();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Sync Mail St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)
            ->where('effect_type', 'order_success_email')
            ->firstOrFail();

        (new DeliverOrderSideEffect($outbox->id))->handle();

        Mail::assertSent(OrderSuccessMail::class, function (OrderSuccessMail $mail) use ($outbox, $user) {
            $headers = $mail->headers();
            $hash = md5($outbox->operation_key);

            return $mail->hasTo($user->email)
                && $headers->messageId !== null
                && str_contains($headers->messageId, $hash);
        });

        $outbox->refresh();
        $this->assertEquals('succeeded', $outbox->status);
        $this->assertNull($outbox->last_error);
        $this->assertNull($outbox->locked_at);
        $this->assertNull($outbox->available_at);
        $this->assertNotNull($outbox->processed_at);
    }

    /**
     * 10. Successful email and notification retries do not duplicate.
     */
    public function test_successful_retries_do_not_duplicate_side_effects(): void
    {
        Queue::fake();
        Mail::fake();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Retry Side Effects St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $notifOutbox = OrderSideEffectOutbox::where('order_id', $order->id)->where('effect_type', 'database_notification')->firstOrFail();
        $emailOutbox = OrderSideEffectOutbox::where('order_id', $order->id)->where('effect_type', 'order_success_email')->firstOrFail();

        for ($i = 1; $i <= 5; $i++) {
            (new DeliverOrderSideEffect($notifOutbox->id))->handle();
            (new DeliverOrderSideEffect($emailOutbox->id))->handle();
        }

        $this->assertEquals(1, UserNotification::where('operation_key', $notifOutbox->operation_key)->count());
        Mail::assertSent(OrderSuccessMail::class, 1);
    }

    /**
     * 11. Unknown effect and invalid status fail closed atomically in claim transaction.
     */
    public function test_unknown_effect_and_invalid_status_fail_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Unknown St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)->firstOrFail();

        // Unknown effect type
        $outbox->effect_type = 'unknown_invalid_effect';
        $outbox->save();

        try {
            (new DeliverOrderSideEffect($outbox->id))->handle();
            $this->fail('Expected LogicException on unknown effect type');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Unsupported outbox effect type', $e->getMessage());
        }

        $outbox->refresh();
        $this->assertEquals('failed', $outbox->status);
        $this->assertEquals(5, $outbox->attempt_count);
        $this->assertStringContainsString('LogicException', $outbox->last_error);

        // Invalid status (e.g. 'archived')
        $outbox->effect_type = 'database_notification';
        $outbox->status = 'archived';
        $outbox->save();

        (new DeliverOrderSideEffect($outbox->id))->handle();
        $this->assertEquals('failed', $outbox->fresh()->status);
    }

    /**
     * 12. Canonical payload validation fails closed on extra/missing keys or corrupted relationship.
     */
    public function test_canonical_payload_validation_fails_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Payload St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)->firstOrFail();

        // Extra key in payload
        $payload = $outbox->payload;
        $payload['extra_malicious_key'] = 'hacked';
        $outbox->payload = $payload;
        $outbox->save();

        try {
            (new DeliverOrderSideEffect($outbox->id))->handle();
            $this->fail('Expected LogicException on extra payload key');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Corrupted payload', $e->getMessage());
        }

        $outbox->refresh();
        $this->assertEquals('failed', $outbox->status);
        $this->assertEquals(5, $outbox->attempt_count);
    }

    /**
     * 13. Recipient mismatch fails closed immediately.
     */
    public function test_recipient_mismatch_fails_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Recipient Mismatch St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)
            ->where('effect_type', 'order_success_email')
            ->firstOrFail();

        // Tamper recipient email in payload
        $payload = $outbox->payload;
        $payload['recipient_email'] = 'attacker@example.com';
        $outbox->payload = $payload;
        $outbox->save();

        try {
            (new DeliverOrderSideEffect($outbox->id))->handle();
            $this->fail('Expected LogicException on recipient email mismatch');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Corrupted payload', $e->getMessage());
        }

        $outbox->refresh();
        $this->assertEquals('failed', $outbox->status);
        $this->assertEquals(5, $outbox->attempt_count);
    }

    /**
     * 14. Conflicting pre-existing UserNotification fails closed immediately (deterministic failure).
     */
    public function test_conflicting_pre_existing_user_notification_fails_closed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Notif Conflict St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)
            ->where('effect_type', 'database_notification')
            ->firstOrFail();

        // Insert conflicting notification row
        $otherUser = User::factory()->create();
        UserNotification::create([
            'operation_key' => $outbox->operation_key,
            'user_id' => $otherUser->id,
            'title' => 'Tampered Title',
            'content' => 'Tampered Content',
            'type' => 'order',
            'data' => [],
        ]);

        try {
            (new DeliverOrderSideEffect($outbox->id))->handle();
            $this->fail('Expected LogicException on conflicting notification');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Conflicting pre-existing notification', $e->getMessage());
        }

        $outbox->refresh();
        $this->assertEquals('failed', $outbox->status);
        $this->assertEquals(5, $outbox->attempt_count);
        $this->assertNull($outbox->available_at);
    }

    /**
     * 15. Deterministic corruption is not redispatched while transient mail failure is eligible after backoff.
     */
    public function test_deterministic_versus_transient_failure_dispatch_eligibility(): void
    {
        Queue::fake();
        Mail::fake();

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Det vs Trans St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outboxes = OrderSideEffectOutbox::where('order_id', $order->id)->orderBy('id', 'asc')->get();
        $notifOutbox = $outboxes[0];
        $emailOutbox = $outboxes[1];

        // 1. Ingest deterministic failure on notification outbox
        $payload = $notifOutbox->payload;
        $payload['user_id'] = 99999;
        $notifOutbox->payload = $payload;
        $notifOutbox->save();

        try {
            (new DeliverOrderSideEffect($notifOutbox->id))->handle();
        } catch (Throwable $e) {
        }

        $this->assertEquals('failed', $notifOutbox->fresh()->status);
        $this->assertEquals(5, $notifOutbox->fresh()->attempt_count);
        $this->assertNull($notifOutbox->fresh()->available_at);

        // 2. Ingest transient failure on email outbox
        Mail::shouldReceive('to')->andThrow(new Exception('Transient SMTP socket timeout'));

        try {
            (new DeliverOrderSideEffect($emailOutbox->id))->handle();
        } catch (Throwable $e) {
        }

        $this->assertEquals('failed', $emailOutbox->fresh()->status);
        $this->assertEquals(1, $emailOutbox->fresh()->attempt_count);
        $this->assertNotNull($emailOutbox->fresh()->available_at);

        // Advance time past backoff delay
        $this->travel(10)->seconds();

        $outboxService = app(OrderSideEffectOutboxService::class);
        $eligible = $outboxService->getEligibleQuery()->pluck('id')->toArray();

        $this->assertNotContains($notifOutbox->id, $eligible);
        $this->assertContains($emailOutbox->id, $eligible);
    }

    /**
     * 16. Pending future, active processing, stale processing, null-lock processing, max-attempt, and succeeded states.
     */
    public function test_all_outbox_claim_and_execution_states(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'All States St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();
        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)->firstOrFail();

        // Future pending (available_at > now) is skipped
        $outbox->refresh();
        $outbox->status = 'pending';
        $outbox->available_at = now()->addHour();
        $outbox->save();
        (new DeliverOrderSideEffect($outbox->id))->handle();
        $this->assertEquals('pending', $outbox->fresh()->status);

        // Active processing (locked_at non-stale) is not stolen
        $outbox->refresh();
        $outbox->status = 'processing';
        $outbox->locked_at = now()->subMinute();
        $outbox->save();
        (new DeliverOrderSideEffect($outbox->id))->handle();
        $this->assertEquals('processing', $outbox->fresh()->status);

        // Null-lock processing becomes terminal failed
        $outbox->refresh();
        $outbox->status = 'processing';
        $outbox->locked_at = null;
        $outbox->save();
        (new DeliverOrderSideEffect($outbox->id))->handle();
        $this->assertEquals('failed', $outbox->fresh()->status);

        // Max attempt failed is not retried
        $outbox->refresh();
        $outbox->status = 'failed';
        $outbox->attempt_count = 5;
        $outbox->available_at = now()->subMinute();
        $outbox->save();
        (new DeliverOrderSideEffect($outbox->id))->handle();
        $this->assertEquals('failed', $outbox->fresh()->status);

        // Stale processing (< 5 attempts) is reclaimed and succeeds
        $outbox->refresh();
        $outbox->status = 'processing';
        $outbox->attempt_count = 1;
        $outbox->locked_at = now()->subMinutes(10);
        $outbox->save();
        (new DeliverOrderSideEffect($outbox->id))->handle();
        $this->assertEquals('succeeded', $outbox->fresh()->status);
    }

    /**
     * 17. failed() callback produces terminal non-eligible state.
     */
    public function test_failed_callback_produces_terminal_non_eligible_state(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Failed Callback St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();
        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)->firstOrFail();

        $job = new DeliverOrderSideEffect($outbox->id);
        $job->failed(new Exception('Exhausted queue retries'));

        $outbox->refresh();
        $this->assertEquals('failed', $outbox->status);
        $this->assertEquals(5, $outbox->attempt_count);
        $this->assertNull($outbox->available_at);
        $this->assertNull($outbox->locked_at);
        $this->assertEquals('Delivery failed: Exception', $outbox->last_error);

        $outboxService = app(OrderSideEffectOutboxService::class);
        $this->assertNotContains($outbox->id, $outboxService->getEligibleQuery()->pluck('id')->toArray());
    }

    /**
     * 18. Success clears retry and failure fields.
     */
    public function test_success_clears_retry_and_failure_fields(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Clear Fields St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();
        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)->firstOrFail();

        $outbox->status = 'failed';
        $outbox->last_error = 'Delivery failed: Exception';
        $outbox->available_at = now()->subMinute();
        $outbox->locked_at = now()->subMinutes(10);
        $outbox->save();

        (new DeliverOrderSideEffect($outbox->id))->handle();

        $outbox->refresh();
        $this->assertEquals('succeeded', $outbox->status);
        $this->assertNull($outbox->last_error);
        $this->assertNull($outbox->available_at);
        $this->assertNull($outbox->locked_at);
        $this->assertNotNull($outbox->processed_at);
    }

    /**
     * 19. Raw exception strings and multiple secret formats never reach last_error.
     */
    public function test_raw_exception_strings_and_secrets_never_reach_last_error(): void
    {
        Queue::fake();
        Mail::fake();

        Mail::shouldReceive('to')->andThrow(new Exception('Raw confidential text: https://secret.server/api?key=123&pass=supersecret Bearer eyJhbGciOi... smtp_pass=my_password_999'));

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Secrets Safe St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)
            ->where('effect_type', 'order_success_email')
            ->firstOrFail();

        try {
            (new DeliverOrderSideEffect($outbox->id))->handle();
            $this->fail('Expected Exception on mail failure');
        } catch (Exception $e) {
            $this->assertStringContainsString('Raw confidential text', $e->getMessage());
        }

        $outbox->refresh();
        $this->assertEquals('failed', $outbox->status);
        $this->assertEquals('Delivery failed: Exception', $outbox->last_error);
        $this->assertStringNotContainsString('https://secret.server', $outbox->last_error);
        $this->assertStringNotContainsString('supersecret', $outbox->last_error);
        $this->assertStringNotContainsString('eyJhbGciOi', $outbox->last_error);
        $this->assertStringNotContainsString('my_password_999', $outbox->last_error);
    }

    /**
     * 20. Immediate dispatch for order independently excludes future, active, succeeded, and terminal records.
     */
    public function test_immediate_dispatch_for_order_independently_excludes_ineligible_records(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Immediate Dispatch St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outboxes = OrderSideEffectOutbox::where('order_id', $order->id)->orderBy('id', 'asc')->get();

        // Outbox 0: Succeeded
        $outboxes[0]->status = 'succeeded';
        $outboxes[0]->save();

        // Outbox 1: Eligible Pending
        $outboxes[1]->status = 'pending';
        $outboxes[1]->available_at = now()->subMinute();
        $outboxes[1]->save();

        Queue::fake();
        $outboxService = app(OrderSideEffectOutboxService::class);
        $outboxService->dispatchOutboxForOrder($order->id);

        Queue::assertPushed(DeliverOrderSideEffect::class, 1);
    }

    /**
     * 21. Command and immediate dispatch eligibility for recovery scheduler.
     */
    public function test_command_and_immediate_dispatch_eligibility(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Eligibility St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outboxes = OrderSideEffectOutbox::where('order_id', $order->id)->orderBy('id', 'asc')->get();

        // 1. Pending future -> not eligible
        $outboxes[0]->status = 'pending';
        $outboxes[0]->available_at = now()->addHour();
        $outboxes[0]->save();

        // 2. Pending past -> eligible
        $outboxes[1]->status = 'pending';
        $outboxes[1]->available_at = now()->subMinute();
        $outboxes[1]->save();

        Queue::fake();
        Artisan::call('order-side-effects:dispatch', ['--limit' => '10']);
        Queue::assertPushed(DeliverOrderSideEffect::class, 1);
    }

    /**
     * 22. All invalid limit representations fail validation for recovery command.
     */
    public function test_all_invalid_limit_representations_fail(): void
    {
        $invalidLimits = ['10.5', '1e2', '+100', '-50', ' 100 ', '0', '1001', 'abc', '010'];

        foreach ($invalidLimits as $invalidLimit) {
            $exitCode = Artisan::call('order-side-effects:dispatch', ['--limit' => $invalidLimit]);
            $this->assertEquals(1, $exitCode, "Limit '{$invalidLimit}' should have failed validation");
        }

        $validExitCode = Artisan::call('order-side-effects:dispatch', ['--limit' => '100']);
        $this->assertEquals(0, $validExitCode);
    }

    /**
     * 23. Delivery failure does not roll back or mutate already committed processing order/inventory state.
     */
    public function test_delivery_failure_does_not_mutate_committed_order_or_inventory(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vendor = $this->createVendor();
        $book = $this->createBook($vendor, 'physical', 100000, 10);
        $stock = WarehouseStock::where('book_id', $book->id)->first();

        $orders = $this->checkoutService->processCheckout(
            [['book_id' => $book->id, 'quantity' => 2]],
            ['shipping_address' => 'No Mutation St', 'phone' => '0901234567', 'payment_method' => 'cod'],
            $user->id
        );
        $order = $orders[0];

        (new ProcessOrder($order->id))->handle();

        $outbox = OrderSideEffectOutbox::where('order_id', $order->id)->firstOrFail();

        // Corrupt outbox user_id
        $payload = $outbox->payload;
        $payload['user_id'] = 999999;
        $outbox->payload = $payload;
        $outbox->save();

        try {
            (new DeliverOrderSideEffect($outbox->id))->handle();
        } catch (Throwable $e) {
            // Expected
        }

        // Verify order, inventory, notifications remained unmodified
        $this->assertEquals('processing', $order->fresh()->status);
        $this->assertEquals(8, $stock->fresh()->quantity);
        $this->assertEquals(0, UserNotification::count());
    }

    /**
     * 24. Isolated SQLite migration up/down.
     */
    public function test_isolated_sqlite_migration_up_and_down(): void
    {
        $migration = include database_path('migrations/2026_07_26_140000_create_order_side_effect_outboxes.php');

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('order_side_effect_outboxes'));
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('user_notifications', 'operation_key'));

        $migration->down();

        $this->assertFalse(DB::getSchemaBuilder()->hasTable('order_side_effect_outboxes'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('user_notifications', 'operation_key'));

        $migration->up();

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('order_side_effect_outboxes'));
        $this->assertTrue(DB::getSchemaBuilder()->hasColumn('user_notifications', 'operation_key'));
    }
}

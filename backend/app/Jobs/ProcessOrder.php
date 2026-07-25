<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\Order;
use App\Services\Inventory\InventoryReservationService;
use App\Services\OrderSideEffectOutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;

class ProcessOrder implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
        $this->queue = 'default';
    }

    /**
     * Get backoff delays in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30, 60];
    }

    /**
     * Unique identifier for queue locking.
     */
    public function uniqueId(): string
    {
        return "process-order:{$this->orderId}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Resolve link & check legacy order
        $link = CheckoutSessionOrder::where('order_id', $this->orderId)->first();
        if (! $link) {
            throw new RuntimeException("Legacy order {$this->orderId} without checkout session is not supported");
        }

        DB::transaction(function () use ($link) {
            // 2. Lock CheckoutSession
            $session = CheckoutSession::where('id', $link->checkout_session_id)->lockForUpdate()->firstOrFail();

            // 3. Commit inventory reservations cho toàn bộ session
            $reservationService = app(InventoryReservationService::class);
            $reservationService->commitSession($session);

            // 4. Lock & reload target order
            $order = Order::withoutGlobalScopes()
                ->with(['orderItems.book', 'user'])
                ->where('id', $this->orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $outboxService = app(OrderSideEffectOutboxService::class);

            // 5. Check order status for idempotency / validity
            if ($order->status === 'processing') {
                $outboxService->recordOutboxEffects($order);
                DB::afterCommit(function () use ($outboxService, $order) {
                    $outboxService->dispatchOutboxForOrder($order->id);
                });

                return;
            }

            if ($order->status !== 'confirmed') {
                throw new LogicException("Order {$order->id} status is '{$order->status}', expected 'confirmed'");
            }

            // Transition confirmed -> processing
            $order->status = 'processing';
            $order->saveQuietly();

            // 6. Record durable outbox records atomically within transaction
            $outboxService->recordOutboxEffects($order);

            // 7. Dispatch outbox delivery jobs ONLY after transaction commits
            DB::afterCommit(function () use ($outboxService, $order) {
                $outboxService->dispatchOutboxForOrder($order->id);
            });

            Log::info("Job ProcessOrder completed: Order [{$order->order_code}] successfully transitioned to processing with outbox recorded.");
        });
    }
}

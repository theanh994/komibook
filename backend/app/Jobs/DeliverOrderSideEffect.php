<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OrderSuccessMail;
use App\Models\Order;
use App\Models\OrderSideEffectOutbox;
use App\Models\UserNotification;
use App\Services\OrderSideEffectOutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use Throwable;

class DeliverOrderSideEffect implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $outboxId;

    public int $tries = 5;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(int $outboxId)
    {
        $this->outboxId = $outboxId;
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
        return "deliver-outbox:{$this->outboxId}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $outbox = null;
        $order = null;
        $validationException = null;
        $outboxService = app(OrderSideEffectOutboxService::class);

        // 1. Claim & Lock outbox record atomically in short transaction with canonical validation
        DB::transaction(function () use (&$outbox, &$order, $outboxService, &$validationException) {
            $outbox = OrderSideEffectOutbox::where('id', $this->outboxId)
                ->lockForUpdate()
                ->first();

            if (! $outbox) {
                return;
            }

            if ($outbox->status === 'succeeded') {
                return;
            }

            if (! in_array($outbox->status, ['pending', 'failed', 'processing'], true)) {
                $outbox->status = 'failed';
                $outbox->locked_at = null;
                $outbox->available_at = null;
                $outbox->last_error = 'Delivery failed: InvalidOutboxStatus';
                $outbox->attempt_count = max($outbox->attempt_count, $this->tries);
                $outbox->save();
                $outbox = null;

                return;
            }

            if ($outbox->attempt_count >= $this->tries) {
                $outbox->status = 'failed';
                $outbox->locked_at = null;
                $outbox->available_at = null;
                $outbox->last_error = 'Delivery failed: AttemptLimitExceeded';
                $outbox->save();
                $outbox = null;

                return;
            }

            if (in_array($outbox->status, ['pending', 'failed'], true)) {
                if ($outbox->available_at !== null && $outbox->available_at->gt(now())) {
                    $outbox = null;

                    return;
                }
            }

            if ($outbox->status === 'processing') {
                if ($outbox->locked_at === null) {
                    $outbox->status = 'failed';
                    $outbox->locked_at = null;
                    $outbox->available_at = null;
                    $outbox->last_error = 'Delivery failed: NullProcessingLock';
                    $outbox->attempt_count = max($outbox->attempt_count, $this->tries);
                    $outbox->save();
                    $outbox = null;

                    return;
                }

                $isStale = $outbox->locked_at->lte(now()->subMinutes(5));
                if (! $isStale) {
                    $outbox = null;

                    return;
                }
            }

            // Lock and resolve order
            $order = Order::withoutGlobalScopes()
                ->with('user')
                ->where('id', $outbox->order_id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                $outbox->status = 'failed';
                $outbox->locked_at = null;
                $outbox->available_at = null;
                $outbox->last_error = 'Delivery failed: OrderNotFound';
                $outbox->attempt_count = max($outbox->attempt_count, $this->tries);
                $outbox->save();
                $outbox = null;

                return;
            }

            try {
                $outboxService->validateOutboxRecord($outbox, $order);
            } catch (Throwable $e) {
                $validationException = $e;
                $outbox->status = 'failed';
                $outbox->locked_at = null;
                $outbox->available_at = null;
                $outbox->attempt_count = max($outbox->attempt_count, $this->tries);
                $outbox->last_error = $this->sanitizeFailure($e);
                $outbox->save();
                $outbox = null;

                return;
            }

            $outbox->status = 'processing';
            $outbox->locked_at = now();
            $outbox->attempt_count += 1;
            $outbox->save();
        });

        if ($validationException) {
            throw $validationException;
        }

        if (! $outbox || ! $order) {
            return;
        }

        // 2. Perform External Side Effect OUTSIDE transaction
        try {
            if ($outbox->effect_type === 'database_notification') {
                $existing = UserNotification::where('operation_key', $outbox->operation_key)->first();

                if ($existing) {
                    $matches = (int) $existing->user_id === (int) $outbox->payload['user_id']
                        && $existing->title === $outbox->payload['title']
                        && $existing->content === $outbox->payload['content']
                        && $existing->type === 'order'
                        && $existing->data === ($outbox->payload['data'] ?? []);

                    if (! $matches) {
                        throw new LogicException("Conflicting pre-existing notification for operation key {$outbox->operation_key}");
                    }
                } else {
                    UserNotification::create([
                        'operation_key' => $outbox->operation_key,
                        'user_id' => $outbox->payload['user_id'],
                        'title' => $outbox->payload['title'],
                        'content' => $outbox->payload['content'],
                        'type' => 'order',
                        'data' => $outbox->payload['data'] ?? [],
                    ]);
                }
            } elseif ($outbox->effect_type === 'order_success_email') {
                $recipient = $order->user?->email;

                if (empty($recipient)) {
                    throw new LogicException("Missing recipient email for user {$order->user_id}");
                }

                Mail::to($recipient)->send(new OrderSuccessMail($order, $outbox->operation_key));
            } else {
                throw new LogicException("Unsupported outbox effect type '{$outbox->effect_type}'.");
            }

            // 3. Mark Succeeded in short transaction
            DB::transaction(function () {
                $item = OrderSideEffectOutbox::where('id', $this->outboxId)->lockForUpdate()->first();
                if ($item) {
                    $item->status = 'succeeded';
                    $item->processed_at = now();
                    $item->locked_at = null;
                    $item->available_at = null;
                    $item->last_error = null;
                    $item->save();
                }
            });
        } catch (Throwable $e) {
            $sanitizedError = $this->sanitizeFailure($e);
            $isDeterministic = $e instanceof LogicException || $e instanceof InvalidArgumentException;

            DB::transaction(function () use ($isDeterministic, $sanitizedError) {
                $item = OrderSideEffectOutbox::where('id', $this->outboxId)->lockForUpdate()->first();
                if ($item) {
                    $item->locked_at = null;
                    $item->last_error = $sanitizedError;

                    if ($isDeterministic || $item->attempt_count >= $this->tries) {
                        $item->status = 'failed';
                        $item->available_at = null;
                        $item->attempt_count = max($item->attempt_count, $this->tries);
                    } else {
                        $backoffs = $this->backoff();
                        $attemptIndex = max(0, $item->attempt_count - 1);
                        $delaySeconds = $backoffs[$attemptIndex] ?? 60;

                        $item->status = 'failed';
                        $item->available_at = now()->addSeconds($delaySeconds);
                    }

                    $item->save();
                }
            });

            throw $e;
        }
    }

    /**
     * Handle job failure after max retries exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $sanitizedError = $exception ? $this->sanitizeFailure($exception) : 'Delivery failed: MaxAttemptsExhausted';

        DB::transaction(function () use ($sanitizedError) {
            $item = OrderSideEffectOutbox::where('id', $this->outboxId)->lockForUpdate()->first();
            if ($item) {
                $item->status = 'failed';
                $item->locked_at = null;
                $item->available_at = null;
                $item->attempt_count = max($item->attempt_count, $this->tries);
                $item->last_error = $sanitizedError;
                $item->save();
            }
        });
    }

    /**
     * Sanitize failure message into a safe failure category and exception class.
     */
    private function sanitizeFailure(Throwable $e): string
    {
        $shortClass = (new ReflectionClass($e))->getShortName();

        return "Delivery failed: {$shortClass}";
    }
}

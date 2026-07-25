<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\DeliverOrderSideEffect;
use App\Models\Order;
use App\Models\OrderSideEffectOutbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrderSideEffectOutboxService
{
    public const SUPPORTED_EFFECT_TYPES = [
        'database_notification',
        'order_success_email',
    ];

    /**
     * Independently derive canonical effect definition and payload for an order.
     *
     * @return array{effect_type: string, operation_key: string, payload: array<string, mixed>}
     */
    public function buildCanonicalDefinition(Order $order, string $effectType): array
    {
        if (! in_array($effectType, self::SUPPORTED_EFFECT_TYPES, true)) {
            throw new \LogicException("Unsupported outbox effect type '{$effectType}'.");
        }

        $isCod = $order->payment_method === 'cod';

        if ($effectType === 'database_notification') {
            $notificationContent = $isCod
                ? "Đơn hàng {$order->order_code} đã được đặt thành công và đang được xử lý."
                : "Đơn hàng {$order->order_code} đã thanh toán thành công và đang được xử lý.";

            return [
                'effect_type' => 'database_notification',
                'operation_key' => "order-processing:{$order->id}:database-notification",
                'payload' => [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'order_code' => $order->order_code,
                    'payment_method' => $order->payment_method,
                    'title' => 'Đặt hàng thành công',
                    'content' => $notificationContent,
                    'data' => [
                        'order_id' => $order->id,
                        'order_code' => $order->order_code,
                        'icon' => 'shopping_bag',
                        'colorClass' => 'bg-green-100 text-green-600',
                    ],
                ],
            ];
        }

        return [
            'effect_type' => 'order_success_email',
            'operation_key' => "order-processing:{$order->id}:order-success-email",
            'payload' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'order_code' => $order->order_code,
                'recipient_email' => $order->user?->email,
            ],
        ];
    }

    /**
     * Record durable outbox effects within the order processing transaction.
     *
     * @return array<int, OrderSideEffectOutbox>
     */
    public function recordOutboxEffects(Order $order): array
    {
        $effectTypes = ['database_notification'];

        if ($order->user && ! empty($order->user->email)) {
            $effectTypes[] = 'order_success_email';
        }

        $records = [];

        foreach ($effectTypes as $effectType) {
            $def = $this->buildCanonicalDefinition($order, $effectType);
            $opKey = $def['operation_key'];
            $payload = $def['payload'];

            $existing = OrderSideEffectOutbox::where('operation_key', $opKey)
                ->orWhere(function ($q) use ($order, $effectType) {
                    $q->where('order_id', $order->id)->where('effect_type', $effectType);
                })
                ->first();

            if ($existing) {
                $matches = (int) $existing->order_id === (int) $order->id
                    && $existing->effect_type === $effectType
                    && $existing->operation_key === $opKey
                    && $existing->payload === $payload;

                if (! $matches) {
                    throw new \LogicException("Conflicting outbox record for order ID {$order->id} and effect type '{$effectType}'.");
                }

                $records[] = $existing;

                continue;
            }

            $records[] = OrderSideEffectOutbox::create([
                'order_id' => $order->id,
                'operation_key' => $opKey,
                'effect_type' => $effectType,
                'payload' => $payload,
                'status' => 'pending',
                'attempt_count' => 0,
                'available_at' => now(),
            ]);
        }

        return $records;
    }

    /**
     * Validate an existing outbox record against canonical derivation and order state.
     */
    public function validateOutboxRecord(OrderSideEffectOutbox $outbox, Order $order): void
    {
        if (! in_array($outbox->effect_type, self::SUPPORTED_EFFECT_TYPES, true)) {
            throw new \LogicException("Unsupported outbox effect type '{$outbox->effect_type}'.");
        }

        if ((int) $outbox->order_id !== (int) $order->id) {
            throw new \LogicException("Corrupted outbox order ID for outbox ID {$outbox->id}.");
        }

        if (! $order->user || (int) $order->user_id !== (int) ($outbox->payload['user_id'] ?? 0)) {
            throw new \LogicException("Corrupted outbox user relationship for outbox ID {$outbox->id}.");
        }

        if (! in_array($order->status, ['processing', 'completed'], true)) {
            throw new \LogicException("Order ID {$order->id} status is '{$order->status}', expected processing or completed.");
        }

        $canonical = $this->buildCanonicalDefinition($order, $outbox->effect_type);

        if ($outbox->operation_key !== $canonical['operation_key']) {
            throw new \LogicException("Corrupted operation key for outbox ID {$outbox->id}.");
        }

        if ($outbox->payload !== $canonical['payload']) {
            throw new \LogicException("Corrupted payload for outbox ID {$outbox->id}.");
        }
    }

    /**
     * Build base query for currently eligible outbox records (excluding max attempts, future availability, active processing, and terminal states).
     *
     * @return Builder<OrderSideEffectOutbox>
     */
    public function getEligibleQuery(?Carbon $now = null): Builder
    {
        $now = $now ?? now();
        $staleThreshold = (clone $now)->subMinutes(5);

        return OrderSideEffectOutbox::query()
            ->where('attempt_count', '<', 5)
            ->where(function (Builder $query) use ($now, $staleThreshold) {
                $query->where(function (Builder $q) use ($now) {
                    $q->whereIn('status', ['pending', 'failed'])
                        ->where(function (Builder $sub) use ($now) {
                            $sub->whereNull('available_at')->orWhere('available_at', '<=', $now);
                        });
                })
                    ->orWhere(function (Builder $q) use ($staleThreshold) {
                        $q->where('status', 'processing')
                            ->whereNotNull('locked_at')
                            ->where('locked_at', '<=', $staleThreshold);
                    });
            });
    }

    /**
     * Dispatch delivery jobs for currently eligible outbox records of an order after transaction commit.
     */
    public function dispatchOutboxForOrder(int $orderId): void
    {
        $outboxes = $this->getEligibleQuery()
            ->where('order_id', $orderId)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($outboxes as $outbox) {
            DeliverOrderSideEffect::dispatch($outbox->id);
        }
    }
}

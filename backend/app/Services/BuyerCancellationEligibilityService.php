<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryReservationStatus;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\InventoryReservation;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Read-only buyer cancellation assessment. The returned reason codes are
 * stable so list/detail previews and the locked mutation path share facts.
 */
class BuyerCancellationEligibilityService
{
    /**
     * @param iterable<int, Order> $orders
     * @return array<int, array<string, mixed>> keyed by order id
     */
    public function previewsForOrders(iterable $orders, int $userId): array
    {
        $targets = collect($orders)
            ->filter(fn (mixed $order): bool => $order instanceof Order)
            ->keyBy(fn (Order $order): int => (int) $order->id);
        if ($targets->isEmpty()) {
            return [];
        }

        $targetIds = $targets->keys()->all();
        $targetLinksByOrder = CheckoutSessionOrder::whereIn('order_id', $targetIds)
            ->get()
            ->groupBy(fn (CheckoutSessionOrder $link): int => (int) $link->order_id);
        $sessionIds = $targetLinksByOrder->flatten(1)->pluck('checkout_session_id')->filter()->unique()->values()->all();
        $sessionsById = CheckoutSession::whereIn('id', $sessionIds)
            ->get()
            ->keyBy(fn (CheckoutSession $session): int => (int) $session->id);
        $linksBySession = CheckoutSessionOrder::whereIn('checkout_session_id', $sessionIds)
            ->orderBy('order_id')
            ->get()
            ->groupBy(fn (CheckoutSessionOrder $link): int => (int) $link->checkout_session_id);
        $linkedOrderIds = $linksBySession->flatten(1)->pluck('order_id')->unique()->values()->all();
        $ordersById = Order::withoutGlobalScopes()
            ->whereIn('id', $linkedOrderIds)
            ->get()
            ->keyBy(fn (Order $order): int => (int) $order->id);
        $committedSessionIds = InventoryReservation::whereIn('checkout_session_id', $sessionIds)
            ->where('status', InventoryReservationStatus::COMMITTED)
            ->pluck('checkout_session_id')
            ->flip();

        $previews = [];
        foreach ($targets as $target) {
            if ($this->normalized($target->payment_method) === 'cod') {
                $previews[(int) $target->id] = $this->assessCodOrder($target, $userId);
                continue;
            }

            $targetLinks = $targetLinksByOrder->get((int) $target->id) ?? collect();
            if ($targetLinks->count() !== 1) {
                $previews[(int) $target->id] = $this->ineligible('incomplete_session_links');
                continue;
            }

            $link = $targetLinks->first();
            $session = $link ? $sessionsById->get((int) $link->checkout_session_id) : null;
            $sessionLinks = $session ? ($linksBySession->get((int) $session->id) ?? collect()) : collect();
            $sessionOrderIds = $sessionLinks->pluck('order_id')->map(fn ($id): int => (int) $id)->all();
            $sessionOrders = collect($sessionOrderIds)
                ->map(fn (int $id) => $ordersById->get($id))
                ->filter();

            $previews[(int) $target->id] = $this->assessOnlineSession(
                $target,
                $userId,
                $link,
                $session,
                $sessionLinks,
                $sessionOrders,
                $session && $committedSessionIds->has((int) $session->id),
                true,
                true
            );
        }

        return $previews;
    }

    /**
     * @return array<string, mixed>
     */
    public function assessCodOrder(Order $order, int $userId, bool $allowAlreadyCancelled = false): array
    {
        if ((int) $order->user_id !== $userId) {
            return $this->ineligible('target_not_owned');
        }
        if ($this->normalized($order->payment_method) !== 'cod') {
            return $this->ineligible('unsupported_payment_method');
        }
        if ($order->payment_status === 'paid') {
            return $this->ineligible('paid_order', null, ['order_id' => $order->id]);
        }
        if ($order->payment_status !== 'unpaid') {
            return $this->ineligible('invalid_payment_status', null, ['order_id' => $order->id]);
        }
        if ($allowAlreadyCancelled && $order->status === 'cancelled') {
            return $this->eligible($this->scope('single_order', collect([$order])));
        }
        if (
            ! in_array($order->status, ['confirmed', 'processing'], true)
            || ! in_array($order->shipping_status, [null, 'pending_pickup'], true)
        ) {
            return $this->ineligible('invalid_order_state', null, ['order_id' => $order->id]);
        }

        return $this->eligible($this->scope('single_order', collect([$order])));
    }

    /**
     * @param Collection<int, CheckoutSessionOrder> $sessionLinks
     * @param Collection<int, Order> $sessionOrders
     * @return array<string, mixed>
     */
    public function assessOnlineSession(
        Order $target,
        int $userId,
        ?CheckoutSessionOrder $targetLink,
        ?CheckoutSession $session,
        Collection $sessionLinks,
        Collection $sessionOrders,
        bool $hasCommittedReservation,
        bool $allowAlreadyCancelled = false,
        bool $requirePendingOrder = false
    ): array {
        if ((int) $target->user_id !== $userId) {
            return $this->ineligible('target_not_owned');
        }
        if (! in_array($this->normalized($target->payment_method), ['online', 'vnpay', 'demo_wallet', 'wallet'], true)) {
            return $this->ineligible('unsupported_payment_method');
        }
        if (! $targetLink) {
            return $this->ineligible('missing_session_link');
        }
        if (! $session) {
            return $this->ineligible('missing_checkout_session');
        }
        if ((int) $session->user_id !== $userId) {
            return $this->ineligible('session_not_owned');
        }

        $linkedOrderIds = $sessionLinks->pluck('order_id')->map(fn ($id): int => (int) $id)->values();
        $uniqueOrderIds = $linkedOrderIds->unique()->sort()->values();
        if (
            $linkedOrderIds->isEmpty()
            || $linkedOrderIds->count() !== $uniqueOrderIds->count()
            || ! $uniqueOrderIds->contains((int) $target->id)
            || $sessionOrders->count() !== $uniqueOrderIds->count()
        ) {
            return $this->ineligible('incomplete_session_orders');
        }

        $ordersById = $sessionOrders->keyBy(fn (Order $order): int => (int) $order->id);
        $orderedSessionOrders = $uniqueOrderIds->map(fn (int $id) => $ordersById->get($id));
        if ($orderedSessionOrders->contains(fn (mixed $order): bool => ! $order instanceof Order)) {
            return $this->ineligible('incomplete_session_orders');
        }

        $scope = $this->scope('checkout_session', $orderedSessionOrders);
        $hasPendingOrder = false;
        foreach ($orderedSessionOrders as $order) {
            if ((int) $order->user_id !== $userId) {
                return $this->ineligible('sibling_not_owned', $scope, ['order_id' => $order->id]);
            }
            if (! in_array($this->normalized($order->payment_method), ['online', 'vnpay', 'demo_wallet', 'wallet'], true)) {
                return $this->ineligible('unsupported_session_payment_method', $scope, ['order_id' => $order->id]);
            }
            if ($order->payment_status === 'paid') {
                return $this->ineligible('paid_order', $scope, ['order_id' => $order->id]);
            }
            if ($order->payment_status !== 'unpaid') {
                return $this->ineligible('invalid_payment_status', $scope, [
                    'order_id' => $order->id,
                    'payment_status' => $order->payment_status,
                ]);
            }
            if (! $allowAlreadyCancelled || $order->status !== 'cancelled') {
                if ($order->status !== 'pending') {
                    return $this->ineligible('invalid_order_state', $scope, [
                        'order_id' => $order->id,
                        'status' => $order->status,
                    ]);
                }
            }
            if ($order->status === 'pending') {
                $hasPendingOrder = true;
            }
        }

        if ($hasCommittedReservation) {
            return $this->ineligible('committed_reservation', $scope);
        }
        if ($requirePendingOrder && ! $hasPendingOrder) {
            return $this->ineligible('already_converged', $scope);
        }

        return $this->eligible($scope);
    }

    /**
     * @param Collection<int, Order> $orders
     * @return array<string, mixed>
     */
    private function scope(string $type, Collection $orders): array
    {
        $orders = $orders->sortBy(fn (Order $order): int => (int) $order->id)->values();

        return [
            'type' => $type,
            'count' => $orders->count(),
            'order_ids' => $orders->map(fn (Order $order): int => (int) $order->id)->all(),
            'order_codes' => $orders->map(fn (Order $order): string => (string) ($order->order_code ?: $order->id))->all(),
        ];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    private function eligible(array $scope): array
    {
        return ['eligible' => true, 'reason_code' => 'eligible', 'scope' => $scope];
    }

    /**
     * @param array<string, mixed>|null $scope
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function ineligible(string $reasonCode, ?array $scope = null, array $context = []): array
    {
        return ['eligible' => false, 'reason_code' => $reasonCode, 'scope' => $scope, 'context' => $context];
    }

    private function normalized(mixed $value): string
    {
        return is_string($value) ? strtolower(trim($value)) : '';
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryReservationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderTransitionOperation;
use App\Models\PaymentTransaction;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;

class CheckoutSessionLifecycleService
{
    /**
     * Buyer chủ động hủy đơn hàng / checkout session.
     *
     * @return array<int, Order>
     */
    public function cancelByBuyer(int $orderId, int $userId): array
    {
        $order = Order::withoutGlobalScopes()->where('id', $orderId)->first();
        if (! $order) {
            throw new RuntimeException("Order ID {$orderId} not found");
        }

        if ((int) $order->user_id !== $userId) {
            throw new AuthorizationException("User ID {$userId} is not authorized to cancel order ID {$orderId}");
        }

        if (strtolower((string) $order->payment_method) === 'cod') {
            return [$this->cancelCodOrder($orderId, $userId)];
        }

        $link = CheckoutSessionOrder::where('order_id', $order->id)->first();
        if (! $link) {
            throw new RuntimeException("Order ID {$orderId} is not linked to a checkout session");
        }

        return DB::transaction(function () use ($link, $orderId, $userId) {
            // 1. Lock CheckoutSession
            $session = CheckoutSession::where('id', $link->checkout_session_id)->lockForUpdate()->firstOrFail();
            if ((int) $session->user_id !== $userId) {
                throw new AuthorizationException("User ID {$userId} is not authorized to cancel session ID {$session->id}");
            }
            // 2. Lock links & Orders by ID asc
            $sessionLinks = CheckoutSessionOrder::where('checkout_session_id', $session->id)
                ->orderBy('order_id', 'asc')
                ->lockForUpdate()
                ->get();

            $orderIds = $sessionLinks->pluck('order_id')->sort()->values()->toArray();
            if (empty($orderIds)) {
                throw new RuntimeException("CheckoutSession ID {$session->id} has no linked orders");
            }

            $sessionOrders = Order::withoutGlobalScopes()
                ->whereIn('id', $orderIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            if ($sessionOrders->count() !== count($orderIds)) {
                throw new RuntimeException("Incomplete orders in CheckoutSession ID {$session->id}");
            }

            $hasCommittedReservation = InventoryReservation::where('checkout_session_id', $session->id)
                ->where('status', InventoryReservationStatus::COMMITTED)
                ->exists();
            $lockedTarget = $sessionOrders->firstWhere('id', $orderId);
            if (! $lockedTarget) {
                throw new RuntimeException("Order ID {$orderId} is not linked to checkout session ID {$session->id}");
            }

            $assessment = app(BuyerCancellationEligibilityService::class)->assessOnlineSession(
                $lockedTarget,
                $userId,
                $sessionLinks->firstWhere('order_id', $orderId),
                $session,
                $sessionLinks,
                $sessionOrders,
                $hasCommittedReservation,
                true
            );
            if (! ($assessment['eligible'] ?? false)) {
                $this->throwForBuyerCancellationIneligibility($assessment, $session);
            }

            // 3. Lock PaymentTransactions strictly by checkout_session_id and ID asc
            $transactions = PaymentTransaction::where('checkout_session_id', $session->id)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // 4. Convergence check: check if all orders are cancelled, no pending txns, and no reserved inventory
            $allCancelled = $sessionOrders->every(fn ($so) => $so->status === 'cancelled');
            $hasPendingTx = $transactions->contains(fn ($tx) => $tx->status === PaymentTransactionStatus::PENDING);
            $hasActiveReservation = InventoryReservation::where('checkout_session_id', $session->id)
                ->where('status', InventoryReservationStatus::RESERVED)
                ->exists();

            if ($allCancelled && ! $hasPendingTx && ! $hasActiveReservation) {
                return $sessionOrders->all();
            }

            $reservationService = app(InventoryReservationService::class);

            // Perform updates
            foreach ($sessionOrders as $so) {
                if ($so->status !== 'cancelled') {
                    $so->status = 'cancelled';
                    $so->save();
                }
            }

            foreach ($transactions as $tx) {
                if ($tx->status === PaymentTransactionStatus::PENDING) {
                    $tx->status = PaymentTransactionStatus::EXPIRED;
                    $tx->save();
                }
            }

            // 5. Release inventory reservations (reserved -> released)
            $reservationService->releaseSession($session);

            return $sessionOrders->all();
        });
    }

    private function cancelCodOrder(int $orderId, int $userId): Order
    {
        return DB::transaction(function () use ($orderId, $userId) {
            $order = Order::withoutGlobalScopes()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $order->user_id !== $userId) {
                throw new AuthorizationException("User ID {$userId} is not authorized to cancel order ID {$orderId}");
            }
            $assessment = app(BuyerCancellationEligibilityService::class)->assessCodOrder($order, $userId, true);
            if (! ($assessment['eligible'] ?? false)) {
                $this->throwForBuyerCancellationIneligibility($assessment);
            }
            if ($order->status === 'cancelled') {
                return $order;
            }

            $operationKey = "buyer-cancel-cod:{$order->id}";
            if (OrderTransitionOperation::where('operation_key', $operationKey)->exists()) {
                throw new LogicException("Cancellation operation exists but order ID {$order->id} is not cancelled");
            }

            app(InventoryReservationService::class)->restoreCommittedOrder($order, $operationKey);
            $from = $order->status;
            $order->status = 'cancelled';
            $order->save();

            OrderTransitionOperation::create([
                'order_id' => $order->id,
                'operation_key' => $operationKey,
                'actor_type' => 'customer',
                'actor_id' => $userId,
                'transition_kind' => 'order',
                'from_state' => $from,
                'to_state' => 'cancelled',
                'metadata' => ['payment_method' => 'cod'],
                'occurred_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * @param array<string, mixed> $assessment
     */
    private function throwForBuyerCancellationIneligibility(array $assessment, ?CheckoutSession $session = null): never
    {
        $reasonCode = $assessment['reason_code'] ?? 'ineligible';
        $context = $assessment['context'] ?? [];
        $orderId = (int) ($context['order_id'] ?? 0);

        if ($reasonCode === 'paid_order') {
            throw new LogicException('Cannot cancel paid order through buyer cancellation; use the return/refund workflow');
        }
        if ($reasonCode === 'committed_reservation' && $session) {
            throw new LogicException("Cannot cancel session ID {$session->id} with committed inventory reservation");
        }
        if ($reasonCode === 'sibling_not_owned' && $orderId && $session) {
            throw new LogicException("Order ID {$orderId} owner does not match CheckoutSession owner");
        }
        if ($reasonCode === 'invalid_order_state' && $orderId) {
            $status = (string) ($context['status'] ?? 'unknown');
            throw new LogicException("Cannot cancel order ID {$orderId} in status '{$status}'");
        }
        if ($reasonCode === 'invalid_payment_status' && $orderId) {
            $paymentStatus = (string) ($context['payment_status'] ?? 'unknown');
            throw new LogicException("Cannot cancel order ID {$orderId} with payment status '{$paymentStatus}'");
        }

        throw new LogicException("Cannot cancel buyer order: {$reasonCode}");
    }

    /**
     * Hết hạn checkout session (session expiry).
     *
     * @return array<int, Order>
     */
    public function expireSession(CheckoutSession|int $sessionOrId): array
    {
        $sessionId = is_object($sessionOrId) ? $sessionOrId->id : (int) $sessionOrId;

        return DB::transaction(function () use ($sessionId) {
            // 1. Lock CheckoutSession
            $session = CheckoutSession::where('id', $sessionId)->lockForUpdate()->firstOrFail();

            // Fail-closed if session has not reached its expiration time yet
            if (! $session->expires_at || $session->expires_at->isFuture()) {
                throw new LogicException("Cannot expire checkout session ID {$session->id} before its expiration time");
            }

            // Fail-closed if any reservation is COMMITTED
            $hasCommittedReservation = InventoryReservation::where('checkout_session_id', $session->id)
                ->where('status', InventoryReservationStatus::COMMITTED)
                ->exists();
            if ($hasCommittedReservation) {
                throw new LogicException("Cannot expire session ID {$session->id} with committed inventory reservation");
            }

            // 2. Lock links & Orders by ID asc
            $sessionLinks = CheckoutSessionOrder::where('checkout_session_id', $session->id)
                ->orderBy('order_id', 'asc')
                ->lockForUpdate()
                ->get();

            $orderIds = $sessionLinks->pluck('order_id')->sort()->values()->toArray();
            if (empty($orderIds)) {
                throw new RuntimeException("CheckoutSession ID {$session->id} has no linked orders");
            }

            $sessionOrders = Order::withoutGlobalScopes()
                ->whereIn('id', $orderIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            if ($sessionOrders->count() !== count($orderIds)) {
                throw new RuntimeException("Incomplete orders in CheckoutSession ID {$session->id}");
            }

            // Precondition & ownership integrity check
            foreach ($sessionOrders as $so) {
                if ((int) $so->user_id !== (int) $session->user_id) {
                    throw new LogicException("Order ID {$so->id} owner does not match CheckoutSession owner");
                }

                $pm = strtolower((string) $so->payment_method);
                if ($pm !== 'online' && $pm !== 'vnpay') {
                    throw new LogicException("Cannot expire session with COD order ID {$so->id}");
                }

                if ($so->status !== 'pending' && $so->status !== 'cancelled') {
                    throw new LogicException("Cannot expire session with order ID {$so->id} in status '{$so->status}'");
                }

                if ($so->payment_status !== 'unpaid') {
                    throw new LogicException("Cannot expire session with order ID {$so->id} in payment status '{$so->payment_status}'");
                }
            }

            // 3. Lock PaymentTransactions strictly by checkout_session_id and ID asc
            $transactions = PaymentTransaction::where('checkout_session_id', $session->id)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // 4. Convergence check: check if all orders are cancelled, no pending txns, and no reserved inventory
            $allCancelled = $sessionOrders->every(fn ($so) => $so->status === 'cancelled');
            $hasPendingTx = $transactions->contains(fn ($tx) => $tx->status === PaymentTransactionStatus::PENDING);
            $hasActiveReservation = InventoryReservation::where('checkout_session_id', $session->id)
                ->where('status', InventoryReservationStatus::RESERVED)
                ->exists();

            if ($allCancelled && ! $hasPendingTx && ! $hasActiveReservation) {
                return $sessionOrders->all();
            }

            // Perform updates
            foreach ($sessionOrders as $so) {
                if ($so->status !== 'cancelled') {
                    $so->status = 'cancelled';
                    $so->save();
                }
            }

            foreach ($transactions as $tx) {
                if ($tx->status === PaymentTransactionStatus::PENDING) {
                    $tx->status = PaymentTransactionStatus::EXPIRED;
                    $tx->save();
                }
            }

            // 5. Expire inventory reservations (reserved -> expired)
            $reservationService = app(InventoryReservationService::class);
            $reservationService->expireSession($session);

            return $sessionOrders->all();
        });
    }

    /**
     * Tự động quét và hết hạn toàn bộ session đã quá hạn.
     */
    public function expireAllExpiredSessions(int $batchSize = 50): int
    {
        $now = now();
        $expiredSessionIds = CheckoutSession::where('expires_at', '<=', $now)
            ->whereHas('checkoutSessionOrders.order', function ($q) {
                $q->withoutGlobalScopes()
                    ->where('status', 'pending')
                    ->where('payment_status', 'unpaid')
                    ->whereIn('payment_method', ['online', 'vnpay']);
            })
            ->orderBy('id', 'asc')
            ->limit($batchSize)
            ->pluck('id')
            ->toArray();

        $count = 0;
        foreach ($expiredSessionIds as $sessionId) {
            try {
                $this->expireSession($sessionId);
                $count++;
            } catch (\Throwable $e) {
                Log::error(sprintf('Failed to expire checkout session ID %d: %s', $sessionId, get_class($e)));
            }
        }

        return $count;
    }
}

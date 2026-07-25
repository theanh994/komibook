<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\LoyaltyPointLedger;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\OrderTransitionOperation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEarningLedger;
use Illuminate\Support\Facades\DB;
use LogicException;

class OrderFulfillmentService
{
    private const ALLOWED_ACTOR_TYPES = ['vendor', 'system'];

    /**
     * Validate actor identity before authorization.
     */
    private function assertActorAndVendorAuthorization(Order $order, string $actorType, ?int $actorId, array $allowedActors = ['vendor']): void
    {
        if (! in_array($actorType, $allowedActors, true) || ! in_array($actorType, self::ALLOWED_ACTOR_TYPES, true)) {
            throw new LogicException("Unsupported actor type '{$actorType}'.");
        }

        if ($actorType === 'vendor') {
            if ($actorId === null) {
                throw new LogicException('Vendor actor ID is required.');
            }

            $vendor = Vendor::withoutGlobalScopes()
                ->where('user_id', $actorId)
                ->first();

            if (! $vendor || (int) $vendor->id !== (int) $order->vendor_id) {
                throw new LogicException("Vendor ID {$actorId} is not authorized to manage order ID {$order->id}.");
            }
        }

        if ($actorType === 'system') {
            if ($actorId !== null) {
                throw new LogicException('System actor must not specify an actor ID.');
            }
        }
    }

    /**
     * Validate stored transition graph edge against approved edges.
     */
    private function isValidGraphEdge(string $kind, string $fromState, string $toState): bool
    {
        if ($kind === 'order') {
            return ($fromState === 'processing' && $toState === 'shipped')
                || ($fromState === 'processing' && $toState === 'completed');
        }

        if ($kind === 'shipping') {
            $approvedShippingEdges = [
                'pending_pickup' => ['picked_up', 'failed'],
                'picked_up' => ['delivering', 'failed'],
                'delivering' => ['delivered', 'failed'],
            ];

            return isset($approvedShippingEdges[$fromState]) && in_array($toState, $approvedShippingEdges[$fromState], true);
        }

        return false;
    }

    /**
     * Check exact operation matching and idempotency payload.
     */
    private function checkOrVerifyOperation(
        Order $order,
        string $opKey,
        string $transitionKind,
        string $fromState,
        string $toState,
        string $actorType,
        ?int $actorId,
        array $expectedMetadata = []
    ): ?OrderTransitionOperation {
        $existingOp = OrderTransitionOperation::where('operation_key', $opKey)->first();

        if (! $existingOp) {
            return null;
        }

        if (! $this->isValidGraphEdge($transitionKind, $existingOp->from_state, $existingOp->to_state)) {
            throw new LogicException("Stored operation key '{$opKey}' contains an invalid transition edge from '{$existingOp->from_state}' to '{$existingOp->to_state}'.");
        }

        $matches = (int) $existingOp->order_id === (int) $order->id
            && $existingOp->transition_kind === $transitionKind
            && $existingOp->from_state === $fromState
            && $existingOp->to_state === $toState
            && $existingOp->actor_type === $actorType
            && (int) $existingOp->actor_id === (int) $actorId;

        if ($matches) {
            $existingMeta = $existingOp->metadata ?? [];
            if (count($existingMeta) !== count($expectedMetadata)) {
                $matches = false;
            } else {
                foreach ($expectedMetadata as $k => $v) {
                    if (! array_key_exists($k, $existingMeta)) {
                        $matches = false;
                        break;
                    }
                    if ($existingMeta[$k] !== $v) {
                        $matches = false;
                        break;
                    }
                }
            }
        }

        if (! $matches) {
            throw new LogicException("Conflicting operation key '{$opKey}' reuse detected.");
        }

        return $existingOp;
    }

    /**
     * Vendor cập nhật trạng thái đơn hàng (chỉ cho phép processing -> shipped).
     */
    public function updateOrderStatusByVendor(
        Order|int $orderOrId,
        string $newStatus,
        string $actorType,
        ?int $actorId,
        ?string $operationKey = null
    ): Order {
        if ($newStatus !== 'shipped') {
            throw new LogicException('Vendors are only allowed to update order status from processing to shipped.');
        }

        $orderId = is_object($orderOrId) ? $orderOrId->id : (int) $orderOrId;
        $opKey = $operationKey ?? "order-vendor-ship:{$orderId}";

        return DB::transaction(function () use ($orderId, $actorType, $actorId, $opKey) {
            $order = Order::withoutGlobalScopes()->where('id', $orderId)->lockForUpdate()->firstOrFail();

            $this->assertActorAndVendorAuthorization($order, $actorType, $actorId, ['vendor']);

            $fromState = 'processing';
            $expectedMeta = [
                'shipping_status' => 'pending_pickup',
            ];

            $verifiedOp = $this->checkOrVerifyOperation($order, $opKey, 'order', $fromState, 'shipped', $actorType, $actorId, $expectedMeta);

            if ($verifiedOp) {
                if ($order->status === 'shipped') {
                    return $order;
                }
                throw new LogicException("Operation key '{$opKey}' exists but order status is corrupted.");
            }

            if ($order->status === 'shipped') {
                throw new LogicException("State transition to 'shipped' for order ID {$order->id} is not backed by a valid transition operation.");
            }

            if ($order->status !== 'processing') {
                throw new LogicException("Cannot transition order ID {$order->id} from status '{$order->status}' to 'shipped'. Expected 'processing'.");
            }

            $order->status = 'shipped';
            if (empty($order->shipping_status) || $order->shipping_status === 'pending_pickup') {
                $order->shipping_status = 'pending_pickup';
            }
            $order->save();

            OrderTransitionOperation::create([
                'order_id' => $order->id,
                'operation_key' => $opKey,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'transition_kind' => 'order',
                'from_state' => $fromState,
                'to_state' => 'shipped',
                'metadata' => [
                    'shipping_status' => $order->shipping_status,
                ],
                'occurred_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * Cập nhật trạng thái giao hàng mô phỏng.
     */
    public function updateShippingStatus(
        Order|int $orderOrId,
        string $newShippingStatus,
        ?string $carrier,
        ?string $trackingCode,
        string $actorType,
        ?int $actorId,
        ?string $operationKey = null
    ): Order {
        $allowedShippingStatuses = ['pending_pickup', 'picked_up', 'delivering', 'delivered', 'failed'];
        if (! in_array($newShippingStatus, $allowedShippingStatuses, true)) {
            throw new LogicException("Invalid shipping status '{$newShippingStatus}'.");
        }

        $orderId = is_object($orderOrId) ? $orderOrId->id : (int) $orderOrId;

        return DB::transaction(function () use ($orderId, $newShippingStatus, $carrier, $trackingCode, $actorType, $actorId, $operationKey) {
            $order = Order::withoutGlobalScopes()->where('id', $orderId)->lockForUpdate()->firstOrFail();

            $this->assertActorAndVendorAuthorization($order, $actorType, $actorId, ['vendor']);

            $currentShipping = $order->shipping_status ?? 'pending_pickup';

            if ($currentShipping === 'failed' && $newShippingStatus !== 'failed') {
                throw new LogicException("Cannot transition from failed shipping status for order ID {$order->id}.");
            }

            $expectedMeta = [
                'order_status' => $order->status,
                'shipping_carrier' => $carrier ?? $order->shipping_carrier,
                'shipping_tracking_code' => $trackingCode ?? $order->shipping_tracking_code,
            ];

            if ($currentShipping === $newShippingStatus) {
                $existingOp = null;
                if ($operationKey !== null) {
                    $existingOp = OrderTransitionOperation::where('operation_key', $operationKey)->first();
                } else {
                    $existingOp = OrderTransitionOperation::where('order_id', $order->id)
                        ->where('transition_kind', 'shipping')
                        ->where('to_state', $newShippingStatus)
                        ->orderBy('id', 'desc')
                        ->first();
                }

                if (! $existingOp) {
                    throw new LogicException("State transition to '{$newShippingStatus}' is not backed by a valid transition operation.");
                }

                $opKey = $existingOp->operation_key;
                $fromState = $existingOp->from_state;

                if ($newShippingStatus === 'delivered') {
                    return $this->executeOrderCompletion($order, $carrier, $trackingCode, $actorType, $actorId, $opKey);
                }

                $verifiedOp = $this->checkOrVerifyOperation($order, $opKey, 'shipping', $fromState, $newShippingStatus, $actorType, $actorId, $expectedMeta);
                if ($verifiedOp) {
                    return $order;
                }

                throw new LogicException("Conflicting operation key '{$opKey}' reuse detected.");
            }

            $validTransitions = [
                'pending_pickup' => ['picked_up', 'failed'],
                'picked_up' => ['delivering', 'failed'],
                'delivering' => ['delivered', 'failed'],
            ];

            if (! isset($validTransitions[$currentShipping]) || ! in_array($newShippingStatus, $validTransitions[$currentShipping], true)) {
                throw new LogicException("Invalid shipping transition from '{$currentShipping}' to '{$newShippingStatus}'.");
            }

            $opKey = $operationKey ?? "shipping-update:{$order->id}:{$currentShipping}:{$newShippingStatus}";

            if ($newShippingStatus === 'delivered') {
                $existingOp = OrderTransitionOperation::where('operation_key', $opKey)->first();
                if ($existingOp) {
                    return $this->executeOrderCompletion($order, $carrier, $trackingCode, $actorType, $actorId, $opKey);
                }

                if ($currentShipping !== 'delivering') {
                    throw new LogicException("Cannot complete delivery from shipping status '{$currentShipping}'. Expected 'delivering'.");
                }

                return $this->executeOrderCompletion($order, $carrier, $trackingCode, $actorType, $actorId, $opKey);
            }

            $existingOp = $this->checkOrVerifyOperation($order, $opKey, 'shipping', $currentShipping, $newShippingStatus, $actorType, $actorId, $expectedMeta);
            if ($existingOp) {
                return $order;
            }

            $fromState = $currentShipping;
            $order->shipping_status = $newShippingStatus;
            if (! empty($carrier)) {
                $order->shipping_carrier = $carrier;
            }
            if (! empty($trackingCode)) {
                $order->shipping_tracking_code = $trackingCode;
            }
            $order->save();

            OrderTransitionOperation::create([
                'order_id' => $order->id,
                'operation_key' => $opKey,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'transition_kind' => 'shipping',
                'from_state' => $fromState,
                'to_state' => $newShippingStatus,
                'metadata' => $expectedMeta,
                'occurred_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * Hoàn tất đơn hàng ebook trực tiếp (processing -> completed).
     */
    public function completeEbookOrder(
        Order|int $orderOrId,
        string $actorType,
        ?int $actorId,
        ?string $operationKey = null
    ): Order {
        $orderId = is_object($orderOrId) ? $orderOrId->id : (int) $orderOrId;
        $opKey = $operationKey ?? "ebook-complete:{$orderId}";

        return DB::transaction(function () use ($orderId, $actorType, $actorId, $opKey) {
            $order = Order::withoutGlobalScopes()->where('id', $orderId)->lockForUpdate()->firstOrFail();

            $this->assertActorAndVendorAuthorization($order, $actorType, $actorId, ['vendor', 'system']);

            $order->loadMissing('orderItems.book');
            if ($order->orderItems->isEmpty()) {
                throw new LogicException("Cannot complete ebook order ID {$order->id} with no items.");
            }

            $hasNonEbook = $order->orderItems->contains(function ($item) {
                return ! $item->book || $item->book->type !== 'ebook';
            });

            if ($hasNonEbook) {
                throw new LogicException("Physical or mixed order ID {$order->id} cannot be completed via ebook fulfillment.");
            }

            return $this->executeOrderCompletion($order, null, null, $actorType, $actorId, $opKey, true);
        });
    }

    /**
     * Thực thi quy trình hoàn tất đơn hàng (Order Completion) atomically.
     */
    private function executeOrderCompletion(
        Order $order,
        ?string $carrier,
        ?string $trackingCode,
        string $actorType,
        ?int $actorId,
        string $opKey,
        bool $isEbook = false
    ): Order {
        // Order Status Precondition
        if (! $isEbook && $order->status !== 'shipped' && $order->status !== 'completed') {
            throw new LogicException("Cannot complete physical order ID {$order->id} from status '{$order->status}'. Expected 'shipped'.");
        }

        if ($isEbook && $order->status !== 'processing' && $order->status !== 'completed') {
            throw new LogicException("Cannot complete ebook order ID {$order->id} from status '{$order->status}'. Expected 'processing'.");
        }

        $pm = strtolower((string) $order->payment_method);
        if ($pm !== 'cod' && $order->payment_status !== 'paid') {
            throw new LogicException("Cannot complete unpaid online order ID {$order->id}.");
        }

        // Lock & Validate immutable snapshot
        $sessionOrder = CheckoutSessionOrder::where('order_id', $order->id)->lockForUpdate()->first();
        if (! $sessionOrder) {
            throw new LogicException("Missing immutable CheckoutSessionOrder snapshot for order ID {$order->id}.");
        }

        if (empty($sessionOrder->checkout_session_id)) {
            throw new LogicException("Missing checkout session for snapshot in order ID {$order->id}.");
        }

        if (empty($sessionOrder->vendor_id) || empty($order->vendor_id) || (int) $sessionOrder->vendor_id !== (int) $order->vendor_id) {
            throw new LogicException("Inconsistent vendor in snapshot for order ID {$order->id}.");
        }

        $session = CheckoutSession::where('id', $sessionOrder->checkout_session_id)->lockForUpdate()->first();
        if (! $session) {
            throw new LogicException("Missing checkout session for snapshot in order ID {$order->id}.");
        }

        if ((int) $session->user_id !== (int) $order->user_id) {
            throw new LogicException("Inconsistent customer in snapshot for order ID {$order->id}.");
        }

        $sessionCurrency = $session->currency ?? 'VND';
        if ($sessionCurrency !== 'VND') {
            throw new LogicException("Invalid currency '{$sessionCurrency}' for order ID {$order->id}. Only VND supported.");
        }

        $vendor = Vendor::withoutGlobalScopes()->where('id', $order->vendor_id)->lockForUpdate()->first();
        if (! $vendor) {
            throw new LogicException("Inconsistent vendor in snapshot for order ID {$order->id}.");
        }

        $order->loadMissing('orderItems.book');
        $hasVendorMismatch = $order->orderItems->contains(function ($item) use ($order) {
            return ! $item->book || (int) $item->book->vendor_id !== (int) $order->vendor_id;
        });

        if ($hasVendorMismatch) {
            throw new LogicException("Inconsistent vendor in snapshot for order ID {$order->id}.");
        }

        if ((int) $sessionOrder->order_id !== (int) $order->id) {
            throw new LogicException("Inconsistent snapshot data for order ID {$order->id}.");
        }

        $grossAmount = (int) $sessionOrder->total_amount;
        $commissionAmount = (int) $sessionOrder->commission_amount;

        if ($grossAmount < 0 || $commissionAmount < 0 || $commissionAmount > $grossAmount) {
            throw new LogicException("Invalid snapshot commission or gross amount for order ID {$order->id}.");
        }

        $netAmount = $grossAmount - $commissionAmount;
        $points = (int) floor($grossAmount / 10000);

        // Lock Projections
        $user = User::where('id', $order->user_id)->lockForUpdate()->firstOrFail();

        $fromState = $isEbook ? 'processing' : ($order->shipping_status === 'delivered' ? 'delivering' : ($order->shipping_status ?? 'delivering'));
        $toState = $isEbook ? 'completed' : 'delivered';
        $kind = $isEbook ? 'order' : 'shipping';

        $expectedCompletionMetadata = $isEbook ? [
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'gross_amount' => $grossAmount,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'points' => $points,
        ] : [
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'shipping_carrier' => $carrier ?? $order->shipping_carrier,
            'shipping_tracking_code' => $trackingCode ?? $order->shipping_tracking_code,
            'gross_amount' => $grossAmount,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'points' => $points,
        ];

        // Check Idempotency & Ledger Corruption
        $existingOp = OrderTransitionOperation::where('operation_key', $opKey)->first();
        if ($existingOp) {
            $this->checkOrVerifyOperation($order, $opKey, $kind, $fromState, $toState, $actorType, $actorId, $expectedCompletionMetadata);

            $earningLedger = VendorEarningLedger::where('order_id', $order->id)->first();
            $pointLedger = LoyaltyPointLedger::where('order_id', $order->id)->first();

            if (! $earningLedger
                || (int) $earningLedger->vendor_id !== (int) $order->vendor_id
                || $earningLedger->operation_key !== "vendor-earning:{$order->id}"
                || (int) $earningLedger->gross_amount !== $grossAmount
                || (int) $earningLedger->commission_amount !== $commissionAmount
                || (int) $earningLedger->net_amount !== $netAmount
                || $earningLedger->currency !== 'VND'
            ) {
                throw new LogicException("Corrupted vendor earning ledger state for operation key '{$opKey}'.");
            }

            if ($points > 0) {
                if (! $pointLedger
                    || (int) $pointLedger->user_id !== (int) $order->user_id
                    || $pointLedger->operation_key !== "loyalty-point:{$order->id}"
                    || $pointLedger->type !== 'order_completed'
                    || (int) $pointLedger->points !== $points
                ) {
                    throw new LogicException("Corrupted loyalty point ledger state for operation key '{$opKey}'.");
                }
            } else {
                if ($pointLedger !== null) {
                    throw new LogicException("Unexpected loyalty point ledger for zero-point order ID {$order->id}.");
                }
            }

            if ($order->status !== 'completed' || $order->payment_status !== 'paid' || (! $isEbook && $order->shipping_status !== 'delivered')) {
                throw new LogicException("Corrupted order projection state for operation key '{$opKey}'.");
            }

            $cumulativeVendorEarnings = (int) VendorEarningLedger::where('vendor_id', $order->vendor_id)->sum('net_amount');
            if ((int) $vendor->balance < $cumulativeVendorEarnings) {
                throw new LogicException("Vendor balance projection is below cumulative durable ledger contribution for operation key '{$opKey}'.");
            }

            if ($points > 0) {
                $cumulativeUserPoints = (int) LoyaltyPointLedger::where('user_id', $order->user_id)->sum('points');
                if ((int) $user->points < $cumulativeUserPoints) {
                    throw new LogicException("User points projection is below cumulative durable ledger contribution for operation key '{$opKey}'.");
                }
            }

            return $order;
        }

        if ($order->status === 'completed') {
            throw new LogicException("State transition to 'completed' for order ID {$order->id} is not backed by a valid transition operation.");
        }

        // Insert Ledgers BEFORE Updating Projections
        OrderTransitionOperation::create([
            'order_id' => $order->id,
            'operation_key' => $opKey,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'transition_kind' => $kind,
            'from_state' => $fromState,
            'to_state' => $toState,
            'metadata' => $expectedCompletionMetadata,
            'occurred_at' => now(),
        ]);

        if ($points > 0) {
            LoyaltyPointLedger::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'operation_key' => "loyalty-point:{$order->id}",
                'type' => 'order_completed',
                'points' => $points,
            ]);
        }

        VendorEarningLedger::create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'operation_key' => "vendor-earning:{$order->id}",
            'gross_amount' => $grossAmount,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'currency' => 'VND',
        ]);

        // Update Projections ONLY AFTER Ledgers Created
        $order->status = 'completed';
        if (! $isEbook) {
            $order->shipping_status = 'delivered';
            if (! empty($carrier)) {
                $order->shipping_carrier = $carrier;
            }
            if (! empty($trackingCode)) {
                $order->shipping_tracking_code = $trackingCode;
            }
        }
        $order->payment_status = 'paid';
        $order->save();

        $vendor->balance += $netAmount;
        $vendor->save();

        if ($points > 0) {
            $user->points += $points;

            $nextTier = MembershipTier::where('min_points', '<=', $user->points)
                ->orderBy('min_points', 'desc')
                ->first();

            if ($nextTier && (! $user->membership_tier_id || $nextTier->id !== $user->membership_tier_id)) {
                $user->membership_tier_id = $nextTier->id;
            }

            $user->save();
        }

        return $order;
    }
}

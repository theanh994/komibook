<?php

namespace App\Services;

use App\Enums\InventoryReservationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundTransaction;
use App\Models\RefundTransactionAttempt;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\ReturnRequestTransition;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorFinancialHold;
use App\Models\WarehouseStock;
use App\Services\Refunds\RefundGatewayInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ReturnRefundService
{
    private const ACTIVE_RETURN_STATES = [
        'requested',
        'under_review',
        'approved',
        'item_received',
        'refund_processing',
        'refund_failed',
        'refunded',
    ];

    private const EDGES = [
        'requested' => ['under_review', 'rejected'],
        'under_review' => ['approved', 'rejected'],
        'approved' => ['item_received'],
        'item_received' => ['refund_processing'],
        'refund_processing' => ['refunded', 'refund_failed'],
        'refund_failed' => ['refund_processing'],
    ];

    public function __construct(private readonly RefundGatewayInterface $refundGateway) {}

    /**
     * @param  array<int, array{order_item_id:int, quantity:int}>  $requestedItems
     */
    public function createRequest(
        int $orderId,
        User $buyer,
        array $requestedItems,
        string $reason,
        string $operationKey
    ): ReturnRequest {
        return DB::transaction(function () use ($orderId, $buyer, $requestedItems, $reason, $operationKey) {
            $existing = ReturnRequestTransition::where('operation_key', $operationKey)->first();
            if ($existing) {
                $existingReturn = ReturnRequest::with(['items.orderItem', 'transitions'])
                    ->findOrFail($existing->return_request_id);
                if ((int) $existingReturn->order_id !== $orderId || (int) $existingReturn->user_id !== (int) $buyer->id) {
                    throw new LogicException('Khóa thao tác đã được dùng cho yêu cầu trả hàng khác.');
                }

                return $existingReturn;
            }

            $order = Order::withoutGlobalScopes()
                ->with(['orderItems.book', 'invoiceSnapshot'])
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $order->user_id !== (int) $buyer->id) {
                throw new AuthorizationException('Bạn không có quyền trả đơn hàng này.');
            }
            if ($order->status !== 'completed' || $order->shipping_status !== 'delivered') {
                throw new LogicException('Chỉ đơn hàng vật lý đã giao mới có thể yêu cầu trả hàng.');
            }

            $deliveredAt = $order->transitionOperations()
                ->where('transition_kind', 'shipping')
                ->where('to_state', 'delivered')
                ->max('occurred_at');
            if (! $deliveredAt || now()->isAfter(Carbon::parse($deliveredAt)->addDays(7))) {
                throw new LogicException('Đơn hàng đã quá thời hạn trả hàng 7 ngày.');
            }
            if (! $order->invoiceSnapshot) {
                throw new LogicException('Đơn hàng không có invoice snapshot để tính hoàn tiền.');
            }

            $normalized = collect($requestedItems)
                ->groupBy('order_item_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'));
            if ($normalized->isEmpty()) {
                throw new LogicException('Yêu cầu trả hàng phải có ít nhất một sản phẩm.');
            }

            $orderItems = $order->orderItems->keyBy('id');
            $invoiceLines = collect($order->invoiceSnapshot->line_items)->keyBy('order_item_id');
            $refundLines = [];
            $refundTotal = 0;
            $invoiceSubtotal = max(1, (int) $order->invoiceSnapshot->subtotal_amount);

            foreach ($normalized as $orderItemId => $quantity) {
                /** @var OrderItem|null $orderItem */
                $orderItem = $orderItems->get((int) $orderItemId);
                if (! $orderItem || $quantity <= 0 || $quantity > $orderItem->quantity) {
                    throw new LogicException('Sản phẩm hoặc số lượng trả không hợp lệ.');
                }
                $invoiceLine = $invoiceLines->get((int) $orderItem->id);
                if (! $invoiceLine) {
                    throw new LogicException('Invoice snapshot không có dòng sản phẩm cần hoàn.');
                }
                if (($invoiceLine['type'] ?? null) !== 'physical') {
                    throw new LogicException('E-book không thuộc luồng trả hàng vật lý.');
                }

                $provenance = $orderItem->product_taxonomy_snapshot['provenance']
                    ?? $invoiceLine['provenance']
                    ?? null;
                $returnable = $orderItem->return_policy_snapshot['is_returnable']
                    ?? $invoiceLine['return_policy_snapshot']['is_returnable']
                    ?? false;
                if ($provenance !== 'used_resale' || ! $returnable) {
                    throw new LogicException('Chỉ sách cũ đủ điều kiện mới thuộc luồng trả hàng và hoàn tiền.');
                }

                $alreadyRequested = (int) ReturnRequestItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->whereHas('returnRequest', fn ($query) => $query->whereIn('status', self::ACTIVE_RETURN_STATES))
                    ->sum('quantity');
                if ($alreadyRequested + $quantity > $orderItem->quantity) {
                    throw new LogicException('Số lượng trả vượt quá số lượng chưa có yêu cầu hoàn trả.');
                }

                $unitAmount = (int) ($invoiceLine['unit_price'] ?? 0);
                $gross = $unitAmount * $quantity;
                $refundAmount = (int) round(
                    ((int) $order->invoiceSnapshot->total_amount * $gross) / $invoiceSubtotal
                );
                $refundLines[] = [$orderItem, $quantity, $refundAmount, $unitAmount];
                $refundTotal += $refundAmount;
            }

            $alreadyReserved = (int) ReturnRequest::where('order_id', $order->id)
                ->whereIn('status', self::ACTIVE_RETURN_STATES)
                ->sum('refund_amount');
            $remainingRefundable = max(0, (int) $order->total_amount - $alreadyReserved);
            if ($remainingRefundable === 0) {
                throw new LogicException('Đơn hàng không còn giá trị có thể hoàn.');
            }
            $refundTotal = min($refundTotal, $remainingRefundable);
            $overflow = (int) collect($refundLines)->sum(fn ($line) => $line[2]) - $refundTotal;
            for ($index = count($refundLines) - 1; $index >= 0 && $overflow > 0; $index--) {
                $reduction = min($overflow, $refundLines[$index][2]);
                $refundLines[$index][2] -= $reduction;
                $overflow -= $reduction;
            }

            $return = ReturnRequest::create([
                'code' => (string) Str::uuid(),
                'order_id' => $order->id,
                'user_id' => $buyer->id,
                'vendor_id' => $order->vendor_id,
                'status' => 'requested',
                'currency' => $order->invoiceSnapshot->currency,
                'refund_amount' => $refundTotal,
                'reason' => $reason,
                'requested_at' => now(),
            ]);

            foreach ($refundLines as [$orderItem, $quantity, $refundAmount, $unitAmount]) {
                ReturnRequestItem::create([
                    'return_request_id' => $return->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => $quantity,
                    'unit_amount' => $unitAmount,
                    'refund_amount' => $refundAmount,
                ]);
            }

            $this->recordTransition($return, null, 'requested', 'customer', $buyer->id, $reason, $operationKey);

            return $return->load(['items.orderItem', 'transitions']);
        });
    }

    public function transition(
        int $returnId,
        string $target,
        User $actor,
        string $operationKey,
        ?string $reason = null
    ): ReturnRequest {
        return DB::transaction(function () use ($returnId, $target, $actor, $operationKey, $reason) {
            $existing = ReturnRequestTransition::where('operation_key', $operationKey)->first();
            if ($existing) {
                $existingReturn = ReturnRequest::with(['items.orderItem.book', 'transitions', 'refundTransaction.attempts'])
                    ->findOrFail($existing->return_request_id);
                $this->authorizeStaffActor($existingReturn, $actor);
                if ((int) $existingReturn->id !== $returnId || $existing->to_state !== $target) {
                    throw new LogicException('Khóa thao tác đã được dùng cho chuyển trạng thái khác.');
                }

                return $existingReturn;
            }

            $return = ReturnRequest::whereKey($returnId)->lockForUpdate()->firstOrFail();
            $this->authorizeStaffActor($return, $actor);
            $from = $return->status;
            if (! in_array($target, self::EDGES[$from] ?? [], true)) {
                throw new LogicException("Không thể chuyển yêu cầu từ {$from} sang {$target}.");
            }

            if ($target === 'approved') {
                $holdAmount = $this->calculateVendorReversal($return)['net_amount'];
                VendorFinancialHold::firstOrCreate(
                    ['return_request_id' => $return->id],
                    [
                        'vendor_id' => $return->vendor_id,
                        'operation_key' => "refund-hold:{$return->id}",
                        'amount' => $holdAmount,
                        'currency' => $return->currency,
                        'status' => 'active',
                    ]
                );
                $return->approved_at = now();
            } elseif ($target === 'item_received') {
                $this->restoreInventory($return);
                $return->item_received_at = now();
            } elseif ($target === 'refund_processing') {
                $this->prepareRefund($return);
                $return->refund_started_at = now();
                $return->order()->withoutGlobalScopes()->update(['refund_status' => 'refunding']);
            } elseif ($target === 'rejected') {
                $return->rejected_at = now();
                $return->resolution_reason = $reason;
            }

            $return->status = $target;
            $return->save();
            $this->recordTransition($return, $from, $target, $actor->role, $actor->id, $reason, $operationKey);

            return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
        });
    }

    public function processRefund(
        int $returnId,
        User $actor,
        string $operationKey,
        string $clientIp,
        ?string $manualEvidence = null
    ): ReturnRequest {
        $previousAttempt = RefundTransactionAttempt::where('operation_key', $operationKey)->first();
        if ($previousAttempt) {
            $return = ReturnRequest::whereKey($returnId)->firstOrFail();
            $this->authorizeStaffActor($return, $actor);
            if ((int) $previousAttempt->refundTransaction->return_request_id !== $returnId) {
                throw new LogicException('Khóa thao tác hoàn tiền đã được dùng cho yêu cầu khác.');
            }
            if ($previousAttempt->status === 'succeeded' && $return->status !== 'refunded') {
                return $this->finalizeRefund($returnId, $actor, $operationKey.':complete');
            }
            if (in_array($previousAttempt->status, ['processing', 'pending'], true)) {
                if ($previousAttempt->status === 'pending') {
                    return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
                }
                throw new LogicException('Giao dịch hoàn tiền này đang được xử lý.');
            }

            return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
        }

        $return = DB::transaction(function () use ($returnId, $actor, $operationKey) {
            $return = ReturnRequest::whereKey($returnId)->lockForUpdate()->firstOrFail();
            $this->authorizeStaffActor($return, $actor);

            if ($return->status === 'refunded') {
                return $return->load(['order.checkoutSessionOrder.checkoutSession.paymentTransactions', 'refundTransaction.attempts']);
            }
            if ($return->status === 'item_received' || $return->status === 'refund_failed') {
                return $this->transition($return->id, 'refund_processing', $actor, $operationKey);
            }
            if ($return->status !== 'refund_processing') {
                throw new LogicException('Yêu cầu chưa sẵn sàng để hoàn tiền.');
            }

            return $return->load(['order.checkoutSessionOrder.checkoutSession.paymentTransactions', 'refundTransaction']);
        });

        $refund = $return->refundTransaction;
        if (! $refund) {
            throw new LogicException('Không tìm thấy giao dịch hoàn tiền.');
        }

        if ($refund->provider === 'cod') {
            if (! $manualEvidence) {
                throw new LogicException('Hoàn tiền COD cần chứng từ hoặc mã tham chiếu.');
            }

            return $this->finalizeRefund($return->id, $actor, $operationKey.':complete', $manualEvidence);
        }

        $payment = $refund->paymentTransaction;
        if (! $payment) {
            throw new LogicException('Không tìm thấy giao dịch thanh toán gốc.');
        }

        $attempt = DB::transaction(function () use ($refund, $operationKey) {
            $lockedRefund = RefundTransaction::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($lockedRefund->attempts()->whereIn('status', ['processing', 'pending'])->exists()) {
                throw new LogicException('Giao dịch hoàn tiền này đang được xử lý.');
            }

            return RefundTransactionAttempt::create([
                'refund_transaction_id' => $lockedRefund->id,
                'operation_key' => $operationKey,
                'attempt_number' => $lockedRefund->attempts()->count() + 1,
                'status' => 'processing',
                'request_payload' => [],
                'response_payload' => [],
                'attempted_at' => now(),
            ]);
        });

        try {
            $result = $this->refundGateway->refund($refund->load('returnRequest'), $payment, (string) $actor->id, $clientIp);
        } catch (\Throwable $exception) {
            $result = [
                'successful' => false,
                'pending' => false,
                'provider_reference' => null,
                'request' => [],
                'response' => [],
                'failure_reason' => $exception->getMessage(),
            ];
        }

        $attempt->update([
            'status' => $result['successful'] ? 'succeeded' : ($result['pending'] ? 'pending' : 'failed'),
            'request_payload' => $result['request'],
            'response_payload' => $result['response'],
            'failure_reason' => $result['failure_reason'],
        ]);

        if ($result['successful']) {
            $refund->provider_reference = $result['provider_reference'];
            $refund->save();

            return $this->finalizeRefund($return->id, $actor, $operationKey.':complete');
        }

        if ($result['pending']) {
            return $return->fresh()->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
        }

        return $this->failRefund($return->id, $actor, $operationKey.':failed', $result['failure_reason']);
    }

    public function reconcileRefund(
        int $returnId,
        User $actor,
        string $operationKey,
        string $clientIp
    ): ReturnRequest {
        $previousAttempt = RefundTransactionAttempt::where('operation_key', $operationKey)->first();
        if ($previousAttempt) {
            $return = ReturnRequest::whereKey($returnId)->firstOrFail();
            $this->authorizeStaffActor($return, $actor);
            if ((int) $previousAttempt->refundTransaction->return_request_id !== $returnId) {
                throw new LogicException('Khóa đối soát đã được dùng cho yêu cầu khác.');
            }
            if ($previousAttempt->status === 'succeeded' && $return->status !== 'refunded') {
                return $this->finalizeRefund($returnId, $actor, $operationKey.':complete');
            }

            return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
        }

        $return = ReturnRequest::with(['refundTransaction.paymentTransaction'])
            ->whereKey($returnId)
            ->firstOrFail();
        $this->authorizeStaffActor($return, $actor);
        if ($return->status === 'refunded') {
            return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
        }
        if ($return->status !== 'refund_processing' || $return->refundTransaction?->provider !== 'vnpay') {
            throw new LogicException('Chỉ giao dịch VNPAY đang xử lý mới có thể đối soát.');
        }
        if (! $return->refundTransaction->paymentTransaction) {
            throw new LogicException('Không tìm thấy giao dịch thanh toán gốc để đối soát.');
        }

        $attempt = DB::transaction(function () use ($return, $operationKey) {
            $refund = RefundTransaction::whereKey($return->refundTransaction->id)->lockForUpdate()->firstOrFail();

            return RefundTransactionAttempt::create([
                'refund_transaction_id' => $refund->id,
                'operation_key' => $operationKey,
                'attempt_number' => $refund->attempts()->count() + 1,
                'status' => 'processing',
                'request_payload' => [],
                'response_payload' => [],
                'attempted_at' => now(),
            ]);
        });

        try {
            $result = $this->refundGateway->queryRefund(
                $return->refundTransaction->load('returnRequest'),
                $return->refundTransaction->paymentTransaction,
                $operationKey,
                $clientIp
            );
        } catch (\Throwable $exception) {
            $result = [
                'successful' => false,
                'pending' => false,
                'provider_reference' => null,
                'request' => [],
                'response' => [],
                'failure_reason' => $exception->getMessage(),
            ];
        }

        $attempt->update([
            'status' => $result['successful'] ? 'succeeded' : ($result['pending'] ? 'pending' : 'failed'),
            'request_payload' => $result['request'],
            'response_payload' => $result['response'],
            'failure_reason' => $result['failure_reason'],
        ]);

        if ($result['successful']) {
            $return->refundTransaction->update(['provider_reference' => $result['provider_reference']]);

            return $this->finalizeRefund($returnId, $actor, $operationKey.':complete');
        }

        return $return->fresh()->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
    }

    private function prepareRefund(ReturnRequest $return): RefundTransaction
    {
        $order = Order::withoutGlobalScopes()
            ->with('checkoutSessionOrder.checkoutSession.paymentTransactions')
            ->findOrFail($return->order_id);
        $provider = $order->payment_method === 'cod' ? 'cod' : 'vnpay';
        $payment = $provider === 'vnpay'
            ? $order->checkoutSessionOrder?->checkoutSession?->paymentTransactions
                ?->first(fn ($transaction) => $transaction->status === PaymentTransactionStatus::PAID)
            : null;

        if ($provider === 'vnpay' && ! $payment) {
            throw new LogicException('Không tìm thấy giao dịch VNPAY đã thanh toán.');
        }

        if ($payment) {
            $reserved = (int) RefundTransaction::where('payment_transaction_id', $payment->id)
                ->whereIn('status', ['pending', 'processing', 'refunded'])
                ->sum('amount');
            if ($reserved + $return->refund_amount > $payment->amount) {
                throw new LogicException('Tổng tiền hoàn vượt quá giao dịch thanh toán gốc.');
            }
        }

        $refund = RefundTransaction::firstOrCreate(
            ['return_request_id' => $return->id],
            [
                'payment_transaction_id' => $payment?->id,
                'provider' => $provider,
                'idempotency_key' => "refund:{$return->code}",
                'amount' => $return->refund_amount,
                'currency' => $return->currency,
                'status' => 'processing',
                'processing_at' => now(),
            ]
        );
        if ($refund->status !== 'refunded') {
            $refund->status = 'processing';
            $refund->failure_reason = null;
            $refund->failed_at = null;
            $refund->processing_at = now();
            $refund->save();
        }

        return $refund;
    }

    private function finalizeRefund(
        int $returnId,
        User $actor,
        string $operationKey,
        ?string $evidence = null
    ): ReturnRequest {
        return DB::transaction(function () use ($returnId, $actor, $operationKey, $evidence) {
            $return = ReturnRequest::whereKey($returnId)->lockForUpdate()->firstOrFail();
            if ($return->status === 'refunded') {
                return $return->load(['refundTransaction.attempts', 'transitions']);
            }
            if ($return->status !== 'refund_processing') {
                throw new LogicException('Yêu cầu không ở trạng thái đang hoàn tiền.');
            }

            $refund = RefundTransaction::where('return_request_id', $return->id)->lockForUpdate()->firstOrFail();
            $refund->status = 'refunded';
            $refund->evidence = $evidence ?? $refund->evidence;
            $refund->failure_reason = null;
            $refund->refunded_at = now();
            $refund->save();

            $this->reverseFinancialEffects($return);

            $hold = VendorFinancialHold::where('return_request_id', $return->id)->lockForUpdate()->first();
            if ($hold && $hold->status === 'active') {
                $hold->status = 'consumed';
                $hold->consumed_at = now();
                $hold->save();
            }

            $from = $return->status;
            $return->status = 'refunded';
            $return->refunded_at = now();
            $return->save();
            $this->recordTransition($return, $from, 'refunded', $actor->role, $actor->id, $evidence, $operationKey);
            $this->updateOrderRefundProjection($return);

            return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
        });
    }

    private function failRefund(int $returnId, User $actor, string $operationKey, ?string $reason): ReturnRequest
    {
        return DB::transaction(function () use ($returnId, $actor, $operationKey, $reason) {
            $return = ReturnRequest::whereKey($returnId)->lockForUpdate()->firstOrFail();
            if ($return->status !== 'refund_processing') {
                throw new LogicException('Yêu cầu không ở trạng thái đang hoàn tiền.');
            }

            $refund = RefundTransaction::where('return_request_id', $return->id)->lockForUpdate()->firstOrFail();
            $refund->status = 'failed';
            $refund->failure_reason = $reason;
            $refund->failed_at = now();
            $refund->save();

            $return->status = 'refund_failed';
            $return->save();
            $this->recordTransition($return, 'refund_processing', 'refund_failed', $actor->role, $actor->id, $reason, $operationKey);

            return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
        });
    }

    private function restoreInventory(ReturnRequest $return): void
    {
        $return->load('items.orderItem.inventoryReservation.allocations');

        foreach ($return->items as $returnItem) {
            if ($returnItem->inventory_restored_at) {
                continue;
            }

            $reservation = $returnItem->orderItem->inventoryReservation;
            if (! $reservation || $reservation->status !== InventoryReservationStatus::COMMITTED) {
                throw new LogicException('Không tìm thấy committed inventory reservation để hoàn kho.');
            }

            $remaining = $returnItem->quantity;
            foreach ($reservation->allocations()->orderBy('id')->lockForUpdate()->get() as $allocation) {
                if ($remaining <= 0) {
                    break;
                }

                $alreadyRestored = (int) DB::table('inventory_return_restorations')
                    ->where('inventory_reservation_allocation_id', $allocation->id)
                    ->sum('quantity');
                $available = max(0, (int) $allocation->quantity - $alreadyRestored);
                $restoreQuantity = min($remaining, $available);
                if ($restoreQuantity === 0) {
                    continue;
                }

                $stock = WarehouseStock::whereKey($allocation->warehouse_stock_id)->lockForUpdate()->firstOrFail();
                $operationKey = "return-stock:{$returnItem->id}:{$allocation->id}";
                DB::table('inventory_return_restorations')->insert([
                    'return_request_item_id' => $returnItem->id,
                    'inventory_reservation_allocation_id' => $allocation->id,
                    'warehouse_stock_id' => $stock->id,
                    'operation_key' => $operationKey,
                    'quantity' => $restoreQuantity,
                    'restored_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $stock->quantity += $restoreQuantity;
                $stock->save();
                $remaining -= $restoreQuantity;
            }

            if ($remaining !== 0) {
                throw new LogicException('Phân bổ tồn kho không đủ để hoàn số lượng đã nhận.');
            }

            $returnItem->inventory_restored_at = now();
            $returnItem->save();
            $bookId = $returnItem->orderItem->book_id;
            $totalOnHand = (int) WarehouseStock::where('book_id', $bookId)->sum('quantity');
            Book::withoutGlobalScopes()->whereKey($bookId)->update(['stock' => $totalOnHand]);
        }
    }

    private function reverseFinancialEffects(ReturnRequest $return): void
    {
        $order = Order::withoutGlobalScopes()->whereKey($return->order_id)->lockForUpdate()->firstOrFail();
        $vendor = Vendor::withoutGlobalScopes()->whereKey($return->vendor_id)->lockForUpdate()->firstOrFail();
        $user = User::whereKey($return->user_id)->lockForUpdate()->firstOrFail();

        $earning = $order->vendorEarningLedger()->first();
        if ($earning && ! DB::table('vendor_earning_reversals')->where('return_request_id', $return->id)->exists()) {
            $reversal = $this->calculateVendorReversal($return);
            DB::table('vendor_earning_reversals')->insert([
                'vendor_id' => $vendor->id,
                'order_id' => $order->id,
                'return_request_id' => $return->id,
                'operation_key' => "vendor-refund:{$return->id}",
                'gross_amount' => $reversal['gross_amount'],
                'commission_amount' => $reversal['commission_amount'],
                'net_amount' => $reversal['net_amount'],
                'currency' => $return->currency,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $vendor->balance = max(0, (int) $vendor->balance - $reversal['net_amount']);
            $vendor->save();
        }

        $pointLedger = $order->loyaltyPointLedger()->first();
        if ($pointLedger && ! DB::table('loyalty_point_reversals')->where('return_request_id', $return->id)->exists()) {
            $alreadyReversed = (int) DB::table('loyalty_point_reversals')
                ->where('order_id', $order->id)
                ->sum('points');
            $refundedBefore = (int) ReturnRequest::where('order_id', $order->id)
                ->where('status', 'refunded')
                ->where('id', '!=', $return->id)
                ->sum('refund_amount');
            $cumulativeRatio = $order->total_amount > 0
                ? min(1, ($refundedBefore + $return->refund_amount) / $order->total_amount)
                : 0;
            $targetReversal = min(
                (int) $pointLedger->points,
                (int) round((int) $pointLedger->points * $cumulativeRatio)
            );
            $points = max(0, $targetReversal - $alreadyReversed);
            DB::table('loyalty_point_reversals')->insert([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'return_request_id' => $return->id,
                'operation_key' => "loyalty-refund:{$return->id}",
                'points' => $points,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $user->points = max(0, (int) $user->points - $points);
            $user->save();
        }
    }

    /**
     * @return array{gross_amount:int, commission_amount:int, net_amount:int}
     */
    private function calculateVendorReversal(ReturnRequest $return): array
    {
        $order = Order::withoutGlobalScopes()->findOrFail($return->order_id);
        $earning = $order->vendorEarningLedger()->first();
        if (! $earning) {
            throw new LogicException('Không tìm thấy vendor earning ledger để giữ và đảo doanh thu.');
        }

        $alreadyGross = (int) DB::table('vendor_earning_reversals')
            ->where('order_id', $order->id)
            ->sum('gross_amount');
        $alreadyCommission = (int) DB::table('vendor_earning_reversals')
            ->where('order_id', $order->id)
            ->sum('commission_amount');
        $refundedBefore = (int) ReturnRequest::where('order_id', $order->id)
            ->where('status', 'refunded')
            ->where('id', '!=', $return->id)
            ->sum('refund_amount');
        $gross = min(
            (int) $return->refund_amount,
            max(0, (int) $earning->gross_amount - $alreadyGross)
        );
        $cumulativeRatio = $order->total_amount > 0
            ? min(1, ($refundedBefore + $return->refund_amount) / $order->total_amount)
            : 0;
        $targetCommission = min(
            (int) $earning->commission_amount,
            (int) round((int) $earning->commission_amount * $cumulativeRatio)
        );
        $commission = min($gross, max(0, $targetCommission - $alreadyCommission));

        return [
            'gross_amount' => $gross,
            'commission_amount' => $commission,
            'net_amount' => max(0, $gross - $commission),
        ];
    }

    private function updateOrderRefundProjection(ReturnRequest $return): void
    {
        $order = Order::withoutGlobalScopes()->whereKey($return->order_id)->lockForUpdate()->firstOrFail();
        $refunded = (int) ReturnRequest::where('order_id', $order->id)
            ->where('status', 'refunded')
            ->sum('refund_amount');
        $order->refund_status = $refunded >= (int) $order->total_amount ? 'refunded' : 'partially_refunded';
        $order->save();
    }

    private function authorizeStaffActor(ReturnRequest $return, User $actor): void
    {
        if ($actor->role === 'admin') {
            return;
        }
        if ($actor->role === 'vendor' && (int) $actor->vendor?->id === (int) $return->vendor_id) {
            return;
        }

        throw new AuthorizationException('Bạn không có quyền xử lý yêu cầu trả hàng này.');
    }

    private function recordTransition(
        ReturnRequest $return,
        ?string $from,
        string $to,
        string $actorType,
        int $actorId,
        ?string $reason,
        string $operationKey
    ): void {
        ReturnRequestTransition::create([
            'return_request_id' => $return->id,
            'operation_key' => $operationKey,
            'from_state' => $from,
            'to_state' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}

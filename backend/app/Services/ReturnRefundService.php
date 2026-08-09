<?php

namespace App\Services;

use App\Enums\InventoryReservationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Book;
use App\Models\DemoWalletAccount;
use App\Models\DemoWalletLedgerEntry;
use App\Models\InventoryReservationAllocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
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
            if (! $deliveredAt) {
                throw new LogicException('Không xác định được thời điểm khách hàng nhận hàng.');
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

                $policy = $orderItem->return_policy_snapshot
                    ?? $invoiceLine['return_policy_snapshot']
                    ?? [];
                $returnable = (bool) ($policy['is_returnable'] ?? false);
                $returnWindowDays = (int) ($policy['return_window_days'] ?? 0);
                if (! $returnable || $returnWindowDays < 1) {
                    throw new LogicException('Sản phẩm này không thuộc chính sách trả hàng của đơn mua.');
                }
                if (now()->isAfter(Carbon::parse($deliveredAt)->addDays($returnWindowDays))) {
                    throw new LogicException("Sản phẩm đã quá thời hạn trả hàng {$returnWindowDays} ngày.");
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
        if ($target === 'refund_processing') {
            throw new LogicException('Chỉ luồng xử lý hoàn tiền nguyên tử mới được chuyển sang trạng thái hoàn tiền.');
        }

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
                $this->assertCanonicalApprovalOrder($return);
                $holdAmount = $this->calculateVendorReversal($return)['net_amount'];
                $hold = VendorFinancialHold::where('return_request_id', $return->id)->lockForUpdate()->first();
                if ($hold) {
                    if ((int) $hold->vendor_id !== (int) $return->vendor_id
                        || $hold->operation_key !== "refund-hold:{$return->id}"
                        || $hold->currency !== $return->currency
                        || (int) $hold->amount !== $holdAmount
                        || $hold->status !== 'active') {
                        throw new LogicException('Khoản giữ tiền hoàn trả hiện có không nhất quán.');
                    }
                } else {
                    VendorFinancialHold::create([
                        'vendor_id' => $return->vendor_id,
                        'operation_key' => "refund-hold:{$return->id}",
                        'amount' => $holdAmount,
                        'currency' => $return->currency,
                        'status' => 'active',
                        'return_request_id' => $return->id,
                    ]);
                }
                $return->approved_at = now();
            } elseif ($target === 'item_received') {
                $this->restoreInventory($return);
                $return->item_received_at = now();
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
        return DB::transaction(function () use ($returnId, $actor, $operationKey, $manualEvidence) {
            $return = ReturnRequest::whereKey($returnId)->lockForUpdate()->firstOrFail();
            $order = Order::withoutGlobalScopes()->whereKey($return->order_id)->lockForUpdate()->firstOrFail();
            $this->assertOnlineOrder($order);
            $this->assertCanonicalReturnOrder($return, $order);
            $this->authorizeStaffActor($return, $actor);

            $operationAttempt = RefundTransactionAttempt::where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($operationAttempt) {
                $attemptRefund = RefundTransaction::whereKey($operationAttempt->refund_transaction_id)->lockForUpdate()->firstOrFail();
                if ((int) $attemptRefund->return_request_id !== (int) $return->id) {
                    throw new LogicException('Khóa thao tác hoàn tiền đã được dùng cho yêu cầu khác.');
                }
            }

            if ($return->status === 'refunded') {
                $allocations = $this->canonicalAllocations($order, $return);
                $this->assertRefundedEvidence($return, $order, $allocations[(int) $return->id] ?? null);
                $this->assertOrderRefundProjection($order);

                return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
            }
            if (! in_array($return->status, ['item_received', 'refund_failed', 'refund_processing'], true)) {
                throw new LogicException('Yêu cầu chưa sẵn sàng để hoàn tiền.');
            }
            $from = $return->status;
            $refund = $this->prepareRefund($return, $from);
            $attempt = $operationAttempt;
            if ($attempt) {
                $this->assertAttemptMatches($attempt, $refund, $return);
                if ($attempt->status === 'succeeded' && $return->status === 'refunded') {
                    return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
                }
                throw new LogicException('Bằng chứng attempt hoàn tiền hiện có không nhất quán với trạng thái trả hàng.');
            }
            if ($from === 'refund_failed') {
                $priorStatuses = $refund->attempts()->lockForUpdate()->pluck('status');
                if ($priorStatuses->isEmpty() || $priorStatuses->contains(fn ($status) => $status !== 'failed')) {
                    throw new LogicException('Hoàn tiền thất bại lịch sử chỉ được thử lại khi có attempt thất bại nhất quán.');
                }
            }
            if ($from === 'refund_processing' && $refund->attempts()->lockForUpdate()->exists()) {
                throw new LogicException('Hoàn tiền đang xử lý lịch sử phải không có attempt để tiếp tục một cách an toàn.');
            }
            if ($refund->attempts()->whereIn('status', ['pending', 'processing', 'succeeded'])->exists()) {
                throw new LogicException('Giao dịch hoàn tiền đã có attempt đang xử lý hoặc thành công nhưng chưa hoàn tất.');
            }

            $this->validateOtherRefundReversals($order, $return);

            $attempt = RefundTransactionAttempt::create([
                'refund_transaction_id' => $refund->id,
                'operation_key' => $operationKey,
                'attempt_number' => (int) $refund->attempts()->max('attempt_number') + 1,
                'status' => 'processing',
                'request_payload' => ['refund_destination' => 'komibook_wallet'],
                'response_payload' => [],
                'attempted_at' => now(),
            ]);

            $return->status = 'refund_processing';
            $return->refund_started_at = $return->refund_started_at ?? now();
            $return->save();
            if ($from !== 'refund_processing') {
                $this->recordTransition(
                    $return,
                    $from,
                    'refund_processing',
                    $actor->role,
                    $actor->id,
                    null,
                    $this->settlementTransitionKey($operationKey, 'processing')
                );
            }
            $order->refund_status = 'refunding';
            $order->save();

            $buyer = User::whereKey($return->user_id)->lockForUpdate()->firstOrFail();
            app(DemoWalletService::class)->creditRefund($buyer, $refund->paymentTransaction, $order, (int) $refund->amount, $return->id);
            $this->reverseFinancialEffects($return);
            $this->consumeFinancialHold($return);

            $attempt->status = 'succeeded';
            $attempt->response_payload = ['internal_wallet_credit' => true, 'no_external_call' => true];
            $attempt->failure_reason = null;
            $attempt->save();
            $refund->provider_reference = 'KOMIBOOK-WALLET-REFUND-'.$attempt->id;
            $refund->status = 'refunded';
            $refund->evidence = $manualEvidence ?? $refund->evidence;
            $refund->failure_reason = null;
            $refund->refunded_at = now();
            $refund->save();
            $return->status = 'refunded';
            $return->refunded_at = now();
            $return->save();
            $this->recordTransition(
                $return,
                'refund_processing',
                'refunded',
                $actor->role,
                $actor->id,
                $manualEvidence,
                $this->settlementTransitionKey($operationKey, 'complete')
            );
            $this->updateOrderRefundProjection($return);

            return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
        });
    }

    public function reconcileRefund(
        int $returnId,
        User $actor,
        string $operationKey,
        string $clientIp
    ): ReturnRequest {
        return DB::transaction(function () use ($returnId, $actor) {
            $return = ReturnRequest::whereKey($returnId)->lockForUpdate()->firstOrFail();
            $this->authorizeStaffActor($return, $actor);
            if ($return->status === 'refunded') {
                $order = Order::withoutGlobalScopes()->whereKey($return->order_id)->lockForUpdate()->firstOrFail();
                $this->assertOnlineOrder($order);
                $this->assertCanonicalReturnOrder($return, $order);
                $allocations = $this->canonicalAllocations($order, $return);
                $this->assertRefundedEvidence($return, $order, $allocations[(int) $return->id] ?? null);
                $this->assertOrderRefundProjection($order);

                return $return->load(['items.orderItem.book', 'transitions', 'refundTransaction.attempts']);
            }

            throw new LogicException('Đối soát gateway hoàn tiền đã bị vô hiệu hóa; trạng thái chưa hoàn tiền không thể được thay đổi qua endpoint này.');
        });
    }

    private function prepareRefund(ReturnRequest $return, string $returnState): RefundTransaction
    {
        $order = Order::withoutGlobalScopes()->whereKey($return->order_id)->lockForUpdate()->firstOrFail();
        $this->assertOnlineOrder($order);
        $checkoutSessionId = $order->checkoutSessionOrder?->checkout_session_id;
        $payments = $checkoutSessionId
            ? PaymentTransaction::where('checkout_session_id', $checkoutSessionId)
                ->where('status', PaymentTransactionStatus::PAID)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
            : collect();
        if ($payments->count() !== 1) {
            throw new LogicException('Giao dịch online đã thanh toán phải có đúng một giao dịch gốc chuẩn.');
        }
        /** @var PaymentTransaction $payment */
        $payment = $payments->sole();
        $this->assertCanonicalReturnOrder($return, $order);
        $this->assertCanonicalPaidPayment($order, $return, $payment);
        $provider = $payment->provider;

        if ($payment) {
            $reserved = (int) RefundTransaction::where('payment_transaction_id', $payment->id)
                ->where('return_request_id', '!=', $return->id)
                ->whereIn('status', ['pending', 'processing', 'refunded'])
                ->sum('amount');
            if ($reserved + $return->refund_amount > $payment->amount) {
                throw new LogicException('Tổng tiền hoàn vượt quá giao dịch thanh toán gốc.');
            }
        }

        $refund = RefundTransaction::where('return_request_id', $return->id)->lockForUpdate()->first();
        if (! $refund) {
            if ($returnState !== 'item_received') {
                throw new LogicException('Trạng thái hoàn tiền lịch sử không có giao dịch hoàn tiền chuẩn để tiếp tục.');
            }
            $refund = RefundTransaction::create([
                'return_request_id' => $return->id,
                'payment_transaction_id' => $payment?->id,
                'provider' => $provider,
                'idempotency_key' => "refund:{$return->code}",
                'amount' => $return->refund_amount,
                'currency' => $return->currency,
                'status' => 'processing',
                'processing_at' => now(),
            ]);
        } else {
            if ((int) $refund->payment_transaction_id !== (int) $payment->id
                || $refund->provider !== $provider
                || (int) $refund->amount !== (int) $return->refund_amount
                || $refund->currency !== $return->currency
                || $refund->idempotency_key !== "refund:{$return->code}") {
                throw new LogicException('Giao dịch hoàn tiền hiện có không nhất quán với đơn hàng và giao dịch gốc.');
            }
            if ($returnState === 'item_received'
                || ($returnState === 'refund_processing' && ($refund->status !== 'processing' || $refund->provider_reference !== null))
                || ($returnState === 'refund_failed' && ($refund->status !== 'failed' || $refund->provider_reference !== null))
                || ! in_array($returnState, ['item_received', 'refund_processing', 'refund_failed'], true)) {
                throw new LogicException('Trạng thái hoàn tiền lịch sử không nhất quán và không thể tiếp tục.');
            }
        }

        return $refund;
    }

    private function restoreInventory(ReturnRequest $return): void
    {
        $return->load('items.orderItem.inventoryReservation');
        $itemsToRestore = $return->items
            ->filter(fn (ReturnRequestItem $item) => $item->inventory_restored_at === null)
            ->values();

        foreach ($itemsToRestore as $returnItem) {
            $orderItem = $returnItem->orderItem;
            $reservation = $orderItem?->inventoryReservation;
            if (! $orderItem || ! $reservation || $reservation->status !== InventoryReservationStatus::COMMITTED
                || (int) $reservation->order_item_id !== (int) $returnItem->order_item_id
                || (int) $reservation->book_id !== (int) $orderItem->book_id) {
                throw new LogicException('Không tìm thấy committed inventory reservation hợp lệ để hoàn kho.');
            }
        }

        $usedBookInventory = app(UsedBookInventoryService::class);
        $usedListings = $usedBookInventory->lockListingsForBooks(
            $itemsToRestore->pluck('orderItem.book_id')->unique()->values()->all()
        )->keyBy('book_id');
        $reservationIds = $itemsToRestore->pluck('orderItem.inventoryReservation.id')->unique()->values()->all();
        $allocationsByReservation = InventoryReservationAllocation::whereIn('inventory_reservation_id', $reservationIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('inventory_reservation_id');
        $stocksById = WarehouseStock::whereIn(
            'id',
            $allocationsByReservation->flatten(1)->pluck('warehouse_stock_id')->unique()->values()->all()
        )
            ->orderBy('book_id')
            ->orderBy('warehouse_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $usedChecks = [];

        foreach ($itemsToRestore as $returnItem) {
            $bookId = (int) $returnItem->orderItem->book_id;
            $listing = $usedListings->get($bookId);
            if ($listing) {
                $check = $usedBookInventory->inspect($listing, true);
                if (! $check['valid']) {
                    throw new LogicException('Used-book inventory is incoherent: '.$check['reason_code']);
                }
                $usedChecks[$bookId] = $check;
            }

            $reservation = $returnItem->orderItem->inventoryReservation;
            $allocations = $allocationsByReservation->get($reservation->id, collect());
            if ($allocations->isEmpty()
                || (int) $reservation->quantity <= 0
                || $allocations->contains(fn (InventoryReservationAllocation $allocation) => (int) $allocation->quantity <= 0)
                || $allocations->pluck('warehouse_stock_id')->unique()->count() !== $allocations->count()
                || (int) $allocations->sum('quantity') !== (int) $reservation->quantity) {
                throw new LogicException('Phân bổ tồn kho committed không nhất quán để hoàn kho.');
            }

            foreach ($allocations as $allocation) {
                $stock = $stocksById->get($allocation->warehouse_stock_id);
                if (! $stock || (int) $stock->book_id !== $bookId) {
                    throw new LogicException('Phân bổ tồn kho committed không thuộc sách của dòng trả hàng.');
                }
                if (isset($usedChecks[$bookId]) && (int) $stock->id !== (int) $usedChecks[$bookId]['stock']->id) {
                    throw new LogicException('Used-book return allocation does not target its bound stock.');
                }
            }
        }

        foreach ($return->items as $returnItem) {
            if ($returnItem->inventory_restored_at) {
                continue;
            }

            $reservation = $returnItem->orderItem->inventoryReservation;
            $listing = $usedListings->get($returnItem->orderItem->book_id);

            $remaining = $returnItem->quantity;
            foreach ($allocationsByReservation->get($reservation->id, collect()) as $allocation) {
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

                $stock = $stocksById->get($allocation->warehouse_stock_id);
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
            $totalOnHand = $listing
                ? (int) $stocksById->get($usedChecks[$bookId]['stock']->id)->quantity
                : (int) WarehouseStock::where('book_id', $bookId)->sum('quantity');
            Book::withoutGlobalScopes()->whereKey($bookId)->update(['stock' => $totalOnHand]);
            if ($listing) {
                $listing->update(['quantity_available' => $totalOnHand, 'status' => $totalOnHand > 0 && $listing->status === 'sold_out' ? 'active' : $listing->status]);
            }
        }
    }

    private function assertOnlineOrder(Order $order): void
    {
        if ($order->payment_method === 'cod') {
            throw new LogicException('Hoàn tiền COD không được hỗ trợ bởi luồng hoàn Ví KomiBook.');
        }
    }

    private function assertCanonicalReturnOrder(ReturnRequest $return, Order $order): void
    {
        $order->unsetRelation('checkoutSessionOrder');
        $order->unsetRelation('invoiceSnapshot');
        $sessionOrder = $order->checkoutSessionOrder;
        $sessionOrder?->unsetRelation('checkoutSession');
        $session = $sessionOrder?->checkoutSession;
        $invoice = $order->invoiceSnapshot;
        if ((int) $return->order_id !== (int) $order->id || ! $sessionOrder || ! $session || ! $invoice
            || (int) $sessionOrder->order_id !== (int) $order->id || (int) $invoice->order_id !== (int) $order->id
            || (int) $return->user_id !== (int) $order->user_id || (int) $return->user_id !== (int) $session->user_id
            || (int) $return->vendor_id !== (int) $order->vendor_id || (int) $return->vendor_id !== (int) $sessionOrder->vendor_id
            || $return->currency !== $invoice->currency || $return->currency !== $session->currency) {
            throw new LogicException('Ràng buộc party hoặc loại tiền canonical của yêu cầu hoàn trả không nhất quán.');
        }
    }

    /**
     * @param  array{gross_amount:int, commission_amount:int, tax_amount:int, net_amount:int}|null  $expected
     */
    private function assertRefundedEvidence(ReturnRequest $return, Order $order, ?array $expected): void
    {
        $this->assertCanonicalReturnOrder($return, $order);
        if ($return->status !== 'refunded' || ! $expected) {
            throw new LogicException('Bằng chứng hoàn tiền hiện có không nhất quán.');
        }

        $refunds = RefundTransaction::where('return_request_id', $return->id)->lockForUpdate()->get();
        $sessionId = $order->checkoutSessionOrder?->checkout_session_id;
        $paidPayments = $sessionId
            ? PaymentTransaction::where('checkout_session_id', $sessionId)
                ->where('status', PaymentTransactionStatus::PAID)
                ->lockForUpdate()
                ->get()
            : collect();
        if ($refunds->count() !== 1 || $paidPayments->count() !== 1) {
            throw new LogicException('Bằng chứng hoàn tiền hiện có không nhất quán.');
        }
        /** @var RefundTransaction $refund */
        $refund = $refunds->sole();
        /** @var PaymentTransaction $payment */
        $payment = $paidPayments->sole();
        if ((int) $refund->return_request_id !== (int) $return->id
            || (int) $refund->payment_transaction_id !== (int) $payment->id
            || $refund->idempotency_key !== "refund:{$return->code}"
            || (int) $refund->amount !== (int) $return->refund_amount
            || $refund->currency !== $return->currency
            || $refund->provider !== $payment->provider
            || $refund->status !== 'refunded') {
            throw new LogicException('Bằng chứng hoàn tiền hiện có không nhất quán.');
        }
        $this->assertCanonicalPaidPayment($order, $return, $payment);

        $attempts = $refund->attempts()->lockForUpdate()->get();
        if ($attempts->where('status', 'succeeded')->count() !== 1
            || $attempts->contains(fn (RefundTransactionAttempt $attempt) => in_array($attempt->status, ['pending', 'processing'], true))) {
            throw new LogicException('Bằng chứng attempt hoàn tiền hiện có không nhất quán.');
        }
        $succeededAttempt = $attempts->firstWhere('status', 'succeeded');
        if ($refund->provider_reference !== 'KOMIBOOK-WALLET-REFUND-'.$succeededAttempt->id) {
            throw new LogicException('Bằng chứng attempt hoàn tiền hiện có không nhất quán.');
        }

        $holds = VendorFinancialHold::where('return_request_id', $return->id)->lockForUpdate()->get();
        if ($holds->count() !== 1) {
            throw new LogicException('Khoản giữ tiền hoàn trả hiện có không nhất quán.');
        }
        $hold = $holds->sole();
        if ((int) $hold->vendor_id !== (int) $return->vendor_id
            || $hold->operation_key !== "refund-hold:{$return->id}"
            || $hold->currency !== $return->currency
            || (int) $hold->amount !== $expected['net_amount']
            || $hold->status !== 'consumed') {
            throw new LogicException('Khoản giữ tiền hoàn trả hiện có không nhất quán.');
        }

        $credits = DemoWalletLedgerEntry::where('return_request_id', $return->id)
            ->where('entry_type', 'refund_credit')->lockForUpdate()->get();
        $debits = DemoWalletLedgerEntry::where('return_request_id', $return->id)
            ->where('entry_type', 'vendor_refund_debit')->lockForUpdate()->get();
        if ($credits->count() !== 1 || $debits->count() !== 1) {
            throw new LogicException('Bằng chứng ví hoàn tiền hiện có không nhất quán.');
        }
        $credit = $credits->sole();
        $debit = $debits->sole();
        $buyerAccount = DemoWalletAccount::whereKey($credit->demo_wallet_account_id)->lockForUpdate()->first();
        $vendor = Vendor::withoutGlobalScopes()->whereKey($return->vendor_id)->lockForUpdate()->first();
        $vendorAccount = DemoWalletAccount::whereKey($debit->demo_wallet_account_id)->lockForUpdate()->first();
        $buyerKeys = ["komibook-wallet:refund:return:{$return->id}:credit", "komibook-wallet:refund:{$return->id}:credit"];
        if (! $buyerAccount || (int) $buyerAccount->user_id !== (int) $return->user_id
            || $buyerAccount->currency !== $return->currency || $buyerAccount->status !== 'active'
            || ! in_array($credit->operation_key, $buyerKeys, true)
            || (int) $credit->payment_transaction_id !== (int) $payment->id
            || (int) $credit->order_id !== (int) $order->id
            || (int) $credit->return_request_id !== (int) $return->id
            || $credit->entry_type !== 'refund_credit'
            || (int) $credit->amount !== (int) $return->refund_amount
            || ! $vendor || ! $vendorAccount || (int) $vendorAccount->user_id !== (int) $vendor->user_id
            || $vendorAccount->currency !== $return->currency || $vendorAccount->status !== 'active'
            || $debit->operation_key !== "komibook-wallet:vendor-refund:{$return->id}:debit"
            || (int) $debit->vendor_id !== (int) $return->vendor_id
            || (int) $debit->order_id !== (int) $order->id
            || (int) $debit->return_request_id !== (int) $return->id
            || $debit->entry_type !== 'vendor_refund_debit'
            || (int) $debit->amount !== $expected['net_amount']) {
            throw new LogicException('Bằng chứng ví hoàn tiền hiện có không nhất quán.');
        }
        $this->assertWalletLedgerProjection($buyerAccount, $credit, true);
        app(DemoWalletService::class)->assertVendorRefundDebitEvidence(
            $debit,
            $vendor,
            $vendorAccount,
            $order,
            $expected['net_amount'],
            (int) $return->id,
        );

        $earningRows = DB::table('vendor_earning_reversals')->where('return_request_id', $return->id)->lockForUpdate()->get();
        if ($earningRows->count() !== 1) {
            throw new LogicException('Bằng chứng đảo doanh thu hiện có không nhất quán.');
        }
        $earning = $earningRows->sole();
        if ((int) $earning->vendor_id !== (int) $return->vendor_id
            || (int) $earning->order_id !== (int) $order->id
            || $earning->operation_key !== "vendor-refund:{$return->id}"
            || (int) $earning->gross_amount !== $expected['gross_amount']
            || (int) $earning->commission_amount !== $expected['commission_amount']
            || (int) $earning->tax_amount !== $expected['tax_amount']
            || (int) $earning->net_amount !== $expected['net_amount']
            || $earning->currency !== $return->currency) {
            throw new LogicException('Bằng chứng đảo doanh thu hiện có không nhất quán.');
        }

        $pointLedger = $order->loyaltyPointLedger()->lockForUpdate()->first();
        $pointRows = DB::table('loyalty_point_reversals')->where('return_request_id', $return->id)->lockForUpdate()->get();
        if (! $pointLedger) {
            if ($pointRows->isNotEmpty()) {
                throw new LogicException('Bằng chứng đảo điểm hiện có không nhất quán.');
            }

            return;
        }
        if ($pointRows->count() !== 1) {
            throw new LogicException('Bằng chứng đảo điểm hiện có không nhất quán.');
        }
        $point = $pointRows->sole();
        if ((int) $point->user_id !== (int) $return->user_id
            || (int) $point->order_id !== (int) $order->id
            || $point->operation_key !== "loyalty-refund:{$return->id}"
            || (int) $point->points !== $this->calculateLoyaltyReversal($return, (int) $pointLedger->points)) {
            throw new LogicException('Bằng chứng đảo điểm hiện có không nhất quán.');
        }
    }

    private function settlementTransitionKey(string $operationKey, string $phase): string
    {
        return 'refund-settlement:'.$phase.':'.hash('sha256', $operationKey);
    }

    private function assertCanonicalPaidPayment(Order $order, ReturnRequest $return, PaymentTransaction $payment): void
    {
        $session = $order->checkoutSessionOrder?->checkoutSession;
        if (! $session
            || (int) $payment->checkout_session_id !== (int) $session->id
            || $payment->status !== PaymentTransactionStatus::PAID
            || (int) $payment->amount !== (int) $session->total_amount
            || $payment->currency !== $return->currency
            || ! $payment->paid_at
            || trim((string) $payment->provider_reference) === ''
            || trim((string) $payment->provider_transaction_id) === '') {
            throw new LogicException('Giao dịch thanh toán gốc không cùng bằng chứng canonical.');
        }
    }

    private function assertWalletLedgerProjection(
        DemoWalletAccount $account,
        DemoWalletLedgerEntry $entry,
        bool $isCredit
    ): void {
        $arithmeticValid = $isCredit
            ? (int) $entry->balance_before + (int) $entry->amount === (int) $entry->balance_after
            : (int) $entry->balance_before - (int) $entry->amount === (int) $entry->balance_after;
        if (! $arithmeticValid) {
            throw new LogicException('Bằng chứng số dư Ví KomiBook hiện có không nhất quán.');
        }

        $entries = DemoWalletLedgerEntry::where('demo_wallet_account_id', $account->id)
            ->orderBy('id')->lockForUpdate()->get();
        if ($entries->isEmpty()) {
            throw new LogicException('Ví KomiBook không có chuỗi sổ cái để xác minh số dư.');
        }
        $previousAfter = null;
        foreach ($entries as $ledgerEntry) {
            if ($previousAfter !== null && $previousAfter !== (int) $ledgerEntry->balance_before) {
                throw new LogicException('Chuỗi sổ cái Ví KomiBook không liên tục.');
            }
            $previousAfter = (int) $ledgerEntry->balance_after;
        }
        if ($previousAfter === null || (int) $account->balance !== $previousAfter) {
            throw new LogicException('Số dư Ví KomiBook không khớp với sổ cái.');
        }
    }

    private function assertAttemptMatches(
        RefundTransactionAttempt $attempt,
        RefundTransaction $refund,
        ReturnRequest $return
    ): void {
        if ((int) $attempt->refund_transaction_id !== (int) $refund->id
            || (int) $refund->return_request_id !== (int) $return->id
            || ! in_array($attempt->status, ['processing', 'succeeded'], true)) {
            throw new LogicException('Attempt hoàn tiền hiện có không thuộc đúng yêu cầu hoặc có trạng thái không hợp lệ.');
        }
    }

    private function consumeFinancialHold(ReturnRequest $return): void
    {
        $hold = VendorFinancialHold::where('return_request_id', $return->id)->lockForUpdate()->first();
        $expected = $this->calculateVendorReversal($return)['net_amount'];
        if (! $hold
            || (int) $hold->vendor_id !== (int) $return->vendor_id
            || $hold->operation_key !== "refund-hold:{$return->id}"
            || $hold->currency !== $return->currency
            || (int) $hold->amount !== $expected
            || $hold->status !== 'active') {
            throw new LogicException('Khoản giữ tiền hoàn trả không tồn tại, đã được dùng, hoặc không nhất quán.');
        }

        $hold->status = 'consumed';
        $hold->consumed_at = now();
        $hold->save();
    }

    private function reverseFinancialEffects(ReturnRequest $return): void
    {
        $order = Order::withoutGlobalScopes()->whereKey($return->order_id)->lockForUpdate()->firstOrFail();
        $vendor = Vendor::withoutGlobalScopes()->whereKey($return->vendor_id)->lockForUpdate()->firstOrFail();
        $user = User::whereKey($return->user_id)->lockForUpdate()->firstOrFail();

        $earning = $order->vendorEarningLedger()->first();
        if (! $earning) {
            throw new LogicException('Không tìm thấy vendor earning ledger để đảo doanh thu.');
        }
        $reversal = $this->calculateVendorReversal($return);
        $existingEarningReversal = DB::table('vendor_earning_reversals')->where('return_request_id', $return->id)->lockForUpdate()->first();
        if ($existingEarningReversal) {
            if ((int) $existingEarningReversal->vendor_id !== (int) $vendor->id
                || (int) $existingEarningReversal->order_id !== (int) $order->id
                || $existingEarningReversal->operation_key !== "vendor-refund:{$return->id}"
                || (int) $existingEarningReversal->gross_amount !== $reversal['gross_amount']
                || (int) $existingEarningReversal->commission_amount !== $reversal['commission_amount']
                || (int) $existingEarningReversal->tax_amount !== $reversal['tax_amount']
                || (int) $existingEarningReversal->net_amount !== $reversal['net_amount']
                || $existingEarningReversal->currency !== $return->currency) {
                throw new LogicException('Bằng chứng đảo doanh thu nhà bán không nhất quán.');
            }
        } else {
            DB::table('vendor_earning_reversals')->insert([
                'vendor_id' => $vendor->id,
                'order_id' => $order->id,
                'return_request_id' => $return->id,
                'operation_key' => "vendor-refund:{$return->id}",
                'gross_amount' => $reversal['gross_amount'],
                'commission_amount' => $reversal['commission_amount'],
                'tax_amount' => $reversal['tax_amount'],
                'net_amount' => $reversal['net_amount'],
                'currency' => $return->currency,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        app(DemoWalletService::class)->debitVendorRefund($vendor, $order, $reversal['net_amount'], $return->id);

        $pointLedger = $order->loyaltyPointLedger()->first();
        if ($pointLedger) {
            $existingPointReversal = DB::table('loyalty_point_reversals')->where('return_request_id', $return->id)->lockForUpdate()->first();
            $points = $this->calculateLoyaltyReversal($return, (int) $pointLedger->points);
            if ($existingPointReversal) {
                if ((int) $existingPointReversal->user_id !== (int) $user->id
                    || (int) $existingPointReversal->order_id !== (int) $order->id
                    || $existingPointReversal->operation_key !== "loyalty-refund:{$return->id}"
                    || (int) $existingPointReversal->points !== $points) {
                    throw new LogicException('Bằng chứng đảo điểm khách hàng không nhất quán.');
                }
            } else {
                if ((int) $user->points < $points) {
                    throw new LogicException('Điểm khách hàng không đủ để đảo chính xác giao dịch hoàn trả.');
                }
                DB::table('loyalty_point_reversals')->insert([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'return_request_id' => $return->id,
                    'operation_key' => "loyalty-refund:{$return->id}",
                    'points' => $points,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $user->points = (int) $user->points - $points;
                $user->save();
            }
        }
    }

    /**
     * @return array{gross_amount:int, commission_amount:int, tax_amount:int, net_amount:int}
     */
    private function calculateVendorReversal(ReturnRequest $return): array
    {
        $order = Order::withoutGlobalScopes()->findOrFail($return->order_id);
        $earning = $order->vendorEarningLedger()->first();
        if (! $earning) {
            throw new LogicException('Không tìm thấy vendor earning ledger để giữ và đảo doanh thu.');
        }

        return $this->canonicalAllocations($order, $return)[(int) $return->id];
    }

    private function assertCanonicalApprovalOrder(ReturnRequest $return): void
    {
        $lowerReturns = ReturnRequest::where('order_id', $return->order_id)
            ->where('id', '<', $return->id)
            ->lockForUpdate()
            ->get();
        $lowerHolds = VendorFinancialHold::whereIn('return_request_id', $lowerReturns->pluck('id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('return_request_id');
        foreach ($lowerReturns as $lower) {
            if (in_array($lower->status, ['requested', 'under_review'], true)
                || ($lower->status !== 'rejected' && ! $lowerHolds->has($lower->id))) {
                throw new LogicException('Phải phê duyệt theo thứ tự canonical của yêu cầu trả hàng trên cùng đơn hàng.');
            }
        }

        $laterHoldExists = VendorFinancialHold::query()
            ->join('return_requests', 'return_requests.id', '=', 'vendor_financial_holds.return_request_id')
            ->where('return_requests.order_id', $return->order_id)
            ->where('return_requests.id', '>', $return->id)
            ->lockForUpdate()
            ->exists();
        if ($laterHoldExists) {
            throw new LogicException('Khoản giữ tiền của yêu cầu có ID lớn hơn làm thứ tự phê duyệt canonical không nhất quán.');
        }
    }

    /**
     * @return array<int, array{gross_amount:int, commission_amount:int, tax_amount:int, net_amount:int}>
     */
    private function canonicalAllocations(Order $order, ReturnRequest $current): array
    {
        $earning = $order->vendorEarningLedger()->firstOrFail();
        $heldIds = VendorFinancialHold::query()
            ->join('return_requests', 'return_requests.id', '=', 'vendor_financial_holds.return_request_id')
            ->where('return_requests.order_id', $order->id)
            ->lockForUpdate()
            ->pluck('return_requests.id');
        $returns = ReturnRequest::whereIn('id', $heldIds)->lockForUpdate()->get()->keyBy('id');
        $returns->put($current->id, $current);
        $ordered = $returns->sortBy('id')->values();
        $total = (int) $order->total_amount;
        if ($total <= 0) {
            throw new LogicException('Đơn hàng không có tổng tiền hợp lệ để phân bổ hoàn trả.');
        }

        $cumulative = 0;
        $previous = ['gross' => 0, 'commission' => 0, 'tax' => 0];
        $allocations = [];
        foreach ($ordered as $candidate) {
            $cumulative += (int) $candidate->refund_amount;
            if ($cumulative > $total) {
                throw new LogicException('Tổng phân bổ hoàn trả canonical vượt giá trị đơn hàng.');
            }
            $targets = [
                'gross' => $this->canonicalTarget((int) $earning->gross_amount, $cumulative, $total),
                'commission' => $this->canonicalTarget((int) $earning->commission_amount, $cumulative, $total),
                'tax' => $this->canonicalTarget((int) $earning->tax_amount, $cumulative, $total),
            ];
            $gross = $targets['gross'] - $previous['gross'];
            $commission = $targets['commission'] - $previous['commission'];
            $tax = $targets['tax'] - $previous['tax'];
            if ($gross < 0 || $commission < 0 || $tax < 0 || $gross < $commission + $tax) {
                throw new LogicException('Phân bổ canonical của doanh thu nhà bán không hợp lệ.');
            }
            $allocations[(int) $candidate->id] = [
                'gross_amount' => $gross,
                'commission_amount' => $commission,
                'tax_amount' => $tax,
                'net_amount' => $gross - $commission - $tax,
            ];
            $previous = $targets;
        }

        $holds = VendorFinancialHold::whereIn('return_request_id', $ordered->pluck('id'))->lockForUpdate()->get()->keyBy('return_request_id');
        foreach ($holds as $returnId => $hold) {
            $candidate = $returns->get($returnId);
            $expected = $allocations[(int) $returnId] ?? null;
            if (! $candidate || ! $expected || (int) $hold->vendor_id !== (int) $candidate->vendor_id
                || $hold->operation_key !== "refund-hold:{$returnId}" || $hold->currency !== $candidate->currency
                || (int) $hold->amount !== $expected['net_amount'] || ! in_array($hold->status, ['active', 'consumed'], true)) {
                throw new LogicException('Khoản giữ tiền canonical không nhất quán.');
            }
            if ($hold->status === 'consumed') {
                $refund = RefundTransaction::where('return_request_id', $returnId)->lockForUpdate()->first();
                if ($candidate->status !== 'refunded' || ! $refund || $refund->status !== 'refunded') {
                    throw new LogicException('Khoản giữ tiền đã dùng không có bằng chứng hoàn tiền canonical.');
                }
            }
        }

        return $allocations;
    }

    private function canonicalTarget(int $componentTotal, int $cumulative, int $orderTotal): int
    {
        if ($cumulative >= $orderTotal) {
            return $componentTotal;
        }

        return intdiv(($componentTotal * $cumulative) + intdiv($orderTotal, 2), $orderTotal);
    }

    private function calculateLoyaltyReversal(ReturnRequest $return, int $totalPoints): int
    {
        $order = Order::withoutGlobalScopes()->whereKey($return->order_id)->lockForUpdate()->firstOrFail();
        $heldIds = VendorFinancialHold::query()
            ->join('return_requests', 'return_requests.id', '=', 'vendor_financial_holds.return_request_id')
            ->where('return_requests.order_id', $order->id)
            ->lockForUpdate()
            ->pluck('return_requests.id');
        $returns = ReturnRequest::whereIn('id', $heldIds)->lockForUpdate()->get()->keyBy('id');
        $returns->put($return->id, $return);
        $cumulative = 0;
        $previous = 0;
        foreach ($returns->sortBy('id') as $candidate) {
            $cumulative += (int) $candidate->refund_amount;
            $target = $this->canonicalTarget($totalPoints, $cumulative, (int) $order->total_amount);
            if ((int) $candidate->id === (int) $return->id) {
                return $target - $previous;
            }
            $previous = $target;
        }

        throw new LogicException('Không thể xác định phân bổ điểm canonical cho yêu cầu hoàn trả.');
    }

    private function validateOtherRefundReversals(Order $order, ReturnRequest $current): void
    {
        $allocations = $this->canonicalAllocations($order, $current);
        $checkoutSessionId = $order->checkoutSessionOrder?->checkout_session_id;
        $paidPayments = $checkoutSessionId
            ? PaymentTransaction::where('checkout_session_id', $checkoutSessionId)
                ->where('status', PaymentTransactionStatus::PAID)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
            : collect();
        if ($paidPayments->count() !== 1) {
            throw new LogicException('Giao dịch online đã thanh toán phải có đúng một giao dịch gốc chuẩn.');
        }
        /** @var PaymentTransaction $canonicalPayment */
        $canonicalPayment = $paidPayments->sole();
        $this->assertCanonicalPaidPayment($order, $current, $canonicalPayment);
        $returns = ReturnRequest::where('order_id', $order->id)->lockForUpdate()->get()->keyBy('id');
        $holds = VendorFinancialHold::whereIn('return_request_id', $returns->keys())->lockForUpdate()->get()->keyBy('return_request_id');
        $refunds = RefundTransaction::whereIn('return_request_id', $returns->keys())->lockForUpdate()->get()->keyBy('return_request_id');
        $earningRows = DB::table('vendor_earning_reversals')->where('order_id', $order->id)->lockForUpdate()->get()->keyBy('return_request_id');
        $pointRows = DB::table('loyalty_point_reversals')->where('order_id', $order->id)->lockForUpdate()->get()->keyBy('return_request_id');

        foreach ($returns as $returnId => $other) {
            if ((int) $returnId === (int) $current->id) {
                continue;
            }
            $this->assertCanonicalReturnOrder($other, $order);
            $hold = $holds->get($returnId);
            $refund = $refunds->get($returnId);
            $attempts = $refund ? $refund->attempts()->lockForUpdate()->get() : collect();
            $earningRow = $earningRows->get($returnId);
            $pointRow = $pointRows->get($returnId);
            $returnWalletEvidence = DemoWalletLedgerEntry::where('return_request_id', $returnId)
                ->whereIn('entry_type', ['refund_credit', 'vendor_refund_debit'])->lockForUpdate()->get();
            $expected = $allocations[(int) $returnId] ?? null;
            $hasFinancialEvidence = $earningRow || $pointRow;
            $coherentHold = $hold && $expected
                && (int) $hold->vendor_id === (int) $other->vendor_id
                && $hold->operation_key === "refund-hold:{$returnId}"
                && $hold->currency === $other->currency
                && (int) $hold->amount === $expected['net_amount'];
            $coherentRefund = $refund
                && (int) $refund->payment_transaction_id === (int) $canonicalPayment->id
                && $refund->provider === $canonicalPayment->provider
                && $refund->idempotency_key === "refund:{$other->code}"
                && (int) $refund->amount === (int) $other->refund_amount
                && $refund->currency === $other->currency;

            if (in_array($other->status, ['requested', 'under_review', 'rejected'], true)) {
                if ($hold || $refund || $attempts->isNotEmpty() || $hasFinancialEvidence || $returnWalletEvidence->isNotEmpty()) {
                    throw new LogicException('Yêu cầu chưa phê duyệt có bằng chứng hoàn tiền không hợp lệ.');
                }

                continue;
            }
            if (in_array($other->status, ['approved', 'item_received'], true)) {
                if (! $coherentHold || $hold->status !== 'active' || $refund || $attempts->isNotEmpty() || $hasFinancialEvidence || $returnWalletEvidence->isNotEmpty()) {
                    throw new LogicException('Yêu cầu đã phê duyệt có bằng chứng hoàn tiền không hợp lệ.');
                }

                continue;
            }
            if ($other->status === 'refund_processing') {
                if (! $coherentHold || $hold->status !== 'active' || ! $coherentRefund || $refund->status !== 'processing'
                    || $refund->provider_reference !== null || $attempts->isNotEmpty() || $hasFinancialEvidence || $returnWalletEvidence->isNotEmpty()) {
                    throw new LogicException('Yêu cầu đang hoàn tiền có bằng chứng lịch sử không hợp lệ.');
                }

                continue;
            }
            if ($other->status === 'refund_failed') {
                if (! $coherentHold || $hold->status !== 'active' || ! $coherentRefund || $refund->status !== 'failed'
                    || $refund->provider_reference !== null || $attempts->isEmpty()
                    || $attempts->contains(fn ($attempt) => $attempt->status !== 'failed') || $hasFinancialEvidence || $returnWalletEvidence->isNotEmpty()) {
                    throw new LogicException('Yêu cầu hoàn tiền thất bại có bằng chứng lịch sử không hợp lệ.');
                }

                continue;
            }
            if ($other->status !== 'refunded') {
                throw new LogicException('Yêu cầu hoàn tiền khác có vòng đời bằng chứng không hợp lệ.');
            }
            $this->assertRefundedEvidence($other, $order, $expected);
        }
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

    private function assertOrderRefundProjection(Order $order): void
    {
        $refunded = (int) ReturnRequest::where('order_id', $order->id)
            ->where('status', 'refunded')
            ->sum('refund_amount');
        $expected = $refunded <= 0
            ? 'none'
            : ($refunded >= (int) $order->total_amount ? 'refunded' : 'partially_refunded');
        if ($order->refund_status !== $expected) {
            throw new LogicException('Projection trạng thái hoàn tiền của đơn hàng không nhất quán.');
        }
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

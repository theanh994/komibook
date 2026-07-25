<?php

namespace App\Services\Payments;

use App\Enums\InventoryReservationStatus;
use App\Enums\PaymentTransactionStatus;
use App\Jobs\ProcessOrder;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\CheckoutSessionLifecycleService;
use Illuminate\Support\Facades\DB;
use Throwable;

class VnpayCallbackService
{
    protected VnpayGateway $gateway;

    public function __construct(?VnpayGateway $gateway = null)
    {
        $this->gateway = $gateway ?? new VnpayGateway;
    }

    /**
     * Xử lý VNPAY IPN callback và cập nhật trạng thái atomically.
     *
     * @return array{RspCode: string, Message: string}
     */
    public function handleIpn(array $queryParams): array
    {
        try {
            $normalized = $this->gateway->verifyAndNormalizeCallback($queryParams);
        } catch (Throwable $e) {
            return ['RspCode' => '97', 'Message' => 'Invalid Checksum'];
        }

        $providerRef = $normalized['provider_reference'];
        $txn = PaymentTransaction::where('provider', 'vnpay')
            ->where('provider_reference', $providerRef)
            ->first();

        if (! $txn) {
            return ['RspCode' => '01', 'Message' => 'Order Not Found'];
        }

        if ($txn->amount !== $normalized['amount'] || $txn->currency !== $normalized['currency']) {
            return ['RspCode' => '04', 'Message' => 'Invalid Amount'];
        }

        try {
            return DB::transaction(function () use ($txn, $normalized) {
                // 1. Khóa CheckoutSession
                $session = CheckoutSession::where('id', $txn->checkout_session_id)->lockForUpdate()->first();
                if (! $session) {
                    return ['RspCode' => '01', 'Message' => 'Order Not Found'];
                }

                // 2. Tải lại và khóa PaymentTransaction
                $lockedTxn = PaymentTransaction::where('id', $txn->id)->lockForUpdate()->first();
                if (! $lockedTxn) {
                    return ['RspCode' => '01', 'Message' => 'Order Not Found'];
                }

                // Re-validate sau khi khóa
                if ($lockedTxn->provider !== 'vnpay' || $lockedTxn->provider_reference !== $normalized['provider_reference'] || (int) $lockedTxn->checkout_session_id !== (int) $session->id) {
                    return ['RspCode' => '99', 'Message' => 'Unknown error'];
                }

                if ($lockedTxn->amount !== $normalized['amount'] || $lockedTxn->currency !== $normalized['currency']) {
                    return ['RspCode' => '04', 'Message' => 'Invalid Amount'];
                }

                // 3. Khóa CheckoutSessionOrder theo order_id tăng dần
                $sessionOrders = CheckoutSessionOrder::where('checkout_session_id', $session->id)
                    ->orderBy('order_id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $orderIds = $sessionOrders->pluck('order_id')->sort()->values()->toArray();
                if (empty($orderIds)) {
                    return ['RspCode' => '99', 'Message' => 'Unknown error'];
                }

                // 4. Khóa toàn bộ Order theo ID tăng dần
                $orders = Order::withoutGlobalScopes()
                    ->whereIn('id', $orderIds)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($orders->count() !== count($orderIds)) {
                    return ['RspCode' => '99', 'Message' => 'Unknown error'];
                }

                $isSuccessCallback = ($normalized['response_code'] === '00' && $normalized['transaction_status'] === '00');
                $providerTxnId = $normalized['provider_transaction_id'];

                $storedId = $lockedTxn->provider_transaction_id;
                $storedHasId = is_string($storedId) && trim($storedId) !== '';
                $incomingHasId = is_string($providerTxnId) && trim($providerTxnId) !== '';

                // Kiểm tra trạng thái Terminal & Idempotency
                if ($lockedTxn->status === PaymentTransactionStatus::PAID) {
                    if ($isSuccessCallback
                        && $lockedTxn->amount === $normalized['amount']
                        && $lockedTxn->currency === $normalized['currency']
                        && $storedHasId && $incomingHasId
                        && $storedId === $providerTxnId
                    ) {
                        return ['RspCode' => '00', 'Message' => 'Confirm Success'];
                    }

                    return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
                }

                if ($lockedTxn->status === PaymentTransactionStatus::FAILED) {
                    if (! $isSuccessCallback) {
                        if (! $storedHasId && ! $incomingHasId) {
                            return ['RspCode' => '00', 'Message' => 'Confirm Success'];
                        }
                        if ($storedHasId && $incomingHasId && $storedId === $providerTxnId) {
                            return ['RspCode' => '00', 'Message' => 'Confirm Success'];
                        }
                    }

                    return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
                }

                if (in_array($lockedTxn->status, [
                    PaymentTransactionStatus::EXPIRED,
                    PaymentTransactionStatus::REFUNDING,
                    PaymentTransactionStatus::REFUNDED,
                ], true)) {
                    return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
                }

                if ($lockedTxn->status !== PaymentTransactionStatus::PENDING) {
                    return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
                }

                // Xử lý trạng thái PENDING
                if ($isSuccessCallback) {
                    if (! $incomingHasId) {
                        return ['RspCode' => '97', 'Message' => 'Invalid Checksum'];
                    }

                    if ($storedHasId && $storedId !== $providerTxnId) {
                        return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
                    }

                    $duplicateTxn = PaymentTransaction::where('provider', 'vnpay')
                        ->where('provider_transaction_id', $providerTxnId)
                        ->where('id', '!=', $lockedTxn->id)
                        ->exists();

                    if ($duplicateTxn) {
                        return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
                    }

                    // Precondition: Late success callback arriving after session expiry / order cancellation
                    $allCancelledOrder = $orders->every(fn ($o) => $o->status === 'cancelled');
                    if ($allCancelledOrder) {
                        $hasPendingTx = PaymentTransaction::where('checkout_session_id', $session->id)
                            ->where('status', PaymentTransactionStatus::PENDING)
                            ->exists();

                        $hasActiveReservation = InventoryReservation::where('checkout_session_id', $session->id)
                            ->where('status', InventoryReservationStatus::RESERVED)
                            ->exists();

                        if ($hasPendingTx || $hasActiveReservation) {
                            app(CheckoutSessionLifecycleService::class)->expireSession($session);
                        }

                        return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
                    }

                    $isSessionExpired = ($session->expires_at && $session->expires_at->isPast());
                    if ($isSessionExpired) {
                        app(CheckoutSessionLifecycleService::class)->expireSession($session);

                        return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
                    }

                    // Kiểm tra trạng thái đơn hàng trước khi chuyển confirmed/paid
                    foreach ($orders as $ord) {
                        $pm = strtolower((string) $ord->payment_method);
                        if ($ord->status !== 'pending' || $ord->payment_status !== 'unpaid' || ($pm !== 'online' && $pm !== 'vnpay')) {
                            return ['RspCode' => '99', 'Message' => 'Unknown error'];
                        }
                    }

                    // Chuyển trạng thái giao dịch và đơn hàng sang paid/confirmed
                    $lockedTxn->status = PaymentTransactionStatus::PAID;
                    $lockedTxn->provider_transaction_id = $providerTxnId;
                    $lockedTxn->provider_occurred_at = $normalized['provider_occurred_at'];
                    $lockedTxn->paid_at = now();
                    $lockedTxn->response_payload = $normalized['payload'];
                    $lockedTxn->save();

                    $jobOrderIds = [];
                    foreach ($orders as $ord) {
                        $ord->status = 'confirmed';
                        $ord->payment_status = 'paid';
                        $ord->save();

                        $jobOrderIds[] = $ord->id;
                    }

                    // Khai báo afterCommit hook cho ProcessOrder jobs
                    DB::afterCommit(function () use ($jobOrderIds) {
                        foreach ($jobOrderIds as $orderIdToProcess) {
                            ProcessOrder::dispatch($orderIdToProcess);
                        }
                    });

                    return ['RspCode' => '00', 'Message' => 'Confirm Success'];
                } else {
                    // Provider failure transition
                    $lockedTxn->status = PaymentTransactionStatus::FAILED;
                    $lockedTxn->provider_transaction_id = $incomingHasId ? $providerTxnId : null;
                    $lockedTxn->provider_occurred_at = $normalized['provider_occurred_at'];
                    $lockedTxn->failed_at = now();
                    $lockedTxn->response_payload = $normalized['payload'];
                    $lockedTxn->save();

                    return ['RspCode' => '00', 'Message' => 'Confirm Success'];
                }
            });
        } catch (Throwable $e) {
            return ['RspCode' => '99', 'Message' => 'Unknown error'];
        }
    }

    /**
     * Xử lý Return URL read-only và trả URL redirect về frontend.
     */
    public function handleReturn(array $queryParams): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173').'/orders';

        try {
            $normalized = $this->gateway->verifyAndNormalizeCallback($queryParams);
        } catch (Throwable $e) {
            return $frontendUrl.'?payment=invalid_signature';
        }

        $txn = PaymentTransaction::where('provider', 'vnpay')
            ->where('provider_reference', $normalized['provider_reference'])
            ->first();

        if (! $txn) {
            return $frontendUrl.'?payment=invalid_transaction';
        }

        if ($txn->amount !== $normalized['amount'] || $txn->currency !== $normalized['currency']) {
            return $frontendUrl.'?payment=invalid_transaction';
        }

        $isSuccessCallback = ($normalized['response_code'] === '00' && $normalized['transaction_status'] === '00');
        $storedId = $txn->provider_transaction_id;
        $incomingId = $normalized['provider_transaction_id'];

        $storedHasId = is_string($storedId) && trim($storedId) !== '';
        $incomingHasId = is_string($incomingId) && trim($incomingId) !== '';

        // Kiểm tra tương thích provider transaction identity
        if ($storedHasId) {
            if (! $incomingHasId || $storedId !== $incomingId) {
                return $frontendUrl.'?payment=invalid_transaction';
            }
        } else {
            // Terminal transaction không có stored ID chỉ tương thích với incoming ID null
            if (in_array($txn->status, [PaymentTransactionStatus::PAID, PaymentTransactionStatus::FAILED], true)) {
                if ($incomingHasId) {
                    return $frontendUrl.'?payment=invalid_transaction';
                }
            }
        }

        if ($isSuccessCallback) {
            if ($txn->status === PaymentTransactionStatus::PAID) {
                return $frontendUrl.'?payment=success';
            }

            if ($txn->status === PaymentTransactionStatus::PENDING) {
                return $frontendUrl.'?payment=pending';
            }

            return $frontendUrl.'?payment=failed';
        }

        // Provider failure
        if ($txn->status === PaymentTransactionStatus::FAILED || $txn->status === PaymentTransactionStatus::PENDING || $txn->status === PaymentTransactionStatus::PAID) {
            return $frontendUrl.'?payment=failed';
        }

        return $frontendUrl.'?payment=failed';
    }
}

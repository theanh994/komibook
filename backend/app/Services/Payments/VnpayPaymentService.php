<?php

namespace App\Services\Payments;

use App\Enums\PaymentTransactionStatus;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\CheckoutSessionLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class VnpayPaymentService
{
    /**
     * Khởi tạo hoặc tái sử dụng VNPAY payment attempt cho CheckoutSession.
     *
     * @return array{status: string, url: string, provider_reference: string, checkout_code: string}
     */
    public function createPaymentAttempt(int $orderId, User $user, string $clientIp): array
    {
        try {
            $result = DB::transaction(function () use ($orderId, $user, $clientIp) {
                // 1. Resolve link để biết session cần khóa; xác minh lại link sau khi session đã được khóa.
                $sessionOrder = CheckoutSessionOrder::where('order_id', $orderId)->first();
                if (! $sessionOrder || ! $sessionOrder->checkout_session_id) {
                    throw new HttpException(422, 'Order is not linked to a valid checkout session.');
                }

                // 2. Luôn khóa CheckoutSession trước order để giữ thứ tự khóa ổn định giữa các request cùng session.
                $session = CheckoutSession::where('id', $sessionOrder->checkout_session_id)->lockForUpdate()->first();
                if (! $session) {
                    throw new HttpException(422, 'Checkout session not found.');
                }

                // 3. XÁC MINH OWNERSHIP NGAY SAU KHI KHÓA SESSION - KHÔNG MUTATE NẾU KHÔNG PHẢI CHỦ SỞ HỮU
                if ((int) $session->user_id !== (int) $user->id) {
                    throw new HttpException(403, 'Unauthorized order access.');
                }

                // 4. Nếu session hết hạn, thực hiện lifecycle cleanup ngay trong transaction này sau khi đã xác minh owner
                if ($session->expires_at && $session->expires_at->isPast()) {
                    app(CheckoutSessionLifecycleService::class)->expireSession($session);

                    return ['status' => 'expired'];
                }

                if ($session->currency !== 'VND') {
                    throw new HttpException(422, 'Invalid checkout currency.');
                }

                if (! is_int($session->total_amount) || $session->total_amount <= 0) {
                    throw new HttpException(422, 'Invalid checkout total amount.');
                }

                // 5. Khóa links và toàn bộ order của session theo thứ tự ID ổn định.
                $sessionOrdersLinks = CheckoutSessionOrder::where('checkout_session_id', $session->id)
                    ->orderBy('order_id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $sessionOrderIds = $sessionOrdersLinks->pluck('order_id')->all();

                if (empty($sessionOrderIds)) {
                    throw new HttpException(422, 'Incomplete orders in checkout session.');
                }

                if (! in_array($orderId, $sessionOrderIds, true)) {
                    throw new HttpException(422, 'Order is not linked to a valid checkout session.');
                }

                $sessionOrders = Order::withoutGlobalScopes()
                    ->whereIn('id', $sessionOrderIds)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($sessionOrders->count() !== count($sessionOrderIds)) {
                    throw new HttpException(422, 'Incomplete orders in checkout session.');
                }

                foreach ($sessionOrders as $sOrder) {
                    if ((int) $sOrder->user_id !== (int) $user->id) {
                        throw new HttpException(403, 'Unauthorized order access.');
                    }

                    $pm = strtolower((string) $sOrder->payment_method);
                    if ($pm !== 'online' && $pm !== 'vnpay') {
                        throw new HttpException(422, 'Checkout is not configured for online payment.');
                    }

                    if (! in_array($sOrder->status, ['draft', 'pending'], true) || $sOrder->payment_status !== 'unpaid') {
                        throw new HttpException(422, 'Order is not in a valid state for payment.');
                    }

                    if ($sOrder->status === 'draft') {
                        $sOrder->status = 'pending';
                        $sOrder->saveQuietly();
                    }
                }

                // 6. Query pending attempt (chỉ lọc provider = vnpay và khóa hàng)
                $existingTxn = PaymentTransaction::where('checkout_session_id', $session->id)
                    ->where('provider', 'vnpay')
                    ->where('status', PaymentTransactionStatus::PENDING)
                    ->lockForUpdate()
                    ->first();

                $gateway = new VnpayGateway;

                if ($existingTxn) {
                    // Pending không có expires_at hoặc đã quá hạn -> đính nhãn EXPIRED và tạo attempt mới
                    if ($existingTxn->expires_at === null || $existingTxn->expires_at->isPast()) {
                        $existingTxn->status = PaymentTransactionStatus::EXPIRED;
                        $existingTxn->save();
                        $existingTxn = null;
                    } else {
                        // Xác minh payload đã lưu trước khi tái sử dụng
                        $payload = $existingTxn->request_payload;
                        if (! is_array($payload)
                            || empty($payload['vnp_TxnRef'])
                            || empty($payload['vnp_OrderInfo'])
                            || empty($payload['vnp_ReturnUrl'])
                            || empty($payload['vnp_IpAddr'])
                            || empty($payload['vnp_CreateDate'])
                            || empty($payload['vnp_ExpireDate'])
                            || $existingTxn->amount !== $session->total_amount
                            || $existingTxn->currency !== $session->currency
                            || $existingTxn->provider_reference !== $payload['vnp_TxnRef']
                        ) {
                            throw new InvalidArgumentException('Corrupted or invalid stored payment payload.');
                        }

                        $createDate = CarbonImmutable::createFromFormat('YmdHis', (string) $payload['vnp_CreateDate'], 'Asia/Ho_Chi_Minh');
                        if ($createDate === false) {
                            throw new InvalidArgumentException('Invalid create date format in stored payload.');
                        }

                        // Gọi lại VnpayGateway để tái tạo URL và payload
                        $gatewayResult = $gateway->createPaymentUrl(
                            $existingTxn->provider_reference,
                            $existingTxn->amount,
                            (string) $payload['vnp_OrderInfo'],
                            (string) $payload['vnp_ReturnUrl'],
                            (string) $payload['vnp_IpAddr'],
                            $createDate
                        );

                        // So sánh canonical request payload được tái tạo với payload đã lưu
                        if ($gatewayResult['request_payload'] !== $payload) {
                            throw new InvalidArgumentException('Recreated payment payload mismatch.');
                        }

                        return [
                            'status' => 'success',
                            'url' => $gatewayResult['url'],
                            'provider_reference' => $existingTxn->provider_reference,
                            'checkout_code' => $session->checkout_code,
                        ];
                    }
                }

                // 7. Tạo attempt mới
                $providerReference = 'CS'.$session->id.time().strtolower(Str::random(8));
                $idempotencyKey = 'IDEM_'.$session->id.'_'.time().'_'.strtolower(Str::random(6));

                $returnUrl = trim((string) config('services.vnpay.return_url'));
                if ($returnUrl === '') {
                    $returnUrl = route('vnpay.return');
                }

                $gatewayResult = $gateway->createPaymentUrl(
                    $providerReference,
                    $session->total_amount,
                    $session->checkout_code,
                    $returnUrl,
                    $clientIp,
                    now()
                );

                $attemptTtl = now()->addMinutes(15);
                $expiresAt = $attemptTtl;
                if ($session->expires_at && $session->expires_at->lt($attemptTtl)) {
                    $expiresAt = $session->expires_at;
                }

                PaymentTransaction::create([
                    'checkout_session_id' => $session->id,
                    'provider' => 'vnpay',
                    'provider_reference' => $providerReference,
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $session->total_amount,
                    'currency' => 'VND',
                    'status' => PaymentTransactionStatus::PENDING,
                    'expires_at' => $expiresAt,
                    'request_payload' => $gatewayResult['request_payload'],
                ]);

                return [
                    'status' => 'success',
                    'url' => $gatewayResult['url'],
                    'provider_reference' => $providerReference,
                    'checkout_code' => $session->checkout_code,
                ];
            });

            if (isset($result['status']) && $result['status'] === 'expired') {
                throw new HttpException(422, 'Checkout session has expired.');
            }

            return $result;
        } catch (HttpException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            throw new HttpException(503, 'Payment gateway service is currently unavailable.');
        } catch (\Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            throw new HttpException(503, 'Payment gateway service is currently unavailable.');
        }
    }
}

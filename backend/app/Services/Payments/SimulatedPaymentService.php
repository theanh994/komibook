<?php

namespace App\Services\Payments;

use App\Enums\PaymentTransactionStatus;
use App\Jobs\ProcessOrder;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\DemoWalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SimulatedPaymentService
{
    public function __construct(
        private readonly PaymentProviderService $providers,
        private readonly DemoWalletService $wallets,
    ) {}

    public function create(string $provider, int $orderId, User $user): array
    {
        $provider = strtolower($provider);
        $capability = $this->providers->capability($provider);
        if ($provider !== 'demo_wallet'
            || $capability['mode'] !== 'demo' || ! $capability['available']) {
            throw new HttpException(503, 'Phương thức thanh toán chưa khả dụng ở chế độ demo an toàn.');
        }

        return DB::transaction(function () use ($provider, $orderId, $user, $capability) {
            $link = CheckoutSessionOrder::where('order_id', $orderId)->firstOrFail();
            $session = CheckoutSession::whereKey($link->checkout_session_id)->lockForUpdate()->firstOrFail();
            if ((int) $session->user_id !== (int) $user->id) {
                throw new HttpException(403, 'Không có quyền thanh toán checkout này.');
            }
            if ($session->expires_at?->isPast()) {
                throw new HttpException(422, 'Checkout đã hết hạn.');
            }

            $orders = Order::withoutGlobalScopes()
                ->whereIn('id', CheckoutSessionOrder::where('checkout_session_id', $session->id)->pluck('order_id'))
                ->orderBy('id')->lockForUpdate()->get();
            if ($orders->isEmpty() || $orders->contains(fn ($order) => $order->status !== 'pending' || $order->payment_status !== 'unpaid')) {
                throw new HttpException(422, 'Checkout không còn ở trạng thái chờ thanh toán.');
            }
            if ($orders->contains(fn ($order) => ! in_array(strtolower((string) $order->payment_method), ['online', $provider], true))) {
                throw new HttpException(422, 'Phương thức thanh toán không khớp với đơn hàng.');
            }

            if ($provider === 'demo_wallet') {
                $this->wallets->accountFor($user);
            }

            $existing = PaymentTransaction::where('checkout_session_id', $session->id)
                ->where('provider', $provider)->where('status', PaymentTransactionStatus::PENDING)
                ->lockForUpdate()->first();
            if ($existing && $existing->expires_at?->isFuture()) {
                return $this->present($existing, $capability);
            }
            if ($existing) {
                $existing->status = PaymentTransactionStatus::EXPIRED;
                $existing->save();
            }

            $reference = strtoupper($provider).'_DEMO_'.$session->id.'_'.Str::lower(Str::random(8));
            $expiresAt = now()->addMinutes(15);
            if ($session->expires_at && $session->expires_at->lt($expiresAt)) {
                $expiresAt = $session->expires_at->copy();
            }
            $transaction = PaymentTransaction::create([
                'checkout_session_id' => $session->id,
                'provider' => $provider,
                'provider_reference' => $reference,
                'idempotency_key' => 'demo:'.$provider.':'.$session->id.':'.Str::uuid(),
                'amount' => $session->total_amount,
                'currency' => 'VND',
                'status' => PaymentTransactionStatus::PENDING,
                'expires_at' => $expiresAt,
                'request_payload' => ['demo_only' => true, 'provider' => $provider, 'checkout_code' => $session->checkout_code],
            ]);

            return $this->present($transaction, $capability);
        });
    }

    public function complete(string $provider, PaymentTransaction $transaction, User $user): array
    {
        $provider = strtolower($provider);
        $capability = $this->providers->capability($provider);
        if ($capability['mode'] !== 'demo' || ! $capability['available']) {
            throw new HttpException(503, 'Thanh toán demo đang bị khóa.');
        }

        return DB::transaction(function () use ($provider, $transaction, $user) {
            $session = CheckoutSession::whereKey($transaction->checkout_session_id)->lockForUpdate()->firstOrFail();
            if ((int) $session->user_id !== (int) $user->id) {
                throw new HttpException(403, 'Không có quyền hoàn tất giao dịch này.');
            }
            $locked = PaymentTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->provider !== $provider) {
                throw new HttpException(422, 'Provider không khớp giao dịch.');
            }
            if ($locked->status === PaymentTransactionStatus::PAID) {
                return ['status' => 'success', 'transaction_id' => $locked->id];
            }
            if ($locked->status !== PaymentTransactionStatus::PENDING || $locked->expires_at?->isPast()) {
                throw new HttpException(422, 'Giao dịch demo đã hết hiệu lực.');
            }

            $orders = Order::withoutGlobalScopes()
                ->whereIn('id', CheckoutSessionOrder::where('checkout_session_id', $session->id)->pluck('order_id'))
                ->orderBy('id')->lockForUpdate()->get();
            if ($orders->isEmpty() || $orders->contains(fn ($order) => $order->status !== 'pending' || $order->payment_status !== 'unpaid')) {
                throw new HttpException(422, 'Đơn hàng không còn chờ thanh toán.');
            }
            if ($provider === 'demo_wallet') {
                $this->wallets->debit($user, $locked);
            }

            $locked->status = PaymentTransactionStatus::PAID;
            $locked->provider_transaction_id = 'DEMO-'.$locked->id;
            $locked->provider_occurred_at = now();
            $locked->paid_at = now();
            $locked->response_payload = ['demo_only' => true, 'confirmed_by_user' => true];
            $locked->save();

            $ids = [];
            foreach ($orders as $order) {
                $order->status = 'confirmed';
                $order->payment_status = 'paid';
                $order->save();
                $ids[] = $order->id;
            }
            DB::afterCommit(fn () => collect($ids)->each(fn ($id) => ProcessOrder::dispatch($id)));

            return ['status' => 'success', 'transaction_id' => $locked->id, 'order_ids' => $ids];
        });
    }

    private function present(PaymentTransaction $transaction, array $capability): array
    {
        return [
            'status' => 'success',
            'internal_wallet' => true,
            'transaction_id' => $transaction->id,
            'provider' => $transaction->provider,
            'provider_name' => $capability['name'],
            'amount' => $transaction->amount,
            'expires_at' => $transaction->expires_at?->toISOString(),
            'qr_payload' => $capability['supports_qr'] ? 'KOMIBOOK-DEMO|'.$transaction->provider_reference.'|'.$transaction->amount : null,
            'notice' => 'Thanh toán nội bộ bằng số dư Ví KomiBook; không gọi dịch vụ ngoài và không phát sinh phí tích hợp.',
        ];
    }
}

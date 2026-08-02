<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentTransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payments\VnpayCallbackService;
use App\Services\Payments\VnpayPaymentService;
use App\Services\Payments\VnpayTransactionQueryService;
use Illuminate\Http\Request;

class VnpayController extends Controller
{
    public function createPayment(Request $request, VnpayPaymentService $paymentService)
    {
        $request->validate(['order_id' => 'required|integer|exists:orders,id']);

        $result = $paymentService->createPaymentAttempt(
            (int) $request->order_id,
            $request->user(),
            $request->ip() ?? '127.0.0.1'
        );

        return response()->json($result);
    }

    public function vnpayReturn(
        Request $request,
        VnpayCallbackService $callbackService,
        VnpayTransactionQueryService $queryService
    )
    {
        // VNPAY cannot call a localhost IPN endpoint. Local development may
        // therefore confirm the same signed, idempotent callback on browser
        // return. Production remains IPN-only.
        if (app()->environment('local') && config('services.vnpay.confirm_on_return')) {
            $confirmation = $callbackService->handleIpn($request->query());
            $providerReference = trim((string) $request->query('vnp_TxnRef', ''));
            if (($confirmation['RspCode'] ?? null) === '00' && $providerReference !== '') {
                $redirect = $this->localPaymentRedirect($providerReference);
                if ($redirect !== null) {
                    return redirect($redirect);
                }
            }
            if (($confirmation['RspCode'] ?? null) !== '00') {
                if ($providerReference !== '') {
                    $reconciled = $queryService->reconcile($providerReference, $request->ip() ?? '127.0.0.1');
                    if (isset($reconciled['PaymentStatus'])) {
                        $frontendUrl = config('app.frontend_url', 'http://localhost:5173').'/orders';

                        return redirect($frontendUrl.'?payment='.$reconciled['PaymentStatus']);
                    }
                }
            }
        }

        $redirectUrl = $callbackService->handleReturn($request->query());

        return redirect($redirectUrl);
    }

    public function vnpayIpn(Request $request, VnpayCallbackService $callbackService)
    {
        $result = $callbackService->handleIpn($request->query());

        return response()->json($result);
    }

    private function localPaymentRedirect(string $providerReference): ?string
    {
        $transaction = PaymentTransaction::query()
            ->where('provider', 'vnpay')
            ->where('provider_reference', $providerReference)
            ->first();
        if (! $transaction) {
            return null;
        }

        $status = $transaction->status instanceof PaymentTransactionStatus
            ? $transaction->status
            : PaymentTransactionStatus::tryFrom((string) $transaction->status);
        $payment = match ($status) {
            PaymentTransactionStatus::PAID => 'success',
            PaymentTransactionStatus::FAILED => 'failed',
            PaymentTransactionStatus::PENDING => 'pending',
            default => null,
        };

        return $payment === null
            ? null
            : config('app.frontend_url', 'http://localhost:5173').'/orders?payment='.$payment;
    }
}

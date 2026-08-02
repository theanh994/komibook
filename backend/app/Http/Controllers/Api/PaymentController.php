<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\DemoWalletService;
use App\Services\Payments\PaymentProviderService;
use App\Services\Payments\SimulatedPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function providers(PaymentProviderService $providers): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $providers->capabilities()]);
    }

    public function create(Request $request, string $provider, SimulatedPaymentService $payments): JsonResponse
    {
        $validated = $request->validate(['order_id' => ['required', 'integer', 'exists:orders,id']]);

        return response()->json($payments->create($provider, (int) $validated['order_id'], $request->user()), 201);
    }

    public function complete(Request $request, string $provider, PaymentTransaction $paymentTransaction, SimulatedPaymentService $payments): JsonResponse
    {
        return response()->json($payments->complete($provider, $paymentTransaction, $request->user()));
    }

    public function wallet(Request $request, PaymentProviderService $providers, DemoWalletService $wallets): JsonResponse
    {
        abort_unless($providers->capability('demo_wallet')['available'], 503, 'Ví KomiBook đang bị khóa.');
        $account = $wallets->accountFor($request->user());

        return response()->json(['status' => 'success', 'data' => [
            'balance' => $account->balance,
            'reserved_balance' => $account->reserved_balance,
            'currency' => $account->currency,
            'entries' => $account->entries()->latest('id')->limit(50)->get([
                'id', 'entry_type', 'amount', 'balance_before', 'balance_after', 'created_at', 'metadata',
            ]),
            'notice' => 'Ví KomiBook dùng để thanh toán, nhận hoàn tiền và nhận doanh thu nội bộ. Hệ thống không hỗ trợ nạp tiền từ bên ngoài.',
        ]]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\VnpayCallbackService;
use App\Services\Payments\VnpayPaymentService;
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

    public function vnpayReturn(Request $request, VnpayCallbackService $callbackService)
    {
        $redirectUrl = $callbackService->handleReturn($request->query());

        return redirect($redirectUrl);
    }

    public function vnpayIpn(Request $request, VnpayCallbackService $callbackService)
    {
        $result = $callbackService->handleIpn($request->query());

        return response()->json($result);
    }
}

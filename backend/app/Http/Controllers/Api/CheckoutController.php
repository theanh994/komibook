<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use Exception;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * Handle the incoming checkout request.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $items = $request->input('items');
            $shippingData = [
                'shipping_address' => $request->input('shipping_address') ?: 'Digital delivery',
                'phone' => $request->input('phone') ?: 'Không áp dụng',
                'payment_method' => $request->input('payment_method'),
            ];
            $couponCode = $request->input('coupon_code');

            // Gọi service xử lý logic lõi
            $orders = $this->checkoutService->processCheckout(
                $items,
                $shippingData,
                $user->id,
                $couponCode,
                [
                    'accepted' => $request->boolean('ebook_terms_accepted'),
                    'accepted_at' => now()->toISOString(),
                    'ip_hash' => hash('sha256', (string) $request->ip()),
                    'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
                ],
            );

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Đặt hàng thành công. Đơn hàng đang được xử lý.',
                'data' => $orders,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

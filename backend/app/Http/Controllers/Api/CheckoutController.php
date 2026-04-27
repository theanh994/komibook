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
            $shippingData = $request->only(['shipping_address', 'phone']);

            // Gọi service xử lý logic lõi
            $orders = $this->checkoutService->processCheckout($items, $shippingData, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Đặt hàng thành công. Đơn hàng đang được xử lý.',
                'data' => $orders
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

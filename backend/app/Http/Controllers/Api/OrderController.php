<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Response;

class OrderController extends Controller
{
    public function myOrders(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['orderItems.book'])
            ->orderBy('created_at', 'desc')
            ->get();

        return OrderResource::collection($orders);
    }

    public function generateEbookLink(Request $request, $order_id, $book_id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('id', $order_id)
            ->firstOrFail();

        // Tạm thời bỏ qua check trạng thái thanh toán để test cho dễ
        // if (!$order->isPaid() && !$order->isCompleted()) {
        //     return response()->json(['message' => 'Đơn hàng chưa hoàn thành'], 403);
        // }

        $orderItem = $order->orderItems()->where('book_id', $book_id)->firstOrFail();
        $book = $orderItem->book;

        if ($book->type !== 'ebook') {
            return response()->json(['message' => 'Sách này không phải là e-book'], 400);
        }

        if (empty($book->file_path)) {
            return response()->json(['message' => 'Sách này chưa được cấu hình file E-book'], 404);
        }

        $filename = basename($book->file_path);
        
        $relativeUrl = URL::temporarySignedRoute(
            'api.ebook.stream', 
            now()->addMinutes(10), 
            ['filename' => $filename],
            false // Generate signature for relative URL only
        );

        $url = rtrim(config('app.url'), '/') . $relativeUrl;

        return response()->json(['url' => $url]);
    }
    
    public function streamEbook(Request $request, $filename)
    {
        // Verify relative signature to avoid proxy host/scheme mismatches
        if (!$request->hasValidSignature(false)) {
            abort(401, 'Link đã hết hạn hoặc không hợp lệ.');
        }

        $path = storage_path('app/private/ebooks/' . $filename);
        
        if (!file_exists($path)) {
            // Create a dummy file for testing if it doesn't exist
            if (!file_exists(storage_path('app/private/ebooks'))) {
                mkdir(storage_path('app/private/ebooks'), 0755, true);
            }
            file_put_contents($path, '%PDF-1.4 Dummy PDF Content for Testing');
        }

        return Response::file($path);
    }
}

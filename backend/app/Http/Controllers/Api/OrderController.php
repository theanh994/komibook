<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Vendor;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

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

    public function myLibrary(Request $request)
    {
        $userId = $request->user()->id;

        $orders = Order::where('user_id', $userId)
            ->with(['orderItems.book'])
            ->orderBy('created_at', 'desc')
            ->get();

        $libraryItems = [];
        $seenBookIds = [];

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                if ($item->book && !in_array($item->book_id, $seenBookIds)) {
                    $seenBookIds[] = $item->book_id;
                    $libraryItems[] = [
                        'order_id' => $order->id,
                        'status' => $order->order_status,
                        'purchased_at' => $order->created_at?->toISOString(),
                        'book' => [
                            'id' => $item->book->id,
                            'title' => $item->book->title,
                            'slug' => $item->book->slug,
                            'cover_image' => $item->book->cover_image,
                            'author' => $item->book->author_name ?? 'KomiBook Author',
                            'type' => $item->book->type ?? 'physical',
                            'file_path' => $item->book->file_path,
                        ]
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $libraryItems,
        ]);
    }

    public function generateEbookLink(Request $request, $order_id, $book_id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('id', $order_id)
            ->firstOrFail();

        // Kiểm tra đơn hàng đã thanh toán hoặc hoàn thành
        if (!$order->isPaid() && !$order->isCompleted()) {
            return response()->json(['message' => 'Đơn hàng chưa được thanh toán. Vui lòng thanh toán trước khi đọc.'], 403);
        }

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
            [
                'filename' => $filename,
                'email' => $request->user()->email,
                'name' => $request->user()->name
            ],
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

        // Chặn path traversal attack
        $filename = basename($filename);
        $path = storage_path('app/private/ebooks/' . $filename);
        
        if (!file_exists($path)) {
            abort(404, 'File e-book không tồn tại trên hệ thống.');
        }

        $email = $request->query('email', 'reader@komibook.com');
        $name = $request->query('name', 'Độc giả');

        // Bổ sung header watermark để frontend giải mã hiển thị Social DRM
        return Response::file($path, [
            'Access-Control-Expose-Headers' => 'X-Reader-Watermark-Email, X-Reader-Watermark-Name',
            'X-Reader-Watermark-Email' => base64_encode($email),
            'X-Reader-Watermark-Name' => base64_encode($name),
        ]);
    }

    /**
     * Cập nhật trạng thái giao hàng mô phỏng.
     */
    public function updateShippingStatus(Request $request, $id)
    {
        $request->validate([
            'shipping_status' => 'required|in:pending_pickup,delivering,delivered,failed',
            'shipping_carrier' => 'nullable|string',
        ]);

        $order = Order::findOrFail($id);

        // Sinh mã vận đơn ngẫu nhiên nếu chưa có
        if (empty($order->shipping_tracking_code)) {
            $carrier = $request->shipping_carrier ?? 'GHTK';
            $order->shipping_carrier = $carrier;
            $order->shipping_tracking_code = $carrier . rand(100000000, 999999999);
        } else if ($request->has('shipping_carrier')) {
            $order->shipping_carrier = $request->shipping_carrier;
        }

        $order->shipping_status = $request->shipping_status;

        // Nếu giao thành công -> đơn hàng chuyển sang completed
        if ($request->shipping_status === 'delivered') {
            $order->status = 'completed';
            
            // Tích lũy điểm thành viên: 1 điểm cho mỗi 10,000 VND spent
            $earnedPoints = floor($order->total_amount / 10000);
            if ($earnedPoints > 0) {
                $user = $order->user;
                if ($user) {
                    $user->increment('points', $earnedPoints);
                    
                    // Tự động kiểm tra và thăng hạng thành viên
                    $nextTier = \App\Models\MembershipTier::where('min_points', '<=', $user->points)
                        ->orderBy('min_points', 'desc')
                        ->first();
                    if ($nextTier && (!$user->membership_tier_id || $nextTier->id !== $user->membership_tier_id)) {
                        $user->membership_tier_id = $nextTier->id;
                        $user->save();
                    }
                }
            }
        } else if ($request->shipping_status === 'failed') {
            $order->status = 'cancelled';
        }

        $order->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật trạng thái giao hàng thành công.',
            'data' => $order
        ]);
    }
}

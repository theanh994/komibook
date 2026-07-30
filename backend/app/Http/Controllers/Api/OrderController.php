<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerOrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Models\EbookEntitlement;
use App\Models\EbookVersion;
use App\Models\Order;
use App\Services\CheckoutSessionLifecycleService;
use App\Services\EbookAccessService;
use App\Services\OrderFulfillmentService;
use App\Support\PublicMediaUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;

class OrderController extends Controller
{
    public function myOrders(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['orderItems.book'])
            ->orderBy('created_at', 'desc')
            ->get();

        return OrderResource::collection($orders)->additional([
            'status' => 'success',
        ]);
    }

    public function myOrderDetail(Request $request, $orderId)
    {
        $order = Order::withoutGlobalScopes()
            ->where('user_id', $request->user()->id)
            ->where('id', $orderId)
            ->with(['user', 'orderItems.book', 'invoiceSnapshot'])
            ->first();

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn hàng không tồn tại.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new CustomerOrderDetailResource($order),
        ]);
    }

    public function myLibrary(Request $request)
    {
        $user = $request->user();
        $ebookAccessService = app(EbookAccessService::class);

        $orders = Order::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->with(['orderItems.book'])
            ->orderBy('created_at', 'desc')
            ->get();

        $libraryItems = [];
        $seenBookIds = [];

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                if ($item->book && ! in_array($item->book_id, $seenBookIds)) {
                    $seenBookIds[] = $item->book_id;
                    $book = $item->book;

                    $validOrder = null;
                    if ($book->type === 'ebook') {
                        $validOrder = $ebookAccessService->getValidOrder($user, $book->id);
                    }
                    $entitlement = $book->type === 'ebook'
                        ? EbookEntitlement::where('user_id', $user->id)
                            ->where('book_id', $book->id)
                            ->whereNull('revoked_at')
                            ->with('purchaseVersion')
                            ->first()
                        : null;
                    $versions = $entitlement
                        ? EbookVersion::where('book_id', $book->id)
                            ->where('version', '>=', $entitlement->purchaseVersion->version)
                            ->orderByDesc('version')
                            ->get()
                            ->map(fn (EbookVersion $version) => [
                                'id' => $version->id,
                                'version' => $version->version,
                                'release_notes' => $version->release_notes,
                                'published_at' => $version->published_at?->toISOString(),
                                'is_purchase_version' => $version->id === $entitlement->purchase_version_id,
                            ])
                            ->values()
                        : collect();

                    $libraryItems[] = [
                        'order_id' => $book->type === 'ebook' ? ($validOrder?->id) : $order->id,
                        'status' => $order->status,
                        'purchased_at' => $order->created_at?->toISOString(),
                        'has_access' => $book->type === 'ebook' ? ($validOrder !== null) : false,
                        'purchase_version_id' => $entitlement?->purchase_version_id,
                        'purchase_version' => $entitlement?->purchaseVersion?->version,
                        'latest_version_id' => $versions->first()['id'] ?? null,
                        'latest_version' => $versions->first()['version'] ?? null,
                        'available_versions' => $versions,
                        'book' => [
                            'id' => $book->id,
                            'title' => $book->title,
                            'slug' => $book->slug,
                            'cover_image' => PublicMediaUrl::storage($book->cover_image),
                            'author' => $book->author_name ?? $book->author ?? 'Chưa cập nhật người viết',
                            'type' => $book->type ?? 'physical',
                        ],
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
        $user = $request->user();
        $ebookAccessService = app(EbookAccessService::class);

        $validOrder = $ebookAccessService->getValidOrder($user, (int) $book_id);

        if (! $validOrder || (int) $validOrder->id !== (int) $order_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn hàng chưa được thanh toán hoặc không đủ quyền truy cập.',
            ], 403);
        }

        $orderItem = $validOrder->orderItems()->where('book_id', $book_id)->first();
        if (! $orderItem || ! $orderItem->book) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sách không tồn tại trong đơn hàng.',
            ], 404);
        }

        $book = $orderItem->book;
        $entitlement = EbookEntitlement::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->whereNull('revoked_at')
            ->with('purchaseVersion')
            ->first();
        $versions = collect();
        $version = null;

        if ($entitlement) {
            abort_unless($entitlement->purchaseVersion, 403, 'Entitlement không có phiên bản mua hợp lệ.');
            $versions = EbookVersion::where('book_id', $book->id)
                ->where('version', '>=', $entitlement->purchaseVersion->version)
                ->orderByDesc('version')
                ->get();

            $version = $request->filled('version_id')
                ? $versions->firstWhere('id', $request->integer('version_id'))
                : $versions->first();

            abort_if($request->filled('version_id') && ! $version, 404, 'Phiên bản không thuộc quyền đọc.');
        } elseif ($request->filled('version_id')) {
            abort(403, 'Đơn hàng cũ chưa có quyền chọn phiên bản.');
        }

        $filePath = $version?->file_path ?? $book->file_path;
        if (empty($filePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sách này chưa được cấu hình file E-book',
            ], 404);
        }

        $filename = basename($filePath);

        $relativeUrl = URL::temporarySignedRoute(
            'api.ebook.stream',
            now()->addMinutes(10),
            [
                'filename' => $filename,
                'email' => $user->email,
                'name' => $user->name,
            ],
            false // Generate signature for relative URL only
        );

        $url = rtrim(config('app.url'), '/').$relativeUrl;

        return response()->json([
            'status' => 'success',
            'data' => [
                'url' => $url,
                'version_id' => $version?->id,
                'purchase_version_id' => $entitlement?->purchase_version_id,
                'available_versions' => $versions->map(fn (EbookVersion $availableVersion) => [
                    'id' => $availableVersion->id,
                    'version' => $availableVersion->version,
                    'release_notes' => $availableVersion->release_notes,
                    'published_at' => $availableVersion->published_at?->toISOString(),
                    'is_purchase_version' => $availableVersion->id === $entitlement?->purchase_version_id,
                ])->values(),
            ],
        ]);
    }

    public function streamEbook(Request $request, $filename)
    {
        // Verify relative signature to avoid proxy host/scheme mismatches
        if (! $request->hasValidSignature(false)) {
            abort(401, 'Link đã hết hạn hoặc không hợp lệ.');
        }

        // Chặn path traversal attack
        $filename = basename($filename);
        $path = storage_path('app/private/ebooks/'.$filename);

        if (! file_exists($path)) {
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
            'shipping_status' => 'required|in:pending_pickup,picked_up,delivering,delivered,failed',
            'shipping_carrier' => 'nullable|string',
            'shipping_tracking_code' => 'nullable|string',
        ]);

        try {
            $fulfillmentService = app(OrderFulfillmentService::class);
            $order = $fulfillmentService->updateShippingStatus(
                (int) $id,
                $request->shipping_status,
                $request->shipping_carrier,
                $request->shipping_tracking_code,
                'vendor',
                (int) $request->user()->id
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật trạng thái giao hàng thành công.',
                'data' => $order,
            ]);
        } catch (\LogicException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể cập nhật trạng thái giao hàng.',
            ], 404);
        }
    }

    /**
     * Buyer chủ động hủy đơn hàng / checkout session.
     */
    public function cancel(Request $request, $id)
    {
        $userId = (int) $request->user()->id;
        $orderId = (int) $id;

        try {
            $lifecycleService = app(CheckoutSessionLifecycleService::class);
            $cancelledOrders = $lifecycleService->cancelByBuyer($orderId, $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Hủy đơn hàng thành công.',
                'data' => OrderResource::collection(collect($cancelledOrders)),
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền thực hiện thao tác này',
            ], 403);
        } catch (\LogicException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn hàng không tồn tại hoặc không thể hủy',
            ], 404);
        }
    }
}

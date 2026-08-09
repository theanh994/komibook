<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsedBookListingResource;
use App\Models\Book;
use App\Models\DemoWalletAccount;
use App\Models\Order;
use App\Models\SellerFulfillmentAddress;
use App\Models\UsedBookListing;
use App\Models\Vendor;
use App\Models\WarehouseStock;
use App\Services\DemoWalletService;
use App\Services\OrderFulfillmentService;
use App\Services\ProductTaxonomyService;
use App\Services\UsedBookInventoryService;
use App\Services\UsedBookSellerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsedBookSellerController extends Controller
{
    public function index(Request $request, UsedBookSellerService $sellerService)
    {
        $sellerService->readProfileFor($request->user());
        $listings = UsedBookListing::query()
            ->where('seller_user_id', $request->user()->id)
            ->with(['book.category'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => UsedBookListingResource::collection($listings),
            'meta' => ['ownership' => 'used_book_seller', 'address_visibility' => 'private'],
        ]);
    }

    public function store(Request $request, UsedBookSellerService $sellerService, ProductTaxonomyService $taxonomy)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'required|integer|exists:categories,id',
            'price' => 'required|integer|min:1000',
            'condition' => 'required|in:like_new,good,fair',
            'defects' => 'nullable|string|max:3000',
            'quantity' => 'required|integer|min:1|max:100',
            'actual_photos' => 'required|array|min:1|max:8',
            'actual_photos.*' => 'required|image|max:10240',
            'authenticity_attested' => 'accepted',
        ]);
        $address = SellerFulfillmentAddress::where('user_id', $request->user()->id)
            ->where('status', 'verified')
            ->whereNull('retired_at')
            ->latest('verified_at')
            ->first();
        abort_unless((bool) $address, 422, 'Cần đăng ký địa chỉ gửi hàng trước khi thêm sách cũ.');
        $paths = collect($request->file('actual_photos'))
            ->map(fn ($file) => $file->store('used-books/photos', 'public'))
            ->all();

        [$book, $listing] = DB::transaction(function () use ($validated, $paths, $address, $request, $taxonomy, $sellerService) {
            $profile = $sellerService->profileFor($request->user());
            $vendor = Vendor::withoutGlobalScopes()->findOrFail($profile->catalog_vendor_id);
            $warehouse = $sellerService->ensureVendorWarehouse($vendor, $address);

            $book = Book::withoutGlobalScopes()->create($taxonomy->normalize([
                'vendor_id' => $profile->catalog_vendor_id,
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'slug' => Str::slug($validated['title']).'-used-'.Str::lower(Str::random(5)),
                'author' => $validated['author_name'],
                'description' => $validated['description'] ?? null,
                'cover_image' => $paths[0],
                'price' => $validated['price'],
                'stock' => $validated['quantity'],
                'type' => 'physical',
                'format' => 'physical',
                'provenance' => 'used_resale',
                'condition' => $validated['condition'],
                'fulfillment_mode' => 'seller_verified_address',
                'status' => 'draft',
                'publishing_status' => 'submitted_for_review',
            ]));

            WarehouseStock::create([
                'warehouse_id' => $warehouse->id,
                'book_id' => $book->id,
                'quantity' => $validated['quantity'],
            ]);

            $listing = UsedBookListing::create([
                'book_id' => $book->id,
                'warehouse_id' => $warehouse->id,
                'seller_user_id' => $request->user()->id,
                'seller_fulfillment_address_id' => $address->id,
                'condition' => $validated['condition'],
                'actual_photos' => $paths,
                'defects' => $validated['defects'] ?? null,
                'quantity_available' => $validated['quantity'],
                'authenticity_attested_at' => now(),
                'status' => 'pending',
            ]);

            return [$book, $listing];
        });

        $listing->setRelation('book', $book->loadMissing('category'));

        return response()->json(['status' => 'success', 'data' => new UsedBookListingResource($listing)], 201);
    }

    public function updateInventory(Request $request, UsedBookListing $listing, UsedBookInventoryService $inventory)
    {
        $validated = $request->validate(['quantity_available' => 'required|integer|min:0|max:100']);
        DB::transaction(function () use ($listing, $validated, $request, $inventory) {
            $locked = UsedBookListing::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->seller_user_id === (int) $request->user()->id, 403);
            $check = $inventory->inspect($locked, true);
            abort_unless($check['valid'], 422, 'Used-book inventory is incoherent: '.$check['reason_code']);
            $activeReservedQuantity = $inventory->activeReservedQuantityForStock((int) $check['stock']->id);
            abort_if(
                (int) $validated['quantity_available'] < $activeReservedQuantity,
                422,
                'Requested on-hand quantity is below active inventory reservations.'
            );
            $check['stock']->update(['quantity' => $validated['quantity_available']]);
            $check['book']->update(['stock' => $validated['quantity_available']]);
            $status = $locked->status;
            if ($status === 'active' && (int) $validated['quantity_available'] === 0) {
                $status = 'sold_out';
            }
            if ($status === 'sold_out' && (int) $validated['quantity_available'] > 0) {
                $status = 'active';
            }
            $locked->update(['quantity_available' => $validated['quantity_available'], 'status' => $status]);
        });

        return response()->json([
            'status' => 'success',
            'data' => new UsedBookListingResource($listing->fresh()->load('book.category')),
        ]);
    }

    public function showAddress(Request $request)
    {
        $address = SellerFulfillmentAddress::where('user_id', $request->user()->id)
            ->whereNull('retired_at')
            ->latest()
            ->first();

        return response()->json(['status' => 'success', 'data' => $address ? [
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'address_line' => $address->address_line,
            'ward' => $address->ward,
            'district' => $address->district,
            'province' => $address->province,
            'postal_code' => $address->postal_code,
            'status' => $address->status,
        ] : null]);
    }

    public function orders(Request $request, UsedBookSellerService $sellerService)
    {
        $profile = $sellerService->readProfileFor($request->user());
        $status = $request->query('status');
        $sellerListingBookIds = UsedBookListing::query()
            ->where('seller_user_id', $request->user()->id)
            ->select('book_id');

        $query = Order::withoutGlobalScopes()
            ->where('vendor_id', $profile->catalog_vendor_id)
            ->whereHas('orderItems')
            ->whereDoesntHave('orderItems', fn ($items) => $items->whereNotIn('book_id', $sellerListingBookIds))
            ->with(['orderItems.book.category', 'user', 'invoiceSnapshot'])
            ->latest('id');

        if ($status && $status !== 'all') {
            if ($status === 'processing') {
                $query->whereIn('status', ['confirmed', 'processing']);
            } else {
                $query->where('status', $status);
            }
        }

        $orders = $query->paginate(15);

        $orders->getCollection()->transform(function ($order) {
            $snapshot = $order->invoiceSnapshot?->buyer_snapshot ?? [];
            $itemSubtotal = (int) $order->orderItems->sum(fn ($item) => (int) $item->price * (int) $item->quantity);
            $shippingFee = (int) ($order->invoiceSnapshot?->shipping_fee_amount ?? max(0, (int) $order->total_amount - $itemSubtotal));
            $commissionRate = 10.0;
            $commissionAmount = (int) round(($itemSubtotal * $commissionRate) / 100);
            $netEarning = max(0, $itemSubtotal - $commissionAmount);

            return [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'item_subtotal' => $itemSubtotal,
                'shipping_fee' => $shippingFee,
                'commission_amount' => $commissionAmount,
                'net_earning' => $netEarning,
                'total_amount' => (int) $order->total_amount,
                'shipping_carrier' => $order->shipping_carrier,
                'shipping_tracking_code' => $order->shipping_tracking_code,
                'shipping_status' => $order->shipping_status ?? 'pending_pickup',
                'created_at' => $order->created_at?->toIso8601String(),
                'buyer' => [
                    'name' => $snapshot['name'] ?? $order->user?->name ?? 'Người mua',
                    'phone' => $snapshot['phone'] ?? $order->phone,
                    'shipping_address' => $snapshot['shipping_address'] ?? $order->shipping_address,
                ],
                'items' => $order->orderItems->map(fn ($item) => [
                    'id' => $item->id,
                    'book_id' => $item->book_id,
                    'title' => $item->book?->display_title ?: $item->book?->title,
                    'cover_image' => $item->book?->cover_image,
                    'price' => (int) $item->price,
                    'quantity' => (int) $item->quantity,
                    'condition' => $item->book?->condition,
                ]),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    public function showOrder(Request $request, Order $order, UsedBookSellerService $sellerService)
    {
        $profile = $sellerService->readProfileFor($request->user());
        abort_unless((int) $order->vendor_id === (int) $profile->catalog_vendor_id, 403, 'Không có quyền truy cập đơn hàng này.');
        abort_unless($this->isEligibleUsedBookSellerOrder($order, (int) $request->user()->id), 403, 'Không có quyền truy cập đơn hàng này.');

        $order->loadMissing(['orderItems.book.category', 'user', 'invoiceSnapshot']);
        $snapshot = $order->invoiceSnapshot?->buyer_snapshot ?? [];
        $itemSubtotal = (int) $order->orderItems->sum(fn ($item) => (int) $item->price * (int) $item->quantity);
        $shippingFee = (int) ($order->invoiceSnapshot?->shipping_fee_amount ?? max(0, (int) $order->total_amount - $itemSubtotal));
        $commissionRate = 10.0;
        $commissionAmount = (int) round(($itemSubtotal * $commissionRate) / 100);
        $netEarning = max(0, $itemSubtotal - $commissionAmount);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'item_subtotal' => $itemSubtotal,
                'shipping_fee' => $shippingFee,
                'commission_amount' => $commissionAmount,
                'net_earning' => $netEarning,
                'total_amount' => (int) $order->total_amount,
                'shipping_carrier' => $order->shipping_carrier,
                'shipping_tracking_code' => $order->shipping_tracking_code,
                'shipping_status' => $order->shipping_status ?? 'pending_pickup',
                'created_at' => $order->created_at?->toIso8601String(),
                'buyer' => [
                    'name' => $snapshot['name'] ?? $order->user?->name ?? 'Người mua',
                    'phone' => $snapshot['phone'] ?? $order->phone,
                    'shipping_address' => $snapshot['shipping_address'] ?? $order->shipping_address,
                ],
                'items' => $order->orderItems->map(fn ($item) => [
                    'id' => $item->id,
                    'book_id' => $item->book_id,
                    'title' => $item->book?->display_title ?: $item->book?->title,
                    'cover_image' => $item->book?->cover_image,
                    'price' => (int) $item->price,
                    'quantity' => (int) $item->quantity,
                    'condition' => $item->book?->condition,
                ]),
            ],
        ]);
    }

    public function shipOrder(Request $request, Order $order, UsedBookSellerService $sellerService, OrderFulfillmentService $fulfillmentService)
    {
        return DB::transaction(function () use ($request, $order, $sellerService, $fulfillmentService) {
            $lockedOrder = Order::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $profile = $sellerService->readProfileFor($request->user());
            abort_unless((int) $lockedOrder->vendor_id === (int) $profile->catalog_vendor_id, 403, 'Không có quyền xử lý đơn hàng này.');
            abort_unless($this->isEligibleUsedBookSellerOrder($lockedOrder, (int) $request->user()->id), 403, 'Không có quyền xử lý đơn hàng này.');

            if ($lockedOrder->status === 'completed') {
                return $this->shippingStatusResponse($lockedOrder, 'Đơn hàng đã hoàn tất trước đó.');
            }

            if ($lockedOrder->status === 'shipped') {
                return $this->shippingStatusResponse($lockedOrder, 'Đơn hàng đã được xác nhận đóng gói trước đó.');
            }

            if (! in_array($lockedOrder->status, ['pending', 'confirmed', 'processing'], true)) {
                return $this->sellerShippingStateErrorResponse();
            }

            $validated = $request->validate([
                'shipping_carrier' => 'required|string|max:255',
                'shipping_tracking_code' => 'required|string|max:255',
            ]);

            try {
                $updatedOrder = $fulfillmentService->updateOrderStatusByVendor(
                    $lockedOrder,
                    'shipped',
                    'vendor',
                    $request->user()->id
                );
            } catch (\LogicException) {
                return $this->sellerShippingStateErrorResponse();
            }

            $updatedOrder->shipping_carrier = $validated['shipping_carrier'];
            $updatedOrder->shipping_tracking_code = $validated['shipping_tracking_code'];
            $updatedOrder->shipping_status = 'pending_pickup';
            $updatedOrder->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã xác nhận đóng gói thành công! Đang chờ Đơn vị vận chuyển đến lấy hàng.',
                'data' => [
                    'id' => $updatedOrder->id,
                    'status' => $updatedOrder->status,
                    'shipping_status' => $updatedOrder->shipping_status,
                    'shipping_carrier' => $updatedOrder->shipping_carrier,
                    'shipping_tracking_code' => $updatedOrder->shipping_tracking_code,
                ],
            ]);
        });
    }

    public function advanceShippingStep(Request $request, Order $order, UsedBookSellerService $sellerService, OrderFulfillmentService $fulfillmentService)
    {
        return DB::transaction(function () use ($request, $order, $sellerService, $fulfillmentService) {
            $lockedOrder = Order::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $profile = $sellerService->readProfileFor($request->user());
            abort_unless((int) $lockedOrder->vendor_id === (int) $profile->catalog_vendor_id, 403, 'Không có quyền xử lý đơn hàng này.');
            abort_unless($this->isEligibleUsedBookSellerOrder($lockedOrder, (int) $request->user()->id), 403, 'Không có quyền xử lý đơn hàng này.');

            if ($lockedOrder->status === 'completed') {
                return $this->shippingStatusResponse($lockedOrder, 'Đơn hàng đã hoàn tất trước đó.');
            }

            if ($lockedOrder->status !== 'shipped') {
                return $this->sellerShippingStateErrorResponse();
            }

            $current = $lockedOrder->shipping_status ?? 'pending_pickup';
            $transitions = [
                'pending_pickup' => ['to' => 'picked_up', 'message' => 'Đã cập nhật: Đơn vị vận chuyển đã lấy hàng từ người bán.'],
                'picked_up' => ['to' => 'delivering', 'message' => 'Đã cập nhật: Đơn hàng đang được vận chuyển giao tới người mua.'],
                'delivering' => ['to' => 'awaiting_customer_confirmation', 'message' => 'Đã cập nhật: Đơn hàng đã giao tới địa chỉ, đang chờ người mua kiểm tra.'],
            ];

            if ($current === 'awaiting_customer_confirmation') {
                return $this->shippingStatusResponse($lockedOrder, 'Đơn hàng đang chờ người mua xác nhận đã nhận hàng.');
            }

            if (! isset($transitions[$current])) {
                return $this->sellerShippingStateErrorResponse();
            }

            try {
                $updatedOrder = $fulfillmentService->updateShippingStatus(
                    $lockedOrder,
                    $transitions[$current]['to'],
                    $lockedOrder->shipping_carrier,
                    $lockedOrder->shipping_tracking_code,
                    'vendor',
                    $request->user()->id
                );
            } catch (\LogicException) {
                return $this->sellerShippingStateErrorResponse();
            }

            return $this->shippingStatusResponse($updatedOrder, $transitions[$current]['message']);
        });
    }

    public function confirmDelivered(Request $request, Order $order, UsedBookSellerService $sellerService, OrderFulfillmentService $fulfillmentService)
    {
        return DB::transaction(function () use ($request, $order, $sellerService, $fulfillmentService) {
            $lockedOrder = Order::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $profile = $sellerService->readProfileFor($request->user());
            abort_unless((int) $lockedOrder->vendor_id === (int) $profile->catalog_vendor_id, 403, 'Không có quyền xử lý đơn hàng này.');
            abort_unless($this->isEligibleUsedBookSellerOrder($lockedOrder, (int) $request->user()->id), 403, 'Không có quyền xử lý đơn hàng này.');

            if ($lockedOrder->status === 'completed') {
                return $this->shippingStatusResponse($lockedOrder, 'Đơn hàng đã hoàn tất trước đó.');
            }

            if ($lockedOrder->status !== 'shipped') {
                return $this->sellerShippingStateErrorResponse();
            }

            $current = $lockedOrder->shipping_status ?? 'pending_pickup';
            if ($current === 'awaiting_customer_confirmation') {
                return $this->shippingStatusResponse($lockedOrder, 'Đơn hàng đang chờ người mua xác nhận đã nhận hàng.');
            }

            if ($current !== 'delivering') {
                return $this->sellerShippingStateErrorResponse();
            }

            try {
                $updatedOrder = $fulfillmentService->updateShippingStatus(
                    $lockedOrder,
                    'awaiting_customer_confirmation',
                    $lockedOrder->shipping_carrier,
                    $lockedOrder->shipping_tracking_code,
                    'vendor',
                    $request->user()->id
                );
            } catch (\LogicException) {
                return $this->sellerShippingStateErrorResponse();
            }

            return $this->shippingStatusResponse($updatedOrder, 'Đã cập nhật: Đơn hàng đã giao tới địa chỉ, đang chờ người mua kiểm tra.');
        });
    }

    private function shippingStatusResponse(Order $order, string $message)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'shipping_status' => $order->shipping_status,
                'shipping_carrier' => $order->shipping_carrier,
                'shipping_tracking_code' => $order->shipping_tracking_code,
            ],
        ]);
    }

    private function sellerShippingStateErrorResponse()
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Không thể cập nhật trạng thái giao hàng của đơn hàng này.',
        ], 422);
    }

    private function isEligibleUsedBookSellerOrder(Order $order, int $sellerUserId): bool
    {
        $bookIds = $order->orderItems()->pluck('book_id');
        if ($bookIds->isEmpty() || $bookIds->contains(null)) {
            return false;
        }

        $uniqueBookIds = $bookIds->unique()->values();

        return UsedBookListing::query()
            ->where('seller_user_id', $sellerUserId)
            ->whereIn('book_id', $uniqueBookIds)
            ->distinct()
            ->count('book_id') === $uniqueBookIds->count();
    }

    public function walletSummary(Request $request, UsedBookSellerService $sellerService, DemoWalletService $walletService)
    {
        $sellerService->readProfileFor($request->user());
        $account = DemoWalletAccount::where('user_id', $request->user()->id)->first();
        if (! $account) {
            return response()->json(['status' => 'success', 'data' => ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'VND', 'entries' => []]]);
        }

        $entries = $account->entries()
            ->latest('id')
            ->limit(30)
            ->get([
                'id', 'entry_type', 'amount', 'balance_before', 'balance_after', 'created_at', 'metadata',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'balance' => (int) $account->balance,
                'reserved_balance' => (int) $account->reserved_balance,
                'currency' => $account->currency,
                'entries' => $entries,
            ],
        ]);
    }

    public function upsertAddress(Request $request, UsedBookSellerService $sellerService)
    {
        $validated = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^0[0-9]{9}$/'],
            'address_line' => 'required|string|max:500',
            'ward' => 'nullable|string|max:120',
            'district' => 'nullable|string|max:120',
            'province' => 'required|string|max:120',
            'postal_code' => 'nullable|string|max:20',
        ]);

        $address = DB::transaction(function () use ($validated, $request, $sellerService) {
            $address = SellerFulfillmentAddress::updateOrCreate(
                ['user_id' => $request->user()->id, 'retired_at' => null],
                [...$validated, 'status' => 'verified', 'verified_at' => now(), 'verified_by' => $request->user()->id],
            );
            $sellerService->profileFor($request->user());

            return $address;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Đã lưu địa chỉ gửi hàng riêng tư.',
            'data' => ['status' => $address->status],
        ]);
    }
}

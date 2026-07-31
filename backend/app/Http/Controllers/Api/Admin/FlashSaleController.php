<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\FlashSale;
use App\Models\FlashSaleBook;
use App\Models\UserNotification;
use App\Models\VendorFlashSaleRequest;
use App\Models\VendorFollow;
use App\Services\FlashSaleWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FlashSaleController extends Controller
{
    public function vendorRequests()
    {
        return response()->json([
            'data' => VendorFlashSaleRequest::with([
                'vendor:id,shop_name',
                'book:id,title,cover_image',
            ])->latest()->get(),
        ]);
    }

    public function decideVendorRequest(Request $request, VendorFlashSaleRequest $promotionRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'decision_reason' => 'nullable|required_if:status,rejected|string|max:2000',
        ]);

        $campaign = DB::transaction(function () use ($promotionRequest, $request, $validated) {
            $promotionRequest->lockForUpdate();
            abort_unless($promotionRequest->status === 'pending', 422, 'Đề xuất này đã được xử lý.');
            $promotionRequest->update([
                ...$validated,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            $campaign = null;
            if ($validated['status'] === 'approved') {
                abort_unless($promotionRequest->preferred_start_time->isFuture(), 422, 'Chỉ được duyệt trước giờ bắt đầu chiến dịch.');
                $campaign = FlashSale::firstOrCreate([
                    'title' => $promotionRequest->title,
                    'start_time' => $promotionRequest->preferred_start_time,
                    'end_time' => $promotionRequest->preferred_end_time,
                ], [
                    'status' => 'enrollment_open',
                    'is_active' => false,
                    'timezone' => config('app.timezone', 'Asia/Ho_Chi_Minh'),
                    'coupon_stacking_policy' => 'deny',
                    'priority' => 0,
                    'created_by' => $request->user()->id,
                ]);

                $groups = collect($promotionRequest->groups ?: [[
                    'book_ids' => [$promotionRequest->book_id],
                    'discount_percent' => $promotionRequest->discount_percent,
                    'max_quantity' => $promotionRequest->max_quantity,
                ]]);
                $bookIds = $groups->flatMap(fn ($group) => $group['book_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
                $books = Book::withoutGlobalScopes()
                    ->where('vendor_id', $promotionRequest->vendor_id)
                    ->whereIn('id', $bookIds)
                    ->get()
                    ->keyBy('id');
                abort_unless($books->count() === $bookIds->count(), 422, 'Một số sách không còn thuộc gian hàng.');

                foreach ($groups as $group) {
                    foreach ($group['book_ids'] ?? [] as $bookId) {
                        $book = $books->get((int) $bookId);
                        abort_unless($book?->isPublished(), 422, 'Chỉ sách đang xuất bản mới được duyệt Flash Sale.');
                        $discount = (float) $group['discount_percent'];
                        $basePrice = (int) ($book->sale_price ?? $book->price);
                        FlashSaleBook::updateOrCreate([
                            'flash_sale_id' => $campaign->id,
                            'book_id' => $book->id,
                        ], [
                            'vendor_id' => $promotionRequest->vendor_id,
                            'discount_percent' => $discount,
                            'sale_price' => (int) round($basePrice * (100 - $discount) / 100),
                            'max_quantity' => (int) ($group['max_quantity'] ?? 0),
                            'sold_quantity' => 0,
                            'status' => 'approved',
                            'decided_by' => $request->user()->id,
                            'decision_reason' => null,
                        ]);
                    }
                }
            }

            $vendorUserId = $promotionRequest->vendor?->user_id;
            $recipientIds = collect([$vendorUserId]);
            if ($validated['status'] === 'approved' && Schema::hasTable('vendor_follows')) {
                $recipientIds = $recipientIds->merge(
                    VendorFollow::where('vendor_id', $promotionRequest->vendor_id)->pluck('user_id')
                );
            }
            $recipientIds->filter()->unique()->each(function ($userId) use ($promotionRequest, $campaign, $validated) {
                UserNotification::firstOrCreate([
                    'operation_key' => "vendor-flash-request:{$promotionRequest->id}:{$validated['status']}:user:{$userId}",
                ], [
                    'user_id' => $userId,
                    'title' => $validated['status'] === 'approved' ? 'Flash Sale sắp diễn ra' : 'Đề xuất Flash Sale bị từ chối',
                    'content' => $validated['status'] === 'approved'
                        ? "Chiến dịch {$promotionRequest->title} sẽ bắt đầu lúc {$promotionRequest->preferred_start_time->format('H:i d/m/Y')}."
                        : 'Đề xuất Flash Sale chưa được duyệt. Vui lòng xem lý do trong kênh Nhà bán.',
                    'type' => 'marketing',
                    'data' => ['flash_sale_id' => $campaign?->id, 'vendor_id' => $promotionRequest->vendor_id],
                ]);
            });

            return $campaign;
        });

        return response()->json([
            'message' => $validated['status'] === 'approved'
                ? 'Đã duyệt đề xuất Flash Sale.'
                : 'Đã từ chối đề xuất Flash Sale.',
            'data' => $promotionRequest->load([
                'vendor:id,shop_name',
                'book:id,title,cover_image',
            ]),
            'campaign' => $campaign,
        ]);
    }

    public function index()
    {
        $sales = FlashSale::withCount('items')
            ->withMax('items', 'discount_percent')
            ->orderBy('start_time', 'desc')
            ->get()
            ->map(function ($sale) {
                // Determine status based on dates
                $now = now();
                $status = 'active';
                if ($sale->start_time > $now) {
                    $status = 'upcoming';
                } elseif ($sale->end_time < $now || ! $sale->is_active) {
                    $status = 'ended';
                }

                return [
                    'id' => $sale->id,
                    'title' => $sale->title,
                    'start' => $sale->start_time->format('Y-m-d H:i'),
                    'end' => $sale->end_time->format('Y-m-d H:i'),
                    'products' => $sale->items_count,
                    'maxDiscount' => $sale->items_max_discount_percent ?: 0,
                    'status' => $sale->status,
                    'time_status' => $status,
                ];
            });

        return response()->json(['data' => $sales]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'is_active' => 'boolean',
            'timezone' => 'required|timezone',
            'coupon_stacking_policy' => 'required|in:allow,deny',
            'priority' => 'nullable|integer|min:0',
        ]);
        $sale = FlashSale::create([...$validated, 'status' => 'draft', 'is_active' => false, 'created_by' => $request->user()->id]);

        return response()->json(['message' => 'Flash sale created', 'data' => $sale], 201);
    }

    public function show(FlashSale $flashSale)
    {
        $flashSale->load(['items.book.vendor']);

        return response()->json(['data' => $flashSale]);
    }

    public function update(Request $request, FlashSale $flashSale)
    {
        abort_unless($flashSale->status === 'draft', 422);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'is_active' => 'boolean',
            'timezone' => 'required|timezone',
            'coupon_stacking_policy' => 'required|in:allow,deny',
            'priority' => 'nullable|integer|min:0',
        ]);

        $flashSale->update($validated);

        return response()->json(['message' => 'Flash sale updated', 'data' => $flashSale]);
    }

    public function destroy(FlashSale $flashSale)
    {
        abort_unless($flashSale->status === 'draft' && ! $flashSale->items()->exists(), 422);
        $flashSale->delete();

        return response()->json(['message' => 'Flash sale deleted']);
    }

    public function addItem(Request $request, FlashSale $flash_sale, FlashSaleWorkflowService $workflow)
    {
        $validated = $request->validate([
            'book_ids' => 'required|array',
            'book_ids.*' => 'exists:books,id',
            'discount_percent' => 'required|numeric|min:0|max:100',
            'max_quantity' => 'nullable|integer|min:0',
        ]);

        $added = [];
        foreach ($validated['book_ids'] as $bookId) {
            if ($flash_sale->items()->where('book_id', $bookId)->exists()) {
                continue;
            }

            $book = Book::withoutGlobalScopes()->with('vendor')->findOrFail($bookId);
            $item = $flash_sale->items()->create([
                'book_id' => $bookId,
                'vendor_id' => $book->vendor_id,
                'discount_percent' => $validated['discount_percent'],
                'max_quantity' => $validated['max_quantity'] ?? 0,
                'sold_quantity' => 0,
                'status' => 'pending',
            ]);
            $added[] = $workflow->decide($item, 'approved', $request->user(), null, 'flash-admin-item:'.$flash_sale->id.':'.$bookId.':'.now()->timestamp);
        }

        return response()->json(['message' => 'Items added', 'data' => $added], 201);
    }

    public function transition(Request $request, FlashSale $flashSale, FlashSaleWorkflowService $workflow)
    {
        $validated = $request->validate(['to_status' => 'required|in:enrollment_open,active,ended,cancelled', 'reason' => 'nullable|string|max:2000', 'operation_key' => 'required|string|max:128']);

        return response()->json(['status' => 'success', 'data' => $workflow->transition($flashSale, $validated['to_status'], $request->user(), $validated['reason'] ?? null, $validated['operation_key'])]);
    }

    public function approveItem(Request $request, $item_id, FlashSaleWorkflowService $workflow)
    {
        $validated = $request->validate(['operation_key' => 'required|string|max:128']);

        return response()->json(['data' => $workflow->decide(FlashSaleBook::findOrFail($item_id), 'approved', $request->user(), null, $validated['operation_key'])]);
    }

    public function rejectItem(Request $request, $item_id, FlashSaleWorkflowService $workflow)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000', 'operation_key' => 'required|string|max:128']);

        return response()->json(['data' => $workflow->decide(FlashSaleBook::findOrFail($item_id), 'rejected', $request->user(), $validated['reason'], $validated['operation_key'])]);
    }

    public function removeItem(FlashSale $flash_sale, $item_id)
    {
        $flash_sale->items()->where('id', $item_id)->delete();

        return response()->json(['message' => 'Item removed']);
    }

    public function bulkRemoveItems(Request $request, FlashSale $flash_sale)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $flash_sale->items()->whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Items removed']);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\FlashSale;
use App\Models\FlashSaleBook;
use App\Services\FlashSaleWorkflowService;
use Illuminate\Http\Request;

class FlashSaleController extends Controller
{
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

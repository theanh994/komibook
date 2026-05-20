<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
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
                } elseif ($sale->end_time < $now || !$sale->is_active) {
                    $status = 'ended';
                }

                return [
                    'id' => $sale->id,
                    'title' => $sale->title,
                    'start' => $sale->start_time->format('Y-m-d H:i'),
                    'end' => $sale->end_time->format('Y-m-d H:i'),
                    'products' => $sale->items_count,
                    'maxDiscount' => $sale->items_max_discount_percent ?: 0,
                    'status' => $status,
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
        ]);

        $sale = FlashSale::create($validated);

        return response()->json(['message' => 'Flash sale created', 'data' => $sale], 201);
    }

    public function show(FlashSale $flashSale)
    {
        $flashSale->load('items.book');
        return response()->json(['data' => $flashSale]);
    }

    public function update(Request $request, FlashSale $flashSale)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'is_active' => 'boolean',
        ]);

        $flashSale->update($validated);

        return response()->json(['message' => 'Flash sale updated', 'data' => $flashSale]);
    }

    public function destroy(FlashSale $flashSale)
    {
        $flashSale->delete();
        return response()->json(['message' => 'Flash sale deleted']);
    }

    public function addItem(Request $request, FlashSale $flash_sale)
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

            $item = $flash_sale->items()->create([
                'book_id' => $bookId,
                'discount_percent' => $validated['discount_percent'],
                'max_quantity' => $validated['max_quantity'] ?? 0,
                'sold_quantity' => 0,
            ]);
            $added[] = $item->load('book');
        }

        return response()->json(['message' => 'Items added', 'data' => $added], 201);
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\UsedBookListing;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function index()
    {
        $vendor = Auth::user()->vendor;
        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse'])->where('vendor_id', $vendor->id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $transfers,
        ]);
    }

    public function show($id)
    {
        $vendor = Auth::user()->vendor;
        $transfer = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'items.book'])
            ->where('vendor_id', $vendor->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $transfer,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'reason' => 'nullable|string',
            'items' => 'required|array',
            'items.*.book_id' => 'required|exists:books,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $vendor = Auth::user()->vendor;
        $fromWarehouse = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->findOrFail($request->from_warehouse_id);
        $toWarehouse = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->findOrFail($request->to_warehouse_id);
        $bookIds = collect($request->items)->pluck('book_id')->map(fn ($id) => (int) $id)->unique();
        $ownedBookIds = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->whereIn('id', $bookIds)->pluck('id');
        abort_unless($ownedBookIds->count() === $bookIds->count(), 403, 'Phiếu điều chuyển chứa sách không thuộc gian hàng hiện tại.');
        $transferCode = 'TRF-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));

        abort_if(UsedBookListing::whereIn('book_id', $bookIds)->exists(), 422, 'Used-book inventory can only be changed through its canonical path.');

        return DB::transaction(function () use ($request, $vendor, $transferCode, $fromWarehouse, $toWarehouse) {
            $transfer = StockTransfer::create([
                'vendor_id' => $vendor->id,
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'transfer_code' => $transferCode,
                'reason' => $request->reason,
                'status' => 'draft',
            ]);

            foreach ($request->items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'book_id' => $item['book_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Lập phiếu điều chuyển nháp thành công.',
                'data' => $transfer->load('items'),
            ], 201);
        });
    }

    public function ship($id)
    {
        $vendor = Auth::user()->vendor;
        $transfer = StockTransfer::where('vendor_id', $vendor->id)->findOrFail($id);

        if ($transfer->status !== 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Phiếu chuyển chỉ được xuất kho khi ở trạng thái nháp.',
            ], 422);
        }

        return DB::transaction(function () use ($transfer, $vendor) {
            abort_if(UsedBookListing::whereIn('book_id', $transfer->items()->pluck('book_id'))->exists(), 422, 'Used-book inventory can only be changed through its canonical path.');
            $ownedWarehouseIds = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->pluck('id');
            foreach ($transfer->items as $item) {
                // Trừ kho xuất
                $stockFrom = WarehouseStock::where('warehouse_id', $transfer->from_warehouse_id)
                    ->where('book_id', $item->book_id)
                    ->first();

                if (! $stockFrom || $stockFrom->quantity < $item->quantity) {
                    throw new \Exception("Sách ID {$item->book_id} không đủ số lượng trong kho xuất.");
                }

                $stockFrom->quantity -= $item->quantity;
                $stockFrom->save();
            }

            $transfer->status = 'shipped';
            $transfer->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã xuất kho và chuyển hàng thành công.',
            ]);
        });
    }

    public function receive($id)
    {
        $vendor = Auth::user()->vendor;
        $transfer = StockTransfer::where('vendor_id', $vendor->id)->findOrFail($id);

        if ($transfer->status !== 'shipped') {
            return response()->json([
                'status' => 'error',
                'message' => 'Phiếu chuyển chỉ được nhập kho khi ở trạng thái đã xuất hàng.',
            ], 422);
        }

        $ownedWarehouseIds = Warehouse::where('vendor_id', $vendor->id)->pluck('id');

        return DB::transaction(function () use ($transfer, $vendor, $ownedWarehouseIds) {
            $items = $transfer->items()->orderBy('id')->lockForUpdate()->get();
            abort_if(UsedBookListing::whereIn('book_id', $items->pluck('book_id'))->exists(), 422, 'Used-book inventory can only be changed through its canonical path.');
            foreach ($items as $item) {
                // Cộng kho nhập
                $stockTo = WarehouseStock::where('warehouse_id', $transfer->to_warehouse_id)
                    ->where('book_id', $item->book_id)
                    ->first();

                if ($stockTo) {
                    $stockTo->quantity += $item->quantity;
                    $stockTo->save();
                } else {
                    WarehouseStock::create([
                        'warehouse_id' => $transfer->to_warehouse_id,
                        'book_id' => $item->book_id,
                        'quantity' => $item->quantity,
                    ]);
                }

                // Cập nhật lại tồn tổng của sách trong bảng books
                $book = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->find($item->book_id);
                if ($book && $book->type !== 'ebook') {
                    $totalStock = WarehouseStock::where('book_id', $book->id)
                        ->whereIn('warehouse_id', $ownedWarehouseIds)
                        ->sum('quantity');
                    $book->stock = $totalStock;
                    $book->save();
                }
            }

            $transfer->status = 'received';
            $transfer->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã xác nhận nhận hàng và nhập kho thành công.',
            ]);
        });
    }
}

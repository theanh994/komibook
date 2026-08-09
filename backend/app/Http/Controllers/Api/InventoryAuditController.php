<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\UsedBookListing;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryAuditController extends Controller
{
    public function index()
    {
        $vendor = Auth::user()->vendor;
        $audits = InventoryAudit::with(['warehouse', 'auditor'])->where('vendor_id', $vendor->id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $audits,
        ]);
    }

    public function show($id)
    {
        $vendor = Auth::user()->vendor;
        $audit = InventoryAudit::with(['warehouse', 'auditor', 'items.book'])
            ->where('vendor_id', $vendor->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $audit,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'audit_period' => 'required|string|max:100',
            'items' => 'required|array',
            'items.*.book_id' => 'required|exists:books,id',
            'items.*.physical_qty' => 'required|integer|min:0',
        ]);

        $user = Auth::user();
        $vendor = $user->vendor;
        $warehouse = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->findOrFail($request->warehouse_id);
        $bookIds = collect($request->items)->pluck('book_id')->map(fn ($id) => (int) $id)->unique();
        $ownedBookIds = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->whereIn('id', $bookIds)->pluck('id');
        abort_unless($ownedBookIds->count() === $bookIds->count(), 403, 'Phiếu kiểm kê chứa sách không thuộc gian hàng hiện tại.');

        abort_if(UsedBookListing::whereIn('book_id', $bookIds)->exists(), 422, 'Used-book inventory can only be changed through its canonical path.');

        return DB::transaction(function () use ($request, $user, $vendor, $warehouse) {
            $audit = InventoryAudit::create([
                'vendor_id' => $vendor->id,
                'warehouse_id' => $warehouse->id,
                'audit_period' => $request->audit_period,
                'audited_by' => $user->id,
                'status' => 'draft',
            ]);

            foreach ($request->items as $item) {
                // Lấy tồn hệ thống hiện tại của book trong kho này
                $stock = WarehouseStock::where('warehouse_id', $warehouse->id)
                    ->where('book_id', $item['book_id'])
                    ->first();
                $systemQty = $stock ? $stock->quantity : 0;
                $difference = $item['physical_qty'] - $systemQty;

                InventoryAuditItem::create([
                    'inventory_audit_id' => $audit->id,
                    'book_id' => $item['book_id'],
                    'system_qty' => $systemQty,
                    'physical_qty' => $item['physical_qty'],
                    'difference' => $difference,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Lập phiếu kiểm kê nháp thành công.',
                'data' => $audit->load('items'),
            ], 201);
        });
    }

    public function complete($id)
    {
        $vendor = Auth::user()->vendor;
        $audit = InventoryAudit::where('vendor_id', $vendor->id)->findOrFail($id);

        if ($audit->status === 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Phiếu kiểm kê này đã được xác nhận và đối soát rồi.',
            ], 422);
        }

        return DB::transaction(function () use ($audit, $vendor) {
            abort_if(UsedBookListing::whereIn('book_id', $audit->items()->pluck('book_id'))->exists(), 422, 'Used-book inventory can only be changed through its canonical path.');
            $ownedWarehouseIds = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->pluck('id');
            foreach ($audit->items as $item) {
                // Cập nhật số lượng trong warehouse_stocks
                $stock = WarehouseStock::where('warehouse_id', $audit->warehouse_id)
                    ->where('book_id', $item->book_id)
                    ->first();

                if ($stock) {
                    $stock->quantity = $item->physical_qty;
                    $stock->save();
                } else {
                    WarehouseStock::create([
                        'warehouse_id' => $audit->warehouse_id,
                        'book_id' => $item->book_id,
                        'quantity' => $item->physical_qty,
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

            $audit->status = 'completed';
            $audit->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Đối soát và điều chỉnh tồn kho thành công!',
            ]);
        });
    }
}

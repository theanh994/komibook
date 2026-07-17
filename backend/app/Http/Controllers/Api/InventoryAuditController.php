<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\WarehouseStock;
use App\Models\Book;
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
            'data' => $audits
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
            'data' => $audit
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

        return DB::transaction(function () use ($request, $user, $vendor) {
            $audit = InventoryAudit::create([
                'vendor_id' => $vendor->id,
                'warehouse_id' => $request->warehouse_id,
                'audit_period' => $request->audit_period,
                'audited_by' => $user->id,
                'status' => 'draft',
            ]);

            foreach ($request->items as $item) {
                // Lấy tồn hệ thống hiện tại của book trong kho này
                $stock = WarehouseStock::where('warehouse_id', $request->warehouse_id)
                    ->where('book_id', $item['book_id'])
                    ->first();
                $systemQty = $stock ? $stock->stock : 0;
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
                'data' => $audit->load('items')
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
                'message' => 'Phiếu kiểm kê này đã được xác nhận và đối soát rồi.'
            ], 422);
        }

        return DB::transaction(function () use ($audit) {
            foreach ($audit->items as $item) {
                // Cập nhật số lượng trong warehouse_stocks
                $stock = WarehouseStock::where('warehouse_id', $audit->warehouse_id)
                    ->where('book_id', $item->book_id)
                    ->first();

                if ($stock) {
                    $stock->stock = $item->physical_qty;
                    $stock->save();
                } else {
                    WarehouseStock::create([
                        'warehouse_id' => $audit->warehouse_id,
                        'book_id' => $item->book_id,
                        'stock' => $item->physical_qty,
                    ]);
                }

                // Cập nhật lại tồn tổng của sách trong bảng books
                $book = Book::withoutGlobalScopes()->find($item->book_id);
                if ($book && $book->type !== 'ebook') {
                    $totalStock = WarehouseStock::where('book_id', $book->id)->sum('stock');
                    $book->stock = $totalStock;
                    $book->save();
                }
            }

            $audit->status = 'completed';
            $audit->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Đối soát và điều chỉnh tồn kho thành công!'
            ]);
        });
    }
}

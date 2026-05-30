<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    /**
     * Lấy danh sách kho và chi tiết tồn kho từng sách.
     */
    public function index(Request $request)
    {
        $vendor = Auth::user()->vendor;
        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        // 1. Danh sách kho hàng
        $warehouses = Warehouse::orderBy('id', 'asc')->get();

        // 2. Danh sách sách và phân bổ tồn kho
        $booksQuery = Book::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id);

        // Áp dụng filters
        if ($request->filled('warehouse_id') && $request->warehouse_id !== 'Tất cả kho') {
            $warehouseId = $request->warehouse_id;
            $booksQuery->whereHas('stocks', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        }

        if ($request->filled('type') && $request->type !== 'Tất cả loại sách') {
            $type = $request->type === 'Ebook' ? 'ebook' : 'physical';
            $booksQuery->where('type', $type);
        }

        if ($request->filled('status') && $request->status !== 'Tất cả trạng thái') {
            $statusFilter = $request->status;
            if ($statusFilter === 'Còn hàng') {
                $booksQuery->where('stock', '>=', 10);
            } elseif ($statusFilter === 'Sắp hết') {
                $booksQuery->where('stock', '>', 0)->where('stock', '<', 10);
            } elseif ($statusFilter === 'Hết hàng') {
                $booksQuery->where('stock', 0);
            }
        }

        // Phân trang
        $books = $booksQuery->orderBy('id', 'desc')->paginate(10);

        $stocksData = [];
        foreach ($books as $book) {
            $breakdown = [];
            $mainLocation = 'Digital Server';

            if ($book->type === 'physical') {
                $stocks = WarehouseStock::where('book_id', $book->id)
                    ->with('warehouse')
                    ->get();

                $mainLocation = 'Chưa phân bổ';
                if ($stocks->isNotEmpty()) {
                    $firstStock = $stocks->first();
                    $mainLocation = $firstStock->warehouse->name . ' - ' . ($firstStock->shelf_location ?: 'Chưa rõ kệ');
                }

                // Đảm bảo trả về đủ breakdown cho tất cả các kho của Vendor này
                foreach ($warehouses as $wh) {
                    $stockInWh = $stocks->firstWhere('warehouse_id', $wh->id);
                    $breakdown[] = [
                        'warehouse_id' => $wh->id,
                        'warehouse_name' => $wh->name,
                        'shelf_location' => $stockInWh ? $stockInWh->shelf_location : '-',
                        'quantity' => $stockInWh ? $stockInWh->quantity : 0
                    ];
                }
            }

            // Trạng thái tồn kho
            $stockStatus = 'Còn hàng';
            if ($book->stock === 0) {
                $stockStatus = 'Hết hàng';
            } elseif ($book->stock < 10) {
                $stockStatus = 'Sắp hết';
            }

            $stocksData[] = [
                'id' => $book->id,
                'sku' => $book->isbn ?: 'SKU-' . str_pad($book->id, 4, '0', STR_PAD_LEFT),
                'title' => $book->title,
                'cover_image' => $book->cover_image,
                'type' => $book->type === 'ebook' ? 'Ebook' : 'Sách vật lý',
                'stock' => $book->stock,
                'main_location' => $mainLocation,
                'status' => $stockStatus,
                'breakdown' => $breakdown
            ];
        }

        return response()->json([
            'warehouses' => $warehouses,
            'stocks' => $stocksData,
            'pagination' => [
                'total' => $books->total(),
                'per_page' => $books->perPage(),
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'from' => $books->firstItem(),
                'to' => $books->lastItem(),
            ]
        ]);
    }

    /**
     * Tạo kho mới.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'capacity' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $warehouse = Warehouse::create([
            'name' => $request->name,
            'address' => $request->address,
            'capacity' => $request->capacity ?: '0%',
            'status' => $request->status ?: 'Hoạt động',
        ]);

        return response()->json([
            'message' => 'Tạo kho hàng thành công',
            'warehouse' => $warehouse
        ], 201);
    }

    /**
     * Điều chỉnh hoặc điều chuyển tồn kho giữa các kho.
     */
    public function adjustStock(Request $request)
    {
        $request->validate([
            'type' => 'required|in:adjust,transfer',
            'book_id' => 'required|exists:books,id',
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'target_warehouse_id' => 'required_if:type,transfer|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
            'shelf_location' => 'nullable|string',
        ]);

        $book = Book::withoutGlobalScopes()->findOrFail($request->book_id);
        
        DB::beginTransaction();
        try {
            if ($request->type === 'adjust') {
                // Điều chỉnh tăng/giảm tồn kho tại 1 kho cụ thể
                $stock = WarehouseStock::firstOrNew([
                    'warehouse_id' => $request->source_warehouse_id,
                    'book_id' => $book->id
                ]);
                
                $stock->quantity = $request->quantity;
                if ($request->filled('shelf_location')) {
                    $stock->shelf_location = $request->shelf_location;
                }
                $stock->save();
            } else {
                // Điều chuyển từ kho A sang kho B
                $sourceStock = WarehouseStock::where('warehouse_id', $request->source_warehouse_id)
                    ->where('book_id', $book->id)
                    ->first();

                if (!$sourceStock || $sourceStock->quantity < $request->quantity) {
                    return response()->json(['message' => 'Số lượng tồn kho nguồn không đủ để điều chuyển'], 400);
                }

                // Trừ kho nguồn
                $sourceStock->quantity -= $request->quantity;
                $sourceStock->save();

                // Cộng kho đích
                $targetStock = WarehouseStock::firstOrNew([
                    'warehouse_id' => $request->target_warehouse_id,
                    'book_id' => $book->id
                ]);
                $targetStock->quantity += $request->quantity;
                if ($request->filled('shelf_location')) {
                    $targetStock->shelf_location = $request->shelf_location;
                }
                $targetStock->save();
            }

            // Đồng bộ lại tổng tồn kho (stock) trong bảng books
            $totalStock = WarehouseStock::where('book_id', $book->id)->sum('quantity');
            $book->update(['stock' => $totalStock]);

            // Xoá key cache tồn kho trên Redis để đồng bộ tồn kho mới nhất
            try {
                \Illuminate\Support\Facades\Redis::del("book_stock:{$book->id}");
            } catch (\Exception $ex) {
                \Illuminate\Support\Facades\Log::warning("Failed to clear Redis stock cache: " . $ex->getMessage());
            }

            DB::commit();
            return response()->json(['message' => 'Cập nhật tồn kho thành công', 'total_stock' => $totalStock]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lấy các chỉ số thống kê kho.
     */
    public function stats()
    {
        $vendor = Auth::user()->vendor;
        if (!$vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        // Tổng mặt hàng
        $totalItems = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->count();

        // Số mặt hàng sắp hết (stock < 10 và > 0)
        $lowStockItems = Book::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('stock', '>', 0)
            ->where('stock', '<', 10)
            ->count();

        // Số mặt hàng hết hàng (stock == 0)
        $outOfStockItems = Book::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('stock', 0)
            ->count();

        return response()->json([
            'total_items' => $totalItems,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
        ]);
    }
}

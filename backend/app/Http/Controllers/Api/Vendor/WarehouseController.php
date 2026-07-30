<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WarehouseController extends Controller
{
    /**
     * Lấy danh sách kho và chi tiết tồn kho từng sách.
     */
    public function index(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (! $vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        // 1. Danh sách kho hàng
        $warehouses = Warehouse::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->orderBy('id', 'asc')
            ->get();
        $warehouseIds = $warehouses->pluck('id');

        // 2. Danh sách sách và phân bổ tồn kho
        $booksQuery = Book::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id);

        // Áp dụng filters
        if ($request->filled('warehouse_id') && $request->warehouse_id !== 'Tất cả kho') {
            $warehouseId = (int) $request->warehouse_id;
            abort_unless($warehouseIds->contains($warehouseId), 403, 'Kho không thuộc gian hàng hiện tại.');
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
        $pageBookIds = $books->getCollection()->pluck('id');
        $stocksByBook = $pageBookIds->isEmpty()
            ? collect()
            : WarehouseStock::whereIn('book_id', $pageBookIds)
                ->whereIn('warehouse_id', $warehouseIds)
                ->with('warehouse')
                ->get()
                ->groupBy('book_id');

        $stocksData = [];
        foreach ($books as $book) {
            $breakdown = [];
            $mainLocation = 'Digital Server';

            if ($book->type === 'physical') {
                $stocks = $stocksByBook->get($book->id, collect());

                $mainLocation = 'Chưa phân bổ';
                if ($stocks->isNotEmpty()) {
                    $firstStock = $stocks->first();
                    $mainLocation = $firstStock->warehouse->name.' - '.($firstStock->shelf_location ?: 'Chưa rõ kệ');
                }

                // Đảm bảo trả về đủ breakdown cho tất cả các kho của Vendor này
                foreach ($warehouses as $wh) {
                    $stockInWh = $stocks->firstWhere('warehouse_id', $wh->id);
                    $breakdown[] = [
                        'warehouse_id' => $wh->id,
                        'warehouse_name' => $wh->name,
                        'shelf_location' => $stockInWh ? $stockInWh->shelf_location : '-',
                        'quantity' => $stockInWh ? $stockInWh->quantity : 0,
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
                'sku' => $book->isbn ?: 'SKU-'.str_pad($book->id, 4, '0', STR_PAD_LEFT),
                'title' => $book->title,
                'cover_image' => PublicMediaUrl::storage($book->cover_image),
                'type' => $book->type === 'ebook' ? 'Ebook' : 'Sách vật lý',
                'stock' => $book->stock,
                'main_location' => $mainLocation,
                'status' => $stockStatus,
                'breakdown' => $breakdown,
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
            ],
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

        $vendor = $request->user()->vendor;
        if (! $vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        $warehouse = Warehouse::create([
            'vendor_id' => $vendor->id,
            'author_fulfillment_address_id' => null,
            'name' => $request->name,
            'address' => $request->address,
            'capacity' => $request->capacity ?: '0%',
            'status' => $request->status ?: 'Hoạt động',
        ]);

        return response()->json([
            'message' => 'Tạo kho hàng thành công',
            'warehouse' => $warehouse,
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

        $vendor = $request->user()->vendor;
        abort_unless($vendor, 404, 'Vendor profile not found');

        $book = Book::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->findOrFail($request->book_id);
        $sourceWarehouse = Warehouse::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->findOrFail($request->source_warehouse_id);
        $targetWarehouse = null;
        if ($request->input('type') === 'transfer') {
            $targetWarehouse = Warehouse::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->findOrFail($request->target_warehouse_id);
            abort_if($sourceWarehouse->is($targetWarehouse), 422, 'Kho nguồn và kho đích phải khác nhau.');
        }

        DB::beginTransaction();
        try {
            if ($request->type === 'adjust') {
                // Điều chỉnh tăng/giảm tồn kho tại 1 kho cụ thể
                $stock = WarehouseStock::firstOrNew([
                    'warehouse_id' => $sourceWarehouse->id,
                    'book_id' => $book->id,
                ]);

                $stock->quantity = $request->quantity;
                if ($request->filled('shelf_location')) {
                    $stock->shelf_location = $request->shelf_location;
                }
                $stock->save();
            } else {
                // Điều chuyển từ kho A sang kho B
                $sourceStock = WarehouseStock::where('warehouse_id', $sourceWarehouse->id)
                    ->where('book_id', $book->id)
                    ->first();

                if (! $sourceStock || $sourceStock->quantity < $request->quantity) {
                    DB::rollBack();

                    return response()->json(['message' => 'Số lượng tồn kho nguồn không đủ để điều chuyển'], 400);
                }

                // Trừ kho nguồn
                $sourceStock->quantity -= $request->quantity;
                $sourceStock->save();

                // Cộng kho đích
                $targetStock = WarehouseStock::firstOrNew([
                    'warehouse_id' => $targetWarehouse->id,
                    'book_id' => $book->id,
                ]);
                $targetStock->quantity += $request->quantity;
                if ($request->filled('shelf_location')) {
                    $targetStock->shelf_location = $request->shelf_location;
                }
                $targetStock->save();
            }

            // Đồng bộ lại tổng tồn kho (stock) trong bảng books
            $ownedWarehouseIds = Warehouse::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->pluck('id');
            $totalStock = WarehouseStock::where('book_id', $book->id)
                ->whereIn('warehouse_id', $ownedWarehouseIds)
                ->sum('quantity');
            $book->update(['stock' => $totalStock]);

            // Xoá key cache tồn kho trên Redis để đồng bộ tồn kho mới nhất
            try {
                Redis::del("book_stock:{$book->id}");
            } catch (\Exception $ex) {
                Log::warning('Failed to clear Redis stock cache: '.$ex->getMessage());
            }

            DB::commit();

            return response()->json(['message' => 'Cập nhật tồn kho thành công', 'total_stock' => $totalStock]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Lỗi hệ thống: '.$e->getMessage()], 500);
        }
    }

    /**
     * Lấy các chỉ số thống kê kho.
     */
    public function stats(Request $request)
    {
        $vendor = $request->user()->vendor;
        if (! $vendor) {
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

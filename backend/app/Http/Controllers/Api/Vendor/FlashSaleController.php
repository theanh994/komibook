<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\FlashSale;
use App\Models\FlashSaleBook;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FlashSaleController extends Controller
{
    /**
     * Lấy danh sách các chiến dịch Flash Sale mà Vendor có thể tham gia.
     */
    public function index()
    {
        $now = now();
        $vendor = Auth::user()->vendor()->withoutGlobalScopes()->first();

        if (! $vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        // Lấy tất cả flash sale chưa kết thúc (đang hoạt động hoặc sắp diễn ra)
        $sales = FlashSale::where('status', 'enrollment_open')
            ->where('end_time', '>', $now)
            ->orderBy('start_time', 'asc')
            ->get()
            ->map(function ($sale) use ($vendor, $now) {
                // Đếm số lượng sách của Vendor này đã đăng ký trong chiến dịch này
                $registeredCount = FlashSaleBook::where('flash_sale_id', $sale->id)
                    ->whereHas('book', function ($q) use ($vendor) {
                        $q->where('vendor_id', $vendor->id);
                    })
                    ->count();

                // Xác định trạng thái của chiến dịch
                $status = 'active';
                if ($sale->start_time > $now) {
                    $status = 'upcoming';
                }

                return [
                    'id' => $sale->id,
                    'title' => $sale->title,
                    'start' => $sale->start_time->format('Y-m-d H:i'),
                    'end' => $sale->end_time->format('Y-m-d H:i'),
                    'registered_count' => $registeredCount,
                    'status' => $status,
                ];
            });

        return response()->json(['data' => $sales]);
    }

    /**
     * Lấy danh sách sách của Vendor này đã đăng ký trong một chiến dịch cụ thể.
     */
    public function registeredBooks(FlashSale $flash_sale)
    {
        $vendor = Auth::user()->vendor()->withoutGlobalScopes()->first();

        if (! $vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }

        $items = FlashSaleBook::where('flash_sale_id', $flash_sale->id)
            ->whereHas('book', function ($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })
            ->with(['book:id,title,price,cover_image'])
            ->get()
            ->each(function (FlashSaleBook $item) {
                if ($item->book) {
                    $item->book->cover_image = PublicMediaUrl::storage($item->book->cover_image);
                }
            });

        return response()->json(['data' => $items]);
    }

    /**
     * Đăng ký sách tham gia Flash Sale (chờ Admin duyệt).
     */
    public function register(Request $request, FlashSale $flash_sale)
    {
        $vendor = Auth::user()->vendor()->withoutGlobalScopes()->first();

        if (! $vendor) {
            return response()->json(['message' => 'Vendor profile not found'], 404);
        }
        abort_unless($flash_sale->status === 'enrollment_open' && $flash_sale->end_time->isFuture(), 422);

        $validated = $request->validate([
            'book_ids' => 'required|array|min:1',
            'book_ids.*' => 'integer|distinct',
            'discount_percent' => 'required|numeric|min:1|max:99',
            'max_quantity' => 'nullable|integer|min:1',
        ]);

        $bookIds = collect($validated['book_ids'])->map(fn ($id) => (int) $id);
        $books = Book::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereIn('id', $bookIds)
            ->get()
            ->keyBy('id');
        abort_unless($books->count() === $bookIds->count(), 403, 'Đề xuất chứa sách không thuộc gian hàng của bạn.');
        $existingItems = FlashSaleBook::where('flash_sale_id', $flash_sale->id)
            ->whereIn('book_id', $bookIds)
            ->get()
            ->keyBy('book_id');

        foreach ($bookIds as $bookId) {
            $book = $books->get($bookId);
            if (! $book->isPublished() || ! $vendor->isActive()) {
                return response()->json(['message' => "Cuốn sách ID {$bookId} hoặc gian hàng chưa đủ điều kiện."], 422);
            }
            if (! $book->isEbook() && ($validated['max_quantity'] ?? 0) > $book->stock) {
                return response()->json(['message' => "Giới hạn Flash Sale vượt tồn kho của sách ID {$bookId}."], 422);
            }

            $item = $existingItems->get($bookId);
            if ($item && $item->status !== 'rejected') {
                return response()->json(['message' => "Cuốn sách ID {$bookId} đã được đăng ký."], 422);
            }
        }

        $registered = DB::transaction(function () use ($bookIds, $books, $existingItems, $validated, $vendor, $flash_sale) {
            $created = [];
            foreach ($bookIds as $bookId) {
                $book = $books->get($bookId);
                $item = $existingItems->get($bookId);
                // Chỉ ghi sau khi toàn bộ đề xuất đã vượt qua validation phía trên.
                $values = [
                    'vendor_id' => $vendor->id,
                    'discount_percent' => $validated['discount_percent'],
                    'max_quantity' => $validated['max_quantity'] ?? 0,
                    'sold_quantity' => 0,
                    'status' => 'pending',
                    'sale_price' => null,
                    'decided_by' => null,
                    'decision_reason' => null,
                ];
                if ($item) {
                    $item->update($values);
                } else {
                    $item = FlashSaleBook::create([...$values, 'flash_sale_id' => $flash_sale->id, 'book_id' => $bookId]);
                }

                $created[] = $item->load('book');
            }

            return $created;
        });

        return response()->json([
            'message' => 'Đăng ký đề xuất tham gia Flash Sale thành công. Vui lòng chờ Admin kiểm duyệt.',
            'data' => $registered,
        ], 201);
    }
}

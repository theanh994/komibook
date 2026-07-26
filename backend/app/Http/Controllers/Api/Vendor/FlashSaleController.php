<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\FlashSale;
use App\Models\FlashSaleBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            ->get();

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
            'book_ids' => 'required|array',
            'book_ids.*' => 'exists:books,id',
            'discount_percent' => 'required|numeric|min:1|max:99',
            'max_quantity' => 'nullable|integer|min:1',
        ]);

        $registered = [];

        foreach ($validated['book_ids'] as $bookId) {
            // Xác thực sách thuộc sở hữu của Vendor này
            $book = Book::withoutGlobalScopes()->where('id', $bookId)->where('vendor_id', $vendor->id)->first();
            if (! $book) {
                return response()->json(['message' => "Cuốn sách ID {$bookId} không thuộc gian hàng của bạn."], 403);
            }
            if (! $book->isPublished() || ! $vendor->isActive()) {
                return response()->json(['message' => "Cuốn sách ID {$bookId} hoặc gian hàng chưa đủ điều kiện."], 422);
            }
            if (! $book->isEbook() && ($validated['max_quantity'] ?? 0) > $book->stock) {
                return response()->json(['message' => "Giới hạn Flash Sale vượt tồn kho của sách ID {$bookId}."], 422);
            }

            // Tạo mới hoặc cập nhật nếu đã đăng ký rồi
            $item = FlashSaleBook::where('flash_sale_id', $flash_sale->id)->where('book_id', $bookId)->first();
            if ($item && $item->status !== 'rejected') {
                return response()->json(['message' => "Cuốn sách ID {$bookId} đã được đăng ký."], 422);
            }
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

            $registered[] = $item->load('book');
        }

        return response()->json([
            'message' => 'Đăng ký đề xuất tham gia Flash Sale thành công. Vui lòng chờ Admin kiểm duyệt.',
            'data' => $registered,
        ], 201);
    }
}

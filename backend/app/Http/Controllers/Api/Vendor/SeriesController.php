<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\Series;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SeriesController extends Controller
{
    /**
     * Lấy danh sách Bộ Sách thuộc về Gian hàng (Vendor).
     */
    public function index(Request $request): JsonResponse
    {
        $vendorId = $request->user()?->vendor?->id;

        $series = Series::whereHas('books', function ($q) use ($vendorId) {
            if ($vendorId) {
                $q->withoutGlobalScopes()->where('vendor_id', $vendorId);
            }
        })
        ->with(['books' => function ($q) use ($vendorId) {
            if ($vendorId) {
                $q->withoutGlobalScopes()->where('vendor_id', $vendorId);
            }
        }])
        ->withCount(['books' => function ($q) use ($vendorId) {
            if ($vendorId) {
                $q->withoutGlobalScopes()->where('vendor_id', $vendorId);
            }
        }])
        ->orderBy('title', 'asc')
        ->get();

        $data = $series->map(function ($s) {
            return [
                'id'          => $s->id,
                'title'       => $s->title,
                'description' => $s->description,
                'books_count' => $s->books_count,
                'books'       => BookResource::collection($s->books),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * Đổi tên Bộ Sách.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $series = Series::findOrFail($id);
        $series->update([
            'title' => trim($request->input('title')),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã cập nhật tên bộ sách thành công.',
            'data'    => $series,
        ]);
    }

    /**
     * Áp dụng giảm giá đồng loạt cho toàn bộ sách thuộc 1 Bộ Sách (Khống chế tối đa 15%).
     */
    public function applyDiscount(Request $request, $id): JsonResponse
    {
        $request->validate([
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:15'],
        ]);

        $vendorId = $request->user()?->vendor?->id;
        $discountPct = (float) $request->input('discount_percent');

        $books = Book::withoutGlobalScopes()
            ->where('series_id', $id)
            ->when($vendorId, function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            })
            ->get();

        if ($books->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không tìm thấy sách nào thuộc bộ sách này.',
            ], 404);
        }

        foreach ($books as $book) {
            if ($discountPct > 0) {
                $salePrice = (int) round($book->price * (1 - $discountPct / 100));
                // Đảm bảo sale_price không nhỏ hơn 0 và nhỏ hơn price
                if ($salePrice >= $book->price) {
                    $salePrice = null;
                }
                $book->update(['sale_price' => $salePrice]);
            } else {
                $book->update(['sale_price' => null]);
            }

            try {
                \Illuminate\Support\Facades\Redis::del("book_stock:{$book->id}");
            } catch (\Exception $e) {
                Log::warning("Failed Redis clear stock cache: " . $e->getMessage());
            }
        }

        $msg = $discountPct > 0
            ? "Đã áp dụng giảm giá {$discountPct}% cho toàn bộ " . $books->count() . " tập sách trong bộ."
            : "Đã gỡ bỏ giảm giá cho các tập sách trong bộ.";

        return response()->json([
            'status'  => 'success',
            'message' => $msg,
        ]);
    }

    /**
     * Xóa Bộ Sách (Gỡ series_id khỏi tất cả các sách thuộc bộ sách này).
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $vendorId = $request->user()?->vendor?->id;

        Book::withoutGlobalScopes()
            ->where('series_id', $id)
            ->when($vendorId, function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            })
            ->update(['series_id' => null]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã gỡ bộ sách thành công.',
        ]);
    }
}

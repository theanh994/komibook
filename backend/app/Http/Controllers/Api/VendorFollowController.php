<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\Vendor;
use App\Models\VendorFollow;
use App\Support\PublicMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VendorFollowController extends Controller
{
    public function storefront(Request $request, string $slug): JsonResponse
    {
        $vendor = Vendor::withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();
        abort_unless($vendor->isActive(), 404);

        $books = Book::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->sellable()
            ->with(['vendor', 'category', 'categories', 'latestEbookVersion'])
            ->orderByDesc('views')
            ->orderByDesc('id')
            ->paginate(12);

        return response()->json([
            'status' => 'success',
            'data' => [
                'vendor' => [
                    'id' => $vendor->id,
                    'shop_name' => $vendor->shop_name,
                    'slug' => $vendor->slug,
                    'description' => $vendor->description,
                    'logo' => PublicMediaUrl::storage($vendor->logo),
                    'views_count' => Schema::hasColumn('vendors', 'views_count') ? (int) $vendor->views_count : 0,
                    'followers_count' => Schema::hasTable('vendor_follows')
                        ? VendorFollow::where('vendor_id', $vendor->id)->count()
                        : 0,
                ],
                'books' => BookResource::collection($books->getCollection()),
                'pagination' => [
                    'current_page' => $books->currentPage(),
                    'last_page' => $books->lastPage(),
                    'per_page' => $books->perPage(),
                    'total' => $books->total(),
                ],
            ],
        ]);
    }

    public function status(Request $request, Vendor $vendor): JsonResponse
    {
        if (! Schema::hasTable('vendor_follows')) {
            return response()->json(['following' => false, 'available' => false, 'followers_count' => 0]);
        }

        return response()->json([
            'following' => VendorFollow::where('user_id', $request->user()->id)
                ->where('vendor_id', $vendor->id)
                ->exists(),
            'available' => true,
            'followers_count' => VendorFollow::where('vendor_id', $vendor->id)->count(),
        ]);
    }

    public function toggle(Request $request, Vendor $vendor): JsonResponse
    {
        abort_unless(Schema::hasTable('vendor_follows'), 503, 'Tính năng theo dõi sẽ khả dụng sau khi nâng cấp dữ liệu.');
        abort_unless($vendor->isActive(), 422, 'Gian hàng chưa hoạt động.');
        abort_if($vendor->user_id === $request->user()->id, 422, 'Bạn không thể theo dõi gian hàng của chính mình.');
        $follow = VendorFollow::where('user_id', $request->user()->id)
            ->where('vendor_id', $vendor->id)
            ->first();
        if ($follow) {
            $follow->delete();

            return response()->json([
                'following' => false,
                'followers_count' => VendorFollow::where('vendor_id', $vendor->id)->count(),
                'message' => 'Đã bỏ theo dõi gian hàng.',
            ]);
        }

        VendorFollow::create(['user_id' => $request->user()->id, 'vendor_id' => $vendor->id]);

        return response()->json([
            'following' => true,
            'followers_count' => VendorFollow::where('vendor_id', $vendor->id)->count(),
            'message' => 'Bạn sẽ nhận thông báo Flash Sale và chiến dịch mới của gian hàng.',
        ]);
    }

    public function recordVisit(Vendor $vendor): JsonResponse
    {
        abort_unless($vendor->isActive(), 404);
        if (Schema::hasColumn('vendors', 'views_count')) {
            $vendor->increment('views_count');
        }

        return response()->json(['status' => 'recorded'], 202);
    }
}

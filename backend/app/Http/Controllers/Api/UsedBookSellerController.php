<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsedBookListingResource;
use App\Models\Book;
use App\Models\SellerFulfillmentAddress;
use App\Models\UsedBookListing;
use App\Services\ProductTaxonomyService;
use App\Services\UsedBookSellerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsedBookSellerController extends Controller
{
    public function index(Request $request, UsedBookSellerService $sellerService)
    {
        $sellerService->profileFor($request->user());
        $listings = UsedBookListing::query()
            ->where('seller_user_id', $request->user()->id)
            ->with(['book.category'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => UsedBookListingResource::collection($listings),
            'meta' => ['ownership' => 'used_book_seller', 'address_visibility' => 'private'],
        ]);
    }

    public function store(Request $request, UsedBookSellerService $sellerService, ProductTaxonomyService $taxonomy)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'required|integer|exists:categories,id',
            'price' => 'required|integer|min:1000',
            'condition' => 'required|in:like_new,good,fair',
            'defects' => 'nullable|string|max:3000',
            'quantity' => 'required|integer|min:1|max:100',
            'actual_photos' => 'required|array|min:1|max:8',
            'actual_photos.*' => 'required|image|max:10240',
            'authenticity_attested' => 'accepted',
        ]);
        $profile = $sellerService->profileFor($request->user());
        $address = SellerFulfillmentAddress::where('user_id', $request->user()->id)
            ->where('status', 'verified')
            ->latest('verified_at')
            ->first();
        abort_unless($address, 422, 'Cần đăng ký địa chỉ gửi hàng trước khi thêm sách cũ.');
        $paths = collect($request->file('actual_photos'))
            ->map(fn ($file) => $file->store('used-books/photos', 'public'))
            ->all();

        [$book, $listing] = DB::transaction(function () use ($validated, $paths, $profile, $address, $request, $taxonomy) {
            $book = Book::withoutGlobalScopes()->create($taxonomy->normalize([
                'vendor_id' => $profile->catalog_vendor_id,
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'slug' => Str::slug($validated['title']).'-used-'.Str::lower(Str::random(5)),
                'author' => $validated['author_name'],
                'description' => $validated['description'] ?? null,
                'cover_image' => $paths[0],
                'price' => $validated['price'],
                'stock' => $validated['quantity'],
                'type' => 'physical',
                'format' => 'physical',
                'provenance' => 'used_resale',
                'condition' => $validated['condition'],
                'fulfillment_mode' => 'seller_verified_address',
                'status' => 'draft',
                'publishing_status' => 'draft',
            ]));
            $listing = UsedBookListing::create([
                'book_id' => $book->id,
                'seller_user_id' => $request->user()->id,
                'seller_fulfillment_address_id' => $address->id,
                'condition' => $validated['condition'],
                'actual_photos' => $paths,
                'defects' => $validated['defects'] ?? null,
                'quantity_available' => $validated['quantity'],
                'authenticity_attested_at' => now(),
                'status' => 'draft',
            ]);

            return [$book, $listing];
        });

        $listing->setRelation('book', $book->loadMissing('category'));

        return response()->json(['status' => 'success', 'data' => new UsedBookListingResource($listing)], 201);
    }

    public function updateInventory(Request $request, UsedBookListing $listing)
    {
        $validated = $request->validate(['quantity_available' => 'required|integer|min:0|max:100']);
        abort_unless($listing->seller_user_id === $request->user()->id, 403);
        $listing->update($validated);
        Book::withoutGlobalScopes()->whereKey($listing->book_id)
            ->update(['stock' => $validated['quantity_available']]);

        return response()->json([
            'status' => 'success',
            'data' => new UsedBookListingResource($listing->fresh()->load('book.category')),
        ]);
    }

    public function showAddress(Request $request)
    {
        $address = SellerFulfillmentAddress::where('user_id', $request->user()->id)
            ->whereNull('retired_at')
            ->latest()
            ->first();

        return response()->json(['status' => 'success', 'data' => $address ? [
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'address_line' => $address->address_line,
            'ward' => $address->ward,
            'district' => $address->district,
            'province' => $address->province,
            'postal_code' => $address->postal_code,
            'status' => $address->status,
        ] : null]);
    }

    public function upsertAddress(Request $request, UsedBookSellerService $sellerService)
    {
        $sellerService->profileFor($request->user());
        $validated = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^0[0-9]{9}$/'],
            'address_line' => 'required|string|max:500',
            'ward' => 'nullable|string|max:120',
            'district' => 'nullable|string|max:120',
            'province' => 'required|string|max:120',
            'postal_code' => 'nullable|string|max:20',
        ]);

        $address = SellerFulfillmentAddress::updateOrCreate(
            ['user_id' => $request->user()->id, 'retired_at' => null],
            [...$validated, 'status' => 'verified', 'verified_at' => now(), 'verified_by' => $request->user()->id],
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đã lưu địa chỉ gửi hàng riêng tư.',
            'data' => ['status' => $address->status],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreBookRequest;
use App\Http\Requests\Vendor\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\EbookVersion;
use App\Models\Series;
use App\Models\Warehouse;
use App\Models\WarehouseDocument;
use App\Services\BookInventoryOnboardingService;
use App\Services\BookSupplyChainRequirementResolver;
use App\Services\CommercialPartyService;
use App\Services\ProductTaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookController extends Controller
{
    public function createScope(Request $request, BookSupplyChainRequirementResolver $resolver): JsonResponse
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        $activeWarehouses = Warehouse::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereIn('status', ['active', 'Hoạt động'])
            ->orderBy('id')
            ->get(['id', 'vendor_id', 'name', 'address', 'province', 'district', 'status']);
        $primaryWarehouse = $activeWarehouses->firstWhere('id', $vendor->primary_warehouse_id)
            ?? ($activeWarehouses->count() === 1 ? $activeWarehouses->first() : null);
        $supplyChain = $resolver->scope($vendor);
        $blockingReasons = [];
        if (! $primaryWarehouse) {
            $blockingReasons[] = $activeWarehouses->isEmpty()
                ? 'Gian hàng chưa có kho đang hoạt động.'
                : 'Gian hàng có nhiều kho nhưng chưa chọn kho tổng.';
        }
        if (! $supplyChain['supply_chain_ready']) {
            $blockingReasons[] = 'Hồ sơ xuất bản và cung ứng chưa đủ điều kiện.';
        }

        return response()->json(['status' => 'success', 'data' => [
            'vendor' => ['id' => $vendor->id, 'shop_name' => $vendor->shop_name],
            'primary_warehouse' => $primaryWarehouse,
            'warehouses' => $activeWarehouses,
            'business_model' => $supplyChain['business_model'],
            'supply_chain_mode' => $supplyChain['mode'],
            'required_commercial_roles' => $supplyChain['required_commercial_roles'],
            'inferred_relationship_id' => $supplyChain['inferred_relationship_id'],
            'relationships' => $supplyChain['relationships']->map(fn ($relationship) => [
                'id' => $relationship->id,
                'role' => $relationship->role,
                'status' => $relationship->status,
                'is_demo' => (bool) $relationship->is_demo,
                'organization' => $relationship->organization ? [
                    'id' => $relationship->organization->id,
                    'display_name' => $relationship->organization->display_name,
                    'legal_name' => $relationship->organization->legal_name,
                    'slug' => $relationship->organization->slug,
                    'organization_types' => $relationship->organization->organization_types,
                    'data_mode' => $relationship->organization->data_mode,
                    'status' => $relationship->organization->status,
                ] : null,
            ])->values(),
            'can_create_physical_book' => $blockingReasons === [],
            'blocking_reasons' => $blockingReasons,
        ]]);
    }

    /**
     * Lấy danh sách sách của Vendor đang đăng nhập.
     *
     * MultiVendorScoped đã tự động filter theo vendor_id,
     * nên chỉ cần gọi Book::paginate() bình thường.
     */
    public function index(Request $request)
    {
        $query = Book::with(['category', 'categories']);

        // Tìm kiếm theo tên sách hoặc tác giả
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('author', 'LIKE', "%{$search}%");
            });
        }

        // Lọc theo danh mục (nhiều danh mục category_ids hoặc đơn category_id)
        if ($request->filled('category_ids')) {
            $categoryIds = is_array($request->category_ids)
                ? $request->category_ids
                : explode(',', $request->category_ids);

            $query->where(function ($q) use ($categoryIds) {
                $q->whereIn('category_id', $categoryIds)
                    ->orWhereHas('categories', function ($catQuery) use ($categoryIds) {
                        $catQuery->whereIn('categories.id', $categoryIds);
                    });
            });
        } elseif ($request->filled('category_id') && $request->category_id !== 'all') {
            $catId = $request->category_id;
            $query->where(function ($q) use ($catId) {
                $q->where('category_id', $catId)
                    ->orWhereHas('categories', function ($catQuery) use ($catId) {
                        $catQuery->where('categories.id', $catId);
                    });
            });
        }

        // Lọc theo trạng thái
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Lọc theo loại sách
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Sắp xếp danh sách sách
        if ($request->filled('sort')) {
            switch ($request->sort) {
                case 'created_at_asc':
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'title_asc':
                case 'name_asc':
                    $query->orderBy('title', 'asc');
                    break;
                case 'title_desc':
                case 'name_desc':
                    $query->orderBy('title', 'desc');
                    break;
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'created_at_desc':
                case 'latest':
                default:
                    $query->orderBy('created_at', 'desc');
                    break;
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $books = $query->paginate($request->get('per_page', 15));

        return BookResource::collection($books)->additional([
            'status' => 'success',
            'message' => 'Lấy danh sách sách thành công.',
        ]);
    }

    /**
     * Thêm sách mới cho Vendor.
     *
     * vendor_id sẽ được tự động gán bởi MultiVendorScoped trait,
     * KHÔNG CẦN set thủ công.
     */
    public function store(
        StoreBookRequest $request,
        ProductTaxonomyService $taxonomy,
        CommercialPartyService $commercialParties,
        BookSupplyChainRequirementResolver $supplyChainResolver,
        BookInventoryOnboardingService $inventoryOnboarding,
    ): JsonResponse {
        $data = $request->validated();
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        $clientOperationKey = (string) ($data['operation_key'] ?? Str::uuid());
        $receiptOperationKey = "book-create:{$vendor->id}:".substr($clientOperationKey, 0, 88);
        $existingReceipt = WarehouseDocument::where('vendor_id', $vendor->id)
            ->where('operation_key', $receiptOperationKey)
            ->with('lines.book')
            ->first();
        if ($existingReceipt?->lines->first()?->book) {
            return response()->json([
                'status' => 'success',
                'message' => 'Yêu cầu này đã được xử lý trước đó.',
                'data' => new BookResource($existingReceipt->lines->first()->book),
                'receipt_document' => $existingReceipt,
            ]);
        }
        $uploadedPublicPaths = [];
        $uploadedPrivatePaths = [];

        // Tạo slug tự động từ title
        $data['slug'] = Str::slug($data['title']).'-'.Str::random(5);

        // Upload ảnh bìa nếu có
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')
                ->store('books/covers', 'public');
            $uploadedPublicPaths[] = $data['cover_image'];
        } else {
            $data['cover_image'] = 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=600&auto=format&fit=crop';
        }

        // Upload album ảnh phụ (gallery_images)
        $gallery = [];
        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $file) {
                $gallery[] = $file->store('books/gallery', 'public');
                $uploadedPublicPaths[] = $gallery[array_key_last($gallery)];
            }
        }
        $data['gallery_images'] = $gallery;

        // Upload file E-book nếu có
        if ($request->hasFile('ebook_file')) {
            $data['file_path'] = $request->file('ebook_file')
                ->store('ebooks', 'local'); // Lưu ở disk private
            $uploadedPrivatePaths[] = $data['file_path'];
        }

        // Xử lý danh mục (category_ids)
        $categoryIds = [];
        if ($request->has('category_ids')) {
            $rawCat = $request->input('category_ids');
            $categoryIds = is_array($rawCat) ? $rawCat : json_decode($rawCat, true);
        } elseif (! empty($data['category_id'])) {
            $categoryIds = [(int) $data['category_id']];
        }
        if (! empty($categoryIds)) {
            $data['category_id'] = $categoryIds[0];
        }

        $seriesNameProvided = $request->has('series_name');
        $seriesName = $seriesNameProvided ? trim((string) $request->input('series_name')) : null;
        $seriesIdProvided = $request->has('series_id') ? ($request->input('series_id') ?: null) : null;

        $initialQuantity = max(0, (int) ($data['stock'] ?? 0));
        $data['status'] = 'published';
        $data['publishing_status'] = 'published';
        $data['published_at'] = now();
        if (($data['type'] ?? 'physical') === 'physical') {
            $data['stock'] = 0;
        }

        $warehouseId = $data['warehouse_id'] ?? null;
        $submittedRelationships = $data;
        $externalCounterpartyName = $data['external_counterparty_name'] ?? null;
        unset(
            $data['ebook_file'],
            $data['category_ids'],
            $data['series_name'],
            $data['warehouse_id'],
            $data['publisher_relationship_id'],
            $data['supplier_relationship_id'],
            $data['responsible_organization_relationship_id'],
            $data['external_counterparty_name'],
            $data['operation_key'],
        );

        try {
            [$book, $receipt] = DB::transaction(function () use ($request, $vendor, $taxonomy, $commercialParties, $supplyChainResolver, $inventoryOnboarding, $data, $categoryIds, $warehouseId, $submittedRelationships, $seriesNameProvided, $seriesName, $seriesIdProvided, $initialQuantity, $externalCounterpartyName, $receiptOperationKey) {
                if ($seriesNameProvided) {
                    if ($seriesName !== '') {
                        $series = Series::whereRaw('LOWER(title) = ?', [mb_strtolower($seriesName)])->firstOrCreate([
                            'title' => $seriesName,
                        ]);
                        $data['series_id'] = $series->id;
                    } else {
                        $data['series_id'] = null;
                    }
                } elseif ($seriesIdProvided !== null) {
                    $data['series_id'] = $seriesIdProvided;
                }
                $book = Book::create($taxonomy->normalize($data));

                if (! empty($categoryIds)) {
                    $book->categories()->sync($categoryIds);
                }

                if ($book->type === 'physical') {
                    $activeWarehouses = Warehouse::withoutGlobalScopes()
                        ->where('vendor_id', $vendor->id)
                        ->whereIn('status', ['active', 'Hoạt động'])
                        ->orderBy('id')
                        ->get();
                    $warehouse = $activeWarehouses->firstWhere('id', $vendor->primary_warehouse_id)
                        ?? ($activeWarehouses->count() === 1 ? $activeWarehouses->first() : null);
                    if (! $warehouse) {
                        throw ValidationException::withMessages([
                            'warehouse_id' => 'Hãy chọn một kho tổng đang hoạt động trước khi thêm sách vật lý.',
                        ]);
                    }
                    if ($warehouseId && (int) $warehouseId !== (int) $warehouse->id) {
                        throw ValidationException::withMessages([
                            'warehouse_id' => 'Sách mới chỉ được nhập vào kho tổng của gian hàng.',
                        ]);
                    }
                    if (! $vendor->primary_warehouse_id) {
                        $vendor->update(['primary_warehouse_id' => $warehouse->id]);
                    }
                }

                if ($book->provenance !== 'used_resale') {
                    $relationshipIds = $supplyChainResolver->resolve($vendor, $submittedRelationships);
                    $book = $commercialParties->assign($book, $relationshipIds, $request->user());
                }

                $receipt = $book->type === 'physical'
                    ? $inventoryOnboarding->createReceiptDraft(
                        $book,
                        $vendor,
                        $warehouse,
                        $request->user(),
                        $initialQuantity,
                        $externalCounterpartyName,
                        $receiptOperationKey,
                    )
                    : null;

                return [$book, $receipt];
            });
        } catch (\Throwable $exception) {
            foreach ($uploadedPublicPaths as $path) {
                Storage::disk('public')->delete($path);
            }
            foreach ($uploadedPrivatePaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return response()->json([
            'status' => 'success',
            'message' => $receipt
                ? 'Đã tạo sách công khai và phiếu nhập nháp. Tồn kho chỉ tăng sau khi ghi sổ phiếu.'
                : 'Thêm sách thành công!',
            'data' => new BookResource($book->load([
                'category',
                'categories',
                'activeCommercialParties.organization',
                'warehouseStocks.warehouse',
            ])),
            'receipt_document' => $receipt,
        ], 201);
    }

    /**
     * Xem chi tiết một cuốn sách của Vendor.
     *
     * Global Scope đã đảm bảo Vendor chỉ thấy sách của chính mình.
     */
    public function show(Book $book): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Lấy chi tiết sách thành công.',
            'data' => new BookResource($book->load(['category', 'categories'])),
        ]);
    }

    /**
     * Cập nhật thông tin sách.
     */
    public function update(UpdateBookRequest $request, Book $book, ProductTaxonomyService $taxonomy): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            $data['publishing_status'] = $data['status'];
            $data['published_at'] = $data['status'] === 'published'
                ? ($book->published_at ?? now())
                : null;
        }

        // Cập nhật slug nếu title thay đổi
        if (isset($data['title']) && $data['title'] !== $book->title) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5);
        }

        // Upload ảnh bìa mới nếu có
        if ($request->hasFile('cover_image')) {
            // Xóa ảnh cũ
            if ($book->cover_image && ! filter_var($book->cover_image, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($book->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')
                ->store('books/covers', 'public');
        } elseif (! $book->cover_image) {
            $data['cover_image'] = 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=600&auto=format&fit=crop';
        }

        // Xử lý album ảnh minh họa (gallery_images)
        $existingGallery = [];
        if ($request->has('existing_gallery_images') || $request->exists('existing_gallery_images')) {
            $rawExisting = $request->input('existing_gallery_images');
            if (is_string($rawExisting)) {
                $existingGallery = json_decode($rawExisting, true) ?? [];
            } elseif (is_array($rawExisting)) {
                $existingGallery = $rawExisting;
            } else {
                $existingGallery = [];
            }

            // Chuẩn hóa đường dẫn: loại bỏ tiền tố '/storage/' để đồng bộ với Database
            $existingGallery = array_map(function ($img) {
                if (is_string($img) && str_starts_with($img, '/storage/')) {
                    return substr($img, strlen('/storage/'));
                }

                return $img;
            }, $existingGallery);

            // Xóa tệp khỏi đĩa chỉ khi thực sự bị người dùng bấm xóa khỏi album
            if (is_array($book->gallery_images)) {
                foreach ($book->gallery_images as $oldImg) {
                    if (! in_array($oldImg, $existingGallery) && ! filter_var($oldImg, FILTER_VALIDATE_URL)) {
                        Storage::disk('public')->delete($oldImg);
                    }
                }
            }
        } else {
            // Nếu request không gửi existing_gallery_images, giữ nguyên album ảnh hiện tại của sách
            $existingGallery = is_array($book->gallery_images) ? $book->gallery_images : [];
        }

        // Thêm các ảnh mới được tải lên
        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $file) {
                $existingGallery[] = $file->store('books/gallery', 'public');
            }
        }
        $data['gallery_images'] = array_values($existingGallery);

        // Upload file E-book mới nếu có
        if ($request->hasFile('ebook_file')) {
            // Xóa file cũ
            if ($book->file_path && ! EbookVersion::where('file_path', $book->file_path)->exists()) {
                Storage::disk('local')->delete($book->file_path);
            }
            $data['file_path'] = $request->file('ebook_file')
                ->store('ebooks', 'local');
        }

        // Xử lý cập nhật danh mục
        if ($request->has('category_ids')) {
            $rawCat = $request->input('category_ids');
            $categoryIds = is_array($rawCat) ? $rawCat : json_decode($rawCat, true);
            if (! empty($categoryIds)) {
                $data['category_id'] = $categoryIds[0];
                $book->categories()->sync($categoryIds);
            }
        } elseif (! empty($data['category_id'])) {
            $book->categories()->sync([(int) $data['category_id']]);
        }

        // Xử lý bộ sách (Series)
        if ($request->has('series_name')) {
            $sName = trim((string) $request->input('series_name'));
            if ($sName !== '') {
                $series = Series::whereRaw('LOWER(title) = ?', [mb_strtolower($sName)])->first();
                if (! $series) {
                    $series = Series::create(['title' => $sName]);
                }
                $data['series_id'] = $series->id;
            } else {
                $data['series_id'] = null;
            }
        } elseif ($request->has('series_id')) {
            $data['series_id'] = $request->input('series_id') ?: null;
        }

        unset($data['ebook_file'], $data['category_ids'], $data['existing_gallery_images'], $data['series_name']);

        $book->update($taxonomy->normalize($data, $book));

        // Xoá key cache tồn kho trên Redis để cập nhật thông tin mới nhất
        try {
            Redis::del("book_stock:{$book->id}");
        } catch (\Exception $ex) {
            \Illuminate\Support\Facades\Log::warning('Failed to clear Redis stock cache: '.$ex->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật sách thành công!',
            'data' => new BookResource($book->fresh()->load(['category', 'categories'])),
        ]);
    }

    /**
     * Xóa một cuốn sách.
     */
    public function destroy(Book $book): JsonResponse
    {
        // Xóa file ảnh bìa
        if ($book->cover_image) {
            Storage::disk('public')->delete($book->cover_image);
        }

        // Xóa file E-book
        if ($book->file_path) {
            Storage::disk('local')->delete($book->file_path);
        }

        $book->delete();

        // Xoá key cache tồn kho trên Redis
        try {
            Redis::del("book_stock:{$book->id}");
        } catch (\Exception $ex) {
            // ignore
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa sách thành công!',
        ]);
    }

    /**
     * Thao tác hàng loạt gán hoặc xóa Bộ sách (Series) cho nhiều sách.
     */
    public function bulkSeries(Request $request): JsonResponse
    {
        $request->validate([
            'book_ids' => ['required', 'array', 'min:1'],
            'book_ids.*' => ['integer', 'exists:books,id'],
            'action' => ['required', 'string', 'in:assign,remove'],
            'series_name' => ['nullable', 'string', 'max:255'],
        ]);

        $bookIds = $request->input('book_ids');
        $action = $request->input('action');
        $seriesId = null;

        if ($action === 'assign') {
            $sName = trim((string) $request->input('series_name'));
            if ($sName !== '') {
                $series = Series::whereRaw('LOWER(title) = ?', [mb_strtolower($sName)])->first();
                if (! $series) {
                    $series = Series::create(['title' => $sName]);
                }
                $seriesId = $series->id;
            }
        }

        // Cập nhật series_id cho các cuốn sách (MultiVendorScope tự động giới hạn chỉ sách của vendor này)
        Book::whereIn('id', $bookIds)->update(['series_id' => $seriesId]);

        $msg = $action === 'remove'
            ? 'Đã xóa bộ sách cho các cuốn sách được chọn.'
            : 'Đã gán thành công bộ sách cho các cuốn sách được chọn.';

        return response()->json([
            'status' => 'success',
            'message' => $msg,
        ]);
    }

    /**
     * Thao tác hàng loạt giảm giá cho nhiều cuốn sách được chọn (Giới hạn tối đa 15%).
     */
    public function bulkDiscount(Request $request): JsonResponse
    {
        $request->validate([
            'book_ids' => ['required', 'array', 'min:1'],
            'book_ids.*' => ['integer', 'exists:books,id'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:15'],
        ]);

        $bookIds = $request->input('book_ids');
        $discountPct = (float) $request->input('discount_percent');

        $books = Book::whereIn('id', $bookIds)->get();

        foreach ($books as $book) {
            if ($discountPct > 0) {
                $salePrice = (int) round($book->price * (1 - $discountPct / 100));
                if ($salePrice >= $book->price) {
                    $salePrice = null;
                }
                $book->update(['sale_price' => $salePrice]);
            } else {
                $book->update(['sale_price' => null]);
            }

            try {
                Redis::del("book_stock:{$book->id}");
            } catch (\Exception $e) {
                Log::warning('Failed Redis clear stock cache: '.$e->getMessage());
            }
        }

        $msg = $discountPct > 0
            ? "Đã áp dụng giảm giá {$discountPct}% cho ".$books->count().' cuốn sách được chọn.'
            : 'Đã gỡ bỏ giảm giá cho các cuốn sách được chọn.';

        return response()->json([
            'status' => 'success',
            'message' => $msg,
        ]);
    }
}

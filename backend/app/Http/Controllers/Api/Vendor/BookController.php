<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreBookRequest;
use App\Http\Requests\Vendor\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookController extends Controller
{
    /**
     * Lấy danh sách sách của Vendor đang đăng nhập.
     *
     * MultiVendorScoped đã tự động filter theo vendor_id,
     * nên chỉ cần gọi Book::paginate() bình thường.
     */
    public function index(Request $request)
    {
        $query = Book::with(['category'])->orderBy('created_at', 'desc');

        // Tìm kiếm theo tên sách
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('author', 'LIKE', "%{$search}%");
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

        $books = $query->paginate($request->get('per_page', 15));

        return BookResource::collection($books)->additional([
            'status'  => 'success',
            'message' => 'Lấy danh sách sách thành công.',
        ]);
    }

    /**
     * Thêm sách mới cho Vendor.
     *
     * vendor_id sẽ được tự động gán bởi MultiVendorScoped trait,
     * KHÔNG CẦN set thủ công.
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Tạo slug tự động từ title
        $data['slug'] = Str::slug($data['title']) . '-' . Str::random(5);

        // Upload ảnh bìa nếu có
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')
                ->store('books/covers', 'public');
        }

        // Upload file E-book nếu có
        if ($request->hasFile('ebook_file')) {
            $data['file_path'] = $request->file('ebook_file')
                ->store('ebooks', 'local'); // Lưu ở disk private
        }

        // Nếu không chỉ định status, mặc định là draft
        $data['status'] = $data['status'] ?? 'draft';

        // Loại bỏ key ebook_file vì Book model không có cột này
        unset($data['ebook_file']);

        $book = Book::create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Thêm sách thành công!',
            'data'    => new BookResource($book->load('category')),
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
            'status'  => 'success',
            'message' => 'Lấy chi tiết sách thành công.',
            'data'    => new BookResource($book->load('category')),
        ]);
    }

    /**
     * Cập nhật thông tin sách.
     */
    public function update(UpdateBookRequest $request, Book $book): JsonResponse
    {
        $data = $request->validated();

        // Cập nhật slug nếu title thay đổi
        if (isset($data['title']) && $data['title'] !== $book->title) {
            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(5);
        }

        // Upload ảnh bìa mới nếu có
        if ($request->hasFile('cover_image')) {
            // Xóa ảnh cũ
            if ($book->cover_image) {
                Storage::disk('public')->delete($book->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')
                ->store('books/covers', 'public');
        }

        // Upload file E-book mới nếu có
        if ($request->hasFile('ebook_file')) {
            // Xóa file cũ
            if ($book->file_path) {
                Storage::disk('local')->delete($book->file_path);
            }
            $data['file_path'] = $request->file('ebook_file')
                ->store('ebooks', 'local');
        }

        // Loại bỏ key ebook_file vì Book model không có cột này
        unset($data['ebook_file']);

        $book->update($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cập nhật sách thành công!',
            'data'    => new BookResource($book->fresh()->load('category')),
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

        return response()->json([
            'status'  => 'success',
            'message' => 'Xóa sách thành công!',
        ]);
    }
}

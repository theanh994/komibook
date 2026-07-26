<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Lấy danh sách sách yêu thích của người dùng.
     */
    public function index(Request $request)
    {
        $books = $request->user()->wishlistBooks()->with(['category', 'vendor'])->latest('wishlists.created_at')->get();

        return BookResource::collection($books)->additional([
            'status' => 'success',
        ]);
    }

    /**
     * Thêm hoặc xóa sách khỏi danh sách yêu thích (Toggle).
     */
    public function toggle(Request $request, $bookId)
    {
        $user = $request->user();
        $book = Book::findOrFail($bookId);

        $exists = $user->wishlistBooks()->where('book_id', $bookId)->exists();

        if ($exists) {
            $user->wishlistBooks()->detach($bookId);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã xóa khỏi danh sách yêu thích',
                'data' => [
                    'state' => 'removed',
                    'is_favorite' => false,
                ],
            ]);
        } else {
            $user->wishlistBooks()->attach($bookId);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã thêm vào danh sách yêu thích',
                'data' => [
                    'state' => 'added',
                    'is_favorite' => true,
                ],
            ]);
        }
    }

    /**
     * Kiểm tra xem sách có trong wishlist không.
     */
    public function check(Request $request, $bookId)
    {
        $exists = $request->user()->wishlistBooks()->where('book_id', $bookId)->exists();

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_favorite' => $exists,
            ],
        ]);
    }
}

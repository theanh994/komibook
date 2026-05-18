<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use App\Http\Resources\BookResource;

class WishlistController extends Controller
{
    /**
     * Lấy danh sách sách yêu thích của người dùng.
     */
    public function index(Request $request)
    {
        $books = $request->user()->wishlistBooks()->with(['category', 'vendor'])->latest('wishlists.created_at')->get();
        return BookResource::collection($books);
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
                'message' => 'Đã xóa khỏi danh sách yêu thích',
                'status' => 'removed'
            ]);
        } else {
            $user->wishlistBooks()->attach($bookId);
            return response()->json([
                'message' => 'Đã thêm vào danh sách yêu thích',
                'status' => 'added'
            ]);
        }
    }

    /**
     * Kiểm tra xem sách có trong wishlist không.
     */
    public function check(Request $request, $bookId)
    {
        $exists = $request->user()->wishlistBooks()->where('book_id', $bookId)->exists();
        return response()->json(['is_favorite' => $exists]);
    }
}

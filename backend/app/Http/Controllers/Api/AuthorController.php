<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthorController extends Controller
{
    /**
     * Đăng ký trở thành tác giả.
     */
    public function register(Request $request)
    {
        $request->validate([
            'pen_name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'bank_account_number' => 'required|string|max:50',
            'bank_name' => 'required|string|max:100',
            'bank_holder_name' => 'required|string|max:255',
            'identity_document' => 'required|image|max:5120', // CCCD/Passport Max 5MB
        ]);

        $user = Auth::user();

        // Kiểm tra xem đã đăng ký chưa
        $existing = Author::where('user_id', $user->id)->first();
        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn đã gửi yêu cầu đăng ký tác giả trước đó rồi.'
            ], 422);
        }

        $filePath = null;
        if ($request->hasFile('identity_document')) {
            $filePath = $request->file('identity_document')->store('authors/cccd', 'public');
        }

        $author = Author::create([
            'user_id' => $user->id,
            'pen_name' => $request->pen_name,
            'bio' => $request->bio,
            'bank_account_number' => $request->bank_account_number,
            'bank_name' => $request->bank_name,
            'bank_holder_name' => $request->bank_holder_name,
            'identity_document' => $filePath,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Gửi yêu cầu đăng ký tác giả thành công! Chờ ban quản trị phê duyệt.',
            'data' => $author
        ], 201);
    }

    /**
     * Lấy trạng thái đăng ký tác giả của user hiện tại.
     */
    public function status()
    {
        $user = Auth::user();
        $author = Author::where('user_id', $user->id)->first();

        return response()->json([
            'status' => 'success',
            'data' => $author
        ]);
    }

    /**
     * Bảng thống kê của tác giả.
     */
    public function dashboardStats()
    {
        $user = Auth::user();
        $author = Author::where('user_id', $user->id)->first();

        if (!$author || $author->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn chưa phải là tác giả được kích hoạt.'
            ], 403);
        }

        // Tác giả hoạt động như Vendor, lấy thống kê của Vendor liên kết
        $vendor = $user->vendor;
        if (!$vendor) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_books' => 0,
                    'total_chapters' => 0,
                    'total_revenue' => 0,
                    'balance' => 0,
                ]
            ]);
        }

        $booksCount = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->count();
        $ebooksCount = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->where('type', 'ebook')->count();
        $physicalCount = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->where('type', 'physical')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'pen_name' => $author->pen_name,
                'status' => $author->status,
                'total_books' => $booksCount,
                'total_ebooks' => $ebooksCount,
                'total_physical' => $physicalCount,
                'balance' => $vendor->balance,
                'total_withdrawn' => $vendor->total_withdrawn,
            ]
        ]);
    }
}

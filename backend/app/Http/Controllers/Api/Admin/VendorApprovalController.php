<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Author;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorApprovalController extends Controller
{
    /**
     * Danh sách nhà bán và tác giả chờ duyệt.
     */
    public function index()
    {
        // Lấy danh sách các Vendor ở trạng thái inactive (chưa được duyệt)
        $vendors = Vendor::with('user')->where('status', 'inactive')->get();

        // Lấy danh sách tác giả chờ duyệt
        $authors = Author::with('user')->where('status', 'pending')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'vendors' => $vendors,
                'authors' => $authors
            ]
        ]);
    }

    /**
     * Phê duyệt Vendor.
     */
    public function approveVendor($id)
    {
        $vendor = Vendor::findOrFail($id);
        
        if ($vendor->status === 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Nhà bán này đã được phê duyệt trước đó.'
            ], 422);
        }

        $vendor->status = 'active';
        $vendor->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Phê duyệt tài khoản nhà bán đối tác thành công.'
        ]);
    }

    /**
     * Phê duyệt Tác giả (Author).
     */
    public function approveAuthor($id)
    {
        $author = Author::findOrFail($id);

        if ($author->status === 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tác giả này đã được phê duyệt trước đó.'
            ], 422);
        }

        return DB::transaction(function () use ($author) {
            $author->status = 'active';
            $author->phone_verified_at = now();
            $author->save();

            // Cập nhật vai trò người dùng thành 'vendor' để họ có quyền đăng bán sách cũ & ebook
            $user = $author->user;
            $user->role = 'vendor';
            $user->save();

            // Tự động tạo hồ sơ Vendor cho tác giả nếu chưa có
            $vendor = Vendor::where('user_id', $user->id)->first();
            if (!$vendor) {
                Vendor::create([
                    'user_id' => $user->id,
                    'shop_name' => $author->pen_name . ' (Tác giả)',
                    'slug' => str_replace(' ', '-', strtolower($author->pen_name)) . '-' . rand(100, 999),
                    'description' => $author->bio ?? 'Tác giả tự sáng tác trên nền tảng KomiBook.',
                    'status' => 'active',
                ]);
            } else {
                $vendor->status = 'active';
                $vendor->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Phê duyệt đối tác Tác giả thành công! Vai trò tài khoản đã chuyển thành đối tác và kích hoạt gian hàng.'
            ]);
        });
    }

    /**
     * Từ chối phê duyệt (cho cả Vendor hoặc Author).
     */
    public function reject(Request $request, $type, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($type === 'vendor') {
            $vendor = Vendor::withoutGlobalScopes()->findOrFail($id);
            $vendor->status = 'rejected';
            $vendor->rejection_reason = $request->reason;
            $vendor->save();
        } else if ($type === 'author') {
            $author = Author::findOrFail($id);
            $author->status = 'rejected';
            $author->rejection_reason = $request->reason;
            $author->save();
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Loại đối tác không hợp lệ.'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã từ chối đơn đăng ký đối tác thành công và lưu lý do.'
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookDrmSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DrmController extends Controller
{
    /**
     * Lấy cấu hình DRM của sách.
     */
    public function show($bookId)
    {
        $vendor = Auth::user()->vendor;
        $book = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->findOrFail($bookId);

        $settings = BookDrmSetting::firstOrCreate(
            ['book_id' => $book->id],
            [
                'social_drm' => true,
                'hard_drm' => false,
                'copy_limit_percent' => 10,
                'allow_printing' => false,
                'license_type' => 'all_rights_reserved'
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Cập nhật cấu hình DRM.
     */
    public function update(Request $request, $bookId)
    {
        $request->validate([
            'copyright_number' => 'nullable|string|max:100',
            'copyright_owner' => 'nullable|string|max:255',
            'social_drm' => 'required|boolean',
            'hard_drm' => 'required|boolean',
            'copy_limit_percent' => 'required|integer|min:0|max:100',
            'allow_printing' => 'required|boolean',
            'license_type' => 'required|string|max:100',
        ]);

        $vendor = Auth::user()->vendor;
        $book = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->findOrFail($bookId);

        $settings = BookDrmSetting::updateOrCreate(
            ['book_id' => $book->id],
            [
                'copyright_number' => $request->copyright_number,
                'copyright_owner' => $request->copyright_owner,
                'social_drm' => $request->social_drm,
                'hard_drm' => $request->hard_drm,
                'copy_limit_percent' => $request->copy_limit_percent,
                'allow_printing' => $request->allow_printing,
                'license_type' => $request->license_type,
            ]
        );

        // Giả lập: Nếu có bản quyền được đăng ký, cập nhật trạng thái sách (nếu là ebook)
        // Hệ thống sẽ cho đăng tải ebook để bán chỉ khi ebook đã được đăng ký bản quyền dưới tên tác giả
        // Ở đây ta mô phỏng: Nếu copyright_number có giá trị, cho phép cập nhật status thành 'published'
        if ($book->type === 'ebook') {
            if (empty($request->copyright_number)) {
                $book->status = 'draft';
                $book->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật cấu hình DRM thành công.',
            'data' => $settings
        ]);
    }
}

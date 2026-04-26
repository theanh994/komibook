<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Lấy danh sách toàn bộ danh mục.
     */
    public function index(): JsonResponse
    {
        // Trả về tất cả danh mục. (Có thể tuỳ chỉnh load hierarchy nếu cần)
        $categories = Category::all(['id', 'name', 'slug', 'parent_id']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Lấy danh mục thành công.',
            'data'    => $categories,
        ]);
    }
}

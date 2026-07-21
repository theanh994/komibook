<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Lấy danh sách thể loại sách (dành cho Admin)
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::with(['parent'])
            ->withCount(['books', 'children'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Lấy danh sách thể loại thành công.',
            'data'    => $categories,
        ]);
    }

    /**
     * Thêm mới thể loại sách
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => 'nullable|string|max:255|unique:categories,slug',
            'parent_id' => 'nullable|exists:categories,id',
        ], [
            'name.required'    => 'Vui lòng nhập tên thể loại.',
            'slug.unique'      => 'Slug (đường dẫn) này đã tồn tại.',
            'parent_id.exists' => 'Danh mục cha không hợp lệ.',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $originalSlug = $validated['slug'];
            $count = 1;
            while (Category::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = "{$originalSlug}-{$count}";
                $count++;
            }
        }

        $category = Category::create($validated);
        $category->load(['parent']);
        $category->loadCount(['books', 'children']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Thêm thể loại sách thành công.',
            'data'    => $category,
        ], 201);
    }

    /**
     * Chi tiết thể loại
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['parent', 'children']);
        $category->loadCount(['books']);

        return response()->json([
            'status' => 'success',
            'data'   => $category,
        ]);
    }

    /**
     * Cập nhật thể loại sách
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category->id),
            ],
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($category) {
                    if ($value == $category->id) {
                        $fail('Không thể chọn chính thể loại này làm danh mục cha.');
                    }
                },
            ],
        ], [
            'name.required' => 'Vui lòng nhập tên thể loại.',
            'slug.unique'   => 'Slug (đường dẫn) này đã tồn tại.',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $originalSlug = $validated['slug'];
            $count = 1;
            while (Category::where('slug', $validated['slug'])->where('id', '!=', $category->id)->exists()) {
                $validated['slug'] = "{$originalSlug}-{$count}";
                $count++;
            }
        }

        $category->update($validated);
        $category->load(['parent']);
        $category->loadCount(['books', 'children']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cập nhật thể loại sách thành công.',
            'data'    => $category,
        ]);
    }

    /**
     * Xóa thể loại sách
     */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->children()->count() > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không thể xóa thể loại đang chứa các danh mục con.',
            ], 422);
        }

        if ($category->books()->count() > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không thể xóa thể loại đang được gán cho sách.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã xóa thể loại sách thành công.',
        ]);
    }
}

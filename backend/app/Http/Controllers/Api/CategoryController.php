<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Lấy danh sách toàn bộ danh mục.
     */
    public function index(Request $request): JsonResponse
    {
        $publishedBookCount = Book::withoutGlobalScopes()
            ->selectRaw('COUNT(DISTINCT books.id)')
            ->sellable()
            ->browseVisible()
            ->where(function ($bookQuery) {
                $bookQuery
                    ->whereColumn('books.category_id', 'categories.id')
                    ->orWhereExists(function ($pivotQuery) {
                        $pivotQuery->selectRaw('1')
                            ->from('book_category')
                            ->whereColumn('book_category.book_id', 'books.id')
                            ->whereColumn('book_category.category_id', 'categories.id');
                    });
            });

        $categories = Category::query()
            ->select(['id', 'name', 'slug', 'parent_id'])
            ->selectSub($publishedBookCount, 'published_books_count');

        if ($request->boolean('popular')) {
            $categories
                ->orderByDesc('published_books_count')
                ->orderBy('name')
                ->limit(min(max($request->integer('limit', 10), 1), 10));
        } else {
            $categories->orderBy('name');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Lấy danh mục thành công.',
            'data' => $categories->get(),
        ]);
    }
}

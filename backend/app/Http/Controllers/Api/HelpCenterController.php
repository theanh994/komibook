<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\Request;

class HelpCenterController extends Controller
{
    /**
     * Lấy các bài viết trợ giúp công khai.
     */
    public function index(Request $request)
    {
        $query = HelpArticle::where('status', 'published');

        if ($request->has('category')) {
            $query->where('category_name', $request->category);
        }

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $articles = $query->orderBy('views_count', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $articles
        ]);
    }

    /**
     * Xem chi tiết bài viết công khai.
     */
    public function show($id)
    {
        $article = HelpArticle::where('status', 'published')->findOrFail($id);
        $article->increment('views_count');

        return response()->json([
            'status' => 'success',
            'data' => $article
        ]);
    }

    /**
     * Đánh giá bài viết là hữu ích.
     */
    public function helpful($id)
    {
        $article = HelpArticle::where('status', 'published')->findOrFail($id);
        $article->increment('helpful_count');

        return response()->json([
            'status' => 'success',
            'message' => 'Cảm ơn phản hồi của bạn!'
        ]);
    }

    /**
     * ADMIN: Xem toàn bộ các bài viết (nháp/công bố).
     */
    public function adminIndex()
    {
        $articles = HelpArticle::orderBy('category_name')->orderBy('title')->get();

        return response()->json([
            'status' => 'success',
            'data' => $articles
        ]);
    }

    /**
     * ADMIN: Thêm bài viết mới.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:draft,published',
        ]);

        $article = HelpArticle::create([
            'category_name' => $request->category_name,
            'title' => $request->title,
            'content' => $request->content,
            'status' => $request->status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã tạo bài viết trợ giúp mới.',
            'data' => $article
        ], 201);
    }

    /**
     * ADMIN: Cập nhật bài viết.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'category_name' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:draft,published',
        ]);

        $article = HelpArticle::findOrFail($id);
        $article->update([
            'category_name' => $request->category_name,
            'title' => $request->title,
            'content' => $request->content,
            'status' => $request->status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật bài viết thành công.',
            'data' => $article
        ]);
    }

    /**
     * ADMIN: Xóa bài viết.
     */
    public function destroy($id)
    {
        $article = HelpArticle::findOrFail($id);
        $article->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa bài viết trợ giúp thành công.'
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ArticleCommentController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $article = Article::where('slug', $slug)
            ->where('status', ArticleStatus::Published)
            ->where('allow_comments', true)->firstOrFail();
        $validated = $request->validate([
            'body' => 'required|string|min:3|max:3000',
            'parent_id' => 'nullable|integer|exists:article_comments,id',
            'guest_name' => 'required_without:user|string|max:120',
            'guest_email' => 'nullable|email|max:255',
        ]);
        $key = 'article-comment:'.($request->user()?->id ?: $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['body' => 'Bạn gửi bình luận quá nhanh. Vui lòng thử lại sau.']);
        }
        RateLimiter::hit($key, 60);
        if (isset($validated['parent_id']) && ! ArticleComment::whereKey($validated['parent_id'])->where('article_id', $article->id)->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'Bình luận trả lời không thuộc bài viết này.']);
        }
        $comment = $article->comments()->create([
            'user_id' => $request->user()?->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'guest_name' => $request->user() ? null : $validated['guest_name'],
            'guest_email_hash' => empty($validated['guest_email']) ? null : hash('sha256', mb_strtolower($validated['guest_email'])),
            'body' => HtmlSanitizer::sanitize(strip_tags($validated['body'])),
            'status' => 'pending',
        ]);

        return response()->json(['status' => 'success', 'message' => 'Bình luận đã được gửi và đang chờ duyệt.', 'data' => $comment], 201);
    }
}

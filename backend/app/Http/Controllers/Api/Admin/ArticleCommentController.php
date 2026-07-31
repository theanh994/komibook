<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleComment;
use App\Models\ArticleCommentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArticleCommentController extends Controller
{
    public function index(Request $request)
    {
        $comments = ArticleComment::with(['user:id,name,email', 'article:id,title,slug'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate($request->integer('per_page', 20));

        return response()->json(['status' => 'success', 'data' => $comments]);
    }

    public function moderate(Request $request, ArticleComment $comment)
    {
        $validated = $request->validate(['status' => 'required|in:approved,rejected,spam,hidden', 'reason' => 'nullable|string|max:1000', 'operation_key' => 'nullable|string|max:128']);
        if (in_array($validated['status'], ['rejected', 'spam', 'hidden'], true) && blank($validated['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Cần nhập lý do cho thao tác kiểm duyệt này.']);
        }
        $operationKey = $validated['operation_key'] ?? 'article-comment:'.Str::uuid();
        $updated = DB::transaction(function () use ($request, $comment, $validated, $operationKey) {
            $existing = ArticleCommentEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                return $comment->fresh();
            }
            $locked = ArticleComment::lockForUpdate()->findOrFail($comment->id);
            $from = $locked->status;
            $locked->update(['status' => $validated['status'], 'moderated_by' => $request->user()->id, 'moderation_reason' => $validated['reason'] ?? null, 'moderated_at' => now()]);
            ArticleCommentEvent::create(['article_comment_id' => $locked->id, 'actor_id' => $request->user()->id, 'from_status' => $from, 'to_status' => $validated['status'], 'reason' => $validated['reason'] ?? null, 'operation_key' => $operationKey]);

            return $locked->fresh();
        });

        return response()->json(['status' => 'success', 'data' => $updated]);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\ArticleSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArticleSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $submissions = ArticleSubmission::with(['user:id,name,avatar', 'book:id,title,slug,cover_image'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate($request->integer('per_page', 20));

        return response()->json(['status' => 'success', 'data' => $submissions]);
    }

    public function moderate(Request $request, ArticleSubmission $submission)
    {
        $validated = $request->validate(['action' => 'required|in:convert,reject', 'reason' => 'nullable|string|max:2000']);
        if ($validated['action'] === 'reject' && blank($validated['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Cần nhập lý do từ chối.']);
        }
        $result = DB::transaction(function () use ($request, $submission, $validated) {
            $locked = ArticleSubmission::lockForUpdate()->findOrFail($submission->id);
            abort_unless($locked->status === 'pending', 422);
            $article = null;
            if ($validated['action'] === 'convert') {
                $article = Article::create([
                    'created_by' => $request->user()->id,
                    'article_type' => 'review',
                    'title' => $locked->title,
                    'slug' => $this->uniqueSlug($locked->title),
                    'excerpt' => Str::limit(strip_tags($locked->body), 300),
                    'body' => $locked->body,
                    'status' => ArticleStatus::Draft,
                    'reading_minutes' => max(1, (int) ceil($locked->word_count / 220)),
                ]);
                $article->books()->sync([$locked->book_id]);
                ArticleRevision::create(['article_id' => $article->id, 'actor_id' => $request->user()->id, 'revision' => 1, 'snapshot' => $article->fresh(['books'])->toArray()]);
            }
            $locked->update([
                'status' => $validated['action'] === 'convert' ? 'converted' : 'rejected',
                'converted_article_id' => $article?->id,
                'moderated_by' => $request->user()->id,
                'moderation_reason' => $validated['reason'] ?? null,
                'moderated_at' => now(),
            ]);

            return $locked->fresh(['user:id,name', 'book:id,title,slug']);
        });

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'review-cong-dong';
        $slug = $base;
        $counter = 2;
        while (Article::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}

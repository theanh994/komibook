<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArticleSubmission;
use App\Models\Book;
use App\Models\OrderItem;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;

class ArticleSubmissionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|integer|exists:books,id',
            'title' => 'required|string|min:10|max:255',
            'body' => 'required|string|min:500|max:50000',
        ]);
        Book::findOrFail($validated['book_id']);
        $plainBody = trim(strip_tags($validated['body']));
        $verifiedPurchase = OrderItem::query()
            ->where('book_id', $validated['book_id'])
            ->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id)->whereIn('status', ['completed', 'delivered']))
            ->exists();
        $submission = ArticleSubmission::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'body' => HtmlSanitizer::sanitize($validated['body']),
            'status' => 'pending',
            'verified_purchase' => $verifiedPurchase,
            'word_count' => str_word_count($plainBody),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Bài review đã được gửi tới ban biên tập.', 'data' => $submission], 201);
    }
}

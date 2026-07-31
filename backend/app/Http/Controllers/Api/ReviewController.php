<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewModerationEvent;
use App\Models\ReviewReport;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewController extends Controller
{
    public function upsert(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = $this->verifiedOrder($request, $book);
        abort_unless($order, 422, 'Bạn chỉ có thể đánh giá sách sau khi giao dịch đã hoàn tất.');

        try {
            $review = $this->persistReview($request, $book, $order, $validated);
        } catch (QueryException $exception) {
            if (! in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true)) {
                throw $exception;
            }
            // A concurrent first-write won the unique key. Lock and apply this request as the explicit edit.
            $review = $this->persistReview($request, $book, $order, $validated);
        }

        return response()->json(['status' => 'success', 'data' => $review->load('user')], $review->wasRecentlyCreated ? 201 : 200);
    }

    private function persistReview(Request $request, Book $book, Order $order, array $validated): Review
    {
        return DB::transaction(function () use ($request, $book, $order, $validated) {
            $review = Review::query()->where([
                'user_id' => $request->user()->id,
                'book_id' => $book->id,
                'active_key' => 1,
            ])->lockForUpdate()->first();

            if ($review) {
                $review->update($validated + [
                    'purchase_order_id' => $order->id,
                    'verified_purchase' => true,
                    'moderation_status' => 'published',
                    'edited_at' => now(),
                    'moderation_reason' => null,
                ]);
            } else {
                $review = Review::create($validated + [
                    'user_id' => $request->user()->id,
                    'book_id' => $book->id,
                    'purchase_order_id' => $order->id,
                    'verified_purchase' => true,
                    'moderation_status' => 'published',
                    'active_key' => 1,
                ]);
            }

            return $review;
        });
    }

    public function report(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'in:spam,abuse,personal_information,irrelevant,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_if($review->user_id === $request->user()->id, 422, 'Bạn không thể báo cáo đánh giá của chính mình.');
        abort_unless($review->active_key === 1, 404);

        $report = ReviewReport::firstOrCreate([
            'review_id' => $review->id,
            'reporter_id' => $request->user()->id,
        ], $validated);

        return response()->json(['status' => 'success', 'data' => $report], $report->wasRecentlyCreated ? 201 : 200);
    }

    public function moderationQueue(Request $request): JsonResponse
    {
        $reviews = Review::query()->where('active_key', 1)
            ->with(['user:id,name,email', 'book:id,vendor_id,title,slug', 'book.vendor:id,shop_name'])
            ->withCount(['reports as open_reports_count' => fn ($query) => $query->where('status', 'open')])
            ->when($request->filled('status'), fn ($query) => $query->where('moderation_status', $request->string('status')))
            ->when($request->filled('rating'), fn ($query) => $query->where('rating', $request->integer('rating')))
            ->when($request->filled('vendor_id'), fn ($query) => $query->whereHas('book', fn ($book) => $book->where('vendor_id', $request->integer('vendor_id'))))
            ->when($request->boolean('reported'), fn ($query) => $query->whereHas('reports', fn ($reports) => $reports->where('status', 'open')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('open_reports_count')->latest()->paginate($request->integer('per_page', 20));

        $vendors = Vendor::withoutGlobalScopes()
            ->whereHas('books.reviews', fn ($query) => $query->where('active_key', 1))
            ->orderBy('shop_name')
            ->get(['id', 'shop_name']);

        return response()->json([
            'status' => 'success',
            'data' => $reviews,
            'filters' => ['vendors' => $vendors],
        ]);
    }

    public function moderate(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:published,hidden,rejected'],
            'reason' => ['required_unless:status,published', 'nullable', 'string', 'max:1000'],
            'operation_key' => ['nullable', 'string', 'max:100'],
        ]);
        abort_unless($review->active_key === 1, 404);

        $event = DB::transaction(function () use ($request, $review, $validated) {
            $operationKey = $validated['operation_key'] ?? (string) Str::uuid();
            $existing = ReviewModerationEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                abort_unless($existing->review_id === $review->id && $existing->to_status === $validated['status'], 409, 'Khóa thao tác đã được dùng cho yêu cầu khác.');

                return $existing;
            }

            $from = $review->moderation_status;
            $review->update([
                'moderation_status' => $validated['status'],
                'moderation_reason' => $validated['reason'] ?? null,
                'moderated_at' => now(),
                'moderated_by' => $request->user()->id,
            ]);
            ReviewReport::where('review_id', $review->id)->where('status', 'open')->update([
                'status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'updated_at' => now(),
            ]);

            return ReviewModerationEvent::create([
                'review_id' => $review->id, 'actor_id' => $request->user()->id,
                'action' => 'moderated', 'from_status' => $from, 'to_status' => $validated['status'],
                'reason' => $validated['reason'] ?? null, 'operation_key' => $operationKey,
            ]);
        });

        return response()->json(['status' => 'success', 'data' => ['review' => $review->fresh(), 'event' => $event]]);
    }

    private function verifiedOrder(Request $request, Book $book): ?Order
    {
        return Order::withoutGlobalScopes()->where('user_id', $request->user()->id)
            ->where('status', 'completed')->where('payment_status', 'paid')
            ->whereHas('orderItems', fn ($query) => $query->where('book_id', $book->id))
            ->latest('id')->first();
    }
}

<?php

namespace App\Services;

use App\Enums\BookPublicationStatus;
use App\Models\Book;
use App\Models\BookPublishedRevision;
use App\Models\BookPublishingEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookPublishingService
{
    public function __construct(private BookPublicationEligibilityService $eligibility) {}

    public function transition(Book $book, BookPublicationStatus $target, User $actor, ?string $reason = null, ?string $operationKey = null, mixed $scheduledFor = null): Book
    {
        $operationKey ??= 'book-publishing:'.Str::uuid();

        return DB::transaction(function () use ($book, $target, $actor, $reason, $operationKey, $scheduledFor): Book {
            $existing = BookPublishingEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                if ($existing->book_id !== $book->id || $existing->to_status !== $target->value) {
                    throw ValidationException::withMessages(['operation_key' => 'Operation key was already used.']);
                }

                return $book->fresh();
            }

            $locked = Book::withoutGlobalScopes()->lockForUpdate()->findOrFail($book->id);
            $from = $locked->publishing_status instanceof BookPublicationStatus
                ? $locked->publishing_status
                : (BookPublicationStatus::tryFrom($locked->publishing_status ?? '') ?? BookPublicationStatus::Draft);
            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages(['publishing_status' => "Invalid publication transition: {$from->value} -> {$target->value}."]);
            }
            if ($target === BookPublicationStatus::ChangesRequested && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Review feedback is required.']);
            }
            if (in_array($target, [BookPublicationStatus::SubmittedForReview, BookPublicationStatus::Resubmitted, BookPublicationStatus::Published], true)) {
                $this->eligibility->assertEligible($locked);
            }
            if ($target === BookPublicationStatus::Scheduled && (! $scheduledFor || now()->gte($scheduledFor))) {
                throw ValidationException::withMessages(['scheduled_for' => 'Schedule must be in the future.']);
            }

            $updates = ['publishing_status' => $target, 'publication_feedback' => $reason];
            if (in_array($target, [BookPublicationStatus::SubmittedForReview, BookPublicationStatus::Resubmitted], true)) {
                $updates['submitted_for_review_at'] = now();
            }
            if ($target === BookPublicationStatus::Approved) {
                $updates['approved_at'] = now();
            }
            if ($target === BookPublicationStatus::Scheduled) {
                $updates['scheduled_for'] = $scheduledFor;
            }
            if ($target === BookPublicationStatus::ChangesRequested) {
                $updates['publication_version'] = $locked->publication_version + 1;
            }
            if ($target === BookPublicationStatus::Published) {
                $updates += ['status' => 'published', 'published_at' => now(), 'scheduled_for' => null];
            }
            $locked->update($updates);

            BookPublishingEvent::create([
                'book_id' => $locked->id, 'actor_id' => $actor->id, 'from_status' => $from->value,
                'to_status' => $target->value, 'reason' => $reason, 'operation_key' => $operationKey,
                'metadata' => $scheduledFor ? ['scheduled_for' => (string) $scheduledFor] : null,
            ]);
            if ($target === BookPublicationStatus::Published) {
                BookPublishedRevision::create([
                    'book_id' => $locked->id, 'published_by' => $actor->id, 'version' => $locked->publication_version,
                    'book_snapshot' => $locked->only(['title', 'description', 'cover_image', 'price', 'sale_price', 'type', 'file_path']),
                    'chapter_snapshot' => $locked->chapters()->get(['id', 'title', 'content', 'order', 'is_free', 'current_revision'])->toArray(),
                    'published_at' => now(),
                ]);
            }

            UserNotification::firstOrCreate(
                ['operation_key' => "notification:{$operationKey}:{$locked->vendor->user_id}"],
                ['user_id' => $locked->vendor->user_id, 'title' => 'Cập nhật xuất bản', 'content' => 'Sách đã chuyển sang trạng thái '.$target->value.($reason ? '. Lý do: '.$reason : '.'), 'type' => 'system', 'data' => ['book_id' => $locked->id, 'status' => $target->value]]
            );

            return $locked->fresh(['publishingEvents', 'publishedRevisions']);
        });
    }
}

<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\FlashSaleBook;
use App\Models\FlashSaleEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlashSaleWorkflowService
{
    public function transition(FlashSale $sale, string $target, User $actor, ?string $reason, string $key): FlashSale
    {
        return DB::transaction(function () use ($sale, $target, $actor, $reason, $key) {
            if ($event = FlashSaleEvent::where('operation_key', $key)->first()) {
                if ($event->flash_sale_id !== $sale->id || $event->to_status !== $target) {
                    throw ValidationException::withMessages(['operation_key' => 'Operation key conflict.']);
                }

                return $sale->fresh();
            }
            $locked = FlashSale::lockForUpdate()->findOrFail($sale->id);
            $allowed = ['draft' => ['enrollment_open', 'cancelled'], 'enrollment_open' => ['active', 'cancelled'], 'active' => ['ended', 'cancelled'], 'ended' => [], 'cancelled' => []];
            if (! in_array($target, $allowed[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Invalid Flash Sale lifecycle transition.']);
            }
            if ($target === 'active' && ! ($locked->start_time->lte(now()) && $locked->end_time->gt(now()))) {
                throw ValidationException::withMessages(['status' => 'Campaign can become active only inside its configured time window.']);
            }
            if (in_array($target, ['ended', 'cancelled'], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required.']);
            }
            $from = $locked->status;
            $locked->update(['status' => $target, 'is_active' => $target === 'active']);
            FlashSaleEvent::create(['flash_sale_id' => $locked->id, 'actor_id' => $actor->id, 'action' => 'campaign_transition', 'from_status' => $from, 'to_status' => $target, 'reason' => $reason, 'operation_key' => $key]);

            return $locked->fresh();
        });
    }

    public function decide(FlashSaleBook $item, string $target, User $actor, ?string $reason, string $key): FlashSaleBook
    {
        return DB::transaction(function () use ($item, $target, $actor, $reason, $key) {
            if ($event = FlashSaleEvent::where('operation_key', $key)->first()) {
                if ($event->flash_sale_book_id !== $item->id || $event->to_status !== $target) {
                    throw ValidationException::withMessages(['operation_key' => 'Operation key conflict.']);
                }

                return $item->fresh();
            }
            $locked = FlashSaleBook::with(['book.vendor', 'flashSale'])->lockForUpdate()->findOrFail($item->id);
            if ($locked->status !== 'pending' || ! in_array($target, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'Only pending enrollment can be decided.']);
            }
            if ($target === 'rejected' && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A rejection reason is required.']);
            }
            if ($target === 'approved') {
                if (! $locked->book?->isPublished() || ! $locked->book?->vendor?->isActive()) {
                    throw ValidationException::withMessages(['book' => 'Book or vendor is not eligible.']);
                }
                $overlap = FlashSaleBook::where('book_id', $locked->book_id)->where('status', 'approved')->whereKeyNot($locked->id)
                    ->whereHas('flashSale', fn ($query) => $query->whereNotIn('status', ['ended', 'cancelled'])->where('start_time', '<', $locked->flashSale->end_time)->where('end_time', '>', $locked->flashSale->start_time))->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['book' => 'Book already has an overlapping approved promotion.']);
                }
            }
            $from = $locked->status;
            $locked->update(['status' => $target, 'sale_price' => $target === 'approved' ? (int) round((int) ($locked->book->sale_price ?? $locked->book->price) * (100 - $locked->discount_percent) / 100) : null, 'decided_by' => $actor->id, 'decision_reason' => $reason]);
            FlashSaleEvent::create(['flash_sale_id' => $locked->flash_sale_id, 'flash_sale_book_id' => $locked->id, 'actor_id' => $actor->id, 'action' => 'enrollment_decision', 'from_status' => $from, 'to_status' => $target, 'reason' => $reason, 'snapshot' => $locked->fresh()->toArray(), 'operation_key' => $key]);
            $vendorUserId = $locked->book->vendor?->user_id;
            if ($vendorUserId) {
                UserNotification::firstOrCreate(['operation_key' => "notification:{$key}:{$vendorUserId}"], ['user_id' => $vendorUserId, 'title' => 'Cập nhật Flash Sale', 'content' => "Đăng ký Flash Sale đã được {$target}.", 'type' => 'system', 'data' => ['flash_sale_book_id' => $locked->id, 'status' => $target]]);
            }

            return $locked->fresh(['book', 'flashSale']);
        });
    }
}

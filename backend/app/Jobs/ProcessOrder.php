<?php

namespace App\Jobs;

use App\Models\Book;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            // Lấy order cùng với orderItems (bỏ qua Global Scope vendor để đảm bảo Job luôn lấy được đơn hàng)
            $order = Order::withoutGlobalScopes()->with('orderItems')->findOrFail($this->orderId);

            // Chuyển trạng thái sang processing
            $order->status = 'processing';
            $order->saveQuietly();

            // Trừ tồn kho thực tế trong MySQL
            foreach ($order->orderItems as $item) {
                // Sử dụng decrement đảm bảo an toàn truy cập đồng thời (concurrency)
                Book::withoutGlobalScopes()
                    ->where('id', $item->book_id)
                    ->decrement('stock', $item->quantity);
            }
            
            Log::info("Job ProcessOrder completed: Order [{$order->order_code}] successfully processed.");
        });
    }
}

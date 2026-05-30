<?php

namespace App\Jobs;

use App\Models\Book;
use App\Models\Order;
use App\Models\WarehouseStock;
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
            $order = Order::withoutGlobalScopes()->with(['orderItems.book', 'user'])->findOrFail($this->orderId);

            // Chuyển trạng thái sang processing
            $order->status = 'processing';
            $order->saveQuietly();

            // Trừ tồn kho thực tế trong MySQL
            foreach ($order->orderItems as $item) {
                // Sử dụng decrement đảm bảo an toàn truy cập đồng thời (concurrency)
                $book = Book::withoutGlobalScopes()->where('id', $item->book_id)->first();
                if ($book) {
                    $book->decrement('stock', $item->quantity);

                    // Trừ tồn kho chi tiết trong warehouse_stocks cho sách vật lý
                    if ($book->type === 'physical') {
                        $warehouseStock = WarehouseStock::where('book_id', $book->id)
                            ->where('quantity', '>=', $item->quantity)
                            ->first();

                        if ($warehouseStock) {
                            $warehouseStock->decrement('quantity', $item->quantity);
                        } else {
                            // Trừ lũy tiến từ các kho chứa sách
                            $remainingToDeduct = $item->quantity;
                            $stocks = WarehouseStock::where('book_id', $book->id)
                                ->where('quantity', '>', 0)
                                ->orderBy('quantity', 'desc')
                                ->get();

                            foreach ($stocks as $stock) {
                                if ($remainingToDeduct <= 0) break;
                                $deduct = min($stock->quantity, $remainingToDeduct);
                                $stock->decrement('quantity', $deduct);
                                $remainingToDeduct -= $deduct;
                            }
                        }
                    }
                }
            }

            // Tạo thông báo cơ sở dữ liệu cho User
            try {
                $isCod = $order->payment_method === 'cod';
                $messageContent = $isCod
                    ? "Đơn hàng {$order->order_code} đã được đặt thành công và đang được xử lý."
                    : "Đơn hàng {$order->order_code} đã thanh toán thành công và đang được xử lý.";

                \App\Models\UserNotification::create([
                    'user_id' => $order->user_id,
                    'title' => 'Đặt hàng thành công',
                    'content' => $messageContent,
                    'type' => 'order',
                    'data' => [
                        'order_id' => $order->id,
                        'order_code' => $order->order_code,
                        'icon' => 'shopping_bag',
                        'colorClass' => 'bg-green-100 text-green-600'
                    ]
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to create database notification: " . $e->getMessage());
            }

            // Gửi email xác nhận thành công cho Khách hàng
            try {
                if ($order->user && $order->user->email) {
                    \Illuminate\Support\Facades\Mail::to($order->user->email)
                        ->send(new \App\Mail\OrderSuccessMail($order));
                }
            } catch (\Exception $e) {
                Log::error("Failed to send order success mail: " . $e->getMessage());
            }
            
            Log::info("Job ProcessOrder completed: Order [{$order->order_code}] successfully processed.");
        });
    }
}

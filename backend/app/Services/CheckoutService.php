<?php

namespace App\Services;

use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CheckoutService
{
    /**
     * Xử lý luồng Checkout chịu tải cao với Redis Lock và Queue.
     * 
     * @param array $items [ ['book_id' => 1, 'quantity' => 2], ... ]
     * @param array $shippingData ['shipping_address' => '...', 'phone' => '...']
     * @param int $userId
     * @return array Danh sách Order được tạo
     * @throws Exception
     */
    public function processCheckout(array $items, array $shippingData, int $userId): array
    {
        $bookIds = array_column($items, 'book_id');
        // Không dùng GlobalScope Vendor ở đây vì giỏ hàng chứa sách của nhiều shop khác nhau
        $books = Book::withoutGlobalScopes()->whereIn('id', $bookIds)->get()->keyBy('id');

        // BƯỚC 2a: Redis Stock Lock
        foreach ($items as $item) {
            $bookId = $item['book_id'];
            $quantity = $item['quantity'];
            
            if (!$books->has($bookId)) {
                throw new Exception("Sản phẩm không tồn tại (ID: {$bookId})");
            }
            $book = $books->get($bookId);

            $redisKey = "book_stock:{$bookId}";

            // Nếu key chưa có trên Redis, load từ DB lên
            if (!Redis::exists($redisKey)) {
                Redis::set($redisKey, $book->stock);
            }

            // Trừ tồn kho tạm thời trên Redis
            $remaining = Redis::decrBy($redisKey, $quantity);

            // Nếu < 0 tức là hết hàng -> Rollback Redis và báo lỗi
            if ($remaining < 0) {
                Redis::incrBy($redisKey, $quantity); // Hoàn lại số lượng vừa trừ
                throw new Exception("Sản phẩm '{$book->title}' không đủ số lượng tồn kho.");
            }
        }

        // BƯỚC 2b: Split Orders theo Multi-vendor
        $groupedItems = [];
        foreach ($items as $item) {
            $bookId = $item['book_id'];
            $quantity = $item['quantity'];
            $book = $books->get($bookId);
            $vendorId = $book->vendor_id;

            if (!isset($groupedItems[$vendorId])) {
                $groupedItems[$vendorId] = [];
            }

            $price = $book->sale_price ?? $book->price;

            $groupedItems[$vendorId][] = [
                'book_id' => $bookId,
                'quantity' => $quantity,
                'price' => $price,
            ];
        }

        // BƯỚC 2c: Create Orders
        $createdOrders = [];

        try {
            DB::beginTransaction();

            foreach ($groupedItems as $vendorId => $vendorItems) {
                // Tính tổng tiền cho vendor này
                $totalAmount = array_sum(array_map(function($item) {
                    return $item['price'] * $item['quantity'];
                }, $vendorItems));

                // Tạo Order
                $order = new Order([
                    'user_id' => $userId,
                    'vendor_id' => $vendorId,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'payment_method' => 'cod', // Mặc định COD
                    'shipping_address' => $shippingData['shipping_address'],
                    'phone' => $shippingData['phone'],
                ]);
                
                // Sử dụng saveQuietly() để không kích hoạt các event Eloquent (như creating/created)
                // Điều này giúp tránh việc trait MultiVendorScoped tự động override `vendor_id` thành id của user hiện tại.
                $order->order_code = Order::generateOrderCode();
                $order->saveQuietly();

                // Tạo OrderItem
                foreach ($vendorItems as $item) {
                    $orderItem = new OrderItem([
                        'order_id' => $order->id,
                        'book_id' => $item['book_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);
                    $orderItem->saveQuietly();
                }

                $createdOrders[] = $order;
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            // Rollback Redis stock nếu DB lưu thất bại
            foreach ($items as $item) {
                Redis::incrBy("book_stock:{$item['book_id']}", $item['quantity']);
            }
            throw $e;
        }

        // Bước 3: Đẩy Job vào Queue để xử lý bất đồng bộ
        foreach ($createdOrders as $order) {
            ProcessOrder::dispatch($order->id);
        }

        return $createdOrders;
    }
}

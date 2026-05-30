<?php

namespace App\Services;

use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Coupon;
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
     * @param string|null $couponCode
     * @return array Danh sách Order được tạo
     * @throws Exception
     */
    public function processCheckout(array $items, array $shippingData, int $userId, ?string $couponCode = null): array
    {
        $bookIds = array_column($items, 'book_id');
        // Không dùng GlobalScope Vendor ở đây vì giỏ hàng chứa sách của nhiều shop khác nhau
        $books = Book::withoutGlobalScopes()->whereIn('id', $bookIds)->get()->keyBy('id');

        // BƯỚC 1: Kiểm tra E-book đã sở hữu
        $ebookIds = [];
        foreach ($items as $item) {
            $bookId = $item['book_id'];
            if ($books->has($bookId) && $books->get($bookId)->isEbook()) {
                $ebookIds[] = $bookId;
            }
        }

        if (!empty($ebookIds)) {
            // Tìm các e-book đã mua thành công trước đó
            $ownedEbookIds = OrderItem::whereIn('book_id', $ebookIds)
                ->whereHas('order', function ($q) use ($userId) {
                    $q->withoutGlobalScopes()
                      ->where('user_id', $userId)
                      ->whereIn('status', ['pending', 'processing', 'shipped', 'completed']);
                })
                ->pluck('book_id')
                ->unique()
                ->toArray();

            if (!empty($ownedEbookIds)) {
                $ownedTitles = $books->only($ownedEbookIds)->pluck('title')->implode(', ');
                throw new Exception("Bạn đã sở hữu E-book: {$ownedTitles}. Mỗi E-book chỉ được mua 1 lần.");
            }
        }

        // BƯỚC 2a: Redis Stock Lock (có fallback)
        $useRedis = false;
        try {
            // Thử ping Redis để xác định có kết nối được không
            Redis::ping();
            $useRedis = true;
        } catch (\Exception $e) {
            Log::warning("Redis is not available, falling back to database stock check: " . $e->getMessage());
        }

        if ($useRedis) {
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
        } else {
            // DB fallback: kiểm tra tồn kho trực tiếp từ CSDL
            foreach ($items as $item) {
                $bookId = $item['book_id'];
                $quantity = $item['quantity'];
                
                if (!$books->has($bookId)) {
                    throw new Exception("Sản phẩm không tồn tại (ID: {$bookId})");
                }
                $book = $books->get($bookId);
                
                if ($book->stock < $quantity) {
                    throw new Exception("Sản phẩm '{$book->title}' không đủ số lượng tồn kho.");
                }
            }
        }

        // BƯỚC 2b: Split Orders theo Multi-vendor
        $groupedItems = [];
        $totalCartAmount = 0;
        foreach ($items as $item) {
            $bookId = $item['book_id'];
            $quantity = $item['quantity'];
            $book = $books->get($bookId);
            $vendorId = $book->vendor_id;

            if (!isset($groupedItems[$vendorId])) {
                $groupedItems[$vendorId] = [];
            }

            $price = $book->sale_price ?? $book->price;
            $subtotal = $price * $quantity;
            $totalCartAmount += $subtotal;

            $groupedItems[$vendorId][] = [
                'book_id' => $bookId,
                'quantity' => $quantity,
                'price' => $price,
            ];
        }

        // BƯỚC 2b.2: Xác thực & Tính toán giảm giá Coupon
        $coupon = null;
        $vendorDiscounts = [];
        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon) {
                // Kiểm tra thời gian
                $now = now();
                $isValidTime = true;
                if (($coupon->start_time && $now->lt($coupon->start_time)) || ($coupon->end_time && $now->gt($coupon->end_time))) {
                    $isValidTime = false;
                }
                if ($coupon->valid_until && $coupon->valid_until->isPast()) {
                    $isValidTime = false;
                }
                
                // Kiểm tra giới hạn sử dụng
                $isValidUsage = true;
                if ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) {
                    $isValidUsage = false;
                }

                // Kiểm tra đơn hàng tối thiểu
                $isValidMinOrder = $totalCartAmount >= $coupon->min_order_value;

                if ($isValidTime && $isValidUsage && $isValidMinOrder) {
                    $totalCategoryBase = 0;
                    // Phân bổ giảm giá theo từng vendor
                    foreach ($groupedItems as $vendorId => $vendorItems) {
                        $eligibleBase = 0;
                        foreach ($vendorItems as $vItem) {
                            $book = $books->get($vItem['book_id']);
                            if (!$coupon->category_id || ($book && $book->category_id == $coupon->category_id)) {
                                $eligibleBase += $vItem['price'] * $vItem['quantity'];
                            }
                        }
                        $vendorDiscounts[$vendorId] = ($eligibleBase * $coupon->discount_percent) / 100;
                        $totalCategoryBase += $eligibleBase;
                    }

                    $totalCalculatedDiscount = array_sum($vendorDiscounts);
                    if ($totalCalculatedDiscount > 0) {
                        // Khống chế số tiền giảm tối đa
                        if ($coupon->max_discount_amount && $totalCalculatedDiscount > $coupon->max_discount_amount) {
                            $scale = $coupon->max_discount_amount / $totalCalculatedDiscount;
                            foreach ($vendorDiscounts as $vendorId => $discount) {
                                $vendorDiscounts[$vendorId] = round($discount * $scale);
                            }
                        } else {
                            foreach ($vendorDiscounts as $vendorId => $discount) {
                                $vendorDiscounts[$vendorId] = round($discount);
                            }
                        }
                    }
                } else {
                    $coupon = null; // Huỷ coupon nếu không thoả mãn điều kiện
                }
            }
        }

        // Xác định phương thức thanh toán
        $paymentMethod = 'cod';
        if (isset($shippingData['payment_method'])) {
            $pm = strtolower($shippingData['payment_method']);
            if ($pm === 'vnpay' || $pm === 'online') {
                $paymentMethod = 'online';
            }
        }

        // BƯỚC 2c: Create Orders
        $createdOrders = [];

        try {
            DB::beginTransaction();

            foreach ($groupedItems as $vendorId => $vendorItems) {
                // Tính tổng tiền cho vendor này
                $subtotalAmount = array_sum(array_map(function($item) {
                    return $item['price'] * $item['quantity'];
                }, $vendorItems));

                // Áp dụng giảm giá coupon cho vendor này nếu có
                $discountForThisVendor = $vendorDiscounts[$vendorId] ?? 0;
                $totalAmount = max(0, $subtotalAmount - $discountForThisVendor);

                // Tạo Order
                $order = new Order([
                    'user_id' => $userId,
                    'vendor_id' => $vendorId,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'payment_method' => $paymentMethod,
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

            // Tăng số lượt sử dụng của Coupon
            if ($coupon) {
                $coupon->increment('used_count');
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            if ($useRedis) {
                // Rollback Redis stock nếu DB lưu thất bại
                try {
                    foreach ($items as $item) {
                        Redis::incrBy("book_stock:{$item['book_id']}", $item['quantity']);
                    }
                } catch (\Exception $ex) {
                    Log::error("Failed to rollback Redis stock: " . $ex->getMessage());
                }
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

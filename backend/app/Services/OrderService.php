<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * OrderService — Xử lý logic tạo đơn hàng từ giỏ hàng đa nhà bán.
 *
 * Giỏ hàng (cart) được truyền vào dưới dạng Collection các item:
 *
 * [
 *   ['book_id' => 1, 'quantity' => 2],
 *   ['book_id' => 3, 'quantity' => 1],
 *   ...
 * ]
 *
 * Vì đây là hệ thống multi-vendor, mỗi vendor sẽ xử lý đơn hàng riêng.
 * Service này tách giỏ hàng → nhóm theo vendor_id → tạo nhiều Order.
 */
class OrderService
{
    /**
     * Tạo đơn hàng từ giỏ hàng.
     *
     * @param  User  $buyer           Người mua
     * @param  array $cartItems       Mảng items từ giỏ hàng [['book_id'=>?, 'quantity'=>?], ...]
     * @param  array $shippingInfo    ['shipping_address' => '...', 'phone' => '...', 'payment_method' => 'cod|online']
     * @return Collection<Order>      Danh sách các đơn hàng đã được tạo
     *
     * @throws \Exception Nếu sách không tồn tại, hết hàng, hoặc DB lỗi
     */
    public function createOrdersFromCart(User $buyer, array $cartItems, array $shippingInfo): Collection
    {
        // ── Bước 1: Load tất cả sách cần thiết (bypass scope để lấy đầy đủ) ──
        $bookIds = collect($cartItems)->pluck('book_id')->unique();

        $books = Book::withoutGlobalScopes()
            ->whereIn('id', $bookIds)
            ->where('status', 'published')
            ->get()
            ->keyBy('id');

        // Kiểm tra sách tồn tại và còn hàng
        foreach ($cartItems as $item) {
            $book = $books->get($item['book_id']);

            if (! $book) {
                throw new \Exception("Sách ID {$item['book_id']} không tồn tại hoặc đã ngừng bán.");
            }

            if ($book->type === 'physical' && $book->stock < $item['quantity']) {
                throw new \Exception("Sách \"{$book->title}\" không đủ số lượng tồn kho.");
            }
        }

        // ── Bước 2: Nhóm các item theo vendor_id ─────────────────────────────
        //
        //  Ví dụ giỏ hàng ban đầu:
        //   [book_id:1 (vendor:A), book_id:2 (vendor:B), book_id:3 (vendor:A)]
        //
        //  Sau khi group:
        //   vendor_A => [book_id:1, book_id:3]
        //   vendor_B => [book_id:2]
        //
        $grouped = collect($cartItems)->groupBy(function ($item) use ($books) {
            return $books->get($item['book_id'])->vendor_id;
        });

        // ── Bước 3: Tạo một Order riêng cho mỗi vendor ───────────────────────
        $createdOrders = collect();

        DB::transaction(function () use ($grouped, $books, $buyer, $shippingInfo, &$createdOrders) {
            foreach ($grouped as $vendorId => $items) {
                // Tính tổng tiền cho đơn hàng của vendor này
                $totalAmount = collect($items)->sum(function ($item) use ($books) {
                    $book  = $books->get($item['book_id']);
                    $price = $book->sale_price ?? $book->price; // Ưu tiên giá khuyến mãi
                    return $price * $item['quantity'];
                });

                // Tạo Order
                $order = Order::create([
                    'user_id'          => $buyer->id,
                    'vendor_id'        => $vendorId,
                    'total_amount'     => $totalAmount,
                    'status'           => 'pending',
                    'payment_status'   => 'unpaid',
                    'payment_method'   => $shippingInfo['payment_method'] ?? 'cod',
                    'shipping_address' => $shippingInfo['shipping_address'],
                    'phone'            => $shippingInfo['phone'],
                    // order_code được tự sinh trong Order::booted()
                ]);

                // Tạo OrderItems và trừ tồn kho (sách vật lý)
                foreach ($items as $item) {
                    $book  = $books->get($item['book_id']);
                    $price = $book->sale_price ?? $book->price;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'book_id'  => $book->id,
                        'quantity' => $item['quantity'],
                        'price'    => $price, // Snapshot giá tại thời điểm mua
                    ]);

                    // Trừ tồn kho với sách vật lý
                    if ($book->type === 'physical') {
                        $book->decrement('stock', $item['quantity']);

                        // Tự động chuyển status nếu hết hàng
                        if ($book->fresh()->stock === 0) {
                            $book->update(['status' => 'out_of_stock']);
                        }
                    }
                }

                $createdOrders->push($order->load('orderItems'));
            }
        });

        return $createdOrders;
    }
}

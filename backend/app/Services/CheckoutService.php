<?php

namespace App\Services;

use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Inventory\InventoryReservationService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CheckoutService
{
    /**
     * Lấy tỷ lệ hoa hồng (commission_rate) từ system_config.json với fallback an toàn.
     */
    protected function getCommissionRate(): float
    {
        $defaultRate = 10.0;
        $configPath = storage_path('app/private/system_config.json');

        if (! File::exists($configPath)) {
            return $defaultRate;
        }

        try {
            $content = File::get($configPath);
            if ($content === false || $content === '') {
                return $defaultRate;
            }

            $config = json_decode($content, true);
            if (! is_array($config) || ! isset($config['commission_rate']) || ! is_numeric($config['commission_rate'])) {
                return $defaultRate;
            }

            $rate = (float) $config['commission_rate'];

            return min(100.0, max(0.0, $rate));
        } catch (\Throwable $e) {
            return $defaultRate;
        }
    }

    /**
     * Xử lý luồng Checkout với Inventory Reservation.
     *
     * @param  array  $items  [ ['book_id' => 1, 'quantity' => 2], ... ]
     * @param  array  $shippingData  ['shipping_address' => '...', 'phone' => '...']
     * @return array Danh sách Order được tạo
     *
     * @throws Exception
     */
    public function processCheckout(array $items, array $shippingData, int $userId, ?string $couponCode = null): array
    {
        $bookIds = array_column($items, 'book_id');
        $books = Book::withoutGlobalScopes()->whereIn('id', $bookIds)->get()->keyBy('id');

        // BƯỚC 1: Kiểm tra E-book đã sở hữu
        $ebookIds = [];
        foreach ($items as $item) {
            $bookId = $item['book_id'];
            if ($books->has($bookId) && $books->get($bookId)->isEbook()) {
                $ebookIds[] = $bookId;
            }
        }

        if (! empty($ebookIds)) {
            $ownedEbookIds = OrderItem::whereIn('book_id', $ebookIds)
                ->whereHas('order', function ($q) use ($userId) {
                    $q->withoutGlobalScopes()
                        ->where('user_id', $userId)
                        ->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped', 'completed']);
                })
                ->pluck('book_id')
                ->unique()
                ->toArray();

            if (! empty($ownedEbookIds)) {
                $ownedTitles = $books->only($ownedEbookIds)->pluck('title')->implode(', ');
                throw new Exception("Bạn đã sở hữu E-book: {$ownedTitles}. Mỗi E-book chỉ được mua 1 lần.");
            }
        }

        // BƯỚC 2: Split Orders theo Multi-vendor
        $groupedItems = [];
        $totalCartAmount = 0;
        foreach ($items as $item) {
            $bookId = $item['book_id'];
            $quantity = $item['quantity'];

            if (! $books->has($bookId)) {
                throw new Exception("Sản phẩm không tồn tại (ID: {$bookId})");
            }
            $book = $books->get($bookId);
            $vendorId = $book->vendor_id;

            if (! isset($groupedItems[$vendorId])) {
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

        // BƯỚC 3: Xác thực & Tính toán giảm giá Coupon
        $coupon = null;
        $vendorDiscounts = [];
        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon) {
                $now = now();
                $isValidTime = true;
                if (($coupon->start_time && $now->lt($coupon->start_time)) || ($coupon->end_time && $now->gt($coupon->end_time))) {
                    $isValidTime = false;
                }
                if ($coupon->valid_until && $coupon->valid_until->isPast()) {
                    $isValidTime = false;
                }

                $isValidUsage = true;
                if ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) {
                    $isValidUsage = false;
                }

                $isValidMinOrder = $totalCartAmount >= $coupon->min_order_value;

                if ($isValidTime && $isValidUsage && $isValidMinOrder) {
                    $totalCategoryBase = 0;
                    foreach ($groupedItems as $vendorId => $vendorItems) {
                        $eligibleBase = 0;
                        foreach ($vendorItems as $vItem) {
                            $book = $books->get($vItem['book_id']);
                            if (! $coupon->category_id || ($book && $book->category_id == $coupon->category_id)) {
                                $eligibleBase += $vItem['price'] * $vItem['quantity'];
                            }
                        }
                        $vendorDiscounts[$vendorId] = ($eligibleBase * $coupon->discount_percent) / 100;
                        $totalCategoryBase += $eligibleBase;
                    }

                    $totalCalculatedDiscount = array_sum($vendorDiscounts);
                    if ($totalCalculatedDiscount > 0) {
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
                    $coupon = null;
                }
            }
        }

        // BƯỚC 4: Xác định phương thức thanh toán
        $paymentMethod = 'cod';
        if (isset($shippingData['payment_method'])) {
            $pm = strtolower($shippingData['payment_method']);
            if ($pm === 'vnpay' || $pm === 'online') {
                $paymentMethod = 'online';
            }
        }

        // BƯỚC 5: Tính toán snapshot tài chính cho từng vendor & session
        $user = User::with('membershipTier')->find($userId);
        $commissionRate = $this->getCommissionRate();

        $vendorSnapshots = [];
        $sessionSubtotal = 0;
        $sessionDiscount = 0;
        $sessionFee = 0;
        $sessionTotal = 0;

        foreach ($groupedItems as $vendorId => $vendorItems) {
            $subtotal = (int) array_sum(array_map(function ($vItem) {
                return $vItem['price'] * $vItem['quantity'];
            }, $vendorItems));

            $couponDisc = (int) round($vendorDiscounts[$vendorId] ?? 0);

            $membershipDisc = 0;
            if ($user && $user->membershipTier && $user->membershipTier->discount_percent > 0) {
                $afterCoupon = max(0, $subtotal - $couponDisc);
                $membershipDisc = (int) round(($afterCoupon * (float) $user->membershipTier->discount_percent) / 100.0);
            }

            $discount = min($subtotal, $couponDisc + $membershipDisc);
            $fee = 0;
            $total = max(0, $subtotal - $discount + $fee);
            $commissionAmt = (int) round(($total * $commissionRate) / 100.0);

            $vendorSnapshots[$vendorId] = [
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'fee_amount' => $fee,
                'total_amount' => $total,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmt,
            ];

            $sessionSubtotal += $subtotal;
            $sessionDiscount += $discount;
            $sessionFee += $fee;
            $sessionTotal += $total;
        }

        // BƯỚC 6: DB Transaction - Create Session, Orders, Items, Links, và Inventory Reservations
        $createdOrders = [];
        $isCod = ($paymentMethod === 'cod');

        try {
            DB::beginTransaction();

            $expiresAt = now()->addMinutes(15);

            $checkoutSession = CheckoutSession::create([
                'user_id' => $userId,
                'currency' => 'VND',
                'subtotal_amount' => $sessionSubtotal,
                'discount_amount' => $sessionDiscount,
                'fee_amount' => $sessionFee,
                'total_amount' => $sessionTotal,
                'expires_at' => $expiresAt,
            ]);

            foreach ($groupedItems as $vendorId => $vendorItems) {
                $snapshot = $vendorSnapshots[$vendorId];

                $initialStatus = $isCod ? 'confirmed' : 'pending';

                $order = new Order([
                    'user_id' => $userId,
                    'vendor_id' => $vendorId,
                    'total_amount' => $snapshot['total_amount'],
                    'status' => $initialStatus,
                    'payment_status' => 'unpaid',
                    'payment_method' => $paymentMethod,
                    'shipping_address' => $shippingData['shipping_address'],
                    'phone' => $shippingData['phone'],
                ]);

                $order->order_code = Order::generateOrderCode();
                $order->saveQuietly();

                foreach ($vendorItems as $item) {
                    $orderItem = new OrderItem([
                        'order_id' => $order->id,
                        'book_id' => $item['book_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);
                    $orderItem->saveQuietly();
                }

                $checkoutSessionOrder = new CheckoutSessionOrder([
                    'checkout_session_id' => $checkoutSession->id,
                    'order_id' => $order->id,
                    'vendor_id' => $vendorId,
                    'subtotal_amount' => $snapshot['subtotal_amount'],
                    'discount_amount' => $snapshot['discount_amount'],
                    'fee_amount' => $snapshot['fee_amount'],
                    'commission_rate' => $snapshot['commission_rate'],
                    'commission_amount' => $snapshot['commission_amount'],
                    'total_amount' => $snapshot['total_amount'],
                ]);
                $checkoutSessionOrder->saveQuietly();

                $createdOrders[] = $order;
            }

            // Gọi InventoryReservationService::reserve() cho toàn session
            $reservationService = app(InventoryReservationService::class);
            $reservationService->reserve(
                $checkoutSession,
                $expiresAt,
                "checkout-reserve:{$checkoutSession->checkout_code}"
            );

            // Tăng số lượt sử dụng Coupon
            if ($coupon) {
                $coupon->increment('used_count');
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        // BƯỚC 7: Post-Commit - Chỉ dispatch ProcessOrder cho đơn COD
        if ($isCod) {
            foreach ($createdOrders as $order) {
                ProcessOrder::dispatch($order->id);
            }
        }

        return $createdOrders;
    }
}

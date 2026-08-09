<?php

namespace App\Services;

use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\Coupon;
use App\Models\InvoiceSnapshot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Inventory\InventoryReservationService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

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
    public function processCheckout(
        array $items,
        array $shippingData,
        int $userId,
        ?string $couponCode = null,
        ?array $digitalConsent = null,
    ): array {
        $bookIds = array_map(fn ($item) => (int) ($item['book_id'] ?? 0), $items);
        if (count($bookIds) !== count(array_unique($bookIds, SORT_REGULAR))) {
            throw ValidationException::withMessages(['items' => 'Duplicate book IDs are not allowed.']);
        }
        $books = Book::withoutGlobalScopes()
            ->with(['vendor', 'returnPolicyVersion', 'categories'])
            ->whereIn('id', $bookIds)
            ->get()
            ->keyBy('id');

        // BƯỚC 1: Kiểm tra E-book đã sở hữu
        $ebookIds = [];
        foreach ($items as $item) {
            $bookId = $item['book_id'];
            if ($books->has($bookId) && $books->get($bookId)->isEbook()) {
                $ebookIds[] = $bookId;
            }
        }

        if (! empty($ebookIds)) {
            if ($digitalConsent !== null && ! ($digitalConsent['accepted'] ?? false)) {
                throw ValidationException::withMessages([
                    'ebook_terms_accepted' => 'Bạn phải đồng ý điều khoản nội dung số: ebook không được trả lại sau khi mua.',
                ]);
            }
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
        $promotionPricing = app(FlashSalePricingService::class);
        $ebookVersions = app(EbookVersionService::class);
        $commercialParties = app(CommercialPartyService::class);
        foreach ($items as $item) {
            $bookId = $item['book_id'];
            $quantity = $item['quantity'];

            if (! $books->has($bookId)) {
                throw new Exception("Sản phẩm không tồn tại (ID: {$bookId})");
            }
            $book = $books->get($bookId);
            if (! $book->isPublished()) {
                throw new Exception("Sản phẩm chưa được xuất bản (ID: {$bookId})");
            }
            if (! $book->vendor || ! $book->vendor->isActive()) {
                throw new Exception("Nhà bán của sản phẩm đang ngừng hoạt động (ID: {$bookId})");
            }
            $vendorId = $book->vendor_id;

            if (! isset($groupedItems[$vendorId])) {
                $groupedItems[$vendorId] = [];
            }

            $pricing = $promotionPricing->resolve($book, (int) $quantity);
            $price = $pricing['unit_price'];
            $subtotal = $price * $quantity;
            $totalCartAmount += $subtotal;

            $groupedItems[$vendorId][] = [
                'book_id' => $bookId,
                'quantity' => $quantity,
                'price' => $price,
                'list_unit_price' => $pricing['list_unit_price'],
                'promotion_discount_amount' => $pricing['promotion_discount_amount'],
                'flash_sale_book_id' => $pricing['flash_sale_book_id'],
                'promotion_snapshot' => $pricing['promotion_snapshot'],
                'ebook_version_id' => $book->isEbook()
                    ? $ebookVersions->currentOrCreate($book)->id
                    : null,
                'product_taxonomy_snapshot' => [
                    'format' => $book->format ?? $book->type,
                    'provenance' => $book->provenance,
                    'condition' => $book->condition,
                    'fulfillment_mode' => $book->fulfillment_mode,
                ],
                'commercial_parties_snapshot' => $commercialParties->snapshot($book),
                'return_policy_snapshot' => $book->returnPolicyVersion
                    ? [
                        'id' => $book->returnPolicyVersion->id,
                        'policy_key' => $book->returnPolicyVersion->policy_key,
                        'version' => $book->returnPolicyVersion->version,
                        'is_returnable' => $book->returnPolicyVersion->is_returnable,
                        'return_window_days' => $book->returnPolicyVersion->return_window_days,
                        'terms' => $book->returnPolicyVersion->terms,
                    ]
                    : null,
                'ebook_consent_snapshot' => $book->isEbook() && $digitalConsent !== null
                    ? [
                        ...$digitalConsent,
                        'policy_key' => $book->returnPolicyVersion?->policy_key,
                        'policy_version' => $book->returnPolicyVersion?->version,
                        'non_returnable' => true,
                    ]
                    : null,
            ];
        }

        // BƯỚC 3: Xác thực & Tính toán giảm giá Coupon
        $shippingPricing = app(ShippingPricingPolicy::class);
        if ($couponCode) {
            foreach ($groupedItems as $vendorItems) {
                foreach ($vendorItems as $vendorItem) {
                    if (($vendorItem['promotion_snapshot']['coupon_stacking_policy'] ?? null) === 'deny') {
                        throw ValidationException::withMessages(['coupon' => 'This coupon cannot be stacked with the active Flash Sale.']);
                    }
                }
            }
        }

        // BƯỚC 4: Xác định phương thức thanh toán
        $paymentMethod = 'cod';
        if (isset($shippingData['payment_method'])) {
            $pm = strtolower($shippingData['payment_method']);
            if (in_array($pm, ['vnpay', 'demo_wallet', 'online'], true)) {
                $paymentMethod = 'online';
            }
        }
        $isCod = ($paymentMethod === 'cod');

        // BƯỚC 5: Tính toán snapshot tài chính cho từng vendor & session
        $user = User::with('membershipTier')->find($userId);
        $feeService = app(CommerceFeeService::class);
        $feeSchedule = $feeService->effective();

        // BƯỚC 6: DB Transaction - Create Session, Orders, Items, Links, và Inventory Reservations
        $createdOrders = [];

        try {
            DB::beginTransaction();

            [$coupon, $vendorDiscounts, $vendorShippingDiscounts] = $this->resolveLockedCouponPricing(
                $couponCode,
                $totalCartAmount,
                $groupedItems,
                $books,
                $shippingPricing,
            );
            [$vendorSnapshots, $sessionSubtotal, $sessionDiscount, $sessionFee, $sessionTotal] = $this->buildVendorSnapshots(
                $groupedItems,
                $books,
                $vendorDiscounts,
                $vendorShippingDiscounts,
                $totalCartAmount,
                $user,
                $feeService,
                $feeSchedule,
                $shippingPricing,
            );

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

                $invoiceLineItems = [];
                foreach ($vendorItems as $item) {
                    $orderItem = new OrderItem([
                        'order_id' => $order->id,
                        'book_id' => $item['book_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'list_unit_price' => $item['list_unit_price'],
                        'promotion_discount_amount' => $item['promotion_discount_amount'],
                        'flash_sale_book_id' => $item['flash_sale_book_id'],
                        'promotion_snapshot' => $item['promotion_snapshot'],
                        'ebook_version_id' => $item['ebook_version_id'],
                        'product_taxonomy_snapshot' => $item['product_taxonomy_snapshot'],
                        'commercial_parties_snapshot' => $item['commercial_parties_snapshot'],
                        'return_policy_snapshot' => $item['return_policy_snapshot'],
                        'commercial_parties_snapshot' => $item['commercial_parties_snapshot'],
                        'ebook_consent_snapshot' => $item['ebook_consent_snapshot'],
                    ]);
                    $orderItem->saveQuietly();
                    if ($item['flash_sale_book_id']) {
                        $promotionPricing->reserve($item['flash_sale_book_id'], (int) $item['quantity']);
                    }
                    $book = $books->get($item['book_id']);
                    $invoiceLineItems[] = [
                        'order_item_id' => $orderItem->id,
                        'book_id' => $item['book_id'],
                        'title' => $book?->title,
                        'type' => $book?->type,
                        'provenance' => $book?->provenance,
                        'condition' => $book?->condition,
                        'return_policy_snapshot' => $item['return_policy_snapshot'],
                        'quantity' => (int) $item['quantity'],
                        'unit_price' => (int) $item['price'],
                        'list_unit_price' => (int) $item['list_unit_price'],
                        'promotion_discount_amount' => (int) $item['promotion_discount_amount'],
                        'promotion_snapshot' => $item['promotion_snapshot'],
                        'line_total' => (int) $item['price'] * (int) $item['quantity'],
                    ];
                }

                $vendor = Vendor::withoutGlobalScopes()->with('user')->findOrFail($vendorId);
                InvoiceSnapshot::create([
                    'order_id' => $order->id,
                    'invoice_number' => 'INV-'.$order->order_code,
                    'currency' => 'VND',
                    'issued_at' => now(),
                    'buyer_snapshot' => [
                        'user_id' => $user?->id,
                        'name' => $user?->name,
                        'email' => $user?->email,
                        'phone' => $shippingData['phone'],
                        'shipping_address' => $shippingData['shipping_address'],
                    ],
                    'seller_snapshot' => [
                        'vendor_id' => $vendor->id,
                        'shop_name' => $vendor->shop_name,
                        'contact_name' => $vendor->user?->name,
                        'contact_email' => $vendor->user?->email,
                        'contact_phone' => $vendor->user?->phone,
                    ],
                    'line_items' => $invoiceLineItems,
                    'subtotal_amount' => $snapshot['subtotal_amount'],
                    'coupon_discount_amount' => $snapshot['coupon_discount_amount'],
                    'membership_discount_amount' => $snapshot['membership_discount_amount'],
                    'shipping_fee_amount' => $snapshot['shipping_fee_amount'],
                    'service_fee_amount' => $snapshot['fee_amount'],
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'total_amount' => $snapshot['total_amount'],
                ]);

                $checkoutSessionOrder = new CheckoutSessionOrder([
                    'checkout_session_id' => $checkoutSession->id,
                    'order_id' => $order->id,
                    'vendor_id' => $vendorId,
                    'coupon_id' => $coupon?->id,
                    'coupon_type' => $coupon?->coupon_type,
                    'coupon_code' => $coupon?->code,
                    'coupon_discount_amount' => $snapshot['coupon_discount_amount'],
                    'membership_discount_amount' => $snapshot['membership_discount_amount'],
                    'shipping_fee_amount' => $snapshot['shipping_fee_amount'],
                    'pricing_policy_snapshot' => $shippingPricing->snapshot(),
                    'commerce_fee_schedule_id' => $snapshot['fee_schedule_id'],
                    'subtotal_amount' => $snapshot['subtotal_amount'],
                    'discount_amount' => $snapshot['discount_amount'],
                    'fee_amount' => $snapshot['fee_amount'],
                    'service_fee_rate' => $snapshot['service_fee_rate'],
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
            if ($coupon && (array_sum($vendorDiscounts) + array_sum($vendorShippingDiscounts)) > 0) {
                $coupon->increment('used_count');
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        // BƯỚC 7: Post-Commit - Dispatch ProcessOrder cho đơn COD
        if ($isCod) {
            foreach ($createdOrders as $order) {
                ProcessOrder::dispatch($order->id);
            }
        }

        return $createdOrders;
    }

    /**
     * Giai đoạn 2: Xác nhận đơn COD (draft -> confirmed -> dispatch ProcessOrder)
     *
     * @param  int[]  $orderIds
     */
    /**
     * Revalidates the exact coupon row while it is locked by this checkout
     * transaction. A coupon quote is never trusted across this lock boundary.
     *
     * @return array{0:?Coupon,1:array<int,int>,2:array<int,int>}
     */
    private function resolveLockedCouponPricing(?string $couponCode, int $totalCartAmount, array $groupedItems, $books, ShippingPricingPolicy $shippingPricing): array
    {
        if (! $couponCode) {
            return [null, [], []];
        }

        $coupon = Coupon::where('code', $couponCode)->lockForUpdate()->first();
        if (! $coupon || $coupon->status !== 'active' || ! in_array($coupon->coupon_type, ['product', 'shipping'], true)) {
            throw ValidationException::withMessages(['coupon' => 'Coupon is unavailable or has an invalid type.']);
        }
        app(CouponPricingService::class)->validateCurrent($coupon, $totalCartAmount);
        $quote = app(CouponPricingService::class)->quote($coupon, $groupedItems, $books, $totalCartAmount, $shippingPricing);

        return [$coupon, $quote['product_discounts'], $quote['shipping_discounts']];
    }

    /** @return array{0:array<int,array<string,int|float>>,1:int,2:int,3:int,4:int} */
    private function buildVendorSnapshots(array $groupedItems, $books, array $vendorDiscounts, array $vendorShippingDiscounts, int $totalCartAmount, ?User $user, CommerceFeeService $feeService, array $feeSchedule, ShippingPricingPolicy $shippingPricing): array
    {
        $snapshots = [];
        $sessionSubtotal = $sessionDiscount = $sessionFee = $sessionTotal = 0;
        foreach ($groupedItems as $vendorId => $vendorItems) {
            $subtotal = (int) array_sum(array_map(fn ($item) => (int) $item['price'] * (int) $item['quantity'], $vendorItems));
            $hasPhysical = collect($vendorItems)->some(fn ($item) => ($books->get($item['book_id'])?->type ?? 'ebook') !== 'ebook');
            $productCouponDiscount = (int) ($vendorDiscounts[$vendorId] ?? 0);
            $shippingCouponDiscount = (int) ($vendorShippingDiscounts[$vendorId] ?? 0);
            $couponDiscount = $productCouponDiscount + $shippingCouponDiscount;
            $shippingFee = $shippingPricing->feeAfterDiscount($shippingPricing->baseFeeForVendor($hasPhysical, $totalCartAmount), $shippingCouponDiscount);
            $membershipDiscount = $user?->membershipTier?->discount_percent > 0
                ? (int) round((max(0, $subtotal - $productCouponDiscount) * (float) $user->membershipTier->discount_percent) / 100, 0, PHP_ROUND_HALF_UP)
                : 0;
            $discount = min($subtotal, $productCouponDiscount + $membershipDiscount);
            $fee = $feeService->calculate(max(0, $subtotal - $discount), $feeSchedule);
            $snapshots[$vendorId] = [
                'subtotal_amount' => $subtotal,
                'coupon_discount_amount' => $couponDiscount,
                'membership_discount_amount' => $membershipDiscount,
                'discount_amount' => $discount,
                'shipping_fee_amount' => $shippingFee,
                'fee_amount' => (int) $fee['service_fee_amount'],
                'total_amount' => (int) $fee['total_amount'] + $shippingFee,
                'fee_schedule_id' => $feeSchedule['id'],
                'service_fee_rate' => $fee['service_fee_rate'],
                'commission_rate' => $fee['commission_rate'],
                'commission_amount' => (int) $fee['commission_amount'],
            ];
            $sessionSubtotal += $subtotal;
            $sessionDiscount += $discount;
            $sessionFee += (int) $fee['service_fee_amount'];
            $sessionTotal += (int) $fee['total_amount'] + $shippingFee;
        }

        return [$snapshots, $sessionSubtotal, $sessionDiscount, $sessionFee, $sessionTotal];
    }

}

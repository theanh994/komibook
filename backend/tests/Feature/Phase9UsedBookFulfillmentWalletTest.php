<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\Category;
use App\Models\DemoWalletLedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProviderSetting;
use App\Models\SellerFulfillmentAddress;
use App\Models\UsedBookListing;
use App\Models\User;
use App\Services\DemoWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase9UsedBookFulfillmentWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_used_book_seller_can_only_advance_shipping_until_the_buyer_confirms_receipt(): void
    {
        Storage::fake('public');
        PaymentProviderSetting::create([
            'provider' => 'demo_wallet',
            'enabled_by_admin' => true,
            'mode' => 'demo',
            'reason' => 'Test',
        ]);

        // 1. Seller registers address and lists used book
        $seller = User::factory()->create(['role' => 'customer', 'name' => 'Người Bán Sách Cũ A']);
        SellerFulfillmentAddress::create([
            'user_id' => $seller->id,
            'recipient_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'address_line' => '123 Đường Sách Cũ',
            'province' => 'Hà Nội',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Sách Văn Học Cũ', 'slug' => 'sach-van-hoc-cu']);

        $resListing = $this->actingAs($seller)->postJson('/api/used-book-seller/listings', [
            'title' => 'Dế Mèn Phiêu Lưu Ký (Bản cũ 1995)',
            'author_name' => 'Tô Hoài',
            'category_id' => $category->id,
            'price' => 50000,
            'condition' => 'good',
            'defects' => 'Giấy hơi ngả vàng',
            'quantity' => 1,
            'actual_photos' => [UploadedFile::fake()->image('demen.jpg')],
            'authenticity_attested' => true,
        ])->assertCreated();

        $bookId = $resListing->json('data.book.id');
        $listing = UsedBookListing::where('book_id', $bookId)->firstOrFail();

        // 2. Admin approves
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listing->id}/approve")->assertOk();

        // 3. Buyer purchases used book
        $buyer = User::factory()->create(['role' => 'customer', 'name' => 'Người Mua Độc Giả']);
        $checkoutRes = $this->actingAs($buyer)->postJson('/api/checkout', [
            'items' => [['book_id' => $bookId, 'quantity' => 1]],
            'shipping_address' => '789 Phố Nguyễn Du, Hà Nội',
            'phone' => '0912345678',
            'payment_method' => 'DEMO_WALLET',
        ])->assertCreated();

        $orderId = $checkoutRes->json('data.0.id');

        // Fund buyer wallet & complete payment
        $wallets = app(DemoWalletService::class);
        $buyerAccount = $wallets->accountFor($buyer);
        $buyerAccount->update(['balance' => 200000]);

        $attemptRes = $this->actingAs($buyer)->postJson('/api/payments/demo_wallet/attempts', [
            'order_id' => $orderId,
        ])->assertCreated();

        $txId = $attemptRes->json('transaction_id');
        $this->actingAs($buyer)->postJson("/api/payments/demo_wallet/attempts/{$txId}/complete")->assertOk();

        // Ensure order is processed
        (new ProcessOrder($orderId))->handle();

        // The seller's catalog vendor can also have ordinary and mixed orders.
        // Those orders must never become visible or actionable through C2C seller APIs.
        $usedBook = Book::withoutGlobalScopes()->findOrFail($bookId);
        $ordinaryBook = Book::withoutGlobalScopes()->create([
            'vendor_id' => $usedBook->vendor_id,
            'category_id' => $category->id,
            'title' => 'Sách catalog thông thường',
            'slug' => 'ordinary-catalog-book',
            'author' => 'KomiBook',
            'type' => 'physical',
            'price' => 60000,
            'stock' => 5,
            'status' => 'published',
        ]);
        $ordinaryOrder = Order::create([
            'user_id' => $buyer->id,
            'vendor_id' => $usedBook->vendor_id,
            'total_amount' => 60000,
            'status' => 'shipped',
            'payment_status' => 'paid',
            'payment_method' => 'DEMO_WALLET',
            'shipping_address' => 'Ordinary order address',
            'phone' => '0912000001',
            'shipping_carrier' => 'Ordinary Carrier',
            'shipping_tracking_code' => 'ORDINARY-1',
            'shipping_status' => 'delivering',
        ]);
        OrderItem::create(['order_id' => $ordinaryOrder->id, 'book_id' => $ordinaryBook->id, 'quantity' => 1, 'price' => 60000]);

        $mixedOrder = Order::create([
            'user_id' => $buyer->id,
            'vendor_id' => $usedBook->vendor_id,
            'total_amount' => 110000,
            'status' => 'shipped',
            'payment_status' => 'paid',
            'payment_method' => 'DEMO_WALLET',
            'shipping_address' => 'Mixed order address',
            'phone' => '0912000002',
            'shipping_carrier' => 'Mixed Carrier',
            'shipping_tracking_code' => 'MIXED-1',
            'shipping_status' => 'delivering',
        ]);
        OrderItem::create(['order_id' => $mixedOrder->id, 'book_id' => $bookId, 'quantity' => 1, 'price' => 50000]);
        OrderItem::create(['order_id' => $mixedOrder->id, 'book_id' => $ordinaryBook->id, 'quantity' => 1, 'price' => 60000]);

        // 4. Seller views orders list on Kênh Bán Sách Cũ
        $ordersRes = $this->actingAs($seller)->getJson('/api/used-book-seller/orders')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $orderId)
            ->assertJsonPath('data.data.0.buyer.name', 'Người Mua Độc Giả')
            ->assertJsonPath('data.data.0.buyer.shipping_address', '789 Phố Nguyễn Du, Hà Nội');
        $listedOrderIds = collect($ordersRes->json('data.data'))->pluck('id')->all();
        $this->assertContains($orderId, $listedOrderIds);
        $this->assertNotContains($ordinaryOrder->id, $listedOrderIds);
        $this->assertNotContains($mixedOrder->id, $listedOrderIds);

        foreach ([$ordinaryOrder, $mixedOrder] as $nonUsedBookOrder) {
            $before = $nonUsedBookOrder->only(['status', 'shipping_status', 'shipping_carrier', 'shipping_tracking_code']);
            $this->actingAs($seller)->getJson("/api/used-book-seller/orders/{$nonUsedBookOrder->id}")->assertForbidden();
            $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$nonUsedBookOrder->id}/ship", [
                'shipping_carrier' => 'Blocked Carrier',
                'shipping_tracking_code' => 'BLOCKED-1',
            ])->assertForbidden();
            $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$nonUsedBookOrder->id}/advance-shipping")->assertForbidden();
            $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$nonUsedBookOrder->id}/confirm-delivered")->assertForbidden();
            $this->assertSame($before, $nonUsedBookOrder->fresh()->only(array_keys($before)));
            $this->assertSame(0, $this->vendorEarningCreditsFor($nonUsedBookOrder->id));
        }

        // Seller shipping actions fail closed until the order is actually shipped.
        $order = Order::findOrFail($orderId);
        $statusBeforeShipping = $order->status;
        $shippingBeforeShipping = $order->shipping_status;
        $sellerWallet = $wallets->accountFor($seller);
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/advance-shipping")
            ->assertUnprocessable();
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/confirm-delivered")
            ->assertUnprocessable();
        $this->assertSame($statusBeforeShipping, $order->fresh()->status);
        $this->assertSame($shippingBeforeShipping, $order->fresh()->shipping_status);
        $this->assertSame(0, (int) $sellerWallet->fresh()->balance);
        $this->assertSame(0, $this->vendorEarningCreditsFor($orderId));

        // A progressed shipping value cannot bypass the pre-shipment order-status gate.
        $order->forceFill(['shipping_status' => 'delivering'])->save();
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/advance-shipping")
            ->assertUnprocessable();
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/confirm-delivered")
            ->assertUnprocessable();
        $this->assertSame($statusBeforeShipping, $order->fresh()->status);
        $this->assertSame('delivering', $order->fresh()->shipping_status);
        $this->assertSame(0, (int) $sellerWallet->fresh()->balance);
        $this->assertSame(0, $this->vendorEarningCreditsFor($orderId));
        $order->forceFill(['shipping_status' => $shippingBeforeShipping])->save();

        // 5. Step 1: Seller confirms packing & assigns carrier/tracking code -> pending_pickup
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/ship", [
            'shipping_carrier' => 'KomiExpress C2C',
            'shipping_tracking_code' => 'KM-C2C-998877',
        ])->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.shipping_status', 'pending_pickup')
            ->assertJsonPath('data.shipping_carrier', 'KomiExpress C2C')
            ->assertJsonPath('data.shipping_tracking_code', 'KM-C2C-998877');
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/ship")
            ->assertOk()
            ->assertJsonPath('data.shipping_status', 'pending_pickup')
            ->assertJsonPath('data.shipping_carrier', 'KomiExpress C2C')
            ->assertJsonPath('data.shipping_tracking_code', 'KM-C2C-998877');

        $this->assertSame(0, (int) $sellerWallet->fresh()->balance);
        $this->assertSame(0, $this->vendorEarningCreditsFor($orderId));

        // A different used-book seller cannot move this seller's order.
        $otherSeller = User::factory()->create(['role' => 'customer']);
        $this->actingAs($otherSeller)->postJson("/api/used-book-seller/orders/{$orderId}/advance-shipping")
            ->assertForbidden();
        $this->assertSame('pending_pickup', Order::findOrFail($orderId)->shipping_status);

        // Failed and unknown shipping states fail closed and never release earnings.
        $order = Order::findOrFail($orderId);
        $order->forceFill(['shipping_status' => 'failed'])->save();
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/advance-shipping")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Không thể cập nhật trạng thái giao hàng của đơn hàng này.');
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(0, $this->vendorEarningCreditsFor($orderId));

        $order->forceFill(['shipping_status' => 'unknown'])->save();
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/confirm-delivered")
            ->assertUnprocessable();
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(0, $this->vendorEarningCreditsFor($orderId));
        $order->forceFill(['shipping_status' => 'pending_pickup'])->save();

        // Step 2: Shipper picks up package -> picked_up.
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/advance-shipping")
            ->assertOk()
            ->assertJsonPath('data.shipping_status', 'picked_up');
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/ship", [
            'shipping_carrier' => '',
            'shipping_tracking_code' => [],
        ])->assertOk()
            ->assertJsonPath('data.shipping_status', 'picked_up')
            ->assertJsonPath('data.shipping_carrier', 'KomiExpress C2C')
            ->assertJsonPath('data.shipping_tracking_code', 'KM-C2C-998877');

        // Step 3: In transit to buyer -> delivering
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/advance-shipping")
            ->assertOk()
            ->assertJsonPath('data.shipping_status', 'delivering');
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/ship", [
            'shipping_carrier' => [],
            'shipping_tracking_code' => '',
        ])->assertOk()
            ->assertJsonPath('data.shipping_status', 'delivering')
            ->assertJsonPath('data.shipping_carrier', 'KomiExpress C2C')
            ->assertJsonPath('data.shipping_tracking_code', 'KM-C2C-998877');

        // Step 4: Delivered to buyer address -> awaiting_customer_confirmation
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/advance-shipping")
            ->assertOk()
            ->assertJsonPath('data.shipping_status', 'awaiting_customer_confirmation');
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/ship")->assertOk()
            ->assertJsonPath('data.shipping_status', 'awaiting_customer_confirmation')
            ->assertJsonPath('data.shipping_carrier', 'KomiExpress C2C')
            ->assertJsonPath('data.shipping_tracking_code', 'KM-C2C-998877');

        // Seller arrival confirmation and retries are harmless and cannot complete the order.
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/confirm-delivered")
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.shipping_status', 'awaiting_customer_confirmation');
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/advance-shipping")
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.shipping_status', 'awaiting_customer_confirmation');
        $this->assertSame(0, (int) $sellerWallet->fresh()->balance);
        $this->assertSame(0, $this->vendorEarningCreditsFor($orderId));

        // Only the actual buyer can confirm receipt and release the seller earning.
        $otherBuyer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($otherBuyer)->postJson("/api/my-orders/{$orderId}/confirm-received", [
            'idempotency_key' => 'phase9-cross-buyer-confirm',
        ])->assertForbidden();
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(0, $this->vendorEarningCreditsFor($orderId));

        $this->actingAs($buyer)->postJson("/api/my-orders/{$orderId}/confirm-received", [
            'idempotency_key' => 'phase9-buyer-confirm',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.shipping_status', 'delivered');

        // The same buyer idempotency key does not duplicate the financial credit.
        $this->actingAs($buyer)->postJson("/api/my-orders/{$orderId}/confirm-received", [
            'idempotency_key' => 'phase9-buyer-confirm',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->assertSame(1, $this->vendorEarningCreditsFor($orderId));

        // Seller retries after completion cannot trigger another wallet mutation.
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/advance-shipping")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/confirm-delivered")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->actingAs($seller)->postJson("/api/used-book-seller/orders/{$orderId}/ship", [
            'shipping_carrier' => '',
            'shipping_tracking_code' => [],
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.shipping_status', 'delivered')
            ->assertJsonPath('data.shipping_carrier', 'KomiExpress C2C')
            ->assertJsonPath('data.shipping_tracking_code', 'KM-C2C-998877');

        // 7. Seller checks Ví KomiBook & verifies earnings
        $walletRes = $this->actingAs($seller)->getJson('/api/used-book-seller/wallet')
            ->assertOk();

        $sellerBalance = $walletRes->json('data.balance');
        $this->assertEquals(45000, $sellerBalance);
        $this->assertSame(1, $this->vendorEarningCreditsFor($orderId));
    }

    private function vendorEarningCreditsFor(int $orderId): int
    {
        return DemoWalletLedgerEntry::query()
            ->where('order_id', $orderId)
            ->where('entry_type', 'vendor_earning_credit')
            ->count();
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ProcessOrder;
use App\Models\Book;
use App\Models\Category;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentProviderSetting;
use App\Models\SellerFulfillmentAddress;
use App\Models\UsedBookListing;
use App\Models\User;
use App\Models\WarehouseStock;
use App\Services\DemoWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase8UsedBookCheckoutPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_used_book_checkout_payment_flow_and_sold_out_status_sync(): void
    {
        Storage::fake('public');
        PaymentProviderSetting::create([
            'provider' => 'demo_wallet',
            'enabled_by_admin' => true,
            'mode' => 'demo',
            'reason' => 'Test',
        ]);

        // 1. Seller registers address and lists used book
        $seller = User::factory()->create(['role' => 'customer', 'name' => 'Người Bán Sách Cũ']);
        SellerFulfillmentAddress::create([
            'user_id' => $seller->id,
            'recipient_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'address_line' => '123 Đường Sách Cũ',
            'province' => 'Hà Nội',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Sách Cũ Hiếm', 'slug' => 'sach-cu-hiem']);

        $response = $this->actingAs($seller)->postJson('/api/used-book-seller/listings', [
            'title' => 'Komi Nữ Thần Sợ Giao Tiếp Tập 31 (Bản cũ)',
            'author_name' => 'Tomohito Oda',
            'category_id' => $category->id,
            'price' => 45000,
            'condition' => 'like_new',
            'defects' => 'Chỉ đọc 1 lần, mới 99%',
            'quantity' => 1,
            'actual_photos' => [UploadedFile::fake()->image('komi31.jpg')],
            'authenticity_attested' => true,
        ])->assertCreated();

        $bookId = $response->json('data.book.id');
        $listing = UsedBookListing::where('book_id', $bookId)->firstOrFail();
        $this->assertEquals('pending', $listing->status);

        // 2. Admin approves used book listing
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson("/api/admin/used-book-listings/{$listing->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $book = Book::withoutGlobalScopes()->findOrFail($bookId);
        $this->assertEquals('published', $book->status);

        // Verify WarehouseStock was created for used book
        $stock = WarehouseStock::where('book_id', $bookId)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(1, $stock->quantity);

        // 3. Buyer performs checkout with Demo Wallet
        $buyer = User::factory()->create(['role' => 'customer', 'name' => 'Người Mua']);
        $checkoutRes = $this->actingAs($buyer)->postJson('/api/checkout', [
            'items' => [
                ['book_id' => $bookId, 'quantity' => 1],
            ],
            'shipping_address' => '456 Phố Mua Sách, Hà Nội',
            'phone' => '0987654321',
            'payment_method' => 'DEMO_WALLET',
        ])->assertCreated();

        $orderId = $checkoutRes->json('data.0.id');
        $this->assertNotNull($orderId);

        $order = Order::withoutGlobalScopes()->findOrFail($orderId);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('unpaid', $order->payment_status);

        // Verify inventory reservation was created
        $reservation = InventoryReservation::where('book_id', $bookId)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(1, $reservation->quantity);

        // A live reservation is an on-hand floor: the seller cannot consume it,
        // and the same reservation must still be able to complete payment.
        $inventoryBefore = [
            (int) $stock->fresh()->quantity,
            (int) $book->fresh()->stock,
            $listing->fresh()->only(['quantity_available', 'status', 'warehouse_id']),
        ];
        $this->actingAs($seller)->patchJson("/api/used-book-seller/listings/{$listing->id}/inventory", ['quantity_available' => 0])->assertStatus(422);
        $this->assertSame($inventoryBefore, [
            (int) $stock->fresh()->quantity,
            (int) $book->fresh()->stock,
            $listing->fresh()->only(['quantity_available', 'status', 'warehouse_id']),
        ]);
        $this->actingAs($seller)->patchJson("/api/used-book-seller/listings/{$listing->id}/inventory", ['quantity_available' => 1])->assertOk();

        // Credit Demo Wallet for buyer
        $wallets = app(DemoWalletService::class);
        $account = $wallets->accountFor($buyer);
        $account->update(['balance' => 100000]);

        // 4. Initiate Demo Wallet Payment & Complete Transaction
        $attemptRes = $this->actingAs($buyer)->postJson('/api/payments/demo_wallet/attempts', [
            'order_id' => $orderId,
        ])->assertCreated();

        $transactionId = $attemptRes->json('transaction_id');
        $this->assertNotNull($transactionId);

        $this->actingAs($buyer)->postJson("/api/payments/demo_wallet/attempts/{$transactionId}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // 5. Verify payment completed and execute ProcessOrder job to commit reservation
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        (new ProcessOrder($order->id))->handle();

        $stock->refresh();
        $this->assertEquals(0, $stock->quantity);

        $book->refresh();
        $this->assertEquals(0, $book->stock);

        $listing->refresh();
        $this->assertEquals(0, $listing->quantity_available);
        $this->assertEquals('sold_out', $listing->status);
    }
}

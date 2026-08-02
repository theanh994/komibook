<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSessionOrder;
use App\Models\DemoWalletAccount;
use App\Models\PaymentProviderSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEarningLedger;
use App\Models\VendorTaxSchedule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\CheckoutService;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentAndVendorTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_capabilities_only_expose_configured_vnpay_and_demo_wallet_without_removed_providers(): void
    {
        config()->set('services.vnpay.tmn_code', 'LOCAL_TEST_MERCHANT');
        config()->set('services.vnpay.hash_secret', 'local-test-secret');
        config()->set('payment_providers.providers.vnpay.mode', 'sandbox');

        $response = $this->getJson('/api/payment-providers')->assertOk();

        $this->assertTrue(collect($response->json('data'))->contains(
            fn (array $provider): bool => $provider['id'] === 'vnpay'
                && $provider['available'] === true
                && $provider['mode'] === 'sandbox'
                && $provider['name'] === 'VNPAY Sandbox'
        ));
        $this->assertFalse(collect($response->json('data'))->contains(
            fn (array $provider): bool => in_array($provider['id'], ['payos', 'momo'], true)
        ));
    }

    public function test_admin_can_only_enable_no_cost_demo_modes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/payment-providers/demo_wallet', [
            'enabled' => true,
            'mode' => 'live',
            'reason' => 'Không được phép',
        ])->assertUnprocessable();

        $this->putJson('/api/admin/payment-providers/demo_wallet', [
            'enabled' => true,
            'mode' => 'demo',
            'reason' => 'Kiểm thử nội bộ không phát sinh phí',
        ])->assertOk()
            ->assertJsonPath('data.mode', 'demo')
            ->assertJsonPath('data.available', true);

        $this->assertDatabaseHas('payment_provider_settings', [
            'provider' => 'demo_wallet',
            'mode' => 'demo',
            'enabled_by_admin' => true,
        ]);
    }

    public function test_enable_demo_command_removes_retired_providers_and_enables_only_sandbox_and_wallet(): void
    {
        PaymentProviderSetting::create(['provider' => 'momo', 'enabled_by_admin' => true, 'mode' => 'demo']);
        PaymentProviderSetting::create(['provider' => 'payos', 'enabled_by_admin' => true, 'mode' => 'demo']);

        $this->artisan('payments:enable-demo')->assertSuccessful();

        $this->assertDatabaseMissing('payment_provider_settings', ['provider' => 'momo']);
        $this->assertDatabaseMissing('payment_provider_settings', ['provider' => 'payos']);
        $this->assertDatabaseHas('payment_provider_settings', [
            'provider' => 'vnpay', 'enabled_by_admin' => true, 'mode' => 'sandbox',
        ]);
        $this->assertDatabaseHas('payment_provider_settings', [
            'provider' => 'demo_wallet', 'enabled_by_admin' => true, 'mode' => 'demo',
        ]);
    }

    public function test_demo_wallet_payment_is_internal_and_idempotent(): void
    {
        Queue::fake();
        PaymentProviderSetting::create([
            'provider' => 'demo_wallet',
            'enabled_by_admin' => true,
            'mode' => 'demo',
            'reason' => 'Kiểm thử',
        ]);
        [$buyer, $vendor, $book] = $this->commerceFixture(true);
        $orders = (new CheckoutService)->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Hà Nội', 'phone' => '0900000000', 'payment_method' => 'demo_wallet'],
            $buyer->id
        );
        Sanctum::actingAs($buyer);

        $attempt = $this->postJson('/api/payments/demo_wallet/attempts', ['order_id' => $orders[0]->id])
            ->assertCreated()
            ->assertJsonPath('internal_wallet', true)
            ->json();
        DemoWalletAccount::where('user_id', $buyer->id)->update(['balance' => 1_000_000]);
        $before = DemoWalletAccount::where('user_id', $buyer->id)->value('balance');

        $url = "/api/payments/demo_wallet/attempts/{$attempt['transaction_id']}/complete";
        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertOk();

        $this->assertSame($before - (int) $attempt['amount'], (int) DemoWalletAccount::where('user_id', $buyer->id)->value('balance'));
        $this->assertDatabaseCount('demo_wallet_ledger_entries', 1);
        $this->assertDatabaseHas('orders', ['id' => $orders[0]->id, 'payment_status' => 'paid', 'status' => 'confirmed']);
    }

    public function test_vnpay_cannot_be_completed_by_the_internal_simulation_endpoint(): void
    {
        Queue::fake();
        config()->set('services.vnpay.tmn_code', 'LOCAL_TEST_MERCHANT');
        config()->set('services.vnpay.hash_secret', 'local-test-secret');
        config()->set('payment_providers.providers.vnpay.mode', 'sandbox');
        PaymentProviderSetting::create([
            'provider' => 'vnpay',
            'enabled_by_admin' => true,
            'mode' => 'sandbox',
            'reason' => 'Kiểm thử VNPAY Sandbox',
        ]);
        [$buyer, $vendor, $book] = $this->commerceFixture(false);
        $orders = (new CheckoutService)->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Hà Nội', 'phone' => '0900000000', 'payment_method' => 'vnpay'],
            $buyer->id
        );
        Sanctum::actingAs($buyer);

        $this->postJson('/api/payments/vnpay/attempts', ['order_id' => $orders[0]->id])
            ->assertServiceUnavailable();

        $this->assertDatabaseMissing('payment_transactions', ['provider' => 'vnpay']);
        $this->assertDatabaseHas('orders', ['id' => $orders[0]->id, 'payment_status' => 'unpaid']);
    }

    public function test_new_vendor_earnings_do_not_calculate_or_withhold_tax(): void
    {
        Queue::fake();
        [$buyer, $vendor, $book] = $this->commerceFixture(false, 120_000);
        VendorTaxSchedule::create([
            'tax_year' => now()->year,
            'effective_at' => now()->subMinute(),
            'brackets' => [
                ['up_to' => 100_000, 'rate_bps' => 1000],
                ['up_to' => null, 'rate_bps' => 2000],
            ],
            'reason' => 'Biểu thuế kiểm thử',
            'operation_key' => 'tax-test-'.uniqid(),
        ]);
        $orders = (new CheckoutService)->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['shipping_address' => 'Hà Nội', 'phone' => '0900000000', 'payment_method' => 'cod'],
            $buyer->id
        );
        $order = $orders[0];
        $order->status = 'processing';
        $order->save();
        $fulfillment = new OrderFulfillmentService;
        $fulfillment->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $vendor->user_id);
        $fulfillment->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TAX-1', 'vendor', $vendor->user_id);
        $fulfillment->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TAX-1', 'vendor', $vendor->user_id);
        $fulfillment->updateShippingStatus($order->id, 'awaiting_customer_confirmation', 'GHTK', 'TAX-1', 'vendor', $vendor->user_id);
        $fulfillment->confirmReceivedByCustomer($order->id, (int) $buyer->id);

        $snapshot = CheckoutSessionOrder::where('order_id', $order->id)->firstOrFail();
        $earning = VendorEarningLedger::where('order_id', $order->id)->firstOrFail();

        $this->assertDatabaseMissing('vendor_tax_ledger_entries', ['order_id' => $order->id]);
        $this->assertSame(0, (int) $earning->tax_amount);
        $this->assertSame((int) $earning->gross_amount - (int) $earning->commission_amount, (int) $earning->net_amount);
        $this->assertSame((int) $earning->net_amount, (int) $vendor->fresh()->balance);
        $this->assertDatabaseHas('demo_wallet_ledger_entries', [
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'entry_type' => 'vendor_earning_credit',
            'amount' => $earning->net_amount,
        ]);
    }

    /** @return array{User, Vendor, Book} */
    private function commerceFixture(bool $demoVendor, int $price = 100_000): array
    {
        $buyer = User::factory()->create();
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Nhà bán '.uniqid(),
            'slug' => 'vendor-'.uniqid(),
            'status' => 'active',
            'is_demo' => $demoVendor,
            'balance' => 0,
        ]);
        $category = Category::create(['name' => 'Danh mục '.uniqid(), 'slug' => 'category-'.uniqid()]);
        $book = Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách '.uniqid(),
            'slug' => 'book-'.uniqid(),
            'author' => 'Tác giả',
            'price' => $price,
            'stock' => 10,
            'type' => 'physical',
            'status' => 'published',
        ]);
        $warehouse = Warehouse::create([
            'vendor_id' => $vendor->id,
            'name' => 'Kho chính',
            'address' => 'Hà Nội',
            'capacity' => 1000,
            'status' => 'active',
        ]);
        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'book_id' => $book->id, 'quantity' => 10]);

        return [$buyer, $vendor, $book];
    }
}

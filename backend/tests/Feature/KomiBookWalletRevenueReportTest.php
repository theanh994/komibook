<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEarningLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KomiBookWalletRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_report_requires_an_explicit_completed_run_and_does_not_write_legacy_snapshots(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/finance-report')->assertOk()
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('reason', 'no_completed_run');

        $this->assertNull($response->json('data'));
        $this->assertDatabaseCount('revenue_report_snapshots', 0);
    }

    public function test_vendor_can_filter_revenue_by_month_and_new_earnings_have_zero_tax(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $customer = User::factory()->create();
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Nhà bán báo cáo',
            'slug' => 'nha-ban-bao-cao',
            'status' => 'active',
        ]);
        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'code' => 'REPORT-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'shipping_fee' => 0,
            'discount_amount' => 0,
            'shipping_address' => 'Hà Nội',
            'phone' => '0900000000',
        ]);
        VendorEarningLedger::create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'operation_key' => 'report-earning-1',
            'gross_amount' => 100000,
            'commission_amount' => 10000,
            'tax_amount' => 0,
            'net_amount' => 90000,
            'currency' => 'VND',
        ]);
        Sanctum::actingAs($vendorUser);

        $this->getJson('/api/vendor/finance/revenue?granularity=month&period='.now()->format('Y-m'))
            ->assertOk()
            ->assertJsonPath('data.summary.gross_revenue', 100000)
            ->assertJsonPath('data.summary.net_revenue', 90000)
            ->assertJsonPath('data.summary.tax_withheld', 0)
            ->assertJsonCount(1, 'data.entries');
    }
}

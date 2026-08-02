<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase7FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_finance_report_is_portable_and_returns_a_complete_twenty_four_month_series(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');

        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Komi Finance',
            'slug' => 'komi-finance',
            'status' => 'active',
            'onboarding_status' => 'approved',
        ]);

        $this->createOrder($customer, $vendor, 120000, 'completed', 'online', now());
        $this->createOrder($customer, $vendor, 80000, 'completed', 'cod', now()->subMonthsNoOverflow(2));
        $this->createOrder($customer, $vendor, 30000, 'processing', 'cod', now());
        $this->createOrder($customer, $vendor, 40000, 'completed', 'cod', now()->subMonthsNoOverflow(13));

        foreach ([
            ['pending', 10000],
            ['approved', 20000],
            ['processing', 30000],
            ['completed', 40000],
            ['rejected', 50000],
        ] as [$status, $amount]) {
            PayoutRequest::withoutGlobalScopes()->create([
                'vendor_id' => $vendor->id,
                'amount' => $amount,
                'bank_name' => 'Komi Bank',
                'account_number' => '***',
                'account_name' => 'Komi Finance',
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/admin/finance-report');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kpi.total_revenue', 240000)
            ->assertJsonPath('data.kpi.monthly_revenue', 120000)
            ->assertJsonPath('data.kpi.total_orders', 4)
            ->assertJsonPath('data.kpi.completed_orders', 3)
            ->assertJsonPath('data.kpi.avg_order_value', 80000)
            ->assertJsonPath('data.payout_stats.pending', 10000)
            ->assertJsonPath('data.payout_stats.approved', 90000)
            ->assertJsonPath('data.payout_stats.rejected', 50000)
            ->assertJsonCount(24, 'data.revenue_by_month')
            ->assertJsonPath('data.revenue_by_month.0.month', '2024-08')
            ->assertJsonPath('data.revenue_by_month.0.revenue', 0)
            ->assertJsonPath('data.revenue_by_month.21.month', '2026-05')
            ->assertJsonPath('data.revenue_by_month.21.revenue', 80000)
            ->assertJsonPath('data.revenue_by_month.23.month', '2026-07')
            ->assertJsonPath('data.revenue_by_month.23.revenue', 120000)
            ->assertJsonPath('data.top_vendors.0.shop_name', 'Komi Finance')
            ->assertJsonPath('data.top_vendors.0.revenue', 240000);

        $methods = collect($response->json('data.revenue_by_payment_method'))->keyBy('payment_method');
        $this->assertSame(120000, (int) $methods->get('cod')['revenue']);
        $this->assertSame(120000, (int) $methods->get('online')['revenue']);
    }

    public function test_customer_cannot_access_finance_report(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->getJson('/api/admin/finance-report')->assertForbidden();
    }

    private function createOrder(
        User $customer,
        Vendor $vendor,
        int $amount,
        string $status,
        string $paymentMethod,
        Carbon $createdAt,
    ): Order {
        $order = Order::withoutGlobalScopes()->create([
            'order_code' => 'P7-FIN-'.str()->upper(str()->random(10)),
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total_amount' => $amount,
            'status' => $status,
            'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
            'payment_method' => $paymentMethod,
            'shipping_address' => 'KomiBook test',
            'phone' => '0900000000',
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $order;
    }
}

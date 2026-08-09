<?php

namespace Tests\Feature;

use App\Models\RevenueReportRun;
use App\Models\User;
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

    public function test_report_is_unavailable_until_an_admin_explicitly_refreshes_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/api/admin/finance-report')
            ->assertOk()
            ->assertExactJson(['status' => 'unavailable', 'data' => null, 'reason' => 'no_completed_run']);
        $this->assertDatabaseCount('revenue_report_runs', 0);
    }

    public function test_refresh_publishes_exactly_twenty_four_months_and_replays_by_key(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $request = ['reason' => 'monthly finance close', 'idempotency_key' => 'phase7-finance-1'];

        $first = $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/api/admin/finance-report/refresh', $request)
            ->assertOk()->assertJsonPath('status', 'success')->assertJsonCount(24, 'data.revenue_by_month');
        $runId = $first->json('run.id');

        $this->assertDatabaseCount('revenue_report_runs', 1);
        $this->assertDatabaseHas('revenue_report_runs', ['public_id' => $runId, 'status' => RevenueReportRun::COMPLETED]);
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/api/admin/finance-report/refresh', $request)
            ->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('run.id', $runId);
        $this->assertDatabaseCount('revenue_report_runs', 1);
    }

    public function test_customer_cannot_access_finance_report(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->getJson('/api/admin/finance-report')->assertForbidden();
    }
}

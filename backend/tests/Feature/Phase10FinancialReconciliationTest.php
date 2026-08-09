<?php

namespace Tests\Feature;

use App\Models\RevenueReportRun;
use App\Models\User;
use App\Services\RevenueReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase10FinancialReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_publishes_an_immutable_run_without_a_fabricated_commission_or_platform_net(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $result = app(RevenueReportService::class)->refreshLast24Months($admin, 'phase10-reconcile', 'reconciliation close');

        $run = $result['run'];
        $this->assertSame(RevenueReportRun::COMPLETED, $run->status);
        $this->assertCount(24, $run->payload['revenue_by_month']);
        $this->assertNull($run->payload['kpi']['commission_rate']);
        $this->assertNull($run->payload['kpi']['platform_net_retention']);
        $this->assertSame([], $run->payload['revenue_by_payment_method']);
        $this->assertDatabaseCount('revenue_report_snapshots', 0);
    }
}

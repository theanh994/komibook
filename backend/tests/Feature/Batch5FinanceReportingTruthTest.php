<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\InvoiceSnapshot;
use App\Models\Order;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use App\Models\RevenueReportRun;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEarningLedger;
use App\Services\RevenueReportRequestConflict;
use App\Services\RevenueReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch5FinanceReportingTruthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_get_and_export_are_non_mutating_when_no_completed_run_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/admin/finance-report')
            ->assertOk()->assertJsonPath('status', 'unavailable');
        $this->actingAs($admin)->get('/api/admin/finance-report/export')
            ->assertStatus(409)->assertJsonPath('reason', 'no_completed_run');
        $this->assertDatabaseCount('revenue_report_runs', 0);
    }

    public function test_refresh_requires_reason_and_key_and_rejects_changed_fingerprint(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $session = ['auth.password_confirmed_at' => time()];
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession($session)->postJson('/api/admin/finance-report/refresh', [])->assertUnprocessable();
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession($session)->postJson('/api/admin/finance-report/refresh', ['reason' => 'close'])->assertUnprocessable();
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession($session)->postJson('/api/admin/finance-report/refresh', ['reason' => 'close', 'idempotency_key' => 'batch5-1'])->assertOk();
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession($session)->postJson('/api/admin/finance-report/refresh', ['reason' => 'different', 'idempotency_key' => 'batch5-1'])->assertUnprocessable();
        $this->assertDatabaseCount('revenue_report_runs', 1);
    }

    public function test_completed_payload_has_twenty_four_immutable_months_and_never_a_fixed_rate(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/api/admin/finance-report/refresh', ['reason' => 'close', 'idempotency_key' => 'batch5-2'])
            ->assertOk()->assertJsonCount(24, 'data.revenue_by_month')
            ->assertJsonPath('data.kpi.commission_rate', null)
            ->assertJsonPath('data.kpi.platform_net_retention', null)
            ->assertJsonPath('data.revenue_by_payment_method', []);
        $run = RevenueReportRun::firstOrFail();
        $this->assertSame('complete', $run->quality['status']);
        $this->assertSame('2024-09', $run->payload['revenue_by_month'][0]['month']);
        $this->assertSame('2026-08', $run->payload['revenue_by_month'][23]['month']);
    }

    public function test_revenue_report_run_migration_down_and_up_are_isolated(): void
    {
        $migration = require database_path('migrations/2026_08_09_000004_create_revenue_report_runs.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('revenue_report_runs'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('revenue_report_runs'));
    }

    public function test_active_slot_returns_conflict_while_same_key_running_and_failed_replays_are_truthful(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $session = ['auth.password_confirmed_at' => time()];
        $this->createRun($admin, 'other-active', 'other', RevenueReportRun::RUNNING, 'admin-finance-24-months');
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession($session)
            ->postJson('/api/admin/finance-report/refresh', ['reason' => 'close', 'idempotency_key' => 'new-key'])
            ->assertConflict()->assertJsonPath('message', 'refresh_in_progress');

        DB::table('revenue_report_runs')->delete();
        $running = $this->createRun($admin, 'running-key', 'close', RevenueReportRun::RUNNING, 'admin-finance-24-months');
        $runningUpdatedAt = $running->updated_at?->toISOString();
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession($session)
            ->postJson('/api/admin/finance-report/refresh', ['reason' => 'close', 'idempotency_key' => 'running-key'])
            ->assertStatus(202)->assertJsonPath('status', 'running')->assertJsonPath('data', null);
        $running = $running->fresh();
        $this->assertSame(RevenueReportRun::RUNNING, $running->status);
        $this->assertNull($running->payload);
        $this->assertNull($running->completed_at);
        $this->assertSame($runningUpdatedAt, $running->updated_at?->toISOString());

        DB::table('revenue_report_runs')->delete();
        $this->createRun($admin, 'failed-key', 'close', RevenueReportRun::FAILED, null, 'source_integrity');
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession($session)
            ->postJson('/api/admin/finance-report/refresh', ['reason' => 'close', 'idempotency_key' => 'failed-key'])
            ->assertUnprocessable()->assertJsonPath('status', 'failed')->assertJsonPath('failure_code', 'source_integrity');
    }

    public function test_canonical_earnings_use_dynamic_commission_and_explicit_components(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        [$buyer, $vendor] = $this->parties();
        $this->canonicalEarning($buyer, $vendor, Carbon::parse('2026-07-10 10:00:00'), 100000, 80000, 10000, 10000, 0);
        $this->canonicalEarning($buyer, $vendor, Carbon::parse('2026-08-02 10:00:00'), 200000, 160000, 20000, 20000, 25000);

        $run = app(RevenueReportService::class)->refreshLast24Months($admin, 'canonical-rates', 'close')['run'];
        $july = collect($run->payload['revenue_by_month'])->firstWhere('month', '2026-07');
        $august = collect($run->payload['revenue_by_month'])->firstWhere('month', '2026-08');
        $this->assertSame(100000, $july['gross_revenue']);
        $this->assertSame(0, $july['commission_amount']);
        $this->assertSame(200000, $august['gross_revenue']);
        $this->assertSame(25000, $august['commission_amount']);
        $this->assertSame(240000, $run->payload['kpi']['merchandise_revenue']);
        $this->assertSame(30000, $run->payload['kpi']['shipping_revenue']);
        $this->assertSame(30000, $run->payload['kpi']['service_fee_revenue']);
        $this->assertSame(300000, $run->payload['kpi']['gross_revenue']);
        $this->assertSame(25000, $run->payload['kpi']['total_commission']);
        $this->assertSame(275000, $run->payload['kpi']['total_vendor_net']);
    }

    public function test_legacy_evidence_is_partial_and_contradiction_fails_without_replacing_prior_run(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        [$buyer, $vendor] = $this->parties();
        $order = $this->canonicalEarning($buyer, $vendor, now(), 100000, 80000, 10000, 10000, 0);
        $first = app(RevenueReportService::class)->refreshLast24Months($admin, 'prior-good', 'close')['run'];
        $payloadHash = hash('sha256', json_encode($first->payload, JSON_THROW_ON_ERROR));
        DB::table('checkout_session_orders')->where('order_id', $order->id)->update(['total_amount' => 99999]);

        try {
            app(RevenueReportService::class)->refreshLast24Months($admin, 'bad-proof', 'close');
            $this->fail('Expected contradictory immutable evidence to fail.');
        } catch (\Throwable) {
            // The attempt is terminalized safely; details never become response data.
        }
        $failed = RevenueReportRun::query()->where('operation_key', 'bad-proof')->firstOrFail();
        $this->assertSame(RevenueReportRun::FAILED, $failed->status);
        $this->assertNull($failed->payload);
        $this->assertSame('source_integrity', $failed->failure_code);
        $this->assertSame($first->public_id, app(RevenueReportService::class)->latestCompletedRun()?->public_id);
        $this->assertSame($payloadHash, hash('sha256', json_encode($first->fresh()->payload, JSON_THROW_ON_ERROR)));
    }

    public function test_explicit_zero_is_complete_but_missing_checkout_is_unknown(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        [$buyer, $vendor] = $this->parties();
        $this->canonicalEarning($buyer, $vendor, now(), 100000, 100000, 0, 0, 0);
        $legacy = Order::withoutGlobalScopes()->create(['order_code' => 'LEGACY-'.Str::random(8), 'user_id' => $buyer->id, 'vendor_id' => $vendor->id, 'total_amount' => 999999, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cod', 'shipping_address' => 'test', 'phone' => '0900000000']);
        VendorEarningLedger::create(['vendor_id' => $vendor->id, 'order_id' => $legacy->id, 'operation_key' => 'legacy-'.$legacy->id, 'gross_amount' => 50000, 'commission_amount' => 6250, 'tax_amount' => 0, 'net_amount' => 43750, 'currency' => 'VND']);

        $run = app(RevenueReportService::class)->refreshLast24Months($admin, 'legacy-null', 'close')['run'];
        $month = collect($run->payload['revenue_by_month'])->firstWhere('month', '2026-08');
        $this->assertSame(150000, $month['gross_revenue']);
        $this->assertSame(6250, $month['commission_amount']);
        $this->assertSame(143750, $month['vendor_net_amount']);
        $this->assertNull($month['merchandise_revenue']);
        $this->assertNull($month['shipping_revenue']);
        $this->assertNull($month['service_fee_revenue']);
        $this->assertSame('partial', $run->quality['status']);
        $this->assertSame(1, $run->quality['unknown_component_count']);
    }

    public function test_refund_and_reversal_use_distinct_event_months_and_vendor_csv_rows(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        [$buyer, $vendor] = $this->parties();
        $order = $this->canonicalEarning($buyer, $vendor, Carbon::parse('2026-08-01'), 100000, 90000, 5000, 5000, 12500);
        $return = ReturnRequest::create(['code' => (string) Str::uuid(), 'order_id' => $order->id, 'user_id' => $buyer->id, 'vendor_id' => $vendor->id, 'status' => 'refunded', 'currency' => 'VND', 'refund_amount' => 30000, 'reason' => 'test', 'requested_at' => Carbon::parse('2026-05-01'), 'refunded_at' => Carbon::parse('2026-06-15')]);
        RefundTransaction::create(['return_request_id' => $return->id, 'provider' => 'test', 'idempotency_key' => 'refund-'.$return->id, 'amount' => 30000, 'currency' => 'VND', 'status' => 'refunded', 'refunded_at' => Carbon::parse('2026-06-15')]);
        DB::table('vendor_earning_reversals')->insert(['vendor_id' => $vendor->id, 'order_id' => $order->id, 'return_request_id' => $return->id, 'operation_key' => 'reversal-'.$return->id, 'gross_amount' => 30000, 'commission_amount' => 3750, 'tax_amount' => 0, 'net_amount' => 26250, 'currency' => 'VND', 'created_at' => Carbon::parse('2026-05-20'), 'updated_at' => Carbon::parse('2026-05-20')]);

        $run = app(RevenueReportService::class)->refreshLast24Months($admin, 'refund-reversal', 'close')['run'];
        $may = collect($run->payload['revenue_by_month'])->firstWhere('month', '2026-05');
        $june = collect($run->payload['revenue_by_month'])->firstWhere('month', '2026-06');
        $this->assertSame(3750, $may['commission_reversal_amount']);
        $this->assertSame(26250, $may['vendor_net_reversal_amount']);
        $this->assertSame(30000, $june['refund_amount']);

        $vendorUser = $vendor->user;
        $json = $this->actingAs($vendorUser)->getJson('/api/vendor/finance/revenue?granularity=year&period=2026')->assertOk();
        $json->assertJsonPath('data.summary.customer_refund_amount', 30000)->assertJsonPath('data.summary.commission_reversal_amount', 3750)->assertJsonPath('data.summary.vendor_net_reversal_amount', 26250);
        $csv = $this->actingAs($vendorUser)->get('/api/vendor/finance/revenue/export?granularity=year&period=2026')->assertOk()->streamedContent();
        $this->assertStringContainsString('earning', $csv);
        $this->assertStringContainsString('customer_refund', $csv);
        $this->assertStringContainsString('earning_reversal', $csv);
        $this->assertStringContainsString('30000', $csv);
        $this->assertStringContainsString('3750', $csv);
    }

    public function test_running_run_cannot_terminalize_while_rewriting_audit_identity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $run = $this->createRun($admin, 'identity-lock', 'close', RevenueReportRun::RUNNING, 'admin-finance-24-months');
        $originalPublicId = $run->public_id;
        $originalWindowStart = $run->window_start?->toDateString();

        $run->fill([
            'public_id' => (string) Str::uuid(),
            'window_start' => now()->subYear(),
            'status' => RevenueReportRun::FAILED,
            'active_slot' => null,
            'failed_at' => now(),
            'failure_code' => 'build_failed',
        ]);
        try {
            $run->save();
            $this->fail('Expected audit identity rewrite to be rejected.');
        } catch (\LogicException) {
            // Expected model guard.
        }

        $fresh = RevenueReportRun::query()->findOrFail($run->id);
        $this->assertSame(RevenueReportRun::RUNNING, $fresh->status);
        $this->assertSame($originalPublicId, $fresh->public_id);
        $this->assertSame($originalWindowStart, $fresh->window_start?->toDateString());
    }

    public function test_invoice_seller_subtotal_discount_and_arithmetic_mismatches_fail_safely(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        [$buyer, $vendor] = $this->parties();
        $order = $this->canonicalEarning($buyer, $vendor, now(), 100000, 80000, 10000, 10000, 0);
        $prior = app(RevenueReportService::class)->refreshLast24Months($admin, 'invoice-prior', 'close')['run'];
        $invoiceId = InvoiceSnapshot::query()->where('order_id', $order->id)->value('id');

        DB::table('invoice_snapshots')->where('id', $invoiceId)->update(['seller_snapshot' => json_encode(['vendor_id' => $vendor->id + 1], JSON_THROW_ON_ERROR)]);
        $this->assertSourceFailure($admin, 'invoice-seller', $prior->public_id);
        DB::table('invoice_snapshots')->where('id', $invoiceId)->update(['seller_snapshot' => json_encode(['vendor_id' => $vendor->id], JSON_THROW_ON_ERROR), 'subtotal_amount' => 79999]);
        $this->assertSourceFailure($admin, 'invoice-subtotal', $prior->public_id);
        DB::table('invoice_snapshots')->where('id', $invoiceId)->update(['subtotal_amount' => 80000, 'coupon_discount_amount' => 1]);
        $this->assertSourceFailure($admin, 'invoice-discount', $prior->public_id);
        DB::table('invoice_snapshots')->where('id', $invoiceId)->update(['coupon_discount_amount' => 0, 'total_amount' => 99999]);
        $this->assertSourceFailure($admin, 'invoice-arithmetic', $prior->public_id);
    }

    public function test_order_vendor_tenancy_mismatch_fails_without_replacing_the_prior_completed_run(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        [$buyer, $vendor] = $this->parties();
        $order = $this->canonicalEarning($buyer, $vendor, now(), 100000, 80000, 10000, 10000, 0);
        $prior = app(RevenueReportService::class)->refreshLast24Months($admin, 'order-vendor-prior', 'close')['run'];
        $priorPayloadHash = hash('sha256', json_encode($prior->payload, JSON_THROW_ON_ERROR));
        $priorUpdatedAt = $prior->updated_at?->toISOString();
        [, $otherVendor] = $this->parties();

        DB::table('orders')->where('id', $order->id)->update(['vendor_id' => $otherVendor->id]);

        $this->assertSourceFailure($admin, 'order-vendor-mismatch', $prior->public_id);
        $preserved = $prior->fresh();
        $this->assertSame($priorPayloadHash, hash('sha256', json_encode($preserved->payload, JSON_THROW_ON_ERROR)));
        $this->assertSame($priorUpdatedAt, $preserved->updated_at?->toISOString());
    }

    public function test_same_key_replays_across_reporting_window_boundaries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Carbon::setTestNow('2026-06-30 23:59:00');
        $first = app(RevenueReportService::class)->refreshLast24Months($admin, 'month-boundary', 'close');
        Carbon::setTestNow('2026-07-01 00:01:00');
        $replay = app(RevenueReportService::class)->refreshLast24Months($admin, 'month-boundary', 'close');

        $this->assertTrue($replay['replayed']);
        $this->assertSame($first['run']->id, $replay['run']->id);
        $this->assertDatabaseCount('revenue_report_runs', 1);
        try {
            app(RevenueReportService::class)->refreshLast24Months($admin, 'month-boundary', 'different reason');
            $this->fail('Expected changed reason to conflict.');
        } catch (RevenueReportRequestConflict) {
            $this->addToAssertionCount(1);
        }
        try {
            app(RevenueReportService::class)->refreshLast24Months(User::factory()->create(['role' => 'admin']), 'month-boundary', 'close');
            $this->fail('Expected changed actor to conflict.');
        } catch (RevenueReportRequestConflict) {
            $this->addToAssertionCount(1);
        }
    }

    private function parties(): array
    {
        $buyer = User::factory()->create();
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create(['user_id' => $vendorUser->id, 'shop_name' => 'Truth Shop', 'slug' => 'truth-shop-'.Str::random(6), 'status' => 'active']);

        return [$buyer, $vendor];
    }

    private function canonicalEarning(User $buyer, Vendor $vendor, Carbon $at, int $gross, int $merchandise, int $shipping, int $service, int $commission): Order
    {
        $order = Order::withoutGlobalScopes()->create(['order_code' => 'B5-'.Str::upper(Str::random(10)), 'user_id' => $buyer->id, 'vendor_id' => $vendor->id, 'total_amount' => $gross, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cod', 'shipping_address' => 'test', 'phone' => '0900000000']);
        $session = CheckoutSession::create(['user_id' => $buyer->id, 'currency' => 'VND', 'subtotal_amount' => $merchandise, 'discount_amount' => 0, 'fee_amount' => $service, 'total_amount' => $gross]);
        CheckoutSessionOrder::create(['checkout_session_id' => $session->id, 'order_id' => $order->id, 'vendor_id' => $vendor->id, 'subtotal_amount' => $merchandise, 'discount_amount' => 0, 'coupon_discount_amount' => 0, 'membership_discount_amount' => 0, 'shipping_fee_amount' => $shipping, 'fee_amount' => $service, 'commission_rate' => $gross ? $commission / $gross * 100 : 0, 'commission_amount' => $commission, 'total_amount' => $gross]);
        InvoiceSnapshot::create(['order_id' => $order->id, 'invoice_number' => 'INV-'.$order->order_code, 'currency' => 'VND', 'issued_at' => $at, 'buyer_snapshot' => [], 'seller_snapshot' => ['shop_name' => 'Truth Shop', 'vendor_id' => $vendor->id], 'line_items' => [], 'subtotal_amount' => $merchandise, 'coupon_discount_amount' => 0, 'membership_discount_amount' => 0, 'shipping_fee_amount' => $shipping, 'service_fee_amount' => $service, 'tax_amount' => 0, 'total_amount' => $gross]);
        $ledger = VendorEarningLedger::create(['vendor_id' => $vendor->id, 'order_id' => $order->id, 'operation_key' => 'earning-'.$order->id, 'gross_amount' => $gross, 'commission_amount' => $commission, 'tax_amount' => 0, 'net_amount' => $gross - $commission, 'currency' => 'VND']);
        $ledger->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $order;
    }

    private function createRun(User $admin, string $key, string $reason, string $status, ?string $slot, ?string $failureCode = null): RevenueReportRun
    {
        return RevenueReportRun::withoutEvents(fn () => RevenueReportRun::create([
            'public_id' => (string) Str::uuid(),
            'operation_key' => $key,
            'request_fingerprint' => hash('sha256', json_encode(['actor_id' => $admin->id, 'reason' => $reason, 'operation' => 'admin_finance_report_24_months'], JSON_THROW_ON_ERROR)),
            'requested_by' => $admin->id,
            'reason' => $reason,
            'status' => $status,
            'active_slot' => $slot,
            'window_start' => now()->subMonthsNoOverflow(23)->startOfMonth(),
            'window_end' => now(),
            'as_of_at' => now(),
            'started_at' => now(),
            'failed_at' => $status === RevenueReportRun::FAILED ? now() : null,
            'failure_code' => $failureCode,
        ]));
    }

    private function assertSourceFailure(User $admin, string $key, string $priorPublicId): void
    {
        try {
            app(RevenueReportService::class)->refreshLast24Months($admin, $key, 'close');
            $this->fail('Expected immutable invoice contradiction to fail.');
        } catch (\Throwable) {
            // The failure is intentionally terminalized below.
        }
        $failed = RevenueReportRun::query()->where('operation_key', $key)->firstOrFail();
        $this->assertSame(RevenueReportRun::FAILED, $failed->status);
        $this->assertNull($failed->payload);
        $this->assertSame($priorPublicId, app(RevenueReportService::class)->latestCompletedRun()?->public_id);
    }
}

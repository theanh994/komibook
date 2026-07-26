<?php

namespace Tests\Feature;

use App\Jobs\DispatchNotificationCampaignChunk;
use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignChunk;
use App\Models\User;
use App\Models\Vendor;
use App\Services\NotificationCampaignDispatchService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class Phase4PayoutCampaignOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payout_reservation_is_idempotent_and_prevents_overdraw(): void
    {
        [$user, $vendor] = $this->vendorWithBalance(200000);
        $service = app(PayoutService::class);
        $data = ['amount' => 150000, 'bank_name' => 'Komi Bank', 'account_number' => '123', 'account_name' => 'Vendor'];

        $first = $service->reserve($vendor, $data, $user, 'reserve-one');
        $second = $service->reserve($vendor, $data, $user, 'reserve-one');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(50000, (int) $vendor->fresh()->balance);
        $this->assertDatabaseCount('payout_ledger_entries', 1);

        $this->expectException(LogicException::class);
        $service->reserve($vendor, ['amount' => 50001] + $data, $user, 'reserve-two');
    }

    public function test_payout_counts_total_withdrawn_only_after_completed_transition(): void
    {
        [$vendorUser, $vendor] = $this->vendorWithBalance(300000);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(PayoutService::class);
        $payout = $service->reserve($vendor, ['amount' => 100000, 'bank_name' => 'Komi Bank', 'account_number' => '123', 'account_name' => 'Vendor'], $vendorUser, 'complete-flow');

        $service->transition($payout, 'approved', $admin, 'approve-flow', ['reason' => 'Đã đối soát']);
        $this->assertSame(0, (int) $vendor->fresh()->total_withdrawn);
        $service->transition($payout, 'processing', $admin, 'processing-flow', ['transfer_reference' => 'BANK-001']);
        $completed = $service->transition($payout, 'completed', $admin, 'complete-flow-transition', ['transfer_reference' => 'BANK-001', 'transfer_evidence' => 'evidence/BANK-001.pdf']);
        $service->transition($payout, 'completed', $admin, 'complete-flow-transition', ['transfer_reference' => 'BANK-001', 'transfer_evidence' => 'evidence/BANK-001.pdf']);

        $this->assertSame('completed', $completed->status);
        $this->assertSame(100000, (int) $vendor->fresh()->total_withdrawn);
        $this->assertDatabaseHas('payout_ledger_entries', ['payout_request_id' => $payout->id, 'entry_type' => 'completed']);
        $this->assertDatabaseCount('payout_transitions', 4);
    }

    public function test_rejected_payout_releases_reservation_once(): void
    {
        [$vendorUser, $vendor] = $this->vendorWithBalance(200000);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(PayoutService::class);
        $payout = $service->reserve($vendor, ['amount' => 100000, 'bank_name' => 'Komi Bank', 'account_number' => '123', 'account_name' => 'Vendor'], $vendorUser, 'reject-flow');
        $service->transition($payout, 'rejected', $admin, 'reject-transition', ['reason' => 'Sai thông tin ngân hàng']);
        $service->transition($payout, 'rejected', $admin, 'reject-transition', ['reason' => 'Sai thông tin ngân hàng']);

        $this->assertSame(200000, (int) $vendor->fresh()->balance);
        $this->assertDatabaseCount('payout_ledger_entries', 2);
    }

    public function test_only_recently_authenticated_verified_admin_can_transition_payout(): void
    {
        [$vendorUser, $vendor] = $this->vendorWithBalance(200000);
        $payout = app(PayoutService::class)->reserve($vendor, ['amount' => 100000, 'bank_name' => 'Komi Bank', 'account_number' => '123', 'account_name' => 'Vendor'], $vendorUser, 'route-guard');
        $customer = User::factory()->create();
        $this->actingAs($customer)->patchJson("/api/admin/reconciliation/payouts/{$payout->id}/transition", ['target' => 'approved', 'reason' => 'No', 'idempotency_key' => 'forbidden'])->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])
            ->patchJson("/api/admin/reconciliation/payouts/{$payout->id}/transition", ['target' => 'approved', 'reason' => 'Đã đối soát', 'idempotency_key' => 'guarded-approve'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_due_scheduler_dispatches_consented_audience_in_chunks_once(): void
    {
        Queue::fake();
        User::factory()->count(201)->create(['role' => 'customer', 'marketing_consent_at' => now(), 'marketing_opt_out_at' => null]);
        User::factory()->create(['role' => 'customer', 'marketing_consent_at' => null]);
        $campaign = NotificationCampaign::create(['title' => 'Due', 'message' => 'Hello', 'target_audience' => 'all', 'scheduled_at' => now()->subMinute(), 'status' => 'scheduled']);

        $this->artisan('campaigns:dispatch-due')->assertSuccessful();
        $this->artisan('campaigns:dispatch-due')->assertSuccessful();

        $this->assertSame(201, $campaign->fresh()->audience_count);
        $this->assertSame(2, $campaign->fresh()->chunk_count);
        $this->assertDatabaseCount('notification_campaign_chunks', 2);
        Queue::assertPushed(DispatchNotificationCampaignChunk::class, 2);
    }

    public function test_campaign_chunk_rechecks_consent_and_creates_idempotent_notifications(): void
    {
        Queue::fake();
        Mail::fake();
        $allowed = User::factory()->create(['role' => 'customer', 'marketing_consent_at' => now(), 'marketing_opt_out_at' => null]);
        $revoked = User::factory()->create(['role' => 'customer', 'marketing_consent_at' => now(), 'marketing_opt_out_at' => null]);
        $campaign = NotificationCampaign::create(['title' => 'Privacy', 'message' => 'Hello', 'target_audience' => 'all', 'status' => 'draft']);
        app(NotificationCampaignDispatchService::class)->start($campaign, 'privacy-dispatch');
        $revoked->update(['marketing_consent_at' => null, 'marketing_opt_out_at' => now()]);
        $chunk = NotificationCampaignChunk::firstOrFail();

        $job = new DispatchNotificationCampaignChunk($chunk->id);
        $job->handle(app(NotificationCampaignDispatchService::class));
        $job->handle(app(NotificationCampaignDispatchService::class));

        $this->assertDatabaseHas('user_notifications', ['operation_key' => "campaign:{$campaign->id}:user:{$allowed->id}"]);
        $this->assertDatabaseMissing('user_notifications', ['operation_key' => "campaign:{$campaign->id}:user:{$revoked->id}"]);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertFalse((bool) $campaign->fresh()->telemetry_available);
    }

    public function test_campaign_job_failure_is_reported_without_fabricated_analytics(): void
    {
        Queue::fake();
        User::factory()->create(['role' => 'customer', 'marketing_consent_at' => now()]);
        $campaign = NotificationCampaign::create(['title' => 'Failure', 'message' => 'Hello', 'target_audience' => 'all', 'status' => 'draft']);
        app(NotificationCampaignDispatchService::class)->start($campaign, 'failure-dispatch');
        $chunk = NotificationCampaignChunk::firstOrFail();

        (new DispatchNotificationCampaignChunk($chunk->id))->failed(new RuntimeException('secret provider text'));

        $campaign->refresh();
        $this->assertSame('partial_failed', $campaign->dispatch_status);
        $this->assertSame(1, $campaign->failed_chunk_count);
        $this->assertFalse((bool) $campaign->telemetry_available);
        $this->assertStringNotContainsString('secret provider text', $chunk->fresh()->last_error);
    }

    private function vendorWithBalance(int $balance): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create(['user_id' => $user->id, 'shop_name' => 'Payout Shop', 'slug' => 'payout-shop-'.uniqid(), 'status' => 'active']);
        $vendor->forceFill(['balance' => $balance, 'total_withdrawn' => 0])->save();

        return [$user, $vendor];
    }
}

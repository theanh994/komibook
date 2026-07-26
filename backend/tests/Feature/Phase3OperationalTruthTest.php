<?php

namespace Tests\Feature;

use App\Jobs\DispatchNotificationCampaignChunk;
use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignChunk;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationCampaignDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class Phase3OperationalTruthTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_get_campaign_analytics_does_not_mutate_database(): void
    {
        $campaign = $this->campaign(['status' => 'sent']);
        $this->actingAs($this->admin)->getJson("/api/admin/notifications/campaigns/{$campaign->id}")->assertOk();
        $this->assertSame(0, $campaign->fresh()->sent_count);
    }

    public function test_counter_zero_is_not_replaced_by_fake_numbers(): void
    {
        $campaign = $this->campaign(['status' => 'sent']);
        $this->actingAs($this->admin)->getJson("/api/admin/notifications/campaigns/{$campaign->id}")->assertOk()->assertJson([
            'campaign' => ['sent_count' => 0, 'opened_count' => 0, 'click_count' => 0],
            'analytics' => ['open_rate' => null, 'click_rate' => null, 'telemetry_available' => false, 'hourly_opens' => [], 'devices' => [], 'segments' => []],
        ]);
    }

    public function test_open_and_click_rates_are_hidden_without_real_telemetry_source(): void
    {
        $campaign = $this->campaign(['status' => 'sent', 'sent_count' => 100, 'opened_count' => 25, 'click_count' => 10]);
        $this->actingAs($this->admin)->getJson("/api/admin/notifications/campaigns/{$campaign->id}")->assertOk()->assertJson([
            'analytics' => ['open_rate' => null, 'click_rate' => null, 'telemetry_available' => false],
        ]);
    }

    public function test_empty_audience_query_does_not_fallback_to_all_customers(): void
    {
        $campaign = $this->campaign();
        $this->actingAsAdminRecently()->postJson("/api/admin/notifications/campaigns/{$campaign->id}/send")->assertOk();
        $this->assertSame('sent', $campaign->fresh()->status);
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_fiction_enthusiasts_audience_fails_closed_with_422(): void
    {
        $campaign = $this->campaign(['target_audience' => 'fiction_enthusiasts']);
        $this->actingAsAdminRecently()->postJson("/api/admin/notifications/campaigns/{$campaign->id}/send")
            ->assertStatus(422)->assertJsonPath('message', 'Audience fiction_enthusiasts chưa có nguồn dữ liệu đồng ý hợp lệ.');
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_dispatch_success_updates_real_sent_count_without_fake_opened_or_clicked(): void
    {
        User::factory()->count(2)->create(['role' => 'customer', 'marketing_consent_at' => now()]);
        $campaign = $this->campaign();
        $this->actingAsAdminRecently()->postJson("/api/admin/notifications/campaigns/{$campaign->id}/send")->assertOk();
        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(0, $campaign->opened_count);
        $this->assertSame(0, $campaign->click_count);
        $this->assertSame(2, UserNotification::where('data->campaign_id', $campaign->id)->count());
    }

    public function test_dispatch_failure_is_reported_for_retry_without_fake_success(): void
    {
        Queue::fake();
        User::factory()->create(['role' => 'customer', 'marketing_consent_at' => now()]);
        $campaign = $this->campaign();
        app(NotificationCampaignDispatchService::class)->start($campaign, 'phase3-failure');
        $chunk = NotificationCampaignChunk::firstOrFail();
        (new DispatchNotificationCampaignChunk($chunk->id))->failed(new RuntimeException('provider secret'));
        $campaign->refresh();
        $this->assertSame('draft', $campaign->status);
        $this->assertSame(0, $campaign->sent_count);
        $this->assertSame('partial_failed', $campaign->dispatch_status);
        $this->assertDatabaseCount('user_notifications', 0);
    }

    private function actingAsAdminRecently(): static
    {
        return $this->actingAs($this->admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function campaign(array $attributes = []): NotificationCampaign
    {
        return NotificationCampaign::create(array_merge(['title' => 'Operational truth', 'message' => 'No fake metrics', 'target_audience' => 'all', 'status' => 'draft', 'sent_count' => 0, 'opened_count' => 0, 'click_count' => 0], $attributes));
    }
}

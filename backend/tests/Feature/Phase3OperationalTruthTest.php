<?php

namespace Tests\Feature;

use App\Models\NotificationCampaign;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase3OperationalTruthTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_get_campaign_analytics_does_not_mutate_database()
    {
        $campaign = NotificationCampaign::create([
            'title' => 'Test Campaign',
            'message' => 'Hello test',
            'target_audience' => 'all',
            'status' => 'sent',
            'sent_count' => 0,
            'opened_count' => 0,
            'click_count' => 0,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/notifications/campaigns/{$campaign->id}");

        $response->assertStatus(200);

        // Database record must NOT be updated during GET request
        $campaign->refresh();
        $this->assertEquals(0, $campaign->sent_count);
        $this->assertEquals(0, $campaign->opened_count);
        $this->assertEquals(0, $campaign->click_count);
    }

    public function test_counter_zero_is_not_replaced_by_fake_numbers()
    {
        $campaign = NotificationCampaign::create([
            'title' => 'Zero Stats Campaign',
            'message' => 'Message zero',
            'target_audience' => 'all',
            'status' => 'sent',
            'sent_count' => 0,
            'opened_count' => 0,
            'click_count' => 0,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/notifications/campaigns/{$campaign->id}");

        $response->assertStatus(200)
            ->assertJson([
                'campaign' => [
                    'sent_count' => 0,
                    'opened_count' => 0,
                    'click_count' => 0,
                ],
                'analytics' => [
                    'open_rate' => 0,
                    'click_rate' => 0,
                    'telemetry_available' => false,
                    'hourly_opens' => [],
                    'devices' => [],
                    'segments' => [],
                ],
            ]);
    }

    public function test_open_and_click_rates_calculated_from_real_counters()
    {
        $campaign = NotificationCampaign::create([
            'title' => 'Real Stats Campaign',
            'message' => 'Message real',
            'target_audience' => 'all',
            'status' => 'sent',
            'sent_count' => 100,
            'opened_count' => 25,
            'click_count' => 10,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/notifications/campaigns/{$campaign->id}");

        $response->assertStatus(200)
            ->assertJson([
                'analytics' => [
                    'open_rate' => 25.0,
                    'click_rate' => 10.0,
                    'telemetry_available' => false,
                ],
            ]);
    }

    public function test_empty_audience_query_does_not_fallback_to_all_customers()
    {
        // No customers in database
        $campaign = NotificationCampaign::create([
            'title' => 'Empty Audience Campaign',
            'message' => 'Message empty',
            'target_audience' => 'all',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/admin/notifications/campaigns/{$campaign->id}/send");

        $response->assertStatus(200);
        $campaign->refresh();
        $this->assertEquals('sent', $campaign->status);
        $this->assertEquals(0, $campaign->sent_count);
        $this->assertEquals(0, UserNotification::count());
    }

    public function test_fiction_enthusiasts_audience_fails_closed_with_422()
    {
        $campaign = NotificationCampaign::create([
            'title' => 'Fiction Audience Campaign',
            'message' => 'Message fiction',
            'target_audience' => 'fiction_enthusiasts',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/admin/notifications/campaigns/{$campaign->id}/send");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Phân loại khán giả fiction_enthusiasts chưa được hỗ trợ.',
            ]);

        $campaign->refresh();
        $this->assertEquals('draft', $campaign->status);
    }

    public function test_dispatch_success_updates_real_sent_count_without_fake_opened_or_clicked()
    {
        User::factory()->create(['role' => 'customer']);
        User::factory()->create(['role' => 'customer']);

        $campaign = NotificationCampaign::create([
            'title' => 'Dispatch Campaign',
            'message' => 'Message dispatch',
            'target_audience' => 'all',
            'status' => 'draft',
            'sent_count' => 0,
            'opened_count' => 0,
            'click_count' => 0,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/admin/notifications/campaigns/{$campaign->id}/send");

        $response->assertStatus(200);
        $campaign->refresh();
        $this->assertEquals('sent', $campaign->status);
        $this->assertEquals(2, $campaign->sent_count);
        $this->assertEquals(0, $campaign->opened_count);
        $this->assertEquals(0, $campaign->click_count);
        $this->assertEquals(2, UserNotification::where('data->campaign_id', $campaign->id)->count());
    }

    public function test_dispatch_failure_rolls_back_notifications_sent_count_and_sent_status()
    {
        User::factory()->create(['role' => 'customer']);

        $campaign = NotificationCampaign::create([
            'title' => 'Atomic Dispatch Campaign',
            'message' => 'Message atomic dispatch',
            'target_audience' => 'all',
            'status' => 'draft',
            'sent_count' => 0,
            'opened_count' => 0,
            'click_count' => 0,
        ]);

        DB::statement(<<<'SQL'
            CREATE TRIGGER fail_notification_campaign_sent
            BEFORE UPDATE OF status ON notification_campaigns
            WHEN NEW.status = 'sent'
            BEGIN
                SELECT RAISE(ABORT, 'forced campaign status failure');
            END
        SQL);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/notifications/campaigns/{$campaign->id}/send");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Không thể gửi chiến dịch thông báo.',
            ]);

        $campaign->refresh();
        $this->assertSame('draft', $campaign->status);
        $this->assertSame(0, $campaign->sent_count);
        $this->assertSame(0, UserNotification::where('data->campaign_id', $campaign->id)->count());
    }
}

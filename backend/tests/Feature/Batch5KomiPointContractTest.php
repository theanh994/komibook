<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\MembershipTier;
use App\Models\User;
use App\Services\RagSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Batch5KomiPointContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_api_and_rag_publish_the_same_approved_earning_rule(): void
    {
        $user = User::factory()->create(['points' => 42]);
        MembershipTier::create([
            'name' => 'Komi Bronze',
            'min_points' => 0,
            'discount_percent' => 0,
            'benefits' => 'Basic benefits',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/membership')
            ->assertOk()
            ->assertJsonPath(
                'data.earning_rule.description',
                'Nhận 1 KomiPoint cho mỗi 10.000 VNĐ giá trị đơn hàng hoàn tất.'
            );

        $session = ChatSession::create([
            'target_type' => ChatSession::TARGET_PLATFORM,
            'responder_mode' => ChatSession::MODE_AI,
            'status' => ChatSession::STATUS_OPEN,
        ]);

        $knowledge = app(RagSearchService::class)->buildKnowledge($session, 'KomiPoint');
        $membershipSource = collect($knowledge['entries'])->firstWhere('type', 'membership');

        $this->assertNotNull($membershipSource);
        $this->assertStringContainsString('1 KomiPoint cho mỗi 10.000đ', $membershipSource['content']);
    }
}

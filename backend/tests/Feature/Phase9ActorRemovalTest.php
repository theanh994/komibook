<?php

namespace Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase9ActorRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_actor_schema_and_http_entry_points_are_removed(): void
    {
        foreach ([
            'authors',
            'author_onboarding_events',
            'author_fulfillment_addresses',
            'author_commerce_profiles',
            'book_authors',
            'author_delegations',
            'copyright_claims',
            'royalty_agreements',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} must be retired.");
        }

        $this->getJson('/api/author/status')->assertNotFound();
        $this->postJson('/api/auth/register', [
            'name' => 'Actor không còn hợp lệ',
            'email' => 'retired-actor@example.test',
            'phone' => '0900000000',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'desired_role' => 'author',
        ])->assertUnprocessable()->assertJsonValidationErrors('desired_role');
    }

    public function test_user_contract_only_exposes_active_neutral_capabilities(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $payload = (new UserResource($user->load(['vendor', 'membershipTier', 'usedBookSellerProfile'])))
            ->response()
            ->getData(true)['data'];

        $this->assertArrayNotHasKey('author_status', $payload);
        $this->assertArrayNotHasKey('author_profile', $payload);
        $this->assertArrayNotHasKey('approved_author', $payload['capabilities']);
        $this->assertFalse($payload['capabilities']['used_book_seller']);
    }
}

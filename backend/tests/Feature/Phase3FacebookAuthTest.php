<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FacebookTokenVerifier;
use App\Services\FacebookTokenVerifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class Phase3FacebookAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.facebook', [
            'app_id' => 'facebook_app_123',
            'app_secret' => 'facebook_secret_456',
            'graph_version' => 'v-test',
        ]);
    }

    public function test_facebook_registration_uses_one_time_backend_verified_challenge(): void
    {
        $this->app->instance(FacebookTokenVerifierInterface::class, $this->verifier([
            'id' => 'facebook_user_100',
            'email' => 'facebook@example.com',
            'name' => 'Facebook Reader',
        ]));

        $loginResponse = $this->postJson('/api/auth/facebook-login', [
            'access_token' => 'valid-facebook-token',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('status', 'needs_registration')
            ->assertJsonPath('data.email', 'facebook@example.com')
            ->assertJsonPath('data.name', 'Facebook Reader');

        $challengeToken = $loginResponse->json('data.challenge_token');
        $this->assertTrue(Str::isUuid($challengeToken));

        $registrationPayload = [
            'challenge_token' => $challengeToken,
            'name' => 'Facebook Reader',
            'email' => 'facebook@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'desired_role' => 'customer',
        ];

        $this->postJson('/api/auth/register', $registrationPayload)
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonMissing(['facebook_id']);

        $user = User::where('email', 'facebook@example.com')->firstOrFail();
        $this->assertSame('facebook_user_100', $user->facebook_id);

        $this->postJson('/api/auth/register', [
            ...$registrationPayload,
            'email' => 'replay@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }

    public function test_facebook_login_links_verified_matching_email_and_creates_session(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@example.com',
            'facebook_id' => null,
        ]);

        $this->app->instance(FacebookTokenVerifierInterface::class, $this->verifier([
            'id' => 'facebook_user_200',
            'email' => 'linked@example.com',
            'name' => 'Linked Reader',
        ]));

        $this->postJson('/api/auth/facebook-login', [
            'access_token' => 'valid-facebook-token',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertAuthenticatedAs($user);
        $this->assertSame('facebook_user_200', $user->fresh()->facebook_id);
    }

    public function test_facebook_auth_fails_closed_for_missing_config_invalid_token_and_direct_id(): void
    {
        Config::set('services.facebook.app_secret', '');

        $this->postJson('/api/auth/facebook-login', [
            'access_token' => 'valid-facebook-token',
        ])
            ->assertStatus(500)
            ->assertJsonPath('status', 'error');

        Config::set('services.facebook.app_secret', 'facebook_secret_456');
        $this->app->instance(FacebookTokenVerifierInterface::class, $this->verifier(
            exception: new InvalidArgumentException('Token Facebook không hợp lệ.')
        ));

        $this->postJson('/api/auth/facebook-login', [
            'access_token' => 'invalid-facebook-token',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/auth/register', [
            'name' => 'Forged Facebook User',
            'email' => 'forged@example.com',
            'facebook_id' => 'forged-id',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }

    public function test_real_verifier_checks_token_app_ownership_before_loading_profile(): void
    {
        Http::fake([
            'https://graph.facebook.com/v-test/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'app_id' => 'facebook_app_123',
                    'user_id' => 'facebook_user_300',
                ],
            ]),
            'https://graph.facebook.com/v-test/facebook_user_300*' => Http::response([
                'id' => 'facebook_user_300',
                'email' => 'verified@example.com',
                'name' => 'Verified Facebook User',
            ]),
        ]);

        $profile = (new FacebookTokenVerifier)->verify('facebook-access-token');

        $this->assertSame([
            'id' => 'facebook_user_300',
            'email' => 'verified@example.com',
            'name' => 'Verified Facebook User',
        ], $profile);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/debug_token')
            && $request['input_token'] === 'facebook-access-token'
            && $request['access_token'] === 'facebook_app_123|facebook_secret_456');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/facebook_user_300')
            && $request['appsecret_proof'] === hash_hmac('sha256', 'facebook-access-token', 'facebook_secret_456'));
    }

    private function verifier(
        array $profile = [],
        ?InvalidArgumentException $exception = null
    ): FacebookTokenVerifierInterface {
        return new class($profile, $exception) implements FacebookTokenVerifierInterface
        {
            public function __construct(
                private readonly array $profile,
                private readonly ?InvalidArgumentException $exception
            ) {}

            public function verify(string $accessToken): array
            {
                if ($this->exception) {
                    throw $this->exception;
                }

                return $this->profile;
            }
        };
    }
}

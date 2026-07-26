<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RegistrationEmailOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationEmailOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_email_otp_is_sent_and_returns_single_use_verification_token(): void
    {
        Notification::fake();
        $email = 'new.reader@example.com';

        $this->postJson('/api/auth/email/send-otp', ['email' => $email])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        Notification::assertSentTo(
            new AnonymousNotifiable,
            RegistrationEmailOtp::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $email
        );

        $emailHash = hash('sha256', $email);
        Cache::put('registration_email_otp_'.$emailHash, Hash::make('12345678'), now()->addMinutes(5));

        $verification = $this->postJson('/api/auth/email/verify-otp', [
            'email' => $email,
            'otp' => '12345678',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $token = $verification->json('data.verification_token');

        $payload = [
            'name' => 'New Reader',
            'email' => $email,
            'phone' => '0987654321',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'email_verification_token' => $token,
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertCreated()
            ->assertJsonPath('status', 'success');

        $this->assertNotNull(User::where('email', $email)->firstOrFail()->email_verified_at);

        User::where('email', $email)->delete();
        $this->postJson('/api/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }

    public function test_email_registration_is_rejected_without_verified_otp(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Unverified Reader',
            'email' => 'unverified@example.com',
            'phone' => '0912345678',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }
}

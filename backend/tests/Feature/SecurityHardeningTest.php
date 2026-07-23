<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Vendor;
use App\Services\GoogleTokenVerifier;
use App\Services\GoogleTokenVerifierInterface;
use App\Services\HtmlSanitizer;
use App\Services\Otp\FakeOtpSender;
use App\Services\Otp\OtpSenderInterface;
use App\Services\Otp\ProductionOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * AUTH-02: Test login tạo cookie session, /api/auth/me dùng cookie session không cần Bearer token.
     * Login, Register, Google login response không chứa access_token hay token_type.
     * Logout làm mất session và /api/auth/me trả 401.
     */
    public function test_auth_02_cookie_session_authentication_and_no_tokens_in_response()
    {
        $user = User::factory()->create([
            'email' => 'session_user@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        // 1. Login tạo session cookie
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'session_user@example.com',
            'password' => 'Password123!',
        ]);

        $loginRes->assertStatus(200);
        $loginRes->assertJsonMissing(['access_token', 'token_type']);

        // 2. Request tới /api/auth/me thành công bằng cookie session mà KHÔNG gửi Bearer token
        $meRes = $this->getJson('/api/auth/me');
        $meRes->assertStatus(200);
        $meRes->assertJsonPath('data.user.email', 'session_user@example.com');

        // 3. Logout hủy session
        $logoutRes = $this->postJson('/api/auth/logout');
        $logoutRes->assertStatus(200);

        // Clear in-memory auth state guard trong test runner
        $this->app->make('auth')->forgetGuards();

        // 4. Request tới /api/auth/me trả về 401 Unauthorized sau khi logout
        $meResAfterLogout = $this->getJson('/api/auth/me');
        $meResAfterLogout->assertStatus(401);
    }

    /**
     * AUTH-01: Test Google Authentication 6-point verification & challenge token registration flow.
     */
    public function test_auth_01_google_auth_verifier_and_challenge_token_flow()
    {
        Config::set('services.google.client_id', 'valid_google_client_id_123');

        // Mock GoogleTokenVerifierInterface qua container
        $mockVerifier = new class implements GoogleTokenVerifierInterface
        {
            public function verify(string $idToken): array
            {
                if (str_starts_with($idToken, 'invalid_')) {
                    throw new InvalidArgumentException('Token Google không hợp lệ.');
                }

                return [
                    'sub' => 'google_sub_9999',
                    'email' => 'new_google_user@gmail.com',
                    'name' => 'Google Test User',
                    'email_verified' => true,
                    'aud' => 'valid_google_client_id_123',
                    'iss' => 'https://accounts.google.com',
                ];
            }
        };

        $this->app->instance(GoogleTokenVerifierInterface::class, $mockVerifier);

        // 1. Google login chưa có tài khoản -> Trả về status 'needs_registration' + 'challenge_token' (UUID)
        $res = $this->postJson('/api/auth/google-login', [
            'id_token' => 'valid_fake_id_token',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'needs_registration');
        $challengeToken = $res->json('data.challenge_token');
        $this->assertNotEmpty($challengeToken);

        // 2. Client tự gửi google_id công khai -> Bị loại bỏ bởi validation
        $regAttemptSelfGoogleId = $this->postJson('/api/auth/register', [
            'name' => 'Self Sent Google ID',
            'google_id' => 'hacked_google_id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $regAttemptSelfGoogleId->assertStatus(422);

        // 3. Đăng ký thành công bằng challenge_token
        $regRes = $this->postJson('/api/auth/register', [
            'challenge_token' => $challengeToken,
            'name' => 'Google Registered User',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '0912345678',
        ]);

        $regRes->assertStatus(201);
        $regRes->assertJsonMissing(['google_id']); // UserResource không chứa google_id

        $user = User::where('email', 'new_google_user@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('google_sub_9999', $user->google_id);

        // 4. Replay attack: Dùng lại challenge_token đã sử dụng -> Bị từ chối 422
        $replayRes = $this->postJson('/api/auth/register', [
            'challenge_token' => $challengeToken,
            'name' => 'Replay User',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $replayRes->assertStatus(422);
    }

    /**
     * Test Google Token Verifier fail-closed when Google Client ID is missing.
     */
    public function test_google_token_verifier_fail_closed_when_client_id_missing()
    {
        Config::set('services.google.client_id', '');

        $verifier = new GoogleTokenVerifier;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Google Client ID chưa được cấu hình.');

        $verifier->verify('any_token');
    }

    /**
     * SEC-01: Test OTP hardening & OTP reuse/expiration.
     */
    public function test_sec_01_otp_security_hardening()
    {
        $fakeSender = new FakeOtpSender;
        $this->app->instance(OtpSenderInterface::class, $fakeSender);

        // 1. Chuẩn hóa SĐT và Gửi OTP thành công
        $sendRes = $this->postJson('/api/auth/phone/send-otp', [
            'phone' => '84912345678', // 84912345678 được chuẩn hóa về 0912345678
        ]);

        $sendRes->assertStatus(200);
        $sendRes->assertJsonMissing(['otp']); // Không trả OTP trong HTTP response

        $storedOtp = Cache::get('test_otp_0912345678');
        $this->assertEquals(6, strlen($storedOtp));

        // 2. Rate limit 60s cho lần gửi tiếp theo
        $rateRes = $this->postJson('/api/auth/phone/send-otp', [
            'phone' => '0912345678',
        ]);
        $rateRes->assertStatus(429);

        // 3. Verify OTP với mã sai -> Bị từ chối
        $failVerify = $this->postJson('/api/auth/phone/verify-otp', [
            'phone' => '0912345678',
            'otp' => '000000',
        ]);
        $failVerify->assertStatus(422);

        // 4. Verify OTP đúng -> Trả về status 'needs_registration' nếu là SĐT mới
        $successVerify = $this->postJson('/api/auth/phone/verify-otp', [
            'phone' => '0912345678',
            'otp' => $storedOtp,
        ]);
        $successVerify->assertStatus(200);
        $successVerify->assertJsonPath('status', 'needs_registration');

        // 5. Dùng lại OTP đã verify thành công -> Bị từ chối (OTP đã bị xóa khỏi cache)
        $reusedVerify = $this->postJson('/api/auth/phone/verify-otp', [
            'phone' => '0912345678',
            'otp' => $storedOtp,
        ]);
        $reusedVerify->assertStatus(422);
    }

    /**
     * Test Production OTP Sender fail-closed behavior.
     */
    public function test_production_otp_sender_fail_closed()
    {
        $sender = new ProductionOtpSender;

        // 1. Thiếu SMS provider -> Throw RuntimeException
        Config::set('services.sms.provider', '');
        try {
            $sender->send('0912345678', '123456');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Hệ thống chưa được cấu hình dịch vụ SMS Gateway provider', $e->getMessage());
        }

        // 2. Cấu hình provider nhưng chưa có adapter -> Throw RuntimeException (Không return true khống)
        Config::set('services.sms.provider', 'unsupported_provider');
        try {
            $sender->send('0912345678', '123456');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('chưa được hỗ trợ adapter', $e->getMessage());
        }
    }

    /**
     * SEC-02: Test HTMLPurifier lọc Stored XSS trong mô tả sách.
     */
    public function test_sec_02_html_purifier_xss_protection()
    {
        $maliciousHtml = '<p>Mô tả sách hay</p><script>alert("XSS")</script><iframe src="http://evil.com"></iframe><a href="javascript:alert(1)" onclick="steal()">Link</a><img src="x" onerror="alert(1)">';

        $sanitized = HtmlSanitizer::sanitize($maliciousHtml);

        $this->assertStringContainsString('<p>Mô tả sách hay</p>', $sanitized);
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('alert(', $sanitized);
        $this->assertStringNotContainsString('<iframe', $sanitized);
        $this->assertStringNotContainsString('onclick', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Shop Test XSS',
            'slug' => 'shop-test-xss-'.time(),
            'status' => 'active',
        ]);
        $category = Category::create([
            'name' => 'Kinh Tế',
            'slug' => 'kinh-te-'.time(),
        ]);

        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách Test XSS',
            'slug' => 'sach-test-xss-'.time(),
            'author' => 'Tác Giả XSS',
            'price' => 100000,
            'description' => '<p>Mô tả</p><script>alert("hack")</script>',
            'status' => 'published',
            'type' => 'physical',
        ]);

        // Dry run: DB chưa đổi
        $this->artisan('app:sanitize-book-descriptions')
            ->assertExitCode(0);

        $book->refresh();
        $this->assertStringContainsString('<script>', $book->description);

        // Force mode: DB đã dọn dẹp
        $this->artisan('app:sanitize-book-descriptions --force')
            ->assertExitCode(0);

        $book->refresh();
        $this->assertStringNotContainsString('<script>', $book->description);
    }

    /**
     * SEC-03 & SEC-04: Test bảo vệ file riêng tư CCCD và ticket attachments & Artisan migration commands.
     */
    public function test_sec_03_and_04_private_files_and_migration_commands()
    {
        Storage::fake('public');
        Storage::fake('private');

        $user = User::factory()->create(['role' => 'customer']);
        $otherUser = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $filePath = UploadedFile::fake()->image('cccd.jpg')->store('authors/cccd', 'private');

        $author = Author::create([
            'user_id' => $user->id,
            'pen_name' => 'Tác Giả Bảo Mật',
            'bank_account_number' => '12345678',
            'bank_name' => 'Vietcombank',
            'bank_holder_name' => 'NGUYEN VAN A',
            'identity_document' => $filePath,
            'status' => 'pending',
        ]);

        // JSON response của Author không rò rỉ identity_document raw path
        $authorArray = $author->toArray();
        $this->assertArrayNotHasKey('identity_document', $authorArray);
        $this->assertTrue($authorArray['has_identity_document']);
        $this->assertEquals("/api/authors/{$author->id}/identity-document", $authorArray['identity_document_url']);

        // Phân quyền tải file CCCD bằng đúng URL trả về từ JSON response
        $downloadUrl = $authorArray['identity_document_url'];
        $this->getJson($downloadUrl)->assertStatus(401);
        $this->actingAs($otherUser)->getJson($downloadUrl)->assertStatus(403);
        $this->actingAs($user)->getJson($downloadUrl)->assertStatus(200);
        $this->actingAs($admin)->getJson($downloadUrl)->assertStatus(200);

        // Test Ticket Message Attachment URL & Access
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => 'Cần hỗ trợ thanh toán',
            'category' => 'billing',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $ticketAttachmentPath = UploadedFile::fake()->create('bill.pdf', 100)->store('tickets', 'private');
        $message = TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message' => 'Gửi hóa đơn đính kèm',
            'attachment' => $ticketAttachmentPath,
        ]);

        $msgArray = $message->toArray();
        $this->assertArrayNotHasKey('attachment', $msgArray);
        $this->assertTrue($msgArray['has_attachment']);
        $this->assertEquals("/api/support/tickets/{$ticket->id}/messages/{$message->id}/attachment", $msgArray['attachment_url']);

        $ticketDownloadUrl = $msgArray['attachment_url'];
        $this->app->make('auth')->forgetGuards();
        $this->getJson($ticketDownloadUrl)->assertStatus(401);
        $this->actingAs($otherUser)->getJson($ticketDownloadUrl)->assertStatus(403);
        $this->actingAs($user)->getJson($ticketDownloadUrl)->assertStatus(200);
        $this->actingAs($admin)->getJson($ticketDownloadUrl)->assertStatus(200);

        // Test migration commands dry-run vs force & idempotency
        $publicFile = UploadedFile::fake()->image('legacy_ticket.png');
        $publicPath = Storage::disk('public')->putFileAs('tickets', $publicFile, 'legacy_ticket.png');

        TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message' => 'Legacy ticket message',
            'attachment' => $publicPath,
        ]);

        // Dry-run command
        $this->artisan('app:migrate-ticket-attachments')->assertExitCode(0);
        $this->assertTrue(Storage::disk('public')->exists($publicPath));

        // Force command -> chuyển từ public sang private, verify sha256 checksum, xóa public
        $this->artisan('app:migrate-ticket-attachments --force')->assertExitCode(0);
        $this->assertTrue(Storage::disk('private')->exists($publicPath));
        $this->assertFalse(Storage::disk('public')->exists($publicPath));

        // Idempotency check -> Chạy lại command không báo lỗi
        $this->artisan('app:migrate-ticket-attachments --force')->assertExitCode(0);
    }

    /**
     * Test Security Headers có mặt đầy đủ trong phản hồi API.
     */
    public function test_security_headers_present_in_responses()
    {
        $response = $this->getJson('/api/books');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}

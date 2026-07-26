<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\MembershipTier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorDrmInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $admin;

    protected $vendorUser;

    protected $vendor;

    protected $category;

    private function verifiedEmailToken(string $email): string
    {
        $token = (string) Str::uuid();
        Cache::put('registration_email_verified_'.$token, strtolower(trim($email)), now()->addMinutes(10));

        return $token;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Tạo người dùng thường
        $this->user = User::factory()->create([
            'role' => 'customer',
        ]);

        // Tạo quản trị viên
        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // Tạo vendor
        $this->vendorUser = User::factory()->create([
            'role' => 'vendor',
        ]);

        $this->vendor = Vendor::create([
            'user_id' => $this->vendorUser->id,
            'shop_name' => 'Komi Store',
            'slug' => 'komi-store',
            'status' => 'active',
        ]);

        // Tạo category
        $this->category = Category::create([
            'name' => 'Kinh Tế',
            'slug' => 'kinh-te',
        ]);
    }

    /**
     * Test đăng ký tác giả đối tác.
     */
    public function test_author_registration_success()
    {
        $file = UploadedFile::fake()->image('cccd.jpg');

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/register-author', [
                'pen_name' => 'Nam Cao',
                'bio' => 'Tác giả hiện thực xuất sắc.',
                'bank_name' => 'Vietcombank',
                'bank_account_number' => '007100012345',
                'bank_holder_name' => 'NGUYEN VAN A',
                'identity_document' => $file,
                'terms_accepted' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Hồ sơ tác giả đã được gửi để kiểm duyệt.',
            ]);

        $this->assertDatabaseHas('authors', [
            'pen_name' => 'Nam Cao',
            'user_id' => $this->user->id,
            'onboarding_status' => 'submitted',
        ]);
    }

    /**
     * Test lấy thống kê kênh tác giả.
     */
    public function test_author_stats_unauthorized_if_not_approved()
    {
        // Khi chưa được duyệt tác giả
        $response = $this->actingAs($this->user)
            ->getJson('/api/author/dashboard-stats');

        $response->assertStatus(403);
    }

    /**
     * Test sinh link stream sách Ebook kèm watermark.
     */
    public function test_generate_ebook_link_denied_if_unpaid()
    {
        // Tạo sách ebook
        $book = Book::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'title' => 'Bóng Tối Sau Lưng',
            'slug' => 'bong-toi-sau-lung',
            'author' => 'Tác giả A',
            'type' => 'ebook',
            'price' => 150000,
            'file_path' => 'ebooks/demo.pdf',
        ]);

        // Tạo đơn hàng chưa thanh toán
        $order = Order::create([
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 150000,
            'total_amount' => 150000,
            'subtotal' => 150000,
            'tax' => 0,
            'shipping_address' => '123 Test Street',
            'phone' => '0901234567',
            'name' => 'John Doe',
            'payment_method' => 'online',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'price' => 150000,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/orders/{$order->id}/ebooks/{$book->id}/generate-link");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Đơn hàng chưa được thanh toán hoặc không đủ quyền truy cập.',
            ]);
    }

    /**
     * Test quy trình kiểm kê kho hàng (Inventory Audit).
     */
    public function test_inventory_audit_creation()
    {
        $warehouse = Warehouse::create([
            'name' => 'Kho Tổng Q1',
            'address' => '123 Nguyễn Huệ, Quận 1',
            'vendor_id' => $this->vendor->id,
        ]);

        $book = Book::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'title' => 'Sách Kiểm Kho',
            'slug' => 'sach-kiem-kho',
            'author' => 'Tác giả A',
            'type' => 'physical',
            'price' => 80000,
        ]);

        $response = $this->actingAs($this->vendorUser)
            ->postJson('/api/vendor/inventory/audits', [
                'warehouse_id' => $warehouse->id,
                'audit_period' => '2026-07',
                'notes' => 'Kiểm kê định kỳ tháng 7/2026',
                'items' => [
                    [
                        'book_id' => $book->id,
                        'system_qty' => 10,
                        'physical_qty' => 9,
                        'discrepancy_reason' => 'Mất mát',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Lập phiếu kiểm kê nháp thành công.',
            ]);

        $this->assertDatabaseHas('inventory_audits', [
            'warehouse_id' => $warehouse->id,
            'audit_period' => '2026-07',
        ]);
    }

    /**
     * Test phiếu vận chuyển chuyển kho (Stock Transfer).
     */
    public function test_stock_transfer_creation()
    {
        $from = Warehouse::create([
            'name' => 'Kho A',
            'address' => 'Hà Nội',
            'vendor_id' => $this->vendor->id,
        ]);

        $to = Warehouse::create([
            'name' => 'Kho B',
            'address' => 'Hồ Chí Minh',
            'vendor_id' => $this->vendor->id,
        ]);

        $book = Book::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'title' => 'Sách Chuyển Kho',
            'slug' => 'sach-chuyen-kho',
            'author' => 'Tác giả A',
            'type' => 'physical',
            'price' => 100000,
        ]);

        $response = $this->actingAs($this->vendorUser)
            ->postJson('/api/vendor/inventory/transfers', [
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'notes' => 'Chuyển hàng hỗ trợ chi nhánh nam',
                'items' => [
                    [
                        'book_id' => $book->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Lập phiếu điều chuyển nháp thành công.',
            ]);

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
        ]);
    }

    /**
     * Test phân khúc khách hàng & Hạng thành viên (CRM).
     */
    public function test_membership_tier_crud()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/membership-tiers', [
                'name' => 'Kim Cương',
                'min_points' => 10000,
                'discount_percent' => 20,
                'benefits' => 'Freeship mọi đơn, phòng chờ VIP, hỗ trợ 24/7',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('membership_tiers', [
            'name' => 'Kim Cương',
            'min_points' => 10000,
        ]);
    }

    /**
     * Test gửi yêu cầu hỗ trợ (Support Ticket).
     */
    public function test_support_ticket_creation()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/support/tickets', [
                'subject' => 'Không thanh toán được qua ví điện tử',
                'category' => 'billing',
                'priority' => 'high',
                'message' => 'Tôi bấm thanh toán MoMo nhưng trang web báo lỗi không kết nối.',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Gửi yêu cầu hỗ trợ thành công.',
            ]);

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Không thanh toán được qua ví điện tử',
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test VIP member discount application and points reward upon delivery.
     */
    public function test_membership_tier_checkout_discount_and_points()
    {
        // 1. Tạo Hạng thành viên Vàng (giảm 10%)
        $goldTier = MembershipTier::create([
            'name' => 'Vàng',
            'min_points' => 2000,
            'discount_percent' => 10,
            'benefits' => 'Giảm 10% đơn hàng',
        ]);

        // Cập nhật hạng thành viên của user thành Vàng
        $this->user->update([
            'membership_tier_id' => $goldTier->id,
            'points' => 2500,
        ]);

        // 2. Tạo Sách vật lý giá 200,000đ
        $book = Book::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'title' => 'Sách VIP Test',
            'slug' => 'sach-vip-test',
            'author' => 'Tác giả A',
            'type' => 'physical',
            'price' => 200000,
            'stock' => 100,
            'status' => 'published',
        ]);

        $warehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Kho VIP Test',
            'address' => '123 VIP Street',
            'capacity' => 1000,
            'status' => 'active',
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'book_id' => $book->id,
            'quantity' => 100,
        ]);

        // 3. Thực hiện checkout
        $response = $this->actingAs($this->user)
            ->postJson('/api/checkout', [
                'items' => [
                    [
                        'book_id' => $book->id,
                        'quantity' => 1,
                    ],
                ],
                'shipping_address' => '123 VIP Street',
                'phone' => '0901234567',
                'payment_method' => 'cod',
            ]);

        $response->assertStatus(201);

        // 4. Kiểm tra đơn hàng được giảm giá 10% (còn 180,000đ)
        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(180000, $order->total_amount);

        // Chuyển đơn hàng sang processing để thực hiện state machine fulfillment chính thức
        $order->status = 'processing';
        $order->save();

        $fulfillmentService = new OrderFulfillmentService;
        $fulfillmentService->updateOrderStatusByVendor($order->id, 'shipped', 'vendor', $this->vendorUser->id);
        $fulfillmentService->updateShippingStatus($order->id, 'picked_up', 'GHTK', 'TRK1', 'vendor', $this->vendorUser->id);
        $fulfillmentService->updateShippingStatus($order->id, 'delivering', 'GHTK', 'TRK1', 'vendor', $this->vendorUser->id);

        // 5. Cập nhật trạng thái giao hàng thành công (delivered) để tích lũy điểm
        // Cần đóng vai trò Vendor để cập nhật trạng thái vận đơn
        $updateResponse = $this->actingAs($this->vendorUser)
            ->patchJson("/api/vendor/orders/{$order->id}/shipping", [
                'shipping_status' => 'delivered',
                'shipping_carrier' => 'GHTK',
            ]);

        $updateResponse->assertStatus(200);

        // 6. Kiểm tra số điểm được cộng thêm: 180,000đ / 10,000đ = +18 điểm
        // Ban đầu 2500 + 18 = 2518 điểm
        $this->user->refresh();
        $this->assertEquals(2518, $this->user->points);
    }

    /**
     * Test đăng ký tài khoản chọn vai trò mong muốn.
     */
    public function test_registration_desired_role()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tác Giả Mới',
            'email' => 'author_new@komibook.id.vn',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0987111222',
            'desired_role' => 'author',
            'email_verification_token' => $this->verifiedEmailToken('author_new@komibook.id.vn'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('authors', [
            'pen_name' => 'Tác Giả Mới',
            'status' => 'pending',
        ]);
    }

    /**
     * Test từ chối phê duyệt tác giả kèm lý do.
     */
    public function test_admin_author_rejection_with_reason()
    {
        $author = Author::create([
            'user_id' => $this->user->id,
            'pen_name' => 'Tác Giả Cũ',
            'bank_account_number' => '123',
            'bank_name' => 'Vietinbank',
            'bank_holder_name' => 'NGUYEN VAN B',
            'identity_document' => 'document.jpg',
            'status' => 'pending',
            'onboarding_status' => 'under_review',
            'terms_accepted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/admin/approvals/partners/author/{$author->id}/reject", [
                'reason' => 'Hồ sơ CCCD bị mờ, không rõ chữ.',
            ]);

        $response->assertStatus(200);
        $author->refresh();
        $this->assertEquals('rejected', $author->status);
        $this->assertEquals('Hồ sơ CCCD bị mờ, không rõ chữ.', $author->rejection_reason);
    }

    /**
     * Test duyệt tác giả không tự cấp quyền nhà bán hoặc kho hàng.
     */
    public function test_author_approval_does_not_grant_vendor_warehouse_access()
    {
        $author = Author::create([
            'user_id' => $this->user->id,
            'pen_name' => 'Tác Giả Có Kho',
            'bank_account_number' => '123',
            'bank_name' => 'Vietinbank',
            'bank_holder_name' => 'NGUYEN VAN B',
            'identity_document' => 'document.jpg',
            'phone_verified_at' => now(),
            'status' => 'pending',
            'onboarding_status' => 'under_review',
            'terms_accepted_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/admin/approvals/authors/{$author->id}/approve")
            ->assertOk();

        $this->user->refresh();
        $this->assertSame('customer', $this->user->role);
        $this->assertNull($this->user->vendor);
        $this->assertSame('approved', $author->fresh()->onboarding_status->value);

        $this->actingAs($this->user)
            ->postJson('/api/vendor/warehouses', [
                'name' => 'Kho Sách Tác Giả 1',
                'address' => 'Số 1 Nguyễn Du',
                'capacity' => '50%',
                'status' => 'Hoạt động',
            ])->assertForbidden();
    }

    public function test_author_approval_requires_phone_verification()
    {
        $author = Author::create([
            'user_id' => $this->user->id,
            'pen_name' => 'Tác Giả Chưa Xác Minh',
            'bank_account_number' => '123',
            'bank_name' => 'Vietinbank',
            'bank_holder_name' => 'NGUYEN VAN C',
            'identity_document' => 'unverified-document.jpg',
            'status' => 'pending',
            'onboarding_status' => 'under_review',
            'terms_accepted_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/admin/approvals/authors/{$author->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tác giả phải xác minh số điện thoại trước khi được phê duyệt.');

        $this->assertSame('pending', $author->fresh()->status);
    }

    /**
     * Test đăng ký tài khoản có số điện thoại, giới tính, ngày sinh.
     */
    public function test_registration_with_phone_gender_birthday()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Nguyễn Văn Test',
            'email' => 'testphone@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0987654321',
            'gender' => 'male',
            'birthday' => '2000-01-01',
            'email_verification_token' => $this->verifiedEmailToken('testphone@gmail.com'),
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Đăng ký thành công.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'testphone@gmail.com',
            'phone' => '0987654321',
            'gender' => 'male',
            'birthday' => '2000-01-01',
        ]);
    }

    /**
     * Test đăng nhập bằng số điện thoại.
     */
    public function test_login_with_phone_number()
    {
        User::factory()->create([
            'email' => 'userphone@gmail.com',
            'phone' => '0123456789',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => '0123456789',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Đăng nhập thành công.',
            ]);
    }

    /**
     * Test đăng nhập/đăng ký qua Google an toàn bằng id_token & challenge_token.
     */
    public function test_google_social_login()
    {
        config(['services.google.client_id' => 'test_google_client_id_123']);

        // 1. Gửi request google-login kèm id_token của tài khoản chưa tồn tại
        $response1 = $this->postJson('/api/auth/google-login', [
            'id_token' => 'test_fake_google_token',
        ]);

        $response1->assertStatus(200)
            ->assertJson([
                'status' => 'needs_registration',
                'message' => 'Tài khoản Google chưa liên kết. Vui lòng hoàn tất thông tin đăng ký.',
            ]);

        $challengeToken = $response1->json('data.challenge_token');
        $this->assertNotNull($challengeToken);

        // 2. Hoàn tất đăng ký gửi kèm challenge_token đã xác minh từ Backend
        $response2 = $this->postJson('/api/auth/register', [
            'name' => 'Google User Custom Name',
            'password' => 'mycustompassword123',
            'password_confirmation' => 'mycustompassword123',
            'phone' => '0989888888',
            'gender' => 'female',
            'birthday' => '1995-10-10',
            'challenge_token' => $challengeToken,
        ]);

        $response2->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'googleuser@gmail.com',
            'google_id' => 'google_123456789',
            'phone' => '0989888888',
            'gender' => 'female',
            'birthday' => '1995-10-10',
        ]);

        // 3. Đăng nhập lại qua Google sau khi đã tạo tài khoản thành công
        $response3 = $this->postJson('/api/auth/google-login', [
            'id_token' => 'test_fake_google_token',
        ]);

        $response3->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Đăng nhập Google thành công.',
            ]);
    }

    /**
     * Test đăng nhập Google thất bại khi thiếu id_token hoặc challenge_token không hợp lệ.
     */
    public function test_google_login_invalid_requests()
    {
        // Gửi không có id_token -> Bị từ chối 422
        $this->postJson('/api/auth/google-login', [])->assertStatus(422);

        // Đăng ký với challenge_token giả -> Bị từ chối 422
        $this->postJson('/api/auth/register', [
            'name' => 'Fake User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'challenge_token' => 'invalid-challenge-uuid',
        ])->assertStatus(422);
    }

    /**
     * Test đăng ký tài khoản chỉ bằng số điện thoại (không điền email).
     */
    public function test_registration_with_phone_only_no_email()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Người Dùng SĐT',
            'phone' => '0912345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'gender' => 'male',
            'birthday' => '1990-05-05',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '0912345678',
            'email' => null,
            'gender' => 'male',
        ]);
    }

    /**
     * Test đăng ký tài khoản chỉ bằng email (không điền số điện thoại).
     */
    public function test_registration_with_email_only_no_phone()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Người Dùng Email',
            'email' => 'user_email_only@komibook.id.vn',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'email_verification_token' => $this->verifiedEmailToken('user_email_only@komibook.id.vn'),
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'user_email_only@komibook.id.vn',
            'phone' => null,
        ]);
    }

    /**
     * Test gửi và xác thực OTP cho số điện thoại chưa đăng ký.
     */
    public function test_phone_otp_flow_unregistered()
    {
        // 1. Gửi OTP
        $response1 = $this->postJson('/api/auth/phone/send-otp', [
            'phone' => '0989999999',
        ]);
        $response1->assertStatus(200);

        // 2. Lấy OTP từ test Cache
        $otp = Cache::get('test_otp_0989999999');
        $this->assertNotNull($otp);

        $response2 = $this->postJson('/api/auth/phone/verify-otp', [
            'phone' => '0989999999',
            'otp' => $otp,
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'status' => 'needs_registration',
                'message' => 'Số điện thoại hợp lệ. Vui lòng hoàn tất thông tin đăng ký.',
                'data' => [
                    'phone' => '0989999999',
                ],
            ]);
    }

    /**
     * Test gửi và xác thực OTP đăng nhập cho số điện thoại đã đăng ký.
     */
    public function test_phone_otp_flow_registered()
    {
        // Tạo trước tài khoản
        $registeredUser = User::factory()->create([
            'phone' => '0988888888',
        ]);
        $author = Author::create([
            'user_id' => $registeredUser->id,
            'pen_name' => 'OTP Author',
            'bank_account_number' => '123',
            'bank_name' => 'Test Bank',
            'bank_holder_name' => 'OTP AUTHOR',
            'identity_document' => 'otp-author.jpg',
            'status' => 'pending',
        ]);

        // 1. Gửi OTP
        $response1 = $this->postJson('/api/auth/phone/send-otp', [
            'phone' => '0988888888',
        ]);
        $response1->assertStatus(200);

        // 2. Lấy OTP từ test Cache
        $otp = Cache::get('test_otp_0988888888');
        $this->assertNotNull($otp);

        $response2 = $this->postJson('/api/auth/phone/verify-otp', [
            'phone' => '0988888888',
            'otp' => $otp,
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Xác thực số điện thoại thành công.',
            ]);
        $this->assertNotNull($author->fresh()->phone_verified_at);
    }

    /**
     * Test nhập sai mã OTP số điện thoại.
     */
    public function test_phone_otp_flow_invalid_code()
    {
        $response = $this->postJson('/api/auth/phone/verify-otp', [
            'phone' => '0987777777',
            'otp' => '99999999', // OTP sai
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Mã OTP không chính xác hoặc đã hết hạn.',
            ]);
    }

    /**
     * Test mã OTP hardcode bị từ chối và rate limit khi gửi quá nhanh.
     */
    public function test_otp_security_hardened()
    {
        // 1. Gửi OTP cho sĐT
        $this->postJson('/api/auth/phone/send-otp', ['phone' => '0977111222'])->assertStatus(200);

        // 2. Thử gửi lại ngay lập tức -> Bị rate limit 429
        $this->postJson('/api/auth/phone/send-otp', ['phone' => '0977111222'])->assertStatus(429);

        // 3. Thử dùng mã 8 chữ số cố định -> Bị từ chối 422
        $this->postJson('/api/auth/phone/verify-otp', [
            'phone' => '0977111222',
            'otp' => '12345678',
        ])->assertStatus(422);

        // 4. Thử nhập sai 5 lần -> Bị khóa 429
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/phone/verify-otp', ['phone' => '0977111222', 'otp' => '00000000'])->assertStatus(422);
        }
        $this->postJson('/api/auth/phone/verify-otp', ['phone' => '0977111222', 'otp' => '00000000'])->assertStatus(429);
    }
}

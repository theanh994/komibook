<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Warehouse;
use App\Models\InventoryAudit;
use App\Models\StockTransfer;
use App\Models\SupportTicket;
use App\Models\MembershipTier;
use App\Models\Vendor;
use App\Models\Category;

class AuthorDrmInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $vendorUser;
    protected $vendor;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Tạo người dùng thường
        $this->user = User::factory()->create([
            'role' => 'customer'
        ]);

        // Tạo quản trị viên
        $this->admin = User::factory()->create([
            'role' => 'admin'
        ]);

        // Tạo vendor
        $this->vendorUser = User::factory()->create([
            'role' => 'vendor'
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
            'slug' => 'kinh-te'
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
                'identity_document' => $file
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Gửi yêu cầu đăng ký tác giả thành công! Chờ ban quản trị phê duyệt.'
            ]);

        $this->assertDatabaseHas('authors', [
            'pen_name' => 'Nam Cao',
            'user_id' => $this->user->id
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
            'file_path' => 'ebooks/demo.pdf'
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
            'payment_method' => 'online'
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'price' => 150000,
            'quantity' => 1
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/orders/{$order->id}/ebooks/{$book->id}/generate-link");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Đơn hàng chưa được thanh toán. Vui lòng thanh toán trước khi đọc.'
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
                        'discrepancy_reason' => 'Mất mát'
                    ]
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Lập phiếu kiểm kê nháp thành công.'
            ]);

        $this->assertDatabaseHas('inventory_audits', [
            'warehouse_id' => $warehouse->id,
            'audit_period' => '2026-07'
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
                        'quantity' => 5
                    ]
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Lập phiếu điều chuyển nháp thành công.'
            ]);

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id
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
                'benefits' => 'Freeship mọi đơn, phòng chờ VIP, hỗ trợ 24/7'
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('membership_tiers', [
            'name' => 'Kim Cương',
            'min_points' => 10000
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
                'message' => 'Tôi bấm thanh toán MoMo nhưng trang web báo lỗi không kết nối.'
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Gửi yêu cầu hỗ trợ thành công.'
            ]);

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Không thanh toán được qua ví điện tử',
            'user_id' => $this->user->id
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
            'benefits' => 'Giảm 10% đơn hàng'
        ]);

        // Cập nhật hạng thành viên của user thành Vàng
        $this->user->update([
            'membership_tier_id' => $goldTier->id,
            'points' => 2500
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
            'stock' => 100
        ]);

        // 3. Thực hiện checkout
        $response = $this->actingAs($this->user)
            ->postJson('/api/checkout', [
                'items' => [
                    [
                        'book_id' => $book->id,
                        'quantity' => 1
                    ]
                ],
                'shipping_address' => '123 VIP Street',
                'phone' => '0901234567',
                'payment_method' => 'cod'
            ]);

        $response->assertStatus(201);
        
        // 4. Kiểm tra đơn hàng được giảm giá 10% (còn 180,000đ)
        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(180000, $order->total_amount);

        // 5. Cập nhật trạng thái giao hàng thành công (delivered) để tích lũy điểm
        // Cần đóng vai trò Vendor để cập nhật trạng thái vận đơn
        $updateResponse = $this->actingAs($this->vendorUser)
            ->patchJson("/api/vendor/orders/{$order->id}/shipping", [
                'shipping_status' => 'delivered',
                'shipping_carrier' => 'GHTK'
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
            'desired_role' => 'author',
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
        $author = \App\Models\Author::create([
            'user_id' => $this->user->id,
            'pen_name' => 'Tác Giả Cũ',
            'bank_account_number' => '123',
            'bank_name' => 'Vietinbank',
            'bank_holder_name' => 'NGUYEN VAN B',
            'identity_document' => 'document.jpg',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/admin/approvals/partners/author/{$author->id}/reject", [
                'reason' => 'Hồ sơ CCCD bị mờ, không rõ chữ.'
            ]);

        $response->assertStatus(200);
        $author->refresh();
        $this->assertEquals('rejected', $author->status);
        $this->assertEquals('Hồ sơ CCCD bị mờ, không rõ chữ.', $author->rejection_reason);
    }

    /**
     * Test giới hạn kho hàng của tác giả tối đa là 1.
     */
    public function test_author_warehouse_limit()
    {
        // Phê duyệt tác giả để đổi role thành vendor
        $author = \App\Models\Author::create([
            'user_id' => $this->user->id,
            'pen_name' => 'Tác Giả Có Kho',
            'bank_account_number' => '123',
            'bank_name' => 'Vietinbank',
            'bank_holder_name' => 'NGUYEN VAN B',
            'identity_document' => 'document.jpg',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/api/admin/approvals/authors/{$author->id}/approve");

        // Bây giờ user đã là vendor có liên kết author
        $this->user->refresh();
        
        // Tạo kho hàng thứ nhất (OK)
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/vendor/warehouses', [
                'name' => 'Kho Sách Tác Giả 1',
                'address' => 'Số 1 Nguyễn Du',
                'capacity' => '50%',
                'status' => 'Hoạt động',
            ]);
        $response1->assertStatus(201);

        // Tạo kho hàng thứ hai (Bị từ chối vì là Tác giả)
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/vendor/warehouses', [
                'name' => 'Kho Sách Tác Giả 2',
                'address' => 'Số 2 Nguyễn Du',
                'capacity' => '50%',
                'status' => 'Hoạt động',
            ]);
        $response2->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Tác giả đối tác chỉ được sở hữu và đăng ký tối đa 1 nhà kho duy nhất.'
            ]);
    }
}

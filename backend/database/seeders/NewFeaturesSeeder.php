<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Author;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\HelpArticle;
use App\Models\MembershipTier;
use App\Models\Vendor;
use Illuminate\Support\Str;

class NewFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tạo Hạng thành viên (Membership Tiers)
        $tierSilver = MembershipTier::create([
            'name' => 'Bạc',
            'min_points' => 0,
            'discount_percent' => 0,
            'benefits' => 'Tích điểm đổi quà (1%), Theo dõi lịch sử đọc'
        ]);

        $tierGold = MembershipTier::create([
            'name' => 'Vàng',
            'min_points' => 2000,
            'discount_percent' => 10,
            'benefits' => 'Giảm 10% mọi đơn hàng, Voucher sinh nhật'
        ]);

        $tierDiamond = MembershipTier::create([
            'name' => 'Kim Cương (VIP)',
            'min_points' => 5000,
            'discount_percent' => 15,
            'benefits' => 'Miễn phí Vận chuyển, Quà tặng đặc quyền, Hỗ trợ 24/7'
        ]);

        // 2. Tạo Người dùng & Khách hàng
        $cust1 = User::create([
            'name' => 'Trần Thị Bích Ngọc',
            'email' => 'customer1@gmail.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'points' => 2500,
            'membership_tier_id' => $tierGold->id,
        ]);

        $cust2 = User::create([
            'name' => 'Nguyễn Minh Quân',
            'email' => 'customer2@gmail.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'points' => 100,
            'membership_tier_id' => $tierSilver->id,
        ]);

        // 3. Tạo Tác giả tự xuất bản (Approved & Pending)
        $authorUser1 = User::create([
            'name' => 'Tô Hoài',
            'email' => 'author1@gmail.com',
            'password' => bcrypt('password'),
            'role' => 'vendor',
        ]);

        $vendorAuthor = Vendor::create([
            'user_id' => $authorUser1->id,
            'shop_name' => 'Tác giả Tô Hoài',
            'slug' => Str::slug('Tác giả Tô Hoài'),
            'status' => 'active',
        ]);

        Author::create([
            'user_id' => $authorUser1->id,
            'pen_name' => 'Tô Hoài',
            'bio' => 'Nhà văn hiện thực xuất sắc với tác phẩm Dế Mèn Phiêu Lưu Ký.',
            'identity_document' => 'authors/cccd/demo1.jpg',
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '007100123456',
            'bank_holder_name' => 'TO HOAI',
            'status' => 'active'
        ]);

        $authorUser2 = User::create([
            'name' => 'Xuân Quỳnh',
            'email' => 'author2@gmail.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        Author::create([
            'user_id' => $authorUser2->id,
            'pen_name' => 'Xuân Quỳnh',
            'bio' => 'Nữ nhà thơ tình nổi tiếng Việt Nam.',
            'identity_document' => 'authors/cccd/demo2.jpg',
            'bank_name' => 'Agribank',
            'bank_account_number' => '1500205123456',
            'bank_holder_name' => 'NGUYEN THI XUAN QUYNH',
            'status' => 'pending'
        ]);

        // 4. Tạo Tickets hỗ trợ & Tin nhắn
        $ticket1 = SupportTicket::create([
            'user_id' => $cust1->id,
            'subject' => 'Lỗi giao dịch trừ tiền 2 lần qua MoMo',
            'category' => 'billing',
            'priority' => 'high',
            'status' => 'open'
        ]);

        TicketMessage::create([
            'support_ticket_id' => $ticket1->id,
            'sender_id' => $cust1->id,
            'message' => 'Chào ban hỗ trợ, tôi bấm thanh toán đơn hàng bằng MoMo, ví MoMo đã trừ tiền nhưng trang web báo giao dịch thất bại và đơn hàng vẫn ở trạng thái chờ.'
        ]);

        $ticket2 = SupportTicket::create([
            'user_id' => $cust2->id,
            'subject' => 'Cách tải sách Ebook đọc ngoại tuyến',
            'category' => 'technical',
            'priority' => 'medium',
            'status' => 'pending'
        ]);

        TicketMessage::create([
            'support_ticket_id' => $ticket2->id,
            'sender_id' => $cust2->id,
            'message' => 'Làm sao để tôi có thể tải file PDF về đọc trên ipad mà không cần kết nối mạng internet?'
        ]);

        // 5. Tạo FAQ Articles
        HelpArticle::create([
            'category_name' => 'Đọc sách',
            'title' => 'Tôi làm thế nào để đọc sách Ebook ngoại tuyến?',
            'content' => "Để đọc Ebook ngoại tuyến không cần mạng, bạn hãy thực hiện các bước sau:\n1. Mở ứng dụng KomiBook trên thiết bị của bạn.\n2. Truy cập vào mục \"Tủ sách của tôi\".\n3. Chọn cuốn sách mong muốn và nhấn nút \"Tải về đọc ngoại tuyến\".\n4. Hệ thống sẽ mã hóa và lưu trữ sách trực tiếp trên trình duyệt hoặc thiết bị của bạn để đọc ngay cả khi không có mạng.",
            'views_count' => 342,
            'helpful_count' => 25,
            'status' => 'published'
        ]);

        HelpArticle::create([
            'category_name' => 'Bản quyền & In ấn',
            'title' => 'Komibook hỗ trợ in ấn những tài liệu nào?',
            'content' => "Theo chính sách bảo vệ bản quyền DRM, bạn chỉ có thể in các trang sách giấy hoặc tài liệu mở được tác giả cho phép in.\nKhi bấm vào biểu tượng máy in trong Trình đọc sách, một bản Cam kết tôn trọng quyền sở hữu trí tuệ cơ bản sẽ xuất hiện. Bạn cần đồng ý với các điều khoản sở hữu trí tuệ để tiến hành in ấn.",
            'views_count' => 189,
            'helpful_count' => 15,
            'status' => 'published'
        ]);

        HelpArticle::create([
            'category_name' => 'Đối tác & Tác giả',
            'title' => 'Chính sách nhuận bút dành cho Tác giả tự xuất bản',
            'content' => "Komibook chia sẻ doanh thu lên tới 75% giá trị mỗi chương sách/ebook lẻ bán ra dành cho Tác giả đối tác.\nDoanh thu được đối soát tự động hàng tháng và thanh toán vào tài khoản ngân hàng của bạn từ ngày 05 đến 10 hàng tháng sau khi khấu trừ thuế thu nhập cá nhân theo quy định.",
            'views_count' => 512,
            'helpful_count' => 48,
            'status' => 'published'
        ]);
    }
}

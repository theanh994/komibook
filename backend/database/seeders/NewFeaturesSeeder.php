<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\MembershipTier;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tạo Hạng thành viên (Membership Tiers)
        $tierSilver = MembershipTier::firstOrCreate(
            ['name' => 'Bạc'],
            [
                'min_points' => 0,
                'discount_percent' => 0,
                'benefits' => 'Hạng khởi đầu dành cho thành viên mới, tích điểm KomiPoint và theo dõi lịch sử đọc sách',
            ]
        );

        $tierGold = MembershipTier::firstOrCreate(
            ['name' => 'Vàng'],
            [
                'min_points' => 2000,
                'discount_percent' => 10,
                'benefits' => 'Giảm 10% mọi đơn hàng, Voucher sinh nhật',
            ]
        );

        $tierDiamond = MembershipTier::firstOrCreate(
            ['name' => 'Kim Cương (VIP)'],
            [
                'min_points' => 5000,
                'discount_percent' => 15,
                'benefits' => 'Miễn phí Vận chuyển, Quà tặng đặc quyền, Hỗ trợ 24/7',
            ]
        );

        // 2. Tạo Người dùng & Khách hàng
        $cust1 = User::firstOrCreate(
            ['email' => 'customer1@gmail.com'],
            [
                'name' => 'Trần Thị Bích Ngọc',
                'password' => bcrypt('password'),
                'role' => 'customer',
                'points' => 2500,
                'membership_tier_id' => $tierGold->id,
            ]
        );

        $cust2 = User::firstOrCreate(
            ['email' => 'customer2@gmail.com'],
            [
                'name' => 'Nguyễn Minh Quân',
                'password' => bcrypt('password'),
                'role' => 'customer',
                'points' => 100,
                'membership_tier_id' => $tierSilver->id,
            ]
        );

        // 3. Tạo Nhà bán và khách hàng bán sách cũ
        $vendorUser = User::firstOrCreate(
            ['email' => 'vendor-demo@gmail.com'],
            [
                'name' => 'Nhà sách Dế Mèn',
                'password' => bcrypt('password'),
                'role' => 'vendor',
            ]
        );

        Vendor::firstOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'shop_name' => 'Nhà sách Dế Mèn',
                'slug' => Str::slug('Nhà sách Dế Mèn'),
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'used-book-seller@gmail.com'],
            [
                'name' => 'Khách hàng bán sách cũ',
                'password' => bcrypt('password'),
                'role' => 'customer',
            ]
        );

        // 4. Tạo Tickets hỗ trợ & Tin nhắn
        $ticket1 = SupportTicket::firstOrCreate(
            ['subject' => 'Cần kiểm tra trạng thái thanh toán VNPAY Sandbox'],
            [
                'user_id' => $cust1->id,
                'category' => 'billing',
                'priority' => 'high',
                'status' => 'open',
            ]
        );

        TicketMessage::create([
            'support_ticket_id' => $ticket1->id,
            'sender_id' => $cust1->id,
            'message' => 'Chào ban hỗ trợ, tôi đã thử thanh toán bằng VNPAY Sandbox nhưng đơn hàng vẫn ở trạng thái chờ. Nhờ kiểm tra giúp trạng thái giao dịch thử nghiệm.',
        ]);

        $ticket2 = SupportTicket::create([
            'user_id' => $cust2->id,
            'subject' => 'Cách tải sách Ebook đọc ngoại tuyến',
            'category' => 'technical',
            'priority' => 'medium',
            'status' => 'pending',
        ]);

        TicketMessage::create([
            'support_ticket_id' => $ticket2->id,
            'sender_id' => $cust2->id,
            'message' => 'Làm sao để tôi có thể tải file PDF về đọc trên ipad mà không cần kết nối mạng internet?',
        ]);

        // 5. Tạo FAQ Help Articles đầy đủ cho tất cả các danh mục
        $articles = [
            // Tài khoản & Bảo mật
            [
                'category_name' => 'Tài khoản & Bảo mật',
                'title' => 'Hướng dẫn đổi mật khẩu và kích hoạt bảo mật 2 lớp',
                'content' => "Để bảo vệ tài khoản KomiBook an toàn:\n1. Truy cập trang Hồ sơ cá nhân > Cài đặt bảo mật.\n2. Bấm \"Đổi mật khẩu\" và nhập mật khẩu hiện tại kèm mật khẩu mới.\n3. Kích hoạt bảo mật 2 lớp bằng mã OTP gửi về Email đã xác minh.\nLưu ý: Không bao giờ cung cấp mật khẩu hoặc mã OTP cho bất kỳ ai.",
                'views_count' => 284,
                'helpful_count' => 31,
                'status' => 'published',
            ],
            [
                'category_name' => 'Tài khoản & Bảo mật',
                'title' => 'Cách cập nhật thông tin cá nhân và địa chỉ nhận hàng',
                'content' => "Bạn có thể quản lý hồ sơ cá nhân dễ dàng:\n- Đổi tên hiển thị, ảnh đại diện và số điện thoại trong mục Hồ sơ cá nhân.\n- Quản lý Sổ địa chỉ: Thêm mới, sửa hoặc chọn địa chỉ mặc định để tự động điền khi đặt hàng.\n- Địa chỉ của người bán sách cũ được lưu riêng và bảo mật tuyệt đối.",
                'views_count' => 195,
                'helpful_count' => 18,
                'status' => 'published',
            ],

            // Đơn hàng & Vận chuyển
            [
                'category_name' => 'Đơn hàng & Vận chuyển',
                'title' => 'Phương thức thanh toán COD và VNPAY hoạt động thế nào?',
                'content' => "KomiBook hỗ trợ 2 hình thức thanh toán chính thức:\n1. COD (Thanh toán khi nhận hàng): Áp dụng cho sách vật lý và sách cũ. Khách trả tiền mặt cho shipper.\n2. Cổng VNPAY: Áp dụng cho cả sách vật lý lẫn E-book số. Hỗ trợ quét mã QR, chuyển khoản ngân hàng và thẻ ATM/VISA.",
                'views_count' => 412,
                'helpful_count' => 45,
                'status' => 'published',
            ],
            [
                'category_name' => 'Đơn hàng & Vận chuyển',
                'title' => 'Quy trình theo dõi hành trình vận chuyển đơn hàng',
                'content' => "Sau khi hoàn tất đặt hàng:\n1. Vào mục Lịch sử đơn hàng để xem trạng thái đơn (Chờ xác nhận ➔ Đang giao ➔ Thành công).\n2. Mỗi đơn hàng sách vật lý đều có Mã vận đơn kèm liên kết tra cứu thời gian thực từ đối tác vận chuyển.",
                'views_count' => 220,
                'helpful_count' => 22,
                'status' => 'published',
            ],

            // E-book & Trình đọc
            [
                'category_name' => 'E-book & Trình đọc',
                'title' => 'Tôi làm thế nào để đọc sách Ebook ngoại tuyến?',
                'content' => "Để đọc Ebook ngoại tuyến không cần mạng, bạn hãy thực hiện các bước sau:\n1. Mở ứng dụng hoặc trình duyệt KomiBook trên thiết bị của bạn.\n2. Truy cập vào mục \"Tủ sách của tôi\".\n3. Chọn cuốn sách mong muốn và nhấn nút \"Tải về đọc ngoại tuyến\".\n4. Hệ thống sẽ mã hóa và lưu trữ sách trực tiếp trên thiết bị của bạn để đọc ngay cả khi không có mạng.",
                'views_count' => 346,
                'helpful_count' => 25,
                'status' => 'published',
            ],
            [
                'category_name' => 'E-book & Trình đọc',
                'title' => 'Hướng dẫn chuyển đổi phiên bản Ebook mới sau tái bản',
                'content' => "Khi nhà xuất bản phát hành phiên bản tái bản mới hơn của cuốn Ebook bạn đã mua:\n- Tài khoản của bạn vẫn giữ bản tại thời điểm mua.\n- Trình đọc KomiBook tự động hiển thị nút \"Chuyển sang bản tái bản\" hoàn toàn miễn phí mà không mất thêm bất kỳ khoản phí nào.",
                'views_count' => 178,
                'helpful_count' => 14,
                'status' => 'published',
            ],

            // Sách cũ & Trả hàng
            [
                'category_name' => 'Sách cũ & Trả hàng',
                'title' => 'Quy trình 4 bước trả sách cũ và nhận lại tiền qua Ví KomiBook',
                'content' => "Khi sách cũ nhận được không đúng mô tả hoặc bị hỏng:\n1. Mở Lịch sử đơn hàng ➔ Chọn \"Yêu cầu trả hàng\" trong thời hạn chính sách snapshot.\n2. Đính kèm hình ảnh/video quay bóc hàng.\n3. Đơn vị vận chuyển đến lấy hàng tận nhà (địa chỉ người bán được bảo mật).\n4. Tự động hoàn tiền vào Ví KomiBook hoặc tài khoản thanh toán sau khi nghiệm thu.",
                'views_count' => 510,
                'helpful_count' => 62,
                'status' => 'published',
            ],
            [
                'category_name' => 'Sách cũ & Trả hàng',
                'title' => 'Làm sao để đăng nhượng lại sách cũ của cá nhân?',
                'content' => "Mọi thành viên KomiBook đều có thể đăng nhượng lại sách cá nhân:\n1. Truy cập Kênh Bán Sách Cũ (/used-books/manage).\n2. Chụp ảnh tình trạng sách, khai báo giá bán và số lượng.\n3. Cập nhật địa chỉ gửi hàng riêng tư để đơn vị vận chuyển lấy hàng khi có đơn đặt.",
                'views_count' => 389,
                'helpful_count' => 41,
                'status' => 'published',
            ],

            // Bản quyền & Hàng giả
            [
                'category_name' => 'Bản quyền & Hàng giả',
                'title' => 'KomiBook hỗ trợ in ấn & bảo vệ tài liệu bản quyền thế nào?',
                'content' => "Theo chính sách bảo vệ bản quyền DRM, bạn chỉ có thể in các trang sách giấy hoặc tài liệu được đơn vị nắm giữ quyền cho phép in.\nKhi bấm vào biểu tượng máy in trong Trình đọc sách, một bản Cam kết tôn trọng quyền sở hữu trí tuệ cơ bản sẽ xuất hiện. Bạn cần đồng ý với các điều khoản sở hữu trí tuệ để tiến hành in ấn.",
                'views_count' => 189,
                'helpful_count' => 15,
                'status' => 'published',
            ],
            [
                'category_name' => 'Bản quyền & Hàng giả',
                'title' => 'Nghi ngờ sách cũ là sách lậu / hàng giả thì xử lý thế nào?',
                'content' => "KomiBook cam kết bảo vệ tính xác thực của sách:\n- Nếu nhận phải sách nghi là giả/lậu, hãy bấm \"Mở tranh chấp hàng giả\" và gửi hình ảnh chứng minh.\n- Đội ngũ kiểm soát sẽ niêm phong giao dịch, thu hồi sản phẩm và hoàn tiền 100% cho bạn, đồng thời xử lý tài khoản vi phạm.",
                'views_count' => 276,
                'helpful_count' => 33,
                'status' => 'published',
            ],

            // Dành cho Nhà bán
            [
                'category_name' => 'Dành cho Nhà bán',
                'title' => 'Hướng dẫn đăng ký gian hàng chính thức trên KomiBook',
                'content' => "Các Nhà xuất bản, Công ty phát hành sách và Thương hiệu có thể đăng ký Gian hàng chính thức:\n1. Truy cập trang Đăng ký Nhà bán (/vendor/register).\n2. Cung cấp tên Gian hàng, giấy phép đăng ký kinh doanh/phát hành và thông tin liên hệ.\n3. Sau khi xác minh, bạn sẽ sở hữu Cổng Quản lý Gian hàng chuyên nghiệp.",
                'views_count' => 305,
                'helpful_count' => 29,
                'status' => 'published',
            ],
            [
                'category_name' => 'Dành cho Nhà bán',
                'title' => 'Quy định niêm yết Nhà xuất bản và xuất xứ sản phẩm',
                'content' => "Khi đăng bán sách hoặc E-book số:\n- Nhà bán phải khai báo chính xác Nhà xuất bản, đơn vị giữ bản quyền và mã định danh ISBN.\n- Mọi thông tin sai lệch về nguồn gốc xuất xứ đều bị xử lý theo Quy chế quản lý sản phẩm KomiBook.",
                'views_count' => 164,
                'helpful_count' => 19,
                'status' => 'published',
            ],
        ];

        foreach ($articles as $art) {
            HelpArticle::updateOrCreate(
                ['title' => $art['title']],
                $art
            );
        }

    }
}

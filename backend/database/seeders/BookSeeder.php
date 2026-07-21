<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy thông tin Vendor
        $vendor1 = Vendor::where('slug', 'nha-sach-tre')->first();
        $vendor2 = Vendor::where('slug', 'tiem-sach-cu')->first();
        
        $categoriesMap = Category::all()->keyBy('slug');
        $getCatId = function($slug) use ($categoriesMap) {
            return $categoriesMap->get($slug)?->id ?? $categoriesMap->first()?->id;
        };

        // 15 cuốn sách cho Vendor 1
        $vendor1Books = [
            [
                'title' => 'Đắc Nhân Tâm',
                'author' => 'Dale Carnegie',
                'price' => 85000,
                'stock' => 100,
                'category_slug' => 'ky-nang-song',
                'cover_image' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cuốn sách đưa ra các lời khuyên về cách thức cư xử, ứng xử và giao tiếp với mọi người để đạt được thành công trong cuộc sống.'
            ],
            [
                'title' => 'Nhà Lãnh Đạo Không Chức Danh',
                'author' => 'Robin Sharma',
                'price' => 90000,
                'stock' => 50,
                'category_slug' => 'kinh-te',
                'cover_image' => 'https://images.unsplash.com/photo-1512820790803-83ca734da794?q=80&w=600&auto=format&fit=crop',
                'description' => 'Một cuốn sách tuyệt vời chỉ ra rằng bất kể bạn đang làm việc ở vị trí nào, bạn đều có thể trở thành một nhà lãnh đạo xuất chúng.'
            ],
            [
                'title' => 'Sapiens: Lược Sử Loài Người',
                'author' => 'Yuval Noah Harari',
                'price' => 120000,
                'stock' => 30,
                'category_slug' => 'van-hoc',
                'cover_image' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cuốn sách lược khảo lịch sử loài người từ thời kỳ đồ đá cho đến thế kỷ 21, mang lại cái nhìn sâu sắc về văn hóa và xã hội.'
            ],
            [
                'title' => 'Lập Trình Web Với PHP & MySQL',
                'author' => 'Nhiều Tác Giả',
                'price' => 150000,
                'stock' => 45,
                'category_slug' => 'cong-nghe',
                'cover_image' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=600&auto=format&fit=crop',
                'description' => 'Hướng dẫn chi tiết từ cơ bản đến nâng cao để thiết kế và xây dựng các website động với PHP và cơ sở dữ liệu MySQL.'
            ],
            [
                'title' => 'Cha Giàu Cha Nghèo',
                'author' => 'Robert T. Kiyosaki',
                'price' => 75000,
                'stock' => 200,
                'category_slug' => 'kinh-te',
                'cover_image' => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cuốn sách kinh điển thay đổi tư duy tài chính của hàng triệu người, giúp bạn hiểu rõ sự khác biệt giữa tài sản và tiêu sản.'
            ],
            [
                'title' => 'Clean Code: Handbook of Agile Software',
                'author' => 'Robert C. Martin',
                'price' => 210000,
                'stock' => 25,
                'category_slug' => 'cong-nghe',
                'cover_image' => 'https://images.unsplash.com/photo-1605379399642-870262d3d051?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cẩm nang tuyệt vời giúp lập trình viên viết code sạch, dễ đọc, dễ phát triển và bảo trì trong các dự án chuyên nghiệp.'
            ],
            [
                'title' => 'The Pragmatic Programmer',
                'author' => 'Andrew Hunt',
                'price' => 195000,
                'stock' => 30,
                'category_slug' => 'cong-nghe',
                'cover_image' => 'https://images.unsplash.com/photo-1618401471353-b98aedd07871?q=80&w=600&auto=format&fit=crop',
                'description' => 'Những bài học thực tế, lời khuyên vô giá dành cho lập trình viên để cải tiến tư duy thiết kế phần mềm và phát triển sự nghiệp.'
            ],
            [
                'title' => 'Design Patterns: Reusable Object-Oriented Software',
                'author' => 'Erich Gamma',
                'price' => 280000,
                'stock' => 20,
                'category_slug' => 'cong-nghe',
                'cover_image' => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cuốn sách gối đầu giường giới thiệu 23 mẫu thiết kế kinh điển giúp giải quyết các vấn đề thiết kế hướng đối tượng phổ biến.'
            ],
            [
                'title' => 'Introduction to Algorithms',
                'author' => 'Thomas H. Cormen',
                'price' => 350000,
                'stock' => 15,
                'category_slug' => 'cong-nghe',
                'cover_image' => 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cuốn tài liệu đầy đủ và chuyên sâu nhất về giải thuật, cấu trúc dữ liệu và phân tích thuật toán ứng dụng trong khoa học máy tính.'
            ],
            [
                'title' => 'Think and Grow Rich',
                'author' => 'Napoleon Hill',
                'price' => 85000,
                'stock' => 80,
                'category_slug' => 'kinh-te',
                'cover_image' => 'https://images.unsplash.com/photo-1543269865-cbf427effbad?q=80&w=600&auto=format&fit=crop',
                'description' => 'Được viết sau hơn 20 năm nghiên cứu các cá nhân thành công vượt bậc, đây là triết lý làm giàu hàng đầu mọi thời đại.'
            ],
            [
                'title' => 'The Intelligent Investor',
                'author' => 'Benjamin Graham',
                'price' => 185000,
                'stock' => 40,
                'category_slug' => 'kinh-te',
                'cover_image' => 'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cẩm nang hướng dẫn đầu tư giá trị kinh điển giúp các nhà đầu tư tránh các sai lầm nghiêm trọng trên thị trường chứng khoán.'
            ],
            [
                'title' => 'Zero to One',
                'author' => 'Peter Thiel',
                'price' => 115000,
                'stock' => 60,
                'category_slug' => 'kinh-te',
                'cover_image' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cuốn sách của nhà sáng lập PayPal chia sẻ những quan điểm độc đáo về khởi nghiệp, đổi mới sáng tạo và kiến tạo tương lai.'
            ],
            [
                'title' => 'The Lean Startup',
                'author' => 'Eric Ries',
                'price' => 145000,
                'stock' => 70,
                'category_slug' => 'kinh-te',
                'cover_image' => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?q=80&w=600&auto=format&fit=crop',
                'description' => 'Mô hình khởi nghiệp tinh gọn giúp các doanh nghiệp phát triển sản phẩm thông qua phản hồi khách hàng liên tục.'
            ],
            [
                'title' => 'The 7 Habits of Highly Effective People',
                'author' => 'Stephen R. Covey',
                'price' => 160000,
                'stock' => 90,
                'category_slug' => 'ky-nang-song',
                'cover_image' => 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?q=80&w=600&auto=format&fit=crop',
                'description' => 'Giới thiệu 7 thói quen cốt lõi để nâng cao năng lực bản thân, xây dựng mối quan hệ hài hòa và phát triển bền vững.'
            ],
            [
                'title' => 'Atomic Habits',
                'author' => 'James Clear',
                'price' => 150000,
                'stock' => 110,
                'category_slug' => 'ky-nang-song',
                'cover_image' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?q=80&w=600&auto=format&fit=crop',
                'description' => 'Phương pháp cực kỳ khoa học giúp bạn xây dựng các thói quen tốt nhỏ bé hàng ngày để tạo nên những kết quả to lớn lâu dài.'
            ],
        ];

        // 15 cuốn sách cho Vendor 2
        $vendor2Books = [
            [
                'title' => 'Tôi Thấy Hoa Vàng Trên Cỏ Xanh',
                'author' => 'Nguyễn Nhật Ánh',
                'price' => 60000,
                'stock' => 10,
                'category_slug' => 'van-hoc',
                'cover_image' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?q=80&w=600&auto=format&fit=crop',
                'description' => 'Câu chuyện cảm động về tình anh em, tình bạn tuổi thơ ở vùng quê nghèo với những góc nhìn trong trẻo, hồn nhiên.'
            ],
            [
                'title' => 'Mắt Biếc',
                'author' => 'Nguyễn Nhật Ánh',
                'price' => 65000,
                'stock' => 15,
                'category_slug' => 'van-hoc',
                'cover_image' => 'https://images.unsplash.com/photo-1516589178581-6cd7833ae3b2?q=80&w=600&auto=format&fit=crop',
                'description' => 'Tác phẩm văn học lãng mạn đầy nuối tiếc kể về tình yêu đơn phương bền bỉ của Ngạn dành cho Hà Lan - cô gái có đôi mắt biếc.'
            ],
            [
                'title' => 'Sách Giáo Khoa Toán Lớp 10',
                'author' => 'Bộ GD-ĐT',
                'price' => 25000,
                'stock' => 150,
                'category_slug' => 'sach-giao-khoa',
                'cover_image' => 'https://images.unsplash.com/photo-1509228468518-180dd4864904?q=80&w=600&auto=format&fit=crop',
                'description' => 'Sách giáo khoa chuẩn mực trang bị kiến thức toán học đại số và hình học cơ bản cho học sinh lớp 10 trung học phổ thông.'
            ],
            [
                'title' => 'Sách Giáo Khoa Ngữ Văn 12',
                'author' => 'Bộ GD-ĐT',
                'price' => 30000,
                'stock' => 100,
                'category_slug' => 'sach-giao-khoa',
                'cover_image' => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?q=80&w=600&auto=format&fit=crop',
                'description' => 'Tuyển tập các tác phẩm văn học Việt Nam và quốc tế chọn lọc trong chương trình phổ thông dành cho học sinh cuối cấp.'
            ],
            [
                'title' => 'Bí Mật Tư Duy Triệu Phú',
                'author' => 'T. Harv Eker',
                'price' => 88000,
                'stock' => 60,
                'category_slug' => 'kinh-te',
                'cover_image' => 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?q=80&w=600&auto=format&fit=crop',
                'description' => 'Khám phá bí mật về kế hoạch tài chính trong tâm thức giúp bạn lập trình lại tư duy để đạt được sự giàu có bền vững.'
            ],
            [
                'title' => 'The Power of Habit',
                'author' => 'Charles Duhigg',
                'price' => 125000,
                'stock' => 45,
                'category_slug' => 'ky-nang-song',
                'cover_image' => 'https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?q=80&w=600&auto=format&fit=crop',
                'description' => 'Giải mã cách thức hoạt động của thói quen trong cuộc sống, tổ chức và xã hội, và làm thế nào để thay đổi chúng.'
            ],
            [
                'title' => 'Nhà Giả Kim',
                'author' => 'Paulo Coelho',
                'price' => 79000,
                'stock' => 120,
                'category_slug' => 'van-hoc',
                'cover_image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cuốn tiểu thuyết huyền thoại chứa đựng triết lý sâu sắc về việc theo đuổi ước mơ và lắng nghe tiếng nói từ trái tim.'
            ],
            [
                'title' => 'Hoàng Tử Bé',
                'author' => 'Antoine de Saint-Exupéry',
                'price' => 55000,
                'stock' => 140,
                'category_slug' => 'van-hoc',
                'cover_image' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=600&auto=format&fit=crop',
                'description' => 'Một kiệt tác đầy chất thơ kể về hành trình thám hiểm vũ trụ và những khám phá ấm áp về cuộc sống của một cậu bé.'
            ],
            [
                'title' => 'Chiến Tranh Và Hòa Bình',
                'author' => 'Leo Tolstoy',
                'price' => 250000,
                'stock' => 15,
                'category_slug' => 'van-hoc',
                'cover_image' => 'https://images.unsplash.com/photo-1461360370896-922624d12aa1?q=80&w=600&auto=format&fit=crop',
                'description' => 'Thiên tiểu thuyết vĩ đại phản ánh chân thực cuộc sống Nga trong giai đoạn lịch sử hào hùng chống lại Napoleon.'
            ],
            [
                'title' => 'Trăm Năm Cô Đơn',
                'author' => 'Gabriel García Márquez',
                'price' => 140000,
                'stock' => 22,
                'category_slug' => 'van-hoc',
                'cover_image' => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?q=80&w=600&auto=format&fit=crop',
                'description' => 'Tác phẩm hiện thực huyền ảo xuất sắc khắc họa cuộc sống của nhiều thế hệ dòng họ Buendía ở làng Macondo.'
            ],
            [
                'title' => 'Sách Giáo Khoa Hóa Học 11',
                'author' => 'Bộ GD-ĐT',
                'price' => 28000,
                'stock' => 130,
                'category_slug' => 'sach-giao-khoa',
                'cover_image' => 'https://images.unsplash.com/photo-1532187643603-ba119ca4109e?q=80&w=600&auto=format&fit=crop',
                'description' => 'Trang bị các kiến thức về hóa học hữu cơ và vô cơ nâng cao cho học sinh trung học phổ thông theo chương trình chuẩn.'
            ],
            [
                'title' => 'Sách Giáo Khoa Vật Lý 12',
                'author' => 'Bộ GD-ĐT',
                'price' => 32000,
                'stock' => 110,
                'category_slug' => 'sach-giao-khoa',
                'cover_image' => 'https://images.unsplash.com/photo-1614064641938-3bbee52942c7?q=80&w=600&auto=format&fit=crop',
                'description' => 'Nội dung xoay quanh dao động cơ, sóng cơ, dòng điện xoay chiều và vật lý hạt nhân dành cho học sinh chuẩn bị tốt nghiệp.'
            ],
            [
                'title' => 'Sách Giáo Khoa Lịch Sử 12',
                'author' => 'Bộ GD-ĐT',
                'price' => 26000,
                'stock' => 115,
                'category_slug' => 'sach-giao-khoa',
                'cover_image' => 'https://images.unsplash.com/photo-1447069387593-a5de0862481e?q=80&w=600&auto=format&fit=crop',
                'description' => 'Hệ thống hóa toàn bộ tiến trình lịch sử Việt Nam và thế giới từ năm 1945 đến đầu thế kỷ 21.'
            ],
            [
                'title' => 'Sách Giáo Khoa Tiếng Anh 12',
                'author' => 'Bộ GD-ĐT',
                'price' => 35000,
                'stock' => 95,
                'category_slug' => 'sach-giao-khoa',
                'cover_image' => 'https://images.unsplash.com/photo-1546410531-bb4caa6b424d?q=80&w=600&auto=format&fit=crop',
                'description' => 'Phát triển toàn diện 4 kỹ năng Nghe, Nói, Đọc, Viết theo các chủ đề bổ ích dành cho học sinh lớp 12.'
            ],
            [
                'title' => 'Lược Sử Thời Gian',
                'author' => 'Stephen Hawking',
                'price' => 115000,
                'stock' => 40,
                'category_slug' => 'van-hoc',
                'cover_image' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=600&auto=format&fit=crop',
                'description' => 'Cuốn sách khoa học vũ trụ học phổ thông giải thích các khái niệm phức tạp như lỗ đen, không thời gian và Vụ nổ lớn.'
            ],
        ];

        foreach ($vendor1Books as $book) {
            Book::create([
                'vendor_id'   => $vendor1->id,
                'category_id' => $getCatId($book['category_slug']),
                'title'       => $book['title'],
                'slug'        => Str::slug($book['title']) . '-' . rand(1000, 9999),
                'author'      => $book['author'],
                'price'       => $book['price'],
                'stock'       => $book['stock'],
                'status'      => 'published',
                'cover_image' => 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=600&auto=format&fit=crop',
                'description' => $book['description'],
            ]);
        }

        foreach ($vendor2Books as $book) {
            Book::create([
                'vendor_id'   => $vendor2->id,
                'category_id' => $getCatId($book['category_slug']),
                'title'       => $book['title'],
                'slug'        => Str::slug($book['title']) . '-' . rand(1000, 9999),
                'author'      => $book['author'],
                'price'       => $book['price'],
                'stock'       => $book['stock'],
                'status'      => 'published',
                'cover_image' => 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=600&auto=format&fit=crop',
                'description' => $book['description'],
            ]);
        }
    }
}

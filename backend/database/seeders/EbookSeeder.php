<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Book;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;

class EbookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Lấy ngẫu nhiên 1 vendor
        $vendor = Vendor::first();
        if (!$vendor) {
            $this->command->error('Vui lòng tạo ít nhất 1 Vendor trước khi chạy seeder này.');
            return;
        }

        // Lấy hoặc tạo Category
        $category = \App\Models\Category::firstOrCreate(
            ['slug' => 'ebooks'],
            ['name' => 'Sách Điện Tử']
        );

        // 2. Tạo 10 cuốn Ebook
        $ebooks = [];
        for ($i = 1; $i <= 10; $i++) {
            $ebooks[] = Book::create([
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'title' => 'Sách Ebook Memory Phần ' . $i,
                'slug' => Str::slug('Sách Ebook Memory Phần ' . $i . '-' . uniqid()),
                'author' => 'Thiên Long',
                'description' => 'Mô tả giả lập cho phần ' . $i,
                'price' => 50000,
                'stock' => 999,
                'type' => 'ebook',
                'status' => 'published',
                'file_path' => 'ebooks/Memory' . $i . ' - ThinLongNguyn6.pdf',
            ]);
        }
        $this->command->info('Đã tạo 10 cuốn Ebook thành công.');

        // 3. Lấy các User để tạo đơn hàng mua Ebook ảo
        $users = User::whereIn('email', ['admin@komibook.id.vn', 'customer1@gmail.com'])->get();
        if ($users->isEmpty()) {
            $users = User::take(1)->get();
        }

        foreach ($users as $u) {
            // 4. Tạo Order ảo
            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'user_id' => $u->id,
                'vendor_id' => $vendor->id,
                'total_amount' => 50000 * 10,
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'cod',
                'shipping_address' => 'Nhà riêng',
                'phone' => '0123456789',
            ]);

            // 5. Thêm 10 OrderItems
            foreach ($ebooks as $ebook) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id' => $ebook->id,
                    'quantity' => 1,
                    'price' => $ebook->price,
                ]);
            }
            $this->command->info('Đã thêm 10 Ebook vào Tủ sách của User: ' . $u->email);
        }
    }
}

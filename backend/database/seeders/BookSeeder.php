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
        // Lấy thông tin Vendor vừa tạo ở UserSeeder
        $vendor1 = Vendor::where('slug', 'nha-sach-tre')->first();
        $vendor2 = Vendor::where('slug', 'tiem-sach-cu')->first();
        
        $categories = Category::all();

        // 5 cuốn sách chuẩn - Vendor 1
        $vendor1Books = [
            ['title' => 'Đắc Nhân Tâm', 'author' => 'Dale Carnegie', 'price' => 85000, 'stock' => 100],
            ['title' => 'Nhà Lãnh Đạo Không Chức Danh', 'author' => 'Robin Sharma', 'price' => 90000, 'stock' => 50],
            ['title' => 'Sapiens: Lược Sử Loài Người', 'author' => 'Yuval Noah Harari', 'price' => 120000, 'stock' => 30],
            ['title' => 'Lập Trình Web Với PHP & MySQL', 'author' => 'Nhiều Tác Giả', 'price' => 150000, 'stock' => 45],
            ['title' => 'Cha Giàu Cha Nghèo', 'author' => 'Robert T. Kiyosaki', 'price' => 75000, 'stock' => 200],
        ];

        // 5 cuốn sách chuẩn - Vendor 2
        $vendor2Books = [
            ['title' => 'Tôi Thấy Hoa Vàng Trên Cỏ Xanh', 'author' => 'Nguyễn Nhật Ánh', 'price' => 60000, 'stock' => 10],
            ['title' => 'Mắt Biếc', 'author' => 'Nguyễn Nhật Ánh', 'price' => 65000, 'stock' => 15],
            ['title' => 'Sách Giáo Khoa Toán Lớp 10', 'author' => 'Bộ GD-ĐT', 'price' => 25000, 'stock' => 150],
            ['title' => 'Sách Giáo Khoa Ngữ Văn 12', 'author' => 'Bộ GD-ĐT', 'price' => 30000, 'stock' => 100],
            ['title' => 'Bí Mật Tư Duy Triệu Phú', 'author' => 'T. Harv Eker', 'price' => 88000, 'stock' => 60],
        ];

        // Do CLI (Seeder) không có user đăng nhập, nên tính năng tự gán vendor_id trong MultiVendorScoped Trait
        // sẽ không được kích hoạt => ta phải gán vendor_id thẳng vào Model khởi tạo.

        foreach ($vendor1Books as $book) {
            Book::create([
                'vendor_id'   => $vendor1->id,
                'category_id' => $categories->random()->id,
                'title'       => $book['title'],
                'slug'        => Str::slug($book['title']) . '-' . rand(1000, 9999), // Tránh trùng slug lặp title chung sau này
                'author'      => $book['author'],
                'price'       => $book['price'],
                'stock'       => $book['stock'],
                'status'      => 'published',
                'description' => 'Mô tả hấp dẫn cho cuốn sách ' . $book['title'],
            ]);
        }

        foreach ($vendor2Books as $book) {
            Book::create([
                'vendor_id'   => $vendor2->id,
                'category_id' => $categories->random()->id,
                'title'       => $book['title'],
                'slug'        => Str::slug($book['title']) . '-' . rand(1000, 9999), // Tránh trùng lặp slug
                'author'      => $book['author'],
                'price'       => $book['price'],
                'stock'       => $book['stock'],
                'status'      => 'published',
                'description' => 'Mô tả hấp dẫn cho cuốn sách ' . $book['title'],
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\PayoutRequest;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Database\Seeder;

class WarehouseAndFinanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Lấy Vendor Nhà sách Trẻ (của vendor1@gmail.com)
        $vendor = Vendor::where('slug', 'nha-sach-tre')->first();
        if (!$vendor) {
            $this->command->error('Vui lòng chạy UserSeeder trước!');
            return;
        }

        // Cập nhật số dư và số tiền đã rút cho Vendor 1
        $vendor->update([
            'balance' => 45500000,
            'total_withdrawn' => 128400000,
        ]);

        // 2. Tạo 3 kho hàng
        $w1 = Warehouse::create([
            'vendor_id' => $vendor->id,
            'name' => 'Kho Trung tâm TP.HCM',
            'address' => '123 Nguyễn Văn Linh, Quận 7, TP.HCM',
            'capacity' => '85%',
            'status' => 'Hoạt động',
        ]);

        $w2 = Warehouse::create([
            'vendor_id' => $vendor->id,
            'name' => 'Kho Vệ tinh Hà Nội',
            'address' => '456 Lê Duẩn, Đống Đa, Hà Nội',
            'capacity' => '60%',
            'status' => 'Hoạt động',
        ]);

        $w3 = Warehouse::create([
            'vendor_id' => $vendor->id,
            'name' => 'Kho Lưu trữ Đà Nẵng',
            'address' => '789 Nguyễn Hữu Thọ, Hải Châu, Đà Nẵng',
            'capacity' => '0%',
            'status' => 'Bảo trì',
        ]);

        // 3. Phân bổ tồn kho cho các sách
        $dacNhanTam = Book::where('vendor_id', $vendor->id)->where('title', 'LIKE', '%Đắc Nhân Tâm%')->first();
        if ($dacNhanTam) {
            // Cập nhật SKU và tổng tồn kho của sách
            $dacNhanTam->update([
                'isbn' => 'SKU-8921-A',
                'stock' => 145,
            ]);

            WarehouseStock::create([
                'warehouse_id' => $w1->id,
                'book_id' => $dacNhanTam->id,
                'quantity' => 100,
                'shelf_location' => 'Kệ A2',
            ]);

            WarehouseStock::create([
                'warehouse_id' => $w2->id,
                'book_id' => $dacNhanTam->id,
                'quantity' => 45,
                'shelf_location' => 'Kệ B4',
            ]);
        }

        $lanhDao = Book::where('vendor_id', $vendor->id)->where('title', 'LIKE', '%Nhà Lãnh Đạo%')->first();
        if ($lanhDao) {
            $lanhDao->update([
                'isbn' => 'ISBN-978-0',
                'stock' => 8,
            ]);

            WarehouseStock::create([
                'warehouse_id' => $w2->id,
                'book_id' => $lanhDao->id,
                'quantity' => 8,
                'shelf_location' => 'Kệ C1',
            ]);
        }

        // Ebook cho vendor (Hết hàng mẫu để test)
        $phpBook = Book::where('vendor_id', $vendor->id)->where('title', 'LIKE', '%PHP%')->first();
        if ($phpBook) {
            $phpBook->update([
                'isbn' => 'EBK-2023-X',
                'type' => 'ebook',
                'stock' => 0,
                'status' => 'out_of_stock',
            ]);
        }

        // 4. Tạo yêu cầu rút tiền (Payout Requests)
        PayoutRequest::create([
            'vendor_id' => $vendor->id,
            'amount' => 15000000,
            'bank_name' => 'Vietcombank',
            'account_number' => '0071000123456',
            'account_name' => 'CHU SHOP NHA SACH TRE',
            'status' => 'pending',
            'created_at' => now()->subDays(3),
        ]);

        PayoutRequest::create([
            'vendor_id' => $vendor->id,
            'amount' => 20500000,
            'bank_name' => 'Vietcombank',
            'account_number' => '0071000123456',
            'account_name' => 'CHU SHOP NHA SACH TRE',
            'status' => 'completed',
            'created_at' => now()->subMonth(),
        ]);

        PayoutRequest::create([
            'vendor_id' => $vendor->id,
            'amount' => 10000000,
            'bank_name' => 'Techcombank',
            'account_number' => '1903456789012',
            'account_name' => 'CHU SHOP NHA SACH TRE',
            'status' => 'completed',
            'created_at' => now()->subMonths(2),
        ]);
    }
}

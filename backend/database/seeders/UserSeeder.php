<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin
        User::create([
            'name'     => 'Administrator',
            'email'    => 'admin@komibook.id.vn',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        // 2. Vendor 1
        $vendor1 = User::create([
            'name'     => 'Chủ shop Nhà sách Trẻ',
            'email'    => 'vendor1@gmail.com',
            'password' => bcrypt('password'),
            'role'     => 'vendor',
        ]);
        
        $vendor1->vendor()->create([
            'shop_name' => 'Nhà sách Trẻ',
            'slug'      => Str::slug('Nhà sách Trẻ'),
            'status'    => 'active',
        ]);

        // 3. Vendor 2
        $vendor2 = User::create([
            'name'     => 'Chủ shop Tiệm sách Cũ',
            'email'    => 'vendor2@gmail.com',
            'password' => bcrypt('password'),
            'role'     => 'vendor',
        ]);

        $vendor2->vendor()->create([
            'shop_name' => 'Tiệm sách Cũ',
            'slug'      => Str::slug('Tiệm sách Cũ'),
            'status'    => 'active',
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProvisionDemoAccounts extends Command
{
    public const CREDENTIALS_PATH = 'demo-credentials/nxb-and-warehouse-accounts-20260731.csv';

    private const PRODUCTION_ACKNOWLEDGEMENT = 'KOMIBOOK_DEMO_ACCOUNTS_20260731';

    private const ACCOUNTS = [
        ['name' => 'Nhà xuất bản Lao Động (Demo)', 'email' => 'nxblaodong.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Nhà xuất bản Trẻ (Demo)', 'email' => 'nxbtre.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Nhà xuất bản Hà Nội (Demo)', 'email' => 'nxbhanoi.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Nhà xuất bản Giáo Dục (Demo)', 'email' => 'nxbgiaoduc.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 01', 'email' => 'quanlykho01.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 02', 'email' => 'quanlykho02.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 03', 'email' => 'quanlykho03.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 04', 'email' => 'quanlykho04.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 05', 'email' => 'quanlykho05.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 06', 'email' => 'quanlykho06.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 07', 'email' => 'quanlykho07.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 08', 'email' => 'quanlykho08.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 09', 'email' => 'quanlykho09.demo@komibook.id.vn', 'role' => 'customer'],
        ['name' => 'Quản kho Demo 10', 'email' => 'quanlykho10.demo@komibook.id.vn', 'role' => 'customer'],
    ];

    protected $signature = 'demo:provision-accounts
        {--dry-run : Chỉ kiểm tra và hiển thị tài khoản sẽ tạo}
        {--production-ack= : Xác nhận bắt buộc khi chạy trên production}';

    protected $description = 'Tạo an toàn 4 tài khoản NXB chờ onboarding và 10 tài khoản Quản kho demo';

    public function handle(): int
    {
        if (app()->environment('production')
            && $this->option('production-ack') !== self::PRODUCTION_ACKNOWLEDGEMENT) {
            $this->error('Thiếu xác nhận rõ ràng để tạo tài khoản demo trên production.');

            return self::FAILURE;
        }

        $emails = array_column(self::ACCOUNTS, 'email');
        $existingEmails = User::query()->whereIn('email', $emails)->pluck('email')->all();
        if ($existingEmails !== []) {
            $allAccountsExist = count($existingEmails) === count(self::ACCOUNTS);
            if ($allAccountsExist && Storage::disk('private')->exists(self::CREDENTIALS_PATH)) {
                $this->info('Đợt tài khoản demo này đã được tạo; không có dữ liệu nào bị thay đổi.');
                $this->line('Tệp thông tin đăng nhập: '.self::CREDENTIALS_PATH);

                return self::SUCCESS;
            }

            $this->error('Phát hiện tài khoản demo trùng hoặc đợt tạo dở dang. Không thực hiện ghi dữ liệu.');

            return self::FAILURE;
        }

        $this->table(
            ['Tên hiển thị', 'Email', 'Vai trò'],
            array_map(fn (array $account) => [$account['name'], $account['email'], $account['role']], self::ACCOUNTS),
        );

        if ($this->option('dry-run')) {
            $this->info('Dry-run hoàn tất: sẽ tạo 4 tài khoản NXB chờ onboarding và 10 tài khoản Quản kho.');

            return self::SUCCESS;
        }

        if (Storage::disk('private')->exists(self::CREDENTIALS_PATH)) {
            $this->error('Tệp thông tin đăng nhập đã tồn tại nhưng chưa có đủ tài khoản. Không ghi đè tệp riêng tư.');

            return self::FAILURE;
        }

        $credentials = array_map(fn (array $account) => [
            ...$account,
            'password' => Str::random(24),
        ], self::ACCOUNTS);

        $csv = $this->buildCredentialsCsv($credentials);
        if (! Storage::disk('private')->put(self::CREDENTIALS_PATH, $csv)) {
            $this->error('Không thể ghi tệp thông tin đăng nhập vào vùng lưu trữ riêng tư.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($credentials) {
                foreach ($credentials as $credential) {
                    $user = new User([
                        'name' => $credential['name'],
                        'email' => $credential['email'],
                        'password' => Hash::make($credential['password']),
                        'role' => $credential['role'],
                        'marketing_opt_out_at' => now(),
                    ]);
                    $user->email_verified_at = now();
                    $user->save();
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('private')->delete(self::CREDENTIALS_PATH);
            report($exception);
            $this->error('Không thể tạo trọn vẹn đợt tài khoản demo; giao dịch đã được hoàn tác.');

            return self::FAILURE;
        }

        $this->info('Đã tạo 4 tài khoản NXB chờ onboarding và 10 tài khoản Quản kho đã xác thực email.');
        $this->line('Tệp thông tin đăng nhập riêng tư: '.self::CREDENTIALS_PATH);
        $this->warn('Các tài khoản Nhà bán chưa có hồ sơ gian hàng; hãy đăng nhập và tự điền thông tin bổ sung.');

        return self::SUCCESS;
    }

    private function buildCredentialsCsv(array $credentials): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['name', 'email', 'password', 'role']);
        foreach ($credentials as $credential) {
            fputcsv($stream, [
                $credential['name'],
                $credential['email'],
                $credential['password'],
                $credential['role'],
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }
}

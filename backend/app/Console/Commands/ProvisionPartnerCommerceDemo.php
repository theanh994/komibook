<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProvisionPartnerCommerceDemo extends Command
{
    public const CREDENTIALS_PATH = 'demo-credentials/distributor-seller-accounts-20260731.csv';

    private const PRODUCTION_ACKNOWLEDGEMENT = 'KOMIBOOK_PARTNER_COMMERCE_20260731';

    private const NEW_DISTRIBUTORS = [
        ['name' => 'IPM (Demo)', 'email' => 'ipm.demo@komibook.id.vn', 'slug' => 'ipm-demo', 'display_name' => 'IPM (Demo)'],
        ['name' => 'Hikari - Thái Hà Books (Demo)', 'email' => 'hikari.thaihabooks.demo@komibook.id.vn', 'slug' => 'hikari-thaihabooks-demo', 'display_name' => 'Hikari - Thái Hà Books (Demo)'],
        ['name' => 'Fahasa (Demo)', 'email' => 'fahasa.demo@komibook.id.vn', 'slug' => 'fahasa-demo', 'display_name' => 'Fahasa (Demo)'],
    ];

    private const EXISTING_PUBLISHERS = [
        ['email' => 'nxblaodong.demo@komibook.id.vn', 'slug' => 'nxb-lao-dong-demo', 'display_name' => 'Nhà xuất bản Lao Động (Demo)', 'direct_seller' => false],
        ['email' => 'nxbtre.demo@komibook.id.vn', 'slug' => 'nxb-tre-demo', 'display_name' => 'Nhà xuất bản Trẻ (Demo)', 'direct_seller' => true],
        ['email' => 'nxbhanoi.demo@komibook.id.vn', 'slug' => 'nxb-ha-noi-demo', 'display_name' => 'Nhà xuất bản Hà Nội (Demo)', 'direct_seller' => false],
        ['email' => 'nxbgiaoduc.demo@komibook.id.vn', 'slug' => 'nxb-giao-duc-demo', 'display_name' => 'Nhà xuất bản Giáo Dục (Demo)', 'direct_seller' => true],
        ['email' => 'nxbkimdong@gmail.com', 'slug' => 'nxb-kim-dong-demo', 'display_name' => 'Nhà xuất bản Kim Đồng (Demo)', 'direct_seller' => true, 'legacy' => true],
    ];

    protected $signature = 'demo:provision-partner-commerce
        {--dry-run : Chỉ kiểm tra điều kiện chuyển đổi}
        {--production-ack= : Xác nhận bắt buộc khi chạy trên production}';

    protected $description = 'Tạo IPM, Hikari, Fahasa và chuẩn hóa vai trò NXB demo theo Seller–Publisher–Distributor';

    public function handle(): int
    {
        if (app()->environment('production')
            && $this->option('production-ack') !== self::PRODUCTION_ACKNOWLEDGEMENT) {
            $this->error('Thiếu xác nhận rõ ràng để chuyển đổi dữ liệu demo trên production.');

            return self::FAILURE;
        }

        $publisherEmails = array_column(self::EXISTING_PUBLISHERS, 'email');
        $publishers = User::whereIn('email', $publisherEmails)->get()->keyBy('email');
        if ($publishers->count() !== count(self::EXISTING_PUBLISHERS)) {
            $this->error('Thiếu một hoặc nhiều tài khoản NXB nền; không thực hiện chuyển đổi.');

            return self::FAILURE;
        }
        $kimDong = $publishers->get('nxbkimdong@gmail.com');
        if (! $kimDong?->vendor()->withoutGlobalScopes()->exists()) {
            $this->error('Tài khoản Kim Đồng chưa có hồ sơ Nhà bán để bảo toàn/chuyển đổi.');

            return self::FAILURE;
        }

        $newEmails = array_column(self::NEW_DISTRIBUTORS, 'email');
        $existingNewAccounts = User::whereIn('email', $newEmails)->count();
        $allOrganizationSlugs = array_merge(
            array_column(self::NEW_DISTRIBUTORS, 'slug'),
            array_column(self::EXISTING_PUBLISHERS, 'slug'),
        );
        $existingOrganizations = Organization::whereIn('slug', $allOrganizationSlugs)->count();
        if ($existingNewAccounts === count(self::NEW_DISTRIBUTORS)
            && $existingOrganizations === count($allOrganizationSlugs)
            && Storage::disk('private')->exists(self::CREDENTIALS_PATH)) {
            $this->info('Dữ liệu partner commerce demo đã tồn tại; không có dữ liệu nào bị thay đổi.');

            return self::SUCCESS;
        }
        if ($existingNewAccounts > 0 || $existingOrganizations > 0 || Storage::disk('private')->exists(self::CREDENTIALS_PATH)) {
            $this->error('Phát hiện dữ liệu demo trùng hoặc đợt chuyển đổi dở dang; không ghi dữ liệu.');

            return self::FAILURE;
        }

        $this->table(['Đơn vị', 'Mô hình', 'Trạng thái sau chuyển đổi'], [
            ['IPM', 'Distributor–Seller', 'Vendor draft + organization draft'],
            ['Hikari - Thái Hà Books', 'Distributor–Seller', 'Vendor draft + organization draft'],
            ['Fahasa', 'Distributor–Seller', 'Vendor draft + organization draft'],
            ['NXB Lao Động, Hà Nội', 'Publisher-only', 'Organization manager'],
            ['NXB Trẻ, Giáo Dục', 'Direct Publisher–Seller', 'Vendor draft + organization draft'],
            ['NXB Kim Đồng', 'Direct Publisher–Seller', 'Giữ gian hàng; yêu cầu xác minh ngân hàng'],
        ]);
        if ($this->option('dry-run')) {
            $this->info('Dry-run hoàn tất; chưa ghi tài khoản, organization hoặc hồ sơ Nhà bán.');

            return self::SUCCESS;
        }

        $credentials = array_map(fn (array $account) => [
            ...$account,
            'password' => Str::random(24),
        ], self::NEW_DISTRIBUTORS);
        if (! Storage::disk('private')->put(self::CREDENTIALS_PATH, $this->buildCredentialsCsv($credentials))) {
            $this->error('Không thể ghi tệp thông tin đăng nhập vào private storage.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($credentials, $publishers, $kimDong) {
                foreach ($credentials as $credential) {
                    $user = User::create([
                        'name' => $credential['name'],
                        'email' => $credential['email'],
                        'password' => Hash::make($credential['password']),
                        'role' => 'customer',
                        'marketing_opt_out_at' => now(),
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $organization = $this->createOrganization(
                        $user,
                        $credential['slug'],
                        $credential['display_name'],
                        ['distributor', 'supplier', 'bookstore'],
                    );
                    $vendor = $this->createDraftVendor($user, $organization, 'distributor');
                    $this->createSelfRelationship($vendor, $organization);
                }

                foreach (self::EXISTING_PUBLISHERS as $publisherData) {
                    $user = $publishers->get($publisherData['email']);
                    if (! ($publisherData['legacy'] ?? false)) {
                        $user->update(['role' => 'customer']);
                    }
                    $organization = $this->createOrganization(
                        $user,
                        $publisherData['slug'],
                        $publisherData['display_name'],
                        $publisherData['direct_seller'] ? ['publisher', 'supplier'] : ['publisher'],
                    );
                    if ($publisherData['direct_seller']) {
                        $vendor = ($publisherData['legacy'] ?? false)
                            ? $kimDong->vendor()->withoutGlobalScopes()->firstOrFail()
                            : $this->createDraftVendor($user, $organization, 'direct_publisher');
                        $vendor->update([
                            'business_model' => 'direct_publisher',
                            'primary_organization_id' => $organization->id,
                            'payout_bank_status' => 'unverified',
                            'payout_bank_verified_at' => null,
                            'payout_bank_verified_by' => null,
                        ]);
                        $this->createSelfRelationship($vendor, $organization);
                    }
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('private')->delete(self::CREDENTIALS_PATH);
            report($exception);
            $this->error('Chuyển đổi không hoàn tất; giao dịch cơ sở dữ liệu đã được hoàn tác.');

            return self::FAILURE;
        }

        $this->info('Đã tạo 3 Distributor–Seller draft và chuẩn hóa 5 tài khoản NXB demo.');
        $this->line('Tệp đăng nhập riêng tư: '.self::CREDENTIALS_PATH);

        return self::SUCCESS;
    }

    private function createOrganization(User $user, string $slug, string $displayName, array $types): Organization
    {
        $organization = Organization::create([
            'legal_name' => $displayName,
            'display_name' => $displayName,
            'slug' => $slug,
            'organization_types' => $types,
            'status' => 'draft',
        ]);
        OrganizationMembership::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        return $organization;
    }

    private function createDraftVendor(User $user, Organization $organization, string $businessModel): Vendor
    {
        return Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => null,
            'slug' => null,
            'status' => 'inactive',
            'onboarding_status' => 'draft',
            'business_model' => $businessModel,
            'primary_organization_id' => $organization->id,
            'payout_bank_status' => 'unverified',
        ]);
    }

    private function createSelfRelationship(Vendor $vendor, Organization $organization): void
    {
        VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'draft',
            'operation_key' => "demo-self-organization:{$vendor->id}:{$organization->id}",
        ]);
    }

    private function buildCredentialsCsv(array $credentials): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['name', 'email', 'password', 'intended_business_model']);
        foreach ($credentials as $credential) {
            fputcsv($stream, [$credential['name'], $credential['email'], $credential['password'], 'distributor']);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }
}

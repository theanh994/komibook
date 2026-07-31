<?php

namespace Tests\Feature;

use App\Console\Commands\ProvisionPartnerCommerceDemo;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerCommerceDemoProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_distributor_drafts_and_preserves_legacy_kim_dong_shop(): void
    {
        Storage::fake('private');
        foreach ([
            'nxblaodong.demo@komibook.id.vn',
            'nxbtre.demo@komibook.id.vn',
            'nxbhanoi.demo@komibook.id.vn',
            'nxbgiaoduc.demo@komibook.id.vn',
        ] as $email) {
            User::factory()->create(['email' => $email, 'role' => 'vendor']);
        }
        $kimDong = User::factory()->create(['email' => 'nxbkimdong@gmail.com', 'role' => 'vendor']);
        $legacyVendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $kimDong->id,
            'shop_name' => 'Kim Đồng',
            'slug' => 'kim-dong',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'bookstore',
        ]);

        $this->artisan('demo:provision-partner-commerce')->assertSuccessful();

        $this->assertSame(3, User::whereIn('email', [
            'ipm.demo@komibook.id.vn',
            'hikari.thaihabooks.demo@komibook.id.vn',
            'fahasa.demo@komibook.id.vn',
        ])->where('role', 'customer')->count());
        $this->assertSame(5, Vendor::withoutGlobalScopes()->where('onboarding_status', 'draft')->count());
        $this->assertSame('direct_publisher', $legacyVendor->fresh()->business_model);
        $this->assertSame('active', $legacyVendor->fresh()->status);
        $this->assertSame('unverified', $legacyVendor->fresh()->payout_bank_status);
        $this->assertSame(8, Organization::count());
        $this->assertDatabaseCount('organization_memberships', 8);
        $this->assertDatabaseCount('vendor_organization_relationships', 6);
        Storage::disk('private')->assertExists(ProvisionPartnerCommerceDemo::CREDENTIALS_PATH);

        $this->artisan('demo:provision-partner-commerce')->assertSuccessful();
        $this->assertSame(8, Organization::count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoEvidenceModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_vendor_can_submit_without_fake_bank_or_legal_documents(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'status' => 'inactive',
            'onboarding_status' => 'draft',
            'business_model' => 'distributor',
            'is_demo' => true,
            'demo_wallet_code' => 'DEMO-IPM-001',
            'payout_bank_status' => 'demo_disabled',
        ]);

        $this->actingAs($user)->postJson('/api/vendor-onboarding/register', [
            'shop_name' => 'IPM Demo',
            'slug' => 'ipm-demo-shop',
            'legal_name' => 'Công ty Cổ phần Xuất bản và Truyền thông IPM',
            'tax_code' => '0101507251',
            'business_model' => 'distributor',
            'terms_accepted' => true,
        ])->assertOk()
            ->assertJsonPath('data.onboarding_status', 'submitted')
            ->assertJsonPath('data.is_demo', true)
            ->assertJsonPath('data.payout_bank_status', 'demo_disabled');

        $this->assertNull($vendor->fresh()->business_registration_document);
        $this->assertNull($vendor->fresh()->payout_bank_account);
    }

    public function test_demo_wallet_cannot_request_real_payout_even_if_bank_status_is_forced_verified(): void
    {
        $user = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Gian hàng demo',
            'slug' => 'gian-hang-demo',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'is_demo' => true,
            'demo_wallet_code' => 'DEMO-VENDOR-9999',
            'payout_bank_status' => 'verified',
            'payout_bank_account' => '0000',
            'payout_bank_name' => 'Demo',
            'payout_bank_holder' => 'DEMO',
            'balance' => 500000,
        ]);

        $this->actingAs($user)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/api/vendor/finance/payout', ['amount' => 100000])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ví đối soát demo không được phép tạo yêu cầu rút tiền thật.');
    }

    public function test_vendor_updates_existing_demo_organization_with_multipart_files_without_duplication(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'vendor']);
        $organization = Organization::create([
            'legal_name' => 'IPM Demo',
            'display_name' => 'IPM Demo',
            'slug' => 'ipm-demo',
            'organization_types' => ['distributor', 'supplier'],
            'status' => 'demo_accepted',
            'data_mode' => 'demo',
        ]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'distributor',
            'primary_organization_id' => $organization->id,
            'is_demo' => true,
            'demo_wallet_code' => 'DEMO-IPM-001',
            'payout_bank_status' => 'demo_disabled',
        ]);
        VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'demo_accepted',
            'is_demo' => true,
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-REL-IPM-001',
            'operation_key' => 'demo-self-ipm',
        ]);

        $this->actingAs($user)->post("/api/vendor/organizations/{$organization->id}", [
            '_method' => 'PATCH',
            'legal_name' => 'Công ty Cổ phần Xuất bản và Truyền thông IPM',
            'display_name' => 'IPM Việt Nam',
            'slug' => 'ipm-demo',
            'organization_types' => ['distributor', 'supplier'],
            'tax_code' => '0101507251',
            'website' => 'https://ipm.vn',
            'public_source_url' => 'https://ipm.vn/pages/gioi-thieu',
            'public_source_checked_at' => now()->toDateString(),
            'logo' => UploadedFile::fake()->image('logo.webp'),
        ])->assertOk()->assertJsonPath('data.organization.status', 'demo_accepted');

        $this->assertDatabaseCount('organizations', 1);
        $this->assertSame('IPM Việt Nam', $organization->fresh()->display_name);
        Storage::disk('public')->assertExists($organization->fresh()->logo);
    }

    public function test_demo_acceptance_is_operational_but_not_legal_verification(): void
    {
        $organization = Organization::create([
            'legal_name' => 'Demo',
            'display_name' => 'Demo',
            'slug' => 'demo-org',
            'organization_types' => ['publisher'],
            'status' => 'demo_accepted',
            'data_mode' => 'demo',
        ]);
        $relationship = VendorOrganizationRelationship::make([
            'status' => 'demo_accepted',
            'is_demo' => true,
        ]);

        $this->assertTrue($organization->isOperationallyAccepted());
        $this->assertTrue($relationship->isCurrentlyVerified());
        $this->assertNull($organization->verified_at);
    }
}

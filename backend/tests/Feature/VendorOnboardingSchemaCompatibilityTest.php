<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorOnboardingSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_and_new_real_vendor_submission_do_not_depend_on_legacy_rejection_reason_column(): void
    {
        Storage::fake('private');
        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn('rejection_reason');
        });

        try {
            $demoUser = User::factory()->create(['role' => 'customer']);
            Vendor::withoutGlobalScopes()->create([
                'user_id' => $demoUser->id,
                'status' => 'inactive',
                'onboarding_status' => 'draft',
                'business_model' => 'distributor',
                'is_demo' => true,
                'demo_wallet_code' => 'DEMO-IPM-COMPAT',
                'payout_bank_status' => 'demo_disabled',
            ]);

            $this->actingAs($demoUser)->postJson('/api/vendor-onboarding/register', [
                'shop_name' => 'IPM Demo Compatibility',
                'slug' => 'ipm-demo-compatibility',
                'legal_name' => 'Công ty IPM Demo',
                'tax_code' => '0101507251',
                'business_model' => 'distributor',
                'terms_accepted' => true,
                'operation_key' => 'demo-vendor-without-legacy-column',
            ])->assertOk()->assertJsonPath('data.onboarding_status', 'submitted');

            $realUser = User::factory()->create(['role' => 'customer']);
            $this->actingAs($realUser)->post('/api/vendor-onboarding/register', [
                'shop_name' => 'Nhà bán mới',
                'slug' => 'nha-ban-moi-schema-compatibility',
                'legal_name' => 'CÔNG TY NHÀ BÁN MỚI',
                'tax_code' => 'TAX-COMPAT-001',
                'business_model' => 'distributor',
                'business_registration_document' => UploadedFile::fake()->create('business.pdf', 10),
                'representative_identity_document' => UploadedFile::fake()->image('representative.jpg'),
                'payout_bank_account' => '987654321',
                'payout_bank_name' => 'Ngân hàng thử nghiệm',
                'payout_bank_holder' => 'CÔNG TY NHÀ BÁN MỚI',
                'terms_accepted' => true,
                'operation_key' => 'real-vendor-without-legacy-column',
            ])->assertCreated()->assertJsonPath('data.onboarding_status', 'submitted');

            $this->assertDatabaseHas('vendors', [
                'user_id' => $demoUser->id,
                'onboarding_status' => 'submitted',
                'last_review_reason' => null,
            ]);
            $this->assertDatabaseHas('vendors', [
                'user_id' => $realUser->id,
                'onboarding_status' => 'submitted',
                'last_review_reason' => null,
            ]);
        } finally {
            if (! Schema::hasColumn('vendors', 'rejection_reason')) {
                Schema::table('vendors', function (Blueprint $table): void {
                    $table->string('rejection_reason')->nullable();
                });
            }
        }
    }
}

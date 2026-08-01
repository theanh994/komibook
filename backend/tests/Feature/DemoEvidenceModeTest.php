<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
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

    public function test_admin_demo_acceptance_keeps_legal_verification_timestamp_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $organization = Organization::create([
            'legal_name' => 'NXB Demo',
            'display_name' => 'NXB Demo',
            'slug' => 'nxb-demo-review',
            'organization_types' => ['publisher'],
            'status' => 'pending_review',
            'data_mode' => 'demo',
        ]);

        $this->actingAs($admin)->patchJson("/api/admin/organizations/{$organization->id}/transition", [
            'to_status' => 'demo_accepted',
        ])->assertOk()
            ->assertJsonPath('data.status', 'demo_accepted')
            ->assertJsonPath('data.verified_at', null);

        $this->assertNull($organization->fresh()->verified_at);
    }

    public function test_demo_relationship_can_be_assigned_to_a_book_and_is_labelled_as_demo_publicly(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $organization = Organization::create([
            'legal_name' => 'Nhà xuất bản Demo',
            'display_name' => 'Nhà xuất bản Demo',
            'slug' => 'nxb-demo-listing',
            'organization_types' => ['publisher', 'supplier'],
            'status' => 'demo_accepted',
            'data_mode' => 'demo',
        ]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Gian hàng NXB Demo',
            'slug' => 'gian-hang-nxb-demo',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'direct_publisher',
            'primary_organization_id' => $organization->id,
            'is_demo' => true,
            'demo_wallet_code' => 'DEMO-VENDOR-1000',
            'payout_bank_status' => 'demo_disabled',
        ]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'demo_accepted',
            'is_demo' => true,
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-REL-LISTING-001',
            'operation_key' => 'demo-listing-self',
        ]);
        $category = Category::create(['name' => 'Sách demo', 'slug' => 'sach-demo']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách có chuỗi cung ứng demo',
            'slug' => 'sach-co-chuoi-cung-ung-demo',
            'author' => 'Tác giả Demo',
            'description' => 'Mô tả demo',
            'cover_image' => 'books/covers/demo.webp',
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'provenance' => 'publisher_catalog',
            'status' => 'published',
        ]);

        $payload = [
            'publisher_relationship_id' => $relationship->id,
            'supplier_relationship_id' => $relationship->id,
            'responsible_relationship_id' => $relationship->id,
        ];
        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}/commercial-parties", $payload)
            ->assertOk()
            ->assertJsonCount(3, 'data.active_commercial_parties');

        $this->getJson("/api/books/{$book->slug}")
            ->assertOk()
            ->assertJsonPath('data.commercial_parties.supplier.display_name', 'Nhà xuất bản Demo')
            ->assertJsonPath('data.commercial_parties.supplier.acceptance_status', 'demo_accepted')
            ->assertJsonPath('data.commercial_parties.supplier.is_demo', true);
    }

    public function test_admin_dashboard_filters_demo_organizations_and_returns_independent_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Organization::create([
            'legal_name' => 'Nhà xuất bản Kim Đồng Demo',
            'display_name' => 'Kim Đồng Demo',
            'slug' => 'kim-dong-dashboard-demo',
            'organization_types' => ['publisher'],
            'status' => 'demo_accepted',
            'data_mode' => 'demo',
        ]);
        Organization::create([
            'legal_name' => 'Tổ chức thật',
            'display_name' => 'Tổ chức thật',
            'slug' => 'to-chuc-that',
            'organization_types' => ['supplier'],
            'status' => 'verified',
            'data_mode' => 'real',
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)->getJson('/api/admin/organization-reviews?section=organizations&search=Kim&data_mode=demo&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.section', 'organizations')
            ->assertJsonPath('data.summary.organizations', 2)
            ->assertJsonPath('data.items.total', 1)
            ->assertJsonPath('data.items.data.0.slug', 'kim-dong-dashboard-demo');
    }

    public function test_vendor_organization_page_lists_books_that_still_need_supply_chain_links(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $organization = Organization::create([
            'legal_name' => 'Nhà xuất bản Demo',
            'display_name' => 'NXB Demo',
            'slug' => 'nxb-demo-supply-chain-status',
            'organization_types' => ['publisher', 'supplier'],
            'status' => 'demo_accepted',
            'data_mode' => 'demo',
        ]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Gian hàng Demo',
            'slug' => 'gian-hang-demo-supply-chain-status',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'direct_publisher',
            'primary_organization_id' => $organization->id,
            'is_demo' => true,
            'demo_wallet_code' => 'DEMO-VENDOR-STATUS',
            'payout_bank_status' => 'demo_disabled',
        ]);
        VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'demo_accepted',
            'is_demo' => true,
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-REL-STATUS-001',
            'operation_key' => 'demo-supply-chain-status',
        ]);
        $category = Category::create(['name' => 'Sách cần gắn', 'slug' => 'sach-can-gan']);
        Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách chưa gắn chuỗi cung ứng',
            'slug' => 'sach-chua-gan-chuoi-cung-ung',
            'author' => 'Tác giả Demo',
            'description' => 'Mô tả',
            'cover_image' => 'books/covers/demo.webp',
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'provenance' => 'publisher_catalog',
            'status' => 'published',
        ]);

        $this->actingAs($user)->getJson('/api/vendor/organizations')
            ->assertOk()
            ->assertJsonPath('data.supply_chain.unlinked_books_count', 1)
            ->assertJsonPath('data.supply_chain.unlinked_books.0.slug', 'sach-chua-gan-chuoi-cung-ung')
            ->assertJsonPath('data.supply_chain.unlinked_books.0.missing_roles.0', 'publisher')
            ->assertJsonPath('data.supply_chain.unlinked_books.0.missing_roles.1', 'supplier')
            ->assertJsonPath('data.supply_chain.unlinked_books.0.missing_roles.2', 'responsible_organization');
    }
}

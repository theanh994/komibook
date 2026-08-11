<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationRelationshipEvent;
use App\Models\OrganizationReviewEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Services\CommercialPartyService;
use App\Services\OrganizationRelationshipService;
use App\Services\OrganizationReviewService;
use App\Support\AuthorityReviewFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
        $organization = Organization::where('slug', 'ipm-demo-shop')->sole();
        $relationship = VendorOrganizationRelationship::where('vendor_id', $vendor->id)->sole();
        $this->assertTrue($relationship->is_demo);
        $this->assertSame('demo_statement', $relationship->evidence_mode);
        $this->assertNotNull($relationship->demo_reference);
        $this->assertNull($relationship->evidence_document);
        $admin = User::factory()->create(['role' => 'admin']);
        app(OrganizationReviewService::class)->transition($organization, 'demo_accepted', $admin, 'Demo reviewed.', 'demo-bootstrap-org-review');
        app(OrganizationRelationshipService::class)->transition($relationship, 'demo_accepted', $admin, 'Demo relationship reviewed.', 'demo-bootstrap-rel-review');
        $this->assertTrue($relationship->fresh()->isCurrentlyVerified());
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

    public function test_demo_accepted_update_keeps_raw_status_but_invalidates_authority_without_reacceptance(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'vendor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $organization = Organization::create([
            'legal_name' => 'IPM Demo',
            'display_name' => 'IPM Demo',
            'slug' => 'ipm-demo',
            'organization_types' => ['distributor', 'supplier'],
            'status' => 'pending_review',
            'data_mode' => 'demo',
        ]);
        app(OrganizationReviewService::class)->transition($organization, 'demo_accepted', $admin, 'Demo accepted.', 'ipm-demo-accepted');
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

        $originalFingerprint = $organization->fresh()->authority_fingerprint;
        $eventFingerprint = $organization->fresh()->latestReviewEvent->reviewed_fingerprint;
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
        $fresh = $organization->fresh();
        $this->assertSame('demo_accepted', $fresh->status);
        $this->assertSame('IPM Việt Nam', $fresh->display_name);
        $this->assertNotSame($originalFingerprint, $fresh->authority_fingerprint);
        $this->assertSame($eventFingerprint, $fresh->latestReviewEvent->reviewed_fingerprint);
        $this->assertFalse($fresh->hasAuthoritativeAcceptance());
        $this->assertDatabaseCount('organization_review_events', 1);
        Storage::disk('public')->assertExists($fresh->logo);
    }

    public function test_demo_acceptance_is_operational_but_not_legal_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $organization = Organization::create([
            'legal_name' => 'Demo',
            'display_name' => 'Demo',
            'slug' => 'demo-org',
            'organization_types' => ['publisher'],
            'status' => 'demo_accepted',
            'data_mode' => 'demo',
            'verified_by' => $admin->id,
            'last_review_reason' => 'Demo evidence reviewed.',
        ]);
        OrganizationReviewEvent::create(['organization_id' => $organization->id, 'actor_id' => $admin->id, 'from_status' => 'pending_review', 'to_status' => 'demo_accepted', 'reason' => 'Demo evidence reviewed.', 'operation_key' => 'demo-org-predicate']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create(['user_id' => $vendorUser->id, 'shop_name' => 'Predicate vendor', 'slug' => 'predicate-vendor', 'status' => 'active', 'onboarding_status' => 'approved', 'is_demo' => true]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'status' => 'demo_accepted',
            'is_demo' => true,
            'role' => 'self_legal_entity',
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-PREDICATE-001',
            'effective_from' => now()->subDay(),
            'reviewed_by' => $admin->id,
            'last_review_reason' => 'Demo relationship reviewed.',
            'operation_key' => 'demo-relationship-predicate',
        ]);
        OrganizationRelationshipEvent::create(['vendor_organization_relationship_id' => $relationship->id, 'actor_id' => $admin->id, 'from_status' => 'submitted', 'to_status' => 'demo_accepted', 'reason' => 'Demo relationship reviewed.', 'operation_key' => 'demo-relationship-event-predicate']);

        $this->assertTrue($organization->isOperationallyAccepted());
        $this->assertTrue($relationship->isCurrentlyVerified());
        $this->assertNull($organization->verified_at);

        $relationship->update(['reviewed_by' => $vendorUser->id]);
        OrganizationRelationshipEvent::create(['vendor_organization_relationship_id' => $relationship->id, 'actor_id' => $vendorUser->id, 'from_status' => 'demo_accepted', 'to_status' => 'demo_accepted', 'reason' => 'Demo relationship reviewed.', 'operation_key' => 'demo-relationship-non-admin-event']);
        $this->assertFalse($relationship->fresh()->isCurrentlyVerified());
    }

    public function test_admin_demo_acceptance_keeps_legal_verification_timestamp_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $organization = Organization::create([
            'legal_name' => 'NXB Demo',
            'display_name' => 'NXB Demo',
            'slug' => 'nxb-demo-review',
            'organization_types' => ['publisher'],
            'status' => 'pending_review',
            'data_mode' => 'demo',
        ]);

        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])->patchJson("/api/admin/organizations/{$organization->id}/transition", [
            'to_status' => 'demo_accepted', 'reason' => 'Demo reviewed.', 'operation_key' => 'demo-org-admin-accept',
        ])->assertOk()
            ->assertJsonPath('data.status', 'demo_accepted')
            ->assertJsonPath('data.verified_at', null);

        $this->assertNull($organization->fresh()->verified_at);
    }

    public function test_demo_relationship_can_be_assigned_to_a_book_and_is_labelled_as_demo_publicly(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $organization = Organization::create([
            'legal_name' => 'Nhà xuất bản Demo',
            'display_name' => 'Nhà xuất bản Demo',
            'slug' => 'nxb-demo-listing',
            'organization_types' => ['publisher', 'supplier'],
            'status' => 'demo_accepted',
            'data_mode' => 'demo',
            'verified_by' => $admin->id,
            'last_review_reason' => 'Demo organization reviewed.',
        ]);
        OrganizationReviewEvent::create(['organization_id' => $organization->id, 'actor_id' => $admin->id, 'from_status' => 'pending_review', 'to_status' => 'demo_accepted', 'reason' => 'Demo organization reviewed.', 'operation_key' => 'demo-book-org-event']);
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
            'effective_from' => now()->subDay(),
            'reviewed_by' => $admin->id,
            'last_review_reason' => 'Demo relationship reviewed.',
            'operation_key' => 'demo-listing-self',
        ]);
        OrganizationRelationshipEvent::create(['vendor_organization_relationship_id' => $relationship->id, 'actor_id' => $admin->id, 'from_status' => 'submitted', 'to_status' => 'demo_accepted', 'reason' => 'Demo relationship reviewed.', 'operation_key' => 'demo-book-relationship-event']);
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

        $canonicalRelationship = $relationship->fresh(['organization']);
        $this->assertSame($canonicalRelationship->authority_fingerprint, AuthorityReviewFingerprint::relationship($canonicalRelationship));
        $this->assertSame($canonicalRelationship->authority_fingerprint, $canonicalRelationship->latestEvent->reviewed_fingerprint);

        $supplierParty = $book->commercialParties()->where('role', 'supplier')->sole();
        $supplierParty->update(['status' => 'verified', 'verified_at' => now(), 'verified_by' => $admin->id]);
        $this->assertFalse($book->fresh()->activeCommercialParties()->where('role', 'supplier')->exists());
        $this->assertNull(app(CommercialPartyService::class)->snapshot($book->fresh()));

        $supplierParty->update(['status' => 'demo_accepted', 'verified_at' => null, 'verified_by' => null]);
        $this->assertTrue($book->fresh()->activeCommercialParties()->where('role', 'supplier')->exists());

        $originalFingerprint = $canonicalRelationship->authority_fingerprint;
        $originalReviewedFingerprint = $canonicalRelationship->latestEvent->reviewed_fingerprint;
        $relationship->update(['demo_reference' => 'DEMO-REL-LISTING-001-MUTATED']);
        $mutatedRelationship = $relationship->fresh(['organization']);
        $this->assertNotSame($originalFingerprint, $mutatedRelationship->authority_fingerprint);
        $this->assertSame($mutatedRelationship->authority_fingerprint, AuthorityReviewFingerprint::relationship($mutatedRelationship));
        $this->assertSame($originalReviewedFingerprint, $mutatedRelationship->latestEvent->reviewed_fingerprint);
        $this->assertFalse($mutatedRelationship->isCurrentlyVerified());
        $this->assertFalse($book->fresh()->activeCommercialParties()->whereIn('role', CommercialPartyService::ROLES)->exists());
        $this->assertNull(app(CommercialPartyService::class)->snapshot($book->fresh()));

        $service = app(OrganizationRelationshipService::class);
        $eventCount = OrganizationRelationshipEvent::count();
        $resourceFields = [
            'status' => $mutatedRelationship->getRawOriginal('status'),
            'demo_reference' => $mutatedRelationship->getRawOriginal('demo_reference'),
            'authority_fingerprint' => $mutatedRelationship->getRawOriginal('authority_fingerprint'),
            'reviewed_by' => $mutatedRelationship->getRawOriginal('reviewed_by'),
            'last_review_reason' => $mutatedRelationship->getRawOriginal('last_review_reason'),
        ];
        try {
            $service->transition($mutatedRelationship, 'demo_accepted', $admin, 'Demo relationship reviewed.', 'demo-book-relationship-event');
            $this->fail('Fingerprint-stale relationship replay must reject.');
        } catch (ValidationException) {
            $this->assertSame($eventCount, OrganizationRelationshipEvent::count());
            $freshRelationship = $mutatedRelationship->fresh();
            $this->assertSame($resourceFields, [
                'status' => $freshRelationship->getRawOriginal('status'),
                'demo_reference' => $freshRelationship->getRawOriginal('demo_reference'),
                'authority_fingerprint' => $freshRelationship->getRawOriginal('authority_fingerprint'),
                'reviewed_by' => $freshRelationship->getRawOriginal('reviewed_by'),
                'last_review_reason' => $freshRelationship->getRawOriginal('last_review_reason'),
            ]);
        }
        foreach ([
            fn () => $service->transition($mutatedRelationship, 'demo_accepted', $admin, 'Different reason.', 'demo-book-relationship-event'),
            fn () => $service->transition($mutatedRelationship, 'demo_accepted', $admin, 'Demo relationship reviewed.', 'demo-book-org-event'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A mismatched relationship replay must reject.');
            } catch (ValidationException) {
                $this->assertSame($eventCount, OrganizationRelationshipEvent::count());
            }
        }
        $service->transition($mutatedRelationship, 'suspended', $admin, 'Suspended after acceptance.', 'demo-book-relationship-suspended');
        try {
            $service->transition($mutatedRelationship, 'demo_accepted', $admin, 'Demo relationship reviewed.', 'demo-book-relationship-event');
            $this->fail('A stale relationship replay must reject.');
        } catch (ValidationException) {
            $this->assertSame($eventCount + 1, OrganizationRelationshipEvent::count());
        }
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

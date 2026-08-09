<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\OrganizationDistributionAgreementEvent;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRelationshipEvent;
use App\Models\OrganizationReviewEvent;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Services\DistributionAgreementService;
use App\Support\AuthorityReviewFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PartnerCommerceArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_an_organization_without_receiving_vendor_access(): void
    {
        Storage::fake('private');
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)->post('/api/organization-portal/organizations', [
            'legal_name' => 'Công ty Phân phối Demo',
            'display_name' => 'Nhà phân phối Demo',
            'slug' => 'nha-phan-phoi-demo',
            'organization_types' => ['supplier', 'distributor'],
            'verification_document' => UploadedFile::fake()->create('organization.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.membership.role', 'owner');

        $this->assertDatabaseHas('organization_memberships', [
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.capabilities.organization_manager', true)
            ->assertJsonPath('data.user.capabilities.active_vendor', false);
        $this->assertSame('customer', $user->fresh()->role);
    }

    public function test_distribution_agreement_requires_verified_parties_and_admin_review(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $publisher = $this->organization('publisher-demo', ['publisher'], 'pending_review');
        $distributor = $this->organization('distributor-demo', ['supplier', 'distributor'], 'verified');
        OrganizationMembership::create([
            'user_id' => $owner->id,
            'organization_id' => $distributor->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $payload = [
            'publisher_organization_id' => $publisher->id,
            'distributor_organization_id' => $distributor->id,
            'scope' => ['coverage' => 'catalog'],
            'evidence_document' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ];
        $this->actingAs($owner)->post('/api/organization-portal/distribution-agreements', $payload)
            ->assertUnprocessable();

        $publisher->update(['status' => 'verified', 'verification_document' => 'organizations/publisher.pdf', 'submitted_at' => now()->subDay(), 'verified_at' => now(), 'verified_by' => $admin->id, 'last_review_reason' => 'Legal organization reviewed.']);
        OrganizationReviewEvent::create(['organization_id' => $publisher->id, 'actor_id' => $admin->id, 'from_status' => 'pending_review', 'to_status' => 'verified', 'reason' => 'Legal organization reviewed.', 'operation_key' => 'partner-publisher-org-review']);
        $agreementId = $this->actingAs($owner)->post('/api/organization-portal/distribution-agreements', [
            ...$payload,
            'evidence_document' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.status', 'submitted')->json('data.id');
        $this->assertDatabaseHas('organization_distribution_agreement_events', [
            'organization_distribution_agreement_id' => $agreementId,
            'from_status' => 'draft',
            'to_status' => 'submitted',
        ]);

        $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()])->patchJson("/api/admin/distribution-agreements/{$agreementId}/transition", [
            'to_status' => 'verified', 'reason' => 'Agreement reviewed.', 'operation_key' => 'partner-agreement-review',
        ])->assertOk()->assertJsonPath('data.status', 'verified');
        $reviewedAgreement = OrganizationDistributionAgreement::findOrFail($agreementId);
        $this->assertTrue($reviewedAgreement->isCurrentlyVerified());
        $this->assertSame($reviewedAgreement->authority_fingerprint, AuthorityReviewFingerprint::agreement($reviewedAgreement));
        $this->assertSame($reviewedAgreement->authority_fingerprint, $reviewedAgreement->latestEvent->reviewed_fingerprint);

        $service = app(DistributionAgreementService::class);
        $agreement = OrganizationDistributionAgreement::findOrFail($agreementId);
        $eventCount = OrganizationDistributionAgreementEvent::count();
        $service->transition($agreement, 'verified', $admin, 'Agreement reviewed.', 'partner-agreement-review');
        foreach ([
            fn () => $service->transition($agreement, 'verified', User::factory()->create(['role' => 'admin']), 'Agreement reviewed.', 'partner-agreement-review'),
            fn () => $service->transition($agreement, 'verified', $admin, 'Different reason.', 'partner-agreement-review'),
            fn () => $service->transition($agreement, 'verified', $admin, 'Agreement reviewed.', 'partner-publisher-org-review'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A mismatched agreement replay must reject.');
            } catch (ValidationException) {
                $this->assertSame($eventCount, OrganizationDistributionAgreementEvent::count());
            }
        }
        $service->transition($agreement, 'suspended', $admin, 'Suspended after review.', 'partner-agreement-suspended');
        try {
            $service->transition($agreement, 'verified', $admin, 'Agreement reviewed.', 'partner-agreement-review');
            $this->fail('A stale agreement replay must reject.');
        } catch (ValidationException) {
            $this->assertSame($eventCount + 1, OrganizationDistributionAgreementEvent::count());
        }
    }

    public function test_payout_uses_only_the_verified_vendor_bank_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Distributor Seller',
            'slug' => 'distributor-seller',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'payout_bank_name' => 'Verified Bank',
            'payout_bank_account' => '0123456789',
            'payout_bank_holder' => 'CONG TY DEMO',
            'payout_bank_status' => 'verified',
            'payout_bank_verified_at' => now(),
        ]);
        $vendor->forceFill(['balance' => 500000])->save();

        $this->actingAs($user)->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/api/vendor/finance/payout', [
                'amount' => 100000,
                'bank_name' => 'Attacker Bank',
                'account_number' => '999999',
                'account_name' => 'ATTACKER',
                'idempotency_key' => 'partner-payout-test',
            ])->assertCreated();

        $payout = PayoutRequest::firstOrFail();
        $this->assertSame($vendor->payout_bank_name, $payout->bank_name);
        $this->assertSame($vendor->payout_bank_account, $payout->account_number);
        $this->assertSame($vendor->payout_bank_holder, $payout->account_name);
    }

    public function test_listing_with_external_publisher_requires_a_verified_distribution_agreement(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'IPM Demo',
            'slug' => 'ipm-listing-demo',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'distributor',
        ]);
        $publisher = $this->organization('publisher-listing', ['publisher'], 'verified');
        $distributor = $this->organization('distributor-listing', ['supplier', 'distributor'], 'verified');
        $publisherRelationship = $this->relationship($vendor, $publisher, 'publisher_partner');
        $supplierRelationship = $this->relationship($vendor, $distributor, 'self_legal_entity');
        $category = Category::create(['name' => 'Partner commerce', 'slug' => 'partner-commerce']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách phân phối',
            'slug' => 'sach-phan-phoi',
            'author' => 'Metadata',
            'description' => 'Mô tả',
            'cover_image' => 'books/covers/demo.webp',
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'provenance' => 'publisher_catalog',
            'status' => 'published',
        ]);
        $otherBook = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'SÃ¡ch phÃ¢n phá»‘i kháº£o sÃ¡t',
            'slug' => 'sach-phan-phoi-khao-sat',
            'author' => 'Metadata',
            'description' => 'MÃ´ táº£',
            'cover_image' => 'books/covers/demo.webp',
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'provenance' => 'publisher_catalog',
            'status' => 'published',
        ]);
        $payload = [
            'publisher_relationship_id' => $publisherRelationship->id,
            'supplier_relationship_id' => $supplierRelationship->id,
            'responsible_relationship_id' => $supplierRelationship->id,
        ];

        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}/commercial-parties", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supplier');
        $agreementReviewer = User::factory()->create(['role' => 'admin']);
        OrganizationDistributionAgreement::create([
            'publisher_organization_id' => $publisher->id,
            'distributor_organization_id' => $distributor->id,
            'status' => 'verified',
            'is_demo' => false,
            'evidence_mode' => 'real_document',
            'evidence_document' => 'organizations/agreement.pdf',
            'scope' => ['coverage' => 'book_ids', 'book_ids' => [$book->id]],
            'verified_at' => now(),
            'submitted_at' => now()->subDay(),
            'reviewed_by' => $agreementReviewer->id,
            'last_review_reason' => 'Historical fixture review.',
            'effective_from' => now()->subDay(),
            'operation_key' => 'verified-listing-agreement',
        ]);

        $agreement = OrganizationDistributionAgreement::latest('id')->firstOrFail();
        OrganizationDistributionAgreementEvent::create(['organization_distribution_agreement_id' => $agreement->id, 'actor_id' => $agreementReviewer->id, 'from_status' => 'submitted', 'to_status' => 'verified', 'reason' => 'Historical fixture review.', 'operation_key' => 'verified-listing-agreement-event']);
        $agreement = $agreement->fresh(['publisher', 'distributor']);
        $this->assertTrue($agreement->coversBook($book->id));
        $this->assertFalse($agreement->coversBook($otherBook->id));
        $this->assertTrue($agreement->isCurrentlyVerified());
        $this->assertSame($agreement->authority_fingerprint, AuthorityReviewFingerprint::agreement($agreement));
        $this->assertSame($agreement->authority_fingerprint, $agreement->latestEvent->reviewed_fingerprint);

        $this->actingAs($user)->putJson("/api/vendor/books/{$book->id}/commercial-parties", $payload)
            ->assertOk()
            ->assertJsonCount(3, 'data.active_commercial_parties');

        $this->actingAs($user)->putJson("/api/vendor/books/{$otherBook->id}/commercial-parties", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supplier');

        $agreement->update(['reviewed_by' => $user->id]);
        OrganizationDistributionAgreementEvent::create(['organization_distribution_agreement_id' => $agreement->id, 'actor_id' => $user->id, 'from_status' => 'verified', 'to_status' => 'verified', 'reason' => 'Historical fixture review.', 'operation_key' => 'verified-listing-agreement-non-admin-event']);
        $this->assertFalse($agreement->fresh(['publisher', 'distributor'])->isCurrentlyVerified());

        $agreement->update(['reviewed_by' => $agreementReviewer->id]);
        OrganizationDistributionAgreementEvent::create(['organization_distribution_agreement_id' => $agreement->id, 'actor_id' => $agreementReviewer->id, 'from_status' => 'verified', 'to_status' => 'verified', 'reason' => 'Historical fixture review.', 'operation_key' => 'verified-listing-agreement-restored-event']);
        $agreement = $agreement->fresh(['publisher', 'distributor']);
        $this->assertTrue($agreement->isCurrentlyVerified());

        $originalFingerprint = $agreement->authority_fingerprint;
        $originalReviewedFingerprint = $agreement->latestEvent->reviewed_fingerprint;
        $agreement->update(['scope' => ['coverage' => 'catalog']]);
        $mutatedAgreement = $agreement->fresh(['publisher', 'distributor']);
        $this->assertTrue($mutatedAgreement->coversBook($otherBook->id));
        $this->assertNotSame($originalFingerprint, $mutatedAgreement->authority_fingerprint);
        $this->assertSame($mutatedAgreement->authority_fingerprint, AuthorityReviewFingerprint::agreement($mutatedAgreement));
        $this->assertSame($originalReviewedFingerprint, $mutatedAgreement->latestEvent->reviewed_fingerprint);
        $this->assertFalse($mutatedAgreement->isCurrentlyVerified());

        $this->actingAs($user)->putJson("/api/vendor/books/{$otherBook->id}/commercial-parties", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supplier');

        $eventCount = OrganizationDistributionAgreementEvent::count();
        $resourceFields = [
            'status' => $mutatedAgreement->getRawOriginal('status'),
            'scope' => $mutatedAgreement->getRawOriginal('scope'),
            'authority_fingerprint' => $mutatedAgreement->getRawOriginal('authority_fingerprint'),
            'reviewed_by' => $mutatedAgreement->getRawOriginal('reviewed_by'),
            'last_review_reason' => $mutatedAgreement->getRawOriginal('last_review_reason'),
        ];
        try {
            app(DistributionAgreementService::class)->transition($mutatedAgreement, 'verified', $agreementReviewer, 'Historical fixture review.', 'verified-listing-agreement-restored-event');
            $this->fail('Fingerprint-stale agreement replay must reject.');
        } catch (ValidationException) {
            $this->assertSame($eventCount, OrganizationDistributionAgreementEvent::count());
            $freshAgreement = $mutatedAgreement->fresh();
            $this->assertSame($resourceFields, [
                'status' => $freshAgreement->getRawOriginal('status'),
                'scope' => $freshAgreement->getRawOriginal('scope'),
                'authority_fingerprint' => $freshAgreement->getRawOriginal('authority_fingerprint'),
                'reviewed_by' => $freshAgreement->getRawOriginal('reviewed_by'),
                'last_review_reason' => $freshAgreement->getRawOriginal('last_review_reason'),
            ]);
        }
    }

    private function organization(string $slug, array $types, string $status): Organization
    {
        $organization = Organization::create([
            'legal_name' => $slug,
            'display_name' => $slug,
            'slug' => $slug,
            'organization_types' => $types,
            'status' => $status,
            'verification_document' => $status === 'verified' ? 'organizations/'.$slug.'.pdf' : null,
            'submitted_at' => $status === 'verified' ? now()->subDay() : null,
            'verified_at' => $status === 'verified' ? now() : null,
            'verified_by' => $status === 'verified' ? User::factory()->create(['role' => 'admin'])->id : null,
            'last_review_reason' => $status === 'verified' ? 'Fixture legal review.' : null,
        ]);
        if ($status === 'verified') {
            OrganizationReviewEvent::create(['organization_id' => $organization->id, 'actor_id' => $organization->verified_by, 'from_status' => 'pending_review', 'to_status' => 'verified', 'reason' => 'Fixture legal review.', 'operation_key' => 'organization-fixture-'.$slug]);
        }

        return $organization;
    }

    private function relationship(Vendor $vendor, Organization $organization, string $role): VendorOrganizationRelationship
    {
        $reviewer = User::factory()->create(['role' => 'admin']);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => $role,
            'status' => 'verified',
            'is_demo' => false,
            'evidence_mode' => 'real_document',
            'evidence_document' => 'organizations/'.$role.'.pdf',
            'submitted_at' => now()->subDays(2),
            'verified_at' => now(),
            'reviewed_by' => $reviewer->id,
            'last_review_reason' => 'Fixture relationship review.',
            'effective_from' => now()->subDay(),
            'operation_key' => "partner-commerce-{$vendor->id}-{$organization->id}-{$role}",
        ]);
        OrganizationRelationshipEvent::create(['vendor_organization_relationship_id' => $relationship->id, 'actor_id' => $reviewer->id, 'from_status' => 'submitted', 'to_status' => 'verified', 'reason' => 'Fixture relationship review.', 'operation_key' => 'relationship-fixture-event-'.$relationship->id]);

        return $relationship;
    }
}

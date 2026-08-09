<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCommercialParty;
use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationReviewEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Services\OrganizationRelationshipService;
use App\Services\OrganizationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOrganizationShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_showcase_only_exposes_authoritative_organizations_and_canonical_book_parties(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'vendor']);
        $organization = $this->acceptedOrganization($admin, 'nxb-tre-demo', 'demo');
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Gian hang NXB Tre',
            'slug' => 'nxb-tre-shop',
            'status' => 'active',
            'onboarding_status' => 'approved',
            'primary_organization_id' => $organization->id,
            'is_demo' => true,
        ]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $vendor->id,
            'organization_id' => $organization->id,
            'role' => 'self_legal_entity',
            'status' => 'submitted',
            'is_demo' => true,
            'evidence_mode' => 'demo_statement',
            'demo_reference' => 'DEMO-SHOWCASE-REL',
            'submitted_at' => now()->subDay(),
            'operation_key' => 'showcase-self-relationship',
        ]);
        app(OrganizationRelationshipService::class)->transition(
            $relationship,
            'demo_accepted',
            $admin,
            'Demo relationship reviewed.',
            'showcase-self-relationship-review',
        );
        $category = Category::create(['name' => 'Manga', 'slug' => 'manga']);
        $canonicalBook = $this->book($vendor, $category, 'canonical-showcase-book');
        BookCommercialParty::create([
            'book_id' => $canonicalBook->id,
            'organization_id' => $organization->id,
            'vendor_organization_relationship_id' => $relationship->id,
            'role' => 'publisher',
            'status' => 'demo_accepted',
            'active_slot' => 'active',
        ]);
        $rawPartyBook = $this->book($vendor, $category, 'raw-party-showcase-book');
        BookCommercialParty::create([
            'book_id' => $rawPartyBook->id,
            'organization_id' => $organization->id,
            'role' => 'supplier',
            'status' => 'pending',
            'active_slot' => 'active',
        ]);
        $primaryFallbackBook = $this->book($vendor, $category, 'primary-fallback-showcase-book');

        $response = $this->getJson('/api/organizations/nxb-tre-demo')->assertOk();

        $response->assertJsonPath('data.legal_name', 'Nxb Tre Demo Legal Entity')
            ->assertJsonPath('data.published_books.0.slug', $canonicalBook->slug)
            ->assertJsonMissing(['tax_code' => 'PRIVATE-TAX-NXB-TRE-DEMO'])
            ->assertJsonMissing(['license_number' => 'PRIVATE-LICENSE-NXB-TRE-DEMO']);
        $this->assertStringNotContainsString('verification_document', $response->getContent());
        $this->assertSame([$canonicalBook->id], collect($response->json('data.published_books'))->pluck('id')->all());
        $this->assertNotSame($canonicalBook->id, $rawPartyBook->id);
        $this->assertNotSame($canonicalBook->id, $primaryFallbackBook->id);
    }

    public function test_public_index_and_show_fail_closed_for_status_only_missing_event_non_admin_and_stale_fingerprints(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $nonAdmin = User::factory()->create(['role' => 'vendor']);
        $valid = $this->acceptedOrganization($admin, 'public-valid-live', 'live');
        $statusOnly = Organization::create([
            'legal_name' => 'Status only', 'display_name' => 'Status only', 'slug' => 'public-status-only',
            'organization_types' => ['publisher'], 'status' => 'verified', 'data_mode' => 'live',
        ]);
        $missingEvent = Organization::create([
            'legal_name' => 'Missing event', 'display_name' => 'Missing event', 'slug' => 'public-missing-event',
            'organization_types' => ['publisher'], 'status' => 'verified', 'data_mode' => 'live',
            'verification_document' => 'organizations/missing-event.pdf', 'submitted_at' => now()->subDay(),
            'verified_at' => now(), 'verified_by' => $admin->id, 'last_review_reason' => 'Reviewed.',
        ]);
        $nonAdminEvent = Organization::create([
            'legal_name' => 'Non admin event', 'display_name' => 'Non admin event', 'slug' => 'public-non-admin-event',
            'organization_types' => ['publisher'], 'status' => 'verified', 'data_mode' => 'live',
            'verification_document' => 'organizations/non-admin.pdf', 'submitted_at' => now()->subDay(),
            'verified_at' => now(), 'verified_by' => $nonAdmin->id, 'last_review_reason' => 'Reviewed.',
        ]);
        OrganizationReviewEvent::create([
            'organization_id' => $nonAdminEvent->id, 'actor_id' => $nonAdmin->id, 'from_status' => 'pending_review',
            'to_status' => 'verified', 'reason' => 'Reviewed.', 'operation_key' => 'public-non-admin-event-review',
        ]);
        $stale = $this->acceptedOrganization($admin, 'public-stale-fingerprint', 'live');
        $stale->update(['legal_name' => 'Mutated after review']);

        $this->getJson('/api/organizations')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $valid->id);
        foreach ([$statusOnly, $missingEvent, $nonAdminEvent, $stale] as $hidden) {
            $this->getJson('/api/organizations/'.$hidden->slug)->assertNotFound();
        }
    }

    private function acceptedOrganization(User $admin, string $slug, string $mode): Organization
    {
        $organization = Organization::create([
            'legal_name' => ucwords(str_replace('-', ' ', $slug)).' Legal Entity',
            'display_name' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'organization_types' => ['publisher', 'supplier'],
            'tax_code' => 'PRIVATE-TAX-'.strtoupper($slug),
            'license_number' => 'PRIVATE-LICENSE-'.strtoupper($slug),
            'status' => 'pending_review',
            'data_mode' => $mode,
            'verification_document' => $mode === 'live' ? 'organizations/'.$slug.'.pdf' : null,
            'submitted_at' => $mode === 'live' ? now()->subDay() : null,
        ]);
        app(OrganizationReviewService::class)->transition(
            $organization,
            $mode === 'demo' ? 'demo_accepted' : 'verified',
            $admin,
            'Organization evidence reviewed.',
            'public-organization-review-'.$slug,
        );

        return $organization->fresh();
    }

    private function book(Vendor $vendor, Category $category, string $slug): Book
    {
        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'title' => $slug,
            'slug' => $slug, 'author' => 'Author', 'price' => 45000, 'stock' => 10,
            'status' => 'published', 'publishing_status' => 'published',
        ]);
    }
}

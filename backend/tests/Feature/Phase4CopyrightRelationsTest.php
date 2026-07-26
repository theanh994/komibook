<?php

namespace Tests\Feature;

use App\Enums\AuthorOnboardingStatus;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookAuthor;
use App\Models\BookDrmSetting;
use App\Models\Category;
use App\Models\CopyrightClaim;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase4CopyrightRelationsTest extends TestCase
{
    use RefreshDatabase;

    private User $vendorUser;

    private Vendor $vendor;

    private User $authorUser;

    private Author $author;

    private User $admin;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->vendorUser = User::factory()->create(['role' => 'vendor']);
        $this->vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $this->vendorUser->id, 'shop_name' => 'Rights Shop', 'slug' => 'rights-shop',
            'status' => 'active', 'onboarding_status' => 'approved',
        ]);
        $this->authorUser = User::factory()->create(['role' => 'customer']);
        $this->author = Author::create([
            'user_id' => $this->authorUser->id, 'pen_name' => 'Rights Author', 'status' => 'active',
            'onboarding_status' => AuthorOnboardingStatus::Approved, 'phone_verified_at' => now(),
        ]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Rights', 'slug' => 'rights']);
        $this->book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $this->vendor->id, 'category_id' => $category->id, 'title' => 'Rights Book',
            'slug' => 'rights-book', 'author' => 'Legacy Display Only', 'type' => 'ebook',
            'price' => 10000, 'stock' => 0, 'status' => 'draft',
        ]);
    }

    public function test_vendor_invite_requires_explicit_author_acceptance(): void
    {
        $response = $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/authors", [
            'author_id' => $this->author->id, 'role' => 'primary', 'operation_key' => 'invite-primary',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $relationId = $response->json('data.id');
        $this->actingAs($this->authorUser)->patchJson("/api/author/book-authors/{$relationId}/respond", ['decision' => 'accepted'])
            ->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->assertDatabaseHas('rights_relation_events', ['subject_type' => 'book_author', 'subject_id' => $relationId, 'action' => 'invited']);
        $this->assertDatabaseHas('user_notifications', ['operation_key' => 'notification:invite-primary']);
    }

    public function test_accepted_coauthor_is_explicit_claim_participant(): void
    {
        $this->acceptPrimaryRelation();
        $coauthorUser = User::factory()->create();
        $coauthor = Author::create([
            'user_id' => $coauthorUser->id, 'pen_name' => 'Coauthor', 'status' => 'active',
            'onboarding_status' => 'approved', 'phone_verified_at' => now(),
        ]);
        $invite = $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/authors", [
            'author_id' => $coauthor->id, 'role' => 'coauthor', 'operation_key' => 'invite-coauthor',
        ])->assertCreated();
        $this->actingAs($coauthorUser)->patchJson('/api/author/book-authors/'.$invite->json('data.id').'/respond', [
            'decision' => 'accepted', 'operation_key' => 'accept-coauthor',
        ])->assertOk();

        $claim = $this->createClaim();
        $this->assertDatabaseHas('copyright_claim_authors', [
            'copyright_claim_id' => $claim->id, 'author_id' => $coauthor->id, 'role' => 'coauthor',
        ]);
    }

    public function test_legacy_author_string_and_vendor_ownership_do_not_authorize_claim(): void
    {
        $this->actingAs($this->authorUser)->post("/api/author/books/{$this->book->id}/copyright", $this->claimPayload())
            ->assertForbidden();
    }

    public function test_claim_lifecycle_private_evidence_audit_and_dispute(): void
    {
        $this->acceptPrimaryRelation();
        $claimResponse = $this->actingAs($this->authorUser)->post("/api/author/books/{$this->book->id}/copyright", $this->claimPayload());
        $claimResponse->assertCreated()->assertJsonPath('data.status', 'draft');
        $claim = CopyrightClaim::findOrFail($claimResponse->json('data.id'));
        Storage::disk('private')->assertExists($claim->evidence_document);

        $this->actingAs($this->authorUser)->postJson("/api/author/copyright/{$claim->id}/submit", ['operation_key' => 'claim-submit'])
            ->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->actingAs($this->admin)->patchJson("/api/admin/copyright-claims/{$claim->id}/transition", [
            'to_status' => 'under_review', 'operation_key' => 'claim-review',
        ])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/admin/copyright-claims/{$claim->id}/transition", [
            'to_status' => 'verified', 'operation_key' => 'claim-verify',
        ])->assertOk()->assertJsonPath('data.status', 'verified');
        $this->actingAs($this->admin)->patchJson("/api/admin/copyright-claims/{$claim->id}/transition", [
            'to_status' => 'disputed', 'reason' => 'Có khiếu nại quyền sở hữu.', 'operation_key' => 'claim-dispute',
        ])->assertOk()->assertJsonPath('data.status', 'disputed');

        $this->assertDatabaseHas('copyright_claim_events', ['operation_key' => 'claim-verify', 'to_status' => 'verified']);
        $other = User::factory()->create();
        $this->actingAs($other)->get("/api/author/copyright/{$claim->id}/evidence")->assertForbidden();
        $this->actingAs($this->authorUser)->get("/api/author/copyright/{$claim->id}/evidence")->assertOk();
    }

    public function test_overlapping_live_claim_is_rejected(): void
    {
        $this->acceptPrimaryRelation();
        $first = $this->createClaim();
        $this->actingAs($this->authorUser)->postJson("/api/author/copyright/{$first->id}/submit", ['operation_key' => 'first-submit'])->assertOk();
        $second = $this->createClaim();
        $this->actingAs($this->authorUser)->postJson("/api/author/copyright/{$second->id}/submit", ['operation_key' => 'second-submit'])
            ->assertUnprocessable();
    }

    public function test_changes_requested_claim_can_be_corrected_and_resubmitted(): void
    {
        $this->acceptPrimaryRelation();
        $claim = $this->createClaim();
        $this->actingAs($this->authorUser)->postJson("/api/author/copyright/{$claim->id}/submit", [
            'operation_key' => 'correction-submit',
        ])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/admin/copyright-claims/{$claim->id}/transition", [
            'to_status' => 'under_review', 'operation_key' => 'correction-review',
        ])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/admin/copyright-claims/{$claim->id}/transition", [
            'to_status' => 'changes_requested', 'reason' => 'Bổ sung số đăng ký.',
            'operation_key' => 'correction-request',
        ])->assertOk();

        $payload = $this->claimPayload();
        $payload['registration_number'] = 'REG-CORRECTED';
        unset($payload['evidence_document']);
        $this->actingAs($this->authorUser)->patch("/api/author/copyright/{$claim->id}", $payload)
            ->assertOk()->assertJsonPath('data.registration_number', 'REG-CORRECTED');
        $this->actingAs($this->authorUser)->postJson("/api/author/copyright/{$claim->id}/submit", [
            'operation_key' => 'correction-resubmit',
        ])->assertOk()->assertJsonPath('data.status', 'resubmitted')->assertJsonPath('data.application_version', 2);

        $this->assertDatabaseHas('copyright_claim_events', [
            'operation_key' => 'correction-resubmit', 'from_status' => 'changes_requested', 'to_status' => 'resubmitted',
        ]);
    }

    public function test_accepted_scoped_delegate_can_manage_but_revoked_delegate_cannot(): void
    {
        $this->acceptPrimaryRelation();
        $delegate = User::factory()->create();
        $response = $this->actingAs($this->authorUser)->postJson("/api/author/books/{$this->book->id}/delegations", [
            'delegate_user_id' => $delegate->id,
            'permissions' => ['manage_copyright'],
            'operation_key' => 'delegate-rights',
        ])->assertCreated();
        $delegationId = $response->json('data.id');
        $this->actingAs($delegate)->patchJson("/api/author/delegations/{$delegationId}/respond", ['decision' => 'accepted'])->assertOk();
        $this->actingAs($delegate)->post("/api/author/books/{$this->book->id}/copyright", $this->claimPayload())->assertCreated();

        $this->actingAs($this->authorUser)->patchJson("/api/author/delegations/{$delegationId}/revoke", ['reason' => 'Kết thúc ủy quyền.'])->assertOk();
        $this->actingAs($delegate)->post("/api/author/books/{$this->book->id}/copyright", $this->claimPayload())->assertForbidden();
    }

    public function test_technical_drm_never_changes_book_publish_state_or_verifies_rights(): void
    {
        $this->actingAs($this->vendorUser)->putJson("/api/vendor/books/{$this->book->id}/drm-settings", [
            'social_drm' => true, 'hard_drm' => false, 'copy_limit_percent' => 10,
            'allow_printing' => false, 'license_type' => 'all_rights_reserved',
            'copyright_number' => 'UNTRUSTED-STRING',
        ])->assertOk();

        $this->assertSame('draft', $this->book->fresh()->status);
        $this->assertDatabaseMissing('book_drm_settings', ['book_id' => $this->book->id, 'copyright_number' => 'UNTRUSTED-STRING']);
        $this->assertSame(0, CopyrightClaim::count());
        $this->assertInstanceOf(BookDrmSetting::class, $this->book->drmSetting()->first());
    }

    private function acceptPrimaryRelation(): BookAuthor
    {
        return BookAuthor::create([
            'book_id' => $this->book->id, 'author_id' => $this->author->id,
            'invited_by' => $this->vendorUser->id, 'role' => 'primary', 'status' => 'accepted',
            'accepted_at' => now(), 'operation_key' => 'accepted-primary-'.$this->book->id,
        ]);
    }

    private function createClaim(): CopyrightClaim
    {
        $response = $this->actingAs($this->authorUser)->post("/api/author/books/{$this->book->id}/copyright", $this->claimPayload());
        $response->assertCreated();

        return CopyrightClaim::findOrFail($response->json('data.id'));
    }

    private function claimPayload(): array
    {
        return [
            'registration_type' => 'original_work',
            'registration_number' => 'REG-'.uniqid(),
            'rights_scope' => ['digital', 'distribute'],
            'territory_scope' => ['VN'],
            'valid_from' => '2026-01-01',
            'valid_until' => '2030-12-31',
            'evidence_document' => UploadedFile::fake()->create('evidence.pdf', 20),
        ];
    }
}

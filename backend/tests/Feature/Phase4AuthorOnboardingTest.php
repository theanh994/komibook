<?php

namespace Tests\Feature;

use App\Enums\AuthorOnboardingStatus;
use App\Http\Resources\UserResource;
use App\Models\Author;
use App\Models\AuthorOnboardingEvent;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase4AuthorOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $authorUser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->authorUser = User::factory()->create(['role' => 'customer']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_author_submits_private_profile_with_audit_and_notification(): void
    {
        $response = $this->actingAs($this->authorUser)->post('/api/auth/register-author', [
            'pen_name' => 'Bút Danh Mới',
            'bio' => 'Tiểu sử',
            'bank_account_number' => '0123456789',
            'bank_name' => 'Test Bank',
            'bank_holder_name' => 'AUTHOR USER',
            'identity_document' => UploadedFile::fake()->image('identity.jpg'),
            'terms_accepted' => true,
            'operation_key' => 'author-submit-1',
        ]);

        $response->assertCreated()->assertJsonPath('data.onboarding_status', 'submitted');
        $author = Author::where('user_id', $this->authorUser->id)->firstOrFail();
        Storage::disk('private')->assertExists($author->identity_document);
        $this->assertDatabaseHas('author_onboarding_events', [
            'author_id' => $author->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'operation_key' => 'author-submit-1',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->authorUser->id,
            'operation_key' => 'notification:author-submit-1',
        ]);
    }

    public function test_incomplete_draft_is_persisted_without_placeholder_data(): void
    {
        $this->actingAs($this->authorUser)->patchJson('/api/author/draft', [
            'pen_name' => 'Bút Danh Nháp',
            'bio' => 'Sẽ bổ sung sau',
        ])->assertOk()->assertJsonPath('data.onboarding_status', 'draft');

        $this->assertDatabaseHas('authors', [
            'user_id' => $this->authorUser->id,
            'pen_name' => 'Bút Danh Nháp',
            'bank_account_number' => null,
            'identity_document' => null,
        ]);
    }

    public function test_changes_requested_profile_can_be_updated_and_resubmitted(): void
    {
        $author = $this->completeAuthor(AuthorOnboardingStatus::UnderReview);
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/authors/{$author->id}/transition", [
            'to_status' => 'changes_requested',
            'reason' => 'Ảnh định danh cần rõ hơn.',
            'operation_key' => 'request-author-change',
        ])->assertOk();

        $this->actingAs($this->authorUser)->post('/api/auth/register-author', [
            'pen_name' => 'Bút Danh Đã Sửa',
            'bio' => 'Bổ sung',
            'bank_account_number' => '999999',
            'bank_name' => 'New Bank',
            'bank_holder_name' => 'AUTHOR USER',
            'identity_document' => UploadedFile::fake()->image('new-identity.jpg'),
            'terms_accepted' => true,
            'operation_key' => 'author-resubmit-1',
        ])->assertOk()->assertJsonPath('data.onboarding_status', 'resubmitted');

        $this->assertSame(2, $author->fresh()->application_version);
    }

    public function test_approval_requires_phone_and_never_grants_vendor_role_or_profile(): void
    {
        $author = $this->completeAuthor(AuthorOnboardingStatus::UnderReview, phoneVerified: false);
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/authors/{$author->id}/transition", [
            'to_status' => 'approved',
            'operation_key' => 'approve-without-phone',
        ])->assertUnprocessable();

        $author->update(['phone_verified_at' => now()]);
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/authors/{$author->id}/transition", [
            'to_status' => 'approved',
            'operation_key' => 'approve-with-phone',
        ])->assertOk()->assertJsonPath('data.onboarding_status', 'approved');

        $this->assertSame('customer', $this->authorUser->fresh()->role);
        $this->assertFalse(Vendor::withoutGlobalScopes()->where('user_id', $this->authorUser->id)->exists());
    }

    public function test_invalid_transition_and_missing_reason_are_rejected(): void
    {
        $draft = $this->completeAuthor(AuthorOnboardingStatus::Draft);
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/authors/{$draft->id}/transition", [
            'to_status' => 'approved',
        ])->assertUnprocessable();

        $draft->update(['onboarding_status' => AuthorOnboardingStatus::UnderReview]);
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/authors/{$draft->id}/transition", [
            'to_status' => 'rejected',
        ])->assertUnprocessable();
    }

    public function test_transition_operation_key_is_idempotent(): void
    {
        $author = $this->completeAuthor(AuthorOnboardingStatus::Submitted);
        $payload = ['to_status' => 'under_review', 'operation_key' => 'review-idempotent'];

        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/authors/{$author->id}/transition", $payload)->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/authors/{$author->id}/transition", $payload)->assertOk();

        $this->assertDatabaseCount('author_onboarding_events', 1);
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_onboarding_audit_events_are_append_only(): void
    {
        $author = $this->completeAuthor(AuthorOnboardingStatus::Submitted);
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/authors/{$author->id}/transition", [
            'to_status' => 'under_review',
            'operation_key' => 'immutable-event',
        ])->assertOk();

        $event = AuthorOnboardingEvent::where('operation_key', 'immutable-event')->firstOrFail();
        $this->expectException(\LogicException::class);
        $event->update(['reason' => 'rewritten']);
    }

    public function test_approved_author_can_be_suspended_and_private_document_is_isolated(): void
    {
        $author = $this->completeAuthor(AuthorOnboardingStatus::Approved);
        $other = User::factory()->create();

        $this->actingAs($other)->get("/api/authors/{$author->id}/identity-document")->assertForbidden();
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/authors/{$author->id}/transition", [
            'to_status' => 'suspended',
            'reason' => 'Tạm dừng để xác minh khiếu nại.',
            'operation_key' => 'suspend-author',
        ])->assertOk();

        $this->assertSame(AuthorOnboardingStatus::Suspended, $author->fresh()->onboarding_status);
    }

    public function test_user_resource_exposes_independent_capabilities(): void
    {
        $this->completeAuthor(AuthorOnboardingStatus::Approved);
        $data = (new UserResource($this->authorUser->fresh()->load(['author', 'vendor'])))->resolve(request());

        $this->assertTrue($data['capabilities']['approved_author']);
        $this->assertFalse($data['capabilities']['active_vendor']);
    }

    private function completeAuthor(AuthorOnboardingStatus $status, bool $phoneVerified = true): Author
    {
        $path = UploadedFile::fake()->image('identity.jpg')->store('authors/cccd', 'private');

        return Author::create([
            'user_id' => $this->authorUser->id,
            'pen_name' => 'Test Author',
            'bio' => 'Bio',
            'identity_document' => $path,
            'phone_verified_at' => $phoneVerified ? now() : null,
            'bank_account_number' => '123456',
            'bank_name' => 'Test Bank',
            'bank_holder_name' => 'TEST AUTHOR',
            'status' => $status === AuthorOnboardingStatus::Approved ? 'active' : 'pending',
            'onboarding_status' => $status,
            'application_version' => 1,
            'terms_accepted_at' => now(),
        ]);
    }
}

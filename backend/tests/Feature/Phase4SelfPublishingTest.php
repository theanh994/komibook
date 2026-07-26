<?php

namespace Tests\Feature;

use App\Enums\AuthorOnboardingStatus;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookAuthor;
use App\Models\BookChapter;
use App\Models\Category;
use App\Models\CopyrightClaim;
use App\Models\RoyaltyLedgerEntry;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4SelfPublishingTest extends TestCase
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
        $this->vendorUser = User::factory()->create(['role' => 'vendor']);
        $this->vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $this->vendorUser->id, 'shop_name' => 'Publish Shop', 'slug' => 'publish-shop',
            'status' => 'active', 'onboarding_status' => 'approved',
        ]);
        $this->authorUser = User::factory()->create();
        $this->author = Author::create([
            'user_id' => $this->authorUser->id, 'pen_name' => 'Publishing Author', 'status' => 'active',
            'onboarding_status' => AuthorOnboardingStatus::Approved, 'phone_verified_at' => now(),
        ]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Publishing', 'slug' => 'publishing']);
        $this->book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $this->vendor->id, 'category_id' => $category->id, 'title' => 'Publishable Book',
            'slug' => 'publishable-book', 'author' => 'Display only', 'description' => 'Complete description.',
            'cover_image' => 'books/covers/cover.jpg', 'type' => 'ebook', 'file_path' => 'ebooks/book.pdf',
            'price' => 50000, 'stock' => 0, 'status' => 'draft', 'publishing_status' => 'draft',
        ]);
    }

    public function test_ineligible_book_cannot_be_submitted(): void
    {
        $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/submit", [
            'operation_key' => 'ineligible-submit',
        ])->assertUnprocessable()->assertJsonValidationErrors(['authors', 'copyright', 'royalty']);
    }

    public function test_eligible_book_is_reviewed_and_published_with_immutable_snapshot(): void
    {
        $this->makeEligible();
        $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/submit", ['operation_key' => 'book-submit'])
            ->assertOk()->assertJsonPath('data.publishing_status', 'submitted_for_review');
        $this->actingAs($this->admin)->patchJson("/api/admin/books/{$this->book->id}/publishing-transition", [
            'to_status' => 'approved', 'operation_key' => 'book-approve',
        ])->assertOk()->assertJsonPath('data.publishing_status', 'approved');
        $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/publish", ['operation_key' => 'book-publish'])
            ->assertOk()->assertJsonPath('data.publishing_status', 'published');

        $this->assertDatabaseHas('book_publishing_events', ['operation_key' => 'book-publish', 'to_status' => 'published']);
        $this->assertDatabaseHas('book_published_revisions', ['book_id' => $this->book->id, 'version' => 1]);
        $this->assertSame('published', $this->book->fresh()->status);
        $this->assertSame(0, RoyaltyLedgerEntry::count(), 'Publishing must not invent royalty history.');
    }

    public function test_changes_requested_returns_to_draft_and_resubmits(): void
    {
        $this->makeEligible();
        $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/submit", ['operation_key' => 'first-submit'])->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/admin/books/{$this->book->id}/publishing-transition", [
            'to_status' => 'changes_requested', 'reason' => 'Cập nhật phần giới thiệu.', 'operation_key' => 'book-changes',
        ])->assertOk();
        $this->actingAs($this->vendorUser)->patchJson("/api/vendor/books/{$this->book->id}/return-to-draft", ['operation_key' => 'book-redraft'])
            ->assertOk()->assertJsonPath('data.publishing_status', 'draft');
        $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/submit", ['operation_key' => 'book-resubmit'])
            ->assertOk()->assertJsonPath('data.publishing_status', 'resubmitted');
    }

    public function test_direct_publish_status_is_rejected(): void
    {
        $this->actingAs($this->vendorUser)->putJson("/api/vendor/books/{$this->book->id}", ['status' => 'published'])
            ->assertOk();
        $this->assertSame('draft', $this->book->fresh()->status);
        $this->actingAs($this->admin)->patchJson("/api/admin/books/{$this->book->id}/status", ['status' => 'published'])
            ->assertUnprocessable();

        $otherUser = User::factory()->create(['role' => 'vendor']);
        Vendor::withoutGlobalScopes()->create([
            'user_id' => $otherUser->id, 'shop_name' => 'Other Shop', 'slug' => 'other-shop',
            'status' => 'active', 'onboarding_status' => 'approved',
        ]);
        $this->actingAs($otherUser)->getJson("/api/vendor/books/{$this->book->id}/publishing")->assertNotFound();
    }

    public function test_chapter_autosave_conflict_restore_order_import_and_preview_access(): void
    {
        $chapter = $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/chapters", [
            'title' => 'Chapter one', 'content' => 'v1', 'is_free' => true,
        ])->assertCreated()->json('data');
        $this->actingAs($this->vendorUser)->patchJson("/api/vendor/books/{$this->book->id}/chapters/{$chapter['id']}/autosave", [
            'title' => 'Chapter one', 'content' => 'v2', 'is_free' => true, 'expected_revision' => 1,
        ])->assertOk()->assertJsonPath('data.current_revision', 2);
        $this->actingAs($this->vendorUser)->patchJson("/api/vendor/books/{$this->book->id}/chapters/{$chapter['id']}/autosave", [
            'title' => 'Stale', 'content' => 'lost', 'expected_revision' => 1,
        ])->assertUnprocessable();
        $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/chapters/{$chapter['id']}/restore/1")
            ->assertOk()->assertJsonPath('data.content', 'v1')->assertJsonPath('data.current_revision', 3);
        $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/chapters-import", [
            'chapters' => [['title' => 'Imported', 'content' => 'Imported body', 'is_free' => false]],
        ])->assertCreated();
        $ids = $this->book->chapters()->pluck('id')->reverse()->values()->all();
        $this->actingAs($this->vendorUser)->patchJson("/api/vendor/books/{$this->book->id}/chapters-order", ['chapter_ids' => $ids])
            ->assertOk()->assertJsonPath('data.0.id', $ids[0]);

        $this->book->update(['status' => 'published', 'publishing_status' => 'published']);
        $this->getJson("/api/books/{$this->book->id}/chapters/{$chapter['id']}/preview")
            ->assertOk()->assertJsonPath('data.content', 'v1');
        $paid = BookChapter::where('book_id', $this->book->id)->where('is_free', false)->firstOrFail();
        $this->getJson("/api/books/{$this->book->id}/chapters/{$paid->id}/preview")->assertForbidden();
    }

    private function makeEligible(): void
    {
        BookAuthor::create([
            'book_id' => $this->book->id, 'author_id' => $this->author->id, 'invited_by' => $this->vendorUser->id,
            'role' => 'primary', 'status' => 'accepted', 'accepted_at' => now(), 'operation_key' => 'eligible-author',
        ]);
        CopyrightClaim::create([
            'book_id' => $this->book->id, 'owner_author_id' => $this->author->id, 'registration_type' => 'original_work',
            'registration_number' => 'RIGHT-1', 'rights_scope' => ['digital'], 'territory_scope' => ['VN'],
            'evidence_document' => 'copyright/evidence/one.pdf', 'status' => 'verified', 'verified_at' => now(),
        ]);
        $agreement = $this->actingAs($this->vendorUser)->postJson("/api/vendor/books/{$this->book->id}/royalty-agreements", [
            'shares' => [['author_id' => $this->author->id, 'share_percent' => 100]], 'operation_key' => 'royalty-v1',
        ])->assertCreated()->json('data');
        $this->actingAs($this->authorUser)->postJson("/api/author/royalty-agreements/{$agreement['id']}/accept", [
            'operation_key' => 'royalty-author-accept',
        ])->assertCreated();
    }
}

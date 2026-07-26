<?php

namespace Tests\Feature;

use App\Enums\VendorOnboardingStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOnboardingEvent;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase4VendorOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->applicant = User::factory()->create(['role' => 'customer']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_vendor_can_save_real_incomplete_draft(): void
    {
        $this->actingAs($this->applicant)->patchJson('/api/vendor-onboarding/draft', [
            'shop_name' => 'Nhà sách nháp',
            'slug' => 'nha-sach-nhap',
        ])->assertOk()->assertJsonPath('data.onboarding_status', 'draft');

        $this->assertDatabaseHas('vendors', [
            'user_id' => $this->applicant->id,
            'legal_name' => null,
            'status' => 'inactive',
        ]);
    }

    public function test_vendor_submits_private_legal_and_payout_profile(): void
    {
        $response = $this->actingAs($this->applicant)->post('/api/vendor-onboarding/register', $this->applicationPayload('vendor-submit'));
        $response->assertCreated()->assertJsonPath('data.onboarding_status', 'submitted');

        $vendor = Vendor::withoutGlobalScopes()->where('user_id', $this->applicant->id)->firstOrFail();
        Storage::disk('private')->assertExists($vendor->business_registration_document);
        $this->assertDatabaseHas('vendor_onboarding_events', ['vendor_id' => $vendor->id, 'operation_key' => 'vendor-submit']);
        $this->assertDatabaseHas('user_notifications', ['operation_key' => 'notification:vendor-submit']);
    }

    public function test_approval_grants_vendor_compatibility_role_but_not_authorship(): void
    {
        $vendor = $this->completeVendor(VendorOnboardingStatus::UnderReview);
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/vendors/{$vendor->id}/transition", [
            'to_status' => 'approved',
            'operation_key' => 'vendor-approve',
        ])->assertOk();

        $this->applicant->refresh();
        $this->assertSame('vendor', $this->applicant->role);
        $this->assertNull($this->applicant->author);
        $this->actingAs($this->applicant)->getJson('/api/vendor/dashboard-stats')->assertOk();
    }

    public function test_inactive_and_suspended_vendor_cannot_access_commerce_routes(): void
    {
        $this->applicant->update(['role' => 'vendor']);
        $vendor = $this->completeVendor(VendorOnboardingStatus::Submitted);
        $this->actingAs($this->applicant)->getJson('/api/vendor/dashboard-stats')->assertForbidden();

        $vendor->update(['onboarding_status' => VendorOnboardingStatus::Approved, 'status' => 'active']);
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/vendors/{$vendor->id}/transition", [
            'to_status' => 'suspended',
            'reason' => 'Tạm dừng để rà soát pháp lý.',
            'operation_key' => 'vendor-suspend',
        ])->assertOk();
        $this->actingAs($this->applicant)->getJson('/api/vendor/dashboard-stats')->assertForbidden();
    }

    public function test_inactive_vendor_books_are_not_publicly_sellable(): void
    {
        $vendor = $this->completeVendor(VendorOnboardingStatus::Submitted);
        $category = Category::create(['name' => 'Vendor Gate', 'slug' => 'vendor-gate']);
        Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Hidden inactive book',
            'slug' => 'hidden-inactive-book',
            'author' => 'Legacy display',
            'type' => 'ebook',
            'price' => 10000,
            'stock' => 0,
            'status' => 'published',
        ]);

        $this->getJson('/api/books')->assertOk()->assertJsonMissing(['slug' => 'hidden-inactive-book']);
        $this->getJson('/api/books/hidden-inactive-book')->assertNotFound();

        $vendor->update(['onboarding_status' => VendorOnboardingStatus::Approved, 'status' => 'active']);
        $this->getJson('/api/books')->assertOk()->assertJsonFragment(['slug' => 'hidden-inactive-book']);
    }

    public function test_inactive_vendor_ebook_is_rejected_even_by_direct_checkout_id(): void
    {
        $vendor = $this->completeVendor(VendorOnboardingStatus::Submitted);
        $category = Category::create(['name' => 'Checkout Gate', 'slug' => 'checkout-gate']);
        $book = Book::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'title' => 'Inactive Ebook',
            'slug' => 'inactive-ebook', 'author' => 'Legacy', 'type' => 'ebook', 'price' => 10000,
            'stock' => 0, 'status' => 'published',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nhà bán của sản phẩm đang ngừng hoạt động');
        app(CheckoutService::class)->processCheckout(
            [['book_id' => $book->id, 'quantity' => 1]],
            ['payment_method' => 'cod'],
            $this->applicant->id,
        );
    }

    public function test_private_vendor_document_is_cross_account_isolated(): void
    {
        $vendor = $this->completeVendor(VendorOnboardingStatus::Submitted);
        $other = User::factory()->create();

        $this->actingAs($other)->get("/api/vendors/{$vendor->id}/documents/business")->assertForbidden();
        $this->actingAs($this->applicant)->get("/api/vendors/{$vendor->id}/documents/business")->assertOk();
    }

    public function test_vendor_transition_is_idempotent_and_audit_is_append_only(): void
    {
        $vendor = $this->completeVendor(VendorOnboardingStatus::Submitted);
        $payload = ['to_status' => 'under_review', 'operation_key' => 'vendor-review-once'];
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/vendors/{$vendor->id}/transition", $payload)->assertOk();
        $this->actingAs($this->admin)->patchJson("/api/admin/approvals/vendors/{$vendor->id}/transition", $payload)->assertOk();

        $this->assertSame(1, VendorOnboardingEvent::where('operation_key', 'vendor-review-once')->count());
        $this->expectException(\LogicException::class);
        VendorOnboardingEvent::where('operation_key', 'vendor-review-once')->firstOrFail()->update(['reason' => 'rewrite']);
    }

    public function test_cross_vendor_book_access_remains_isolated(): void
    {
        $vendor = $this->completeVendor(VendorOnboardingStatus::Approved);
        $this->applicant->update(['role' => 'vendor']);
        $otherUser = User::factory()->create(['role' => 'vendor']);
        $otherVendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $otherUser->id, 'shop_name' => 'Other', 'slug' => 'other-vendor',
            'status' => 'active', 'onboarding_status' => 'approved',
        ]);
        $category = Category::create(['name' => 'Isolation', 'slug' => 'isolation']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $otherVendor->id, 'category_id' => $category->id, 'title' => 'Other Book',
            'slug' => 'other-book', 'author' => 'Other', 'type' => 'physical', 'price' => 1000, 'stock' => 1, 'status' => 'draft',
        ]);

        $this->actingAs($this->applicant)->getJson("/api/vendor/books/{$book->id}")->assertNotFound();
        $this->assertTrue($vendor->isActive());
    }

    private function completeVendor(VendorOnboardingStatus $status): Vendor
    {
        return Vendor::withoutGlobalScopes()->create([
            'user_id' => $this->applicant->id,
            'shop_name' => 'Test Shop',
            'slug' => 'test-shop-'.$this->applicant->id,
            'description' => 'Test',
            'status' => $status === VendorOnboardingStatus::Approved ? 'active' : 'inactive',
            'onboarding_status' => $status,
            'legal_name' => 'TEST COMPANY',
            'tax_code' => 'TAX123',
            'business_registration_document' => UploadedFile::fake()->create('business.pdf', 10)->store('vendors/legal', 'private'),
            'representative_identity_document' => UploadedFile::fake()->image('representative.jpg')->store('vendors/legal', 'private'),
            'payout_bank_account' => '123456789',
            'payout_bank_name' => 'Test Bank',
            'payout_bank_holder' => 'TEST COMPANY',
            'terms_accepted_at' => now(),
        ]);
    }

    private function applicationPayload(string $operationKey): array
    {
        return [
            'shop_name' => 'Submitted Shop',
            'slug' => 'submitted-shop',
            'description' => 'Description',
            'legal_name' => 'SUBMITTED COMPANY',
            'tax_code' => 'TAX999',
            'business_registration_document' => UploadedFile::fake()->create('business.pdf', 10),
            'representative_identity_document' => UploadedFile::fake()->image('representative.jpg'),
            'payout_bank_account' => '987654321',
            'payout_bank_name' => 'Test Bank',
            'payout_bank_holder' => 'SUBMITTED COMPANY',
            'terms_accepted' => true,
            'operation_key' => $operationKey,
        ];
    }
}

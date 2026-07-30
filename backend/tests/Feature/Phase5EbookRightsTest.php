<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Services\EbookEntitlementService;
use App\Services\EbookVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5EbookRightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ebook_checkout_requires_terms_and_stores_immutable_snapshots(): void
    {
        [$buyer, $book] = $this->publishedEbook();
        $versionService = app(EbookVersionService::class);
        $versionService->release($book, null, 'Bản đầu');
        $latestVersion = $versionService->release($book->fresh(), null, 'Bản mới nhất lúc mua');
        $payload = [
            'items' => [['book_id' => $book->id, 'quantity' => 1]],
            'payment_method' => 'COD',
        ];

        $this->actingAs($buyer)->postJson('/api/checkout', $payload)->assertBadRequest();

        $response = $this->actingAs($buyer)
            ->postJson('/api/checkout', [...$payload, 'ebook_terms_accepted' => true])
            ->assertCreated();

        $order = Order::withoutGlobalScopes()->findOrFail($response->json('data.0.id'));
        $item = $order->orderItems()->firstOrFail();
        $this->assertTrue($item->ebook_consent_snapshot['accepted']);
        $this->assertTrue($item->ebook_consent_snapshot['non_returnable']);
        $this->assertSame('ebook', $item->product_taxonomy_snapshot['format']);
        $this->assertFalse($item->return_policy_snapshot['is_returnable']);
        $this->assertSame($latestVersion->id, $item->ebook_version_id);
        $this->assertSame('Digital delivery', $order->shipping_address);
        $this->assertSame('Không áp dụng', $order->phone);
    }

    public function test_physical_checkout_still_requires_shipping_contact(): void
    {
        [$buyer, $book] = $this->publishedEbook();
        $book->update(['type' => 'physical', 'format' => 'physical', 'stock' => 1]);

        $this->actingAs($buyer)
            ->postJson('/api/checkout', [
                'items' => [['book_id' => $book->id, 'quantity' => 1]],
                'payment_method' => 'COD',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_address', 'phone']);
    }

    public function test_purchaser_keeps_purchase_version_and_receives_new_versions(): void
    {
        [$buyer, $book] = $this->publishedEbook();
        $versionService = app(EbookVersionService::class);
        $versionOne = $versionService->release($book, null, 'Bản đầu');
        $versionTwo = $versionService->release($book->fresh(), null, 'Bản đang bán');

        $order = Order::withoutGlobalScopes()->create([
            'order_code' => 'ORD-EBOOK-VERSIONS',
            'user_id' => $buyer->id,
            'vendor_id' => $book->vendor_id,
            'total_amount' => $book->price,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'online',
            'shipping_address' => 'Digital',
            'phone' => '0900000000',
        ]);
        $item = $order->orderItems()->create([
            'book_id' => $book->id,
            'quantity' => 1,
            'price' => $book->price,
            'ebook_version_id' => $versionTwo->id,
            'ebook_consent_snapshot' => ['accepted' => true, 'non_returnable' => true],
        ]);
        app(EbookEntitlementService::class)->grantForOrder($order);

        $book->update(['description' => 'Nội dung cập nhật']);
        $versionThree = $versionService->release($book->fresh(), null, 'Bổ sung chương mới');

        $response = $this->actingAs($buyer)->getJson('/api/my-library')->assertOk();
        $versions = collect($response->json('data.0.available_versions'));

        $this->assertSame($versionTwo->id, $response->json('data.0.purchase_version_id'));
        $this->assertSame($versionTwo->version, $response->json('data.0.purchase_version'));
        $this->assertSame($versionThree->version, $response->json('data.0.latest_version'));
        $this->assertEqualsCanonicalizing([$versionTwo->id, $versionThree->id], $versions->pluck('id')->all());
        $this->assertNotContains($versionOne->id, $versions->pluck('id')->all());
        $this->assertDatabaseHas('ebook_entitlements', [
            'user_id' => $buyer->id,
            'book_id' => $book->id,
            'order_item_id' => $item->id,
            'purchase_version_id' => $versionTwo->id,
        ]);

        $this->actingAs($buyer)
            ->getJson("/api/orders/{$order->id}/ebooks/{$book->id}/generate-link")
            ->assertOk()
            ->assertJsonPath('data.version_id', $versionThree->id);

        $this->actingAs($buyer)
            ->getJson("/api/orders/{$order->id}/ebooks/{$book->id}/generate-link?version_id={$versionTwo->id}")
            ->assertOk()
            ->assertJsonPath('data.version_id', $versionTwo->id);

        $this->actingAs($buyer)
            ->getJson("/api/orders/{$order->id}/ebooks/{$book->id}/generate-link?version_id={$versionOne->id}")
            ->assertNotFound();
    }

    private function publishedEbook(): array
    {
        $buyer = User::factory()->create(['role' => 'customer']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Ebook Store',
            'slug' => 'ebook-store-'.strtolower(str()->random(4)),
            'status' => 'active',
        ]);
        $category = Category::create(['name' => 'Ebook', 'slug' => 'ebook-'.strtolower(str()->random(4))]);
        $policy = \App\Models\ReturnPolicyVersion::where('policy_key', 'ebook_non_returnable')->firstOrFail();
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Ebook quyền số',
            'slug' => 'ebook-rights-'.strtolower(str()->random(5)),
            'author' => 'Tác giả',
            'price' => 50000,
            'stock' => 0,
            'type' => 'ebook',
            'format' => 'ebook',
            'provenance' => 'self_published',
            'fulfillment_mode' => 'digital',
            'return_policy_version_id' => $policy->id,
            'status' => 'published',
            'publishing_status' => 'published',
            'file_path' => 'ebooks/version-one.pdf',
        ]);

        return [$buyer, $book];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SellerFulfillmentAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase7UsedBookSellerTest extends TestCase
{
    use RefreshDatabase;

    public function test_neutral_surface_keeps_legacy_owner_private_and_returns_book_contract(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => 'customer']);
        $address = SellerFulfillmentAddress::create([
            'user_id' => $seller->id,
            'recipient_name' => 'Người gửi',
            'phone' => '0900000000',
            'address_line' => 'Địa chỉ riêng tư',
            'province' => 'Hà Nội',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Sách cũ', 'slug' => 'sach-cu-neutral']);

        $created = $this->actingAs($seller)->postJson('/api/used-book-seller/listings', [
            'title' => 'Một cuốn sách đã đọc',
            'author_name' => 'Người viết trên bìa',
            'category_id' => $category->id,
            'price' => 45000,
            'condition' => 'good',
            'defects' => 'Xước nhẹ ở gáy',
            'quantity' => 3,
            'actual_photos' => [UploadedFile::fake()->image('actual.jpg')],
            'authenticity_attested' => true,
        ])->assertCreated()
            ->assertJsonPath('data.book.title', 'Một cuốn sách đã đọc')
            ->assertJsonPath('data.quantity_available', 3)
            ->assertJsonMissing(['seller_fulfillment_address_id' => $address->id])
            ->assertJsonMissing(['address_line' => 'Địa chỉ riêng tư']);

        $listingId = $created->json('data.id');

        $this->actingAs($seller)->getJson('/api/used-book-seller/listings')
            ->assertOk()
            ->assertJsonPath('meta.ownership', 'used_book_seller')
            ->assertJsonPath('meta.address_visibility', 'private')
            ->assertJsonPath('data.0.id', $listingId)
            ->assertJsonPath('data.0.book.category.name', 'Sách cũ')
            ->assertJsonPath('data.0.quantity_reserved', 0)
            ->assertJsonMissing(['seller_user_id' => $seller->id]);

        $this->actingAs($seller)->patchJson("/api/used-book-seller/listings/{$listingId}/inventory", [
            'quantity_available' => 2,
        ])->assertOk()->assertJsonPath('data.quantity_available', 2);

        $otherSeller = User::factory()->create(['role' => 'customer']);
        $this->actingAs($otherSeller)->patchJson("/api/used-book-seller/listings/{$listingId}/inventory", [
            'quantity_available' => 1,
        ])->assertForbidden();
    }

    public function test_customer_receives_used_book_seller_capability_without_actor_profile(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)->getJson('/api/used-book-seller/listings')
            ->assertOk()
            ->assertJsonPath('meta.ownership', 'used_book_seller');

        $this->assertDatabaseHas('used_book_seller_profiles', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }
}

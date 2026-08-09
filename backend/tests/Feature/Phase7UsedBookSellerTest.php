<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DemoWalletAccount;
use App\Models\DemoWalletLedgerEntry;
use App\Models\SellerFulfillmentAddress;
use App\Models\UsedBookSellerProfile;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
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

    public function test_get_used_book_seller_listings_does_not_provision_an_actor_profile(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $before = [Vendor::count(), UsedBookSellerProfile::count(), Warehouse::count(), WarehouseStock::count()];

        $this->actingAs($user)->getJson('/api/used-book-seller/listings')
            ->assertForbidden();

        $this->assertSame($before, [Vendor::count(), UsedBookSellerProfile::count(), Warehouse::count(), WarehouseStock::count()]);
    }

    public function test_wallet_get_without_account_returns_zero_without_writes(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $vendor = Vendor::create(['user_id' => $user->id, 'shop_name' => 'Wallet seller', 'slug' => 'wallet-seller-'.$user->id, 'status' => 'active', 'onboarding_status' => 'approved', 'business_model' => 'bookstore']);
        UsedBookSellerProfile::create(['user_id' => $user->id, 'catalog_vendor_id' => $vendor->id, 'status' => 'active', 'capabilities' => ['used_resale']]);
        $before = [DemoWalletAccount::count(), DemoWalletLedgerEntry::count(), UsedBookSellerProfile::count(), Vendor::count(), Warehouse::count(), WarehouseStock::count()];
        $this->actingAs($user)->getJson('/api/used-book-seller/wallet')->assertOk()->assertJsonPath('data.balance', 0)->assertJsonPath('data.currency', 'VND')->assertJsonPath('data.entries', []);
        $this->actingAs($user)->getJson('/api/used-book-seller/wallet')->assertOk();
        $this->assertSame($before, [DemoWalletAccount::count(), DemoWalletLedgerEntry::count(), UsedBookSellerProfile::count(), Vendor::count(), Warehouse::count(), WarehouseStock::count()]);
    }
}

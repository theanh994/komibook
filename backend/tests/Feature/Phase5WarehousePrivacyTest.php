<?php

namespace Tests\Feature;

use App\Models\SellerFulfillmentAddress;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5WarehousePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_only_list_own_warehouses(): void
    {
        [$firstUser, $firstVendor] = $this->vendor('first');
        [, $secondVendor] = $this->vendor('second');

        $own = Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $firstVendor->id,
            'name' => 'Kho của tôi',
            'address' => 'Địa chỉ riêng A',
            'status' => 'Hoạt động',
        ]);
        $foreign = Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $secondVendor->id,
            'name' => 'Kho bên khác',
            'address' => 'Địa chỉ riêng B',
            'status' => 'Hoạt động',
        ]);

        $response = $this->actingAs($firstUser)->getJson('/api/vendor/warehouses');

        $response->assertOk()
            ->assertJsonPath('warehouses.0.id', $own->id)
            ->assertJsonMissing(['id' => $foreign->id])
            ->assertJsonMissing(['address' => 'Địa chỉ riêng B']);
    }

    public function test_seller_address_is_private_and_vendor_warehouses_are_independent(): void
    {
        [$seller, $vendor] = $this->vendor('seller');

        $payload = [
            'recipient_name' => 'Nguyễn Văn A',
            'phone' => '0900000000',
            'address_line' => '12 Đường Riêng',
            'ward' => 'Phường 1',
            'district' => 'Quận 1',
            'province' => 'TP.HCM',
        ];

        $this->actingAs($seller)
            ->putJson('/api/used-book-seller/fulfillment-address', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonMissing(['address_line' => '12 Đường Riêng']);

        $baselineWarehouseCount = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->count();

        $this->actingAs($seller)
            ->postJson('/api/vendor/warehouses', [
                'name' => 'Kho Nhà bán 1',
                'address' => 'Địa chỉ kho độc lập 1',
            ])
            ->assertCreated();

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)
            ->getJson('/api/used-book-seller/fulfillment-address')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($seller)
            ->postJson('/api/vendor/warehouses', [
                'name' => 'Kho Nhà bán 2',
                'address' => 'Địa chỉ kho độc lập 2',
            ])
            ->assertCreated();

        $addresses = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->pluck('address')->all();
        $this->assertCount($baselineWarehouseCount + 2, $addresses);
        $this->assertContains('Địa chỉ kho độc lập 1', $addresses);
        $this->assertContains('Địa chỉ kho độc lập 2', $addresses);
    }

    public function test_sensitive_address_fields_are_hidden_from_default_model_serialization(): void
    {
        $user = User::factory()->create();
        $address = SellerFulfillmentAddress::create([
            'user_id' => $user->id,
            'recipient_name' => 'Người nhận',
            'phone' => '0900000000',
            'address_line' => 'Địa chỉ bí mật',
            'province' => 'Hà Nội',
        ]);

        $this->assertArrayNotHasKey('phone', $address->toArray());
        $this->assertArrayNotHasKey('address_line', $address->toArray());
    }

    private function vendor(string $slug): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => "Shop {$slug}",
            'slug' => "shop-{$slug}",
            'status' => 'active',
        ]);

        return [$user, $vendor];
    }
}

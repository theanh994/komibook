<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase8IdentityAndCommercialPartiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_registers_organization_and_admin_verifies_without_public_private_fields(): void
    {
        Storage::fake('private');
        [$vendorUser] = $this->vendor('phase8-direct');
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($vendorUser)->postJson('/api/vendor/organizations', [
            'legal_name' => 'Công ty TNHH NXB Phase 8',
            'display_name' => 'NXB Phase 8',
            'slug' => 'nxb-phase-8',
            'organization_types' => ['publisher', 'supplier'],
            'tax_code' => 'PRIVATE-TAX-CODE',
            'license_number' => 'PRIVATE-LICENSE',
            'description' => 'Hồ sơ công khai.',
            'verification_document' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $organizationId = $response->json('data.organization.id');
        $relationshipId = $response->json('data.relationship.id');
        $this->actingAs($admin)->patchJson("/api/admin/organizations/{$organizationId}/transition", [
            'to_status' => 'verified',
        ])->assertOk();
        $this->actingAs($admin)->patchJson("/api/admin/organization-relationships/{$relationshipId}/transition", [
            'to_status' => 'verified',
            'operation_key' => 'phase8-verify-self',
        ])->assertOk();

        $public = $this->getJson('/api/organizations/nxb-phase-8')
            ->assertOk()
            ->assertJsonPath('data.display_name', 'NXB Phase 8')
            ->assertJsonMissing(['tax_code' => 'PRIVATE-TAX-CODE'])
            ->assertJsonMissing(['license_number' => 'PRIVATE-LICENSE']);
        $this->assertStringNotContainsString('verification_document', $public->getContent());
    }

    public function test_vendor_cannot_submit_relationship_owned_by_another_vendor(): void
    {
        [$ownerUser, $owner] = $this->vendor('phase8-owner');
        [$attackerUser] = $this->vendor('phase8-attacker');
        $organization = Organization::create([
            'legal_name' => 'Organization A',
            'display_name' => 'Organization A',
            'slug' => 'organization-a',
            'organization_types' => ['publisher'],
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $relationship = VendorOrganizationRelationship::create([
            'vendor_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => 'publisher_partner',
            'status' => 'changes_requested',
            'operation_key' => 'phase8-owner-relation',
        ]);

        $this->actingAs($attackerUser)
            ->postJson("/api/vendor/organization-relationships/{$relationship->id}/submit")
            ->assertForbidden();
        $this->assertSame('changes_requested', $relationship->fresh()->status);
    }

    public function test_warehouse_manager_accepts_scoped_invitation_and_cannot_read_foreign_assignment(): void
    {
        [$vendorUser, $vendor] = $this->vendor('phase8-warehouse');
        [$otherVendorUser, $otherVendor] = $this->vendor('phase8-other');
        $warehouse = $this->warehouse($vendor, 'Kho được giao');
        $otherWarehouse = $this->warehouse($otherVendor, 'Kho không được giao');
        $manager = User::factory()->create(['role' => 'customer', 'email' => 'warehouse.manager@example.test']);
        $otherManager = User::factory()->create(['role' => 'customer']);

        $invite = $this->actingAs($vendorUser)->postJson('/api/vendor/warehouse-managers/invite', [
            'email' => $manager->email,
            'warehouse_id' => $warehouse->id,
            'capabilities' => ['view_inventory', 'receive_stock'],
        ])->assertCreated();
        $assignmentId = $invite->json('data.id');
        $token = $invite->json('invitation_token');
        $otherInvite = $this->actingAs($otherVendorUser)->postJson('/api/vendor/warehouse-managers/invite', [
            'email' => $otherManager->email,
            'warehouse_id' => $otherWarehouse->id,
            'capabilities' => ['view_inventory'],
        ])->assertCreated();

        $this->actingAs($vendorUser)->patchJson("/api/vendor/warehouse-managers/{$assignmentId}/transition", [
            'to_status' => 'active',
        ])->assertForbidden();
        $this->actingAs($manager)->postJson("/api/warehouse-manager/assignments/{$assignmentId}/accept", [
            'invitation_token' => $token,
            'operation_key' => 'phase8-manager-accept',
        ])->assertOk();
        $this->actingAs($manager)->getJson('/api/warehouse-manager/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.warehouse_id', $warehouse->id);
        $this->actingAs($manager)
            ->getJson('/api/warehouse-manager/assignments/'.$otherInvite->json('data.id').'/dashboard')
            ->assertForbidden();
    }

    private function vendor(string $slug): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => "Nhà bán {$slug}",
            'slug' => $slug,
            'status' => 'active',
            'onboarding_status' => 'approved',
            'business_model' => 'bookstore',
        ]);

        return [$user, $vendor];
    }

    private function warehouse(Vendor $vendor, string $name): Warehouse
    {
        return Warehouse::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'address' => 'Địa chỉ riêng tư chỉ dùng trong test',
            'status' => 'Hoạt động',
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use Illuminate\Validation\ValidationException;

class BookSupplyChainRequirementResolver
{
    public const ROLES = ['publisher', 'supplier', 'responsible_organization'];

    public function isSelfSupplied(Vendor $vendor): bool
    {
        return $vendor->business_model === 'direct_publisher';
    }

    public function scope(Vendor $vendor): array
    {
        $relationships = $vendor->organizationRelationships()
            ->with('organization')
            ->get()
            ->filter(fn (VendorOrganizationRelationship $relationship) => $relationship->isCurrentlyVerified()
                && $relationship->organization?->isOperationallyAccepted())
            ->values();

        $selfRelationship = $relationships->first(fn (VendorOrganizationRelationship $relationship) => $relationship->role === 'self_legal_entity'
            && (int) $relationship->organization_id === (int) $vendor->primary_organization_id
        );
        $selfSupplied = $this->isSelfSupplied($vendor);

        return [
            'business_model' => $vendor->business_model ?? 'bookstore',
            'mode' => $selfSupplied ? 'self_supplied' : 'partner_chain',
            'required_commercial_roles' => $selfSupplied ? [] : self::ROLES,
            'inferred_relationship_id' => $selfSupplied ? $selfRelationship?->id : null,
            'relationships' => $relationships->values(),
            'supply_chain_ready' => $selfSupplied ? $selfRelationship !== null : $relationships->isNotEmpty(),
        ];
    }

    public function resolve(Vendor $vendor, array $submitted): array
    {
        $scope = $this->scope($vendor);
        if ($scope['mode'] === 'self_supplied') {
            if (! $scope['inferred_relationship_id']) {
                throw ValidationException::withMessages([
                    'commercial_parties' => 'Hồ sơ tổ chức chính của Nhà bán chưa đủ điều kiện để tự suy ra chuỗi cung ứng.',
                ]);
            }

            return array_fill_keys(self::ROLES, (int) $scope['inferred_relationship_id']);
        }

        $resolved = [];
        foreach (self::ROLES as $role) {
            $key = "{$role}_relationship_id";
            if (empty($submitted[$key])) {
                throw ValidationException::withMessages([
                    $key => 'Vui lòng chọn đầy đủ thông tin xuất bản và cung ứng.',
                ]);
            }
            $resolved[$role] = (int) $submitted[$key];
        }

        return $resolved;
    }
}

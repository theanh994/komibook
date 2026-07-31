<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookCommercialParty;
use App\Models\OrganizationDistributionAgreement;
use App\Models\User;
use App\Models\VendorOrganizationRelationship;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialPartyService
{
    public const ROLES = ['publisher', 'supplier', 'responsible_organization'];

    public function assign(Book $book, array $relationshipIdsByRole, User $actor): Book
    {
        $vendor = $actor->vendor()->withoutGlobalScopes()->firstOrFail();
        abort_unless($book->vendor_id === $vendor->id, 403);
        if ($book->provenance === 'used_resale') {
            throw ValidationException::withMessages([
                'commercial_parties' => 'Sách cũ dùng chính sách nguồn gốc riêng, không gán chuỗi cung ứng như sách mới.',
            ]);
        }

        $relationships = VendorOrganizationRelationship::with('organization')
            ->where('vendor_id', $vendor->id)
            ->whereIn('id', array_values($relationshipIdsByRole))
            ->get()->keyBy('id');
        if ($relationships->count() !== count(array_unique(array_values($relationshipIdsByRole)))) {
            throw ValidationException::withMessages(['commercial_parties' => 'Quan hệ tổ chức không thuộc gian hàng.']);
        }

        foreach (self::ROLES as $role) {
            $relationship = $relationships->get((int) $relationshipIdsByRole[$role]);
            if (! $relationship?->isCurrentlyVerified() || ! $relationship->organization?->isVerified()) {
                throw ValidationException::withMessages([$role => 'Tổ chức và quan hệ phải được xác minh, còn hiệu lực.']);
            }
            $this->assertRoleCompatible($role, $relationship);
        }

        $publisherOrganizationId = (int) $relationships
            ->get((int) $relationshipIdsByRole['publisher'])->organization_id;
        $supplierOrganizationId = (int) $relationships
            ->get((int) $relationshipIdsByRole['supplier'])->organization_id;
        if ($publisherOrganizationId !== $supplierOrganizationId) {
            $hasAgreement = OrganizationDistributionAgreement::query()
                ->where('publisher_organization_id', $publisherOrganizationId)
                ->where('distributor_organization_id', $supplierOrganizationId)
                ->where('status', 'verified')
                ->get()
                ->contains(fn (OrganizationDistributionAgreement $agreement) => $agreement->isCurrentlyVerified()
                    && $agreement->coversBook($book->id));
            if (! $hasAgreement) {
                throw ValidationException::withMessages([
                    'supplier' => 'Nhà cung cấp/nhà phân phối chưa có thỏa thuận còn hiệu lực với Nhà xuất bản cho sách này.',
                ]);
            }
        }

        return DB::transaction(function () use ($book, $relationshipIdsByRole, $relationships, $actor) {
            $locked = Book::withoutGlobalScopes()->lockForUpdate()->findOrFail($book->id);
            foreach (self::ROLES as $role) {
                $latestVersion = (int) $locked->commercialParties()->where('role', $role)->max('version');
                $locked->commercialParties()->where('role', $role)->where('active_slot', 'active')->update([
                    'active_slot' => null,
                    'ended_at' => now(),
                ]);
                $relationship = $relationships->get((int) $relationshipIdsByRole[$role]);
                BookCommercialParty::create([
                    'book_id' => $locked->id,
                    'organization_id' => $relationship->organization_id,
                    'vendor_organization_relationship_id' => $relationship->id,
                    'role' => $role,
                    'status' => 'verified',
                    'version' => $latestVersion + 1,
                    'active_slot' => 'active',
                    'effective_at' => now(),
                    'verified_at' => now(),
                    'verified_by' => $relationship->reviewed_by ?? $actor->id,
                ]);
            }

            return $locked->fresh(['activeCommercialParties.organization', 'vendor']);
        });
    }

    public function snapshot(Book $book): ?array
    {
        $book->loadMissing(['vendor', 'activeCommercialParties.organization']);
        if ($book->provenance === 'used_resale') {
            return [
                'seller' => $this->sellerSnapshot($book),
                'policy' => 'used_book_exception',
                'captured_at' => now()->toIso8601String(),
            ];
        }
        $parties = $book->activeCommercialParties->keyBy('role');
        if (collect(self::ROLES)->contains(fn (string $role) => ! $parties->has($role))) {
            return null;
        }

        return [
            'seller' => $this->sellerSnapshot($book),
            'publisher' => $this->partySnapshot($parties['publisher']),
            'supplier' => $this->partySnapshot($parties['supplier']),
            'responsible_organization' => $this->partySnapshot($parties['responsible_organization']),
            'relationship_label' => $parties->pluck('organization_id')->unique()->count() === 1
                ? 'direct_publisher'
                : 'verified_partner_chain',
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function assertRoleCompatible(string $role, VendorOrganizationRelationship $relationship): void
    {
        $types = $relationship->organization->organization_types ?? [];
        $valid = match ($role) {
            'publisher' => in_array('publisher', $types, true)
                && in_array($relationship->role, ['self_legal_entity', 'publisher_partner'], true),
            'supplier' => count(array_intersect($types, ['supplier', 'publisher', 'distributor'])) > 0
                && in_array($relationship->role, ['self_legal_entity', 'supplier_partner', 'authorized_distributor'], true),
            'responsible_organization' => in_array($relationship->role, [
                'self_legal_entity',
                'publisher_partner',
                'supplier_partner',
                'authorized_distributor',
            ], true),
        };
        if (! $valid) {
            throw ValidationException::withMessages([$role => 'Vai trò tổ chức không phù hợp với quan hệ đã xác minh.']);
        }
    }

    private function sellerSnapshot(Book $book): array
    {
        return [
            'vendor_id' => $book->vendor_id,
            'shop_name' => $book->vendor?->shop_name,
            'shop_slug' => $book->vendor?->slug,
        ];
    }

    private function partySnapshot(BookCommercialParty $party): array
    {
        return [
            'organization_id' => $party->organization_id,
            'display_name' => $party->organization?->display_name,
            'slug' => $party->organization?->slug,
            'party_version' => $party->version,
            'relationship_id' => $party->vendor_organization_relationship_id,
            'verified_at' => $party->verified_at?->toIso8601String(),
        ];
    }
}

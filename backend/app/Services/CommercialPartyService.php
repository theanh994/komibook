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
            throw ValidationException::withMessages(['commercial_parties' => 'Used resale books cannot use the new-book commercial-party chain.']);
        }
        if (array_diff(self::ROLES, array_keys($relationshipIdsByRole))) {
            throw ValidationException::withMessages(['commercial_parties' => 'All canonical commercial-party roles are required.']);
        }

        $ids = array_map('intval', array_values(array_intersect_key($relationshipIdsByRole, array_flip(self::ROLES))));
        $relationships = VendorOrganizationRelationship::with('organization')->where('vendor_id', $vendor->id)->whereIn('id', $ids)->get()->keyBy('id');
        if ($relationships->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['commercial_parties' => 'Relationships must belong to the book vendor.']);
        }

        $modes = [];
        foreach (self::ROLES as $role) {
            $relationship = $relationships->get((int) $relationshipIdsByRole[$role]);
            if (! $relationship?->isCurrentlyVerified()) {
                throw ValidationException::withMessages([$role => 'Organization relationship is not canonically authoritative.']);
            }
            $this->assertRoleCompatible($role, $relationship);
            $modes[] = (bool) $relationship->is_demo;
        }
        if (count(array_unique($modes)) !== 1) {
            throw ValidationException::withMessages(['commercial_parties' => 'Live and demo commercial-party chains cannot be mixed.']);
        }
        $isDemo = $modes[0];

        $publisherOrganizationId = (int) $relationships->get((int) $relationshipIdsByRole['publisher'])->organization_id;
        $supplierOrganizationId = (int) $relationships->get((int) $relationshipIdsByRole['supplier'])->organization_id;
        if ($publisherOrganizationId !== $supplierOrganizationId) {
            $hasAgreement = OrganizationDistributionAgreement::with(['publisher', 'distributor'])
                ->where('publisher_organization_id', $publisherOrganizationId)
                ->where('distributor_organization_id', $supplierOrganizationId)
                ->where('is_demo', $isDemo)
                ->get()
                ->contains(fn (OrganizationDistributionAgreement $agreement) => $agreement->isCurrentlyVerified() && $agreement->coversBook($book->id));
            if (! $hasAgreement) {
                throw ValidationException::withMessages(['supplier' => 'The publisher and supplier require a canonical distribution agreement for this book.']);
            }
        }

        return DB::transaction(function () use ($book, $relationshipIdsByRole, $relationships, $isDemo) {
            $locked = Book::withoutGlobalScopes()->lockForUpdate()->findOrFail($book->id);
            foreach (self::ROLES as $role) {
                $latestVersion = (int) $locked->commercialParties()->where('role', $role)->max('version');
                $locked->commercialParties()->where('role', $role)->where('active_slot', 'active')->update(['active_slot' => null, 'ended_at' => now()]);
                $relationship = $relationships->get((int) $relationshipIdsByRole[$role]);
                BookCommercialParty::create([
                    'book_id' => $locked->id,
                    'organization_id' => $relationship->organization_id,
                    'vendor_organization_relationship_id' => $relationship->id,
                    'role' => $role,
                    'status' => $isDemo ? 'demo_accepted' : 'verified',
                    'version' => $latestVersion + 1,
                    'active_slot' => 'active',
                    'effective_at' => now(),
                    'verified_at' => $isDemo ? null : $relationship->verified_at,
                    'verified_by' => $isDemo ? null : $relationship->reviewed_by,
                ]);
            }

            return $locked->fresh(['activeCommercialParties.organization', 'vendor']);
        });
    }

    public function snapshot(Book $book): ?array
    {
        $book->loadMissing(['vendor', 'activeCommercialParties.organization']);
        if ($book->provenance === 'used_resale') {
            return ['seller' => $this->sellerSnapshot($book), 'policy' => 'used_book_exception', 'captured_at' => now()->toIso8601String()];
        }
        $parties = $book->activeCommercialParties->keyBy('role');
        if (collect(self::ROLES)->contains(fn (string $role) => ! $parties->has($role))) {
            return null;
        }
        $isDemo = $parties->contains(fn (BookCommercialParty $party) => $party->status === 'demo_accepted');

        return [
            'seller' => $this->sellerSnapshot($book),
            'publisher' => $this->partySnapshot($parties['publisher']),
            'supplier' => $this->partySnapshot($parties['supplier']),
            'responsible_organization' => $this->partySnapshot($parties['responsible_organization']),
            'relationship_label' => $isDemo ? 'demo_partner_chain' : ($parties->pluck('organization_id')->unique()->count() === 1 ? 'direct_publisher' : 'verified_partner_chain'),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function assertRoleCompatible(string $role, VendorOrganizationRelationship $relationship): void
    {
        $types = $relationship->organization->organization_types ?? [];
        $valid = match ($role) {
            'publisher' => in_array('publisher', $types, true) && in_array($relationship->role, ['self_legal_entity', 'publisher_partner', 'publisher', 'commercial_partner'], true),
            'supplier' => count(array_intersect($types, ['supplier', 'publisher', 'distributor'])) > 0 && in_array($relationship->role, ['self_legal_entity', 'supplier_partner', 'authorized_distributor', 'supplier', 'distributor', 'commercial_partner'], true),
            'responsible_organization' => in_array($relationship->role, ['self_legal_entity', 'publisher_partner', 'supplier_partner', 'authorized_distributor', 'publisher', 'supplier', 'distributor', 'commercial_partner'], true),
        };
        if (! $valid) {
            throw ValidationException::withMessages([$role => 'Organization role is incompatible with the selected relationship.']);
        }
    }

    private function sellerSnapshot(Book $book): array
    {
        return ['vendor_id' => $book->vendor_id, 'shop_name' => $book->vendor?->shop_name, 'shop_slug' => $book->vendor?->slug];
    }

    private function partySnapshot(BookCommercialParty $party): array
    {
        $isDemo = $party->status === 'demo_accepted';

        return [
            'organization_id' => $party->organization_id,
            'display_name' => $party->organization?->display_name,
            'slug' => $party->organization?->slug,
            'party_version' => $party->version,
            'relationship_id' => $party->vendor_organization_relationship_id,
            'acceptance_status' => $party->status,
            'is_demo' => $isDemo,
            'verified_at' => $isDemo ? null : $party->verified_at?->toIso8601String(),
        ];
    }
}

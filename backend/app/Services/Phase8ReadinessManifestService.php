<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Phase8ReadinessManifestService
{
    public function build(): array
    {
        $records = Schema::hasTable('books')
            ? DB::table('books')->orderBy('id')->get()->map(fn (object $book) => $this->bookRecord($book))->all()
            : [];

        $classifications = collect($records)->countBy('classification');

        return [
            'mode' => 'dry_run',
            'writes_performed' => false,
            'generated_at' => now()->toIso8601String(),
            'schema_readiness' => [
                'organizations' => Schema::hasTable('organizations'),
                'vendor_organization_relationships' => Schema::hasTable('vendor_organization_relationships'),
                'book_commercial_parties' => Schema::hasTable('book_commercial_parties'),
                'warehouse_manager_assignments' => Schema::hasTable('warehouse_manager_assignments'),
                'warehouse_documents' => Schema::hasTable('warehouse_documents'),
            ],
            'summary' => [
                'books' => count($records),
                'verified_direct_publisher' => (int) ($classifications['verified_direct_publisher'] ?? 0),
                'bookstore_requires_review' => (int) ($classifications['bookstore_requires_review'] ?? 0),
                'used_book_exception' => (int) ($classifications['used_book_exception'] ?? 0),
                'conflict_review' => (int) ($classifications['conflict_review'] ?? 0),
                'insufficient_evidence' => (int) ($classifications['insufficient_evidence'] ?? 0),
            ],
            'decision' => collect($records)->contains(fn (array $record) => in_array(
                $record['classification'],
                ['conflict_review', 'insufficient_evidence'],
                true,
            ))
                ? 'stop_automatic_backfill_and_review'
                : 'ready_for_reviewed_additive_migration',
            'records' => $records,
        ];
    }

    private function bookRecord(object $book): array
    {
        $vendor = Schema::hasTable('vendors')
            ? DB::table('vendors')->where('id', $book->vendor_id)->first()
            : null;
        $usedBookLinks = Schema::hasTable('used_book_listings')
            ? DB::table('used_book_listings')->where('book_id', $book->id)->count()
            : 0;
        $warehouseTenantMismatch = Schema::hasTable('warehouse_stocks') && Schema::hasTable('warehouses')
            ? DB::table('warehouse_stocks as stocks')
                ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
                ->where('stocks.book_id', $book->id)
                ->where('warehouses.vendor_id', '!=', $book->vendor_id)
                ->exists()
            : false;

        $conflicts = [];
        if (! $vendor) {
            $conflicts[] = 'missing_vendor';
        }
        if ($warehouseTenantMismatch) {
            $conflicts[] = 'warehouse_stock_tenant_mismatch';
        }

        $classification = match (true) {
            $conflicts !== [] => 'conflict_review',
            ($book->provenance ?? null) === 'used_resale' || $usedBookLinks > 0 => 'used_book_exception',
            $this->hasVerifiedCommercialParties((int) $book->id) => $this->verifiedClassification((int) $book->id),
            default => 'insufficient_evidence',
        };

        return [
            'book_id' => (int) $book->id,
            'vendor_id' => (int) $book->vendor_id,
            'provenance' => $book->provenance ?? null,
            'classification' => $classification,
            'legacy_used_book_links' => $usedBookLinks,
            'conflicts' => $conflicts,
            'recommended_action' => match ($classification) {
                'used_book_exception' => 'preserve_used_book_policy',
                'verified_direct_publisher' => 'preserve_explicit_roles',
                'bookstore_requires_review' => 'review_partner_relationships',
                'conflict_review' => 'resolve_tenant_or_owner_conflict',
                default => 'collect_publisher_supplier_responsible_evidence',
            },
        ];
    }

    private function hasVerifiedCommercialParties(int $bookId): bool
    {
        if (! Schema::hasTable('book_commercial_parties')) {
            return false;
        }

        return DB::table('book_commercial_parties')
            ->where('book_id', $bookId)
            ->where('status', 'verified')
            ->whereNull('ended_at')
            ->whereIn('role', ['publisher', 'supplier', 'responsible_organization'])
            ->distinct()
            ->count('role') === 3;
    }

    private function verifiedClassification(int $bookId): string
    {
        $organizationIds = DB::table('book_commercial_parties')
            ->where('book_id', $bookId)
            ->where('status', 'verified')
            ->whereNull('ended_at')
            ->whereIn('role', ['publisher', 'supplier', 'responsible_organization'])
            ->pluck('organization_id')
            ->unique();

        return $organizationIds->count() === 1
            ? 'verified_direct_publisher'
            : 'bookstore_requires_review';
    }
}

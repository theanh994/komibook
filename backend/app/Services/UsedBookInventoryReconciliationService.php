<?php

namespace App\Services;

use App\Models\UsedBookListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsedBookInventoryReconciliationService
{
    public function __construct(private readonly UsedBookInventoryService $inventory) {}

    /** @return array{counts: array<string, int>, rows: array<int, array<string, mixed>>} */
    public function reconcile(bool $apply = false): array
    {
        if (! Schema::hasTable('used_book_listings') || ! Schema::hasColumn('used_book_listings', 'warehouse_id')) {
            return ['counts' => ['schema_missing' => 1], 'rows' => [['reason_code' => 'warehouse_binding_schema_missing']]];
        }
        $rows = [];
        foreach (UsedBookListing::query()->orderBy('id')->get() as $listing) {
            $row = $apply ? DB::transaction(fn () => $this->applyOne($listing->id)) : $this->inspectOne($listing);
            $rows[] = $row;
        }
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['reason_code']] = ($counts[$row['reason_code']] ?? 0) + 1;
        }
        ksort($counts);

        return compact('counts', 'rows');
    }

    /** @return array<string, mixed> */
    private function inspectOne(UsedBookListing $listing): array
    {
        $check = $listing->warehouse_id === null
            ? $this->inventory->inspectBindableCandidate($listing)
            : $this->inventory->inspect($listing);

        return $this->row($listing, $check['reason_code'], false, $check['stock']?->id);
    }

    /** @return array<string, mixed> */
    private function applyOne(int $listingId): array
    {
        $listing = UsedBookListing::whereKey($listingId)->lockForUpdate()->firstOrFail();
        if ($listing->warehouse_id !== null) {
            $check = $this->inventory->inspect($listing, true);

            return $this->row($listing, $check['reason_code'], false, $check['stock']?->id);
        }
        $check = $this->inventory->inspectBindableCandidate($listing, true);
        if (! $check['valid']) {
            return $this->row($listing, $check['reason_code'], false, $check['stock']?->id);
        }
        $listing->warehouse_id = $check['warehouse']->id;
        $listing->save();

        return $this->row($listing, 'bound', true, $check['stock']->id);
    }

    /** @return array<string, mixed> */
    private function row(UsedBookListing $listing, string $reason, bool $applied, ?int $stockId): array
    {
        return ['listing_id' => $listing->id, 'book_id' => $listing->book_id, 'warehouse_id' => $listing->warehouse_id, 'warehouse_stock_id' => $stockId, 'reason_code' => $reason, 'applied' => $applied];
    }
}

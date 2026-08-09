<?php

namespace App\Services;

use App\Models\Book;
use App\Models\UsedBookListing;
use App\Models\UsedBookSellerProfile;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only canonical-chain checks shared by used-book inventory writers. */
class UsedBookInventoryService
{
    /**
     * Global used-book lock order: listing rows (ascending ID) are always
     * locked before any WarehouseStock row for the same book IDs.
     *
     * @return Collection<int, UsedBookListing>
     */
    public function lockListingsForBooks(array $bookIds): Collection
    {
        return UsedBookListing::whereIn('book_id', array_values(array_unique($bookIds)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @return array{valid: bool, reason_code: string, listing: UsedBookListing, book: ?Book, stock: ?WarehouseStock, warehouse: ?Warehouse} */
    public function inspect(UsedBookListing $listing, bool $lock = false): array
    {
        if (! Schema::hasColumn('used_book_listings', 'warehouse_id')) {
            return $this->result($listing, 'warehouse_binding_schema_missing');
        }

        $bookQuery = Book::withoutGlobalScopes()->whereKey($listing->book_id);
        if ($lock) {
            $bookQuery->lockForUpdate();
        }
        $book = $bookQuery->first();
        if (! $book) {
            return $this->result($listing, 'book_missing');
        }

        $profileQuery = UsedBookSellerProfile::where('user_id', $listing->seller_user_id);
        if ($lock) {
            $profileQuery->lockForUpdate();
        }
        $profile = $profileQuery->first();
        if (! $profile || (int) $profile->catalog_vendor_id !== (int) $book->vendor_id) {
            return $this->result($listing, 'seller_vendor_mismatch', $book);
        }
        if ($listing->warehouse_id === null) {
            $candidate = $this->inspectBindableCandidate($listing, $lock);

            return $candidate['valid']
                ? $this->result($listing, 'warehouse_binding_missing', $candidate['book'], $candidate['stock'], $candidate['warehouse'])
                : $candidate;
        }

        $warehouseQuery = Warehouse::withoutGlobalScopes()->whereKey($listing->warehouse_id);
        if ($lock) {
            $warehouseQuery->lockForUpdate();
        }
        $warehouse = $warehouseQuery->first();
        if (! $warehouse || (int) $warehouse->vendor_id !== (int) $book->vendor_id) {
            return $this->result($listing, 'warehouse_vendor_mismatch', $book, null, $warehouse);
        }

        $stocks = WarehouseStock::where('book_id', $book->id)->orderBy('id');
        if ($lock) {
            $stocks->lockForUpdate();
        }
        $stocks = $stocks->get();
        if ($stocks->count() !== 1) {
            return $this->result($listing, $stocks->isEmpty() ? 'warehouse_stock_missing' : 'warehouse_stock_ambiguous', $book, null, $warehouse);
        }
        $stock = $stocks->first();
        if ((int) $stock->warehouse_id !== (int) $listing->warehouse_id) {
            return $this->result($listing, 'warehouse_stock_binding_mismatch', $book, $stock, $warehouse);
        }
        if ((int) $listing->quantity_available !== (int) $stock->quantity || (int) $book->stock !== (int) $stock->quantity) {
            return $this->result($listing, 'inventory_projection_mismatch', $book, $stock, $warehouse);
        }
        if (($listing->status === 'active' && (int) $stock->quantity <= 0) || ($listing->status === 'sold_out' && (int) $stock->quantity !== 0)) {
            return $this->result($listing, 'inventory_status_mismatch', $book, $stock, $warehouse);
        }
        if ($this->hasWrongAllocation($book->id, $stock->id)) {
            return $this->result($listing, 'reservation_allocation_mismatch', $book, $stock, $warehouse);
        }
        if ((int) $stock->quantity < $this->activeReservedQuantityForStock((int) $stock->id)) {
            return $this->result($listing, 'reservation_capacity_mismatch', $book, $stock, $warehouse);
        }

        return ['valid' => true, 'reason_code' => 'ok', 'listing' => $listing, 'book' => $book, 'stock' => $stock, 'warehouse' => $warehouse];
    }

    /**
     * Proves an unbound listing can be bound without provisioning or repair.
     * This method is read-only even when $lock is true.
     *
     * @return array{valid: bool, reason_code: string, listing: UsedBookListing, book: ?Book, stock: ?WarehouseStock, warehouse: ?Warehouse}
     */
    public function inspectBindableCandidate(UsedBookListing $listing, bool $lock = false): array
    {
        if (! Schema::hasColumn('used_book_listings', 'warehouse_id')) {
            return $this->result($listing, 'warehouse_binding_schema_missing');
        }
        if ($listing->warehouse_id !== null) {
            return $this->result($listing, 'warehouse_binding_already_set');
        }

        $bookQuery = Book::withoutGlobalScopes()->whereKey($listing->book_id);
        if ($lock) {
            $bookQuery->lockForUpdate();
        }
        $book = $bookQuery->first();
        if (! $book) {
            return $this->result($listing, 'book_missing');
        }
        $profileQuery = UsedBookSellerProfile::where('user_id', $listing->seller_user_id);
        if ($lock) {
            $profileQuery->lockForUpdate();
        }
        $profile = $profileQuery->first();
        if (! $profile || (int) $profile->catalog_vendor_id !== (int) $book->vendor_id) {
            return $this->result($listing, 'seller_vendor_mismatch', $book);
        }
        $stocksQuery = WarehouseStock::where('book_id', $book->id)->orderBy('id');
        if ($lock) {
            $stocksQuery->lockForUpdate();
        }
        $stocks = $stocksQuery->get();
        if ($stocks->count() !== 1) {
            return $this->result($listing, $stocks->isEmpty() ? 'warehouse_stock_missing' : 'warehouse_stock_ambiguous', $book);
        }
        $stock = $stocks->first();
        $warehouseQuery = Warehouse::withoutGlobalScopes()->whereKey($stock->warehouse_id);
        if ($lock) {
            $warehouseQuery->lockForUpdate();
        }
        $warehouse = $warehouseQuery->first();
        if (! $warehouse || (int) $warehouse->vendor_id !== (int) $book->vendor_id) {
            return $this->result($listing, 'warehouse_vendor_mismatch', $book, $stock, $warehouse);
        }
        if ((int) $listing->quantity_available !== (int) $stock->quantity || (int) $book->stock !== (int) $stock->quantity) {
            return $this->result($listing, 'inventory_projection_mismatch', $book, $stock, $warehouse);
        }
        if (($listing->status === 'active' && (int) $stock->quantity <= 0) || ($listing->status === 'sold_out' && (int) $stock->quantity !== 0)) {
            return $this->result($listing, 'inventory_status_mismatch', $book, $stock, $warehouse);
        }
        if ($this->hasWrongAllocation($book->id, $stock->id)) {
            return $this->result($listing, 'reservation_allocation_mismatch', $book, $stock, $warehouse);
        }
        if ((int) $stock->quantity < $this->activeReservedQuantityForStock((int) $stock->id)) {
            return $this->result($listing, 'reservation_capacity_mismatch', $book, $stock, $warehouse);
        }

        return ['valid' => true, 'reason_code' => 'bindable', 'listing' => $listing, 'book' => $book, 'stock' => $stock, 'warehouse' => $warehouse];
    }

    private function hasWrongAllocation(int $bookId, int $stockId): bool
    {
        if (! Schema::hasTable('inventory_reservation_allocations')
            || ! Schema::hasTable('inventory_reservations')
            || ! Schema::hasTable('order_items')) {
            return true;
        }

        // Check every persisted reservation lifecycle. Released and expired rows
        // remain valid when their immutable allocation evidence is still canonical.
        $reservations = DB::table('inventory_reservations')
            ->where('book_id', $bookId)
            ->orderBy('id')
            ->get(['id', 'order_item_id', 'book_id', 'quantity']);

        foreach ($reservations as $reservation) {
            if ((int) $reservation->quantity <= 0) {
                return true;
            }
            $orderItemBookId = DB::table('order_items')->where('id', $reservation->order_item_id)->value('book_id');
            if ($orderItemBookId === null || (int) $orderItemBookId !== (int) $reservation->book_id) {
                return true;
            }

            $allocations = DB::table('inventory_reservation_allocations')
                ->where('inventory_reservation_id', $reservation->id)
                ->orderBy('id')
                ->get(['warehouse_stock_id', 'quantity']);
            if ($allocations->isEmpty()
                || $allocations->contains(fn ($allocation) => (int) $allocation->quantity <= 0)
                || $allocations->contains(fn ($allocation) => (int) $allocation->warehouse_stock_id !== $stockId)
                || $allocations->pluck('warehouse_stock_id')->unique()->count() !== $allocations->count()
                || (int) $allocations->sum('quantity') !== (int) $reservation->quantity) {
                return true;
            }
        }

        $stockAllocations = DB::table('inventory_reservation_allocations as allocation')
            ->leftJoin('inventory_reservations as reservation', 'reservation.id', '=', 'allocation.inventory_reservation_id')
            ->leftJoin('order_items as item', 'item.id', '=', 'reservation.order_item_id')
            ->where('allocation.warehouse_stock_id', $stockId)
            ->orderBy('allocation.id')
            ->get([
                'allocation.quantity as allocation_quantity',
                'reservation.id as reservation_id',
                'reservation.book_id as reservation_book_id',
                'reservation.quantity as reservation_quantity',
                'item.id as order_item_id',
                'item.book_id as order_item_book_id',
            ]);

        foreach ($stockAllocations as $allocation) {
            if ($allocation->reservation_id === null
                || $allocation->order_item_id === null
                || (int) $allocation->reservation_book_id !== $bookId
                || (int) $allocation->order_item_book_id !== (int) $allocation->reservation_book_id
                || (int) $allocation->reservation_quantity <= 0
                || (int) $allocation->allocation_quantity <= 0) {
                return true;
            }
        }

        return false;
    }

    /** Active, unexpired allocation capacity that already claims this exact stock row. */
    public function activeReservedQuantityForStock(int $stockId): int
    {
        if (! Schema::hasTable('inventory_reservation_allocations') || ! Schema::hasTable('inventory_reservations')) {
            return 0;
        }

        return (int) DB::table('inventory_reservation_allocations as allocation')
            ->join('inventory_reservations as reservation', 'reservation.id', '=', 'allocation.inventory_reservation_id')
            ->where('allocation.warehouse_stock_id', $stockId)
            ->where('reservation.status', 'reserved')
            ->where(function ($query) {
                $query->whereNull('reservation.expires_at')->orWhere('reservation.expires_at', '>', now());
            })
            ->sum('allocation.quantity');
    }

    /** @return array{valid: bool, reason_code: string, listing: UsedBookListing, book: ?Book, stock: ?WarehouseStock, warehouse: ?Warehouse} */
    private function result(UsedBookListing $listing, string $reason, ?Book $book = null, ?WarehouseStock $stock = null, ?Warehouse $warehouse = null): array
    {
        return ['valid' => false, 'reason_code' => $reason, 'listing' => $listing, 'book' => $book, 'stock' => $stock, 'warehouse' => $warehouse];
    }
}

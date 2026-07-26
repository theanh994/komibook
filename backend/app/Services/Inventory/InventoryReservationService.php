<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\InventoryReservationStatus;
use App\Models\Book;
use App\Models\CheckoutSession;
use App\Models\CheckoutSessionOrder;
use App\Models\InventoryReservation;
use App\Models\InventoryReservationAllocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WarehouseStock;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

class InventoryReservationService
{
    public function restoreCommittedOrder(Order $order, string $operationKey): void
    {
        DB::transaction(function () use ($order, $operationKey) {
            $items = $order->orderItems()->with('inventoryReservation')->orderBy('id')->lockForUpdate()->get();

            foreach ($items as $item) {
                $reservation = $item->inventoryReservation;
                if (! $reservation || $reservation->status === InventoryReservationStatus::RESTORED) {
                    continue;
                }

                if ($reservation->status === InventoryReservationStatus::RESERVED) {
                    $reservation->status = InventoryReservationStatus::RELEASED;
                    $reservation->released_at = now();
                    $reservation->save();

                    continue;
                }
                if ($reservation->status !== InventoryReservationStatus::COMMITTED) {
                    throw new LogicException("Cannot restore reservation ID {$reservation->id} in status '{$reservation->status->value}'");
                }

                $allocations = InventoryReservationAllocation::where('inventory_reservation_id', $reservation->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($allocations as $allocation) {
                    $restoreKey = "{$operationKey}:{$allocation->id}";
                    if (DB::table('inventory_cancellation_restorations')->where('operation_key', $restoreKey)->exists()) {
                        continue;
                    }

                    $stock = WarehouseStock::whereKey($allocation->warehouse_stock_id)->lockForUpdate()->firstOrFail();
                    DB::table('inventory_cancellation_restorations')->insert([
                        'order_item_id' => $item->id,
                        'inventory_reservation_allocation_id' => $allocation->id,
                        'warehouse_stock_id' => $stock->id,
                        'operation_key' => $restoreKey,
                        'quantity' => $allocation->quantity,
                        'restored_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stock->quantity += (int) $allocation->quantity;
                    $stock->save();
                }

                $reservation->status = InventoryReservationStatus::RESTORED;
                $reservation->save();
                $totalOnHand = (int) WarehouseStock::where('book_id', $item->book_id)->sum('quantity');
                Book::withoutGlobalScopes()->whereKey($item->book_id)->update(['stock' => $totalOnHand]);
            }
        });
    }

    /**
     * Giữ chỗ tồn kho cho một CheckoutSession.
     *
     * @return array<int, InventoryReservation>
     */
    public function reserve(CheckoutSession $session, DateTimeInterface|string|int $expiresAt, string $operationKey): array
    {
        $operationKey = $this->validateOperationKey($operationKey);

        $expiryDateTime = match (true) {
            $expiresAt instanceof DateTimeInterface => Carbon::instance($expiresAt),
            is_numeric($expiresAt) => Carbon::createFromTimestamp((int) $expiresAt),
            default => Carbon::parse((string) $expiresAt),
        };

        return DB::transaction(function () use ($session, $expiryDateTime, $operationKey) {
            // 1. Khóa CheckoutSession
            $lockedSession = CheckoutSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            // 2. Tải và khóa OrderItems theo ID asc
            $sessionOrders = CheckoutSessionOrder::where('checkout_session_id', $lockedSession->id)
                ->orderBy('order_id', 'asc')
                ->get();
            $orderIds = $sessionOrders->pluck('order_id')->sort()->values()->toArray();

            $orderItems = OrderItem::withoutGlobalScopes()
                ->whereIn('order_id', $orderIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Lọc các sản phẩm physical hợp lệ
            $physicalItems = [];
            $physicalBookIds = [];

            foreach ($orderItems as $item) {
                $book = Book::withoutGlobalScopes()->with('vendor')->find($item->book_id);

                if (! $book) {
                    throw new RuntimeException("Book not found for item ID {$item->id}");
                }

                if ($book->isEbook()) {
                    continue; // Ebook không tạo reservation
                }

                if (! $book->isPublished()) {
                    throw new RuntimeException("Book '{$book->title}' is not published");
                }

                if (! $book->vendor || $book->vendor->status !== 'active') {
                    throw new RuntimeException("Vendor for book '{$book->title}' is inactive");
                }

                $physicalItems[] = $item;
                $physicalBookIds[] = $book->id;
            }

            if (empty($physicalItems)) {
                return []; // Ebook không tạo reservation
            }

            $physicalBookIds = array_values(array_unique($physicalBookIds));
            sort($physicalBookIds);

            // 3. Khóa WarehouseStocks theo (book_id, warehouse_id, id)
            $warehouseStocks = WarehouseStock::whereIn('book_id', $physicalBookIds)
                ->orderBy('book_id', 'asc')
                ->orderBy('warehouse_id', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // 4. Kiểm tra Idempotency cho operationKey
            $targetKeys = array_map(
                fn ($item) => count($physicalItems) === 1 ? $operationKey : "{$operationKey}#item:{$item->id}",
                $physicalItems
            );

            $existingReservations = InventoryReservation::whereIn('operation_key', $targetKeys)
                ->orderBy('id', 'asc')
                ->get();

            if ($existingReservations->isNotEmpty()) {
                // Kiểm tra payload
                $mismatch = false;

                if ($existingReservations->count() !== count($physicalItems)) {
                    $mismatch = true;
                } else {
                    foreach ($physicalItems as $item) {
                        $res = $existingReservations->firstWhere('order_item_id', $item->id);

                        if (! $res) {
                            $mismatch = true;
                            break;
                        }

                        if ((int) $res->checkout_session_id !== (int) $lockedSession->id
                            || (int) $res->book_id !== (int) $item->book_id
                            || (int) $res->quantity !== (int) $item->quantity
                            || abs($res->expires_at->getTimestamp() - $expiryDateTime->getTimestamp()) > 1
                        ) {
                            $mismatch = true;
                            break;
                        }
                    }
                }

                if ($mismatch) {
                    throw new InvalidArgumentException("Operation key '{$operationKey}' exists with a conflicting payload");
                }

                return $existingReservations->all();
            }

            // 5. Phân bổ kho cho từng physical item (theo dõi planned allocations của cùng lời gọi)
            $itemAllocationsMap = [];
            $plannedAllocationsByStockId = [];
            $now = now();

            foreach ($physicalItems as $item) {
                $neededQty = $item->quantity;
                $itemStocks = $warehouseStocks->where('book_id', $item->book_id)
                    ->sortBy(fn ($stock) => [$stock->warehouse_id, $stock->id]);

                $allocations = [];

                foreach ($itemStocks as $ws) {
                    $activeAllocatedSum = (int) DB::table('inventory_reservation_allocations')
                        ->join('inventory_reservations', 'inventory_reservation_allocations.inventory_reservation_id', '=', 'inventory_reservations.id')
                        ->where('inventory_reservation_allocations.warehouse_stock_id', $ws->id)
                        ->where('inventory_reservations.status', InventoryReservationStatus::RESERVED->value)
                        ->where(function ($q) use ($now) {
                            $q->whereNull('inventory_reservations.expires_at')
                                ->orWhere('inventory_reservations.expires_at', '>', $now);
                        })
                        ->sum('inventory_reservation_allocations.quantity');

                    $alreadyPlannedInCall = $plannedAllocationsByStockId[$ws->id] ?? 0;

                    $available = max(0, (int) $ws->quantity - $activeAllocatedSum - $alreadyPlannedInCall);

                    if ($available <= 0) {
                        continue;
                    }

                    $toAllocate = min($neededQty, $available);
                    $allocations[$ws->id] = $toAllocate;
                    $plannedAllocationsByStockId[$ws->id] = $alreadyPlannedInCall + $toAllocate;
                    $neededQty -= $toAllocate;

                    if ($neededQty <= 0) {
                        break;
                    }
                }

                if ($neededQty > 0) {
                    throw new RuntimeException("Insufficient stock available for book ID {$item->book_id}");
                }

                $itemAllocationsMap[$item->id] = $allocations;
            }

            // 6. Lưu các bản ghi InventoryReservation và Allocation
            $createdReservations = [];

            foreach ($physicalItems as $item) {
                $itemOpKey = count($physicalItems) === 1
                    ? $operationKey
                    : "{$operationKey}#item:{$item->id}";

                $reservation = InventoryReservation::create([
                    'checkout_session_id' => $lockedSession->id,
                    'order_item_id' => $item->id,
                    'book_id' => $item->book_id,
                    'quantity' => $item->quantity,
                    'status' => InventoryReservationStatus::RESERVED,
                    'operation_key' => $itemOpKey,
                    'expires_at' => $expiryDateTime,
                ]);

                foreach ($itemAllocationsMap[$item->id] as $wsId => $allocQty) {
                    InventoryReservationAllocation::create([
                        'inventory_reservation_id' => $reservation->id,
                        'warehouse_stock_id' => $wsId,
                        'quantity' => $allocQty,
                    ]);
                }

                $createdReservations[] = $reservation->load('allocations');
            }

            return $createdReservations;
        });
    }

    /**
     * Commit giữ chỗ tồn kho (reserved -> committed).
     *
     * @return array<int, InventoryReservation>
     */
    public function commit(mixed $target): array
    {
        return DB::transaction(function () use ($target) {
            $reservations = $this->resolveReservations($target);

            if ($reservations->isEmpty()) {
                return [];
            }

            // 1. Lock CheckoutSession trước để giữ thứ tự lock tương thích với reserve
            $sessionIds = $reservations->pluck('checkout_session_id')->unique()->sort()->values()->toArray();
            CheckoutSession::whereIn('id', $sessionIds)->orderBy('id', 'asc')->lockForUpdate()->get();

            // 2. Lock WarehouseStocks theo (book_id, warehouse_id, id)
            $bookIds = $reservations->pluck('book_id')->unique()->sort()->values()->toArray();
            $lockedStocks = WarehouseStock::whereIn('book_id', $bookIds)
                ->orderBy('book_id', 'asc')
                ->orderBy('warehouse_id', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 3. Lock InventoryReservations theo ID asc
            $resIds = $reservations->pluck('id')->sort()->values()->toArray();
            $lockedReservations = InventoryReservation::whereIn('id', $resIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // 4. Lock Allocations theo ID asc
            $allocations = InventoryReservationAllocation::whereIn('inventory_reservation_id', $resIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Kiểm tra tính toàn vẹn của allocation trước khi trừ kho
            $allCommitted = true;

            foreach ($lockedReservations as $res) {
                if ($res->status === InventoryReservationStatus::COMMITTED) {
                    continue;
                }

                $allCommitted = false;

                if ($res->status !== InventoryReservationStatus::RESERVED) {
                    throw new LogicException("Cannot commit reservation ID {$res->id} in status '{$res->status->value}'");
                }

                $resAllocations = $allocations->where('inventory_reservation_id', $res->id);

                if ($resAllocations->isEmpty()) {
                    throw new RuntimeException("Reservation ID {$res->id} has no allocation records");
                }

                $sumAllocated = 0;
                foreach ($resAllocations as $alloc) {
                    if ((int) $alloc->quantity <= 0) {
                        throw new RuntimeException("Allocation ID {$alloc->id} has invalid quantity {$alloc->quantity}");
                    }

                    $sumAllocated += (int) $alloc->quantity;

                    $stock = $lockedStocks->get($alloc->warehouse_stock_id);
                    if (! $stock) {
                        throw new RuntimeException("WarehouseStock ID {$alloc->warehouse_stock_id} not found");
                    }

                    if ((int) $stock->book_id !== (int) $res->book_id) {
                        throw new RuntimeException("WarehouseStock ID {$stock->id} book_id does not match reservation book_id");
                    }
                }

                if ($sumAllocated !== (int) $res->quantity) {
                    throw new RuntimeException("Reservation ID {$res->id} quantity does not match sum of allocations");
                }
            }

            if ($allCommitted) {
                return $lockedReservations->all();
            }

            // Thực hiện trừ kho và chuyển trạng thái
            $affectedBookIds = [];

            foreach ($lockedReservations as $res) {
                if ($res->status === InventoryReservationStatus::COMMITTED) {
                    continue;
                }

                $resAllocations = $allocations->where('inventory_reservation_id', $res->id);

                foreach ($resAllocations as $alloc) {
                    $stock = $lockedStocks->get($alloc->warehouse_stock_id);

                    if (! $stock || (int) $stock->quantity < (int) $alloc->quantity) {
                        throw new RuntimeException("Insufficient on-hand stock in warehouse_stock ID {$alloc->warehouse_stock_id}");
                    }

                    $stock->quantity -= (int) $alloc->quantity;
                    $stock->save();

                    $affectedBookIds[$stock->book_id] = true;
                }

                $res->status = InventoryReservationStatus::COMMITTED;
                $res->committed_at = now();
                $res->save();
            }

            // Đồng bộ projection books.stock trong cùng transaction
            foreach (array_keys($affectedBookIds) as $bookId) {
                $totalOnHand = (int) WarehouseStock::where('book_id', $bookId)->sum('quantity');
                Book::withoutGlobalScopes()->where('id', $bookId)->update(['stock' => $totalOnHand]);
            }

            return $lockedReservations->all();
        });
    }

    /**
     * Release giữ chỗ tồn kho (reserved -> released).
     *
     * @return array<int, InventoryReservation>
     */
    public function release(mixed $target): array
    {
        return DB::transaction(function () use ($target) {
            $reservations = $this->resolveReservations($target);

            if ($reservations->isEmpty()) {
                return [];
            }

            $sessionIds = $reservations->pluck('checkout_session_id')->unique()->sort()->values()->toArray();
            CheckoutSession::whereIn('id', $sessionIds)->orderBy('id', 'asc')->lockForUpdate()->get();

            $resIds = $reservations->pluck('id')->sort()->values()->toArray();
            $lockedReservations = InventoryReservation::whereIn('id', $resIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($lockedReservations as $res) {
                if ($res->status === InventoryReservationStatus::RELEASED) {
                    continue; // Idempotent
                }

                if ($res->status !== InventoryReservationStatus::RESERVED) {
                    throw new LogicException("Cannot release reservation ID {$res->id} in status '{$res->status->value}'");
                }

                $res->status = InventoryReservationStatus::RELEASED;
                $res->released_at = now();
                $res->save();
            }

            return $lockedReservations->all();
        });
    }

    /**
     * Hết hạn giữ chỗ (reserved -> expired).
     *
     * @return array<int, InventoryReservation>
     */
    public function expire(mixed $target): array
    {
        return DB::transaction(function () use ($target) {
            $reservations = $this->resolveReservations($target);

            if ($reservations->isEmpty()) {
                return [];
            }

            $sessionIds = $reservations->pluck('checkout_session_id')->unique()->sort()->values()->toArray();
            CheckoutSession::whereIn('id', $sessionIds)->orderBy('id', 'asc')->lockForUpdate()->get();

            $resIds = $reservations->pluck('id')->sort()->values()->toArray();
            $lockedReservations = InventoryReservation::whereIn('id', $resIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($lockedReservations as $res) {
                if ($res->status === InventoryReservationStatus::EXPIRED) {
                    continue; // Idempotent
                }

                if ($res->status !== InventoryReservationStatus::RESERVED) {
                    throw new LogicException("Cannot expire reservation ID {$res->id} in status '{$res->status->value}'");
                }

                $res->status = InventoryReservationStatus::EXPIRED;
                $res->expired_at = now();
                $res->save();
            }

            return $lockedReservations->all();
        });
    }

    // ─── Explicit Semantic Methods ───────────────────────────────────────────

    public function commitSession(CheckoutSession $session): array
    {
        return $this->commit($session);
    }

    public function commitReservation(InventoryReservation $reservation): InventoryReservation
    {
        $res = $this->commit($reservation);

        return $res[0] ?? $reservation;
    }

    public function commitOperation(string $operationKey): array
    {
        return $this->commit($operationKey);
    }

    public function releaseSession(CheckoutSession $session): array
    {
        return $this->release($session);
    }

    public function releaseReservation(InventoryReservation $reservation): InventoryReservation
    {
        $res = $this->release($reservation);

        return $res[0] ?? $reservation;
    }

    public function releaseOperation(string $operationKey): array
    {
        return $this->release($operationKey);
    }

    public function expireSession(CheckoutSession $session): array
    {
        return $this->expire($session);
    }

    public function expireReservation(InventoryReservation $reservation): InventoryReservation
    {
        $res = $this->expire($reservation);

        return $res[0] ?? $reservation;
    }

    public function expireOperation(string $operationKey): array
    {
        return $this->expire($operationKey);
    }

    /**
     * Tính tổng tồn kho khả dụng (Available-to-sell) của một cuốn sách.
     */
    public function getAvailableToSell(int|Book $book): int
    {
        $bookId = is_object($book) ? $book->id : $book;
        $stocks = WarehouseStock::where('book_id', $bookId)->get();
        $totalAvailable = 0;
        $now = now();

        foreach ($stocks as $ws) {
            $activeAllocatedSum = (int) DB::table('inventory_reservation_allocations')
                ->join('inventory_reservations', 'inventory_reservation_allocations.inventory_reservation_id', '=', 'inventory_reservations.id')
                ->where('inventory_reservation_allocations.warehouse_stock_id', $ws->id)
                ->where('inventory_reservations.status', InventoryReservationStatus::RESERVED->value)
                ->where(function ($q) use ($now) {
                    $q->whereNull('inventory_reservations.expires_at')
                        ->orWhere('inventory_reservations.expires_at', '>', $now);
                })
                ->sum('inventory_reservation_allocations.quantity');

            $totalAvailable += max(0, (int) $ws->quantity - $activeAllocatedSum);
        }

        return $totalAvailable;
    }

    /**
     * Chuyển toàn bộ reservation đã hết hạn sang EXPIRED.
     */
    public function expireExpiredReservations(): int
    {
        $now = now();
        $expiredCount = 0;

        $expiredIds = InventoryReservation::where('status', InventoryReservationStatus::RESERVED->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->pluck('id')
            ->toArray();

        foreach ($expiredIds as $id) {
            $res = InventoryReservation::find($id);
            if ($res) {
                $this->expireReservation($res);
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * Validate operation key an toàn.
     */
    protected function validateOperationKey(string $key): string
    {
        $trimmed = trim($key);
        if ($trimmed === '' || strlen($trimmed) > 150) {
            throw new InvalidArgumentException('Operation key is invalid or exceeds maximum length of 150 characters');
        }

        if (! preg_match('/^[a-zA-Z0-9\:\.\/\-]+$/', $trimmed)) {
            throw new InvalidArgumentException('Operation key contains invalid characters (wildcards %, _ or internal delimiters are forbidden)');
        }

        return $trimmed;
    }

    /**
     * Helper resolve danh sách InventoryReservation từ target đầu vào.
     *
     * @return Collection<int, InventoryReservation>
     */
    protected function resolveReservations(mixed $target): Collection
    {
        if ($target instanceof InventoryReservation) {
            return collect([$target]);
        }

        if ($target instanceof CheckoutSession) {
            return InventoryReservation::where('checkout_session_id', $target->id)->get();
        }

        if (is_string($target)) {
            $key = $this->validateOperationKey($target);
            $escapedKey = str_replace(['%', '_'], ['\%', '\_'], $key);

            return InventoryReservation::where('operation_key', $key)
                ->orWhere('operation_key', 'LIKE', "{$escapedKey}#item:%")
                ->get()
                ->filter(function ($res) use ($key) {
                    if ($res->operation_key === $key) {
                        return true;
                    }
                    $parts = explode('#item:', $res->operation_key, 2);

                    return $parts[0] === $key && is_numeric($parts[1] ?? null);
                })
                ->values();
        }

        throw new InvalidArgumentException('Target must be a CheckoutSession, InventoryReservation, or valid operation key string');
    }
}

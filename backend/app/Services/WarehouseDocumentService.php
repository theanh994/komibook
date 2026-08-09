<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use App\Models\WarehouseDocument;
use App\Models\WarehouseDocumentEvent;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WarehouseDocumentService
{
    private const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['approved', 'cancelled'],
        'approved' => ['posted', 'cancelled'],
        'posted' => [],
        'cancelled' => [],
    ];

    public function transition(
        WarehouseDocument $document,
        string $target,
        User $actor,
        ?string $reason = null,
        ?string $operationKey = null,
    ): WarehouseDocument {
        $operationKey ??= "warehouse-document:{$document->id}:{$target}:".Str::uuid();

        return DB::transaction(function () use ($document, $target, $actor, $reason, $operationKey) {
            $existing = WarehouseDocumentEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                return $existing->document()->with($this->relations())->firstOrFail();
            }

            $locked = WarehouseDocument::query()->lockForUpdate()->findOrFail($document->id);
            $from = $locked->status;
            if (! in_array($target, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Không thể chuyển phiếu từ {$from} sang {$target}.",
                ]);
            }
            if ($target === 'cancelled' && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Phải nhập lý do hủy phiếu.']);
            }
            if ($target === 'posted') {
                $this->postInventory($locked, $actor, $operationKey);
            }

            $updates = ['status' => $target];
            if ($target === 'submitted') {
                $updates['submitted_at'] = now();
            } elseif ($target === 'approved') {
                $updates['approved_at'] = now();
                $updates['approved_by'] = $actor->id;
            } elseif ($target === 'posted') {
                $updates['posted_at'] = now();
                $updates['posted_by'] = $actor->id;
            } elseif ($target === 'cancelled') {
                $updates['cancelled_at'] = now();
            }
            $locked->update($updates);
            WarehouseDocumentEvent::create([
                'warehouse_document_id' => $locked->id,
                'actor_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $target,
                'reason' => $reason,
                'operation_key' => $operationKey,
            ]);

            return $locked->fresh($this->relations());
        });
    }

    private function postInventory(WarehouseDocument $document, User $actor, string $operationKey): void
    {
        $document->loadMissing('lines');
        if ($document->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Phiếu phải có ít nhất một dòng sản phẩm.']);
        }
        // Global D5 lock order: used listings (ID asc) precede any stock lock.
        $usedListings = app(UsedBookInventoryService::class)
            ->lockListingsForBooks($document->lines->pluck('book_id')->unique()->sort()->values()->all())
            ->keyBy('book_id');

        foreach ($document->lines as $line) {
            if ($document->type !== 'count' && (int) $line->quantity <= 0) {
                throw ValidationException::withMessages([
                    'lines' => 'Số lượng trên phiếu nhập, xuất hoặc điều chuyển phải lớn hơn 0 trước khi ghi sổ.',
                ]);
            }
            $book = Book::withoutGlobalScopes()->findOrFail($line->book_id);
            $usedListing = $usedListings->get($book->id);
            if ($usedListing && $document->origin !== 'order_fulfillment') {
                throw ValidationException::withMessages(['lines' => 'Used-book inventory can only be changed through its canonical path.']);
            }
            if ($usedListing && (int) $document->source_warehouse_id !== (int) $usedListing->warehouse_id) {
                throw ValidationException::withMessages(['warehouse' => 'Used-book dispatch must use its bound warehouse.']);
            }
            if ($usedListing) {
                $check = app(UsedBookInventoryService::class)->inspect($usedListing, true);
                $allocationExists = $document->origin === 'order_fulfillment'
                    && $document->type === 'dispatch'
                    && $document->order_id
                    && DB::table('inventory_reservation_allocations as a')
                        ->join('inventory_reservations as r', 'r.id', '=', 'a.inventory_reservation_id')
                        ->join('order_items as oi', 'oi.id', '=', 'r.order_item_id')
                        ->where('r.book_id', $book->id)
                        ->where('oi.order_id', $document->order_id)
                        ->where('a.warehouse_stock_id', $check['stock']?->id)
                        ->exists();
                if (! $check['valid'] || ! $allocationExists) {
                    throw ValidationException::withMessages(['lines' => 'Used-book dispatch evidence is not canonical.']);
                }
            }
            if ($book->vendor_id !== $document->vendor_id) {
                throw ValidationException::withMessages(['lines' => 'Sách không thuộc Nhà bán của phiếu kho.']);
            }

            if ($document->type === 'receipt') {
                $this->applyDelta($document, $line, $document->destination_warehouse_id, (int) $line->quantity, $actor, "{$operationKey}:receipt:{$line->id}");
            } elseif ($document->type === 'dispatch') {
                $this->applyDelta($document, $line, $document->source_warehouse_id, -((int) $line->quantity), $actor, "{$operationKey}:dispatch:{$line->id}");
            } elseif ($document->type === 'transfer') {
                $this->applyDelta($document, $line, $document->source_warehouse_id, -((int) $line->quantity), $actor, "{$operationKey}:transfer-out:{$line->id}");
                $this->applyDelta($document, $line, $document->destination_warehouse_id, (int) $line->quantity, $actor, "{$operationKey}:transfer-in:{$line->id}");
            } elseif ($document->type === 'count') {
                $stock = $this->lockedStock($document->source_warehouse_id, $line->book_id);
                $actual = (int) $line->actual_quantity;
                $delta = $actual - (int) $stock->quantity;
                $this->persistDelta($document, $line, $stock, $delta, $actor, "{$operationKey}:count:{$line->id}");
            }

            $total = $usedListing
                ? (int) WarehouseStock::query()->where('book_id', $line->book_id)->where('warehouse_id', $usedListing->warehouse_id)->value('quantity')
                : WarehouseStock::query()->where('book_id', $line->book_id)->sum('quantity');
            Book::withoutGlobalScopes()->whereKey($line->book_id)->update(['stock' => $total]);
        }
    }

    private function applyDelta($document, $line, ?int $warehouseId, int $delta, User $actor, string $operationKey): void
    {
        if (! $warehouseId) {
            throw ValidationException::withMessages(['warehouse' => 'Phiếu thiếu kho áp dụng.']);
        }
        $stock = $this->lockedStock($warehouseId, $line->book_id);
        $this->persistDelta($document, $line, $stock, $delta, $actor, $operationKey);
    }

    private function persistDelta($document, $line, WarehouseStock $stock, int $delta, User $actor, string $operationKey): void
    {
        $balance = (int) $stock->quantity + $delta;
        if ($balance < 0) {
            throw ValidationException::withMessages([
                'stock' => "Không đủ tồn kho cho sách ID {$line->book_id}.",
            ]);
        }
        $stock->update([
            'quantity' => $balance,
        ]);
        WarehouseStockLedger::create([
            'warehouse_document_id' => $document->id,
            'warehouse_document_line_id' => $line->id,
            'warehouse_id' => $stock->warehouse_id,
            'book_id' => $line->book_id,
            'quantity_delta' => $delta,
            'balance_after' => $balance,
            'actor_id' => $actor->id,
            'operation_key' => $operationKey,
            'metadata' => ['document_type' => $document->type],
        ]);
    }

    private function lockedStock(int $warehouseId, int $bookId): WarehouseStock
    {
        $stock = WarehouseStock::query()->where([
            'warehouse_id' => $warehouseId,
            'book_id' => $bookId,
        ])->lockForUpdate()->first();

        if (! $stock) {
            WarehouseStock::create([
                'warehouse_id' => $warehouseId,
                'book_id' => $bookId,
                'quantity' => 0,
            ]);
            $stock = WarehouseStock::query()->where([
                'warehouse_id' => $warehouseId,
                'book_id' => $bookId,
            ])->lockForUpdate()->firstOrFail();
        }

        return $stock;
    }

    private function relations(): array
    {
        return [
            'sourceWarehouse:id,vendor_id,name,status',
            'destinationWarehouse:id,vendor_id,name,status',
            'lines.book:id,title,cover_image,stock',
            'events',
        ];
    }
}

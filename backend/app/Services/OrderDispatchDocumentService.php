<?php

namespace App\Services;

use App\Enums\InventoryReservationStatus;
use App\Models\Book;
use App\Models\CheckoutSession;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WarehouseDocument;
use App\Models\WarehouseStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class OrderDispatchDocumentService
{
    public function __construct(private readonly WarehouseDocumentService $documents) {}

    /**
     * Ghi sổ phiếu xuất theo allocation rồi mới commit reservation.
     * Phương thức phải chạy trong transaction xử lý đơn hàng.
     *
     * @return array<int, InventoryReservation>
     */
    public function postSession(CheckoutSession $session): array
    {
        return DB::transaction(function () use ($session) {
            $reservations = InventoryReservation::query()
                ->where('checkout_session_id', $session->id)
                ->with([
                    'orderItem.order',
                    'book',
                    'allocations',
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($reservations->isEmpty()) {
                return [];
            }
            if ($reservations->every(fn (InventoryReservation $reservation) => $reservation->status === InventoryReservationStatus::COMMITTED)) {
                return $reservations->all();
            }
            if ($reservations->contains(fn (InventoryReservation $reservation) => $reservation->status !== InventoryReservationStatus::RESERVED)) {
                throw new LogicException('Không thể xuất kho vì một hoặc nhiều giữ chỗ không còn ở trạng thái reserved.');
            }

            $usedBookInventory = app(UsedBookInventoryService::class);
            $lockedUsedListings = $usedBookInventory->lockListingsForBooks(
                $reservations->pluck('book_id')->unique()->values()->all()
            );
            foreach ($lockedUsedListings as $listing) {
                $check = $usedBookInventory->inspect($listing, true);
                if (! $check['valid']) {
                    throw new RuntimeException('Used-book inventory is incoherent: '.$check['reason_code']);
                }
            }
            $usedListings = $lockedUsedListings->keyBy('book_id');
            $reservations->load('allocations.warehouseStock.warehouse');

            $groups = collect();
            foreach ($reservations as $reservation) {
                if ($reservation->allocations->isEmpty()) {
                    throw new RuntimeException("Reservation ID {$reservation->id} không có allocation kho.");
                }
                $allocated = 0;
                foreach ($reservation->allocations as $allocation) {
                    $stock = $allocation->warehouseStock;
                    $warehouse = $stock?->warehouse;
                    $order = $reservation->orderItem?->order;
                    if (! $stock || ! $warehouse || ! $order) {
                        throw new RuntimeException("Allocation ID {$allocation->id} thiếu dữ liệu kho hoặc đơn hàng.");
                    }
                    if ((int) $order->vendor_id !== (int) $warehouse->vendor_id || (int) $stock->book_id !== (int) $reservation->book_id) {
                        throw new RuntimeException("Allocation ID {$allocation->id} không khớp tenant/sản phẩm.");
                    }
                    $listing = $usedListings->get($reservation->book_id);
                    if ($listing && ((int) $listing->warehouse_id !== (int) $warehouse->id || (int) $stock->warehouse_id !== (int) $listing->warehouse_id)) {
                        throw new RuntimeException('Used-book allocation does not target its bound stock.');
                    }
                    $allocated += (int) $allocation->quantity;
                    $key = "{$order->id}:{$warehouse->id}";
                    $group = $groups->get($key, [
                        'order' => $order,
                        'warehouse' => $warehouse,
                        'lines' => collect(),
                    ]);
                    $group['lines']->put(
                        $reservation->book_id,
                        (int) $group['lines']->get($reservation->book_id, 0) + (int) $allocation->quantity,
                    );
                    $groups->put($key, $group);
                }
                if ($allocated !== (int) $reservation->quantity) {
                    throw new RuntimeException("Reservation ID {$reservation->id} có tổng allocation không hợp lệ.");
                }
            }

            foreach ($groups as $group) {
                $this->postGroup($group['order'], $group['warehouse'], $group['lines']);
            }

            foreach ($reservations as $reservation) {
                $reservation->update([
                    'status' => InventoryReservationStatus::COMMITTED,
                    'committed_at' => now(),
                ]);
            }

            $bookIds = $reservations->pluck('book_id')->unique();
            foreach ($bookIds as $bookId) {
                $listing = $usedListings->get($bookId);
                $totalOnHand = $listing
                    ? (int) WarehouseStock::where('book_id', $bookId)->where('warehouse_id', $listing->warehouse_id)->value('quantity')
                    : (int) WarehouseStock::where('book_id', $bookId)->sum('quantity');
                Book::withoutGlobalScopes()->where('id', $bookId)->update(['stock' => $totalOnHand]);

                if ($listing) {
                    $listing->update(['quantity_available' => $totalOnHand]);
                    if ($totalOnHand <= 0 && $listing->status === 'active') {
                        $listing->update(['status' => 'sold_out']);
                    }
                }
            }

            return $reservations->fresh()->all();
        });
    }

    private function postGroup(Order $order, $warehouse, Collection $lines): WarehouseDocument
    {
        $vendor = Vendor::withoutGlobalScopes()->findOrFail($order->vendor_id);
        $actor = User::findOrFail($vendor->user_id);
        $operationKey = "order-dispatch:{$order->id}:warehouse:{$warehouse->id}";
        $document = WarehouseDocument::firstOrCreate(
            ['operation_key' => $operationKey],
            [
                'vendor_id' => $vendor->id,
                'document_code' => 'DISPATCH-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'type' => 'dispatch',
                'origin' => 'order_fulfillment',
                'source_warehouse_id' => $warehouse->id,
                'order_id' => $order->id,
                'status' => 'approved',
                'reason' => 'Xuất hàng theo phân bổ tồn kho của đơn hàng',
                'snapshot' => [
                    'order' => ['id' => $order->id, 'order_code' => $order->order_code, 'shipping_address' => $order->shipping_address],
                    'warehouse' => ['id' => $warehouse->id, 'name' => $warehouse->name, 'address' => $warehouse->address],
                    'suggestion_reason' => $this->suggestionReason($warehouse, $order),
                    'captured_at' => now()->toIso8601String(),
                ],
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ],
        );
        if ($document->lines()->doesntExist()) {
            foreach ($lines as $bookId => $quantity) {
                $document->lines()->create(['book_id' => $bookId, 'quantity' => $quantity]);
            }
        }
        if ($document->status === 'posted') {
            return $document;
        }
        if ($document->status !== 'approved') {
            throw new LogicException("Phiếu xuất {$document->document_code} không ở trạng thái có thể ghi sổ.");
        }

        try {
            return $this->documents->transition(
                $document,
                'posted',
                $actor,
                null,
                "{$operationKey}:post",
            );
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('stock')) {
                throw new RuntimeException($exception->getMessage(), previous: $exception);
            }

            throw $exception;
        }
    }

    private function suggestionReason($warehouse, Order $order): string
    {
        $address = mb_strtolower((string) $order->shipping_address);
        if ($warehouse->district && str_contains($address, mb_strtolower($warehouse->district))) {
            return 'Kho có đủ allocation và cùng quận/huyện với địa chỉ nhận hàng.';
        }
        if ($warehouse->province && str_contains($address, mb_strtolower($warehouse->province))) {
            return 'Kho có đủ allocation và cùng tỉnh/thành với địa chỉ nhận hàng.';
        }

        return 'Kho đã được hệ thống phân bổ đủ tồn, ưu tiên hạn chế tách kiện.';
    }
}

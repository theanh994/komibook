<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseDocument;
use App\Models\WarehouseStock;
use Illuminate\Support\Str;

class BookInventoryOnboardingService
{
    public function createReceiptDraft(
        Book $book,
        Vendor $vendor,
        Warehouse $warehouse,
        User $actor,
        int $quantity,
        ?string $externalCounterpartyName,
        ?string $shelfLocation,
        string $operationKey,
    ): WarehouseDocument {
        WarehouseStock::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'book_id' => $book->id],
            ['quantity' => 0, 'shelf_location' => $shelfLocation],
        );

        $document = WarehouseDocument::firstOrCreate(
            ['operation_key' => $operationKey],
            [
                'vendor_id' => $vendor->id,
                'document_code' => 'RECEIPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'type' => 'receipt',
                'origin' => 'book_creation',
                'receipt_mode' => 'new_print_edition',
                'external_counterparty_name' => $externalCounterpartyName,
                'snapshot' => [
                    'vendor' => ['id' => $vendor->id, 'shop_name' => $vendor->shop_name],
                    'warehouse' => ['id' => $warehouse->id, 'name' => $warehouse->name, 'address' => $warehouse->address],
                    'external_counterparty_name' => $externalCounterpartyName,
                    'captured_at' => now()->toIso8601String(),
                ],
                'destination_warehouse_id' => $warehouse->id,
                'status' => 'draft',
                'reason' => 'Nhập ban đầu khi tạo sản phẩm/Bản in mới',
                'created_by' => $actor->id,
            ],
        );

        $document->lines()->firstOrCreate(
            ['book_id' => $book->id],
            [
                'quantity' => max(0, $quantity),
                'shelf_location' => $shelfLocation,
            ],
        );

        return $document->load('lines.book');
    }
}

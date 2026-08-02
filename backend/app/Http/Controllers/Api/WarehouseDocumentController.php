<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseDocument;
use App\Models\WarehouseManagerAssignment;
use App\Services\WarehouseAssignmentService;
use App\Services\WarehouseDocumentExportService;
use App\Services\WarehouseDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WarehouseDocumentController extends Controller
{
    public function scope(Request $request)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->first();
        if ($request->user()->role === 'admin') {
            return response()->json(['message' => 'Admin phải chọn Nhà bán trong màn hình giám sát.'], 422);
        }
        if ($vendor?->isActive()) {
            $warehouses = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)
                ->get(['id', 'vendor_id', 'name', 'status']);
            $capabilities = WarehouseAssignmentService::CAPABILITIES;
        } else {
            $assignments = $request->user()->warehouseManagerAssignments()
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->get();
            abort_if($assignments->isEmpty(), 403);
            abort_if($assignments->pluck('vendor_id')->unique()->count() > 1, 422, 'Hãy chọn phạm vi một Nhà bán trước khi tạo phiếu.');
            $vendor = Vendor::withoutGlobalScopes()->findOrFail($assignments->first()->vendor_id);
            $warehouses = Warehouse::withoutGlobalScopes()->whereIn('id', $assignments->pluck('warehouse_id'))
                ->get(['id', 'vendor_id', 'name', 'status']);
            $capabilities = $assignments->flatMap->capabilities->unique()->values();
        }
        $books = Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)
            ->where('type', 'physical')->orderBy('title')
            ->get(['id', 'title', 'cover_image', 'stock', 'isbn', 'print_edition'])
            ->map(fn (Book $book) => [
                'id' => $book->id,
                'title' => $book->title,
                'display_title' => $book->display_title,
                'cover_image' => $book->cover_image,
                'stock' => $book->stock,
                'isbn' => $book->isbn,
                'print_edition' => $book->print_edition,
            ]);

        return response()->json(['status' => 'success', 'data' => [
            'vendor' => ['id' => $vendor->id, 'shop_name' => $vendor->shop_name, 'primary_warehouse_id' => $vendor->primary_warehouse_id],
            'warehouses' => $warehouses,
            'books' => $books,
            'capabilities' => $capabilities,
            'can_transfer' => $warehouses->count() >= 2,
        ]]);
    }

    public function index(Request $request)
    {
        $query = WarehouseDocument::query()->with([
            'sourceWarehouse:id,vendor_id,name,status',
            'destinationWarehouse:id,vendor_id,name,status',
            'lines.book:id,title,cover_image,isbn,print_edition',
            'order:id,order_code,shipping_address,status,shipping_status',
        ])->latest();

        $vendor = $request->user()->vendor()->withoutGlobalScopes()->first();
        if ($request->user()->role === 'admin') {
            // Admin may inspect all documents.
        } elseif ($vendor?->isActive()) {
            $query->where('vendor_id', $vendor->id);
        } else {
            $warehouseIds = $request->user()->warehouseManagerAssignments()
                ->where('status', 'active')->pluck('warehouse_id');
            $query->where(function (Builder $scoped) use ($warehouseIds) {
                $scoped->whereIn('source_warehouse_id', $warehouseIds)
                    ->orWhereIn('destination_warehouse_id', $warehouseIds);
            });
        }

        foreach (['type', 'status', 'source_warehouse_id', 'destination_warehouse_id', 'order_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery->where('document_code', 'like', "%{$search}%")
                    ->orWhereHas('order', fn (Builder $orderQuery) => $orderQuery->where('order_code', 'like', "%{$search}%"));
            });
        }

        $documents = $query->paginate(30);
        $documents->getCollection()->each(function (WarehouseDocument $document) {
            $document->lines->each(function ($line) {
                if ($line->book) {
                    $line->book->setAttribute('display_title', $line->book->display_title);
                }
            });
        });

        return response()->json(['status' => 'success', 'data' => $documents]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['receipt', 'dispatch', 'transfer', 'count'])],
            'origin' => ['nullable', Rule::in(['manual', 'book_creation', 'order_fulfillment', 'inventory_adjustment'])],
            'receipt_mode' => ['nullable', Rule::in(['new_print_edition', 'restock_existing'])],
            'external_counterparty_name' => ['nullable', 'string', 'max:255'],
            'source_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'operation_key' => ['required', 'string', 'max:128'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.book_id' => ['required', 'integer', 'distinct', 'exists:books,id'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1'],
            'lines.*.actual_quantity' => ['nullable', 'integer', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($existing = WarehouseDocument::where('operation_key', $validated['operation_key'])->first()) {
            $this->authorizeExisting($request, $existing, 'view');

            return response()->json(['status' => 'success', 'data' => $existing->load('lines')]);
        }

        [$vendor, $source, $destination] = $this->resolveScope($request, $validated);
        abort_if(
            $validated['type'] === 'dispatch' && ! empty($validated['order_id']),
            422,
            'Phiếu xuất theo đơn hàng được hệ thống tạo từ phân bổ tồn kho để tránh trừ hàng hai lần.',
        );
        if ($validated['type'] === 'transfer') {
            abort_if(
                Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->whereIn('status', ['active', 'Hoạt động'])->count() < 2,
                422,
                'Nhà bán chỉ có một kho hoạt động nên không thể tạo phiếu điều chuyển.',
            );
        }
        $this->authorizeDocumentAction($request, $vendor, $source, $destination, $validated['type'], 'create');
        $bookIds = collect($validated['lines'])->pluck('book_id');
        abort_unless(
            Book::withoutGlobalScopes()->where('vendor_id', $vendor->id)->whereIn('id', $bookIds)->count() === $bookIds->count(),
            422,
            'Phiếu chứa sách không thuộc Nhà bán.',
        );

        $document = DB::transaction(function () use ($request, $validated, $vendor, $source, $destination) {
            $document = WarehouseDocument::create([
                'vendor_id' => $vendor->id,
                'document_code' => strtoupper($validated['type']).'-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'type' => $validated['type'],
                'origin' => $validated['origin'] ?? 'manual',
                'receipt_mode' => $validated['type'] === 'receipt'
                    ? ($validated['receipt_mode'] ?? 'restock_existing')
                    : null,
                'external_counterparty_name' => $validated['external_counterparty_name'] ?? null,
                'snapshot' => [
                    'vendor' => ['id' => $vendor->id, 'shop_name' => $vendor->shop_name],
                    'source_warehouse' => $source ? ['id' => $source->id, 'name' => $source->name, 'address' => $source->address] : null,
                    'destination_warehouse' => $destination ? ['id' => $destination->id, 'name' => $destination->name, 'address' => $destination->address] : null,
                    'external_counterparty_name' => $validated['external_counterparty_name'] ?? null,
                    'captured_at' => now()->toIso8601String(),
                ],
                'source_warehouse_id' => $source?->id,
                'destination_warehouse_id' => $destination?->id,
                'order_id' => $validated['order_id'] ?? null,
                'status' => 'draft',
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
                'operation_key' => $validated['operation_key'],
            ]);
            foreach ($validated['lines'] as $line) {
                $document->lines()->create([
                    'book_id' => $line['book_id'],
                    'quantity' => $line['quantity'] ?? 0,
                    'actual_quantity' => $line['actual_quantity'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $document;
        });

        return response()->json(['status' => 'success', 'data' => $document->load('lines.book')], 201);
    }

    public function update(Request $request, WarehouseDocument $document)
    {
        $this->authorizeExisting($request, $document, 'create');
        abort_unless($document->status === 'draft', 422, 'Chỉ phiếu nháp mới được chỉnh sửa.');
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'external_counterparty_name' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.book_id' => ['required', 'integer', 'distinct', 'exists:books,id'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0'],
            'lines.*.actual_quantity' => ['nullable', 'integer', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $bookIds = collect($validated['lines'])->pluck('book_id');
        abort_unless(
            Book::withoutGlobalScopes()->where('vendor_id', $document->vendor_id)->whereIn('id', $bookIds)->count() === $bookIds->count(),
            422,
            'Phiếu chứa sách không thuộc Nhà bán.',
        );

        DB::transaction(function () use ($document, $validated): void {
            $document->update([
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'external_counterparty_name' => $validated['external_counterparty_name'] ?? null,
            ]);
            $document->lines()->delete();
            foreach ($validated['lines'] as $line) {
                $document->lines()->create([
                    'book_id' => $line['book_id'],
                    'quantity' => $line['quantity'] ?? 0,
                    'actual_quantity' => $line['actual_quantity'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);
            }
        });

        return response()->json(['status' => 'success', 'data' => $document->fresh()->load('lines.book')]);
    }

    public function show(Request $request, WarehouseDocument $document)
    {
        $this->authorizeExisting($request, $document, 'view');

        return response()->json(['status' => 'success', 'data' => $document->load([
            'sourceWarehouse:id,vendor_id,name,status',
            'destinationWarehouse:id,vendor_id,name,status',
            'lines.book:id,title,cover_image,stock,isbn,print_edition',
            'order:id,order_code,shipping_address,status,shipping_status',
            'events',
            'ledgers',
        ])]);
    }

    public function transition(
        Request $request,
        WarehouseDocument $document,
        WarehouseDocumentService $service,
    ) {
        $validated = $request->validate([
            'to_status' => ['required', Rule::in(['submitted', 'approved', 'posted', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'operation_key' => ['required', 'string', 'max:128'],
        ]);
        $this->authorizeExisting($request, $document, $validated['to_status']);
        if ($validated['to_status'] === 'approved') {
            $vendor = $request->user()->vendor()->withoutGlobalScopes()->first();
            abort_unless($request->user()->role === 'admin' || $vendor?->id === $document->vendor_id, 403);
        }

        return response()->json(['status' => 'success', 'data' => $service->transition(
            $document,
            $validated['to_status'],
            $request->user(),
            $validated['reason'] ?? null,
            $validated['operation_key'],
        )]);
    }

    public function printable(Request $request, WarehouseDocument $document, WarehouseDocumentExportService $export)
    {
        $this->authorizeExisting($request, $document, 'view');

        return response()->view('warehouse-documents.print', $export->data($document));
    }

    public function pdf(Request $request, WarehouseDocument $document, WarehouseDocumentExportService $export)
    {
        $this->authorizeExisting($request, $document, 'view');

        return $export->pdf($document);
    }

    public function excel(Request $request, WarehouseDocument $document, WarehouseDocumentExportService $export)
    {
        $this->authorizeExisting($request, $document, 'view');

        return $export->excel($document);
    }

    private function resolveScope(Request $request, array $validated): array
    {
        $source = isset($validated['source_warehouse_id'])
            ? Warehouse::withoutGlobalScopes()->findOrFail($validated['source_warehouse_id'])
            : null;
        $destination = isset($validated['destination_warehouse_id'])
            ? Warehouse::withoutGlobalScopes()->findOrFail($validated['destination_warehouse_id'])
            : null;
        $vendorId = $source?->vendor_id ?? $destination?->vendor_id;
        abort_unless($vendorId && (! $source || ! $destination || $source->vendor_id === $destination->vendor_id), 422);
        $vendor = Vendor::withoutGlobalScopes()->findOrFail($vendorId);

        $type = $validated['type'];
        abort_if(in_array($type, ['dispatch', 'count', 'transfer'], true) && ! $source, 422, 'Loại phiếu này cần kho nguồn.');
        abort_if(in_array($type, ['receipt', 'transfer'], true) && ! $destination, 422, 'Loại phiếu này cần kho đích.');
        abort_if(
            $type === 'transfer'
            && Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)->whereIn('status', ['active', 'Hoạt động'])->count() < 2,
            422,
            'Nhà bán chỉ có một kho hoạt động nên không thể tạo phiếu điều chuyển.',
        );
        abort_if($type === 'transfer' && $source?->id === $destination?->id, 422, 'Kho nguồn và kho đích phải khác nhau.');

        return [$vendor, $source, $destination];
    }

    private function authorizeExisting(Request $request, WarehouseDocument $document, string $action): void
    {
        $source = $document->sourceWarehouse()->withoutGlobalScopes()->first();
        $destination = $document->destinationWarehouse()->withoutGlobalScopes()->first();
        $vendor = Vendor::withoutGlobalScopes()->findOrFail($document->vendor_id);
        $this->authorizeDocumentAction($request, $vendor, $source, $destination, $document->type, $action);
    }

    private function authorizeDocumentAction(Request $request, Vendor $vendor, $source, $destination, string $type, string $action): void
    {
        if ($request->user()->role === 'admin' || ($request->user()->role === 'vendor' && $vendor->user_id === $request->user()->id)) {
            return;
        }
        $required = match ($type) {
            'receipt' => 'receive_stock',
            'dispatch' => 'dispatch_stock',
            'transfer' => 'transfer_stock',
            'count' => 'count_inventory',
        };
        if ($action === 'view') {
            $required = 'view_inventory';
        }
        $warehouseIds = collect([$source?->id, $destination?->id])->filter()->unique();
        $assignments = WarehouseManagerAssignment::where('user_id', $request->user()->id)
            ->where('vendor_id', $vendor->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('status', 'active')
            ->get();
        abort_unless(
            $assignments->count() === $warehouseIds->count()
            && $assignments->every(fn ($assignment) => $assignment->can($required)),
            403,
            'Bạn không có quyền thực hiện nghiệp vụ này tại tất cả kho liên quan.',
        );
    }
}

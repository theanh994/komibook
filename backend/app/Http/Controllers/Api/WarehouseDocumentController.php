<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseDocument;
use App\Models\WarehouseManagerAssignment;
use App\Services\WarehouseAssignmentService;
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
            ->get(['id', 'title', 'cover_image', 'stock']);

        return response()->json(['status' => 'success', 'data' => [
            'vendor' => ['id' => $vendor->id, 'shop_name' => $vendor->shop_name],
            'warehouses' => $warehouses,
            'books' => $books,
            'capabilities' => $capabilities,
        ]]);
    }

    public function index(Request $request)
    {
        $query = WarehouseDocument::query()->with([
            'sourceWarehouse:id,vendor_id,name,status',
            'destinationWarehouse:id,vendor_id,name,status',
            'lines.book:id,title,cover_image',
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

        return response()->json(['status' => 'success', 'data' => $query->paginate(30)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['receipt', 'dispatch', 'transfer', 'count'])],
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
            'lines.*.shelf_location' => ['nullable', 'string', 'max:255'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($existing = WarehouseDocument::where('operation_key', $validated['operation_key'])->first()) {
            return response()->json(['status' => 'success', 'data' => $existing->load('lines')]);
        }

        [$vendor, $source, $destination] = $this->resolveScope($request, $validated);
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
                    'shelf_location' => $line['shelf_location'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $document;
        });

        return response()->json(['status' => 'success', 'data' => $document->load('lines.book')], 201);
    }

    public function show(Request $request, WarehouseDocument $document)
    {
        $this->authorizeExisting($request, $document, 'view');

        return response()->json(['status' => 'success', 'data' => $document->load([
            'sourceWarehouse:id,vendor_id,name,status',
            'destinationWarehouse:id,vendor_id,name,status',
            'lines.book:id,title,cover_image,stock',
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

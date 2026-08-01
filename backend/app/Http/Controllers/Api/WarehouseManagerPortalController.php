<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Models\WarehouseManagerAssignment;
use App\Services\WarehouseAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WarehouseManagerPortalController extends Controller
{
    public function assignments(Request $request)
    {
        $assignments = $request->user()->warehouseManagerAssignments()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['vendor:id,shop_name,slug', 'warehouse:id,vendor_id,name,status,capacity'])
            ->get();

        return response()->json(['status' => 'success', 'data' => $assignments]);
    }

    public function accept(
        Request $request,
        WarehouseManagerAssignment $assignment,
        WarehouseAssignmentService $service,
    ) {
        $validated = $request->validate([
            'invitation_token' => ['required', 'string', 'min:32'],
            'operation_key' => ['nullable', 'string', 'max:128'],
        ]);
        abort_unless($assignment->user_id === $request->user()->id, 403);
        abort_unless($assignment->status === 'invited' && $assignment->invitation_token_hash, 422);
        abort_unless(hash_equals($assignment->invitation_token_hash, hash('sha256', $validated['invitation_token'])), 403);

        return response()->json(['status' => 'success', 'data' => $service->transition(
            $assignment,
            'active',
            $request->user(),
            operationKey: $validated['operation_key'] ?? 'warehouse-assignment-accept:'.Str::uuid(),
        )]);
    }

    public function respond(
        Request $request,
        WarehouseManagerAssignment $assignment,
        WarehouseAssignmentService $service,
    ) {
        $validated = $request->validate([
            'decision' => ['required', 'in:accept,decline'],
            'operation_key' => ['nullable', 'string', 'max:128'],
        ]);
        abort_unless($assignment->user_id === $request->user()->id, 403);
        abort_unless($assignment->status === 'invited', 422, 'Lời mời này đã được phản hồi hoặc không còn hiệu lực.');
        abort_if($assignment->expires_at?->isPast(), 422, 'Lời mời này đã hết hạn.');

        $target = $validated['decision'] === 'accept' ? 'active' : 'declined';
        $updated = $service->transition(
            $assignment,
            $target,
            $request->user(),
            operationKey: $validated['operation_key'] ?? 'warehouse-assignment-response:'.Str::uuid(),
        );

        UserNotification::where('user_id', $request->user()->id)
            ->where('data->assignment_id', $assignment->id)
            ->get()
            ->each(function (UserNotification $notification) use ($target) {
                $notification->update([
                    'read_at' => now(),
                    'data' => [...($notification->data ?? []), 'invitation_status' => $target],
                ]);
            });

        return response()->json([
            'status' => 'success',
            'message' => $target === 'active' ? 'Bạn đã chấp nhận quản lý kho.' : 'Bạn đã từ chối lời mời quản lý kho.',
            'data' => $updated,
        ]);
    }

    public function dashboard(Request $request, WarehouseManagerAssignment $assignment)
    {
        abort_unless($assignment->user_id === $request->user()->id && $assignment->isActive(), 403);
        $assignment->load([
            'vendor:id,shop_name,slug',
            'warehouse.stocks.book:id,title,cover_image,stock',
        ]);

        return response()->json(['status' => 'success', 'data' => [
            'assignment' => $assignment,
            'metrics' => [
                'sku_count' => $assignment->warehouse->stocks->count(),
                'total_units' => $assignment->warehouse->stocks->sum('quantity'),
                'low_stock_count' => $assignment->warehouse->stocks->where('quantity', '<=', 5)->count(),
            ],
        ]]);
    }
}

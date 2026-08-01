<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Warehouse;
use App\Models\WarehouseManagerAssignment;
use App\Services\WarehouseAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WarehouseManagerController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $vendor->warehouseManagerAssignments()
            ->with(['user:id,name,email', 'warehouse:id,vendor_id,name,status'])
            ->latest()->get()]);
    }

    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => [Rule::in(WarehouseAssignmentService::CAPABILITIES)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        $warehouse = Warehouse::withoutGlobalScopes()->where('vendor_id', $vendor->id)
            ->findOrFail($validated['warehouse_id']);
        $manager = User::where('email', $validated['email'])->firstOrFail();
        abort_if($manager->role === 'admin' || $manager->id === $request->user()->id, 422, 'Tài khoản này không thể được mời làm Quản lý kho.');
        $plainToken = Str::random(48);
        $assignment = DB::transaction(function () use ($manager, $warehouse, $vendor, $request, $validated, $plainToken) {
            $assignment = WarehouseManagerAssignment::updateOrCreate(
                ['user_id' => $manager->id, 'warehouse_id' => $warehouse->id],
                [
                    'vendor_id' => $vendor->id,
                    'invited_by' => $request->user()->id,
                    'capabilities' => array_values(array_unique($validated['capabilities'])),
                    'status' => 'invited',
                    'invitation_token_hash' => hash('sha256', $plainToken),
                    'invited_at' => now(),
                    'accepted_at' => null,
                    'suspended_at' => null,
                    'revoked_at' => null,
                    'expires_at' => $validated['expires_at'] ?? null,
                    'last_reason' => null,
                ],
            );

            $this->notifyManager($assignment, $vendor->shop_name, $warehouse->name);

            return $assignment;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi lời mời đến thông báo của nhân sự được chỉ định.',
            'data' => $assignment->load(['user:id,name,email', 'warehouse:id,vendor_id,name,status']),
            'invitation_token' => app()->environment('local', 'testing') ? $plainToken : null,
        ], 201);
    }

    public function resendInvitation(Request $request, WarehouseManagerAssignment $assignment)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        abort_unless($assignment->vendor_id === $vendor->id, 403);
        abort_unless($assignment->status === 'invited', 422, 'Chỉ có thể gửi lại lời mời đang chờ phản hồi.');
        abort_if($assignment->expires_at?->isPast(), 422, 'Lời mời đã hết hạn. Hãy tạo lại phân công.');
        $assignment->loadMissing(['warehouse:id,name', 'user:id,name,email']);

        $notification = DB::transaction(fn () => $this->notifyManager(
            $assignment,
            $vendor->shop_name,
            $assignment->warehouse->name,
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi lại lời mời đến trang Thông báo của nhân sự.',
            'data' => $notification,
        ]);
    }

    public function transition(
        Request $request,
        WarehouseManagerAssignment $assignment,
        WarehouseAssignmentService $service,
    ) {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        abort_unless($assignment->vendor_id === $vendor->id, 403);
        $validated = $request->validate([
            'to_status' => ['required', Rule::in(['active', 'suspended', 'revoked'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'operation_key' => ['nullable', 'string', 'max:128'],
        ]);

        return response()->json(['status' => 'success', 'data' => $service->transition(
            $assignment,
            $validated['to_status'],
            $request->user(),
            $validated['reason'] ?? null,
            $validated['operation_key'] ?? null,
        )]);
    }

    private function notifyManager(
        WarehouseManagerAssignment $assignment,
        string $vendorName,
        string $warehouseName,
    ): UserNotification {
        $data = [
            'action_type' => 'warehouse_assignment_invitation',
            'assignment_id' => $assignment->id,
            'invitation_status' => 'invited',
            'vendor_name' => $vendorName,
            'warehouse_name' => $warehouseName,
            'capabilities' => $assignment->capabilities,
            'icon' => 'inventory_2',
            'colorClass' => 'bg-primary/10 text-primary',
        ];
        $notification = UserNotification::where('user_id', $assignment->user_id)
            ->where('data->assignment_id', $assignment->id)
            ->latest()
            ->first();
        if ($notification) {
            $notification->update([
                'title' => 'Lời mời quản lý kho',
                'content' => "{$vendorName} mời bạn quản lý kho {$warehouseName}.",
                'type' => 'system',
                'read_at' => null,
                'data' => $data,
            ]);

            return $notification->fresh();
        }

        return UserNotification::create([
            'user_id' => $assignment->user_id,
            'operation_key' => 'warehouse-invitation:'.$assignment->id.':current',
            'title' => 'Lời mời quản lý kho',
            'content' => "{$vendorName} mời bạn quản lý kho {$warehouseName}.",
            'type' => 'system',
            'data' => $data,
        ]);
    }
}

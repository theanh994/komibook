<?php

namespace App\Services;

use App\Models\User;
use App\Models\WarehouseAssignmentEvent;
use App\Models\WarehouseManagerAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WarehouseAssignmentService
{
    public const CAPABILITIES = [
        'view_inventory',
        'receive_stock',
        'dispatch_stock',
        'transfer_stock',
        'count_inventory',
        'print_documents',
    ];

    private const TRANSITIONS = [
        'invited' => ['active', 'declined', 'revoked'],
        'active' => ['suspended', 'revoked'],
        'suspended' => ['active', 'revoked'],
        'revoked' => [],
    ];

    public function transition(
        WarehouseManagerAssignment $assignment,
        string $target,
        User $actor,
        ?string $reason = null,
        ?string $operationKey = null,
    ): WarehouseManagerAssignment {
        $operationKey ??= 'warehouse-assignment:'.Str::uuid();

        return DB::transaction(function () use ($assignment, $target, $actor, $reason, $operationKey) {
            $existing = WarehouseAssignmentEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                return $existing->assignment()->with(['warehouse', 'vendor', 'user'])->firstOrFail();
            }

            $locked = WarehouseManagerAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $from = $locked->status;
            if (! in_array($target, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Không thể chuyển phân công từ {$from} sang {$target}.",
                ]);
            }
            if (in_array($target, ['suspended', 'revoked'], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Phải nhập lý do tạm dừng hoặc thu hồi.']);
            }

            $isSelfResponse = in_array($target, ['active', 'declined'], true)
                && $from === 'invited'
                && $actor->id === $locked->user_id;
            $isVendorOwner = $actor->role === 'vendor'
                && $locked->vendor()->withoutGlobalScopes()->where('user_id', $actor->id)->exists();
            if ($from === 'invited' && in_array($target, ['active', 'declined'], true) && ! $isSelfResponse) {
                abort(403, 'Chỉ người được mời mới có thể phản hồi phân công kho.');
            }
            if (! $isSelfResponse && ! $isVendorOwner && $actor->role !== 'admin') {
                abort(403, 'Bạn không có quyền thay đổi phân công kho này.');
            }

            $updates = ['status' => $target, 'last_reason' => $reason];
            if ($target === 'active') {
                $updates['accepted_at'] ??= now();
                $updates['suspended_at'] = null;
                $updates['invitation_token_hash'] = null;
            }
            if ($target === 'suspended') {
                $updates['suspended_at'] = now();
            }
            if ($target === 'revoked') {
                $updates['revoked_at'] = now();
                $updates['invitation_token_hash'] = null;
            }
            if ($target === 'declined') {
                $updates['invitation_token_hash'] = null;
            }
            $locked->update($updates);
            WarehouseAssignmentEvent::create([
                'warehouse_manager_assignment_id' => $locked->id,
                'actor_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $target,
                'reason' => $reason,
                'operation_key' => $operationKey,
            ]);

            return $locked->fresh(['warehouse', 'vendor', 'user']);
        });
    }
}

<?php

namespace App\Services;

use App\Models\OrganizationDistributionAgreement;
use App\Models\OrganizationDistributionAgreementEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DistributionAgreementService
{
    private const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['verified', 'changes_requested', 'rejected'],
        'changes_requested' => ['submitted'],
        'verified' => ['suspended', 'revoked'],
        'suspended' => ['verified', 'revoked'],
        'rejected' => [],
        'revoked' => [],
    ];

    public function transition(
        OrganizationDistributionAgreement $agreement,
        string $target,
        User $actor,
        ?string $reason = null,
        ?string $operationKey = null,
    ): OrganizationDistributionAgreement {
        $operationKey ??= 'distribution-agreement:'.Str::uuid();

        return DB::transaction(function () use ($agreement, $target, $actor, $reason, $operationKey) {
            $existing = OrganizationDistributionAgreementEvent::where('operation_key', $operationKey)->first();
            if ($existing) {
                return $existing->agreement()->with(['publisher', 'distributor'])->firstOrFail();
            }

            $locked = OrganizationDistributionAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            $from = $locked->status;
            if (! in_array($target, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Không thể chuyển thỏa thuận từ {$from} sang {$target}."]);
            }
            if (in_array($target, ['changes_requested', 'rejected', 'suspended', 'revoked'], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Phải nhập lý do cho quyết định này.']);
            }
            if (in_array($target, ['verified', 'changes_requested', 'rejected', 'suspended', 'revoked'], true)
                && $actor->role !== 'admin') {
                abort(403, 'Chỉ quản trị viên được duyệt hoặc thu hồi thỏa thuận phân phối.');
            }

            $updates = ['status' => $target, 'last_review_reason' => $reason];
            if ($target === 'submitted') {
                $updates['submitted_at'] = now();
            }
            if ($target === 'verified') {
                $updates['verified_at'] = now();
                $updates['revoked_at'] = null;
                $updates['reviewed_by'] = $actor->id;
            }
            if ($target === 'revoked') {
                $updates['revoked_at'] = now();
                $updates['reviewed_by'] = $actor->id;
            }
            $locked->update($updates);
            OrganizationDistributionAgreementEvent::create([
                'organization_distribution_agreement_id' => $locked->id,
                'actor_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $target,
                'reason' => $reason,
                'operation_key' => $operationKey,
            ]);

            return $locked->fresh(['publisher', 'distributor']);
        });
    }
}

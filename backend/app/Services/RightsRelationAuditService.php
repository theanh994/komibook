<?php

namespace App\Services;

use App\Models\RightsRelationEvent;
use App\Models\UserNotification;

class RightsRelationAuditService
{
    public function record(string $subjectType, int $subjectId, int $actorId, string $action, string $operationKey, ?int $notifyUserId = null, ?string $reason = null, array $metadata = []): void
    {
        RightsRelationEvent::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'actor_id' => $actorId,
            'action' => $action,
            'reason' => $reason,
            'operation_key' => $operationKey,
            'metadata' => $metadata ?: null,
        ]);

        if ($notifyUserId) {
            UserNotification::firstOrCreate(
                ['operation_key' => 'notification:'.$operationKey],
                [
                    'user_id' => $notifyUserId,
                    'title' => 'Cập nhật quan hệ quyền tác giả',
                    'content' => "Quan hệ {$subjectType} đã ghi nhận thao tác {$action}.".($reason ? ' Lý do: '.$reason : ''),
                    'type' => 'system',
                    'data' => ['subject_type' => $subjectType, 'subject_id' => $subjectId, 'action' => $action],
                ]
            );
        }
    }
}

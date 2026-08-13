<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/** Atomic state machine for the AI-to-human support boundary. */
class ChatSessionLifecycleService
{
    public function requestHuman(ChatSession $session, User $actor): ChatSession
    {
        return $this->withSession($session, $actor, function (ChatSession $locked, User $current): ChatSession {
            $this->customerOwns($locked, $current);
            if ($this->tuple($locked, ChatSession::STATUS_QUEUED, ChatSession::MODE_HUMAN, null)) {
                return $locked;
            }
            $this->requireTuple($locked, ChatSession::STATUS_OPEN, ChatSession::MODE_AI, null);

            return $this->transition($locked, $current, 'request_human', 'customer_requested_human', 'Yêu cầu gặp nhân viên đã được đưa vào hàng đợi hỗ trợ. AI sẽ tạm dừng trả lời cho đến khi nhân viên tiếp nhận.', [
                'status' => ChatSession::STATUS_QUEUED, 'responder_mode' => ChatSession::MODE_HUMAN,
                'assigned_user_id' => null, 'assigned_at' => null,
                'auto_resume_at' => null, 'auto_resume_anchor_message_id' => null,
            ]);
        });
    }

    public function resumeAi(ChatSession $session, User $actor): ChatSession
    {
        return $this->withSession($session, $actor, function (ChatSession $locked, User $current): ChatSession {
            $this->customerOwns($locked, $current);
            if ($this->tuple($locked, ChatSession::STATUS_OPEN, ChatSession::MODE_AI, null)) {
                return $locked;
            }
            $this->requireTuple($locked, ChatSession::STATUS_QUEUED, ChatSession::MODE_HUMAN, null);

            return $this->transition($locked, $current, 'resume_ai', 'customer_resumed_ai', 'Trợ lý AI đã được bật lại. Nội dung do AI tạo sẽ luôn được ghi nhận rõ ràng.', [
                'status' => ChatSession::STATUS_OPEN, 'responder_mode' => ChatSession::MODE_AI,
                'assigned_user_id' => null, 'assigned_at' => null,
                'auto_resume_at' => null, 'auto_resume_anchor_message_id' => null,
            ]);
        });
    }

    public function takeover(ChatSession $session, User $actor): ChatSession
    {
        return $this->withSession($session, $actor, function (ChatSession $locked, User $current): ChatSession {
            $this->staffOwnsScope($locked, $current);
            if (in_array($locked->status, [ChatSession::STATUS_ASSIGNED, ChatSession::STATUS_WAITING_CUSTOMER], true)
                && $locked->responder_mode === ChatSession::MODE_HUMAN
                && $locked->assigned_user_id !== null
                && (int) $locked->assigned_user_id === $current->id) {
                return $locked;
            }
            $this->requireTuple($locked, ChatSession::STATUS_QUEUED, ChatSession::MODE_HUMAN, null);

            return $this->transition($locked, $current, 'takeover', 'staff_accepted_handoff', "Xin chào! {$current->name} đã tiếp nhận cuộc trò chuyện này.", [
                'status' => ChatSession::STATUS_ASSIGNED, 'responder_mode' => ChatSession::MODE_HUMAN,
                'assigned_user_id' => $current->id, 'assigned_at' => now(),
                'auto_resume_at' => null, 'auto_resume_anchor_message_id' => null,
            ]);
        });
    }

    /** @return array{0: ChatSession, 1: bool} */
    public function customerMessage(ChatSession $session, User $actor, string $message, ?array $metadata, ?callable $metadataResolver = null): array
    {
        return $this->withSession($session, $actor, function (ChatSession $locked, User $current) use ($message, $metadata, $metadataResolver): array {
            $this->ownerOwns($locked, $current);
            abort_if($locked->isTerminal(), 409, 'Phiên hỗ trợ đã kết thúc.');
            $isAi = $this->tuple($locked, ChatSession::STATUS_OPEN, ChatSession::MODE_AI, null);
            $isAssigned = $locked->assigned_user_id !== null && $this->tuple($locked, ChatSession::STATUS_ASSIGNED, ChatSession::MODE_HUMAN, $locked->assigned_user_id);
            $isWaiting = $this->tuple($locked, ChatSession::STATUS_WAITING_CUSTOMER, ChatSession::MODE_HUMAN, $locked->assigned_user_id);
            abort_unless($isAi || $isAssigned || ($isWaiting && $locked->assigned_user_id !== null), 409, 'Phiên chưa sẵn sàng nhận tin nhắn.');

            $metadata = $metadataResolver ? $metadataResolver() : $metadata;
            ChatMessage::create(['chat_session_id' => $locked->id, 'sender_type' => 'customer', 'sender_id' => $current->id, 'message' => $message, 'metadata' => $metadata]);
            if ($isWaiting) {
                $locked = $this->transition($locked, $current, 'customer_reply', 'customer_replied_to_staff', 'Khách hàng đã phản hồi. Nhân viên tiếp tục phụ trách phiên hỗ trợ này.', [
                    'status' => ChatSession::STATUS_ASSIGNED, 'responder_mode' => ChatSession::MODE_HUMAN,
                    'assigned_user_id' => $locked->assigned_user_id,
                    'auto_resume_at' => null, 'auto_resume_anchor_message_id' => null,
                ]);
            } else {
                $locked->update(['last_message_at' => now()]);
                $locked->refresh();
            }

            return [$locked->setRelation('user', $current), $isAi];
        });
    }

    public function staffReply(ChatSession $session, User $actor, string $message, ?array $metadata, ?callable $metadataResolver = null): ChatSession
    {
        return $this->withSession($session, $actor, function (ChatSession $locked, User $current) use ($message, $metadata, $metadataResolver): ChatSession {
            $this->staffOwnsScope($locked, $current);
            abort_unless((int) $locked->assigned_user_id === $current->id, 403, 'Bạn cần tiếp nhận phiên trước khi trả lời.');
            abort_if($locked->isTerminal(), 409, 'Phiên hỗ trợ đã kết thúc.');
            $assigned = $this->tuple($locked, ChatSession::STATUS_ASSIGNED, ChatSession::MODE_HUMAN, $current->id);
            $waiting = $this->tuple($locked, ChatSession::STATUS_WAITING_CUSTOMER, ChatSession::MODE_HUMAN, $current->id);
            abort_unless($assigned || $waiting, 409, 'Trạng thái phiên không hợp lệ.');
            $metadata = $metadataResolver ? $metadataResolver() : $metadata;
            $staffMessage = ChatMessage::create(['chat_session_id' => $locked->id, 'sender_type' => $current->role === 'admin' ? 'admin' : 'vendor', 'sender_id' => $current->id, 'message' => $message, 'metadata' => $metadata]);
            $deadline = now()->addMinutes($this->idleTimeoutMinutes());
            if ($assigned) {
                return $this->transition($locked, $current, 'staff_reply', 'staff_replied', 'Nhân viên đã phản hồi và đang chờ khách hàng trả lời.', [
                    'status' => ChatSession::STATUS_WAITING_CUSTOMER,
                    'responder_mode' => ChatSession::MODE_HUMAN,
                    'assigned_user_id' => $current->id,
                    'auto_resume_at' => $deadline,
                    'auto_resume_anchor_message_id' => $staffMessage->id,
                ], [
                    'auto_resume_at' => $deadline->toISOString(),
                    'auto_resume_anchor_message_id' => $staffMessage->id,
                    'idle_timeout_minutes' => $this->idleTimeoutMinutes(),
                ]);
            }
            $locked->update([
                'last_message_at' => now(),
                'auto_resume_at' => $deadline,
                'auto_resume_anchor_message_id' => $staffMessage->id,
            ]);

            return $locked->fresh();
        });
    }

    public function extendHumanWait(ChatSession $session, User $actor): ChatSession
    {
        return $this->withSession($session, $actor, function (ChatSession $locked, User $current): ChatSession {
            $this->customerOwns($locked, $current);
            abort_unless(
                $locked->assigned_user_id !== null
                    && $this->tuple($locked, ChatSession::STATUS_WAITING_CUSTOMER, ChatSession::MODE_HUMAN, $locked->assigned_user_id)
                    && $locked->auto_resume_anchor_message_id !== null,
                409,
                'Phiên không thể gia hạn thời gian chờ.'
            );

            $deadline = now()->addMinutes($this->idleTimeoutMinutes());

            return $this->transition($locked, $current, 'extend_human_wait', 'customer_requested_more_human_wait', 'Khách hàng đã chọn tiếp tục chờ tư vấn viên.', [
                'status' => ChatSession::STATUS_WAITING_CUSTOMER,
                'responder_mode' => ChatSession::MODE_HUMAN,
                'assigned_user_id' => $locked->assigned_user_id,
                'auto_resume_at' => $deadline,
                'auto_resume_anchor_message_id' => $locked->auto_resume_anchor_message_id,
            ], [
                'auto_resume_at' => $deadline->toISOString(),
                'idle_timeout_minutes' => $this->idleTimeoutMinutes(),
            ]);
        });
    }

    public function autoResumeIdle(int $sessionId): bool
    {
        return DB::transaction(function () use ($sessionId): bool {
            $locked = ChatSession::query()->lockForUpdate()->find($sessionId);
            if (! $locked
                || $locked->assigned_user_id === null
                || ! $this->tuple($locked, ChatSession::STATUS_WAITING_CUSTOMER, ChatSession::MODE_HUMAN, $locked->assigned_user_id)
                || $locked->auto_resume_at === null
                || $locked->auto_resume_at->isFuture()
                || $locked->auto_resume_anchor_message_id === null) {
                return false;
            }

            $users = User::query()
                ->whereIn('id', collect([$locked->user_id, $locked->assigned_user_id])->filter()->sort()->values())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $owner = $users->get($locked->user_id);
            $assignee = $users->get($locked->assigned_user_id);
            if (! $owner || $owner->role !== 'customer' || ! $assignee || ! $this->isCanonicalAssignee($locked, $assignee)) {
                return false;
            }

            $anchor = ChatMessage::query()->lockForUpdate()->find($locked->auto_resume_anchor_message_id);
            $expectedSenderType = $assignee->role === 'admin' ? 'admin' : 'vendor';
            if (! $anchor
                || (int) $anchor->chat_session_id !== $locked->id
                || (int) $anchor->sender_id !== $assignee->id
                || $anchor->sender_type !== $expectedSenderType) {
                return false;
            }

            $newerParticipantMessageExists = ChatMessage::query()
                ->where('chat_session_id', $locked->id)
                ->where('id', '>', $anchor->id)
                ->whereIn('sender_type', ['customer', 'admin', 'vendor'])
                ->exists();
            if ($newerParticipantMessageExists) {
                return false;
            }

            $this->transitionAsSystem($locked, 'auto_resume_ai', 'customer_idle_30m', 'Phiên tư vấn đã trở lại trợ lý tự động do không có phản hồi trong 30 phút. Trợ lý sẽ chỉ trả lời khi bạn gửi tin nhắn tiếp theo.', [
                'status' => ChatSession::STATUS_OPEN,
                'responder_mode' => ChatSession::MODE_AI,
                'assigned_user_id' => null,
                'assigned_at' => null,
                'auto_resume_at' => null,
                'auto_resume_anchor_message_id' => null,
            ], [
                'policy' => 'human_idle_auto_resume_v1',
                'idle_timeout_minutes' => $this->idleTimeoutMinutes(),
                'anchor_message_id' => $anchor->id,
                'previous_assigned_user_id' => $assignee->id,
            ]);

            return true;
        });
    }

    public function close(ChatSession $session, User $actor, string $resolution): ChatSession
    {
        return $this->withSession($session, $actor, function (ChatSession $locked, User $current) use ($resolution): ChatSession {
            $this->staffOwnsScope($locked, $current);
            if ($locked->status === ChatSession::STATUS_RESOLVED && $locked->responder_mode === ChatSession::MODE_HUMAN && $locked->assigned_user_id !== null && (int) $locked->assigned_user_id === $current->id) {
                return $locked;
            }
            abort_unless((int) $locked->assigned_user_id === $current->id, 403, 'Bạn cần tiếp nhận phiên trước khi hoàn tất.');
            abort_unless(in_array($locked->status, [ChatSession::STATUS_ASSIGNED, ChatSession::STATUS_WAITING_CUSTOMER], true) && $locked->responder_mode === ChatSession::MODE_HUMAN, 409, 'Trạng thái phiên không hợp lệ.');

            return $this->transition($locked, $current, 'close', 'staff_closed', $resolution, ['status' => ChatSession::STATUS_RESOLVED, 'responder_mode' => ChatSession::MODE_HUMAN, 'assigned_user_id' => $current->id, 'resolved_at' => now(), 'auto_resume_at' => null, 'auto_resume_anchor_message_id' => null]);
        });
    }

    public function reopen(ChatSession $session, User $actor): ChatSession
    {
        return $this->withSession($session, $actor, function (ChatSession $locked, User $current): ChatSession {
            $this->customerOwns($locked, $current);
            if ($this->tuple($locked, ChatSession::STATUS_OPEN, ChatSession::MODE_AI, null)) {
                return $locked;
            }
            abort_unless(
                in_array($locked->status, [ChatSession::STATUS_RESOLVED, ChatSession::STATUS_CLOSED], true)
                    && $locked->responder_mode === ChatSession::MODE_HUMAN
                    && $locked->assigned_user_id !== null,
                409,
                'Trạng thái phiên không hợp lệ.'
            );

            return $this->transition($locked, $current, 'reopen', 'customer_reopened_conversation', 'Cuộc trò chuyện đã được mở lại. Trợ lý AI sẽ hỗ trợ trước cho đến khi nhân viên tiếp nhận.', ['status' => ChatSession::STATUS_OPEN, 'responder_mode' => ChatSession::MODE_AI, 'assigned_user_id' => null, 'assigned_at' => null, 'resolved_at' => null, 'auto_resume_at' => null, 'auto_resume_anchor_message_id' => null]);
        });
    }

    /** @return array{0: ChatSession, 1: bool} */
    public function appendAiReply(ChatSession $expected, string $message, ?array $metadata): array
    {
        return DB::transaction(function () use ($expected, $message, $metadata): array {
            $locked = ChatSession::query()->lockForUpdate()->findOrFail($expected->id);
            $owner = $locked->user_id ? User::query()->lockForUpdate()->find($locked->user_id) : null;
            $sameIdentity = $locked->user_id === $expected->user_id
                && $locked->target_type === $expected->target_type
                && $locked->vendor_id === $expected->vendor_id
                && $locked->conversation_key === $expected->conversation_key
                && $locked->lock_version === $expected->lock_version;
            $expectedOwnerRole = $expected->relationLoaded('user') ? $expected->user?->role : null;
            if (! $sameIdentity || ! $owner || ($expectedOwnerRole !== null && $expectedOwnerRole !== $owner->role) || ! in_array($owner->role, ChatSession::PERSONAL_OWNER_ROLES, true) || ! $this->hasCanonicalScope($locked, $owner) || ! $this->tuple($locked, ChatSession::STATUS_OPEN, ChatSession::MODE_AI, null)) {
                return [$locked->fresh(), false];
            }
            ChatMessage::create(['chat_session_id' => $locked->id, 'sender_type' => 'ai', 'message' => $message, 'metadata' => $metadata]);
            $locked->update(['last_message_at' => now()]);

            return [$locked->fresh(), true];
        });
    }

    private function withSession(ChatSession $session, User $actor, callable $callback): mixed
    {
        return DB::transaction(function () use ($session, $actor, $callback): mixed {
            $locked = ChatSession::query()->lockForUpdate()->findOrFail($session->id);
            $current = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->canonicalScope($locked, $current);

            return $callback($locked, $current);
        });
    }

    private function ownerOwns(ChatSession $session, User $actor): void
    {
        abort_unless(in_array($actor->role, ChatSession::PERSONAL_OWNER_ROLES, true)
            && $session->user_id !== null
            && (int) $session->user_id === $actor->id
            && ($actor->role === 'customer' || $this->isCanonicalPersonalAiTuple($session)), 403);
    }

    private function customerOwns(ChatSession $session, User $actor): void
    {
        abort_unless($actor->role === 'customer' && $session->user_id !== null && (int) $session->user_id === $actor->id, 403, 'Bạn không có quyền truy cập phiên hỗ trợ này.');
    }

    private function canonicalScope(ChatSession $session): void
    {
        if ($session->target_type === ChatSession::TARGET_PLATFORM) {
            abort_unless($session->vendor_id === null, 409, 'Phạm vi phiên không hợp lệ.');

            return;
        }

        abort_unless($session->target_type === ChatSession::TARGET_VENDOR && $session->vendor_id !== null, 409, 'Phạm vi phiên không hợp lệ.');
        $vendor = Vendor::withoutGlobalScopes()->lockForUpdate()->whereKey($session->vendor_id)->where('status', 'active')->first();
        abort_unless($vendor, 409, 'Gian hàng hiện không thể nhận hỗ trợ.');
    }

    private function hasCanonicalScope(ChatSession $session, ?User $owner = null): bool
    {
        if ($session->target_type === ChatSession::TARGET_PLATFORM) {
            return $session->vendor_id === null
                && ($owner === null || $owner->role === 'customer' || $this->isCanonicalPersonalAiTuple($session));
        }

        return ($owner === null || $owner->role === 'customer')
            && $session->target_type === ChatSession::TARGET_VENDOR
            && $session->vendor_id !== null
            && Vendor::withoutGlobalScopes()->lockForUpdate()->whereKey($session->vendor_id)->where('status', 'active')->exists();
    }

    private function isCanonicalPersonalAiTuple(ChatSession $session): bool
    {
        return $session->target_type === ChatSession::TARGET_PLATFORM
            && $session->vendor_id === null
            && $session->status === ChatSession::STATUS_OPEN
            && $session->responder_mode === ChatSession::MODE_AI
            && $session->assigned_user_id === null;
    }

    private function staffOwnsScope(ChatSession $session, User $actor): void
    {
        if ($actor->role === 'admin') {
            abort_unless($session->target_type === ChatSession::TARGET_PLATFORM && $session->vendor_id === null, 403);

            return;
        }
        $vendor = Vendor::withoutGlobalScopes()->lockForUpdate()->where('user_id', $actor->id)->where('status', 'active')->first();
        abort_unless($actor->role === 'vendor' && $vendor && $session->target_type === ChatSession::TARGET_VENDOR && (int) $session->vendor_id === $vendor->id, 403);
    }

    private function isCanonicalAssignee(ChatSession $session, User $assignee): bool
    {
        if ($session->target_type === ChatSession::TARGET_PLATFORM) {
            return $session->vendor_id === null && $assignee->role === 'admin';
        }

        if ($session->target_type !== ChatSession::TARGET_VENDOR || $session->vendor_id === null || $assignee->role !== 'vendor') {
            return false;
        }

        return Vendor::withoutGlobalScopes()
            ->lockForUpdate()
            ->whereKey($session->vendor_id)
            ->where('user_id', $assignee->id)
            ->where('status', 'active')
            ->exists();
    }

    private function tuple(ChatSession $session, string $status, string $mode, ?int $assignee): bool
    {
        return $session->status === $status && $session->responder_mode === $mode && $session->assigned_user_id === $assignee;
    }

    private function requireTuple(ChatSession $session, string $status, string $mode, ?int $assignee): void
    {
        abort_unless($this->tuple($session, $status, $mode, $assignee), 409, 'Trạng thái phiên không hợp lệ.');
    }

    private function transition(ChatSession $session, User $actor, string $operation, string $reason, string $message, array $to, array $metadata = []): ChatSession
    {
        return $this->applyTransition($session, $actor->role, $actor->id, $operation, $reason, $message, $to, $metadata);
    }

    private function transitionAsSystem(ChatSession $session, string $operation, string $reason, string $message, array $to, array $metadata = []): ChatSession
    {
        return $this->applyTransition($session, 'system', null, $operation, $reason, $message, $to, $metadata);
    }

    private function applyTransition(ChatSession $session, string $actorType, ?int $actorId, string $operation, string $reason, string $message, array $to, array $metadata): ChatSession
    {
        $from = ['status' => $session->status, 'responder_mode' => $session->responder_mode, 'assigned_user_id' => $session->assigned_user_id];
        $before = $session->lock_version;
        $to = array_merge($to, ['lock_version' => $before + 1, 'last_message_at' => now()]);
        $session->update($to);
        ChatMessage::create(['chat_session_id' => $session->id, 'sender_type' => 'system', 'message' => $message, 'metadata' => array_merge(['event' => 'chat_session_transition', 'schema_version' => 1, 'operation' => $operation, 'reason' => $reason, 'actor_type' => $actorType, 'actor_id' => $actorId, 'from' => $from, 'to' => ['status' => $to['status'], 'responder_mode' => $to['responder_mode'], 'assigned_user_id' => $to['assigned_user_id']], 'target_type' => $session->target_type, 'vendor_id' => $session->vendor_id, 'lock_version_before' => $before, 'lock_version_after' => $before + 1], $metadata)]);

        return $session->fresh();
    }

    private function idleTimeoutMinutes(): int
    {
        return max(1, min(1440, (int) config('chat.human_idle_auto_resume_minutes', 30)));
    }
}

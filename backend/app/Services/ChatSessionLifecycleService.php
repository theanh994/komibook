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
            ]);
        });
    }

    /** @return array{0: ChatSession, 1: bool} */
    public function customerMessage(ChatSession $session, User $actor, string $message, ?array $metadata, ?callable $metadataResolver = null): array
    {
        return $this->withSession($session, $actor, function (ChatSession $locked, User $current) use ($message, $metadata, $metadataResolver): array {
            $this->customerOwns($locked, $current);
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
                ]);
            } else {
                $locked->update(['last_message_at' => now()]);
                $locked->refresh();
            }

            return [$locked, $isAi];
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
            ChatMessage::create(['chat_session_id' => $locked->id, 'sender_type' => $current->role === 'admin' ? 'admin' : 'vendor', 'sender_id' => $current->id, 'message' => $message, 'metadata' => $metadata]);
            if ($assigned) {
                return $this->transition($locked, $current, 'staff_reply', 'staff_replied', 'Nhân viên đã phản hồi và đang chờ khách hàng trả lời.', ['status' => ChatSession::STATUS_WAITING_CUSTOMER, 'responder_mode' => ChatSession::MODE_HUMAN, 'assigned_user_id' => $current->id]);
            }
            $locked->update(['last_message_at' => now()]);

            return $locked->fresh();
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

            return $this->transition($locked, $current, 'close', 'staff_closed', $resolution, ['status' => ChatSession::STATUS_RESOLVED, 'responder_mode' => ChatSession::MODE_HUMAN, 'assigned_user_id' => $current->id, 'resolved_at' => now()]);
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

            return $this->transition($locked, $current, 'reopen', 'customer_reopened_conversation', 'Cuộc trò chuyện đã được mở lại. Trợ lý AI sẽ hỗ trợ trước cho đến khi nhân viên tiếp nhận.', ['status' => ChatSession::STATUS_OPEN, 'responder_mode' => ChatSession::MODE_AI, 'assigned_user_id' => null, 'assigned_at' => null, 'resolved_at' => null]);
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
            if (! $sameIdentity || ! $this->hasCanonicalScope($locked) || ! $owner || $owner->role !== 'customer' || ! $this->tuple($locked, ChatSession::STATUS_OPEN, ChatSession::MODE_AI, null)) {
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
            $this->canonicalScope($locked);

            return $callback($locked, $current);
        });
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

    private function hasCanonicalScope(ChatSession $session): bool
    {
        if ($session->target_type === ChatSession::TARGET_PLATFORM) {
            return $session->vendor_id === null;
        }

        return $session->target_type === ChatSession::TARGET_VENDOR
            && $session->vendor_id !== null
            && Vendor::withoutGlobalScopes()->lockForUpdate()->whereKey($session->vendor_id)->where('status', 'active')->exists();
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

    private function tuple(ChatSession $session, string $status, string $mode, ?int $assignee): bool
    {
        return $session->status === $status && $session->responder_mode === $mode && $session->assigned_user_id === $assignee;
    }

    private function requireTuple(ChatSession $session, string $status, string $mode, ?int $assignee): void
    {
        abort_unless($this->tuple($session, $status, $mode, $assignee), 409, 'Trạng thái phiên không hợp lệ.');
    }

    private function transition(ChatSession $session, User $actor, string $operation, string $reason, string $message, array $to): ChatSession
    {
        $from = ['status' => $session->status, 'responder_mode' => $session->responder_mode, 'assigned_user_id' => $session->assigned_user_id];
        $before = $session->lock_version;
        $to = array_merge($to, ['lock_version' => $before + 1, 'last_message_at' => now()]);
        $session->update($to);
        ChatMessage::create(['chat_session_id' => $session->id, 'sender_type' => 'system', 'message' => $message, 'metadata' => ['event' => 'chat_session_transition', 'schema_version' => 1, 'operation' => $operation, 'reason' => $reason, 'actor_type' => $actor->role, 'actor_id' => $actor->id, 'from' => $from, 'to' => ['status' => $to['status'], 'responder_mode' => $to['responder_mode'], 'assigned_user_id' => $to['assigned_user_id']], 'target_type' => $session->target_type, 'vendor_id' => $session->vendor_id, 'lock_version_before' => $before, 'lock_version_after' => $before + 1]]);

        return $session->fresh();
    }
}

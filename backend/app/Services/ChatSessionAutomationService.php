<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Facades\DB;

class ChatSessionAutomationService
{
    public const HUMAN_REPLY_IDLE_MINUTES = 30;

    public function resumeIfIdle(ChatSession $session): bool
    {
        return DB::transaction(function () use ($session): bool {
            $locked = ChatSession::query()->lockForUpdate()->find($session->id);
            if (! $locked || ! $this->isIdleHumanSession($locked)) {
                return false;
            }

            $locked->update([
                'assigned_user_id' => null,
                'assigned_at' => null,
                'responder_mode' => ChatSession::MODE_AI,
                'status' => ChatSession::STATUS_OPEN,
                'lock_version' => $locked->lock_version + 1,
                'last_message_at' => now(),
            ]);

            ChatMessage::create([
                'chat_session_id' => $locked->id,
                'sender_type' => 'system',
                'message' => 'Đã hơn 30 phút kể từ phản hồi gần nhất của nhân viên. Trợ lý AI được tự động bật lại để hỗ trợ bạn ngay.',
                'metadata' => [
                    'event' => 'ai_auto_resumed',
                    'idle_minutes' => self::HUMAN_REPLY_IDLE_MINUTES,
                    'ai_disclosure' => true,
                ],
            ]);

            $session->refresh();

            return true;
        });
    }

    public function resumeAllIdle(): int
    {
        $sessionIds = ChatSession::query()
            ->where('responder_mode', ChatSession::MODE_HUMAN)
            ->where('status', ChatSession::STATUS_WAITING_CUSTOMER)
            ->where('last_message_at', '<=', now()->subMinutes(self::HUMAN_REPLY_IDLE_MINUTES))
            ->pluck('id');

        $resumed = 0;
        foreach ($sessionIds as $sessionId) {
            $session = ChatSession::query()->find($sessionId);
            if ($session && $this->resumeIfIdle($session)) {
                $resumed++;
            }
        }

        return $resumed;
    }

    private function isIdleHumanSession(ChatSession $session): bool
    {
        return $session->responder_mode === ChatSession::MODE_HUMAN
            && $session->status === ChatSession::STATUS_WAITING_CUSTOMER
            && $session->last_message_at
            && $session->last_message_at->lte(now()->subMinutes(self::HUMAN_REPLY_IDLE_MINUTES));
    }
}

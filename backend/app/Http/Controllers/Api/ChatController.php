<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ChatSessionLifecycleService;
use App\Services\GeminiChatService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private readonly GeminiChatService $geminiService, private readonly ChatSessionLifecycleService $lifecycleService) {}

    public function createSession(Request $request): JsonResponse
    {
        $data = $request->validate(['target_type' => 'nullable|in:platform,vendor', 'vendor_id' => 'nullable|required_if:target_type,vendor|exists:vendors,id']);
        $actor = $this->customer($request);
        $target = $data['target_type'] ?? ChatSession::TARGET_PLATFORM;
        $vendorId = $target === ChatSession::TARGET_VENDOR ? (int) $data['vendor_id'] : null;
        if ($vendorId && ! Vendor::withoutGlobalScopes()->whereKey($vendorId)->where('status', 'active')->exists()) {
            abort(422, 'Gian hàng này hiện không thể nhận yêu cầu hỗ trợ.');
        }
        $key = 'user:'.$actor->id.':'.($vendorId ? 'vendor:'.$vendorId : 'platform');
        $session = ChatSession::query()->where('conversation_key', $key)->first();
        if (! $session) {
            $session = ChatSession::create(['user_id' => $actor->id, 'vendor_id' => $vendorId, 'conversation_key' => $key, 'target_type' => $target, 'responder_mode' => ChatSession::MODE_AI, 'status' => ChatSession::STATUS_OPEN, 'last_message_at' => now()]);
            $vendor = $vendorId ? Vendor::withoutGlobalScopes()->find($vendorId) : null;
            ChatMessage::create(['chat_session_id' => $session->id, 'sender_type' => 'ai', 'message' => $vendor ? "Xin chào! Tôi là Trợ lý AI KomiBook đang hỗ trợ cho gian hàng {$vendor->shop_name}. Tôi có thể tư vấn sách của shop hoặc chuyển bạn tới nhân viên gian hàng." : 'Xin chào! Tôi là Trợ lý AI KomiBook. Tôi có thể gợi ý sách, tóm tắt bài viết, giải đáp chính sách và chuyển bạn tới tư vấn viên khi cần.', 'metadata' => ['ai_disclosure' => true, 'quick_replies' => $vendor ? ['Tư vấn sách của shop', 'Chính sách giao hàng', 'Gặp nhân viên shop'] : ['Gợi ý sách theo nhu cầu', 'Hạng VIP và quyền lợi', 'Tóm tắt bài viết', 'Gặp tư vấn viên KomiBook']]]);
        } elseif ($session->isTerminal()) {
            $session = $this->lifecycleService->reopen($session, $actor);
        } else {
            $this->assertCustomer($session, $actor);
        }

        return response()->json(['success' => true, 'session' => $this->payload($session, true)]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $actor = $this->customer($request);
        $sessions = ChatSession::query()->with(['vendor:id,shop_name,slug', 'lastMessage'])->where('user_id', $actor->id)->orderByDesc('last_message_at')->get();

        return response()->json(['success' => true, 'conversations' => $sessions->map(fn (ChatSession $s) => ['id' => $s->id, 'target_type' => $s->target_type, 'vendor_id' => $s->vendor_id, 'vendor' => $s->vendor, 'responder_mode' => $s->responder_mode, 'status' => $s->status, 'last_message' => $s->lastMessage, 'last_message_at' => $s->last_message_at])->values()]);
    }

    public function showSession(Request $request, ChatSession $session): JsonResponse
    {
        $this->assertCustomer($session, $this->customer($request));

        return response()->json(['success' => true, 'session' => $this->payload($session, true, max(0, $request->integer('after_id')))]);
    }

    public function sendMessage(Request $request, ChatSession $session): JsonResponse
    {
        $actor = $this->customer($request);
        $this->assertCustomer($session, $actor);
        $data = $request->validate(['message' => 'nullable|string|max:2000|required_without:image', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120|required_without:message', 'context_book_id' => 'nullable|integer|min:1']);
        $attachment = null;
        try {
            [$session, $generate] = $this->lifecycleService->customerMessage($session, $actor, trim($data['message'] ?? '') ?: 'Đã gửi một hình ảnh.', null, function () use ($request, $session, &$attachment): ?array {
                return $attachment = $this->storeAttachment($request, $session);
            });
        } catch (\Throwable $exception) {
            $this->deleteAttachment($session, $attachment);

            throw $exception;
        }
        if ($generate) {
            $prompt = trim($data['message'] ?? '') ?: 'Khách hàng vừa gửi một hình ảnh cần được hỗ trợ.';
            $result = $this->geminiService->generateReply($session, $prompt, $data['context_book_id'] ?? null, $attachment);
            [$session] = $this->lifecycleService->appendAiReply($session, $result['message'], $result['metadata'] ?? null);
        }

        return response()->json(['success' => true, 'session' => $this->payload($session, true)]);
    }

    public function updateExternalAiConsent(Request $request, ChatSession $session): JsonResponse
    {
        $actor = $this->customer($request);
        $this->assertCustomer($session, $actor);
        $data = $request->validate(['consent' => ['required', 'boolean'], 'policy_version' => ['required', 'string']]);
        abort_unless(hash_equals(GeminiChatService::EXTERNAL_AI_POLICY_VERSION, $data['policy_version']), 422);
        $updated = DB::transaction(function () use ($session, $actor, $data): ChatSession {
            $locked = ChatSession::query()->lockForUpdate()->findOrFail($session->id);
            $owner = User::query()->lockForUpdate()->findOrFail($actor->id);
            $this->assertCustomer($locked, $owner);
            $grant = (bool) $data['consent'];
            $active = $locked->hasActiveExternalAiConsent(GeminiChatService::EXTERNAL_AI_POLICY_VERSION, GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE, $owner);
            $unrevoked = $locked->external_ai_consented_at && ! $locked->external_ai_consent_revoked_at;
            if (($grant && $active) || (! $grant && ! $unrevoked)) {
                return $locked;
            }
            if ($grant) {
                abort_unless($this->geminiService->externalAiAvailable(), 422);
                $locked->update(['external_ai_consent_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION, 'external_ai_consent_scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE, 'external_ai_consented_at' => now(), 'external_ai_consent_revoked_at' => null, 'lock_version' => $locked->lock_version + 1]);
                ChatMessage::create(['chat_session_id' => $locked->id, 'sender_type' => 'system', 'message' => 'Bạn đã cho phép gửi câu hỏi hiện tại và ngữ cảnh công khai liên quan của KomiBook tới Google Gemini. Lịch sử trò chuyện và hình ảnh không được gửi.', 'metadata' => ['event' => 'external_ai_consent_granted', 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION, 'scope' => GeminiChatService::EXTERNAL_AI_CONSENT_SCOPE]]);
            } else {
                $locked->update(['external_ai_consent_revoked_at' => now(), 'lock_version' => $locked->lock_version + 1]);
                ChatMessage::create(['chat_session_id' => $locked->id, 'sender_type' => 'system', 'message' => 'Bạn đã tắt gửi dữ liệu tới Google Gemini. Trợ lý vẫn trả lời bằng ngữ cảnh nội bộ KomiBook.', 'metadata' => ['event' => 'external_ai_consent_revoked', 'policy_version' => GeminiChatService::EXTERNAL_AI_POLICY_VERSION]]);
            }

            return $locked->fresh();
        });

        return response()->json(['success' => true, 'session' => $this->payload($updated, true)]);
    }

    public function submitFeedback(Request $request, ChatSession $session, ChatMessage $message): JsonResponse
    {
        $this->assertCustomer($session, $this->customer($request));
        abort_unless($message->chat_session_id === $session->id, 404);
        abort_unless($message->sender_type === 'ai', 422, 'Chỉ có thể đánh giá câu trả lời của Trợ lý AI.');
        $data = $request->validate(['feedback' => 'required|in:helpful,unhelpful', 'comment' => 'nullable|string|max:500']);
        $message->update(['feedback' => $data['feedback'], 'feedback_comment' => isset($data['comment']) ? trim($data['comment']) : null]);

        return response()->json(['success' => true, 'message' => $message->fresh()]);
    }

    public function attachment(Request $request, ChatSession $session, ChatMessage $message): StreamedResponse
    {
        abort_unless($message->chat_session_id === $session->id, 404);
        $actor = $request->user('sanctum');
        if ($actor && in_array($actor->role, ['admin', 'vendor'], true)) {
            $this->assertStaffReadable($session, $actor);
        } else {
            $this->assertCustomer($session, $this->customer($request));
        }
        $attachment = $message->metadata['attachment'] ?? null;
        abort_unless(is_array($attachment) && isset($attachment['stored_name']), 404);
        $path = "chat-attachments/{$session->id}/{$attachment['stored_name']}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $attachment['original_name'] ?? 'chat-image', ['Content-Disposition' => 'inline', 'Cache-Control' => 'private, max-age=300']);
    }

    public function requestHuman(Request $request, ChatSession $session): JsonResponse
    {
        $actor = $this->customer($request);
        $this->assertCustomer($session, $actor);
        $data = $request->validate(['target_type' => 'nullable|in:platform,vendor', 'vendor_id' => 'nullable|required_if:target_type,vendor|exists:vendors,id']);
        $target = $data['target_type'] ?? $session->target_type;
        $vendorId = $target === ChatSession::TARGET_VENDOR ? (int) ($data['vendor_id'] ?? $session->vendor_id) : null;
        abort_unless($target === $session->target_type && $vendorId === ($session->vendor_id ? (int) $session->vendor_id : null), 422);
        if ($vendorId) {
            abort_unless(Vendor::withoutGlobalScopes()->whereKey($vendorId)->where('status', 'active')->exists(), 422, 'Gian hàng này hiện không thể nhận hỗ trợ.');
        }
        $session = $this->lifecycleService->requestHuman($session, $actor);

        return response()->json(['success' => true, 'session' => $this->payload($session, true)]);
    }

    public function resumeAi(Request $request, ChatSession $session): JsonResponse
    {
        $actor = $this->customer($request);
        $this->assertCustomer($session, $actor);
        $session = $this->lifecycleService->resumeAi($session, $actor);

        return response()->json(['success' => true, 'session' => $this->payload($session, true)]);
    }

    public function staffSessions(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = ChatSession::query()->with(['user:id,name,email', 'vendor:id,shop_name,slug', 'assignedUser:id,name', 'lastMessage']);
        $this->scopeReadableHumanStates($query);
        $this->scopeStaff($query, $actor);
        $status = $request->query('status');
        if (is_string($status) && in_array($status, [ChatSession::STATUS_QUEUED, ChatSession::STATUS_ASSIGNED, ChatSession::STATUS_WAITING_CUSTOMER, ChatSession::STATUS_RESOLVED, ChatSession::STATUS_CLOSED], true)) {
            $query->where('status', $status);
        }

        return response()->json(['success' => true, 'sessions' => $query->orderByDesc('last_message_at')->paginate(20)]);
    }

    public function staffShow(Request $request, ChatSession $session): JsonResponse
    {
        $this->assertStaffReadable($session, $request->user());

        return response()->json(['success' => true, 'session' => $this->payload($session, true, max(0, $request->integer('after_id')))]);
    }

    public function takeover(Request $request, ChatSession $session): JsonResponse
    {
        $actor = $request->user();
        $this->assertStaff($session, $actor);
        $session = $this->lifecycleService->takeover($session, $actor);

        return response()->json(['success' => true, 'session' => $this->payload($session, true)]);
    }

    public function staffReply(Request $request, ChatSession $session): JsonResponse
    {
        $actor = $request->user();
        $this->assertStaff($session, $actor);
        $data = $request->validate(['message' => 'nullable|string|max:2000|required_without:image', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120|required_without:message']);
        $attachment = null;
        try {
            $session = $this->lifecycleService->staffReply($session, $actor, trim($data['message'] ?? '') ?: 'Đã gửi một hình ảnh.', null, function () use ($request, $session, &$attachment): ?array {
                return $attachment = $this->storeAttachment($request, $session);
            });
        } catch (\Throwable $exception) {
            $this->deleteAttachment($session, $attachment);

            throw $exception;
        }

        return response()->json(['success' => true, 'session' => $this->payload($session, true)]);
    }

    public function close(Request $request, ChatSession $session): JsonResponse
    {
        $actor = $request->user();
        $this->assertStaff($session, $actor);
        $data = $request->validate(['resolution' => 'nullable|string|max:1000']);
        $resolution = trim((string) ($data['resolution'] ?? '')) ?: 'Phiên hỗ trợ đã hoàn tất.';
        $session = $this->lifecycleService->close($session, $actor, $resolution);

        return response()->json(['success' => true, 'session' => $this->payload($session, true)]);
    }

    private function customer(Request $request): User
    {
        $actor = $request->user('sanctum');
        abort_unless($actor instanceof User && $actor->role === 'customer', 403);

        $current = User::query()->findOrFail($actor->id);
        abort_unless($current->role === 'customer', 403);

        return $current;
    }

    private function assertCustomer(ChatSession $session, User $actor): void
    {
        abort_unless($actor->role === 'customer' && $session->user_id !== null && (int) $session->user_id === $actor->id, 403);
    }

    private function assertStaff(ChatSession $session, User $actor): void
    {
        $current = User::query()->findOrFail($actor->id);
        if ($current->role === 'admin') {
            abort_unless($session->target_type === ChatSession::TARGET_PLATFORM && $session->vendor_id === null, 403);

            return;
        }
        $vendor = Vendor::withoutGlobalScopes()->where('user_id', $current->id)->where('status', 'active')->first();
        abort_unless($current->role === 'vendor' && $vendor && $session->target_type === ChatSession::TARGET_VENDOR && (int) $session->vendor_id === $vendor->id, 403);
    }

    private function assertStaffReadable(ChatSession $session, User $actor): void
    {
        abort_unless($this->isReadableHumanSession($session), 403, 'Phiên AI riêng tư chỉ hiển thị sau khi khách yêu cầu gặp nhân viên.');
        $this->assertStaff($session, $actor);
    }

    private function isReadableHumanSession(ChatSession $session): bool
    {
        if ($session->responder_mode !== ChatSession::MODE_HUMAN) {
            return false;
        }

        if ($session->status === ChatSession::STATUS_QUEUED) {
            return $session->assigned_user_id === null;
        }

        return in_array($session->status, [ChatSession::STATUS_ASSIGNED, ChatSession::STATUS_WAITING_CUSTOMER, ChatSession::STATUS_RESOLVED, ChatSession::STATUS_CLOSED], true)
            && $session->assigned_user_id !== null;
    }

    private function scopeReadableHumanStates(Builder $query): void
    {
        $query->where('responder_mode', ChatSession::MODE_HUMAN)->where(function (Builder $states): void {
            $states->where(function (Builder $queued): void {
                $queued->where('status', ChatSession::STATUS_QUEUED)->whereNull('assigned_user_id');
            })->orWhere(function (Builder $owned): void {
                $owned->whereIn('status', [ChatSession::STATUS_ASSIGNED, ChatSession::STATUS_WAITING_CUSTOMER, ChatSession::STATUS_RESOLVED, ChatSession::STATUS_CLOSED])->whereNotNull('assigned_user_id');
            });
        });
    }

    private function scopeStaff(Builder $query, User $actor): void
    {
        $current = User::query()->findOrFail($actor->id);
        if ($current->role === 'admin') {
            $query->where('target_type', ChatSession::TARGET_PLATFORM)->whereNull('vendor_id');

            return;
        }
        $vendor = Vendor::withoutGlobalScopes()->where('user_id', $current->id)->where('status', 'active')->first();
        abort_unless($current->role === 'vendor' && $vendor, 403);
        $query->where('target_type', ChatSession::TARGET_VENDOR)->where('vendor_id', $vendor->id);
    }

    private function storeAttachment(Request $request, ChatSession $session): ?array
    {
        $file = $request->file('image');
        if (! $file) {
            return null;
        } $name = Str::uuid()->toString().'.'.strtolower($file->guessExtension() ?: 'jpg');
        abort_unless($file->storeAs("chat-attachments/{$session->id}", $name, 'local'), 500);

        return ['attachment' => ['stored_name' => $name, 'original_name' => Str::limit($file->getClientOriginalName(), 180, ''), 'mime_type' => (string) $file->getMimeType(), 'size' => (int) $file->getSize()]];
    }

    private function deleteAttachment(ChatSession $session, ?array $metadata): void
    {
        $storedName = $metadata['attachment']['stored_name'] ?? null;
        if (is_string($storedName) && $storedName !== '') {
            try {
                Storage::disk('local')->delete("chat-attachments/{$session->id}/{$storedName}");
            } catch (\Throwable) {
                // Preserve the lifecycle failure; attachment cleanup is best effort.
            }
        }
    }

    private function payload(ChatSession $session, bool $messages = false, int $afterId = 0): array
    {
        $session->loadMissing(['vendor:id,shop_name,slug', 'assignedUser:id,name']);
        $data = ['id' => $session->id, 'target_type' => $session->target_type, 'vendor_id' => $session->vendor_id, 'vendor' => $session->vendor, 'responder_mode' => $session->responder_mode, 'status' => $session->status, 'assigned_user' => $session->assignedUser, 'support_ticket_id' => $session->support_ticket_id, 'subject' => $session->subject, 'category' => $session->category, 'last_message_at' => $session->last_message_at, 'created_at' => $session->created_at, 'external_ai' => $this->geminiService->externalAiPolicyMetadata($session)];
        if ($messages) {
            $query = $session->messages();
            $data['messages'] = $afterId ? $query->where('id', '>', $afterId)->orderBy('id')->limit(200)->get() : $query->orderByDesc('id')->limit(200)->get()->sortBy('id')->values();
        }

        return $data;
    }
}

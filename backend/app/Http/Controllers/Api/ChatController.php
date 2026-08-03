<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ChatSessionAutomationService;
use App\Services\GeminiChatService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    private const GUEST_COOKIE = 'komibook_chat_guest';

    public function __construct(
        private readonly GeminiChatService $geminiService,
        private readonly ChatSessionAutomationService $automationService,
    ) {}

    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'nullable|in:platform,vendor',
            'vendor_id' => 'nullable|required_if:target_type,vendor|exists:vendors,id',
        ]);

        $targetType = $validated['target_type'] ?? ChatSession::TARGET_PLATFORM;
        $vendorId = $targetType === ChatSession::TARGET_VENDOR ? (int) $validated['vendor_id'] : null;

        if ($vendorId !== null && ! Vendor::query()->whereKey($vendorId)->where('status', 'active')->exists()) {
            return response()->json(['message' => 'Gian hàng này hiện không thể nhận yêu cầu hỗ trợ.'], 422);
        }

        $user = $request->user('sanctum');
        [$guestToken, $guestHash, $newGuestToken] = $this->guestIdentity($request, $user);

        $conversationKey = $this->conversationKey($targetType, $vendorId, $user?->id, $guestHash);
        $session = ChatSession::query()->where('conversation_key', $conversationKey)->first();

        if (! $session) {
            $session = ChatSession::create([
                'user_id' => $user?->id,
                'vendor_id' => $vendorId,
                'guest_token_hash' => $user ? null : $guestHash,
                'conversation_key' => $conversationKey,
                'target_type' => $targetType,
                'responder_mode' => ChatSession::MODE_AI,
                'status' => ChatSession::STATUS_OPEN,
                'last_message_at' => now(),
            ]);

            $vendor = $vendorId ? Vendor::query()->find($vendorId) : null;
            $message = $vendor
                ? "Xin chào! Tôi là Trợ lý AI KomiBook đang hỗ trợ cho gian hàng {$vendor->shop_name}. Tôi có thể tư vấn sách của shop hoặc chuyển bạn tới nhân viên gian hàng."
                : 'Xin chào! Tôi là Trợ lý AI KomiBook. Tôi có thể gợi ý sách, tóm tắt bài viết, giải đáp chính sách và chuyển bạn tới tư vấn viên khi cần.';

            ChatMessage::create([
                'chat_session_id' => $session->id,
                'sender_type' => 'ai',
                'message' => $message,
                'metadata' => [
                    'ai_disclosure' => true,
                    'quick_replies' => $vendor
                        ? ['Tư vấn sách của shop', 'Chính sách giao hàng', 'Gặp nhân viên shop']
                        : ['Gợi ý sách theo nhu cầu', 'Hạng VIP và quyền lợi', 'Tóm tắt bài viết', 'Gặp tư vấn viên KomiBook'],
                ],
            ]);
        } elseif ($session->isTerminal()) {
            $session->update([
                'assigned_user_id' => null,
                'responder_mode' => ChatSession::MODE_AI,
                'status' => ChatSession::STATUS_OPEN,
                'assigned_at' => null,
                'resolved_at' => null,
                'last_message_at' => now(),
                'lock_version' => $session->lock_version + 1,
            ]);

            ChatMessage::create([
                'chat_session_id' => $session->id,
                'sender_type' => 'system',
                'message' => 'Cuộc trò chuyện đã được mở lại. Trợ lý AI sẽ hỗ trợ trước cho đến khi nhân viên tiếp nhận.',
                'metadata' => ['event' => 'conversation_reopened', 'ai_disclosure' => true],
            ]);
        }

        $this->automationService->resumeIfIdle($session);

        $response = response()->json([
            'success' => true,
            'session' => $this->sessionPayload($session, true),
        ]);

        return $newGuestToken ? $this->withGuestCookie($response, $guestToken) : $response;
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $query = ChatSession::query()->with(['vendor:id,shop_name,slug', 'lastMessage']);

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $token = $request->cookie(self::GUEST_COOKIE);
            if (! is_string($token) || strlen($token) < 40) {
                return response()->json(['success' => true, 'conversations' => []]);
            }
            $query->whereNull('user_id')->where('guest_token_hash', hash('sha256', $token));
        }

        $sessions = $query->orderByDesc('last_message_at')->get();
        $sessions->each(fn (ChatSession $session) => $this->automationService->resumeIfIdle($session));

        $conversations = $sessions->map(fn (ChatSession $session) => [
            'id' => $session->id,
            'target_type' => $session->target_type,
            'vendor_id' => $session->vendor_id,
            'vendor' => $session->vendor,
            'responder_mode' => $session->responder_mode,
            'status' => $session->status,
            'last_message' => $session->lastMessage,
            'last_message_at' => $session->last_message_at,
        ])->values();

        return response()->json(['success' => true, 'conversations' => $conversations]);
    }

    public function showSession(Request $request, ChatSession $session): JsonResponse
    {
        $this->assertCustomerAccess($request, $session);
        $this->automationService->resumeIfIdle($session);

        $afterId = max(0, $request->integer('after_id'));

        return response()->json([
            'success' => true,
            'session' => $this->sessionPayload($session, true, $afterId),
        ]);
    }

    public function sendMessage(Request $request, ChatSession $session): JsonResponse
    {
        $this->assertCustomerAccess($request, $session);
        abort_if($session->isTerminal(), 409, 'Phiên hỗ trợ đã kết thúc. Hãy mở một phiên mới.');

        $validated = $request->validate([
            'message' => 'nullable|string|max:2000|required_without:image',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120|required_without:message',
            'context_book_id' => 'nullable|integer|min:1',
        ]);
        $user = $request->user('sanctum');

        $this->automationService->resumeIfIdle($session);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'customer',
            'sender_id' => $user?->id,
            'message' => trim($validated['message'] ?? '') ?: 'Đã gửi một hình ảnh.',
            'metadata' => $this->storeAttachment($request, $session),
        ]);

        $session->update([
            'last_message_at' => now(),
            'status' => $session->status === ChatSession::STATUS_WAITING_CUSTOMER
                ? ChatSession::STATUS_ASSIGNED
                : $session->status,
        ]);

        if ($session->responder_mode === ChatSession::MODE_AI && $session->status === ChatSession::STATUS_OPEN) {
            $prompt = trim($validated['message'] ?? '') ?: 'Khách hàng vừa gửi một hình ảnh cần được hỗ trợ.';
            $aiResult = $this->geminiService->generateReply($session, $prompt, $validated['context_book_id'] ?? null);

            ChatMessage::create([
                'chat_session_id' => $session->id,
                'sender_type' => 'ai',
                'message' => $aiResult['message'],
                'metadata' => $aiResult['metadata'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'session' => $this->sessionPayload($session->fresh(), true),
        ]);
    }

    public function submitFeedback(Request $request, ChatSession $session, ChatMessage $message): JsonResponse
    {
        $this->assertCustomerAccess($request, $session);
        abort_unless($message->chat_session_id === $session->id, 404);
        abort_unless($message->sender_type === 'ai', 422, 'Chỉ có thể đánh giá câu trả lời của Trợ lý AI.');

        $validated = $request->validate([
            'feedback' => 'required|in:helpful,unhelpful',
            'comment' => 'nullable|string|max:500',
        ]);

        $message->update([
            'feedback' => $validated['feedback'],
            'feedback_comment' => isset($validated['comment']) ? trim($validated['comment']) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message->fresh(),
        ]);
    }

    public function attachment(Request $request, ChatSession $session, ChatMessage $message): StreamedResponse
    {
        abort_unless($message->chat_session_id === $session->id, 404);

        $user = $request->user('sanctum');
        if ($user && in_array($user->role, ['admin', 'vendor'], true)) {
            $this->assertStaffAccess($user, $session);
        } else {
            $this->assertCustomerAccess($request, $session);
        }

        $attachment = $message->metadata['attachment'] ?? null;
        abort_unless(is_array($attachment) && isset($attachment['stored_name']), 404);

        $path = "chat-attachments/{$session->id}/{$attachment['stored_name']}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $attachment['original_name'] ?? 'chat-image', [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function requestHuman(Request $request, ChatSession $session): JsonResponse
    {
        $this->assertCustomerAccess($request, $session);
        abort_if($session->isTerminal(), 409, 'Phiên hỗ trợ đã kết thúc.');

        $validated = $request->validate([
            'target_type' => 'nullable|in:platform,vendor',
            'vendor_id' => 'nullable|required_if:target_type,vendor|exists:vendors,id',
        ]);

        $targetType = $validated['target_type'] ?? $session->target_type;
        $vendorId = $targetType === ChatSession::TARGET_VENDOR
            ? (int) ($validated['vendor_id'] ?? $session->vendor_id)
            : null;

        abort_if($targetType === ChatSession::TARGET_VENDOR && ! $vendorId, 422, 'Vui lòng chọn gian hàng cần hỗ trợ.');
        abort_if(
            $targetType !== $session->target_type || $vendorId !== ($session->vendor_id ? (int) $session->vendor_id : null),
            422,
            'Mỗi gian hàng có một cuộc trò chuyện riêng. Vui lòng mở đúng cuộc trò chuyện trước khi gặp nhân viên.',
        );
        abort_if($vendorId && ! Vendor::query()->whereKey($vendorId)->where('status', 'active')->exists(), 422, 'Gian hàng này hiện không thể nhận hỗ trợ.');
        abort_if($session->status === ChatSession::STATUS_ASSIGNED, 409, 'Phiên đã được nhân viên tiếp nhận.');

        $session->update([
            'target_type' => $targetType,
            'vendor_id' => $vendorId,
            'responder_mode' => ChatSession::MODE_HUMAN,
            'status' => ChatSession::STATUS_QUEUED,
            'assigned_user_id' => null,
            'assigned_at' => null,
            'last_message_at' => now(),
            'lock_version' => $session->lock_version + 1,
        ]);

        $destination = $targetType === ChatSession::TARGET_VENDOR
            ? Vendor::query()->whereKey($vendorId)->value('shop_name')
            : 'đội ngũ KomiBook';

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'system',
            'message' => "Yêu cầu đã được đưa vào hàng đợi hỗ trợ của {$destination}. AI sẽ tạm dừng trả lời trong khi bạn chờ nhân viên tiếp nhận.",
            'metadata' => ['event' => 'human_requested'],
        ]);

        return response()->json(['success' => true, 'session' => $this->sessionPayload($session->fresh(), true)]);
    }

    public function resumeAi(Request $request, ChatSession $session): JsonResponse
    {
        $this->assertCustomerAccess($request, $session);
        abort_if($session->assigned_user_id !== null || $session->status === ChatSession::STATUS_ASSIGNED, 409, 'Nhân viên đang phụ trách phiên này.');
        abort_if($session->isTerminal(), 409, 'Phiên hỗ trợ đã kết thúc.');

        $session->update([
            'responder_mode' => ChatSession::MODE_AI,
            'status' => ChatSession::STATUS_OPEN,
            'last_message_at' => now(),
            'lock_version' => $session->lock_version + 1,
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'system',
            'message' => 'Trợ lý AI đã được bật lại. Nội dung do AI tạo sẽ luôn được ghi nhãn rõ ràng.',
            'metadata' => ['event' => 'ai_resumed', 'ai_disclosure' => true],
        ]);

        return response()->json(['success' => true, 'session' => $this->sessionPayload($session->fresh(), true)]);
    }

    public function staffSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ChatSession::query()->with(['user:id,name,email', 'vendor:id,shop_name,slug', 'assignedUser:id,name', 'lastMessage']);
        $this->scopeStaffQuery($query, $user);

        $status = $request->query('status');
        if (is_string($status) && in_array($status, [
            ChatSession::STATUS_OPEN,
            ChatSession::STATUS_QUEUED,
            ChatSession::STATUS_ASSIGNED,
            ChatSession::STATUS_WAITING_CUSTOMER,
            ChatSession::STATUS_RESOLVED,
            ChatSession::STATUS_CLOSED,
        ], true)) {
            $query->where('status', $status);
        }

        $sessions = $query->orderByDesc('last_message_at')->paginate(20);
        $sessions->getCollection()->each(fn (ChatSession $session) => $this->automationService->resumeIfIdle($session));

        return response()->json(['success' => true, 'sessions' => $sessions]);
    }

    public function staffShow(Request $request, ChatSession $session): JsonResponse
    {
        $this->assertStaffAccess($request->user(), $session);
        $this->automationService->resumeIfIdle($session);

        return response()->json([
            'success' => true,
            'session' => $this->sessionPayload($session, true, max(0, $request->integer('after_id'))),
        ]);
    }

    public function takeover(Request $request, ChatSession $session): JsonResponse
    {
        $user = $request->user();
        $this->assertStaffAccess($user, $session);

        $session = DB::transaction(function () use ($session, $user) {
            $locked = ChatSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_if($locked->isTerminal(), 409, 'Phiên hỗ trợ đã kết thúc.');
            abort_if($locked->assigned_user_id && $locked->assigned_user_id !== $user->id, 409, 'Phiên đã được nhân viên khác tiếp nhận.');

            if (! $locked->assigned_user_id) {
                $locked->update([
                    'assigned_user_id' => $user->id,
                    'assigned_at' => now(),
                    'responder_mode' => ChatSession::MODE_HUMAN,
                    'status' => ChatSession::STATUS_ASSIGNED,
                    'lock_version' => $locked->lock_version + 1,
                    'last_message_at' => now(),
                ]);

                ChatMessage::create([
                    'chat_session_id' => $locked->id,
                    'sender_type' => $user->role === 'admin' ? 'admin' : 'vendor',
                    'sender_id' => $user->id,
                    'message' => "Xin chào! Tôi là {$user->name} và đã tiếp nhận cuộc trò chuyện này.",
                    'metadata' => ['event' => 'staff_takeover'],
                ]);
            }

            return $locked->fresh();
        });

        return response()->json(['success' => true, 'session' => $this->sessionPayload($session, true)]);
    }

    public function staffReply(Request $request, ChatSession $session): JsonResponse
    {
        $user = $request->user();
        $this->assertStaffAccess($user, $session);
        abort_unless($session->assigned_user_id === $user->id, 403, 'Bạn cần tiếp nhận phiên trước khi trả lời.');
        abort_if($session->isTerminal(), 409, 'Phiên hỗ trợ đã kết thúc.');

        $validated = $request->validate([
            'message' => 'nullable|string|max:2000|required_without:image',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120|required_without:message',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => $user->role === 'admin' ? 'admin' : 'vendor',
            'sender_id' => $user->id,
            'message' => trim($validated['message'] ?? '') ?: 'Đã gửi một hình ảnh.',
            'metadata' => $this->storeAttachment($request, $session),
        ]);

        $session->update([
            'status' => ChatSession::STATUS_WAITING_CUSTOMER,
            'last_message_at' => now(),
        ]);

        return response()->json(['success' => true, 'session' => $this->sessionPayload($session->fresh(), true)]);
    }

    public function close(Request $request, ChatSession $session): JsonResponse
    {
        $user = $request->user();
        $this->assertStaffAccess($user, $session);
        abort_unless($session->assigned_user_id === $user->id, 403, 'Bạn cần tiếp nhận phiên trước khi hoàn tất.');

        $validated = $request->validate(['resolution' => 'nullable|string|max:1000']);
        $session->update([
            'status' => ChatSession::STATUS_RESOLVED,
            'resolved_at' => now(),
            'last_message_at' => now(),
            'lock_version' => $session->lock_version + 1,
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'system',
            'message' => $validated['resolution'] ?? 'Phiên hỗ trợ đã được đánh dấu hoàn tất. Cảm ơn bạn đã liên hệ KomiBook.',
            'metadata' => ['event' => 'resolved'],
        ]);

        return response()->json(['success' => true, 'session' => $this->sessionPayload($session->fresh(), true)]);
    }

    private function assertCustomerAccess(Request $request, ChatSession $session): void
    {
        $user = $request->user('sanctum');
        if ($user && $session->user_id === $user->id) {
            return;
        }

        $token = $request->cookie(self::GUEST_COOKIE);
        if (! $session->user_id && $token && hash_equals((string) $session->guest_token_hash, hash('sha256', $token))) {
            if ($user) {
                $session->update(['user_id' => $user->id, 'guest_token_hash' => null]);
            }

            return;
        }

        abort(403, 'Bạn không có quyền truy cập phiên hỗ trợ này.');
    }

    private function assertStaffAccess(User $user, ChatSession $session): void
    {
        if ($user->role === 'admin') {
            abort_unless($session->target_type === ChatSession::TARGET_PLATFORM, 403, 'Phiên này thuộc phạm vi hỗ trợ của gian hàng.');

            return;
        }

        abort_unless($user->role === 'vendor' && $user->vendor, 403);
        abort_unless($session->target_type === ChatSession::TARGET_VENDOR && $session->vendor_id === $user->vendor->id, 403);
    }

    private function scopeStaffQuery(Builder $query, User $user): void
    {
        if ($user->role === 'admin') {
            $query->where('target_type', ChatSession::TARGET_PLATFORM)->whereNull('vendor_id');

            return;
        }

        abort_unless($user->role === 'vendor' && $user->vendor, 403);
        $query->where('target_type', ChatSession::TARGET_VENDOR)->where('vendor_id', $user->vendor->id);
    }

    /** @return array{0: string|null, 1: string|null, 2: bool} */
    private function guestIdentity(Request $request, ?User $user): array
    {
        if ($user) {
            return [null, null, false];
        }

        $token = $request->cookie(self::GUEST_COOKIE);
        $isNew = ! is_string($token) || strlen($token) < 40;
        $token = $isNew ? Str::random(64) : $token;

        return [$token, hash('sha256', $token), $isNew];
    }

    private function withGuestCookie(JsonResponse $response, string $token): JsonResponse
    {
        $response->headers->setCookie(Cookie::create(
            self::GUEST_COOKIE,
            $token,
            now()->addDays(30),
            '/',
            null,
            app()->environment('production'),
            true,
            false,
            Cookie::SAMESITE_LAX,
        ));

        return $response;
    }

    /** @return array{attachment: array{stored_name: string, original_name: string, mime_type: string, size: int}}|null */
    private function storeAttachment(Request $request, ChatSession $session): ?array
    {
        $file = $request->file('image');
        if (! $file) {
            return null;
        }

        $extension = strtolower($file->guessExtension() ?: 'jpg');
        $storedName = Str::uuid()->toString().'.'.$extension;
        $storedPath = $file->storeAs("chat-attachments/{$session->id}", $storedName, 'local');
        abort_if($storedPath === false, 500, 'Không thể lưu hình ảnh. Vui lòng thử lại.');

        return ['attachment' => [
            'stored_name' => $storedName,
            'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ]];
    }

    private function conversationKey(string $targetType, ?int $vendorId, ?int $userId, ?string $guestHash): string
    {
        $identity = $userId ? 'user:'.$userId : 'guest:'.$guestHash;
        $scope = $targetType === ChatSession::TARGET_VENDOR ? 'vendor:'.$vendorId : 'platform';

        return $identity.':'.$scope;
    }

    private function sessionPayload(ChatSession $session, bool $includeMessages = false, int $afterId = 0): array
    {
        $session->loadMissing(['vendor:id,shop_name,slug', 'assignedUser:id,name']);

        $payload = [
            'id' => $session->id,
            'target_type' => $session->target_type,
            'vendor_id' => $session->vendor_id,
            'vendor' => $session->vendor,
            'responder_mode' => $session->responder_mode,
            'status' => $session->status,
            'assigned_user' => $session->assignedUser,
            'support_ticket_id' => $session->support_ticket_id,
            'subject' => $session->subject,
            'category' => $session->category,
            'last_message_at' => $session->last_message_at,
            'created_at' => $session->created_at,
            'human_idle_timeout_minutes' => ChatSessionAutomationService::HUMAN_REPLY_IDLE_MINUTES,
        ];

        if ($includeMessages) {
            $messages = $session->messages();
            if ($afterId > 0) {
                $payload['messages'] = $messages->where('id', '>', $afterId)->orderBy('id')->limit(200)->get();
            } else {
                $payload['messages'] = $messages->orderByDesc('id')->limit(200)->get()->sortBy('id')->values();
            }
        }

        return $payload;
    }
}

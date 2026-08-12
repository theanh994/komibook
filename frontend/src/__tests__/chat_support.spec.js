import { beforeEach, describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { createPinia, setActivePinia } from 'pinia'
import apiClient from '../services/axios'
import { useChatStore } from '../stores/chatStore'
import { userSafeApiError } from '../utils/apiError'
import { clearStaffSessionState, isStaffSessionAccessLoss } from '../views/vendor/CustomerSupportView.vue'

const sessionPayload = overrides => ({
  id: 41,
  target_type: 'platform',
  vendor_id: null,
  responder_mode: 'ai',
  status: 'open',
  assigned_user: null,
  external_ai: { available: true, consented: false, required: true, version: '2026-08-09.1', scope: ['current_message', 'public_grounding_context'] },
  messages: [{ id: 1, sender_type: 'ai', message: 'Xin chào' }],
  ...overrides,
})

describe('Unified chat support store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.restoreAllMocks()
    vi.useFakeTimers()
  })

  it('opens an explicitly scoped vendor conversation without a client-side session token', async () => {
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { session: sessionPayload({ target_type: 'vendor', vendor_id: 9 }) } })
    const store = useChatStore()

    await store.openChatWithVendor(9, 'Nhà sách Xanh')

    expect(post).toHaveBeenCalledWith('/api/chat/sessions', { target_type: 'vendor', vendor_id: 9 })
    expect(store.vendorId).toBe(9)
    expect(store.vendorName).toBe('Nhà sách Xanh')
    expect(store.$state).not.toHaveProperty('sessionToken')
    store.stopPolling()
  })

  it('uses resource-scoped endpoints for messages and human handoff', async () => {
    const post = vi.spyOn(apiClient, 'post')
      .mockResolvedValueOnce({ data: { session: sessionPayload() } })
      .mockResolvedValueOnce({ data: { session: sessionPayload({ messages: [{ id: 2, sender_type: 'customer', message: 'Cần giúp' }] }) } })
      .mockResolvedValueOnce({ data: { session: sessionPayload({ responder_mode: 'human', status: 'queued' }) } })
    const store = useChatStore()

    await store.openChat()
    await store.sendMessage('Cần giúp')
    await store.requestHumanSupport()

    expect(post).toHaveBeenNthCalledWith(2, '/api/chat/sessions/41/messages', { message: 'Cần giúp' })
    expect(post).toHaveBeenNthCalledWith(3, '/api/chat/sessions/41/request-human', { target_type: 'platform', vendor_id: null })
    expect(store.status).toBe('queued')
    store.stopPolling()
  })

  it('sends the current book context when chat starts from a book detail page', async () => {
    const post = vi.spyOn(apiClient, 'post')
      .mockResolvedValueOnce({ data: { session: sessionPayload({ target_type: 'vendor', vendor_id: 9 }) } })
      .mockResolvedValueOnce({ data: { session: sessionPayload({ target_type: 'vendor', vendor_id: 9 }) } })
    vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { conversations: [] } })
    const store = useChatStore()

    await store.openChatWithVendor(9, 'Nhà sách Xanh', { id: 86, title: '86 - Tập 1' })
    await store.sendMessage('Sách này có bao nhiêu tập?')

    expect(post).toHaveBeenNthCalledWith(2, '/api/chat/sessions/41/messages', {
      message: 'Sách này có bao nhiêu tập?',
      context_book_id: 86,
    })
    expect(store.contextBookName).toBe('86 - Tập 1')
    store.stopPolling()
  })

  it('merges polling deltas without duplicating existing messages', async () => {
    vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { session: sessionPayload() } })
    const get = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { session: sessionPayload({ messages: [{ id: 2, sender_type: 'admin', message: 'Đã tiếp nhận' }] }) } })
    const store = useChatStore()

    await store.openChat()
    await store.refreshMessages()
    await store.refreshMessages()

    expect(get).toHaveBeenCalledWith('/api/chat/sessions/41', { params: { after_id: 1 } })
    expect(store.messages.map(message => message.id)).toEqual([1, 2])
    store.stopPolling()
  })

  it('records AI feedback through the scoped message endpoint', async () => {
    const post = vi.spyOn(apiClient, 'post')
      .mockResolvedValueOnce({ data: { session: sessionPayload() } })
      .mockResolvedValueOnce({ data: { message: { id: 1, sender_type: 'ai', message: 'Xin chào', feedback: 'helpful' } } })
    const store = useChatStore()

    await store.openChat()
    await store.submitFeedback(1, 'helpful')

    expect(post).toHaveBeenNthCalledWith(2, '/api/chat/sessions/41/messages/1/feedback', { feedback: 'helpful' })
    expect(store.messages[0].feedback).toBe('helpful')
    store.stopPolling()
  })

  it('lists one KomiBook thread and separate persistent vendor threads', async () => {
    const conversations = [
      { id: 1, target_type: 'platform', vendor_id: null, last_message: { message: 'Xin chào' } },
      { id: 2, target_type: 'vendor', vendor_id: 9, vendor: { shop_name: 'Nhà sách Xanh' }, last_message: { message: 'Shop đang hỗ trợ' } },
    ]
    const get = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { conversations } })
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { session: sessionPayload({ id: 2, target_type: 'vendor', vendor_id: 9 }) } })
    const store = useChatStore()

    await store.openConversationList()
    await store.selectConversation(conversations[1])

    expect(get).toHaveBeenCalledWith('/api/chat/conversations')
    expect(post).toHaveBeenCalledWith('/api/chat/sessions', { target_type: 'vendor', vendor_id: 9 })
    expect(store.showConversationList).toBe(false)
    expect(store.vendorName).toBe('Nhà sách Xanh')
    store.stopPolling()
  })

  it('does not expose the removed ticket creation action', () => {
    const store = useChatStore()

    expect(store.createTicket).toBeUndefined()
  })

  it('only exposes AI resume for an unassigned human queue', () => {
    const store = useChatStore()
    store.applySession(sessionPayload({ status: 'queued', responder_mode: 'human', assigned_user: null }), true)
    expect(store.isQueued).toBe(true)
    store.applySession(sessionPayload({ status: 'queued', responder_mode: 'human', assigned_user: { id: 7 } }), true)
    expect(store.isQueued).toBe(false)
    store.applySession(sessionPayload({ status: 'queued', responder_mode: 'ai', assigned_user: null }), true)
    expect(store.isQueued).toBe(false)
    store.stopPolling()
  })

  it('lets the customer extend only a canonical waiting-customer deadline', async () => {
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({
      data: {
        session: sessionPayload({
          status: 'waiting_customer',
          responder_mode: 'human',
          assigned_user: { id: 7 },
          auto_resume_at: '2026-08-12T10:30:00.000Z',
          human_idle_timeout_minutes: 30,
        }),
      },
    })
    const store = useChatStore()
    store.applySession(sessionPayload({
      status: 'waiting_customer',
      responder_mode: 'human',
      assigned_user: { id: 7 },
      auto_resume_at: '2026-08-12T10:00:00.000Z',
    }), true)

    expect(store.canExtendHumanWait).toBe(true)
    await store.extendHumanWait()

    expect(post).toHaveBeenCalledWith('/api/chat/sessions/41/extend-human-wait')
    expect(store.session.auto_resume_at).toBe('2026-08-12T10:30:00.000Z')
    store.applySession(sessionPayload({ status: 'assigned', responder_mode: 'human', assigned_user: { id: 7 }, auto_resume_at: null }), true)
    expect(store.canExtendHumanWait).toBe(false)
    store.applySession(sessionPayload({ status: 'waiting_customer', responder_mode: 'human', assigned_user: { id: 7 }, auto_resume_at: 'invalid' }), true)
    expect(store.canExtendHumanWait).toBe(false)
  })

  it('fails closed for queued and malformed customer send states', async () => {
    const post = vi.spyOn(apiClient, 'post')
    const store = useChatStore()
    expect(store.canSendMessage).toBe(false)
    store.applySession(sessionPayload({ status: 'queued', responder_mode: 'human', assigned_user: null }), true)
    expect(store.canSendMessage).toBe(false)
    await store.sendMessage('must not send')
    store.applySession(sessionPayload({ status: 'assigned', responder_mode: 'human', assigned_user: null }), true)
    expect(store.canSendMessage).toBe(false)
    await store.sendMessage('must not send')
    expect(post).not.toHaveBeenCalled()
    store.applySession(sessionPayload({ status: 'open', responder_mode: 'ai', assigned_user: null }), true)
    expect(store.canSendMessage).toBe(true)
    store.applySession(sessionPayload({ status: 'open', responder_mode: 'ai', assigned_user: { id: 7 } }), true)
    expect(store.isAiActive).toBe(false)
    expect(store.canSendMessage).toBe(false)
    store.applySession(sessionPayload({ status: 'assigned', responder_mode: 'human', assigned_user: { id: 7 } }), true)
    expect(store.canSendMessage).toBe(true)
    store.stopPolling()
  })

  it('does not fabricate a canonical state when no session exists', () => {
    const store = useChatStore()

    expect(store.status).toBeNull()
    expect(store.responderMode).toBeNull()
    expect(store.isAiActive).toBe(false)
    expect(store.isQueued).toBe(false)
    expect(store.isHumanActive).toBe(false)
    expect(store.isTerminal).toBe(false)
    expect(store.canSendMessage).toBe(false)
  })

  it('clears sensitive chat state when a session payload is null', () => {
    const store = useChatStore()
    store.applySession(sessionPayload(), true)
    store.sending = true
    store.feedbackPending = { 1: true }
    store.error = 'stale sensitive state'

    store.applySession(null, true)

    expect(store.session).toBeNull()
    expect(store.messages).toEqual([])
    expect(store.sending).toBe(false)
    expect(store.feedbackPending).toEqual({})
    expect(store.error).toBe('')
    expect(store.pollTimer).toBeNull()
  })

  it('clears customer transcript only for authorization or missing-session poll failures', async () => {
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { session: sessionPayload() } })
    const get = vi.spyOn(apiClient, 'get').mockRejectedValue({ response: { status: 403 } })
    const store = useChatStore()

    await store.openChat()
    store.error = 'stale sensitive state'
    await store.refreshMessages()

    expect(post).toHaveBeenCalledOnce()
    expect(get).toHaveBeenCalledOnce()
    expect(store.session).toBeNull()
    expect(store.messages).toEqual([])
    expect(store.error).toBe('')
    expect(store.pollTimer).toBeNull()
  })

  it('keeps the staff view out of private AI-only conversations', () => {
    const staff = readFileSync(new URL('../views/vendor/CustomerSupportView.vue', import.meta.url), 'utf8')
    expect(staff).not.toContain("{ value: 'open'")
    expect(staff).toContain("selected.value?.status === 'queued'")
    expect(staff).toContain('khách hàng đã chủ động yêu cầu gặp nhân viên')
    expect(staff).not.toContain('Theo dõi cả hội thoại AI đang hỗ trợ')
    expect(staff).not.toContain('30 phút')
    expect(staff).toContain("selected.value?.responder_mode === 'human'")
    const widget = readFileSync(new URL('../components/chat/ChatWidget.vue', import.meta.url), 'utf8')
    expect(widget).toContain("chatStore.canSendMessage ? 'Nhập câu hỏi của bạn…' : 'Phiên chưa sẵn sàng nhận tin nhắn'")
    expect(widget).toContain("conversation.responder_mode === 'ai' && unassigned")
    expect(widget).toContain("conversation.responder_mode === 'human' && assigned")
    expect(widget).toContain("return 'Trạng thái phiên chưa xác định'")
    expect(widget).toContain("if (!chatStore.session) return 'Đang khởi tạo phiên hỗ trợ'")
    expect(widget).toContain('v-if="!chatStore.showConversationList && chatStore.session && chatStore.isAiActive && showNoticeBanner"')
    expect(widget).toContain("if (chatStore.isAiActive) return 'Trợ lý AI tự động'")
    expect(widget).toContain('v-if="chatStore.canExtendHumanWait"')
    expect(widget).toContain('Tiếp tục chờ tư vấn viên thêm')
    expect(widget).toContain('min-h-11')
  })

  it('requires an explicit consent action and never adds it to session creation or sending', async () => {
    const post = vi.spyOn(apiClient, 'post')
      .mockResolvedValueOnce({ data: { session: sessionPayload() } })
      .mockResolvedValueOnce({ data: { session: sessionPayload({ messages: [{ id: 2, sender_type: 'customer', message: 'Cần giúp' }] }) } })
    const store = useChatStore()

    await store.openChat()
    await store.sendMessage('Cần giúp')

    expect(post).toHaveBeenNthCalledWith(1, '/api/chat/sessions', { target_type: 'platform', vendor_id: null })
    expect(post).toHaveBeenNthCalledWith(2, '/api/chat/sessions/41/messages', { message: 'Cần giúp' })
    expect(post).not.toHaveBeenCalledWith('/api/chat/sessions/41/external-ai-consent', expect.anything())
    expect(store.hasExternalAiConsent).toBe(false)
    store.stopPolling()
  })

  it('grants and revokes Google Gemini only through the explicit consent endpoint', async () => {
    const post = vi.spyOn(apiClient, 'post')
      .mockResolvedValueOnce({ data: { session: sessionPayload() } })
      .mockResolvedValueOnce({ data: { session: sessionPayload({ external_ai: { ...sessionPayload().external_ai, consented: true } }) } })
      .mockResolvedValueOnce({ data: { session: sessionPayload() } })
    const store = useChatStore()

    await store.openChat()
    await store.setExternalAiConsent(true)
    await store.setExternalAiConsent(false)

    expect(post).toHaveBeenNthCalledWith(2, '/api/chat/sessions/41/external-ai-consent', { consent: true, policy_version: '2026-08-09.1' })
    expect(post).toHaveBeenNthCalledWith(3, '/api/chat/sessions/41/external-ai-consent', { consent: false, policy_version: '2026-08-09.1' })
    expect(store.hasExternalAiConsent).toBe(false)
    store.stopPolling()
  })

  it('clears conversation state when the active account changes or logs out', async () => {
    vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { session: sessionPayload() } })
    const store = useChatStore()
    await store.openChat()

    store.resetChat()

    expect(store.isOpen).toBe(false)
    expect(store.session).toBeNull()
    expect(store.messages).toEqual([])
    expect(store.pollTimer).toBeNull()
  })

  it('keeps Gemini consent available through compact progressive disclosure', () => {
    const widget = readFileSync(new URL('../components/chat/ChatWidget.vue', import.meta.url), 'utf8')

    expect(widget).not.toContain('Tùy chọn Google Gemini')
    expect(widget).not.toContain('<aside v-if="chatStore.session?.external_ai"')
    expect(widget).toContain('aria-label="Cài đặt Google Gemini"')
    expect(widget).toContain(':aria-expanded="showGeminiSettings"')
    expect(widget).toContain('v-if="showGeminiSettings && !chatStore.showConversationList && chatStore.session?.external_ai"')
    expect(widget).toContain('Chỉ câu hỏi hiện tại và ngữ cảnh công khai liên quan có thể được gửi')
    expect(widget).toContain('Lịch sử trò chuyện và hình ảnh không được gửi')
    expect(widget).toContain('Bật Google Gemini cho phiên này')
    expect(widget).toContain('Tắt Google Gemini')
    expect(widget).toContain('chatStore.setExternalAiConsent(true)')
    expect(widget).toContain('chatStore.setExternalAiConsent(false)')
    expect(widget).toContain('v-if="chatStore.hasExternalAiConsent"')
    expect(widget).toContain('v-else-if="chatStore.externalAi.available"')
    expect(widget).toContain('(chatStore.externalAi.available || chatStore.hasExternalAiConsent)')
  })

  it('clears the staff transcript when the selected session disappears or is no longer authorized', () => {
    const staff = readFileSync(new URL('../views/vendor/CustomerSupportView.vue', import.meta.url), 'utf8')

    expect(staff).toContain('const clearSelectedSession = () => {')
    expect(staff).toContain('else clearSelectedSession()')
    expect(staff).toContain('if (isStaffSessionAccessLoss(requestError.response?.status)) clearSelectedSession()')
    expect(staff).toContain('messages.value = []')
    expect(staff).toContain("replyText.value = ''")
    expect(staff).toContain('clearSelectedImage()')
  })

  it('clears every staff-private field only for authorization or missing-session failures', () => {
    const selected = { value: { id: 41 } }
    const messages = { value: [{ id: 1, message: 'private transcript' }] }
    const replyText = { value: 'private reply' }
    const selectedImage = { value: { name: 'private.png' } }
    const actionLoading = { value: true }
    const replyFileInput = { value: { value: 'private.png' } }

    expect(isStaffSessionAccessLoss(401)).toBe(true)
    expect(isStaffSessionAccessLoss(403)).toBe(true)
    expect(isStaffSessionAccessLoss(404)).toBe(true)
    expect(isStaffSessionAccessLoss(500)).toBe(false)
    expect(isStaffSessionAccessLoss(undefined)).toBe(false)

    clearStaffSessionState({ selected, messages, replyText, selectedImage, actionLoading, replyFileInput })

    expect(selected.value).toBeNull()
    expect(messages.value).toEqual([])
    expect(replyText.value).toBe('')
    expect(selectedImage.value).toBeNull()
    expect(actionLoading.value).toBe(false)
    expect(replyFileInput.value.value).toBe('')
  })

  it('never renders database details returned by a failed API request', () => {
    const error = {
      response: {
        status: 500,
        data: { message: "SQLSTATE[42S22]: Unknown column 'target_type' in where clause" },
      },
    }

    expect(userSafeApiError(error, 'Khu vực hỗ trợ đang được cập nhật.')).toBe('Khu vực hỗ trợ đang được cập nhật.')
  })
})

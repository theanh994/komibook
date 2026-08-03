import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import apiClient from '../services/axios'
import { useChatStore } from '../stores/chatStore'
import { userSafeApiError } from '../utils/apiError'

const sessionPayload = overrides => ({
  id: 41,
  target_type: 'platform',
  vendor_id: null,
  responder_mode: 'ai',
  status: 'open',
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

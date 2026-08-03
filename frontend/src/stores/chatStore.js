import { defineStore } from 'pinia'
import apiClient from '@/services/axios'
import { userSafeApiError } from '@/utils/apiError'

export const useChatStore = defineStore('chat', {
  state: () => ({
    isOpen: false,
    session: null,
    messages: [],
    conversations: [],
    showConversationList: false,
    conversationsLoading: false,
    loading: false,
    sending: false,
    feedbackPending: {},
    error: '',
    targetType: 'platform',
    vendorId: null,
    vendorName: null,
    contextBookId: null,
    contextBookName: null,
    pollTimer: null,
  }),

  getters: {
    status: state => state.session?.status || 'open',
    responderMode: state => state.session?.responder_mode || 'ai',
    isAiActive() { return this.responderMode === 'ai' && this.status === 'open' },
    isQueued() { return this.status === 'queued' },
    isHumanActive() { return ['assigned', 'waiting_customer'].includes(this.status) },
    isTerminal() { return ['resolved', 'closed'].includes(this.status) },
    lastMessageId: state => state.messages.reduce((max, message) => Math.max(max, Number(message.id) || 0), 0),
  },

  actions: {
    resetChat() {
      this.stopPolling()
      this.isOpen = false
      this.session = null
      this.messages = []
      this.conversations = []
      this.showConversationList = false
      this.error = ''
      this.targetType = 'platform'
      this.vendorId = null
      this.vendorName = null
      this.contextBookId = null
      this.contextBookName = null
    },

    async toggleChat() {
      if (this.isOpen) {
        this.closeChat()
        return
      }
      await this.openChat()
    },

    async openChat() {
      await this.openForTarget('platform', null, null, null)
    },

    async openConversationList() {
      this.isOpen = true
      this.showConversationList = true
      this.error = ''
      this.stopPolling()
      await this.loadConversations()
    },

    async openChatWithVendor(vendorId, vendorName = 'Gian hàng', contextBook = null) {
      await this.openForTarget('vendor', Number(vendorId), vendorName, contextBook)
    },

    async openForTarget(targetType, vendorId, vendorName, contextBook = null) {
      const targetChanged = this.targetType !== targetType || this.vendorId !== vendorId
      this.targetType = targetType
      this.vendorId = vendorId
      this.vendorName = vendorName
      this.contextBookId = contextBook?.id ? Number(contextBook.id) : null
      this.contextBookName = contextBook?.title || null
      this.isOpen = true
      this.showConversationList = false
      this.error = ''

      if (targetChanged) {
        this.session = null
        this.messages = []
      }

      if (!this.session || this.isTerminal) await this.fetchOrCreateSession()
      this.startPolling()
    },

    async loadConversations() {
      this.conversationsLoading = true
      this.error = ''
      try {
        const response = await apiClient.get('/api/chat/conversations')
        this.conversations = response.data.conversations || []
      } catch (error) {
        this.error = userSafeApiError(error, 'Không thể tải lịch sử trò chuyện. Vui lòng thử lại.')
      } finally {
        this.conversationsLoading = false
      }
    },

    async selectConversation(conversation) {
      await this.openForTarget(
        conversation.target_type,
        conversation.vendor_id ? Number(conversation.vendor_id) : null,
        conversation.vendor?.shop_name || null,
        null,
      )
    },

    closeChat() {
      this.isOpen = false
      this.stopPolling()
    },

    startPolling() {
      this.stopPolling()
      this.pollTimer = globalThis.setInterval(() => {
        if (this.isOpen && !this.showConversationList && this.session && !this.sending) this.refreshMessages()
      }, 5000)
    },

    stopPolling() {
      if (this.pollTimer) globalThis.clearInterval(this.pollTimer)
      this.pollTimer = null
    },

    async fetchOrCreateSession() {
      this.loading = true
      this.error = ''
      try {
        const response = await apiClient.post('/api/chat/sessions', {
          target_type: this.targetType,
          vendor_id: this.vendorId,
        })
        this.applySession(response.data.session, true)
      } catch (error) {
        this.error = userSafeApiError(error, 'Khu vực hỗ trợ đang được cập nhật. Vui lòng thử lại sau ít phút.')
      } finally {
        this.loading = false
      }
    },

    async refreshMessages() {
      if (!this.session) return
      try {
        const response = await apiClient.get(`/api/chat/sessions/${this.session.id}`, {
          params: { after_id: this.lastMessageId },
        })
        this.applySession(response.data.session, false)
      } catch (error) {
        if ([401, 403, 404].includes(error.response?.status)) this.stopPolling()
      }
    },

    applySession(session, replaceMessages) {
      if (!session) return
      const incoming = Array.isArray(session.messages) ? session.messages : []
      this.session = { ...session, messages: undefined }
      if (replaceMessages) {
        this.messages = incoming
      } else if (incoming.length) {
        const known = new Set(this.messages.map(message => message.id))
        this.messages.push(...incoming.filter(message => !known.has(message.id)))
      }
    },

    async sendMessage(text, image = null) {
      const message = text?.trim()
      if ((!message && !image) || !this.session || this.sending || this.isTerminal) return false
      this.sending = true
      this.error = ''
      try {
        let payload
        if (image) {
          payload = new FormData()
          if (message) payload.append('message', message)
          payload.append('image', image)
          if (this.contextBookId) payload.append('context_book_id', String(this.contextBookId))
        } else {
          payload = { message }
          if (this.contextBookId) payload.context_book_id = this.contextBookId
        }
        const response = await apiClient.post(`/api/chat/sessions/${this.session.id}/messages`, payload)
        this.applySession(response.data.session, true)
        await this.loadConversations()
        return true
      } catch (error) {
        this.error = userSafeApiError(error, 'Không thể gửi tin nhắn. Vui lòng thử lại.')
        return false
      } finally {
        this.sending = false
      }
    },

    async submitFeedback(messageId, feedback) {
      if (!this.session || this.feedbackPending[messageId]) return false
      this.feedbackPending[messageId] = true
      this.error = ''
      try {
        const response = await apiClient.post(`/api/chat/sessions/${this.session.id}/messages/${messageId}/feedback`, { feedback })
        const index = this.messages.findIndex(message => message.id === messageId)
        if (index !== -1) this.messages[index] = response.data.message
        return true
      } catch (error) {
        this.error = userSafeApiError(error, 'Không thể ghi nhận đánh giá lúc này.')
        return false
      } finally {
        delete this.feedbackPending[messageId]
      }
    },

    async requestHumanSupport() {
      if (!this.session || this.sending || this.isTerminal) return
      this.sending = true
      this.error = ''
      try {
        const response = await apiClient.post(`/api/chat/sessions/${this.session.id}/request-human`, {
          target_type: this.targetType,
          vendor_id: this.vendorId,
        })
        this.applySession(response.data.session, true)
      } catch (error) {
        this.error = userSafeApiError(error, 'Không thể chuyển tới nhân viên.')
      } finally {
        this.sending = false
      }
    },

    async resumeAi() {
      if (!this.session || this.sending) return
      this.sending = true
      this.error = ''
      try {
        const response = await apiClient.post(`/api/chat/sessions/${this.session.id}/resume-ai`)
        this.applySession(response.data.session, true)
      } catch (error) {
        this.error = userSafeApiError(error, 'Không thể bật lại trợ lý AI.')
      } finally {
        this.sending = false
      }
    },

  },
})

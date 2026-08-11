<template>
  <div class="ui-page space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-sm font-bold text-secondary">{{ authStore.isAdmin ? 'KOMIBOOK SUPPORT' : 'HỖ TRỢ GIAN HÀNG' }}</p>
        <h1 class="mt-1 flex items-center gap-2 text-3xl font-black text-on-surface"><span class="material-symbols-outlined text-brand-green-strong" aria-hidden="true">support_agent</span>Hỗ trợ khách hàng</h1>
        <p class="mt-2 max-w-3xl text-base text-on-surface-variant">Chỉ các phiên mà khách hàng đã chủ động yêu cầu gặp nhân viên mới xuất hiện ở đây. Khi bạn tiếp nhận, quyền hỗ trợ của nhân viên được giữ nguyên cho đến khi có một chuyển trạng thái được ủy quyền. {{ authStore.isAdmin ? 'Bạn chỉ thấy các phiên đã bàn giao cho hỗ trợ nền tảng KomiBook.' : 'Bạn chỉ thấy các phiên đã bàn giao đúng tới gian hàng của mình.' }}</p>
      </div>
      <button type="button" class="ui-btn ui-btn-secondary" :disabled="loading" @click="loadSessions"><i class="pi pi-refresh" :class="{ 'pi-spin': loading }" aria-hidden="true"></i>Làm mới</button>
    </header>

    <section class="grid gap-3 sm:grid-cols-3" aria-label="Tổng quan hàng đợi">
      <div class="ui-panel"><p class="text-sm font-semibold text-on-surface-variant">Đang chờ</p><p class="mt-1 text-3xl font-black tabular-nums text-secondary">{{ counts.queued }}</p></div>
      <div class="ui-panel"><p class="text-sm font-semibold text-on-surface-variant">Đang hỗ trợ</p><p class="mt-1 text-3xl font-black tabular-nums text-brand-green-strong">{{ counts.active }}</p></div>
      <div class="ui-panel"><p class="text-sm font-semibold text-on-surface-variant">Hiển thị</p><p class="mt-1 text-3xl font-black tabular-nums text-primary">{{ sessions.length }}</p></div>
    </section>

    <div v-if="error" class="ui-alert ui-alert-error" role="alert"><p class="font-bold">Không thể tải khu vực hỗ trợ</p><p class="mt-1 text-sm">{{ error }}</p><button type="button" class="ui-btn ui-btn-secondary mt-3" @click="loadSessions">Thử lại</button></div>

    <section class="h-[min(760px,calc(100dvh-7rem))] min-h-[520px] overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-sm" aria-label="Bàn hỗ trợ trực tiếp">
      <div class="grid h-full min-h-0 lg:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="flex min-h-0 flex-col border-b border-outline-variant bg-surface-container-low lg:border-b-0 lg:border-r" aria-label="Danh sách phiên hỗ trợ">
          <div class="border-b border-outline-variant bg-surface-container-lowest p-3">
            <label for="support-filter" class="mb-2 block text-sm font-bold text-on-surface">Trạng thái</label>
            <select id="support-filter" v-model="filter" class="ui-field" @change="loadSessions"><option v-for="option in filters" :key="option.value" :value="option.value">{{ option.label }}</option></select>
          </div>

          <div v-if="loading && !sessions.length" class="grid min-h-48 place-items-center" role="status"><div class="flex items-center gap-2 text-on-surface-variant"><i class="pi pi-spin pi-spinner" aria-hidden="true"></i>Đang tải hàng đợi…</div></div>
          <div v-else-if="!sessions.length" class="grid min-h-48 place-items-center p-6 text-center"><div><span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">forum</span><p class="mt-2 font-bold text-on-surface">Chưa có phiên phù hợp</p><p class="mt-1 text-sm text-on-surface-variant">Phiên sẽ xuất hiện khi khách hàng chủ động yêu cầu gặp nhân viên.</p></div></div>
          <ul v-else class="min-h-0 flex-1 overflow-y-auto" aria-label="Các phiên hỗ trợ">
            <li v-for="session in sessions" :key="session.id" class="border-b border-outline-variant/60">
              <button type="button" class="w-full cursor-pointer p-4 text-left transition-colors hover:bg-brand-green-container/50 focus-visible:outline-none" :class="selected?.id === session.id ? 'border-l-4 border-brand-green-strong bg-brand-green-container/70' : ''" @click="selectSession(session)">
                <div class="flex items-start justify-between gap-3"><p class="font-bold text-on-surface">{{ customerName(session) }}</p><span :class="statusMeta(session.status).classes" class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-1 text-sm font-bold"><span class="material-symbols-outlined text-base" aria-hidden="true">{{ statusMeta(session.status).icon }}</span>{{ statusMeta(session.status).label }}</span></div>
                <p class="mt-2 line-clamp-2 text-sm leading-5 text-on-surface-variant">{{ session.last_message?.message || 'Chưa có nội dung' }}</p>
                <p class="mt-2 text-sm text-outline">#{{ session.id }} · {{ formatDate(session.last_message_at) }}</p>
              </button>
            </li>
          </ul>
        </aside>

        <main class="flex min-h-0 overflow-hidden flex-col bg-surface-container-lowest">
          <div v-if="!selected" class="grid flex-1 place-items-center p-8 text-center"><div><span class="material-symbols-outlined text-5xl text-outline" aria-hidden="true">mark_chat_unread</span><h2 class="mt-3 text-xl font-bold text-on-surface">Chọn một phiên hỗ trợ</h2><p class="mt-1 text-on-surface-variant">Lịch sử hội thoại và thao tác tiếp nhận sẽ hiển thị tại đây.</p></div></div>

          <template v-else>
            <header class="flex flex-col gap-3 border-b border-outline-variant p-4 sm:flex-row sm:items-center sm:justify-between">
              <div><h2 class="text-lg font-black text-on-surface">{{ customerName(selected) }}</h2><p class="mt-1 text-sm text-on-surface-variant">Phiên #{{ selected.id }}<span v-if="selected.assigned_user"> · Phụ trách: {{ selected.assigned_user.name }}</span></p></div>
              <div class="flex flex-wrap gap-2">
                <button v-if="canTakeover" type="button" class="ui-btn ui-btn-commerce" :disabled="actionLoading" @click="takeover"><span class="material-symbols-outlined" aria-hidden="true">person_check</span>Tiếp nhận</button>
                <button v-if="canClose" type="button" class="ui-btn border border-secondary bg-surface-container-lowest text-secondary hover:bg-secondary-fixed" :disabled="actionLoading" @click="closeSession"><span class="material-symbols-outlined" aria-hidden="true">task_alt</span>Hoàn tất</button>
              </div>
            </header>

            <div ref="messagePanel" class="flex-1 space-y-4 overflow-y-auto bg-surface-container-low p-4" role="log" aria-live="polite">
              <article v-for="message in messages" :key="message.id" class="flex flex-col" :class="message.sender_type === 'customer' ? 'items-start' : message.sender_type === 'system' ? 'items-center' : 'items-end'">
                <p class="mb-1 text-sm font-semibold text-on-surface-variant">{{ senderLabel(message.sender_type) }}</p>
                <div class="max-w-[85%] rounded-2xl border px-4 py-3 text-base leading-6 shadow-sm" :class="message.sender_type === 'customer' ? 'rounded-bl-md border-outline-variant bg-surface-container-lowest text-on-surface' : message.sender_type === 'system' ? 'border-outline-variant bg-surface-container text-on-surface-variant' : 'rounded-br-md border-brand-green-strong bg-brand-green-strong text-white'">
                  <a v-if="message.metadata?.attachment" :href="attachmentUrl(message)" target="_blank" rel="noopener" class="mb-2 block overflow-hidden rounded-xl border border-outline-variant/50 bg-surface-container-lowest">
                    <img :src="attachmentUrl(message)" :alt="message.metadata.attachment.original_name || 'Hình ảnh trong hội thoại'" class="max-h-72 w-full object-contain" loading="lazy" />
                  </a>
                  <p class="whitespace-pre-wrap">{{ message.message }}</p>
                  <p class="mt-2 text-sm opacity-75">{{ formatDate(message.created_at) }}</p>
                </div>
              </article>
              <div v-if="detailLoading" class="flex items-center gap-2 text-sm text-on-surface-variant" role="status"><i class="pi pi-spin pi-spinner" aria-hidden="true"></i>Đang cập nhật…</div>
            </div>

            <footer class="border-t border-outline-variant p-4">
              <div v-if="selectedImage" class="mb-2 flex items-center gap-2 rounded-lg border border-brand-green/30 bg-brand-green-container px-3 py-2 text-sm text-on-brand-green-container">
                <span class="material-symbols-outlined text-lg" aria-hidden="true">image</span><span class="min-w-0 flex-1 truncate">{{ selectedImage.name }}</span>
                <button type="button" class="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-green/15" aria-label="Bỏ hình ảnh đã chọn" @click="clearSelectedImage"><span class="material-symbols-outlined text-lg" aria-hidden="true">close</span></button>
              </div>
              <form class="flex items-end gap-2" @submit.prevent="reply">
                <input ref="replyFileInput" type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif" :disabled="!canReply || actionLoading" @change="handleImageSelection" />
                <button type="button" class="ui-btn ui-btn-secondary h-11 px-3" :disabled="!canReply || actionLoading" aria-label="Đính kèm hình ảnh" @click="replyFileInput?.click()"><span class="material-symbols-outlined" aria-hidden="true">add_photo_alternate</span></button>
                <div class="min-w-0 flex-1"><label for="staff-reply" class="mb-2 block text-sm font-bold text-on-surface">Phản hồi khách hàng</label><textarea id="staff-reply" ref="replyInput" v-model="replyText" rows="1" class="ui-field min-h-11 max-h-36 resize-none overflow-y-auto text-base" maxlength="2000" :disabled="!canReply || actionLoading" :placeholder="canReply ? 'Nhập phản hồi…' : 'Hãy tiếp nhận phiên trước khi trả lời'" @input="resizeReply"></textarea></div>
                <button type="submit" class="ui-btn ui-btn-commerce" :disabled="!canReply || (!replyText.trim() && !selectedImage) || actionLoading"><span class="material-symbols-outlined" aria-hidden="true">send</span>Gửi</button>
              </form>
            </footer>
          </template>
        </main>
      </div>
    </section>
  </div>
</template>

<script>
export const isStaffSessionAccessLoss = status => [401, 403, 404].includes(status)

export const clearStaffSessionState = ({ selected, messages, replyText, selectedImage, actionLoading, replyFileInput }) => {
  selected.value = null
  messages.value = []
  replyText.value = ''
  selectedImage.value = null
  actionLoading.value = false
  if (replyFileInput.value) replyFileInput.value.value = ''
}
</script>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import apiClient from '@/services/axios'
import { useAuthStore } from '@/stores/auth'
import { userSafeApiError } from '@/utils/apiError'

const authStore = useAuthStore()
const sessions = ref([])
const selected = ref(null)
const messages = ref([])
const filter = ref('')
const loading = ref(false)
const detailLoading = ref(false)
const actionLoading = ref(false)
const error = ref('')
const replyText = ref('')
const replyInput = ref(null)
const replyFileInput = ref(null)
const selectedImage = ref(null)
const messagePanel = ref(null)
let pollTimer

const filters = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'queued', label: 'Đang chờ' },
  { value: 'assigned', label: 'Đang hỗ trợ' },
  { value: 'waiting_customer', label: 'Chờ khách phản hồi' },
  { value: 'resolved', label: 'Đã hoàn tất' },
]

const baseUrl = computed(() => `/api/chat/${authStore.isAdmin ? 'admin' : 'vendor'}`)
const counts = computed(() => ({ queued: sessions.value.filter(session => session.status === 'queued').length, active: sessions.value.filter(session => ['assigned', 'waiting_customer'].includes(session.status)).length }))
const canTakeover = computed(() => selected.value?.status === 'queued' && selected.value?.responder_mode === 'human' && !selected.value?.assigned_user)
const canReply = computed(() => selected.value?.responder_mode === 'human' && ['assigned', 'waiting_customer'].includes(selected.value?.status) && selected.value?.assigned_user?.id === authStore.user?.id)
const canClose = computed(() => selected.value?.responder_mode === 'human' && ['assigned', 'waiting_customer'].includes(selected.value?.status) && selected.value?.assigned_user?.id === authStore.user?.id)
const lastMessageId = computed(() => messages.value.reduce((max, message) => Math.max(max, Number(message.id) || 0), 0))

const loadSessions = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get(`${baseUrl.value}/sessions`, { params: filter.value ? { status: filter.value } : {} })
    sessions.value = response.data?.sessions?.data || []
    if (selected.value) {
      const summary = sessions.value.find(session => session.id === selected.value.id)
      if (summary) selected.value = { ...selected.value, ...summary }
      else clearSelectedSession()
    }
  } catch (requestError) {
    if (isStaffSessionAccessLoss(requestError.response?.status)) clearSelectedSession()
    error.value = userSafeApiError(requestError, 'Khu vực hỗ trợ đang được cập nhật. Vui lòng thử lại sau ít phút.')
  } finally {
    loading.value = false
  }
}

const selectSession = async session => {
  selected.value = session
  messages.value = []
  replyText.value = ''
  clearSelectedImage()
  await refreshSelected(true)
}

const refreshSelected = async replace => {
  if (!selected.value || detailLoading.value) return
  detailLoading.value = true
  try {
    const response = await apiClient.get(`${baseUrl.value}/sessions/${selected.value.id}`, { params: replace ? {} : { after_id: lastMessageId.value } })
    const payload = response.data.session
    selected.value = { ...selected.value, ...payload, messages: undefined }
    const incoming = payload.messages || []
    if (replace) messages.value = incoming
    else {
      const known = new Set(messages.value.map(message => message.id))
      messages.value.push(...incoming.filter(message => !known.has(message.id)))
    }
    await nextTick()
    if (messagePanel.value) messagePanel.value.scrollTop = messagePanel.value.scrollHeight
  } catch (requestError) {
    if (isStaffSessionAccessLoss(requestError.response?.status)) clearSelectedSession()
    error.value = userSafeApiError(requestError, 'Không thể tải phiên hỗ trợ.')
  } finally {
    detailLoading.value = false
  }
}

const runAction = async (path, payload = {}) => {
  actionLoading.value = true
  error.value = ''
  try {
    const response = await apiClient.post(`${baseUrl.value}/sessions/${selected.value.id}/${path}`, payload)
    selected.value = { ...selected.value, ...response.data.session, messages: undefined }
    messages.value = response.data.session.messages || messages.value
    await loadSessions()
    return true
  } catch (requestError) {
    if (isStaffSessionAccessLoss(requestError.response?.status)) clearSelectedSession()
    error.value = userSafeApiError(requestError, 'Thao tác chưa hoàn tất. Vui lòng thử lại.')
    return false
  } finally {
    actionLoading.value = false
  }
}

const takeover = () => runAction('takeover')
const resizeReply = () => {
  if (!replyInput.value) return
  replyInput.value.style.height = 'auto'
  replyInput.value.style.height = `${Math.min(replyInput.value.scrollHeight, 144)}px`
}
const clearSelectedImage = () => {
  selectedImage.value = null
  if (replyFileInput.value) replyFileInput.value.value = ''
}
const clearSelectedSession = () => {
  clearStaffSessionState({ selected, messages, replyText, selectedImage, actionLoading, replyFileInput })
}
const handleImageSelection = event => {
  const file = event.target.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/') || file.size > 5 * 1024 * 1024) {
    error.value = 'Chỉ hỗ trợ ảnh JPG, PNG, WebP hoặc GIF có dung lượng tối đa 5 MB.'
    clearSelectedImage()
    return
  }
  selectedImage.value = file
  error.value = ''
}
const reply = async () => {
  const text = replyText.value.trim()
  if (!text && !selectedImage.value) return
  const payload = selectedImage.value ? new FormData() : { message: text }
  if (payload instanceof FormData) {
    if (text) payload.append('message', text)
    payload.append('image', selectedImage.value)
  }
  if (await runAction('reply', payload)) {
    replyText.value = ''
    clearSelectedImage()
    await nextTick()
    resizeReply()
  }
}
const closeSession = () => runAction('close', { resolution: 'Phiên hỗ trợ đã hoàn tất. Nếu cần thêm trợ giúp, khách hàng có thể mở một phiên mới.' })

const customerName = session => session.user?.name || `Khách vãng lai #${session.id}`
const formatDate = value => value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : 'Chưa cập nhật'
const attachmentUrl = message => `/api/chat/sessions/${selected.value?.id}/messages/${message.id}/attachment`
const senderLabel = type => ({ customer: 'Khách hàng', ai: 'Trợ lý AI', vendor: 'Nhân viên gian hàng', admin: 'Tư vấn viên KomiBook', system: 'Hệ thống' })[type] || 'Hệ thống'
const statusMeta = status => ({
  queued: { label: 'Đang chờ', icon: 'schedule', classes: 'bg-secondary-fixed text-on-secondary-fixed-variant' },
  assigned: { label: 'Đang hỗ trợ', icon: 'support_agent', classes: 'ui-badge-commerce' },
  waiting_customer: { label: 'Chờ khách', icon: 'hourglass_top', classes: 'bg-primary-fixed text-on-primary-fixed-variant' },
  resolved: { label: 'Hoàn tất', icon: 'task_alt', classes: 'bg-surface-container-high text-on-surface-variant' },
  closed: { label: 'Đã đóng', icon: 'block', classes: 'bg-surface-container-high text-on-surface-variant' },
})[status] || { label: status, icon: 'info', classes: 'bg-surface-container-high text-on-surface-variant' }

onMounted(async () => {
  await loadSessions()
  pollTimer = window.setInterval(async () => { await loadSessions(); await refreshSelected(false) }, 10000)
})
onUnmounted(() => { if (pollTimer) window.clearInterval(pollTimer) })
</script>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import { useAuthStore } from '@/stores/auth'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()

const ticketId = route.params.id
const ticket = ref(null)
const messages = ref([])
const loading = ref(true)
const sending = ref(false)
const error = ref(null)

const replyText = ref('')
const attachment = ref(null)

const isAdminRoute = computed(() => {
  return route.path.startsWith('/admin')
})

const fetchTicketDetails = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await apiClient.get(`/api/support/tickets/${ticketId}`)
    if (res.data?.status === 'success') {
      ticket.value = res.data.data
      messages.value = res.data.data.messages || []
    } else if (res.data) {
      ticket.value = res.data
      messages.value = res.data.messages || []
    } else {
      ticket.value = null
      messages.value = []
    }
  } catch (e) {
    console.error('Không tải được chi tiết ticket', e)
    ticket.value = null
    messages.value = []
    error.value = e.response?.data?.message || 'Không thể kết nối API chi tiết ticket.'
  } finally {
    loading.value = false
  }
}

const downloadAttachment = async (msg) => {
  try {
    const url = msg.attachment_url
    if (!url) {
      toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Tin nhắn không có file đính kèm.', life: 3000 })
      return
    }
    const response = await apiClient.get(url, { responseType: 'blob' })
    const blobUrl = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = blobUrl
    link.setAttribute('download', `ticket-attachment-${msg.id}`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải file đính kèm.', life: 3000 })
  }
}

const onFileSelect = (e) => {
  attachment.value = e.files?.[0] || null
}

const sendReply = async () => {
  if (!replyText.value.trim()) return

  sending.value = true
  try {
    const formData = new FormData()
    formData.append('message', replyText.value)
    if (attachment.value) {
      formData.append('attachment', attachment.value)
    }

    const res = await apiClient.post(`/api/support/tickets/${ticketId}/message`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã gửi', detail: 'Đã gửi phản hồi thành công.', life: 2000 })
      replyText.value = ''
      attachment.value = null
      fetchTicketDetails()
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể gửi phản hồi.', life: 3000 })
  } finally {
    sending.value = false
  }
}

const updateStatus = async (status) => {
  try {
    const res = await apiClient.patch(`/api/admin/support/tickets/${ticketId}/status`, { status })
    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật trạng thái ticket.', life: 2000 })
      if (ticket.value) ticket.value.status = status
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể cập nhật trạng thái.', life: 3000 })
  }
}

const assignToMe = async () => {
  try {
    const res = await apiClient.patch(`/api/admin/support/tickets/${ticketId}/assign`, {
      admin_id: authStore.user?.id
    })
    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã nhận xử lý ticket này.', life: 2000 })
      if (ticket.value) {
        ticket.value.assigned_admin_id = authStore.user?.id
        ticket.value.status = 'pending'
      }
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể nhận xử lý.', life: 3000 })
  }
}

const goBack = () => {
  if (isAdminRoute.value) {
    router.push({ name: 'admin-support-tickets' })
  } else {
    router.push({ name: 'customer-support' })
  }
}

onMounted(() => {
  fetchTicketDetails()
})
</script>

<template>
  <main class="ticket-detail min-h-screen bg-slate-50 p-4 md:p-8 flex flex-col" aria-labelledby="ticket-detail-title">
    <Toast />
    
    <!-- Header -->
    <div class="flex items-center justify-between mb-8 shrink-0">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" aria-label="Quay lại danh sách yêu cầu hỗ trợ" class="p-button-text p-button-secondary p-button-sm min-w-11 min-h-11" @click="goBack" />
        <div v-if="ticket">
          <h1 id="ticket-detail-title" class="text-xl md:text-2xl font-extrabold text-slate-900">Chi tiết yêu cầu #TK-{{ ticket.id }}</h1>
          <p class="text-slate-500 text-xs mt-1">Chủ đề: <strong class="text-slate-700">{{ ticket.subject }}</strong></p>
        </div>
        <h1 v-else id="ticket-detail-title" class="text-xl md:text-2xl font-extrabold text-slate-900">Chi tiết yêu cầu hỗ trợ</h1>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" role="status" aria-live="polite" aria-label="Đang tải chi tiết yêu cầu hỗ trợ" class="flex justify-center p-12 flex-grow items-center">
      <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
    </div>

    <!-- Error State -->
    <div v-else-if="error" role="alert" class="bg-rose-50 border border-rose-200 rounded-2xl p-8 text-center space-y-4 my-6">
      <i class="pi pi-exclamation-triangle text-4xl text-rose-500"></i>
      <h3 class="text-lg font-bold text-rose-800">Không thể tải chi tiết ticket</h3>
      <p class="text-sm text-rose-600 max-w-md mx-auto">{{ error }}</p>
      <div class="flex justify-center gap-3 pt-2">
        <Button label="Quay lại" icon="pi pi-arrow-left" class="p-button-outlined p-button-secondary p-button-sm text-xs" @click="goBack" />
        <Button label="Thử lại" icon="pi pi-refresh" class="p-button-danger p-button-sm text-xs bg-rose-600 text-white" @click="fetchTicketDetails" />
      </div>
    </div>

    <!-- Main Content -->
    <div v-else-if="ticket" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start flex-grow min-h-0">
      <!-- Conversation thread -->
      <div class="lg:col-span-8 flex flex-col bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden min-h-[480px] lg:h-[600px]">
        <!-- Thread messages -->
        <div class="flex-grow p-6 overflow-y-auto space-y-4 bg-slate-50/50">
          <div 
            v-for="msg in messages" 
            :key="msg.id"
            :class="['flex gap-3 max-w-[80%]', msg.sender_id === authStore.user?.id ? 'ml-auto flex-row-reverse' : '']"
          >
            <!-- Avatar mockup -->
            <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">
              {{ msg.sender?.name?.slice(0,2) || '??' }}
            </div>
            
            <div :class="[
              'p-4 rounded-2xl text-sm shadow-sm space-y-1',
              msg.sender_id === authStore.user?.id ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-white text-slate-800 rounded-tl-none border border-slate-150'
            ]">
              <span class="block text-[10px] font-bold opacity-60">{{ msg.sender?.name }}</span>
              <p class="leading-relaxed whitespace-pre-line">{{ msg.message }}</p>
              
              <!-- Attachment preview -->
              <div v-if="msg.has_attachment || msg.attachment" class="mt-2 pt-2 border-t border-slate-100/20 text-xs">
                <button type="button" @click="downloadAttachment(msg)" class="flex min-h-11 items-center gap-1.5 underline opacity-90 hover:opacity-100 bg-transparent border-none text-inherit cursor-pointer">
                  <i class="pi pi-download"></i> Tải / Xem tệp đính kèm
                </button>
              </div>
            </div>
          </div>

          <div v-if="messages.length === 0" class="text-center py-12 text-slate-400 text-xs">
            Chưa có tin nhắn phản hồi nào trong hội thoại này.
          </div>
        </div>

        <!-- Input Box -->
        <div class="p-4 border-t border-slate-200 shrink-0 space-y-3 bg-white">
          <label for="ticket-reply" class="sr-only">Nội dung phản hồi yêu cầu hỗ trợ</label>
          <Textarea id="ticket-reply" v-model="replyText" placeholder="Nhập câu trả lời hoặc phản hồi tại đây..." rows="3" class="w-full text-sm resize-none outline-none border-none p-0 focus:ring-0 focus:outline-none" />
          
          <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 pt-2 border-t border-slate-100">
            <div>
              <FileUpload mode="basic" accept="image/*" :maxFileSize="5242880" @select="onFileSelect" chooseLabel="Thêm ảnh minh họa" class="p-button-text p-button-sm text-xs" />
              <span v-if="attachment" class="text-[10px] text-indigo-600 font-semibold block mt-1">
                <i class="pi pi-paperclip mr-0.5"></i> {{ attachment.name }}
              </span>
            </div>
            <Button label="Gửi phản hồi" icon="pi pi-send" class="p-button-primary bg-indigo-600 text-white p-button-sm text-xs font-bold" :loading="sending" @click="sendReply" />
          </div>
        </div>
      </div>

      <!-- Right Column: Ticket Meta Actions -->
      <div class="lg:col-span-4 bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-6">
        <div>
          <h3 class="font-bold text-slate-800 text-base mb-1">Thông tin yêu cầu</h3>
          <p class="text-slate-400 text-xs">Chi tiết phân loại và người xử lý.</p>
        </div>

        <hr class="border-slate-100" />

        <div class="space-y-4 text-xs">
          <div class="flex justify-between">
            <span class="text-slate-400">Khách hàng:</span>
            <strong class="text-slate-700 font-bold">{{ ticket.user?.name || '—' }}</strong>
          </div>

          <div class="flex justify-between">
            <span class="text-slate-400">Danh mục hỗ trợ:</span>
            <strong class="text-slate-700 capitalize font-bold">{{ ticket.category }}</strong>
          </div>

          <div class="flex justify-between">
            <span class="text-slate-400">Độ ưu tiên:</span>
            <span :class="['font-bold px-2 py-0.5 rounded capitalize', ticket.priority === 'high' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600']">
              {{ ticket.priority }}
            </span>
          </div>

          <div class="flex justify-between">
            <span class="text-slate-400">Trạng thái phiếu:</span>
            <span :class="['font-bold px-2 py-0.5 rounded capitalize', ticket.status === 'open' ? 'bg-rose-100 text-rose-700' : ticket.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700']">
              {{ ticket.status === 'open' ? 'Mở' : ticket.status === 'pending' ? 'Đang xử lý' : 'Đã đóng' }}
            </span>
          </div>
        </div>

        <!-- Admin Controls Section -->
        <div v-if="isAdminRoute" class="space-y-3 pt-6 border-t border-slate-100">
          <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Hành động của Admin</h4>
          
          <Button 
            v-if="!ticket.assigned_admin_id" 
            label="Nhận giải quyết Ticket này" 
            icon="pi pi-user-plus" 
            class="w-full p-button-outlined p-button-sm text-xs" 
            @click="assignToMe" 
          />

          <div class="grid grid-cols-2 gap-2 mt-2">
            <Button label="Đóng Ticket" icon="pi pi-check" class="p-button-success p-button-sm text-xs font-bold" @click="updateStatus('resolved')" />
            <Button label="Mở lại Ticket" icon="pi pi-refresh" class="p-button-outlined p-button-secondary p-button-sm text-xs" @click="updateStatus('open')" />
          </div>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.ticket-detail {
  font-family: 'Inter', sans-serif;
}

:deep(.p-button),
:deep(.p-inputtext) {
  min-height: 44px;
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}
</style>

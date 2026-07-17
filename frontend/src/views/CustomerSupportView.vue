<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import FileUpload from 'primevue/fileupload'

const router = useRouter()
const toast = useToast()

const tickets = ref([])
const loading = ref(true)
const sending = ref(false)

const newTicket = ref({
  subject: '',
  category: 'technical',
  priority: 'medium',
  message: '',
})
const attachment = ref(null)

const categoryOptions = [
  { label: 'Hỗ trợ kỹ thuật', value: 'technical' },
  { label: 'Thanh toán & Hóa đơn', value: 'billing' },
  { label: 'Bản quyền & DRM', value: 'drm' },
  { label: 'Đóng góp ý kiến', value: 'feedback' },
]

const priorityOptions = [
  { label: 'Thấp', value: 'low' },
  { label: 'Trung bình', value: 'medium' },
  { label: 'Cao (Khẩn cấp)', value: 'high' },
]

const fetchTickets = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/support/tickets')
    if (res.data?.status === 'success') {
      tickets.value = res.data.data
    }
  } catch (e) {
    console.error('Không tải được danh sách hỗ trợ', e)
    // Fallback Mock Data
    tickets.value = [
      { id: 1, subject: 'Lỗi tải sách PDF sau thanh toán', category: 'technical', priority: 'high', status: 'open', updated_at: '2026-07-13T10:00:00Z' },
    ]
  } finally {
    loading.value = false
  }
}

const onFileSelect = (e) => {
  attachment.value = e.files?.[0] || null
}

const submitTicket = async () => {
  if (!newTicket.value.subject || !newTicket.value.message) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Vui lòng nhập đầy đủ tiêu đề và nội dung yêu cầu.', life: 3000 })
    return
  }

  sending.value = true
  try {
    const formData = new FormData()
    formData.append('subject', newTicket.value.subject)
    formData.append('category', newTicket.value.category)
    formData.append('priority', newTicket.value.priority)
    formData.append('message', newTicket.value.message)
    if (attachment.value) {
      formData.append('attachment', attachment.value)
    }

    const res = await apiClient.post('/api/support/tickets', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Gửi yêu cầu hỗ trợ thành công. Chúng tôi sẽ phản hồi sớm nhất.', life: 4000 })
      // Reset form
      newTicket.value = { subject: '', category: 'technical', priority: 'medium', message: '' }
      attachment.value = null
      fetchTickets()
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi gửi yêu cầu', detail: 'Không thể gửi yêu cầu hỗ trợ.', life: 3000 })
  } finally {
    sending.value = false
  }
}

const viewDetail = (id) => {
  router.push(`/support/tickets/${id}`)
}

onMounted(() => {
  fetchTickets()
})
</script>

<template>
  <div class="customer-support min-h-screen bg-slate-50 py-12 px-4 md:px-8">
    <Toast />
    
    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <!-- Left Column: Submit form -->
      <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6">
        <div>
          <h2 class="text-xl font-bold text-slate-800">Tạo yêu cầu trợ giúp mới</h2>
          <p class="text-slate-500 text-xs mt-1">Ban hỗ trợ kỹ thuật và bản quyền của Komibook luôn sẵn sàng trợ giúp bạn 24/7.</p>
        </div>

        <form @submit.prevent="submitTicket" class="space-y-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-700">Tiêu đề yêu cầu <span class="text-rose-500">*</span></label>
            <InputText v-model="newTicket.subject" placeholder="Tóm tắt ngắn gọn vấn đề bạn gặp phải" class="w-full text-sm" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-700">Phân loại danh mục</label>
              <Select v-model="newTicket.category" :options="categoryOptions" optionLabel="label" optionValue="value" class="w-full text-sm" />
            </div>
            
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-700">Độ ưu tiên</label>
              <Select v-model="newTicket.priority" :options="priorityOptions" optionLabel="label" optionValue="value" class="w-full text-sm" />
            </div>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-700">Mô tả chi tiết vấn đề <span class="text-rose-500">*</span></label>
            <Textarea v-model="newTicket.message" placeholder="Mô tả cụ thể sự cố (các bước thực hiện, thông báo lỗi nếu có)..." rows="6" class="w-full text-sm" />
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-700">Tải lên tệp đính kèm (Ảnh chụp lỗi)</label>
            <FileUpload mode="basic" accept="image/*" :maxFileSize="5242880" @select="onFileSelect" chooseLabel="Chọn ảnh đính kèm" class="w-full" />
            <span v-if="attachment" class="text-xs text-indigo-600 font-semibold mt-1">
              <i class="pi pi-paperclip mr-1"></i> {{ attachment.name }}
            </span>
          </div>

          <Button type="submit" label="Gửi yêu cầu hỗ trợ" icon="pi pi-send" class="w-full p-button-primary bg-indigo-600 text-white font-bold h-12 rounded-lg" :loading="sending" />
        </form>
      </div>

      <!-- Right Column: My tickets history -->
      <div class="lg:col-span-5 space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
          <h3 class="font-bold text-slate-800 text-base">Lịch sử yêu cầu của bạn</h3>
          
          <div v-if="loading" class="flex justify-center p-6">
            <i class="pi pi-spin pi-spinner text-2xl text-slate-400"></i>
          </div>

          <div v-else class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto pr-2">
            <div 
              v-for="ticket in tickets" 
              :key="ticket.id" 
              class="py-3 cursor-pointer hover:bg-slate-50/50 px-2 rounded-lg transition-colors flex items-center justify-between"
              @click="viewDetail(ticket.id)"
            >
              <div class="min-w-0 pr-2">
                <h4 class="font-bold text-slate-700 text-xs truncate">{{ ticket.subject }}</h4>
                <p class="text-[10px] text-slate-400 mt-1">
                  Mã: #TK-890{{ ticket.id }} | Cập nhật: {{ ticket.updated_at?.split('T')[0] }}
                </p>
              </div>
              <span :class="[
                'text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0',
                ticket.status === 'open' ? 'bg-rose-100 text-rose-700' : ticket.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'
              ]">
                {{ ticket.status === 'open' ? 'Chờ xử lý' : ticket.status === 'pending' ? 'Đang trả lời' : 'Đã đóng' }}
              </span>
            </div>

            <div v-if="tickets.length === 0" class="text-center text-slate-400 text-xs py-8">
              Bạn chưa tạo yêu cầu hỗ trợ nào.
            </div>
          </div>
        </div>

        <div class="bg-indigo-900 text-white rounded-2xl p-6 relative overflow-hidden">
          <h3 class="font-bold text-sm mb-2">Tìm kiếm câu trả lời nhanh?</h3>
          <p class="text-xs text-indigo-200 leading-relaxed mb-4">
            Trước khi gửi yêu cầu hỗ trợ, bạn có thể tham khảo Trung tâm Trợ giúp FAQs để tìm câu trả lời cho các vấn đề thường gặp.
          </p>
          <Button label="Xem FAQs" class="p-button-sm p-button-secondary bg-white text-indigo-950 font-bold border-none" @click="router.push({ name: 'help-center' })" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.customer-support {
  font-family: 'Inter', sans-serif;
}
:deep(.p-select) {
  border-radius: 8px;
}
:deep(.p-inputtext) {
  border-radius: 8px;
}
</style>

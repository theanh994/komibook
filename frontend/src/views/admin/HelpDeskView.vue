<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'

import Button from 'primevue/button'

const router = useRouter()
const tickets = ref([])
const loading = ref(true)
const error = ref(null)

const stats = ref({
  open: 0,
  pending: 0,
  resolved: 0,
})

const fetchTickets = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await apiClient.get('/api/admin/support/tickets')
    if (res.data?.status === 'success') {
      tickets.value = res.data.data || []
    } else if (Array.isArray(res.data)) {
      tickets.value = res.data
    } else {
      tickets.value = []
    }

    const openCount = tickets.value.filter(t => t.status === 'open').length
    const pendingCount = tickets.value.filter(t => t.status === 'pending').length
    const resolvedCount = tickets.value.filter(t => t.status === 'resolved').length
    stats.value = { open: openCount, pending: pendingCount, resolved: resolvedCount }
  } catch (e) {
    console.error('Không tải được danh sách ticket admin', e)
    tickets.value = []
    stats.value = { open: 0, pending: 0, resolved: 0 }
    error.value = e.response?.data?.message || 'Không thể kết nối API danh sách ticket.'
  } finally {
    loading.value = false
  }
}

const getPriorityBadgeClass = (priority) => {
  switch (priority) {
    case 'high': return 'bg-rose-100 text-rose-700'
    case 'medium': return 'bg-amber-100 text-amber-700'
    default: return 'bg-slate-100 text-slate-700'
  }
}

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'open': return 'bg-rose-150 text-rose-800'
    case 'pending': return 'bg-amber-150 text-amber-800'
    default: return 'bg-emerald-100 text-emerald-700'
  }
}

onMounted(() => {
  fetchTickets()
})
</script>

<template>
  <div class="help-desk min-h-screen bg-slate-50 p-6 md:p-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Quản lý Yêu cầu Hỗ trợ (Tickets)</h1>
        <p class="text-slate-500 text-sm mt-1">Tiếp nhận, xử lý và theo dõi các phản hồi từ độc giả và tác giả đối tác.</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-center space-y-3 mb-8">
      <h3 class="text-base font-bold text-rose-800">Không thể tải danh sách ticket</h3>
      <p class="text-xs text-rose-600">{{ error }}</p>
      <Button label="Thử lại" icon="pi pi-refresh" class="p-button-danger p-button-sm text-xs bg-rose-600 text-white" @click="fetchTickets" />
    </div>

    <!-- Stats Bento Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
        <div>
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Yêu cầu mở</span>
          <h2 class="text-3xl font-black text-rose-600 mt-2">{{ stats.open }}</h2>
        </div>
        <p class="text-slate-400 text-xs mt-4">Cần ưu tiên phản hồi gấp</p>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
        <div>
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Đang chờ phản hồi</span>
          <h2 class="text-3xl font-black text-amber-500 mt-2">{{ stats.pending }}</h2>
        </div>
        <p class="text-slate-400 text-xs mt-4">Chờ thông tin từ khách hàng</p>
      </div>

      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
        <div>
          <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Đã giải quyết</span>
          <h2 class="text-3xl font-black text-emerald-600 mt-2">{{ stats.resolved }}</h2>
        </div>
        <p class="text-slate-400 text-xs mt-4">Đã hoàn thành phản hồi</p>
      </div>
    </div>

    <!-- Filter & Tickets Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
        <h3 class="font-bold text-slate-800 text-sm">Danh sách yêu cầu của khách</h3>
      </div>

      <div v-if="loading" class="flex justify-center p-12">
        <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
              <th class="p-4">Mã số</th>
              <th class="p-4">Khách hàng</th>
              <th class="p-4">Tiêu đề yêu cầu</th>
              <th class="p-4">Danh mục</th>
              <th class="p-4">Độ ưu tiên</th>
              <th class="p-4">Trạng thái</th>
              <th class="p-4">Cập nhật lúc</th>
              <th class="p-4 text-center">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-150">
            <tr v-for="ticket in tickets" :key="ticket.id" class="hover:bg-slate-50/30">
              <td class="p-4 font-mono font-semibold text-slate-700">#TK-{{ ticket.id }}</td>
              <td class="p-4 text-slate-800 font-semibold">{{ ticket.user?.name || 'Khách vãng lai' }}</td>
              <td class="p-4 text-slate-700 truncate max-w-xs">{{ ticket.subject }}</td>
              <td class="p-4 text-slate-600 capitalize">{{ ticket.category }}</td>
              <td class="p-4">
                <span :class="['text-xs font-semibold px-2.5 py-0.5 rounded-full capitalize', getPriorityBadgeClass(ticket.priority)]">
                  {{ ticket.priority }}
                </span>
              </td>
              <td class="p-4">
                <span :class="['text-xs font-bold px-2 py-0.5 rounded-full capitalize', getStatusBadgeClass(ticket.status)]">
                  {{ ticket.status === 'open' ? 'Mở' : ticket.status === 'pending' ? 'Đang xử lý' : 'Đã đóng' }}
                </span>
              </td>
              <td class="p-4 text-slate-500">{{ ticket.updated_at?.split('T')[0] }}</td>
              <td class="p-4 text-center">
                <Button label="Hội thoại" icon="pi pi-comments" class="p-button-text p-button-sm p-1 text-xs" @click="router.push({ name: 'admin-support-ticket-detail', params: { id: ticket.id } })" />
              </td>
            </tr>
            <tr v-if="tickets.length === 0 && !error">
              <td colspan="8" class="p-8 text-center text-slate-400">Không tìm thấy yêu cầu hỗ trợ nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.help-desk {
  font-family: 'Inter', sans-serif;
}
</style>

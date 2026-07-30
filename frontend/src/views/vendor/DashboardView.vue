<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Thống kê gian hàng</h2>
      <p class="text-sm text-slate-500 mt-1">Tổng quan về doanh thu và hoạt động kinh doanh của bạn.</p>
    </div>

    <div v-if="errorMessage" class="mb-6 flex flex-col gap-3 rounded-xl border border-error/30 bg-error-container/30 p-4 text-on-error-container sm:flex-row sm:items-center sm:justify-between" role="alert">
      <span>{{ errorMessage }}</span>
      <button type="button" class="min-h-11 rounded-lg border border-error/40 px-4 font-bold" @click="fetchStats">Thử lại</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div v-for="i in 3" :key="i" class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
        <Skeleton height="3rem" width="3rem" class="mb-4" />
        <Skeleton height="1.5rem" width="50%" class="mb-2" />
        <Skeleton height="2rem" width="70%" />
      </div>
    </div>

    <!-- Stats Cards -->
    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      
      <!-- Doanh thu -->
      <div class="relative bg-white rounded-2xl p-6 border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden group">
        <div class="absolute right-0 top-0 w-32 h-32 bg-indigo-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110 duration-500"></div>
        <div class="relative z-10">
          <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center mb-4 shadow-lg shadow-indigo-500/30">
            <i class="pi pi-dollar text-2xl text-white"></i>
          </div>
          <p class="text-sm font-medium text-slate-500 mb-1">Tổng doanh thu</p>
          <h3 class="text-3xl font-bold text-slate-900 tracking-tight">{{ formatCurrency(stats.total_revenue) }}</h3>
        </div>
      </div>

      <!-- Đơn chờ -->
      <div class="relative bg-white rounded-2xl p-6 border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden group">
        <div class="absolute right-0 top-0 w-32 h-32 bg-amber-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110 duration-500"></div>
        <div class="relative z-10">
          <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center mb-4 shadow-lg shadow-orange-500/30">
            <i class="pi pi-shopping-cart text-2xl text-white"></i>
          </div>
          <p class="text-sm font-medium text-slate-500 mb-1">Đơn hàng chờ xử lý</p>
          <h3 class="text-3xl font-bold text-slate-900 tracking-tight">{{ stats.pending_orders || 0 }} <span class="text-base font-normal text-slate-400 ml-1">đơn</span></h3>
        </div>
      </div>

      <!-- Tổng sách -->
      <div class="relative bg-white rounded-2xl p-6 border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden group">
        <div class="absolute right-0 top-0 w-32 h-32 bg-emerald-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110 duration-500"></div>
        <div class="relative z-10">
          <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center mb-4 shadow-lg shadow-emerald-500/30">
            <i class="pi pi-book text-2xl text-white"></i>
          </div>
          <p class="text-sm font-medium text-slate-500 mb-1">Sách đang bán</p>
          <h3 class="text-3xl font-bold text-slate-900 tracking-tight">{{ stats.total_books || 0 }} <span class="text-base font-normal text-slate-400 ml-1">cuốn</span></h3>
        </div>
      </div>

    </div>

    <div v-if="!loading && !errorMessage" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.7fr)]">
      <section class="min-w-0 rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-soft" aria-labelledby="recent-orders-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 id="recent-orders-title" class="text-xl font-black text-on-surface">Đơn hàng gần đây</h3>
            <p class="mt-1 text-sm text-on-surface-variant">Chỉ gồm đơn thuộc gian hàng hiện tại.</p>
          </div>
          <RouterLink to="/vendor/orders" class="inline-flex min-h-11 items-center font-bold text-primary no-underline hover:underline">Xem tất cả</RouterLink>
        </div>
        <div v-if="stats.recent_orders.length" class="overflow-x-auto">
          <table class="w-full min-w-[620px] border-collapse text-left">
            <thead class="border-b border-outline-variant/30 text-sm text-on-surface-variant">
              <tr><th class="py-3">Mã đơn</th><th class="py-3">Khách hàng</th><th class="py-3">Trạng thái</th><th class="py-3 text-right">Giá trị</th></tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
              <tr v-for="order in stats.recent_orders" :key="order.id">
                <td class="py-3 font-bold text-primary"><RouterLink :to="`/vendor/orders/${order.id}`">{{ order.order_code }}</RouterLink></td>
                <td class="py-3 text-on-surface">{{ order.customer_name || 'Khách hàng' }}</td>
                <td class="py-3 text-on-surface-variant">{{ statusLabel(order.status) }}</td>
                <td class="py-3 text-right font-bold text-on-surface">{{ formatCurrency(order.total_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="rounded-xl bg-surface-container-low p-6 text-center text-on-surface-variant">Chưa có đơn hàng trong gian hàng.</div>
      </section>

      <section class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5 shadow-soft" aria-labelledby="status-title">
        <h3 id="status-title" class="text-xl font-black text-on-surface">Tình trạng vận hành</h3>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
          <div v-for="entry in statusEntries" :key="entry.key" class="flex items-center justify-between rounded-xl bg-surface-container-low p-4">
            <dt class="text-sm font-bold text-on-surface-variant">{{ entry.label }}</dt>
            <dd class="text-xl font-black text-primary">{{ entry.value }}</dd>
          </div>
        </dl>
        <RouterLink to="/vendor/analytics" class="mt-4 inline-flex min-h-11 items-center font-bold text-primary no-underline hover:underline">Mở phân tích bán hàng</RouterLink>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import apiClient from '@/services/axios'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const loading = ref(true)
const errorMessage = ref('')
const stats = ref({
  total_revenue: 0,
  pending_orders: 0,
  total_books: 0,
  total_orders: 0,
  completed_orders: 0,
  draft_books: 0,
  order_status_breakdown: {},
  recent_orders: []
})

const statusLabels = { pending: 'Chờ xử lý', processing: 'Đang xử lý', shipped: 'Đang giao', completed: 'Hoàn thành', cancelled: 'Đã hủy' }
const statusLabel = (status) => statusLabels[status] || status
const statusEntries = computed(() => [
  { key: 'orders', label: 'Tổng đơn hàng', value: stats.value.total_orders || 0 },
  { key: 'completed', label: 'Đơn hoàn thành', value: stats.value.completed_orders || 0 },
  { key: 'drafts', label: 'Sách nháp', value: stats.value.draft_books || 0 },
  { key: 'cancelled', label: 'Đơn đã hủy', value: stats.value.order_status_breakdown?.cancelled || 0 },
])

const fetchStats = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/vendor/dashboard-stats')
    const data = response.data.data || {}
    stats.value = {
      ...stats.value,
      ...data,
      recent_orders: Array.isArray(data.recent_orders) ? data.recent_orders : [],
      order_status_breakdown: data.order_status_breakdown || {},
    }
  } catch (error) {
    console.error('Lỗi tải thống kê:', error)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu thống kê', life: 3000 })
    errorMessage.value = 'Không thể tải dữ liệu dashboard. Dữ liệu minh họa không được sử dụng thay thế.'
  } finally {
    loading.value = false
  }
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

onMounted(() => {
  fetchStats()
})
</script>

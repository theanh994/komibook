<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Thống kê gian hàng</h2>
      <p class="text-sm text-slate-500 mt-1">Tổng quan về doanh thu và hoạt động kinh doanh của bạn.</p>
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import apiClient from '@/services/axios'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const loading = ref(true)
const stats = ref({
  total_revenue: 0,
  pending_orders: 0,
  total_books: 0
})

const fetchStats = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/vendor/dashboard-stats')
    stats.value = response.data.data
  } catch (error) {
    console.error('Lỗi tải thống kê:', error)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu thống kê', life: 3000 })
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

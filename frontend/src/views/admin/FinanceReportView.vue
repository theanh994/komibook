<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()

const period = ref('Tháng Này (' + new Date().toLocaleDateString('vi-VN', { month: '2-digit', year: 'numeric' }) + ')')
const loading = ref(true)

const reportData = ref({
  kpi: { total_revenue: 0, monthly_revenue: 0, total_orders: 0, completed_orders: 0, total_customers: 0, avg_order_value: 0 },
  revenue_by_month: [],
  revenue_by_payment_method: [],
  top_vendors: [],
  payout_stats: { pending: 0, approved: 0, rejected: 0 }
})

const fetchReport = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/admin/finance-report')
    if (res.data.status === 'success') {
      reportData.value = res.data.data
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải báo cáo tài chính.', life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchReport()
})

const formatVND = (val) => new Intl.NumberFormat('vi-VN').format(val || 0) + ' ₫'

const kpiCards = computed(() => [
  { label: 'Tổng Doanh Thu', value: formatVND(reportData.value.kpi.total_revenue), icon: 'account_balance_wallet', trend: 'Tất cả thời gian', trendColor: 'text-primary', trendIcon: '' },
  { label: 'Doanh Thu Tháng Này', value: formatVND(reportData.value.kpi.monthly_revenue), icon: 'calendar_month', trend: 'Tháng hiện tại', trendColor: 'text-green-600', trendIcon: 'trending_up' },
  { label: 'Yêu Cầu Rút Tiền Chờ Duyệt', value: formatVND(reportData.value.payout_stats.pending), icon: 'pending_actions', trend: 'Chờ xử lý', trendColor: 'text-yellow-600', trendIcon: '' },
])
</script>

<template>
  <div class="pb-xxl w-full pt-6">
    <!-- Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-end mb-xl animate-fade-in">
      <div>
        <h1 class="font-headline-lg text-headline-lg font-semibold text-primary">Báo cáo tài chính</h1>
        <p class="text-on-surface-variant font-body-md text-body-md mt-1">Tổng quan doanh thu và đối soát</p>
      </div>
      <div class="flex gap-md mt-md md:mt-0">
        <div class="relative bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-2 flex items-center gap-2 shadow-sm cursor-pointer hover:border-primary transition-colors">
          <span class="material-symbols-outlined text-on-surface-variant text-[20px]">calendar_month</span>
          <span class="font-label-md text-label-md text-on-surface">{{ period }}</span>
        </div>
      </div>
    </header>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
      <div v-for="i in 3" :key="i" class="bg-surface-container-lowest h-32 rounded-xl shadow-card border border-outline-variant/20 animate-pulse"></div>
    </div>

    <!-- KPI Cards -->
    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl animate-slide-up">
      <div
        v-for="(card, idx) in kpiCards" :key="idx"
        class="bg-surface-container-lowest p-lg rounded-xl shadow-card border border-outline-variant/20 flex flex-col justify-between h-32 relative overflow-hidden group hover:shadow-card-hover transition-all"
      >
        <div class="flex justify-between items-start z-10">
          <span class="font-label-md text-label-md text-on-surface-variant">{{ card.label }}</span>
          <span class="material-symbols-outlined text-primary bg-primary-container/30 p-1 rounded-md text-[20px]">{{ card.icon }}</span>
        </div>
        <div class="z-10">
          <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ card.value }}</span>
          <div class="flex items-center gap-1 mt-1 text-[12px] font-medium" :class="card.trendColor">
            <span v-if="card.trendIcon" class="material-symbols-outlined text-[14px]">{{ card.trendIcon }}</span>
            {{ card.trend }}
          </div>
        </div>
      </div>
    </div>

    <!-- Charts & Vendors Section -->
    <div v-if="!loading" class="grid grid-cols-1 lg:grid-cols-3 gap-lg mb-xl animate-slide-up delay-100">
      <!-- Top Vendors -->
      <div class="col-span-1 lg:col-span-2 bg-surface-container-lowest rounded-xl shadow-card border border-outline-variant/20 p-lg overflow-hidden">
        <h3 class="font-headline-md text-headline-md text-on-surface mb-6">Top Nhà Bán Hàng (Theo Doanh Thu)</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="text-on-surface-variant text-sm border-b border-outline-variant/30">
                <th class="py-2 font-medium">Nhà Bán Hàng</th>
                <th class="py-2 font-medium text-right">Tổng Đơn Hàng</th>
                <th class="py-2 font-medium text-right">Doanh Thu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10 text-body-md">
              <tr v-for="vendor in reportData.top_vendors" :key="vendor.id" class="hover:bg-surface-container-lowest">
                <td class="py-3 font-medium text-primary">{{ vendor.shop_name }}</td>
                <td class="py-3 text-right">{{ vendor.total_orders }}</td>
                <td class="py-3 text-right font-bold">{{ formatVND(vendor.revenue) }}</td>
              </tr>
              <tr v-if="reportData.top_vendors.length === 0">
                <td colspan="3" class="text-center py-4 text-on-surface-variant">Chưa có dữ liệu</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Payout Stats -->
      <div class="col-span-1 bg-surface-container-lowest rounded-xl shadow-card border border-outline-variant/20 p-lg flex flex-col">
        <h3 class="font-headline-md text-headline-md text-on-surface mb-6">Yêu Cầu Rút Tiền</h3>
        <div class="space-y-4">
          <div class="p-4 rounded-lg bg-yellow-50 border border-yellow-100 flex justify-between items-center">
            <div class="flex items-center gap-2 text-yellow-700">
              <span class="material-symbols-outlined">pending</span>
              <span class="font-medium">Đang chờ duyệt</span>
            </div>
            <span class="font-bold text-yellow-800">{{ formatVND(reportData.payout_stats.pending) }}</span>
          </div>
          <div class="p-4 rounded-lg bg-green-50 border border-green-100 flex justify-between items-center">
            <div class="flex items-center gap-2 text-green-700">
              <span class="material-symbols-outlined">check_circle</span>
              <span class="font-medium">Đã duyệt (Lũy kế)</span>
            </div>
            <span class="font-bold text-green-800">{{ formatVND(reportData.payout_stats.approved) }}</span>
          </div>
          <div class="p-4 rounded-lg bg-red-50 border border-red-100 flex justify-between items-center">
            <div class="flex items-center gap-2 text-red-700">
              <span class="material-symbols-outlined">cancel</span>
              <span class="font-medium">Bị từ chối (Lũy kế)</span>
            </div>
            <span class="font-bold text-red-800">{{ formatVND(reportData.payout_stats.rejected) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shadow-card { box-shadow: 0px 12px 24px -4px rgba(26,58,90,0.04); }
.shadow-card-hover { box-shadow: 0px 16px 32px -4px rgba(26,58,90,0.08); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
</style>

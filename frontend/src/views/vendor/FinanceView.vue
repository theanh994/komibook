<script setup>
import { onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()
const feePolicy = ref(null)
const revenueGranularity = ref('month')
const revenuePeriod = ref(new Date().toISOString().slice(0, 7))
const revenueReport = ref({ summary: {}, entries: [] })
const revenueLoading = ref(false)
const loading = ref(false)
const errorMessage = ref('')

const fetchFinanceData = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/vendor/finance')
    feePolicy.value = response.data.fee_policy || null
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Không thể tải chính sách doanh thu.'
  } finally { loading.value = false }
}

const fetchRevenue = async () => {
  revenueLoading.value = true
  try {
    const response = await apiClient.get('/api/vendor/finance/revenue', { params: { granularity: revenueGranularity.value, period: revenuePeriod.value } })
    revenueReport.value = response.data.data
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể tải báo cáo', detail: error.response?.data?.message || 'Kỳ báo cáo chưa hợp lệ.', life: 3500 })
  } finally { revenueLoading.value = false }
}

const changeGranularity = () => {
  const year = new Date().getFullYear()
  revenuePeriod.value = revenueGranularity.value === 'month' ? new Date().toISOString().slice(0, 7) : revenueGranularity.value === 'quarter' ? `${year}-Q1` : String(year)
  fetchRevenue()
}
const exportRevenue = () => {
  const query = new URLSearchParams({ granularity: revenueGranularity.value, period: revenuePeriod.value })
  window.open(`/api/vendor/finance/revenue/export?${query}`, '_blank', 'noopener')
}
const formatVND = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0))

onMounted(() => { fetchFinanceData(); fetchRevenue() })
</script>

<template>
  <main class="w-full space-y-6 pb-12 pt-6" aria-labelledby="vendor-revenue-title">
    <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div><p class="text-sm font-bold uppercase tracking-wider text-primary">Tài chính Nhà bán</p><h1 id="vendor-revenue-title" class="mt-1 text-3xl font-black text-on-surface">Báo cáo doanh thu</h1><p class="mt-2 text-sm text-on-surface-variant">Theo dõi doanh thu gộp, commission và số thực nhận theo từng kỳ.</p></div>
      <RouterLink to="/wallet" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-5 font-bold text-on-primary no-underline transition-opacity hover:opacity-90"><span class="material-symbols-outlined" aria-hidden="true">account_balance_wallet</span>Mở Ví & Rút tiền</RouterLink>
    </header>

    <div v-if="errorMessage" class="flex flex-col gap-3 rounded-xl border border-error/30 bg-error-container/30 p-4 text-on-error-container sm:flex-row sm:items-center sm:justify-between" role="alert"><span>{{ errorMessage }}</span><button type="button" class="min-h-11 rounded-lg border border-error/40 px-4 font-bold" @click="fetchFinanceData">Thử lại</button></div>

    <section class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5" aria-labelledby="fee-policy-title">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h2 id="fee-policy-title" class="text-xl font-black text-on-surface">Commission và phí dịch vụ</h2><p class="mt-1 text-sm leading-6 text-on-surface-variant">Commission được trừ từ doanh thu gộp. Phí dịch vụ được cộng vào số tiền khách thanh toán.</p></div><RouterLink to="/help-center" class="inline-flex min-h-11 items-center font-bold text-primary no-underline hover:underline">Xem trợ giúp</RouterLink></div>
      <div v-if="feePolicy" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Giá sách ví dụ</p><strong class="mt-1 block text-lg tabular-nums">{{ formatVND(feePolicy.example.seller_gross) }}</strong></div>
        <div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Commission ({{ feePolicy.schedule.commission_rate }}%)</p><strong class="mt-1 block text-lg tabular-nums">− {{ formatVND(feePolicy.example.commission_amount) }}</strong></div>
        <div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Nhà bán nhận</p><strong class="mt-1 block text-lg tabular-nums text-primary">{{ formatVND(feePolicy.example.seller_net) }}</strong></div>
        <div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Khách thanh toán</p><strong class="mt-1 block text-lg tabular-nums">{{ formatVND(feePolicy.example.customer_pays) }}</strong></div>
      </div>
      <div v-else-if="loading" class="mt-5 h-24 animate-pulse rounded-xl bg-surface-container" aria-live="polite"></div>
      <p v-else class="mt-5 rounded-xl bg-surface-container-low p-4 text-sm text-on-surface-variant">Chưa tải được biểu phí; hệ thống không tự suy đoán tỷ lệ commission.</p>
    </section>

    <section class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5" aria-labelledby="period-report-title">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><h2 id="period-report-title" class="text-xl font-black text-on-surface">Doanh thu theo kỳ</h2><p class="mt-1 text-sm text-on-surface-variant">Báo cáo được giữ trong 24 tháng và không bao gồm khấu trừ thuế.</p></div><div class="flex flex-wrap items-end gap-3"><label class="grid gap-1 text-sm font-semibold">Kỳ tính<select v-model="revenueGranularity" class="min-h-11 rounded-lg border border-outline-variant bg-surface px-3" @change="changeGranularity"><option value="month">Theo tháng</option><option value="quarter">Theo quý</option><option value="year">Theo năm</option></select></label><label class="grid gap-1 text-sm font-semibold">Thời gian<input v-model.trim="revenuePeriod" class="min-h-11 rounded-lg border border-outline-variant bg-surface px-3" @keyup.enter="fetchRevenue" /></label><button type="button" class="min-h-11 rounded-lg border border-primary px-4 font-bold text-primary disabled:opacity-50" :disabled="revenueLoading" @click="fetchRevenue">{{ revenueLoading ? 'Đang tải…' : 'Xem báo cáo' }}</button><button type="button" class="min-h-11 rounded-lg bg-primary px-4 font-bold text-on-primary" @click="exportRevenue">Xuất CSV</button></div></div>
      <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Doanh thu gộp</p><strong class="mt-1 block text-xl tabular-nums">{{ formatVND(revenueReport.summary?.gross_revenue) }}</strong></div><div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Commission</p><strong class="mt-1 block text-xl tabular-nums">− {{ formatVND(revenueReport.summary?.commission_amount) }}</strong></div><div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Thực nhận vào ví</p><strong class="mt-1 block text-xl tabular-nums text-primary">{{ formatVND(revenueReport.summary?.net_revenue) }}</strong></div><div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Hoàn tiền</p><strong class="mt-1 block text-xl tabular-nums">{{ formatVND(revenueReport.summary?.refund_amount) }}</strong></div></div>
      <div class="mt-5 overflow-x-auto" tabindex="0"><table class="w-full min-w-[720px] text-left"><thead><tr class="border-b border-outline-variant/30 text-sm text-on-surface-variant"><th class="p-3">Đơn hàng</th><th class="p-3">Ngày ghi nhận</th><th class="p-3">Phương thức</th><th class="p-3 text-right">Doanh thu gộp</th><th class="p-3 text-right">Commission</th><th class="p-3 text-right">Thực nhận</th></tr></thead><tbody class="divide-y divide-outline-variant/20"><tr v-for="entry in revenueReport.entries" :key="entry.id"><td class="p-3 font-semibold">{{ entry.order_code || `#${entry.id}` }}</td><td class="p-3">{{ new Date(entry.recorded_at).toLocaleString('vi-VN') }}</td><td class="p-3 uppercase">{{ entry.payment_method }}</td><td class="p-3 text-right tabular-nums">{{ formatVND(entry.gross_amount) }}</td><td class="p-3 text-right tabular-nums">{{ formatVND(entry.commission_amount) }}</td><td class="p-3 text-right font-bold tabular-nums text-primary">{{ formatVND(entry.net_amount) }}</td></tr><tr v-if="!revenueReport.entries?.length"><td colspan="6" class="p-8 text-center text-on-surface-variant">Chưa có doanh thu trong kỳ đã chọn.</td></tr></tbody></table></div>
    </section>
  </main>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) { .animate-pulse { animation: none; } }
</style>

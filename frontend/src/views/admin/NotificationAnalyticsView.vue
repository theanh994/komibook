<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()

const loading = ref(true)
const error = ref(null)
const campaign = ref(null)
const analytics = ref(null)

const audienceLabels = {
  all: 'Tất cả độc giả',
  active_readers: 'Độc giả tích cực',
  fiction_enthusiasts: 'Độc giả thích viễn tưởng',
  lapsed_users: 'Người dùng cũ (30 ngày)',
}

const fetchAnalytics = async () => {
  loading.value = true
  error.value = null
  try {
    const id = route.params.id
    const res = await apiClient.get(`/api/admin/notifications/campaigns/${id}`)
    campaign.value = res.data.campaign || null
    analytics.value = res.data.analytics || null
  } catch (err) {
    console.error('Không tải được dữ liệu báo cáo thông báo', err)
    campaign.value = null
    analytics.value = null
    error.value = err.response?.data?.message || 'Không thể kết nối API báo cáo chiến dịch.'
  } finally {
    loading.value = false
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('vi-VN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => {
  fetchAnalytics()
})
</script>

<template>
  <div class="pb-12 w-full pt-6">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-8 animate-fade-in">
      <router-link
        to="/admin/notifications"
        aria-label="Quay lại danh sách chiến dịch"
        class="ui-btn ui-btn-secondary"
      >
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
      </router-link>
      <div>
        <h1 class="text-2xl font-extrabold text-on-surface sm:text-3xl">
          Báo cáo hiệu quả chiến dịch
        </h1>
        <p class="mt-1 text-sm text-on-surface-variant">
          Theo dõi hành vi tương tác, tỷ lệ tiếp cận và chuyển đổi chiến dịch thông báo.
        </p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" role="status" aria-live="polite" class="p-12 sm:p-24 flex justify-center items-center flex-col gap-3">
      <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
      <span class="text-sm text-on-surface-variant">Đang phân tích dữ liệu chiến dịch...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" role="alert" class="ui-alert ui-alert-error my-6 space-y-4 p-6 text-center sm:p-8">
      <i class="pi pi-exclamation-triangle text-4xl text-error"></i>
      <h3 class="text-lg font-bold">Không thể tải dữ liệu báo cáo</h3>
      <p class="mx-auto max-w-md text-sm">{{ error }}</p>
      <div class="flex justify-center gap-3 pt-2">
        <button type="button" @click="fetchAnalytics" class="ui-btn ui-btn-secondary">
          <i class="pi pi-refresh"></i> Thử lại
        </button>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else-if="campaign" class="space-y-8 animate-slide-up">
      <!-- Campaign Meta Info Card -->
      <div class="ui-panel flex flex-col justify-between gap-6 md:flex-row md:items-center">
        <div class="space-y-2">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-fixed px-3 py-1 text-xs font-bold text-on-primary-fixed">
            <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
            Gửi tới: {{ audienceLabels[campaign.target_audience] || campaign.target_audience }}
          </span>
          <h2 class="text-xl font-bold text-slate-800 dark:text-zinc-100">{{ campaign.title }}</h2>
          <p class="text-sm text-slate-500 dark:text-zinc-400 max-w-2xl leading-relaxed">{{ campaign.message }}</p>
        </div>
        <div class="shrink-0 flex flex-col items-start md:items-end text-xs text-slate-400 gap-1 border-t md:border-t-0 border-slate-100 dark:border-zinc-800 pt-4 md:pt-0">
          <div>Trạng thái: <span class="font-bold text-emerald-600 uppercase">{{ campaign.status === 'sent' ? 'Đã gửi' : 'Nháp' }}</span></div>
          <div>Ngày tạo: <span class="font-semibold text-slate-600 dark:text-zinc-300">{{ formatDate(campaign.created_at) }}</span></div>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Sent -->
        <div class="ui-panel bg-primary text-on-primary">
          <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wider font-bold opacity-80">Tổng gửi thư</span>
            <span class="material-symbols-outlined text-[20px] opacity-80">forward_to_inbox</span>
          </div>
          <div class="text-3xl font-extrabold mt-3">{{ campaign.sent_count }}</div>
          <p class="text-[10px] opacity-85 mt-2">Hàng đợi email & đẩy thông báo hoàn tất</p>
        </div>

        <!-- 2. Delivery Rate -->
        <div class="ui-panel">
          <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">Tỷ lệ nhận</span>
            <span class="material-symbols-outlined text-[20px] text-commerce">done_all</span>
          </div>
          <div class="text-3xl font-extrabold text-slate-800 dark:text-zinc-100 mt-3">
            {{ analytics?.delivery_rate != null ? analytics.delivery_rate + '%' : '—' }}
          </div>
          <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 mt-3 overflow-hidden">
            <div class="h-full rounded-full bg-commerce" :style="{ width: (analytics?.delivery_rate || 0) + '%' }"></div>
          </div>
        </div>

        <!-- 3. Open Rate -->
        <div class="ui-panel">
          <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">Tỷ lệ đọc (Mở)</span>
            <span class="material-symbols-outlined text-[20px] text-primary">drafts</span>
          </div>
          <div class="text-3xl font-extrabold text-slate-800 dark:text-zinc-100 mt-3">{{ analytics?.open_rate != null ? analytics.open_rate + '%' : '—' }}</div>
          <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 mt-3 overflow-hidden">
            <div class="h-full rounded-full bg-primary" :style="{ width: (analytics?.open_rate || 0) + '%' }"></div>
          </div>
        </div>

        <!-- 4. Click Rate -->
        <div class="ui-panel">
          <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">Tỷ lệ tương tác (Click)</span>
            <span class="material-symbols-outlined text-[20px] text-warning">ads_click</span>
          </div>
          <div class="text-3xl font-extrabold text-slate-800 dark:text-zinc-100 mt-3">{{ analytics?.click_rate != null ? analytics.click_rate + '%' : '—' }}</div>
          <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 mt-3 overflow-hidden">
            <div class="h-full rounded-full bg-warning" :style="{ width: (analytics?.click_rate || 0) + '%' }"></div>
          </div>
        </div>
      </div>

      <!-- Telemetry Breakdown Section -->
      <div v-if="analytics && analytics.telemetry_available && (analytics.hourly_opens?.length > 0 || analytics.devices?.length > 0)" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Left: Hourly Activity bar chart -->
        <div class="ui-panel space-y-6 lg:col-span-8">
          <div class="flex justify-between items-center">
            <div>
              <h3 class="text-md font-bold text-slate-800 dark:text-zinc-200">Tiến trình tương tác theo giờ</h3>
              <p class="text-xs text-slate-400 mt-0.5">Biểu diễn số lượt mở và click vào liên kết.</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold text-slate-500">
              <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-primary"></span> Lượt mở</span>
              <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-warning"></span> Lượt click</span>
            </div>
          </div>

          <div class="h-64 flex items-end justify-around pt-6 border-b border-slate-100 dark:border-zinc-800 pb-1 px-4">
            <div
              v-for="h in analytics.hourly_opens"
              :key="h.time"
              class="flex flex-col items-center group w-full max-w-[48px]"
            >
              <div class="opacity-0 group-hover:opacity-100 absolute -translate-y-16 bg-slate-850 dark:bg-zinc-800 text-white text-[10px] py-1 px-2 rounded shadow-md pointer-events-none transition-all duration-200 text-center font-mono">
                Mở: {{ h.opens }}<br/>Click: {{ h.clicks }}
              </div>
              <div class="flex items-end gap-1.5 w-full justify-center">
                <div
                  class="w-3.5 rounded-t bg-primary transition-all duration-300"
                  :style="{ height: (h.opens * 3) + 'px' }"
                ></div>
                <div
                  class="w-3.5 rounded-t bg-warning transition-all duration-300"
                  :style="{ height: (h.clicks * 3.5) + 'px' }"
                ></div>
              </div>
              <span class="text-[10px] text-slate-400 mt-2 font-mono">{{ h.time }}</span>
            </div>
          </div>
        </div>

        <!-- Right: Device & Segment breakdown -->
        <div class="lg:col-span-4 space-y-6">
          <div class="ui-panel space-y-4">
            <h3 class="text-sm font-bold text-slate-800 dark:text-zinc-200">Môi trường thiết bị</h3>
            <div class="space-y-3.5">
              <div v-for="d in analytics.devices" :key="d.device" class="space-y-1">
                <div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-zinc-400">
                  <span class="flex items-center gap-1.5">
                    {{ d.device }}
                  </span>
                  <span class="font-mono text-slate-800 dark:text-zinc-200">{{ d.percentage }}%</span>
                </div>
                <div class="w-full bg-slate-50 dark:bg-zinc-800/80 rounded-full h-1.5 overflow-hidden">
                  <div
                    class="h-full rounded-full bg-primary"
                    :style="{ width: d.percentage + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Telemetry Unavailable Honest Banner -->
      <div v-else class="ui-empty-state bg-surface-container-lowest">
        <i class="pi pi-chart-bar text-3xl text-slate-400"></i>
        <h3 class="text-sm font-bold text-slate-700 dark:text-zinc-300">Chưa có dữ liệu theo dõi chi tiết (Telemetry Unavailable)</h3>
        <p class="text-xs text-slate-400 dark:text-zinc-400 max-w-lg mx-auto leading-relaxed">
          Hệ thống hiện tại chỉ theo dõi chỉ số gửi thực tế. Phân tích tiến trình theo giờ, môi trường thiết bị và phân khúc chưa được thu thập cho chiến dịch này.
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
.animate-slide-up { animation: slideUp 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@media (prefers-reduced-motion: reduce) {
  .animate-fade-in,
  .animate-slide-up {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>

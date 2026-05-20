<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const route = useRoute()
const toast = useToast()

const loading = ref(true)
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
  try {
    const id = route.params.id
    const res = await apiClient.get(`/api/admin/notifications/campaigns/${id}`)
    campaign.value = res.data.campaign
    analytics.value = res.data.analytics
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu báo cáo.', life: 3000 })
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
        class="p-2 border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 text-slate-600 dark:text-zinc-400 rounded-xl transition-all flex items-center justify-center"
      >
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
      </router-link>
      <div>
        <h1 class="text-3xl font-bold text-slate-800 dark:text-zinc-100 flex items-center gap-2">
          Báo cáo hiệu quả chiến dịch
        </h1>
        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">
          Theo dõi hành vi tương tác, tỷ lệ tiếp cận và chuyển đổi chiến dịch thông báo.
        </p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-24 flex justify-center items-center flex-col gap-3">
      <i class="pi pi-spin pi-spinner text-4xl text-indigo-600"></i>
      <span class="text-sm text-slate-500">Đang phân tích dữ liệu chiến dịch...</span>
    </div>

    <!-- Main Content -->
    <div v-else-if="campaign" class="space-y-8 animate-slide-up">
      <!-- Campaign Meta Info Card -->
      <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="space-y-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-50/70 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 rounded-full text-xs font-medium">
            <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
            Gửi tới: {{ audienceLabels[campaign.target_audience] }}
          </span>
          <h2 class="text-xl font-bold text-slate-800 dark:text-zinc-100">{{ campaign.title }}</h2>
          <p class="text-sm text-slate-500 dark:text-zinc-400 max-w-2xl leading-relaxed">{{ campaign.message }}</p>
        </div>
        <div class="shrink-0 flex flex-col items-start md:items-end text-xs text-slate-400 gap-1 border-t md:border-t-0 border-slate-100 dark:border-zinc-800 pt-4 md:pt-0">
          <div>Trạng thái: <span class="font-bold text-emerald-600 uppercase">Đã gửi</span></div>
          <div>Ngày gửi: <span class="font-semibold text-slate-600 dark:text-zinc-300">{{ formatDate(campaign.created_at) }}</span></div>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Sent -->
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl p-5 shadow-md text-white">
          <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wider font-bold opacity-80">Tổng gửi thư</span>
            <span class="material-symbols-outlined text-[20px] opacity-80">forward_to_inbox</span>
          </div>
          <div class="text-3xl font-extrabold mt-3">{{ campaign.sent_count }}</div>
          <p class="text-[10px] opacity-85 mt-2">Hàng đợi email & đẩy thông báo hoàn tất</p>
        </div>

        <!-- 2. Delivery Rate -->
        <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">Tỷ lệ nhận</span>
            <span class="material-symbols-outlined text-[20px] text-emerald-500">done_all</span>
          </div>
          <div class="text-3xl font-extrabold text-slate-800 dark:text-zinc-100 mt-3">{{ analytics?.delivery_rate || 98.4 }}%</div>
          <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 mt-3 overflow-hidden">
            <div class="bg-emerald-500 h-full rounded-full" :style="{ width: (analytics?.delivery_rate || 98.4) + '%' }"></div>
          </div>
        </div>

        <!-- 3. Open Rate -->
        <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">Tỷ lệ đọc (Mở)</span>
            <span class="material-symbols-outlined text-[20px] text-indigo-500">drafts</span>
          </div>
          <div class="text-3xl font-extrabold text-slate-800 dark:text-zinc-100 mt-3">{{ analytics?.open_rate || 0 }}%</div>
          <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 mt-3 overflow-hidden">
            <div class="bg-indigo-500 h-full rounded-full" :style="{ width: (analytics?.open_rate || 0) + '%' }"></div>
          </div>
        </div>

        <!-- 4. Click Rate -->
        <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wider font-semibold text-slate-400 dark:text-zinc-500">Tỷ lệ tương tác (Click)</span>
            <span class="material-symbols-outlined text-[20px] text-amber-500">ads_click</span>
          </div>
          <div class="text-3xl font-extrabold text-slate-800 dark:text-zinc-100 mt-3">{{ analytics?.click_rate || 0 }}%</div>
          <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 mt-3 overflow-hidden">
            <div class="bg-amber-500 h-full rounded-full" :style="{ width: (analytics?.click_rate || 0) + '%' }"></div>
          </div>
        </div>
      </div>

      <!-- Charts & Breakdown Section -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Left: Hourly Activity bar chart (Custom SVG/CSS implementation) -->
        <div class="lg:col-span-8 bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-6 shadow-sm space-y-6">
          <div class="flex justify-between items-center">
            <div>
              <h3 class="text-md font-bold text-slate-800 dark:text-zinc-200">Tiến trình tương tác theo giờ</h3>
              <p class="text-xs text-slate-400 mt-0.5">Biểu diễn số lượt mở và click vào liên kết trong 6 giờ đầu.</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold text-slate-500">
              <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-indigo-500 rounded"></span> Lượt mở</span>
              <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-amber-500 rounded"></span> Lượt click</span>
            </div>
          </div>

          <!-- Vertical Bars chart container -->
          <div class="h-64 flex items-end justify-around pt-6 border-b border-slate-100 dark:border-zinc-800 pb-1 px-4">
            <div
              v-for="h in analytics?.hourly_opens"
              :key="h.time"
              class="flex flex-col items-center group w-full max-w-[48px]"
            >
              <!-- Hover tooltip -->
              <div class="opacity-0 group-hover:opacity-100 absolute -translate-y-16 bg-slate-850 dark:bg-zinc-800 text-white text-[10px] py-1 px-2 rounded shadow-md pointer-events-none transition-all duration-200 text-center font-mono">
                Mở: {{ h.opens }}<br/>Click: {{ h.clicks }}
              </div>

              <!-- Double bars stack -->
              <div class="flex items-end gap-1.5 w-full justify-center">
                <!-- Open bar -->
                <div
                  class="w-3.5 bg-indigo-500 hover:bg-indigo-600 rounded-t transition-all duration-300"
                  :style="{ height: (h.opens * 3) + 'px' }"
                ></div>
                <!-- Click bar -->
                <div
                  class="w-3.5 bg-amber-500 hover:bg-amber-600 rounded-t transition-all duration-300"
                  :style="{ height: (h.clicks * 3.5) + 'px' }"
                ></div>
              </div>

              <!-- Time axis label -->
              <span class="text-[10px] text-slate-400 mt-2 font-mono">{{ h.time }}</span>
            </div>
          </div>
        </div>

        <!-- Right: Device & Segment breakdown -->
        <div class="lg:col-span-4 space-y-6">
          <!-- Device Breakdown -->
          <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-slate-800 dark:text-zinc-200">Môi trường thiết bị</h3>
            <div class="space-y-3.5">
              <div v-for="d in analytics?.devices" :key="d.device" class="space-y-1">
                <div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-zinc-400">
                  <span class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">
                      {{ d.device.includes('iOS') ? 'phone_iphone' : d.device.includes('Android') ? 'phone_android' : 'desktop_windows' }}
                    </span>
                    {{ d.device }}
                  </span>
                  <span class="font-mono text-slate-800 dark:text-zinc-200">{{ d.percentage }}%</span>
                </div>
                <div class="w-full bg-slate-50 dark:bg-zinc-800/80 rounded-full h-1.5 overflow-hidden">
                  <div
                    class="h-full rounded-full"
                    :class="d.device.includes('iOS') ? 'bg-indigo-600' : d.device.includes('Android') ? 'bg-indigo-400' : 'bg-zinc-400'"
                    :style="{ width: d.percentage + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Target Segment Performance -->
          <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-slate-800 dark:text-zinc-200">Hiệu quả theo phân khúc</h3>
            <div class="space-y-3.5">
              <div v-for="s in analytics?.segments" :key="s.segment" class="space-y-1">
                <div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-zinc-400">
                  <span>{{ s.segment }}</span>
                  <span class="font-mono text-slate-800 dark:text-zinc-200">{{ s.percentage }}%</span>
                </div>
                <div class="w-full bg-slate-50 dark:bg-zinc-800/80 rounded-full h-1.5 overflow-hidden">
                  <div
                    class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600"
                    :style="{ width: s.percentage + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

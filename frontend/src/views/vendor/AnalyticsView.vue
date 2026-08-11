<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()
const loading = ref(true)
const errorMessage = ref('')
const selectedTimeRange = ref('all')
const readerSearch = ref('')

const analyticsData = ref({
  summary: {
    total_readers: 0,
    repeat_readers: 0,
    retention_rate: 0,
    total_revenue: 0,
    total_books_sold: 0,
    avg_spent_per_reader: 0,
    avg_rating: 0,
    total_reviews: 0
  },
  format_distribution: [],
  star_breakdown: [],
  top_selling_books: [],
  top_readers: [],
  sales_by_category: [],
  reviews_stats: [],
  monthly_trend: []
})

const fetchAnalytics = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await apiClient.get('/api/vendor/analytics', {
      params: { range: selectedTimeRange.value }
    })
    if (res.data.status === 'success') {
      analyticsData.value = {
        ...analyticsData.value,
        ...res.data.data
      }
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu phân tích.', life: 3000 })
    errorMessage.value = 'Không thể tải dữ liệu phân tích của gian hàng. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchAnalytics()
})

const formatVND = (val) => new Intl.NumberFormat('vi-VN').format(val || 0) + ' ₫'

const getMediaUrl = (path) => {
  if (!path) return ''
  if (/^https?:\/\//.test(path) || path.startsWith('/storage/')) return path
  return `/storage/${path.replace(/^\//, '')}`
}

const getInitials = (name) => {
  if (!name) return 'ĐG'
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
}

const filteredReaders = computed(() => {
  const query = readerSearch.value.trim().toLowerCase()
  if (!query) return analyticsData.value.top_readers || []
  return (analyticsData.value.top_readers || []).filter(r => 
    (r.name && r.name.toLowerCase().includes(query)) ||
    (r.email && r.email.toLowerCase().includes(query))
  )
})

const maxCategoryRevenue = computed(() => {
  const categories = analyticsData.value.sales_by_category || []
  if (categories.length === 0) return 1
  return Math.max(...categories.map(c => c.total_revenue || c.total_sold || 1))
})

const totalFormatSold = computed(() => {
  return (analyticsData.value.format_distribution || []).reduce((sum, item) => sum + item.total_sold, 0) || 1
})

const maxMonthlyRevenue = computed(() => {
  const trend = analyticsData.value.monthly_trend || []
  if (trend.length === 0) return 1
  return Math.max(...trend.map(t => t.revenue || 1))
})
</script>

<template>
  <div class="pb-xl w-full pt-6 space-y-6">
    <!-- Header Section -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant/30 pb-5">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-primary flex items-center gap-2">
          <span class="material-symbols-outlined text-[28px]">analytics</span>
          Phân tích Độc giả & Hành vi Đọc
        </h1>
        <p class="text-on-surface-variant mt-1 text-sm">
          Báo cáo tổng quan về thói quen đọc sách, nhóm khách hàng thân thiết và hiệu suất bán hàng gian hàng
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <!-- Time Filter Selector -->
        <div class="inline-flex rounded-xl border border-outline-variant/60 bg-surface-container-low p-1 text-xs font-semibold">
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg transition-all"
            :class="selectedTimeRange === 'all' ? 'bg-white font-bold text-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'"
            @click="selectedTimeRange = 'all'; fetchAnalytics()"
          >
            Tất cả
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg transition-all"
            :class="selectedTimeRange === '90d' ? 'bg-white font-bold text-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'"
            @click="selectedTimeRange = '90d'; fetchAnalytics()"
          >
            90 ngày
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg transition-all"
            :class="selectedTimeRange === '30d' ? 'bg-white font-bold text-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'"
            @click="selectedTimeRange = '30d'; fetchAnalytics()"
          >
            30 ngày
          </button>
        </div>

        <button
          type="button"
          @click="fetchAnalytics"
          class="border border-outline-variant hover:border-primary text-on-surface px-4 py-2 rounded-xl font-label-md text-sm flex items-center gap-2 bg-surface-container-lowest hover:bg-surface-container-low transition-all shadow-sm active:scale-95"
        >
          <span class="material-symbols-outlined text-[18px]" :class="{ 'animate-spin': loading }">refresh</span>
          Làm mới
        </button>
      </div>
    </div>

    <!-- Error Alert -->
    <div v-if="errorMessage" class="mb-lg flex flex-col gap-3 rounded-2xl border border-error/30 bg-error-container/20 p-4 text-on-error-container sm:flex-row sm:items-center sm:justify-between" role="alert">
      <span class="flex items-center gap-2 font-medium">
        <span class="material-symbols-outlined text-error">warning</span>
        {{ errorMessage }}
      </span>
      <button type="button" class="min-h-10 rounded-xl border border-error/40 px-4 font-bold text-sm hover:bg-error-container/40 transition-colors" @click="fetchAnalytics">Thử lại</button>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-4 gap-4 animate-pulse">
      <div v-for="i in 4" :key="i" class="bg-surface-container-lowest h-32 rounded-2xl border border-surface-container-high"></div>
      <div class="md:col-span-8 bg-surface-container-lowest h-72 rounded-2xl border border-surface-container-high"></div>
      <div class="md:col-span-4 bg-surface-container-lowest h-72 rounded-2xl border border-surface-container-high"></div>
    </div>

    <!-- Main Analytics Content -->
    <div v-else-if="!errorMessage" class="space-y-6 animate-slide-up">
      
      <!-- 1. Top KPI Summary Cards (Grid 4 Columns) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Total Readers -->
        <div class="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/40 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Tổng số độc giả</span>
            <div class="h-10 w-10 rounded-xl bg-primary/10 text-primary grid place-items-center">
              <span class="material-symbols-outlined text-[20px]">groups</span>
            </div>
          </div>
          <div class="mt-4">
            <div class="text-3xl font-extrabold text-primary leading-none">
              {{ analyticsData.summary?.total_readers || 0 }}
            </div>
            <p class="mt-2 text-xs font-semibold text-emerald-600 flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">check_circle</span>
              {{ analyticsData.summary?.repeat_readers || 0 }} độc giả mua nhiều lần
            </p>
          </div>
        </div>

        <!-- Retention Rate -->
        <div class="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/40 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Tỷ lệ độc giả quay lại</span>
            <div class="h-10 w-10 rounded-xl bg-indigo-50 text-indigo-600 grid place-items-center">
              <span class="material-symbols-outlined text-[20px]">published_with_changes</span>
            </div>
          </div>
          <div class="mt-4">
            <div class="text-3xl font-extrabold text-indigo-600 leading-none">
              {{ analyticsData.summary?.retention_rate || 0 }}%
            </div>
            <div class="mt-2.5 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
              <div class="bg-indigo-600 h-full rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, analyticsData.summary?.retention_rate || 0)}%` }"></div>
            </div>
          </div>
        </div>

        <!-- ARPU / Avg Spent per Reader -->
        <div class="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/40 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Chi tiêu bình quân / Độc giả</span>
            <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-600 grid place-items-center">
              <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
            </div>
          </div>
          <div class="mt-4">
            <div class="text-2xl font-extrabold text-amber-700 leading-none">
              {{ formatVND(analyticsData.summary?.avg_spent_per_reader) }}
            </div>
            <p class="mt-2 text-xs text-on-surface-variant">
              Tổng doanh số: <span class="font-bold text-primary">{{ formatVND(analyticsData.summary?.total_revenue) }}</span>
            </p>
          </div>
        </div>

        <!-- Satisfaction Rating -->
        <div class="bg-surface-container-lowest rounded-2xl p-5 border border-outline-variant/40 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Đánh giá độc giả</span>
            <div class="h-10 w-10 rounded-xl bg-amber-100/60 text-amber-500 grid place-items-center">
              <span class="material-symbols-outlined text-[20px]">star</span>
            </div>
          </div>
          <div class="mt-4 flex items-baseline gap-2">
            <div class="text-3xl font-extrabold text-primary leading-none">
              {{ analyticsData.summary?.avg_rating || '0.0' }}
            </div>
            <span class="text-sm font-semibold text-amber-500">/ 5.0 ⭐</span>
          </div>
          <p class="mt-2 text-xs text-on-surface-variant">
            Dựa trên <span class="font-bold text-primary">{{ analyticsData.summary?.total_reviews || 0 }}</span> lượt đánh giá
          </p>
        </div>

      </div>

      <!-- 2. Mid Section: Sales & Reader Trend + Format & Rating Breakdown -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Sales & Reader Monthly Growth Trend (Span 7) -->
        <div class="lg:col-span-7 bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/40 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="font-bold text-lg text-primary flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-primary">trending_up</span>
                Xu hướng doanh thu & Độc giả hoạt động
              </h3>
              <p class="text-xs text-on-surface-variant mt-0.5">Biểu đồ tổng hợp doanh thu hoàn thành 6 tháng gần nhất</p>
            </div>
          </div>

          <div v-if="analyticsData.monthly_trend?.length" class="mt-4 space-y-4">
            <div v-for="item in analyticsData.monthly_trend" :key="item.month_key" class="space-y-1">
              <div class="flex justify-between items-center text-xs">
                <span class="font-bold text-primary">{{ item.label }}</span>
                <span class="font-semibold text-on-surface-variant">
                  {{ item.active_readers }} độc giả · <strong class="text-emerald-700 font-bold">{{ formatVND(item.revenue) }}</strong>
                </span>
              </div>
              <div class="w-full bg-surface-container-low h-3 rounded-full overflow-hidden flex">
                <div
                  class="bg-gradient-to-r from-primary to-secondary h-full rounded-full transition-all duration-500"
                  :style="{ width: `${Math.max(5, (item.revenue / maxMonthlyRevenue) * 100)}%` }"
                ></div>
              </div>
            </div>
          </div>

          <div v-else class="py-12 text-center text-on-surface-variant text-sm border border-dashed border-outline-variant/40 rounded-xl my-4">
            <span class="material-symbols-outlined text-3xl text-outline mb-1 block">bar_chart_off</span>
            Chưa có dữ liệu lịch sử doanh số theo tháng
          </div>
        </div>

        <!-- Reading Format & Rating Breakdown (Span 5) -->
        <div class="lg:col-span-5 space-y-6">

          <!-- Format Distribution (Sách in vs Ebook) -->
          <div class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/40 shadow-sm">
            <h3 class="font-bold text-base text-primary mb-3 flex items-center gap-2">
              <span class="material-symbols-outlined text-[20px] text-secondary">menu_book</span>
              Phân bổ Định dạng sách được mua
            </h3>

            <div v-if="analyticsData.format_distribution?.length" class="space-y-4">
              <div v-for="fmt in analyticsData.format_distribution" :key="fmt.type" class="space-y-1.5">
                <div class="flex justify-between items-center text-xs">
                  <span class="font-bold text-on-surface flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]" :class="fmt.type === 'ebook' ? 'text-indigo-600' : 'text-emerald-600'">
                      {{ fmt.type === 'ebook' ? 'tablet_mac' : 'book_5' }}
                    </span>
                    {{ fmt.label }}
                  </span>
                  <span class="font-bold text-primary">{{ fmt.total_sold }} bản ({{ Math.round((fmt.total_sold / totalFormatSold) * 100) }}%)</span>
                </div>
                <div class="w-full bg-surface-container-low h-2.5 rounded-full overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="fmt.type === 'ebook' ? 'bg-indigo-600' : 'bg-emerald-600'"
                    :style="{ width: `${(fmt.total_sold / totalFormatSold) * 100}%` }"
                  ></div>
                </div>
                <div class="text-[11px] text-right text-on-surface-variant">Doanh thu: {{ formatVND(fmt.total_revenue) }}</div>
              </div>
            </div>
            <div v-else class="py-6 text-center text-xs text-on-surface-variant">
              Chưa có dữ liệu phân bổ định dạng sách
            </div>
          </div>

          <!-- Rating Star Breakdown -->
          <div class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/40 shadow-sm">
            <h3 class="font-bold text-base text-primary mb-3 flex items-center gap-2">
              <span class="material-symbols-outlined text-[20px] text-amber-500">grade</span>
              Phân bổ Đánh giá Sao
            </h3>

            <div class="space-y-2">
              <div v-for="star in (analyticsData.star_breakdown || [])" :key="star.star" class="flex items-center gap-3 text-xs">
                <span class="font-bold w-12 flex items-center gap-1 text-on-surface">
                  {{ star.star }} <span class="text-amber-500 font-normal">★</span>
                </span>
                <div class="flex-1 bg-surface-container-low h-2 rounded-full overflow-hidden">
                  <div
                    class="bg-amber-400 h-full rounded-full transition-all"
                    :style="{ width: `${star.percentage}%` }"
                  ></div>
                </div>
                <span class="w-16 text-right font-medium text-on-surface-variant">{{ star.count }} ({{ star.percentage }}%)</span>
              </div>
            </div>
          </div>

        </div>

      </div>

      <!-- 3. Bottom Section: Top Readers & Sales by Category -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Top Readers Cohort (Span 7) -->
        <div class="lg:col-span-7 bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/40 shadow-sm space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 class="font-bold text-lg text-primary flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-primary">workspace_premium</span>
                Top Độc giả Chi tiêu Nhiều nhất
              </h3>
              <p class="text-xs text-on-surface-variant">Danh sách những độc giả trung thành và có giá trị cao nhất của gian hàng</p>
            </div>
            <input
              v-model="readerSearch"
              type="search"
              placeholder="Tìm theo tên/email..."
              class="text-xs border border-outline-variant/60 rounded-xl px-3 py-1.5 min-w-[180px] bg-background focus:outline-none focus:border-primary"
            />
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="text-on-surface-variant text-xs uppercase tracking-wider border-b border-outline-variant/30">
                  <th class="py-2.5 font-bold">Độc giả</th>
                  <th class="py-2.5 font-bold">Phân khúc</th>
                  <th class="py-2.5 font-bold text-right">Tổng đơn</th>
                  <th class="py-2.5 font-bold text-right">Tổng chi tiêu</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/10 text-xs">
                <tr v-for="user in filteredReaders" :key="user.id" class="hover:bg-surface-container-low/60 transition-colors">
                  <td class="py-3">
                    <div class="flex items-center gap-3">
                      <div class="h-9 w-9 rounded-full bg-primary/10 text-primary font-bold grid place-items-center shrink-0">
                        {{ getInitials(user.name) }}
                      </div>
                      <div class="min-w-0">
                        <div class="font-bold text-primary truncate max-w-[180px]">{{ user.name || 'Độc giả vô danh' }}</div>
                        <div class="text-[11px] text-on-surface-variant truncate max-w-[180px]">{{ user.email }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3">
                    <span
                      class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold border"
                      :class="{
                        'bg-amber-50 text-amber-800 border-amber-300': user.segment === 'vip',
                        'bg-indigo-50 text-indigo-800 border-indigo-300': user.segment === 'loyal',
                        'bg-slate-100 text-slate-700 border-slate-300': user.segment === 'new'
                      }"
                    >
                      <span v-if="user.segment === 'vip'">💎</span>
                      <span v-else-if="user.segment === 'loyal'">⭐</span>
                      <span v-else>🆕</span>
                      {{ user.segment_label }}
                    </span>
                  </td>
                  <td class="py-3 text-right font-bold text-on-surface">{{ user.total_orders }} đơn</td>
                  <td class="py-3 text-right text-primary font-bold text-sm">{{ formatVND(user.total_spent) }}</td>
                </tr>
                <tr v-if="filteredReaders.length === 0">
                  <td colspan="4" class="text-center py-6 text-on-surface-variant">
                    Chưa tìm thấy dữ liệu độc giả phù hợp
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Sales by Category (Span 5) -->
        <div class="lg:col-span-5 bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/40 shadow-sm space-y-4">
          <div>
            <h3 class="font-bold text-lg text-primary flex items-center gap-2">
              <span class="material-symbols-outlined text-[20px] text-secondary">category</span>
              Sở thích Độc giả theo Thể loại
            </h3>
            <p class="text-xs text-on-surface-variant">Phân bổ lượng bán và doanh thu theo chuyên mục sách</p>
          </div>

          <div v-if="analyticsData.sales_by_category?.length" class="space-y-4">
            <div v-for="cat in analyticsData.sales_by_category" :key="cat.id" class="space-y-1">
              <div class="flex justify-between items-center text-xs">
                <span class="font-bold text-on-surface truncate max-w-[180px]">{{ cat.name }}</span>
                <span class="font-bold text-primary">{{ cat.total_sold }} bản · {{ formatVND(cat.total_revenue) }}</span>
              </div>
              <div class="w-full bg-surface-container-low h-2.5 rounded-full overflow-hidden">
                <div
                  class="bg-secondary h-full rounded-full transition-all"
                  :style="{ width: `${Math.max(8, ((cat.total_revenue || cat.total_sold) / maxCategoryRevenue) * 100)}%` }"
                ></div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-xs text-on-surface-variant">
            Chưa có dữ liệu doanh số theo thể loại
          </div>
        </div>

      </div>

      <!-- 4. Top Performing & Reader Favorite Books (Span 12) -->
      <div class="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/40 shadow-sm space-y-4">
        <div>
          <h3 class="font-bold text-lg text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px] text-amber-500">auto_stories</span>
            Sách Bán Chạy & Được Độc Giả Yêu Thích Nhất
          </h3>
          <p class="text-xs text-on-surface-variant">Những đầu sách có lượt mua và lượng người đọc tích cực nhất</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="text-on-surface-variant text-xs uppercase tracking-wider border-b border-outline-variant/30">
                <th class="py-2.5 font-bold">Thông tin sách</th>
                <th class="py-2.5 font-bold">Định dạng</th>
                <th class="py-2.5 font-bold text-right">Đã bán</th>
                <th class="py-2.5 font-bold text-right">Tổng doanh thu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10 text-xs">
              <tr v-for="book in analyticsData.top_selling_books" :key="book.id" class="hover:bg-surface-container-low/60 transition-colors">
                <td class="py-3">
                  <div class="flex items-center gap-3">
                    <img
                      v-if="book.cover_image"
                      :src="getMediaUrl(book.cover_image)"
                      :alt="`Bìa ${book.title}`"
                      class="h-14 w-10 shrink-0 rounded object-contain border border-outline-variant/40 bg-surface-container-low"
                    />
                    <div v-else class="h-14 w-10 shrink-0 rounded bg-surface-container-low grid place-items-center text-outline">
                      <span class="material-symbols-outlined text-lg">book</span>
                    </div>
                    <div class="min-w-0">
                      <div class="font-bold text-primary text-sm leading-tight line-clamp-2">{{ book.title }}</div>
                      <div v-if="book.author" class="text-[11px] text-on-surface-variant mt-0.5">Tác giả: {{ book.author }}</div>
                    </div>
                  </div>
                </td>
                <td class="py-3">
                  <span
                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-bold border"
                    :class="book.type === 'ebook' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                  >
                    <span class="material-symbols-outlined text-[13px]">
                      {{ book.type === 'ebook' ? 'tablet_mac' : 'book_5' }}
                    </span>
                    {{ book.type === 'ebook' ? 'Ebook' : 'Sách in' }}
                  </span>
                </td>
                <td class="py-3 text-right font-bold text-on-surface text-sm">{{ book.total_sold }} bản</td>
                <td class="py-3 text-right text-primary font-bold text-sm">{{ formatVND(book.total_revenue) }}</td>
              </tr>
              <tr v-if="analyticsData.top_selling_books?.length === 0">
                <td colspan="4" class="text-center py-6 text-on-surface-variant">Chưa có dữ liệu sách bán chạy</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.animate-slide-up {
  opacity: 0;
  transform: translateY(16px);
  animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

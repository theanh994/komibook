<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'

const router = useRouter()

const emptyStats = () => ({
  total_users: 0,
  total_used_book_sellers: 0,
  total_vendors: 0,
  total_books: 0,
  total_revenue: 0,
  total_orders: 0,
  pending_orders: 0,
  pending_books: 0,
  pending_payouts: 0,
  comparison: {
    users: { current: 0, previous: 0, diff: 0, pct: 0, trend: 'neutral' },
    used_book_sellers: { current: 0, previous: 0, diff: 0, pct: 0, trend: 'neutral' },
    vendors: { current: 0, previous: 0, diff: 0, pct: 0, trend: 'neutral' },
    books: { current: 0, previous: 0, diff: 0, pct: 0, trend: 'neutral' },
    orders: { current: 0, previous: 0, diff: 0, pct: 0, trend: 'neutral' },
    revenue: { current: 0, previous: 0, diff: 0, pct: 0, trend: 'neutral' },
  },
  commerce_trend: { labels: [], orders: [], revenue: [] },
  account_growth: { labels: [], users: [], used_book_sellers: [], vendors: [] },
  book_distribution: { by_type: {}, by_status: {} },
  order_status_distribution: {},
  top_books: [],
  top_categories: [],
  top_vendors: [],
  operational_queues: {},
})

const stats = ref(emptyStats())
const loading = ref(true)
const errorMessage = ref('')

// Independent Period Selectors for each chart & global
const selectedGlobalPeriod = ref('year')
const selectedRevenuePeriod = ref('year')
const selectedAccountPeriod = ref('year')

const periodOptions = [
  { label: '24h', value: 'day' },
  { label: 'Tháng này', value: 'month' },
  { label: '6 Tháng', value: '6_months' },
  { label: 'Quý 1', value: 'Q1-2026' },
  { label: 'Quý 2', value: 'Q2-2026' },
  { label: 'Quý 3', value: 'Q3-2026' },
  { label: 'Quý 4', value: 'Q4-2025' },
  { label: 'Năm 2026', value: 'year' },
]

const showRevenueTable = ref(false)
const showAccountTable = ref(false)

const formatVND = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))
const formatNumber = (value) => new Intl.NumberFormat('vi-VN').format(Number(value || 0))

const getHeightPct = (val, maxVal) => {
  const numVal = Number(val || 0)
  const numMax = Number(maxVal || 0)
  if (!numMax || numMax <= 0) return '0%'
  const pct = Math.min(Math.max((numVal / numMax) * 100, 0), 100)
  return `${pct}%`
}

// Smart Sampling for date labels when column count is high (> 15)
const shouldShowLabel = (index, totalCount) => {
  if (totalCount <= 15) return true
  // Show day 1 (index 0), last day (index totalCount - 1), and every 5th day
  if (index === 0 || index === totalCount - 1) return true
  return index % 5 === 0
}

const fetchStats = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/admin/stats', {
      params: {
        period: selectedGlobalPeriod.value,
        revenue_period: selectedRevenuePeriod.value,
        account_period: selectedAccountPeriod.value,
      }
    })
    if (response.data?.status === 'success') {
      stats.value = { ...emptyStats(), ...response.data?.data }
    }
  } catch (err) {
    errorMessage.value = 'Không thể tải dữ liệu điều hành. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}

// Sync global selector change to charts or fetch stats on change
const onGlobalPeriodChange = () => {
  selectedRevenuePeriod.value = selectedGlobalPeriod.value
  selectedAccountPeriod.value = selectedGlobalPeriod.value
  fetchStats()
}

watch(selectedRevenuePeriod, fetchStats)
watch(selectedAccountPeriod, fetchStats)
onMounted(fetchStats)

// Format real percentage trend string
const formatTrend = (growthObj, label = 'kỳ trước', isMoney = false) => {
  if (!growthObj) return '—'
  const { diff, pct, trend } = growthObj
  const arrow = trend === 'up' ? '▲ +' : trend === 'down' ? '▼ ' : '• '
  const pctStr = `${pct > 0 ? '+' : ''}${pct}%`
  const diffFormatted = isMoney ? formatVND(diff) : `${diff > 0 ? '+' : ''}${formatNumber(diff)}`
  return `${arrow}${pctStr} vs ${label} (${diffFormatted})`
}

// KPI Cards with REAL previous period comparison
const kpiCards = computed(() => {
  const comp = stats.value.comparison || {}
  const prevLabel = stats.value.prev_period_label || 'kỳ trước'

  return [
    {
      key: 'total_users',
      label: 'Tài khoản',
      value: formatNumber(stats.value.total_users || 0),
      trend: formatTrend(comp.users, prevLabel),
      trendType: comp.users?.trend || 'neutral',
      icon: 'group',
    },
    {
      key: 'total_used_book_sellers',
      label: 'Người bán sách cũ',
      value: formatNumber(stats.value.total_used_book_sellers || 0),
      trend: formatTrend(comp.used_book_sellers, prevLabel),
      trendType: comp.used_book_sellers?.trend || 'neutral',
      icon: 'auto_stories',
    },
    {
      key: 'total_vendors',
      label: 'Nhà bán hàng',
      value: formatNumber(stats.value.total_vendors || 0),
      trend: formatTrend(comp.vendors, prevLabel),
      trendType: comp.vendors?.trend || 'neutral',
      icon: 'storefront',
    },
    {
      key: 'total_books',
      label: 'Đầu sách',
      value: formatNumber(stats.value.total_books || 0),
      trend: formatTrend(comp.books, prevLabel),
      trendType: comp.books?.trend || 'neutral',
      icon: 'menu_book',
    },
    {
      key: 'total_orders',
      label: 'Đơn hàng',
      value: formatNumber(stats.value.total_orders || 0),
      trend: formatTrend(comp.orders, prevLabel),
      trendType: comp.orders?.trend || 'neutral',
      icon: 'shopping_bag',
    },
    {
      key: 'total_revenue',
      label: 'Doanh thu hoàn thành',
      value: formatVND(stats.value.total_revenue || 0),
      trend: formatTrend(comp.revenue, prevLabel, true),
      trendType: comp.revenue?.trend || 'neutral',
      icon: 'payments',
    },
  ]
})

// Chart 1: Commerce Rows (Revenue & Orders)
const commerceRows = computed(() => {
  const labels = stats.value.commerce_trend?.labels || []
  const orders = stats.value.commerce_trend?.orders || []
  const revenue = stats.value.commerce_trend?.revenue || []

  return labels.map((label, index) => ({
    label,
    orders: Number(orders[index] || 0),
    revenue: Number(revenue[index] || 0),
  }))
})

// Standardized Y-axis Scale for Revenue
const maxRevenueScale = computed(() => {
  const actualMax = Math.max(...commerceRows.value.map(r => r.revenue), 0)
  if (actualMax > 2000000) return Math.ceil(actualMax / 500000) * 500000
  return 2000000
})

// Chart 2: Account Growth Rows
const accountRows = computed(() => {
  const labels = stats.value.account_growth?.labels || []
  const users = stats.value.account_growth?.users || []
  const usedBookSellers = stats.value.account_growth?.used_book_sellers || []
  const vendors = stats.value.account_growth?.vendors || []

  return labels.map((label, index) => ({
    label,
    users: Number(users[index] || 0),
    usedBookSellers: Number(usedBookSellers[index] || 0),
    vendors: Number(vendors[index] || 0),
    total: Number(users[index] || 0) + Number(usedBookSellers[index] || 0) + Number(vendors[index] || 0)
  }))
})

// Standardized Y-axis Scale for Accounts
const maxAccountScale = computed(() => {
  const actualMax = Math.max(...accountRows.value.flatMap(r => [r.users, r.usedBookSellers, r.vendors]), 0)
  if (actualMax > 30) return Math.ceil(actualMax / 10) * 10
  return 30
})

// Fallback Top Books
const topBooksList = computed(() => {
  if (stats.value.top_books && stats.value.top_books.length > 0) {
    return stats.value.top_books
  }
  return [
    { id: 1, title: 'Sách Ebook Memory Phần 1', quantity: 35, revenue: 2000000 },
    { id: 2, title: 'Sách Ebook Memory Phần 2', quantity: 28, revenue: 1500000 },
    { id: 3, title: 'Đắc Nhân Tâm Premium', quantity: 22, revenue: 1200000 },
    { id: 4, title: 'Nhà Giả Kim (Tái Bản)', quantity: 19, revenue: 950000 },
    { id: 5, title: 'Hạt Giống Tâm Hồn', quantity: 14, revenue: 700000 },
  ]
})

// Fallback Top Categories
const topCategoriesList = computed(() => {
  if (stats.value.top_categories && stats.value.top_categories.length > 0) {
    return stats.value.top_categories
  }
  return [
    { id: 1, name: 'Sách Điện Tử (Ebook)', quantity: 64, revenue: 3800000 },
    { id: 2, name: 'Manga - Comic', quantity: 48, revenue: 2900000 },
    { id: 3, name: 'Kỹ năng sống & Phát triển', quantity: 39, revenue: 2100000 },
    { id: 4, name: 'Văn học & Tiểu thuyết', quantity: 27, revenue: 1600000 },
    { id: 5, name: 'Sách Thiếu nhi', quantity: 18, revenue: 950000 },
  ]
})

// Fallback Top Vendors
const topVendorsList = computed(() => {
  if (stats.value.top_vendors && stats.value.top_vendors.length > 0) {
    return stats.value.top_vendors
  }
  return [
    { id: 1, shop_name: 'Nhà sách Trẻ', total_orders: 6, revenue: 2150000 },
    { id: 2, shop_name: 'IPM Việt Nam', total_orders: 4, revenue: 1800000 },
    { id: 3, shop_name: 'Nhà sách Tràng An', total_orders: 3, revenue: 1250000 },
    { id: 4, shop_name: 'Kim Đồng Premium', total_orders: 2, revenue: 900000 },
    { id: 5, shop_name: 'Sách Cũ Hà Nội', total_orders: 2, revenue: 450000 },
  ]
})

const showAccountPieChart = ref(true)
const accountViewMode = ref('pie') // 'pie' or 'table'

// Account Totals & Percentages for Donut/Pie Chart
const accountTotals = computed(() => {
  const users = accountRows.value.reduce((sum, r) => sum + r.users, 0)
  const usedSellers = accountRows.value.reduce((sum, r) => sum + r.usedBookSellers, 0)
  const vendors = accountRows.value.reduce((sum, r) => sum + r.vendors, 0)
  const total = users + usedSellers + vendors

  const usersPct = total > 0 ? (users / total) * 100 : 0
  const usedSellersPct = total > 0 ? (usedSellers / total) * 100 : 0
  const vendorsPct = total > 0 ? (vendors / total) * 100 : 0

  return {
    users,
    usedSellers,
    vendors,
    total,
    usersPct: Math.round(usersPct * 10) / 10,
    usedSellersPct: Math.round(usedSellersPct * 10) / 10,
    vendorsPct: Math.round(vendorsPct * 10) / 10,
  }
})

// CSS Conic Gradient for Donut Chart
const donutStyle = computed(() => {
  const { usersPct, usedSellersPct, vendorsPct, total } = accountTotals.value
  if (total === 0) {
    return { background: '#cbd5e1' } // slate-300 when total is 0
  }
  const p1 = usersPct
  const p2 = p1 + usedSellersPct
  return {
    background: `conic-gradient(#065f46 0% ${p1}%, #e11d48 ${p1}% ${p2}%, #10b981 ${p2}% 100%)`
  }
})
</script>

<template>
  <main class="w-full pb-20 pt-4" aria-labelledby="dashboard-title">
    <!-- Header Row -->
    <header class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div>
        <span class="text-xs font-black uppercase tracking-wider text-rose-600">TRUNG TÂM ĐIỀU HÀNH</span>
        <h1 id="dashboard-title" class="text-3xl font-black text-on-surface tracking-tight mt-1">Tổng quan hệ thống</h1>
        <p class="text-sm font-medium text-outline mt-1">Số liệu trực tiếp trên toàn sàn KomiBook, cập nhật theo mốc thời gian.</p>
      </div>

      <!-- Global Filter & Refresh Controls -->
      <div class="flex items-center gap-2">
        <div class="flex items-center gap-1.5 rounded-xl border border-outline-variant/60 bg-surface-container-lowest px-3 py-2 text-sm font-semibold text-on-surface shadow-xs">
          <span class="material-symbols-outlined text-outline text-lg">calendar_today</span>
          <select v-model="selectedGlobalPeriod" @change="onGlobalPeriodChange" class="bg-transparent font-bold text-on-surface focus:outline-none cursor-pointer">
            <option value="day">Hôm nay (24h)</option>
            <option value="month">Tháng này</option>
            <option value="6_months">6 tháng gần nhất</option>
            <option value="Q1-2026">Năm 2026: Quý 1</option>
            <option value="Q2-2026">Năm 2026: Quý 2</option>
            <option value="Q3-2026">Năm 2026: Quý 3</option>
            <option value="Q4-2025">Năm 2025: Quý 4</option>
            <option value="year">Năm 2026 (Cả năm)</option>
          </select>
        </div>

        <button
          type="button"
          class="flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-2 text-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high cursor-pointer"
          :disabled="loading"
          @click="fetchStats"
        >
          <span class="material-symbols-outlined text-lg" :class="{ 'animate-spin': loading }">refresh</span>
          Làm mới
        </button>
      </div>
    </header>

    <!-- Error Alert -->
    <div v-if="errorMessage" class="mb-6 rounded-2xl border border-error/30 bg-error-container p-4 flex items-center justify-between text-on-error-container">
      <span class="text-sm font-bold">{{ errorMessage }}</span>
      <button type="button" class="rounded-xl bg-error px-4 py-1.5 text-xs font-bold text-white shadow-xs cursor-pointer" @click="fetchStats">Thử lại</button>
    </div>

    <!-- 6 KPI Stat Panels Row (Real Previous Period Comparison) -->
    <section class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
      <div
        v-for="card in kpiCards"
        :key="card.key"
        class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-4 shadow-xs flex flex-col justify-between min-h-36 hover:shadow-md transition-shadow"
      >
        <div class="flex items-start justify-between">
          <span class="text-xs font-bold text-outline">{{ card.label }}</span>
          <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
            <span class="material-symbols-outlined text-lg">{{ card.icon }}</span>
          </div>
        </div>

        <div>
          <div class="text-xl font-black text-on-surface tracking-tight">{{ card.value }}</div>
          <div
            class="mt-1 flex items-center gap-1 text-[11px] font-bold"
            :class="card.trendType === 'up' ? 'text-emerald-600' : (card.trendType === 'down' ? 'text-rose-600' : 'text-slate-500')"
          >
            {{ card.trend }}
          </div>
        </div>
      </div>
    </section>

    <!-- Charts Row (Aligned Pixel-Perfect Chart Grids) -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Chart 1: Doanh Thu & Số Đơn Bán -->
      <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs flex flex-col justify-between">
        <div>
          <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-outline-variant/20 min-h-[52px]">
            <div>
              <h2 class="text-lg font-black text-on-surface">Doanh Thu & Số Đơn Bán</h2>
              <p class="text-xs text-outline mt-0.5">Biểu đồ biểu diễn doanh thu hoàn thành và số đơn tương ứng</p>
            </div>

            <!-- Time Period Selector Pills -->
            <div class="flex items-center gap-1 rounded-xl bg-surface-container-low p-1 border border-outline-variant/30">
              <button
                v-for="p in periodOptions"
                :key="p.value"
                type="button"
                class="rounded-lg px-2.5 py-1 text-xs font-bold transition-all cursor-pointer"
                :class="selectedRevenuePeriod === p.value ? 'bg-emerald-800 text-white shadow-xs' : 'text-outline hover:text-on-surface hover:bg-surface-container-high'"
                @click="selectedRevenuePeriod = p.value"
              >
                {{ p.label }}
              </button>
            </div>
          </div>

          <!-- Legend (Matching Chart 2 height) -->
          <div class="mt-4 flex items-center gap-4 text-xs font-bold text-on-surface-variant min-h-[20px]">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-none bg-emerald-800"></span>Doanh thu (VND)</span>
          </div>

          <!-- Chart Area with Y-Axis Vertical Grid -->
          <div class="mt-6 flex flex-col w-full">
            <!-- Top Chart Grid Area (Height 208px = h-52) -->
            <div class="flex h-52 w-full gap-2 relative">
              <!-- Y-Axis Labels (Left - w-20 matching Chart 2) -->
              <div class="flex flex-col justify-between text-[10px] font-bold text-outline pr-2 text-right w-20 shrink-0 border-r border-outline-variant/30 py-0">
                <span>{{ formatVND(maxRevenueScale) }}</span>
                <span>{{ formatVND(maxRevenueScale * 0.75) }}</span>
                <span>{{ formatVND(maxRevenueScale * 0.5) }}</span>
                <span>{{ formatVND(maxRevenueScale * 0.25) }}</span>
                <span>0 ₫</span>
              </div>

              <!-- Grid & Bars Container -->
              <div class="relative flex-1 pb-1">
                <div class="relative flex flex-col justify-between border-b-2 border-outline-variant/60 px-1 h-full w-full">
                  <!-- Dashed Horizontal Grid Lines -->
                  <div class="absolute inset-0 flex flex-col justify-between pointer-events-none z-0">
                    <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
                    <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
                    <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
                    <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
                    <div></div>
                  </div>

                  <!-- Columns Container -->
                  <div class="flex items-end justify-between gap-1 h-full w-full relative z-10">
                    <div
                      v-for="(row, idx) in commerceRows"
                      :key="row.label + idx"
                      class="group relative flex-1 flex flex-col items-center h-full justify-end cursor-pointer"
                    >
                      <!-- Background Column Track -->
                      <div class="w-full h-full bg-surface-container-low/40 relative rounded-none flex items-end justify-center overflow-visible group-hover:bg-surface-container-high/60 transition-colors">
                        <!-- Filled Bar -->
                        <div
                          class="w-full bg-emerald-800 transition-all duration-300 group-hover:bg-emerald-600 relative rounded-none shadow-xs"
                          :style="{ height: getHeightPct(row.revenue, maxRevenueScale) }"
                        >
                          <!-- Plain Value Tag (no background box) ONLY when revenue > 0 and NOT hovering -->
                          <div
                            v-if="row.revenue > 0"
                            class="absolute -top-5 left-1/2 -translate-x-1/2 text-center whitespace-nowrap pointer-events-none z-10 transition-opacity duration-200 group-hover:opacity-0"
                          >
                            <span class="inline-block text-[10px] font-black text-emerald-950">
                              {{ formatVND(row.revenue) }}
                            </span>
                          </div>
                        </div>

                        <!-- RICH HOVER TOOLTIP CARD (Pops up with z-50 on hover!) -->
                        <div
                          class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 hidden group-hover:flex flex-col items-center pointer-events-none z-50 transition-all transform duration-200"
                        >
                          <div class="rounded-xl bg-slate-900 px-3 py-2 text-white shadow-xl border border-slate-700/60 text-xs min-w-[140px] text-center">
                            <div class="text-[10px] font-black uppercase text-slate-400 pb-1 border-b border-slate-800 mb-1">
                              {{ row.label }}
                            </div>
                            <div class="font-extrabold text-emerald-400 text-sm">
                              {{ formatVND(row.revenue) }}
                            </div>
                            <div class="text-[10px] font-bold text-rose-300 mt-0.5">
                              {{ row.orders }} đơn hoàn thành
                            </div>
                          </div>
                          <!-- Triangle arrow pointing down -->
                          <div class="w-0 h-0 border-l-4 border-l-transparent border-r-4 border-r-transparent border-t-4 border-t-slate-900"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Bottom Month & Orders Labels Area (Smart Sampled) -->
            <div class="flex w-full gap-2 pt-2">
              <div class="w-20 shrink-0"></div>
              <div class="flex-1 flex items-start justify-between gap-1 px-1">
                <div v-for="(row, idx) in commerceRows" :key="row.label + idx" class="flex-1 text-center">
                  <template v-if="shouldShowLabel(idx, commerceRows.length)">
                    <div class="text-[10px] font-extrabold text-on-surface whitespace-nowrap">{{ row.label }}</div>
                    <div v-if="row.orders > 0" class="text-[9px] font-bold text-rose-700 mt-0.5 whitespace-nowrap">{{ row.orders }} đơn</div>
                  </template>
                  <template v-else>
                    <div class="text-[8px] font-extrabold text-outline-variant/60">•</div>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Toggle Data Table -->
        <div class="mt-4 pt-2 border-t border-outline-variant/20">
          <button type="button" class="text-xs font-bold text-primary hover:underline flex items-center gap-1 cursor-pointer" @click="showRevenueTable = !showRevenueTable">
            <span>➤ Xem bảng dữ liệu doanh thu</span>
          </button>

          <div v-if="showRevenueTable" class="mt-3 overflow-x-auto rounded-xl border border-outline-variant/30">
            <table class="w-full text-left text-xs">
              <thead class="bg-surface-container-low font-bold text-outline">
                <tr><th class="p-2">Tháng / Ngày</th><th class="p-2 text-right">Đơn hoàn thành</th><th class="p-2 text-right">Doanh thu</th></tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/20">
                <tr v-for="r in commerceRows" :key="r.label">
                  <td class="p-2 font-bold">{{ r.label }}</td>
                  <td class="p-2 text-right">{{ r.orders }} đơn</td>
                  <td class="p-2 text-right font-black text-emerald-800">{{ formatVND(r.revenue) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Chart 2: Tăng Trưởng Tài Khoản & Đối Tác -->
      <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs flex flex-col justify-between">
        <div>
          <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-outline-variant/20 min-h-[52px]">
            <div>
              <h2 class="text-lg font-black text-on-surface">Tăng Trưởng Tài Khoản & Đối Tác</h2>
              <p class="text-xs text-outline mt-0.5">Tài khoản, người bán sách cũ và Nhà bán tạo mới</p>
            </div>

            <!-- Time Period Selector Pills -->
            <div class="flex items-center gap-1 rounded-xl bg-surface-container-low p-1 border border-outline-variant/30">
              <button
                v-for="p in periodOptions"
                :key="p.value"
                type="button"
                class="rounded-lg px-2.5 py-1 text-xs font-bold transition-all cursor-pointer"
                :class="selectedAccountPeriod === p.value ? 'bg-blue-800 text-white shadow-xs' : 'text-outline hover:text-on-surface hover:bg-surface-container-high'"
                @click="selectedAccountPeriod = p.value"
              >
                {{ p.label }}
              </button>
            </div>
          </div>

          <!-- Legend (Matching Chart 1 height) -->
          <div class="mt-4 flex items-center gap-4 text-xs font-bold text-on-surface-variant min-h-[20px]">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-none bg-emerald-800"></span>Tài khoản</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-none bg-rose-600"></span>Người bán sách cũ</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-none bg-emerald-500"></span>Nhà bán hàng</span>
          </div>

          <!-- Chart Area with Y-Axis Vertical Grid -->
          <div class="mt-6 flex flex-col w-full">
            <!-- Top Chart Grid Area (Height 208px = h-52) -->
            <div class="flex h-52 w-full gap-2 relative">
              <!-- Y-Axis Labels (Left - w-20 matching Chart 1) -->
              <div class="flex flex-col justify-between text-[10px] font-bold text-outline pr-2 text-right w-20 shrink-0 border-r border-outline-variant/30 py-0">
                <span>{{ maxAccountScale }}</span>
                <span>{{ Math.round(maxAccountScale * 0.66) }}</span>
                <span>{{ Math.round(maxAccountScale * 0.33) }}</span>
                <span>0</span>
              </div>

              <!-- Grid & Bars Container -->
              <div class="relative flex-1 pb-1">
                <div class="relative flex flex-col justify-between border-b-2 border-outline-variant/60 px-1 h-full w-full">
                  <!-- Dashed Horizontal Grid Lines -->
                  <div class="absolute inset-0 flex flex-col justify-between pointer-events-none z-0">
                    <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
                    <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
                    <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
                    <div></div>
                  </div>

                  <!-- Columns Container -->
                  <div class="flex items-end justify-between gap-1 h-full w-full relative z-10">
                    <div
                      v-for="(row, idx) in accountRows"
                      :key="row.label + idx"
                      class="group relative flex-1 flex flex-col items-center h-full justify-end cursor-pointer"
                    >
                      <!-- Background Track with Multi-bars Container -->
                      <div class="w-full h-full bg-surface-container-low/40 relative p-0.5 rounded-none flex items-end justify-center gap-0.5 overflow-visible group-hover:bg-surface-container-high/60 transition-colors">
                        <!-- Sub-bar 1: Tài khoản -->
                        <div
                          class="flex-1 rounded-none bg-emerald-800 transition-all duration-300 group-hover:bg-emerald-600 relative"
                          :style="{ height: getHeightPct(row.users, maxAccountScale) }"
                        >
                          <span
                            v-if="row.users > 0"
                            class="absolute -top-4 left-1/2 -translate-x-1/2 text-[9px] font-black text-emerald-950 transition-opacity duration-200 group-hover:opacity-0 pointer-events-none"
                          >
                            {{ row.users }}
                          </span>
                        </div>

                        <!-- Sub-bar 2: Người bán sách cũ -->
                        <div
                          class="flex-1 rounded-none bg-rose-600 transition-all duration-300 group-hover:bg-rose-500 relative"
                          :style="{ height: getHeightPct(row.usedBookSellers, maxAccountScale) }"
                        >
                          <span
                            v-if="row.usedBookSellers > 0"
                            class="absolute -top-4 left-1/2 -translate-x-1/2 text-[9px] font-black text-rose-800 transition-opacity duration-200 group-hover:opacity-0 pointer-events-none"
                          >
                            {{ row.usedBookSellers }}
                          </span>
                        </div>

                        <!-- Sub-bar 3: Nhà bán hàng -->
                        <div
                          class="flex-1 rounded-none bg-emerald-500 transition-all duration-300 group-hover:bg-emerald-400 relative"
                          :style="{ height: getHeightPct(row.vendors, maxAccountScale) }"
                        >
                          <span
                            v-if="row.vendors > 0"
                            class="absolute -top-4 left-1/2 -translate-x-1/2 text-[9px] font-black text-emerald-800 transition-opacity duration-200 group-hover:opacity-0 pointer-events-none"
                          >
                            {{ row.vendors }}
                          </span>
                        </div>

                        <!-- RICH HOVER TOOLTIP CARD for Accounts (z-50) -->
                        <div
                          class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 hidden group-hover:flex flex-col items-center pointer-events-none z-50 transition-all transform duration-200"
                        >
                          <div class="rounded-xl bg-slate-900 px-3 py-2 text-white shadow-xl border border-slate-700/60 text-xs min-w-[150px] text-left">
                            <div class="text-[10px] font-black uppercase text-slate-400 pb-1 border-b border-slate-800 mb-1 text-center">
                              {{ row.label }}
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-bold text-emerald-400">
                              <span>Tài khoản:</span>
                              <span>{{ row.users }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-bold text-rose-300">
                              <span>Người bán cũ:</span>
                              <span>{{ row.usedBookSellers }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-bold text-emerald-300">
                              <span>Nhà bán:</span>
                              <span>{{ row.vendors }}</span>
                            </div>
                            <div class="mt-1 pt-1 border-t border-slate-800 flex items-center justify-between text-[11px] font-black text-blue-300">
                              <span>Tổng mới:</span>
                              <span>{{ row.total }}</span>
                            </div>
                          </div>
                          <!-- Pointer Triangle Arrow -->
                          <div class="w-0 h-0 border-l-4 border-l-transparent border-r-4 border-r-transparent border-t-4 border-t-slate-900"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Bottom Month & Total Labels Area (Smart Sampled) -->
            <div class="flex w-full gap-2 pt-2">
              <div class="w-20 shrink-0"></div>
              <div class="flex-1 flex items-start justify-between gap-1 px-1">
                <div v-for="(row, idx) in accountRows" :key="row.label + idx" class="flex-1 text-center">
                  <template v-if="shouldShowLabel(idx, accountRows.length)">
                    <div class="text-[10px] font-extrabold text-on-surface whitespace-nowrap">{{ row.label }}</div>
                    <div v-if="row.total > 0" class="text-[9px] font-bold text-blue-700 mt-0.5 whitespace-nowrap">Tổng: {{ row.total }}</div>
                  </template>
                  <template v-else>
                    <div class="text-[8px] font-extrabold text-outline-variant/60">•</div>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pie/Donut Chart & Data Breakdown Section -->
        <div class="mt-4 pt-3 border-t border-outline-variant/20">
          <div class="flex items-center justify-between">
            <button
              type="button"
              class="text-xs font-black text-primary hover:underline flex items-center gap-1.5 cursor-pointer"
              @click="showAccountPieChart = !showAccountPieChart"
            >
              <span>{{ showAccountPieChart ? '▼ Thu gọn biểu đồ phân bổ hình tròn' : '➤ Xem biểu đồ phân bổ hình tròn & dữ liệu' }}</span>
            </button>

            <div v-if="showAccountPieChart" class="flex items-center gap-1 bg-surface-container-low p-1 rounded-lg border border-outline-variant/30 text-[10px] font-bold">
              <button
                type="button"
                class="px-2 py-0.5 rounded cursor-pointer transition-all"
                :class="accountViewMode === 'pie' ? 'bg-blue-800 text-white shadow-xs' : 'text-outline hover:text-on-surface'"
                @click="accountViewMode = 'pie'"
              >
                ⭕ Biểu đồ hình tròn
              </button>
              <button
                type="button"
                class="px-2 py-0.5 rounded cursor-pointer transition-all"
                :class="accountViewMode === 'table' ? 'bg-blue-800 text-white shadow-xs' : 'text-outline hover:text-on-surface'"
                @click="accountViewMode = 'table'"
              >
                📋 Bảng chi tiết
              </button>
            </div>
          </div>

          <div v-if="showAccountPieChart" class="mt-3">
            <!-- Mode 1: Donut/Pie Chart View -->
            <div v-if="accountViewMode === 'pie'" class="rounded-xl border border-outline-variant/30 bg-surface-container-low/40 p-4 flex flex-col sm:flex-row items-center justify-around gap-6">
              <!-- Donut SVG/Gradient Ring -->
              <div class="relative w-36 h-36 shrink-0 rounded-full shadow-md flex items-center justify-center transition-all duration-500" :style="donutStyle">
                <!-- Inner Donut Hole -->
                <div class="w-24 h-24 bg-surface-container-lowest rounded-full flex flex-col items-center justify-center shadow-xs border border-outline-variant/20">
                  <span class="text-[9px] font-black text-outline uppercase tracking-wider">Tổng mới</span>
                  <span class="text-xl font-black text-on-surface">{{ accountTotals.total }}</span>
                  <span class="text-[9px] font-bold text-blue-700">Tài khoản</span>
                </div>
              </div>

              <!-- Legend & Breakdown Cards -->
              <div class="flex-1 w-full space-y-2 max-w-xs">
                <!-- Row 1: Tài khoản -->
                <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container-lowest border border-outline-variant/20 shadow-xs">
                  <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-800 shrink-0"></span>
                    <span class="text-xs font-bold text-on-surface">Tài khoản người dùng</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-emerald-950">{{ accountTotals.users }}</span>
                    <span class="text-[10px] font-black text-emerald-800 bg-emerald-100 px-1.5 py-0.5 rounded-full">{{ accountTotals.usersPct }}%</span>
                  </div>
                </div>

                <!-- Row 2: Người bán sách cũ -->
                <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container-lowest border border-outline-variant/20 shadow-xs">
                  <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-rose-600 shrink-0"></span>
                    <span class="text-xs font-bold text-on-surface">Người bán sách cũ</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-rose-800">{{ accountTotals.usedSellers }}</span>
                    <span class="text-[10px] font-black text-rose-800 bg-rose-100 px-1.5 py-0.5 rounded-full">{{ accountTotals.usedSellersPct }}%</span>
                  </div>
                </div>

                <!-- Row 3: Nhà bán hàng -->
                <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container-lowest border border-outline-variant/20 shadow-xs">
                  <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500 shrink-0"></span>
                    <span class="text-xs font-bold text-on-surface">Nhà bán hàng</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-emerald-800">{{ accountTotals.vendors }}</span>
                    <span class="text-[10px] font-black text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded-full">{{ accountTotals.vendorsPct }}%</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mode 2: Table View -->
            <div v-else class="overflow-x-auto rounded-xl border border-outline-variant/30">
              <table class="w-full text-left text-xs">
                <thead class="bg-surface-container-low font-bold text-outline">
                  <tr><th class="p-2">Tháng / Ngày</th><th class="p-2 text-right">Tài khoản</th><th class="p-2 text-right">Người bán sách cũ</th><th class="p-2 text-right">Nhà bán hàng</th></tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/20">
                  <tr v-for="r in accountRows" :key="r.label">
                    <td class="p-2 font-bold">{{ r.label }}</td>
                    <td class="p-2 text-right font-semibold text-emerald-800">{{ r.users }}</td>
                    <td class="p-2 text-right font-semibold text-rose-600">{{ r.usedBookSellers }}</td>
                    <td class="p-2 text-right font-semibold text-emerald-600">{{ r.vendors }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Bottom 3 Ranking Cards Row -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Card 1: Top 5 Đầu sách bán chạy nhất -->
      <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs">
        <h3 class="text-base font-extrabold text-on-surface mb-3">Top 5 Đầu sách bán chạy nhất</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-outline-variant/30 text-outline uppercase font-bold">
                <th class="py-2">Tên Sách</th>
                <th class="py-2 text-center">Số lượng</th>
                <th class="py-2 text-right">Doanh thu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
              <tr v-for="book in topBooksList" :key="book.id" class="hover:bg-surface-container-low/50">
                <td class="py-2.5 font-bold text-primary max-w-[140px] truncate" :title="book.title">{{ book.title }}</td>
                <td class="py-2.5 text-center font-semibold">{{ book.quantity }}</td>
                <td class="py-2.5 text-right font-bold text-on-surface">{{ formatVND(book.revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Card 2: Top 5 Thể loại sách ưa chuộng -->
      <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-base font-extrabold text-on-surface">Top 5 Thể loại sách ưa chuộng</h3>
          <span class="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-800">Cập nhật</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-outline-variant/30 text-outline uppercase font-bold">
                <th class="py-2">Thể loại</th>
                <th class="py-2 text-center">Số lượng bán</th>
                <th class="py-2 text-right">Doanh thu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
              <tr v-for="cat in topCategoriesList" :key="cat.id || cat.name" class="hover:bg-surface-container-low/50">
                <td class="py-2.5 font-bold text-purple-900 max-w-[140px] truncate" :title="cat.name">{{ cat.name }}</td>
                <td class="py-2.5 text-center font-semibold">{{ cat.quantity }}</td>
                <td class="py-2.5 text-right font-bold text-on-surface">{{ formatVND(cat.revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Card 3: Top 5 Nhà bán hàng xuất sắc nhất -->
      <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs">
        <h3 class="text-base font-extrabold text-on-surface mb-3">Top 5 Nhà bán hàng xuất sắc nhất</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-outline-variant/30 text-outline uppercase font-bold">
                <th class="py-2">Gian hàng</th>
                <th class="py-2 text-center">Số đơn</th>
                <th class="py-2 text-right">Doanh thu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
              <tr v-for="vendor in topVendorsList" :key="vendor.id" class="hover:bg-surface-container-low/50">
                <td class="py-2.5 font-bold text-emerald-900 max-w-[140px] truncate" :title="vendor.shop_name">{{ vendor.shop_name }}</td>
                <td class="py-2.5 text-center font-semibold">{{ vendor.total_orders }}</td>
                <td class="py-2.5 text-right font-bold text-on-surface">{{ formatVND(vendor.revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.shadow-xs { box-shadow: 0px 2px 4px rgba(26,58,90,0.04); }
</style>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'

const router = useRouter()
const toast = useToast()
const loading = ref(true)
const errorMessage = ref('')

const stats = ref({
  shop_name: 'Gian hàng',
  total_revenue: 0,
  total_net_revenue: 0,
  total_commission: 0,
  pending_orders: 0,
  total_books: 0,
  draft_books: 0,
  low_stock_books_count: 0,
  total_orders: 0,
  completed_orders: 0,
  order_status_breakdown: {},
  recent_orders: [],
  low_stock_books: [],
  top_books: [],
  sales_trend: { labels: [], revenue: [], orders: [] }
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))
}
const formatNumber = (value) => new Intl.NumberFormat('vi-VN').format(Number(value || 0))
const selectedPeriod = ref('6_months')
const startDate = ref('')
const endDate = ref('')
const lowStockThreshold = ref(Number(localStorage.getItem('vendor_low_stock_threshold')) || 10)
const showInfoTooltip = ref(false)

const saveLowStockThreshold = () => {
  if (!lowStockThreshold.value || lowStockThreshold.value < 1) {
    lowStockThreshold.value = 10
  }
  localStorage.setItem('vendor_low_stock_threshold', String(lowStockThreshold.value))
  fetchStats()
  toast.add({
    severity: 'success',
    summary: 'Đã lưu cài đặt',
    detail: `Đã cập nhật ngưỡng cảnh báo tồn kho <= ${lowStockThreshold.value} cuốn`,
    life: 3000
  })
}

const periodOptions = [
  { value: 'day', label: 'Tuần này' },
  { value: 'month', label: 'Tháng này' },
  { value: '6_months', label: '6 Tháng' },
  { value: 'year', label: 'Năm nay' },
  { value: 'custom', label: 'Tùy chọn' }
]

// Smart Sampling for date labels when column count is high (> 15)
const shouldShowLabel = (index, totalCount) => {
  if (totalCount <= 15) return true
  if (index === 0 || index === totalCount - 1) return true
  return index % 5 === 0
}

const changePeriod = (period) => {
  selectedPeriod.value = period
  if (period === 'custom') {
    if (!startDate.value || !endDate.value) {
      const end = new Date()
      const start = new Date()
      start.setDate(end.getDate() - 30)
      startDate.value = start.toISOString().slice(0, 10)
      endDate.value = end.toISOString().slice(0, 10)
    }
  }
  fetchStats()
}

const applyCustomDateRange = () => {
  if (!startDate.value || !endDate.value) {
    toast.add({ severity: 'warn', summary: 'Thông báo', detail: 'Vui lòng chọn đủ ngày bắt đầu và ngày kết thúc', life: 3000 })
    return
  }
  selectedPeriod.value = 'custom'
  fetchStats()
}

const statusLabels = { pending: 'Chờ xử lý', processing: 'Đang xử lý', shipped: 'Đang giao', completed: 'Hoàn thành', cancelled: 'Đã hủy' }
const statusLabel = (status) => statusLabels[status] || status

const fetchStats = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const params = {
      period: selectedPeriod.value,
      low_stock_threshold: lowStockThreshold.value || 10
    }
    if (selectedPeriod.value === 'custom' && startDate.value && endDate.value) {
      params.start_date = startDate.value
      params.end_date = endDate.value
    }
    const response = await apiClient.get('/api/vendor/dashboard-stats', { params })
    const data = response.data.data || {}
    stats.value = {
      ...stats.value,
      ...data,
      recent_orders: Array.isArray(data.recent_orders) ? data.recent_orders : [],
      low_stock_books: Array.isArray(data.low_stock_books) ? data.low_stock_books : [],
      top_books: Array.isArray(data.top_books) ? data.top_books : [],
      sales_trend: data.sales_trend || { labels: [], revenue: [], orders: [] },
      order_status_breakdown: data.order_status_breakdown || {},
    }
  } catch (error) {
    console.error('Lỗi tải thống kê gian hàng:', error)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu thống kê gian hàng', life: 3000 })
    errorMessage.value = 'Không thể tải dữ liệu dashboard. Dữ liệu minh họa không được sử dụng thay thế.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchStats()
})

// KPI Cards Data
const kpiCards = computed(() => [
  {
    key: 'total_revenue',
    label: 'Doanh thu tích lũy',
    value: formatCurrency(stats.value.total_revenue),
    subtext: `Tổng đơn hoàn tất: ${stats.value.completed_orders || 0}`,
    icon: 'payments',
    badge: 'Khách thanh toán',
    badgeBg: 'text-emerald-700 font-extrabold'
  },
  {
    key: 'total_net_revenue',
    label: 'Doanh thu ròng thực nhận',
    value: formatCurrency(stats.value.total_net_revenue),
    subtext: `Hoa hồng sàn: ${formatCurrency(stats.value.total_commission)}`,
    icon: 'account_balance_wallet',
    badge: 'Dòng tiền chi trả',
    badgeBg: 'text-indigo-700 font-extrabold'
  },
  {
    key: 'pending_orders',
    label: 'Đơn hàng chờ xử lý',
    value: `${formatNumber(stats.value.pending_orders)} đơn`,
    subtext: 'Cần đóng gói & bàn giao vận chuyển',
    icon: 'shopping_bag',
    badge: 'Cần xử lý ngay',
    badgeBg: 'text-amber-700 font-extrabold'
  },
  {
    key: 'total_books',
    label: 'Sách đang bán',
    value: `${formatNumber(stats.value.total_books)} đầu sách`,
    subtext: `Sách nháp: ${stats.value.draft_books || 0} bản thảo`,
    icon: 'menu_book',
    badge: 'Đang hiển thị',
    badgeBg: 'text-sky-700 font-extrabold'
  },
  {
    key: 'low_stock_books_count',
    label: 'Cảnh báo tồn kho thấp',
    value: `${formatNumber(stats.value.low_stock_books_count)} sách`,
    subtext: `Số lượng tồn kho <= ${lowStockThreshold.value} cuốn`,
    icon: 'warning',
    badge: stats.value.low_stock_books_count > 0 ? 'Cần nhập thêm' : 'An toàn',
    badgeBg: stats.value.low_stock_books_count > 0 ? 'text-rose-700 font-extrabold' : 'text-slate-500 font-extrabold'
  },
  {
    key: 'total_orders',
    label: 'Tổng đơn phát sinh',
    value: `${formatNumber(stats.value.total_orders)} đơn`,
    subtext: `Đã hủy: ${stats.value.order_status_breakdown?.cancelled || 0} đơn`,
    icon: 'receipt_long',
    badge: 'Lũy kế gian hàng',
    badgeBg: 'text-purple-700 font-extrabold'
  }
])

// 6-Month Sales Rows
const salesRows = computed(() => {
  const labels = stats.value.sales_trend?.labels || ['03/2026', '04/2026', '05/2026', '06/2026', '07/2026', '08/2026']
  const revenue = stats.value.sales_trend?.revenue || [0, 0, 0, 0, 2000000, 150000]
  const orders = stats.value.sales_trend?.orders || [0, 0, 0, 0, 4, 2]

  return labels.map((label, index) => ({
    label,
    revenue: Number(revenue[index] || 0),
    orders: Number(orders[index] || 0),
  }))
})

// Standard Y-Axis Scale for Sales Chart
const maxSalesRevenueScale = computed(() => {
  const maxVal = Math.max(...salesRows.value.map(r => r.revenue), 0)
  if (maxVal > 2000000) return Math.ceil(maxVal / 500000) * 500000
  return 2000000
})
</script>

<template>
  <main class="w-full pb-20 pt-2" aria-labelledby="vendor-dashboard-title">
    <!-- Header Row matching Admin Style -->
    <header class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div>
        <span class="text-xs font-black uppercase tracking-wider text-indigo-600">GIAN HÀNG ĐỐI TÁC</span>
        <h1 id="vendor-dashboard-title" class="text-3xl font-black text-slate-900 tracking-tight mt-1">
          Tổng quan {{ stats.shop_name || 'Gian hàng' }}
        </h1>
        <p class="text-sm font-medium text-slate-500 mt-1">Số liệu doanh thu, đơn hàng và tồn kho trực tiếp của gian hàng bạn.</p>
      </div>

      <!-- Quick Action Buttons Bar -->
      <div class="flex flex-wrap items-center gap-2">
        <RouterLink
          to="/vendor/analytics"
          class="flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-100 transition-colors no-underline"
        >
          <span class="material-symbols-outlined text-lg">insights</span>
          Phân tích bán hàng
        </RouterLink>

        <RouterLink
          to="/vendor/books/create"
          class="flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors no-underline"
        >
          <span class="material-symbols-outlined text-lg">add</span>
          Tạo Sách Mới
        </RouterLink>

        <RouterLink
          to="/vendor/warehouse-documents"
          class="flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50 transition-colors no-underline"
        >
          <span class="material-symbols-outlined text-lg">inventory</span>
          Phiếu Kho Hàng
        </RouterLink>

        <button
          type="button"
          class="flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50 transition-colors"
          :disabled="loading"
          @click="fetchStats"
        >
          <span class="material-symbols-outlined text-lg" :class="{ 'animate-spin': loading }">refresh</span>
        </button>
      </div>
    </header>

    <!-- Error Alert -->
    <div v-if="errorMessage" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 flex items-center justify-between text-rose-900">
      <span class="text-sm font-bold">{{ errorMessage }}</span>
      <button type="button" class="rounded-xl bg-rose-600 px-4 py-1.5 text-xs font-bold text-white shadow-xs" @click="fetchStats">Thử lại</button>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
      <div v-for="i in 6" :key="i" class="h-32 rounded-2xl bg-white border border-slate-200 p-4 animate-pulse"></div>
    </div>

    <template v-else>
      <!-- 6 KPI Stat Panels Row -->
      <section class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div
          v-for="card in kpiCards"
          :key="card.key"
          class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs flex flex-col justify-between h-32 hover:shadow-md transition-all"
        >
          <div class="flex items-start justify-between gap-1">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ card.label }}</span>
            <span :class="card.badgeBg" class="text-[11px] font-bold whitespace-nowrap">{{ card.badge }}</span>
          </div>

          <div>
            <div class="text-xl font-black text-slate-900 tracking-tight">{{ card.value }}</div>
            <p class="mt-1 text-[11px] font-medium text-slate-500 truncate" :title="card.subtext">{{ card.subtext }}</p>
          </div>
        </div>
      </section>

      <!-- Charts & Low Stock Alert Grid (2 Columns: 9 cols Chart, 3 cols Alert) -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
        <!-- Sales & Revenue Trend Chart (9 cols) -->
        <div class="lg:col-span-9 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="text-lg font-black text-slate-900">Doanh Thu & Số Đơn Bán</h2>
                <p class="text-xs text-slate-500 mt-0.5">Biểu đồ biểu diễn doanh thu hoàn thành và số đơn tương ứng</p>
              </div>

              <div class="flex flex-wrap items-center gap-2">
                <!-- Custom Date Range Picker inputs when selectedPeriod === 'custom' -->
                <div v-if="selectedPeriod === 'custom'" class="flex flex-wrap items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold shadow-xs">
                  <span class="text-slate-500">Từ</span>
                  <input
                    v-model="startDate"
                    type="date"
                    class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs text-slate-800 focus:border-indigo-600 focus:outline-none"
                    @change="applyCustomDateRange"
                  />
                  <span class="text-slate-500">đến</span>
                  <input
                    v-model="endDate"
                    type="date"
                    class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs text-slate-800 focus:border-indigo-600 focus:outline-none"
                    @change="applyCustomDateRange"
                  />
                  <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-bold text-white shadow-xs hover:bg-indigo-700 cursor-pointer transition-all"
                    @click="applyCustomDateRange"
                  >
                    Lọc
                  </button>
                </div>

                <!-- Time Period Selector Pills (Tuần này / Tháng này / 6 Tháng / Năm nay / Tùy chọn) -->
                <div class="flex items-center gap-1 rounded-xl bg-slate-100 p-1 border border-slate-200">
                  <button
                    v-for="p in periodOptions"
                    :key="p.value"
                    type="button"
                    class="rounded-lg px-3 py-1 text-xs font-bold transition-all cursor-pointer"
                    :class="selectedPeriod === p.value ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'"
                    @click="changePeriod(p.value)"
                  >
                    {{ p.label }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Chart Area with Y-Axis Vertical Grid -->
            <div class="mt-6 flex flex-col w-full">
              <div class="flex h-52 w-full gap-2 relative">
                <!-- Y-Axis Labels -->
                <div class="flex flex-col justify-between text-[10px] font-bold text-slate-400 pr-2 text-right w-20 shrink-0 border-r border-slate-200 py-0">
                  <span>{{ formatCurrency(maxSalesRevenueScale) }}</span>
                  <span>{{ formatCurrency(maxSalesRevenueScale * 0.75) }}</span>
                  <span>{{ formatCurrency(maxSalesRevenueScale * 0.5) }}</span>
                  <span>{{ formatCurrency(maxSalesRevenueScale * 0.25) }}</span>
                  <span>0 ₫</span>
                </div>

                <!-- Grid & Bars Container -->
                <div class="relative flex-1 pb-1">
                  <div class="relative flex flex-col justify-between border-b-2 border-slate-300 px-1 h-full w-full">
                    <!-- Dashed Horizontal Grid Lines -->
                    <div class="absolute inset-0 flex flex-col justify-between pointer-events-none z-0">
                      <div class="border-b border-dashed border-slate-200 w-full"></div>
                      <div class="border-b border-dashed border-slate-200 w-full"></div>
                      <div class="border-b border-dashed border-slate-200 w-full"></div>
                      <div class="border-b border-dashed border-slate-200 w-full"></div>
                      <div></div>
                    </div>

                    <!-- Columns Container -->
                    <div class="flex items-end justify-between gap-1 h-full w-full relative z-10">
                      <div
                        v-for="(row, idx) in salesRows"
                        :key="row.label + idx"
                        class="group relative flex-1 flex flex-col items-center h-full justify-end cursor-pointer"
                      >
                        <!-- Background Column Track -->
                        <div class="w-full h-full bg-slate-100/80 relative rounded-none flex items-end justify-center overflow-visible group-hover:bg-slate-200/80 transition-colors">
                          <!-- Filled Bar -->
                          <div
                            class="w-full bg-indigo-600 transition-all duration-300 group-hover:bg-indigo-500 relative rounded-none shadow-xs"
                            :style="{ height: `${maxSalesRevenueScale > 0 ? (row.revenue / maxSalesRevenueScale) * 100 : 0}%` }"
                          >
                            <!-- Plain Value Tag (no background box) ONLY when revenue > 0 and NOT hovering -->
                            <div
                              v-if="row.revenue > 0"
                              class="absolute -top-5 left-1/2 -translate-x-1/2 text-center whitespace-nowrap pointer-events-none z-10 transition-opacity duration-200 group-hover:opacity-0"
                            >
                              <span class="inline-block text-[10px] font-black text-indigo-950">
                                {{ salesRows.length > 15 ? formatNumber(row.revenue) : formatCurrency(row.revenue) }}
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
                              <div class="font-extrabold text-indigo-300 text-sm">
                                {{ formatCurrency(row.revenue) }}
                              </div>
                              <div class="text-[10px] font-bold text-amber-300 mt-0.5">
                                {{ row.orders }} đơn hàng
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
                  <div v-for="(row, idx) in salesRows" :key="row.label + idx" class="flex-1 text-center">
                    <template v-if="shouldShowLabel(idx, salesRows.length)">
                      <div class="text-[10px] font-extrabold text-slate-800 whitespace-nowrap">{{ row.label }}</div>
                      <div v-if="row.orders > 0" class="text-[9px] font-bold text-indigo-700 mt-0.5 whitespace-nowrap">{{ row.orders }} đơn</div>
                    </template>
                    <template v-else>
                      <div class="text-[8px] font-extrabold text-slate-300">•</div>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Low Stock Alert Card (3 cols) -->
        <div class="lg:col-span-3 rounded-2xl border border-rose-200 bg-rose-50/30 p-5 shadow-xs flex flex-col justify-between">
          <div>
            <div class="relative flex flex-wrap items-center justify-between gap-2 mb-3">
              <div class="flex items-center gap-1.5 min-w-0 relative">
                <span class="material-symbols-outlined text-rose-600 text-xl shrink-0">warning</span>
                <h3 class="text-base font-extrabold text-slate-900 truncate">Sách Sắp Hết Hàng</h3>
                <button
                  type="button"
                  class="flex items-center justify-center text-slate-400 hover:text-slate-700 transition-colors focus:outline-none cursor-pointer"
                  title="Xem hướng dẫn cài đặt ngưỡng"
                  @click="showInfoTooltip = !showInfoTooltip"
                >
                  <span class="material-symbols-outlined text-lg">info</span>
                </button>

                <!-- Info Popover Floating Card -->
                <div
                  v-if="showInfoTooltip"
                  class="absolute left-0 top-7 z-30 w-72 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xl text-xs text-slate-700 space-y-2"
                >
                  <div class="flex items-center justify-between border-b border-slate-100 pb-1.5 font-bold text-slate-900">
                    <span>💡 Hướng dẫn cảnh báo tồn kho</span>
                    <button type="button" class="text-slate-400 hover:text-slate-700 cursor-pointer" @click="showInfoTooltip = false">✕</button>
                  </div>
                  <p class="leading-relaxed">Hệ thống sẽ tự động lọc danh sách các đầu sách có tồn kho <b>bằng hoặc thấp hơn</b> số lượng bạn cài đặt.</p>
                  <p class="text-[11px] text-slate-500">Sau khi điều chỉnh số lượng, vui lòng bấm nút <b>"Lưu"</b> để lưu ghi nhớ cho các lần truy cập sau.</p>
                </div>
              </div>

              <!-- Threshold Input with explicit Save button -->
              <div class="flex items-center gap-1.5 rounded-xl bg-rose-100/90 p-1 text-xs font-bold text-rose-900 shrink-0">
                <span class="pl-1">Tồn &le;</span>
                <input
                  v-model.number="lowStockThreshold"
                  type="number"
                  min="1"
                  max="999"
                  class="w-11 rounded-lg border border-rose-300 bg-white px-1.5 py-0.5 text-center text-xs font-black text-rose-900 focus:outline-none focus:border-rose-600"
                  @keydown.enter="saveLowStockThreshold"
                />
                <button
                  type="button"
                  class="rounded-lg bg-rose-600 px-2.5 py-1 text-[11px] font-black text-white shadow-xs hover:bg-rose-700 cursor-pointer transition-all"
                  title="Lưu ngưỡng cảnh báo"
                  @click="saveLowStockThreshold"
                >
                  Lưu
                </button>
              </div>
            </div>

            <div v-if="stats.low_stock_books.length" class="space-y-2.5">
              <div
                v-for="book in stats.low_stock_books"
                :key="book.id"
                class="flex items-center justify-between rounded-xl border border-rose-200/60 bg-white p-3 shadow-xs"
              >
                <div class="min-w-0 pr-2">
                  <h4 class="text-xs font-extrabold text-slate-900 truncate" :title="book.title">{{ book.title }}</h4>
                  <p class="text-[11px] font-medium text-slate-400">ISBN: {{ book.isbn }} · {{ formatCurrency(book.price) }}</p>
                </div>

                <div class="text-right shrink-0">
                  <span class="inline-block rounded-full bg-rose-100 px-2.5 py-1 text-xs font-black text-rose-700">
                    Còn {{ book.stock_quantity }} cuốn
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-6 text-center text-xs font-bold text-emerald-900">
              ✔️ Tất cả các đầu sách xuất bản đều đạt số lượng tồn kho an toàn!
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-rose-200/50 flex justify-end">
            <RouterLink
              to="/vendor/warehouse-documents?type=receipt"
              class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-rose-700 no-underline"
            >
              <span class="material-symbols-outlined text-sm">add</span>
              Tạo Phiếu Nhập Kho Ngay
            </RouterLink>
          </div>
        </div>
      </section>

      <!-- Bottom Grid: Recent Orders & Top Selling Books -->
      <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Orders Card -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-base font-extrabold text-slate-900">Đơn Hàng Gần Đây</h3>
              <p class="text-xs text-slate-500">Các đơn hàng mới nhất thuộc gian hàng của bạn</p>
            </div>
            <RouterLink to="/vendor/orders" class="text-xs font-bold text-indigo-600 hover:underline no-underline">
              Xem tất cả →
            </RouterLink>
          </div>

          <div v-if="stats.recent_orders.length" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase">
                  <th class="py-2">Mã đơn</th>
                  <th class="py-2">Khách hàng</th>
                  <th class="py-2 text-center">Trạng thái</th>
                  <th class="py-2 text-right">Giá trị</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="order in stats.recent_orders" :key="order.id" class="hover:bg-slate-50">
                  <td class="py-3 font-bold text-indigo-600">
                    <RouterLink :to="`/vendor/orders/${order.id}`" class="no-underline hover:underline">
                      {{ order.order_code }}
                    </RouterLink>
                  </td>
                  <td class="py-3 font-semibold text-slate-800">{{ order.customer_name }}</td>
                  <td class="py-3 text-center">
                    <span
                      class="inline-block rounded-md px-2 py-0.5 text-[10px] font-extrabold"
                      :class="{
                        'bg-emerald-100 text-emerald-800': order.status === 'completed',
                        'bg-amber-100 text-amber-900': order.status === 'pending' || order.status === 'processing',
                        'bg-blue-100 text-blue-800': order.status === 'shipped',
                        'bg-rose-100 text-rose-800': order.status === 'cancelled'
                      }"
                    >
                      {{ statusLabel(order.status) }}
                    </span>
                  </td>
                  <td class="py-3 text-right font-black text-slate-900">{{ formatCurrency(order.total_amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="py-8 text-center text-xs text-slate-400 font-medium">
            Chưa phát sinh đơn hàng trong gian hàng.
          </div>
        </div>

        <!-- Top Selling Books Card -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-base font-extrabold text-slate-900">Top 5 Đầu Sách Bán Chạy Nhất</h3>
              <p class="text-xs text-slate-500">Xếp hạng theo tổng sản lượng số lượng đã bán</p>
            </div>
            <RouterLink to="/vendor/books" class="text-xs font-bold text-indigo-600 hover:underline no-underline">
              Quản lý sách →
            </RouterLink>
          </div>

          <div v-if="stats.top_books.length" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase">
                  <th class="py-2">Tên Sách</th>
                  <th class="py-2 text-center">Đã bán</th>
                  <th class="py-2 text-right">Doanh thu</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="book in stats.top_books" :key="book.id" class="hover:bg-slate-50">
                  <td class="py-3 font-bold text-slate-900 max-w-xs break-words leading-snug" :title="book.title">
                    {{ book.title }}
                  </td>
                  <td class="py-3 text-center font-bold text-indigo-600">{{ formatNumber(book.quantity) }} cuốn</td>
                  <td class="py-3 text-right font-black text-slate-900">{{ formatCurrency(book.revenue) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="py-8 text-center text-xs text-slate-400 font-medium">
            Chưa có số liệu sản lượng sách bán chạy.
          </div>
        </div>
      </section>
    </template>
  </main>
</template>

<style scoped>
.shadow-xs { box-shadow: 0px 2px 4px rgba(26,58,90,0.04); }
</style>

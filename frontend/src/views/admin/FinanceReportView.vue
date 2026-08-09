<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()

const loading = ref(true)
const refreshing = ref(false)
const error = ref('')
const reportStatus = ref('unavailable')

const searchQuery = ref('')
const selectedChartYear = ref('all') // 'all' | '2026' | '2025' | '2024'
const selectedLedgerYear = ref('all') // 'all' | '2026' | '2025' | '2024'
const selectedMonthDetail = ref(null)
const showDetailModal = ref(false)
const topVendorSort = ref('revenue') // 'revenue' | 'orders'

const reportData = ref({
  kpi: {
    total_revenue: 0,
    monthly_revenue: 0,
    total_orders: 0,
    completed_orders: 0,
    total_customers: 0,
    avg_order_value: 0,
    total_commission: 0,
    total_vendor_net: 0,
    total_refunds: 0,
  },
  revenue_by_month: [],
  revenue_by_payment_method: [],
  top_vendors: [],
  order_status_stats: [],
  vendors_list: [],
  payout_stats: { pending: 0, approved: 0, rejected: 0 },
  reporting_policy: {}
})

const fetchReport = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await apiClient.get('/api/admin/finance-report')
    if (res.data.status === 'success') {
      reportData.value = res.data.data
      reportStatus.value = 'success'
    } else if (res.data.status === 'unavailable') {
      reportStatus.value = 'unavailable'
    }
  } catch (requestError) {
    const message = requestError.response?.data?.message || 'Không thể tải báo cáo tài chính.'
    error.value = message
    toast.add({ severity: 'error', summary: 'Lỗi', detail: message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const refreshReport = async () => {
  const reason = window.prompt('Nhập lý do làm mới báo cáo tài chính:')?.trim()
  if (!reason) return

  refreshing.value = true
  try {
    const idempotencyKey = globalThis.crypto?.randomUUID?.() || `finance-refresh-${Date.now()}-${Math.random()}`
    await apiClient.post('/api/admin/finance-report/refresh', {
      reason,
      idempotency_key: idempotencyKey
    }, { headers: { 'Idempotency-Key': idempotencyKey } })
    await fetchReport()
    toast.add({ severity: 'success', summary: 'Đã làm mới', detail: 'Báo cáo doanh thu 24 tháng đã được cập nhật.', life: 3000 })
  } catch (requestError) {
    toast.add({ severity: 'error', summary: 'Không thể làm mới', detail: requestError.response?.data?.message || 'Vui lòng thử lại.', life: 3000 })
  } finally {
    refreshing.value = false
  }
}

const exportReport = () => {
  window.open('/api/admin/finance-report/export', '_blank', 'noopener')
}

const printReport = () => {
  window.print()
}

const openMonthDetail = (row) => {
  selectedMonthDetail.value = row
  showDetailModal.value = true
}

const closeMonthDetail = () => {
  showDetailModal.value = false
  selectedMonthDetail.value = null
}

onMounted(() => {
  fetchReport()
})

const formatVND = (val) => new Intl.NumberFormat('vi-VN').format(Number(val) || 0) + ' ₫'
const formatAmount = (val) => (val === null || val === undefined ? '—' : formatVND(val))

// Available Years for filter dropdowns
const availableYears = computed(() => {
  const months = reportData.value.revenue_by_month || []
  const yearsSet = new Set(months.map(m => m.month.split('-')[0]))
  return Array.from(yearsSet).sort((a, b) => b.localeCompare(a))
})

// KPI Cards Data
const kpiCards = computed(() => {
  const k = reportData.value.kpi || {}
  return [
    {
      label: 'Tổng Doanh Thu (24 Tháng)',
      value: formatAmount(k.total_revenue),
      subtext: `Đơn hoàn tất: ${k.completed_orders || 0}`,
      icon: 'account_balance_wallet',
      color: 'border-l-4 border-l-primary bg-surface-container-lowest',
      badge: 'Lũy kế 24 tháng',
      badgeBg: 'bg-primary-container text-on-primary-container'
    },
    {
      label: 'Doanh Thu Tháng Này',
      value: formatAmount(k.monthly_revenue),
      subtext: `AOV: ${formatAmount(k.avg_order_value)}`,
      icon: 'calendar_month',
      color: 'border-l-4 border-l-brand-green bg-surface-container-lowest',
      badge: 'Tháng hiện tại',
      badgeBg: 'bg-brand-green-container text-on-brand-green-container'
    },
    {
      label: 'Hoa Hồng Sàn Sau Hoàn',
      value: formatAmount(k.platform_commission_retention),
      subtext: 'Hoa hồng ghi nhận sau khi trừ hoàn hoa hồng; tỷ lệ thay đổi theo chứng từ.',
      icon: 'percent',
      color: 'border-l-4 border-l-purple-500 bg-surface-container-lowest',
      badge: 'Theo chứng từ',
      badgeBg: 'bg-purple-100 text-purple-800'
    },
    {
      label: 'Phí Vận Chuyển Thu Hộ',
      value: formatAmount(k.shipping_revenue),
      subtext: 'Thu hộ chuyển giao ĐVVC / Shipper',
      icon: 'local_shipping',
      color: 'border-l-4 border-l-amber-500 bg-surface-container-lowest',
      badge: 'Phí ship ĐVVC',
      badgeBg: 'bg-amber-100 text-amber-800'
    },
    {
      label: 'Doanh Thu Ròng Nhà Bán Sau Hoàn',
      value: formatAmount(k.vendor_net_after_refunds),
      subtext: 'Sau hoàn thu nhập/điều chỉnh từ chứng từ đối soát.',
      icon: 'storefront',
      color: 'border-l-4 border-l-blue-500 bg-surface-container-lowest',
      badge: 'Chi trả gian hàng',
      badgeBg: 'bg-blue-100 text-blue-800'
    },
    {
      label: 'Đơn Hàng & Khách Hàng',
      value: formatAmount(k.completed_orders),
      subtext: 'Tổng đơn và khách hàng: chưa có dữ liệu từ báo cáo bất biến.',
      icon: 'shopping_bag',
      color: 'border-l-4 border-l-emerald-500 bg-surface-container-lowest',
      badge: 'Tỉ lệ hoàn tất',
      badgeBg: 'bg-emerald-100 text-emerald-800'
    },
    {
      label: 'Yêu Cầu Rút Tiền Chờ Duyệt',
      value: '—',
      subtext: 'Không có dữ liệu rút tiền trong báo cáo bất biến.',
      icon: 'pending_actions',
      color: 'border-l-4 border-l-amber-500 bg-surface-container-lowest',
      badge: 'Cần xử lý',
      badgeBg: 'bg-amber-100 text-amber-800'
    }
  ]
})

// Revenue Chart Months (Filtered by selectedChartYear)
const chartRevenueMonths = computed(() => {
  let list = reportData.value.revenue_by_month || []
  if (selectedChartYear.value !== 'all') {
    list = list.filter(m => m.month.startsWith(selectedChartYear.value))
  }
  return list
})

// Max monthly revenue for visual bar scaling with Y-Axis coordinate ticks
const maxChartRevenueScale = computed(() => {
  const months = chartRevenueMonths.value
  const maxVal = Math.max(...months.map(m => Number(m.gross_revenue) || 0), 0)
  if (maxVal > 2000000) return Math.ceil(maxVal / 500000) * 500000
  return 2000000
})

// 24-Month Ledger Table (Sorted from NEWEST month down & filtered by Year/Search)
const filteredMonths = computed(() => {
  let list = [...(reportData.value.revenue_by_month || [])]

  // Sort descending by month string ('2026-08' -> '2024-08')
  list.sort((a, b) => b.month.localeCompare(a.month))

  if (selectedLedgerYear.value !== 'all') {
    list = list.filter(m => m.month.startsWith(selectedLedgerYear.value))
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(m => m.month.toLowerCase().includes(q))
  }
  return list
})

// Filtered & Sorted Top Vendors Table
const filteredTopVendors = computed(() => {
  let list = [...(reportData.value.top_vendors || [])]
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(v => v.shop_name.toLowerCase().includes(q))
  }
  if (topVendorSort.value === 'orders') {
    list.sort((a, b) => b.total_orders - a.total_orders)
  } else {
    list.sort((a, b) => b.revenue - a.revenue)
  }
  return list
})

// SVG Donut Segments for Payment Methods Chart
const paymentDonutSegments = computed(() => {
  const raw = reportData.value.revenue_by_payment_method || []
  const colors = {
    cod: '#059669', // Emerald
    online: '#2563eb', // Blue
    vnpay: '#2563eb',
    wallet: '#8b5cf6', // Purple
  }
  const totalRev = raw.reduce((sum, item) => sum + Number(item.revenue || 0), 0) || 1
  let cumulativePct = 0
  const radius = 40
  const circumference = 2 * Math.PI * radius // ~251.32

  return raw.map(item => {
    const rev = Number(item.revenue || 0)
    const count = Number(item.count || 0)
    const pct = rev / totalRev
    const strokeDasharray = `${pct * circumference} ${circumference}`
    const strokeDashoffset = -cumulativePct * circumference
    cumulativePct += pct

    const label = item.payment_method === 'cod' ? 'Thanh toán COD' :
                  item.payment_method === 'vnpay' || item.payment_method === 'online' ? 'VNPay / Chuyển khoản' :
                  item.payment_method === 'wallet' ? 'Ví KomiBook' : item.payment_method

    return {
      method: item.payment_method,
      label,
      revenue: rev,
      count,
      pct: Math.round(pct * 100),
      color: colors[item.payment_method] || '#64748b',
      strokeDasharray,
      strokeDashoffset
    }
  })
})

// SVG Donut Segments for Order Status Chart
const orderStatusDonutSegments = computed(() => {
  const stats = reportData.value.order_status_stats || []
  const meta = {
    completed: { label: 'Hoàn tất', color: '#059669' },
    processing: { label: 'Đang xử lý', color: '#2563eb' },
    shipping: { label: 'Đang giao', color: '#8b5cf6' },
    cancelled: { label: 'Đã hủy', color: '#e11d48' },
    refunded: { label: 'Đã hoàn tiền', color: '#d97706' }
  }
  const totalCount = stats.reduce((sum, item) => sum + Number(item.count || 0), 0) || 1
  let cumulativePct = 0
  const radius = 40
  const circumference = 2 * Math.PI * radius

  return stats.map(item => {
    const count = Number(item.count || 0)
    const total = Number(item.total || 0)
    const pct = count / totalCount
    const strokeDasharray = `${pct * circumference} ${circumference}`
    const strokeDashoffset = -cumulativePct * circumference
    cumulativePct += pct

    return {
      status: item.status,
      label: meta[item.status]?.label || item.status,
      color: meta[item.status]?.color || '#64748b',
      count,
      total,
      pct: Math.round(pct * 100),
      strokeDasharray,
      strokeDashoffset
    }
  })
})
</script>

<template>
  <div class="pb-24 w-full pt-4">
    <!-- Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
      <div>
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-3xl">insights</span>
          <h1 class="text-2xl font-black text-on-surface">Báo cáo tài chính & Doanh thu</h1>
        </div>
        <p class="text-on-surface-variant text-sm mt-1">Tổng quan doanh thu 24 tháng, hoa hồng sàn, dòng tiền chi trả và đối soát gian hàng</p>
      </div>

      <div class="flex flex-wrap gap-2 print:hidden">
        <button
          type="button"
          class="flex min-h-11 items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 font-bold text-primary transition-colors hover:bg-surface-container-high disabled:opacity-50"
          :disabled="refreshing"
          @click="refreshReport"
        >
          <span class="material-symbols-outlined text-lg" :class="{ 'animate-spin': refreshing }">refresh</span>
          {{ refreshing ? 'Đang cập nhật…' : 'Làm mới' }}
        </button>

        <button
          type="button"
          class="flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 font-bold text-on-primary shadow-sm transition-colors hover:bg-primary-container"
          @click="exportReport"
        >
          <span class="material-symbols-outlined text-lg">download</span>
          Xuất CSV 24 Tháng
        </button>

        <button
          type="button"
          class="flex min-h-11 items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 font-bold text-on-surface transition-colors hover:bg-surface-container-high"
          @click="printReport"
        >
          <span class="material-symbols-outlined text-lg">print</span>
          In / Xuất PDF
        </button>
      </div>
    </header>

    <!-- Quick Operations Links Navigation Bar (Removed Kê khai thuế nhà bán button as requested) -->
    <nav class="mb-6 flex flex-wrap items-center gap-2 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-2 shadow-xs print:hidden">
      <span class="px-3 text-xs font-bold uppercase tracking-wider text-outline">Thao tác nhanh:</span>

      <RouterLink
        to="/admin/reconciliation"
        class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-outline-variant/50 bg-surface-container-low px-3 text-sm font-semibold text-on-surface no-underline transition-colors hover:border-primary hover:text-primary"
      >
        <i class="pi pi-sync text-sm text-primary"></i>Đối soát thanh toán
      </RouterLink>

      <RouterLink
        to="/admin/fee-schedules"
        class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-outline-variant/50 bg-surface-container-low px-3 text-sm font-semibold text-on-surface no-underline transition-colors hover:border-primary hover:text-primary"
      >
        <i class="pi pi-percentage text-sm text-primary"></i>Cấu hình Biểu phí sàn
      </RouterLink>

      <RouterLink
        to="/admin/books"
        class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-outline-variant/50 bg-surface-container-low px-3 text-sm font-semibold text-on-surface no-underline transition-colors hover:border-primary hover:text-primary"
      >
        <i class="pi pi-book text-sm text-primary"></i>Quản lý Catalog Sách
      </RouterLink>
    </nav>

    <!-- Filter & Search Bar -->
    <div class="mb-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-3 shadow-xs print:hidden">
      <div class="relative min-w-0 flex-1">
        <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
        <input
          v-model.trim="searchQuery"
          type="search"
          placeholder="Tìm theo tháng (2026-07) hoặc tên nhà bán hàng…"
          class="w-full rounded-xl border border-outline-variant bg-surface px-10 py-2.5 text-sm text-on-surface placeholder:text-outline focus:border-primary focus:outline-none"
        />
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <label class="flex items-center gap-2 text-xs font-bold text-outline">
          Sắp xếp Top Nhà Bán:
          <select v-model="topVendorSort" class="rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
            <option value="revenue">Theo Doanh Thu</option>
            <option value="orders">Theo Số Đơn Hàng</option>
          </select>
        </label>

        <button
          v-if="searchQuery"
          type="button"
          class="rounded-xl border border-outline-variant px-3 py-2 text-xs font-bold text-outline hover:bg-surface-container-high"
          @click="searchQuery = ''"
        >
          Xóa bộ lọc
        </button>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6" role="status" aria-label="Đang tải dữ liệu">
      <div v-for="i in 6" :key="i" class="h-28 rounded-2xl bg-surface-container-lowest border border-outline-variant/30 p-4 animate-pulse"></div>
    </div>

    <!-- Error Alert -->
    <div v-else-if="error" class="mb-6 rounded-2xl border border-error/30 bg-error-container p-6 text-center text-on-error-container" role="alert">
      <span class="material-symbols-outlined text-4xl text-error">error</span>
      <h3 class="mt-2 text-lg font-bold">Không thể tải dữ liệu báo cáo</h3>
      <p class="mt-1 text-sm opacity-90">{{ error }}</p>
      <button type="button" class="mt-4 rounded-xl bg-error px-6 py-2.5 font-bold text-white shadow-sm" @click="fetchReport">Thử lại</button>
    </div>

    <div v-else-if="reportStatus === 'unavailable'" class="mb-6 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-6 text-center" role="status">
      <span class="material-symbols-outlined text-4xl text-outline">inventory_2</span>
      <h3 class="mt-2 text-lg font-bold text-on-surface">Chưa có dữ liệu</h3>
      <p class="mt-1 text-sm text-outline">Báo cáo hoàn tất sẽ xuất hiện sau khi quản trị viên làm mới có lý do.</p>
    </div>

    <!-- Main Content Dashboard -->
    <template v-else>
      <!-- KPI Cards Grid (6 Cards) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <div
          v-for="(card, idx) in kpiCards"
          :key="idx"
          :class="card.color"
          class="rounded-2xl p-4 shadow-xs transition-all hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between"
        >
          <div class="flex items-start justify-between">
            <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">{{ card.label }}</span>
            <span :class="card.badgeBg" class="rounded-full px-2 py-0.5 text-[10px] font-extrabold">{{ card.badge }}</span>
          </div>

          <div class="my-2">
            <div class="text-2xl font-black text-on-surface tracking-tight">{{ card.value }}</div>
            <p class="mt-0.5 text-xs font-medium text-outline">{{ card.subtext }}</p>
          </div>

          <div class="flex items-center justify-between border-t border-outline-variant/20 pt-2 text-[11px] font-semibold text-on-surface-variant">
            <span>Dữ liệu xác thực hệ thống</span>
            <span class="material-symbols-outlined text-sm text-primary">{{ card.icon }}</span>
          </div>
        </div>
      </div>

      <!-- Revenue Trend Chart by Year with Vertical Y-Axis Coordinates -->
      <section class="mb-6 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <h2 class="text-lg font-extrabold text-on-surface">Xu Hướng Doanh Thu Theo Năm</h2>
            <p class="text-xs text-outline">Hiển thị cột doanh thu chính xác tỉ lệ chuẩn theo tọa độ trục Y</p>
          </div>

          <!-- Select Year Filter for Revenue Trend -->
          <div class="flex items-center gap-2">
            <label class="text-xs font-bold text-outline">Xem theo năm:</label>
            <select
              v-model="selectedChartYear"
              class="rounded-xl border border-outline-variant bg-surface px-3 py-1.5 text-xs font-bold text-on-surface focus:border-primary focus:outline-none cursor-pointer"
            >
              <option value="all">Tất cả các năm (24 Tháng)</option>
              <option v-for="yr in availableYears" :key="yr" :value="yr">Năm {{ yr }}</option>
            </select>
          </div>
        </div>

        <!-- Chart Area with Standard Y-Axis Vertical Grid Coordinates -->
        <div class="mt-6 flex h-64 w-full gap-2">
          <!-- Y-Axis Labels (Left) -->
          <div class="flex flex-col justify-between text-[10px] font-bold text-outline pr-2 text-right w-24 pb-8 border-r border-outline-variant/30">
            <span>{{ formatVND(maxChartRevenueScale) }}</span>
            <span>{{ formatVND(maxChartRevenueScale * 0.75) }}</span>
            <span>{{ formatVND(maxChartRevenueScale * 0.5) }}</span>
            <span>{{ formatVND(maxChartRevenueScale * 0.25) }}</span>
            <span>0 ₫</span>
          </div>

          <!-- Grid & Bars Container -->
          <div class="relative flex-1 flex flex-col justify-between border-b border-outline-variant/30 pb-2 px-2">
            <!-- Horizontal Grid Lines -->
            <div class="absolute inset-0 pb-8 flex flex-col justify-between pointer-events-none">
              <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
              <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
              <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
              <div class="border-b border-dashed border-outline-variant/25 w-full"></div>
              <div class="border-b border-outline-variant/40 w-full"></div>
            </div>

            <!-- Columns -->
            <div class="flex items-end justify-between gap-2 h-full w-full relative z-10 overflow-x-auto">
              <div
                v-for="row in chartRevenueMonths"
                :key="row.month"
                class="group relative flex-1 flex flex-col items-center h-full justify-end min-w-[36px] cursor-pointer"
                @click="openMonthDetail(row)"
              >
                <!-- Value Tag Directly Above Bar -->
                <div class="mb-1 text-center min-h-[20px] flex items-end">
                  <span
                    v-if="row.gross_revenue > 0"
                    class="inline-block rounded-md bg-emerald-900 px-1.5 py-0.5 text-[9px] font-black text-white shadow-xs"
                  >
                    {{ formatAmount(row.gross_revenue) }}
                  </span>
                  <span v-else class="text-[9px] font-semibold text-outline">0 ₫</span>
                </div>

                <!-- Bar visual with EXACT percentage height relative to Y-axis scale -->
                <div class="w-full flex items-end justify-center h-44 bg-surface-container-low/30 rounded-t-md overflow-hidden">
                  <div
                    class="w-full rounded-t-md bg-brand-green transition-all duration-500 group-hover:bg-brand-green-strong"
                    :style="{ height: `${(row.gross_revenue / maxChartRevenueScale) * 100}%` }"
                  ></div>
                </div>

                <!-- Month Label below -->
                <div class="mt-2 text-center">
                  <div class="text-[11px] font-extrabold text-on-surface">{{ row.month }}</div>
                </div>
              </div>

              <div v-if="chartRevenueMonths.length === 0" class="w-full text-center py-16 text-sm text-outline">
                Không có dữ liệu doanh thu cho năm được chọn
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- SVG Donut / Pie Charts Section: Payment Methods & Order Status -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Payment Methods Donut / Pie Chart -->
        <section class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-extrabold text-on-surface">Cơ Cấu Phương Thức Thanh Toán</h3>
            <span class="material-symbols-outlined text-outline">pie_chart</span>
          </div>

          <div class="flex flex-col sm:flex-row items-center gap-6">
            <!-- SVG Donut Chart -->
            <div class="relative flex items-center justify-center h-44 w-44 shrink-0">
              <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="40" fill="transparent" stroke="#e2e8f0" stroke-width="14" />
                <circle
                  v-for="seg in paymentDonutSegments"
                  :key="seg.method"
                  cx="50"
                  cy="50"
                  r="40"
                  fill="transparent"
                  :stroke="seg.color"
                  stroke-width="14"
                  :stroke-dasharray="seg.strokeDasharray"
                  :stroke-dashoffset="seg.strokeDashoffset"
                  class="transition-all duration-500 hover:opacity-80"
                />
              </svg>

              <!-- Center Text -->
              <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                <span class="text-[10px] font-bold uppercase text-outline">Thanh toán</span>
                <span class="text-base font-black text-on-surface">{{ paymentDonutSegments.length }} Loại</span>
              </div>
            </div>

            <!-- Legend List -->
            <div class="flex-1 space-y-2.5 w-full">
              <div
                v-for="seg in paymentDonutSegments"
                :key="seg.method"
                class="flex items-center justify-between rounded-xl border border-outline-variant/30 bg-surface-container-low p-2.5 text-xs font-semibold"
              >
                <div class="flex items-center gap-2">
                  <span class="h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: seg.color }"></span>
                  <span class="text-on-surface font-bold">{{ seg.label }}</span>
                </div>
                <div class="text-right">
                  <div class="font-black text-on-surface">{{ formatVND(seg.revenue) }}</div>
                  <div class="text-[10px] text-outline">{{ seg.count }} đơn ({{ seg.pct }}%)</div>
                </div>
              </div>

              <div v-if="paymentDonutSegments.length === 0" class="text-center py-4 text-xs text-outline">
                Chưa có dữ liệu thanh toán
              </div>
            </div>
          </div>
        </section>

        <!-- Order Status Distribution Donut / Pie Chart -->
        <section class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-extrabold text-on-surface">Tỉ Lệ Trạng Thái Đơn Hàng</h3>
            <span class="material-symbols-outlined text-outline">donut_large</span>
          </div>

          <div class="flex flex-col sm:flex-row items-center gap-6">
            <!-- SVG Donut Chart -->
            <div class="relative flex items-center justify-center h-44 w-44 shrink-0">
              <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="40" fill="transparent" stroke="#e2e8f0" stroke-width="14" />
                <circle
                  v-for="seg in orderStatusDonutSegments"
                  :key="seg.status"
                  cx="50"
                  cy="50"
                  r="40"
                  fill="transparent"
                  :stroke="seg.color"
                  stroke-width="14"
                  :stroke-dasharray="seg.strokeDasharray"
                  :stroke-dashoffset="seg.strokeDashoffset"
                  class="transition-all duration-500 hover:opacity-80"
                />
              </svg>

              <!-- Center Text -->
              <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                <span class="text-[10px] font-bold uppercase text-outline">Đơn hàng</span>
                <span class="text-base font-black text-on-surface">{{ reportData.kpi?.completed_orders || 0 }} Đơn</span>
              </div>
            </div>

            <!-- Legend List -->
            <div class="flex-1 space-y-2 w-full max-h-52 overflow-y-auto pr-1">
              <div
                v-for="seg in orderStatusDonutSegments"
                :key="seg.status"
                class="flex items-center justify-between rounded-xl border border-outline-variant/30 bg-surface-container-low p-2 text-xs font-semibold"
              >
                <div class="flex items-center gap-2">
                  <span class="h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: seg.color }"></span>
                  <span class="text-on-surface font-bold">{{ seg.label }}</span>
                </div>
                <div class="text-right">
                  <div class="font-black text-on-surface">{{ seg.count }} đơn ({{ seg.pct }}%)</div>
                  <div class="text-[10px] text-outline">{{ formatVND(seg.total) }}</div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Top Vendors Table -->
      <section class="mb-6 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <h3 class="text-lg font-extrabold text-on-surface">Xếp Hạng Top Nhà Bán Hàng Chi Trả Doanh Thu Cao Nhất</h3>
            <p class="text-xs text-outline">Doanh thu và tổng số đơn hàng đã hoàn tất thành công trên KomiBook</p>
          </div>
          <span class="text-xs font-bold text-primary">Top {{ filteredTopVendors.length }} Nhà Bán</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm border-collapse">
            <thead>
              <tr class="border-b border-outline-variant/30 text-xs font-bold uppercase text-outline">
                <th class="py-3 px-3">Thứ hạng</th>
                <th class="py-3 px-3">Tên Nhà Bán Hàng</th>
                <th class="py-3 px-3 text-right">Tổng Đơn Hàng</th>
                <th class="py-3 px-3 text-right">Doanh Thu Khách Thanh Toán</th>
                <th class="py-3 px-3 text-right">Ước Tính Phí Sàn (Commission)</th>
                <th class="py-3 px-3 text-center print:hidden">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
              <tr
                v-for="(vendor, index) in filteredTopVendors"
                :key="vendor.id"
                class="hover:bg-surface-container-low/60 transition-colors"
              >
                <td class="py-3 px-3 font-extrabold">
                  <span
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                    :class="index === 0 ? 'bg-amber-400 text-amber-950 font-black' : index === 1 ? 'bg-slate-300 text-slate-900 font-black' : index === 2 ? 'bg-amber-700 text-amber-100' : 'bg-surface-container-high text-on-surface'"
                  >
                    {{ index + 1 }}
                  </span>
                </td>

                <td class="py-3 px-3 font-bold text-primary">
                  {{ vendor.shop_name }}
                </td>

                <td class="py-3 px-3 text-right font-medium">
                  {{ vendor.total_orders }} đơn
                </td>

                <td class="py-3 px-3 text-right font-black text-on-surface">
                  {{ formatVND(vendor.revenue) }}
                </td>

                <td class="py-3 px-3 text-right font-bold text-purple-700">
                  {{ formatAmount(vendor.commission_amount) }}
                </td>

                <td class="py-3 px-3 text-center print:hidden">
                  <RouterLink
                    to="/admin/reconciliation"
                    class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-2.5 py-1 text-xs font-bold text-primary hover:border-primary no-underline"
                  >
                    Đối soát
                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                  </RouterLink>
                </td>
              </tr>

              <tr v-if="filteredTopVendors.length === 0">
                <td colspan="6" class="py-8 text-center text-sm text-outline">
                  Không tìm thấy nhà bán hàng phù hợp với từ khóa
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- 24-Month Ledger Table (Sorted from NEWEST month down & Year filter dropdown) -->
      <section class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-xs">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h3 class="text-lg font-extrabold text-on-surface">Kho Bản Kê Doanh Thu Chi Tiết</h3>
            <p class="text-xs text-outline">Bản kê đã hoàn tất tại thời điểm chốt dữ liệu, từ tháng mới nhất.</p>
          </div>

          <!-- Select Year Filter for Ledger Table -->
          <div class="flex items-center gap-2">
            <label class="text-xs font-bold text-outline">Lọc bản kê theo năm:</label>
            <select
              v-model="selectedLedgerYear"
              class="rounded-xl border border-outline-variant bg-surface px-3 py-1.5 text-xs font-bold text-on-surface focus:border-primary focus:outline-none cursor-pointer"
            >
              <option value="all">Tất cả các năm (24 Tháng)</option>
              <option v-for="yr in availableYears" :key="yr" :value="yr">Năm {{ yr }}</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm border-collapse min-w-[800px]">
            <thead>
              <tr class="border-b border-outline-variant/30 text-xs font-bold uppercase text-outline">
                <th class="py-3 px-3">Tháng (Mới nhất)</th>
                <th class="py-3 px-3 text-right">Khách Thanh Toán</th>
                <th class="py-3 px-3 text-right">Đơn Hoàn Tất</th>
                <th class="py-3 px-3 text-right">Phí Sàn (Commission)</th>
                <th class="py-3 px-3 text-right">Doanh Thu Ròng Nhà Bán Sau Hoàn</th>
                <th class="py-3 px-3 text-right">Hoàn Tiền</th>
                <th class="py-3 px-3 text-right">Biên Phí Sàn (%)</th>
                <th class="py-3 px-3 text-center print:hidden">Chi tiết</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
              <tr
                v-for="row in filteredMonths"
                :key="row.month"
                class="hover:bg-surface-container-low/60 transition-colors cursor-pointer"
                @click="openMonthDetail(row)"
              >
                <td class="py-3.5 px-3 font-black text-primary">
                  {{ row.month }}
                </td>

                <td class="py-3.5 px-3 text-right font-bold text-on-surface">
                  {{ formatAmount(row.gross_revenue) }}
                </td>

                <td class="py-3.5 px-3 text-right font-medium">
                  {{ row.completed_orders }} đơn
                </td>

                <td class="py-3.5 px-3 text-right font-bold text-purple-700">
                  {{ formatAmount(row.commission_amount) }}
                </td>

                <td class="py-3.5 px-3 text-right font-bold text-emerald-700">
                  {{ formatAmount(row.vendor_net_after_refunds) }}
                </td>

                <td class="py-3.5 px-3 text-right font-medium text-rose-600">
                  {{ formatAmount(row.refund_amount) }}
                </td>

                <td class="py-3.5 px-3 text-right font-bold text-on-surface-variant">
                  Thay đổi
                </td>

                <td class="py-3.5 px-3 text-center print:hidden" @click.stop>
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-primary-container/40 px-2.5 py-1 text-xs font-bold text-primary hover:bg-primary-container"
                    @click="openMonthDetail(row)"
                  >
                    Xem bản kê
                    <span class="material-symbols-outlined text-xs">visibility</span>
                  </button>
                </td>
              </tr>

              <tr v-if="filteredMonths.length === 0">
                <td colspan="8" class="py-8 text-center text-sm text-outline">
                  Không có tháng nào phù hợp với bộ lọc tìm kiếm
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- Detail Breakdown Modal -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div v-if="showDetailModal && selectedMonthDetail" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" @click="closeMonthDetail">
        <div class="w-full max-w-xl rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 shadow-2xl overflow-hidden" @click.stop>
          <div class="flex items-center justify-between border-b border-outline-variant/30 pb-4">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-2xl">receipt_long</span>
              <div>
                <h3 class="text-lg font-black text-on-surface">Bản Kê Chi Tiết Doanh Thu {{ selectedMonthDetail.month }}</h3>
                <p class="text-xs text-outline">Mã chứng từ đối soát tài chính hàng tháng</p>
              </div>
            </div>
            <button type="button" class="rounded-xl p-1 text-outline hover:bg-surface-container-high hover:text-on-surface" @click="closeMonthDetail">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="my-4 space-y-3 text-sm">
            <div class="flex justify-between items-center py-2 border-b border-outline-variant/20">
              <span class="text-on-surface-variant font-medium">Tổng doanh thu khách thanh toán:</span>
              <span class="font-black text-base text-on-surface">{{ formatAmount(selectedMonthDetail.gross_revenue) }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-outline-variant/20">
              <span class="text-on-surface-variant font-medium">Số đơn hàng hoàn tất:</span>
              <span class="font-bold text-on-surface">{{ selectedMonthDetail.completed_orders }} đơn</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-outline-variant/20">
              <span class="text-on-surface-variant font-medium">Phí hoa hồng KomiBook (Commission):</span>
              <span class="font-bold text-purple-700">{{ formatAmount(selectedMonthDetail.commission_amount) }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-outline-variant/20">
              <span class="text-on-surface-variant font-medium">Doanh thu ròng Nhà bán sau hoàn thu nhập/điều chỉnh:</span>
              <span class="font-bold text-emerald-700">{{ formatAmount(selectedMonthDetail.vendor_net_after_refunds) }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-outline-variant/20">
              <span class="text-on-surface-variant font-medium">Số tiền trả hàng / hoàn tiền:</span>
              <span class="font-bold text-rose-600">{{ formatAmount(selectedMonthDetail.refund_amount) }}</span>
            </div>
            <div class="flex justify-between items-center py-2">
              <span class="text-on-surface-variant font-medium">Tỷ lệ phí sàn thực tế:</span>
              <span class="font-extrabold text-primary">Không công bố (thay đổi theo chứng từ)</span>
            </div>
          </div>

          <div class="rounded-xl bg-surface-container-low p-3 text-xs text-outline mb-4">
            Ghi chú: Dữ liệu này được đối soát tự động bởi hệ thống KomiBook. Hỗ trợ trích xuất làm căn cứ kê khai nghĩa vụ tài chính theo quy định.
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-outline-variant/30">
            <button type="button" class="rounded-xl border border-outline-variant px-4 py-2 text-sm font-bold text-on-surface hover:bg-surface-container-high" @click="closeMonthDetail">Đóng</button>
            <button type="button" class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary shadow-xs hover:bg-primary-container" @click="printReport">In bản kê</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.shadow-card { box-shadow: 0px 12px 24px -4px rgba(26,58,90,0.04); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
@media print {
  body { background: white !important; }
}
</style>

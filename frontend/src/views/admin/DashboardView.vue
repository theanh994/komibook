<script setup>
import { computed, onMounted, ref } from 'vue'
import apiClient from '@/services/axios'

const emptyStats = () => ({
  total_users: 0,
  total_used_book_sellers: 0,
  total_vendors: 0,
  total_books: 0,
  total_revenue: 0,
  total_orders: 0,
  pending_orders: 0,
  commerce_trend: { labels: [], orders: [], revenue: [] },
  account_growth: { labels: [], users: [], used_book_sellers: [], vendors: [] },
  book_distribution: { by_type: {}, by_status: {} },
  order_status_distribution: {},
  operational_queues: {},
})

const stats = ref(emptyStats())
const loading = ref(true)
const errorMessage = ref('')

const cards = [
  { key: 'total_users', label: 'Tài khoản', icon: 'group', format: 'number' },
  { key: 'total_used_book_sellers', label: 'Người bán sách cũ', icon: 'menu_book', format: 'number' },
  { key: 'total_vendors', label: 'Nhà bán hàng', icon: 'storefront', format: 'number' },
  { key: 'total_books', label: 'Đầu sách', icon: 'menu_book', format: 'number' },
  { key: 'total_orders', label: 'Đơn hàng', icon: 'orders', format: 'number' },
  { key: 'total_revenue', label: 'Doanh thu hoàn thành', icon: 'payments', format: 'currency' },
]

const queueLabels = {
  pending_orders: 'Đơn chờ xử lý',
  pending_vendors: 'Nhà bán hàng chờ duyệt',
  draft_books: 'Sách đang ở bản nháp',
}
const typeLabels = { physical: 'Sách vật lý', ebook: 'Ebook' }
const bookStatusLabels = { draft: 'Bản nháp', published: 'Đã xuất bản', out_of_stock: 'Hết hàng' }
const orderStatusLabels = {
  pending: 'Chờ xử lý',
  confirmed: 'Đã xác nhận',
  processing: 'Đang xử lý',
  shipped: 'Đang giao',
  completed: 'Hoàn thành',
  cancelled: 'Đã hủy',
}

const commerceRows = computed(() => stats.value.commerce_trend.labels.map((label, index) => ({
  label,
  orders: Number(stats.value.commerce_trend.orders[index] || 0),
  revenue: Number(stats.value.commerce_trend.revenue[index] || 0),
})))
const accountRows = computed(() => stats.value.account_growth.labels.map((label, index) => ({
  label,
  users: Number(stats.value.account_growth.users[index] || 0),
  usedBookSellers: Number(stats.value.account_growth.used_book_sellers[index] || 0),
  vendors: Number(stats.value.account_growth.vendors[index] || 0),
})))
const bookDistribution = computed(() => [
  ...Object.entries(stats.value.book_distribution.by_type || {}).map(([key, value]) => ({ label: typeLabels[key] || key, value: Number(value), tone: 'primary' })),
  ...Object.entries(stats.value.book_distribution.by_status || {}).map(([key, value]) => ({ label: bookStatusLabels[key] || key, value: Number(value), tone: 'secondary' })),
])
const orderDistribution = computed(() => Object.entries(stats.value.order_status_distribution || {})
  .map(([key, value]) => ({ label: orderStatusLabels[key] || key, value: Number(value) })))
const maxRevenue = computed(() => Math.max(...commerceRows.value.map((row) => row.revenue), 1))
const maxAccountCount = computed(() => Math.max(...accountRows.value.flatMap((row) => [row.users, row.usedBookSellers, row.vendors]), 1))
const maxDistribution = (items) => Math.max(...items.map((item) => item.value), 1)
const barPercent = (value, max) => `${Math.max((Number(value) / max) * 100, value ? 4 : 0)}%`

const formatValue = (value, format = 'number') => format === 'currency'
  ? new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0))
  : new Intl.NumberFormat('vi-VN').format(Number(value || 0))

const fetchStats = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/admin/stats')
    stats.value = { ...emptyStats(), ...response.data?.data }
  } catch {
    stats.value = emptyStats()
    errorMessage.value = 'Không thể tải dữ liệu điều hành. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchStats)
</script>

<template>
  <main class="space-y-6" aria-labelledby="dashboard-title">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <p class="text-sm font-bold uppercase tracking-[0.16em] text-secondary">Trung tâm điều hành</p>
        <h1 id="dashboard-title" class="mt-1 text-2xl font-extrabold text-on-surface sm:text-3xl">Tổng quan hệ thống</h1>
        <p class="mt-1 text-sm text-on-surface-variant">Số liệu trực tiếp trên toàn sàn KomiBook, cập nhật khi bạn làm mới.</p>
      </div>
      <button type="button" class="ui-btn ui-btn-secondary self-start" :disabled="loading" @click="fetchStats">
        <span class="material-symbols-outlined text-xl" :class="{ 'animate-spin': loading }" aria-hidden="true">refresh</span>
        Làm mới
      </button>
    </header>

    <div v-if="errorMessage" class="ui-alert ui-alert-error flex flex-wrap items-center justify-between gap-3" role="alert">
      <span>{{ errorMessage }}</span>
      <button type="button" class="ui-btn ui-btn-secondary" @click="fetchStats">Thử lại</button>
    </div>

    <section aria-labelledby="kpi-title">
      <h2 id="kpi-title" class="sr-only">Chỉ số tổng quan</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <article v-for="card in cards" :key="card.key" class="ui-panel flex min-h-36 flex-col justify-between gap-4">
          <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-semibold text-on-surface-variant">{{ card.label }}</p>
            <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary-fixed text-on-primary-fixed" aria-hidden="true">
              <span class="material-symbols-outlined">{{ card.icon }}</span>
            </span>
          </div>
          <div v-if="loading" class="ui-skeleton h-9 w-3/4" aria-label="Đang tải"></div>
          <p v-else class="text-2xl font-extrabold tracking-tight text-primary">{{ formatValue(stats[card.key], card.format) }}</p>
        </article>
      </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-2" aria-label="Xu hướng hệ thống">
      <article class="ui-panel min-w-0">
        <div class="mb-5">
          <h2 class="text-lg font-extrabold text-on-surface">Doanh thu và đơn hoàn thành</h2>
          <p class="text-sm text-on-surface-variant">Sáu tháng gần nhất; cột biểu diễn doanh thu, số đơn được ghi dưới tháng.</p>
        </div>
        <div v-if="loading" class="ui-skeleton h-64" aria-label="Đang tải biểu đồ"></div>
        <div v-else class="chart-columns" role="img" aria-label="Biểu đồ doanh thu và đơn hoàn thành trong sáu tháng">
          <div v-for="row in commerceRows" :key="row.label" class="chart-column">
            <span class="chart-value">{{ formatValue(row.revenue, 'currency') }}</span>
            <span class="chart-bar bg-primary" :style="{ height: barPercent(row.revenue, maxRevenue) }"></span>
            <strong>{{ row.label }}</strong>
            <small>{{ formatValue(row.orders) }} đơn</small>
          </div>
        </div>
        <details class="data-table-details">
          <summary>Xem bảng dữ liệu doanh thu</summary>
          <div class="ui-table-scroll mt-3">
            <table><thead><tr><th>Tháng</th><th>Đơn hoàn thành</th><th>Doanh thu</th></tr></thead><tbody>
              <tr v-for="row in commerceRows" :key="`table-${row.label}`"><td>{{ row.label }}</td><td>{{ formatValue(row.orders) }}</td><td>{{ formatValue(row.revenue, 'currency') }}</td></tr>
            </tbody></table>
          </div>
        </details>
      </article>

      <article class="ui-panel min-w-0">
        <div class="mb-5">
          <h2 class="text-lg font-extrabold text-on-surface">Tăng trưởng tài khoản</h2>
          <p class="text-sm text-on-surface-variant">Tài khoản, người bán sách cũ và Nhà bán tạo mới theo tháng.</p>
        </div>
        <div class="mb-3 flex flex-wrap gap-4 text-xs font-bold" aria-label="Chú giải">
          <span class="legend-dot before:bg-primary">Tài khoản</span>
          <span class="legend-dot before:bg-secondary">Người bán sách cũ</span>
          <span class="legend-dot before:bg-commerce">Nhà bán hàng</span>
        </div>
        <div v-if="loading" class="ui-skeleton h-64" aria-label="Đang tải biểu đồ"></div>
        <div v-else class="chart-columns" role="img" aria-label="Biểu đồ tăng trưởng tài khoản trong sáu tháng">
          <div v-for="row in accountRows" :key="row.label" class="chart-column">
            <div class="flex h-44 w-full items-end justify-center gap-1">
              <span class="w-1/4 rounded-t bg-primary" :style="{ height: barPercent(row.users, maxAccountCount) }" :title="`${row.users} tài khoản`"></span>
              <span class="w-1/4 rounded-t bg-secondary" :style="{ height: barPercent(row.usedBookSellers, maxAccountCount) }" :title="`${row.usedBookSellers} người bán sách cũ`"></span>
              <span class="w-1/4 rounded-t bg-commerce" :style="{ height: barPercent(row.vendors, maxAccountCount) }" :title="`${row.vendors} nhà bán hàng`"></span>
            </div>
            <strong>{{ row.label }}</strong>
            <small>{{ row.users }}/{{ row.usedBookSellers }}/{{ row.vendors }}</small>
          </div>
        </div>
        <details class="data-table-details">
          <summary>Xem bảng dữ liệu tài khoản</summary>
          <div class="ui-table-scroll mt-3">
            <table><thead><tr><th>Tháng</th><th>Tài khoản</th><th>Người bán sách cũ</th><th>Nhà bán hàng</th></tr></thead><tbody>
              <tr v-for="row in accountRows" :key="`account-${row.label}`"><td>{{ row.label }}</td><td>{{ row.users }}</td><td>{{ row.usedBookSellers }}</td><td>{{ row.vendors }}</td></tr>
            </tbody></table>
          </div>
        </details>
      </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-3" aria-label="Phân bổ và hàng đợi">
      <article class="ui-panel">
        <h2 class="text-lg font-extrabold">Phân bổ sách</h2>
        <p class="mb-5 text-sm text-on-surface-variant">Theo định dạng và trạng thái hiện tại.</p>
        <div class="space-y-4">
          <div v-for="item in bookDistribution" :key="item.label">
            <div class="mb-1 flex justify-between gap-3 text-sm"><span>{{ item.label }}</span><strong>{{ formatValue(item.value) }}</strong></div>
            <div class="h-2.5 overflow-hidden rounded-full bg-surface-container"><span class="block h-full rounded-full" :class="item.tone === 'primary' ? 'bg-primary' : 'bg-secondary'" :style="{ width: barPercent(item.value, maxDistribution(bookDistribution)) }"></span></div>
          </div>
        </div>
      </article>

      <article class="ui-panel">
        <h2 class="text-lg font-extrabold">Trạng thái đơn hàng</h2>
        <p class="mb-5 text-sm text-on-surface-variant">Toàn bộ vòng đời đơn trên hệ thống.</p>
        <div class="space-y-4">
          <div v-for="item in orderDistribution" :key="item.label">
            <div class="mb-1 flex justify-between gap-3 text-sm"><span>{{ item.label }}</span><strong>{{ formatValue(item.value) }}</strong></div>
            <div class="h-2.5 overflow-hidden rounded-full bg-surface-container"><span class="block h-full rounded-full bg-commerce" :style="{ width: barPercent(item.value, maxDistribution(orderDistribution)) }"></span></div>
          </div>
          <p v-if="!loading && orderDistribution.length === 0" class="text-sm text-on-surface-variant">Chưa có đơn hàng.</p>
        </div>
      </article>

      <article class="ui-panel">
        <h2 class="text-lg font-extrabold">Hàng đợi vận hành</h2>
        <p class="mb-5 text-sm text-on-surface-variant">Các mục cần quản trị viên xem xét.</p>
        <dl class="divide-y divide-outline-variant/40">
          <div v-for="(label, key) in queueLabels" :key="key" class="flex min-h-14 items-center justify-between gap-4 py-2">
            <dt class="text-sm text-on-surface-variant">{{ label }}</dt>
            <dd class="rounded-full bg-secondary-fixed px-3 py-1 text-sm font-extrabold text-on-secondary-fixed">{{ formatValue(stats.operational_queues[key]) }}</dd>
          </div>
        </dl>
      </article>
    </section>
  </main>
</template>

<style scoped>
.chart-columns { display: grid; min-height: 16rem; grid-template-columns: repeat(6, minmax(3.25rem, 1fr)); align-items: end; gap: 0.5rem; overflow-x: auto; padding-top: 2rem; }
.chart-column { display: flex; min-width: 3.25rem; height: 14rem; flex-direction: column; align-items: center; justify-content: flex-end; gap: 0.25rem; text-align: center; }
.chart-column strong { font-size: 0.75rem; color: var(--color-on-surface); }
.chart-column small { font-size: 0.6875rem; color: var(--color-on-surface-variant); }
.chart-value { max-width: 6rem; overflow: hidden; text-overflow: ellipsis; font-size: 0.625rem; font-weight: 700; color: var(--color-on-surface-variant); }
.chart-bar { display: block; width: min(2.5rem, 70%); min-height: 2px; border-radius: 0.375rem 0.375rem 0 0; }
.legend-dot { display: inline-flex; align-items: center; gap: 0.4rem; }
.legend-dot::before { width: 0.75rem; height: 0.75rem; border-radius: 0.2rem; content: ''; }
.data-table-details { margin-top: 1rem; font-size: 0.875rem; }
.data-table-details summary { min-height: 44px; cursor: pointer; color: var(--color-primary); font-weight: 700; }
table { width: 100%; border-collapse: collapse; background: var(--color-surface-container-lowest); }
th, td { padding: 0.65rem 0.75rem; border-bottom: 1px solid var(--color-outline-variant); text-align: left; white-space: nowrap; }
th { background: var(--color-surface-container-low); font-size: 0.75rem; }
@media (max-width: 480px) { .chart-columns { gap: 0.25rem; } .chart-column { min-width: 3rem; } }
</style>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const router = useRouter()
const toast = useToast()

// ─── State ───
const orders = ref([])
const allOrdersForCounts = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyParams = ref({ first: 0, rows: 15, page: 1 })

// Filters & Search
const filterStatus = ref('all')
const searchQuery = ref('')
const selectedCheckboxes = ref([])
const bulkShippingLoading = ref(false)

// ─── Status Config ───
const statusMap = {
  pending:    { label: 'Chờ xử lý',    bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary' },
  confirmed:  { label: 'Đã xác nhận',  bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary' },
  processing: { label: 'Đang xử lý',   bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary' },
  shipped:    { label: 'Đang giao',    bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary' },
  completed:  { label: 'Hoàn thành',   bg: 'bg-[#E6F4EA]', text: 'text-[#137333]', dot: 'bg-[#137333]' },
  cancelled:  { label: 'Đã hủy',       bg: 'bg-error-container', text: 'text-on-error-container', dot: 'bg-error' },
}

const statusTabs = [
  { label: 'Tất cả', value: 'all', key: 'all' },
  { label: 'Chờ xử lý', value: 'pending', key: 'pending', hasPulse: true },
  { label: 'Đang xử lý', value: 'processing', key: 'processing' },
  { label: 'Đang giao', value: 'shipped', key: 'shipped' },
  { label: 'Hoàn thành', value: 'completed', key: 'completed' },
  { label: 'Đã hủy', value: 'cancelled', key: 'cancelled' },
]

const getStatus = (status) => statusMap[status] || statusMap.pending

// Dynamic Status Counts Computation
const statusCounts = computed(() => {
  const counts = { all: totalRecords.value, pending: 0, processing: 0, shipped: 0, completed: 0, cancelled: 0 }
  orders.value.forEach(o => {
    if (counts[o.status] !== undefined) counts[o.status]++
  })
  return counts
})

// ─── Formatters ───
const formatPrice = (val) => {
  if (!val && val !== 0) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric'
  })
}

// ─── API Calls ───
const fetchOrders = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/vendor/orders', {
      params: { 
        page: lazyParams.value.page, 
        per_page: lazyParams.value.rows,
        status: filterStatus.value === 'all' ? null : filterStatus.value,
        search: searchQuery.value.trim() || null,
      },
    })
    orders.value = res.data.data || []
    totalRecords.value = res.data.meta?.total || res.data.data?.length || 0
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách đơn hàng.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const changeTab = (tabValue) => {
  filterStatus.value = tabValue
  lazyParams.value.page = 1
  selectedCheckboxes.value = []
  fetchOrders()
}

let searchTimeout = null
const onSearchInput = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    lazyParams.value.page = 1
    fetchOrders()
  }, 350)
}

const toggleSelectAll = (e) => {
  if (e.target.checked) {
    selectedCheckboxes.value = orders.value.map(o => o.id)
  } else {
    selectedCheckboxes.value = []
  }
}

const bulkShipping = async () => {
  if (!selectedCheckboxes.value.length) {
    toast.add({ severity: 'warn', summary: 'Chưa chọn đơn hàng', detail: 'Vui lòng chọn ít nhất 1 đơn hàng.', life: 3000 })
    return
  }
  bulkShippingLoading.value = true
  try {
    await apiClient.patch('/api/vendor/orders/bulk-status', {
      order_ids: selectedCheckboxes.value,
      status: 'shipped',
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã bàn giao vận chuyển ${selectedCheckboxes.value.length} đơn hàng!`, life: 3500 })
    selectedCheckboxes.value = []
    fetchOrders()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.response?.data?.message || 'Không thể bàn giao hàng loạt.', life: 4000 })
  } finally {
    bulkShippingLoading.value = false
  }
}

const openDetail = (order) => {
  router.push({ name: 'vendor-order-detail', params: { id: order.id } })
}

const onPage = (pageNumber) => {
  lazyParams.value.page = pageNumber
  fetchOrders()
}

const totalPages = computed(() => Math.ceil(totalRecords.value / lazyParams.value.rows) || 1)
const pagesArray = computed(() => {
  let pages = []
  for (let i = 1; i <= totalPages.value; i++) {
    pages.push(i)
  }
  return pages
})

onMounted(fetchOrders)
</script>

<template>
  <div class="pb-xxl w-full pt-6 space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
      <div>
        <h2 class="font-headline-lg text-headline-lg font-bold text-on-background flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-3xl">shopping_cart</span>
          Quản lý Đơn hàng
        </h2>
        <p class="font-body-md text-body-md text-on-surface-variant mt-sm">Xem, xử lý bàn giao vận chuyển và theo dõi đơn hàng của khách hàng.</p>
      </div>
    </div>

    <!-- Quick Status Tabs Bar -->
    <section class="rounded-2xl border border-rose-200 bg-rose-50/30 p-2 shadow-xs">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-1">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            type="button"
            class="rounded-xl px-4 py-2 text-sm font-bold transition-all cursor-pointer flex items-center gap-1.5 border-none"
            :class="filterStatus === tab.value ? 'bg-white text-rose-700 shadow-sm border border-rose-200' : 'text-on-surface-variant hover:bg-white/60 bg-transparent'"
            @click="changeTab(tab.value)"
          >
            <span v-if="tab.hasPulse && statusCounts.pending > 0" class="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
            <span>{{ tab.label }}</span>
            <span v-if="tab.value === 'all'" class="text-xs text-slate-500 font-normal">({{ totalRecords }})</span>
          </button>
        </div>
      </div>
    </section>

    <!-- Search & Bulk Action Bar -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-surface-container-lowest p-3 rounded-2xl border border-outline-variant/30 shadow-xs">
      <div class="relative flex-1 min-w-[240px]">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">search</span>
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Tìm đơn hàng theo mã ORD-..., tên khách hàng..."
          class="w-full rounded-xl border border-outline-variant bg-surface px-10 py-2 text-sm text-on-surface placeholder:text-outline focus:border-primary focus:outline-none"
          @input="onSearchInput"
        />
      </div>

      <div class="flex items-center gap-2">
        <button
          v-if="selectedCheckboxes.length > 0"
          type="button"
          class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-on-primary shadow-sm hover:opacity-90 cursor-pointer border-none"
          :disabled="bulkShippingLoading"
          @click="bulkShipping"
        >
          <span class="material-symbols-outlined text-base" :class="{ 'animate-spin': bulkShippingLoading }">
            {{ bulkShippingLoading ? 'progress_activity' : 'local_shipping' }}
          </span>
          Bàn giao {{ selectedCheckboxes.length }} đơn hàng
        </button>
      </div>
    </div>
    
    <!-- Orders Table / Bento Grid Approach -->
    <div class="bg-surface-container-lowest rounded-xl shadow-[0px_12px_24px_rgba(26,58,90,0.04)] border border-surface-container-highest overflow-hidden">
      <!-- Table Header (Desktop) -->
      <div class="hidden md:grid grid-cols-12 gap-md p-md bg-surface-container-low border-b border-surface-container-highest font-label-md text-label-md text-on-surface-variant items-center">
        <div class="col-span-2 flex items-center gap-2">
          <input type="checkbox" class="rounded cursor-pointer" @change="toggleSelectAll" />
          <span>Mã Đơn</span>
        </div>
        <div class="col-span-3">Sản phẩm</div>
        <div class="col-span-2">Khách hàng</div>
        <div class="col-span-2">Ngày đặt</div>
        <div class="col-span-2 text-right">Tổng tiền</div>
        <div class="col-span-1 text-center">Trạng thái</div>
      </div>
      
      <!-- Loading State -->
      <div v-if="loading" class="p-xl text-center flex flex-col justify-center items-center">
        <span class="material-symbols-outlined animate-spin text-primary text-4xl mb-sm">progress_activity</span>
        <span class="font-body-md text-on-surface-variant">Đang tải danh sách đơn hàng...</span>
      </div>

      <!-- Empty State -->
      <div v-else-if="orders.length === 0" class="p-xl text-center flex flex-col justify-center items-center">
        <span class="material-symbols-outlined text-outline text-5xl mb-sm">inbox</span>
        <span class="font-body-md text-on-surface-variant">Không tìm thấy đơn hàng nào.</span>
      </div>

      <!-- Order Items -->
      <div v-else class="divide-y divide-surface-container-highest">
        <div v-for="order in orders" :key="order.id" class="p-md hover:bg-surface-bright transition-colors group cursor-pointer" :class="{ 'opacity-75': order.status === 'cancelled' }" @click="openDetail(order)">
          <div class="grid grid-cols-1 md:grid-cols-12 gap-md items-center">
            <div class="col-span-1 md:col-span-2 flex items-center gap-2 font-label-md text-label-md text-on-surface whitespace-nowrap" :class="{ 'text-on-surface-variant line-through': order.status === 'cancelled' }" @click.stop>
              <input v-model="selectedCheckboxes" :value="order.id" type="checkbox" class="rounded cursor-pointer" />
              <span class="font-bold text-primary">{{ order.order_code }}</span>
            </div>
            
            <div class="col-span-1 md:col-span-3 flex items-center gap-md min-w-0">
              <div 
                v-if="order.items && order.items.length > 0 && order.items[0].book?.cover_image"
                class="w-10 h-14 bg-surface-variant rounded border border-outline-variant flex-shrink-0 bg-cover bg-center" 
                :class="{ 'grayscale': order.status === 'cancelled' }"
                :style="{ backgroundImage: `url(${order.items[0].book.cover_image})` }"
              ></div>
              <div v-else class="w-10 h-14 bg-surface-container-high rounded border border-outline-variant flex-shrink-0 flex items-center justify-center text-outline">
                <span class="material-symbols-outlined text-sm">menu_book</span>
              </div>
              <div class="min-w-0 flex-1">
                <div class="font-body-md text-body-md font-bold text-on-surface leading-snug break-words" :class="{ 'text-on-surface-variant': order.status === 'cancelled' }">
                  {{ order.items && order.items.length > 0 ? (order.items[0].book?.title || order.items[0].title) : 'Sản phẩm' }}
                </div>
                <div v-if="order.items && order.items.length > 1" class="text-xs text-on-surface-variant mt-0.5 font-semibold">
                  +{{ order.items.length - 1 }} sản phẩm khác trong đơn
                </div>
              </div>
            </div>
            
            <div class="col-span-1 md:col-span-2 font-body-md text-body-md text-on-surface-variant truncate whitespace-nowrap">
              <span class="md:hidden text-sm">Khách hàng: </span>{{ order.user?.name || 'Khách vãng lai' }}
            </div>
            
            <div class="col-span-1 md:col-span-2 font-body-md text-body-md text-on-surface-variant whitespace-nowrap">
              <span class="md:hidden text-sm">Ngày đặt: </span>{{ formatDate(order.created_at) }}
            </div>
            
            <div class="col-span-1 md:col-span-2 font-body-md text-body-md font-bold text-on-surface md:text-right whitespace-nowrap" :class="{ 'text-on-surface-variant': order.status === 'cancelled' }">
              <span class="md:hidden text-sm">Giá trị: </span>{{ formatPrice(order.total_amount) }}
            </div>
            
            <div class="col-span-1 md:col-span-1 flex justify-between md:justify-center items-center">
              <span class="md:hidden text-on-surface-variant text-sm">Trạng thái: </span>
              <span :class="['inline-flex items-center px-2 py-1 rounded-full font-label-md text-[12px] whitespace-nowrap', getStatus(order.status).bg, getStatus(order.status).text]">
                <span :class="['w-2 h-2 rounded-full mr-1', getStatus(order.status).dot]"></span>
                {{ getStatus(order.status).label }}
              </span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Pagination Footer -->
      <div v-if="totalPages > 1" class="p-md bg-surface border-t border-surface-container-highest flex justify-between items-center">
        <span class="font-body-md text-sm text-on-surface-variant">Hiển thị {{ (lazyParams.page - 1) * lazyParams.rows + 1 }} đến {{ Math.min(lazyParams.page * lazyParams.rows, totalRecords) }} trong {{ totalRecords }} mục</span>
        <div class="flex gap-xs">
          <button 
            @click="onPage(lazyParams.page - 1)" 
            :disabled="lazyParams.page === 1"
            class="w-8 h-8 flex items-center justify-center rounded border border-outline-variant text-on-surface-variant hover:bg-surface-container-high disabled:opacity-50 border-none bg-transparent cursor-pointer"
          >
            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
          </button>
          
          <button 
            v-for="p in pagesArray" :key="p"
            @click="onPage(p)"
            :class="p === lazyParams.page ? 'bg-primary text-on-primary' : 'border border-outline-variant text-on-surface hover:bg-surface-container-high'"
            class="w-8 h-8 flex items-center justify-center rounded font-label-md text-sm border-none cursor-pointer"
          >
            {{ p }}
          </button>
          
          <button 
            @click="onPage(lazyParams.page + 1)" 
            :disabled="lazyParams.page === totalPages"
            class="w-8 h-8 flex items-center justify-center rounded border border-outline-variant text-on-surface hover:bg-surface-container-high disabled:opacity-50 border-none bg-transparent cursor-pointer"
          >
            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles */
</style>

<style scoped>
/* No specific scoped styles needed as we use Tailwind */
</style>

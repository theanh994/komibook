<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const router = useRouter()
const toast = useToast()

// ─── State ───
const orders = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyParams = ref({ first: 0, rows: 15, page: 1 })

// Filter Status
const filterStatus = ref('all')

// ─── Status Config ───
const statusMap = {
  pending:    { label: 'Chờ xử lý',    bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary' },
  processing: { label: 'Đang xử lý',   bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary' },
  shipped:    { label: 'Đang giao',    bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary' },
  completed:  { label: 'Hoàn thành',   bg: 'bg-[#E6F4EA]', text: 'text-[#137333]', dot: 'bg-[#137333]' },
  cancelled:  { label: 'Đã hủy',       bg: 'bg-error-container', text: 'text-on-error-container', dot: 'bg-error' },
}

const getStatus = (status) => statusMap[status] || statusMap.pending

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
        status: filterStatus.value === 'all' ? null : filterStatus.value
      },
    })
    orders.value = res.data.data
    totalRecords.value = res.data.meta?.total || res.data.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách đơn hàng.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openDetail = (order) => {
  router.push({ name: 'vendor-order-detail', params: { id: order.id } })
}

const onPage = (pageNumber) => {
  lazyParams.value.page = pageNumber
  fetchOrders()
}

const totalPages = computed(() => Math.ceil(totalRecords.value / lazyParams.value.rows))
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
  <div class="px-md md:px-xl pb-xxl max-w-container-max mx-auto w-full pt-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-xl gap-md">
      <div>
        <h2 class="font-headline-lg text-headline-lg font-bold text-on-background">Quản lý Đơn hàng</h2>
        <p class="font-body-md text-body-md text-on-surface-variant mt-sm">Xem, xử lý và theo dõi đơn hàng của khách hàng.</p>
      </div>
      <div class="flex flex-wrap gap-sm">
        <!-- Status Filter -->
        <div class="relative">
          <select 
            v-model="filterStatus"
            @change="fetchOrders"
            class="appearance-none bg-surface-container-lowest border border-outline text-on-background font-body-md text-body-md rounded-lg pl-md pr-xl py-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
          >
            <option value="all">Tất cả trạng thái</option>
            <option value="pending">Chờ xử lý</option>
            <option value="processing">Đang xử lý</option>
            <option value="shipped">Đang giao hàng</option>
            <option value="completed">Hoàn thành</option>
            <option value="cancelled">Đã hủy</option>
          </select>
          <span class="material-symbols-outlined absolute right-md top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">arrow_drop_down</span>
        </div>
        <!-- Quick Actions -->
        <button class="bg-surface-container-lowest border border-primary text-primary font-label-md text-label-md px-md py-sm rounded-lg hover:bg-surface-container-high transition-colors flex items-center gap-xs">
          <span class="material-symbols-outlined text-[18px]">print</span>
          In hóa đơn
        </button>
        <button class="bg-primary text-on-primary font-label-md text-label-md px-md py-sm rounded-lg hover:bg-primary-container hover:text-on-primary-container transition-colors shadow-sm flex items-center gap-xs">
          <span class="material-symbols-outlined text-[18px]">bolt</span>
          Xử lý nhanh
        </button>
      </div>
    </div>
    
    <!-- Orders Table / Bento Grid Approach -->
    <div class="bg-surface-container-lowest rounded-xl shadow-[0px_12px_24px_rgba(26,58,90,0.04)] border border-surface-container-highest overflow-hidden">
      <!-- Table Header (Desktop) -->
      <div class="hidden md:grid grid-cols-12 gap-md p-md bg-surface-container-low border-b border-surface-container-highest font-label-md text-label-md text-on-surface-variant">
        <div class="col-span-2">Mã Đơn</div>
        <div class="col-span-3">Sản phẩm</div>
        <div class="col-span-2">Khách hàng</div>
        <div class="col-span-2">Ngày đặt</div>
        <div class="col-span-1 text-right">Tổng tiền</div>
        <div class="col-span-2 text-center">Trạng thái</div>
      </div>
      
      <!-- Loading State -->
      <div v-if="loading" class="p-xl text-center flex flex-col justify-center items-center">
        <span class="material-symbols-outlined animate-spin text-primary text-4xl mb-sm">progress_activity</span>
        <span class="font-body-md text-on-surface-variant">Đang tải đơn hàng...</span>
      </div>

      <!-- Empty State -->
      <div v-else-if="orders.length === 0" class="p-xl text-center flex flex-col justify-center items-center">
        <span class="material-symbols-outlined text-outline text-5xl mb-sm">inbox</span>
        <span class="font-body-md text-on-surface-variant">Chưa có đơn hàng nào.</span>
      </div>

      <!-- Order Items -->
      <div v-else class="divide-y divide-surface-container-highest">
        <div v-for="order in orders" :key="order.id" @click="openDetail(order)" class="p-md hover:bg-surface-bright transition-colors group cursor-pointer" :class="{ 'opacity-75': order.status === 'cancelled' }">
          <div class="grid grid-cols-1 md:grid-cols-12 gap-md items-center">
            <div class="col-span-1 md:col-span-2 font-label-md text-label-md text-on-surface" :class="{ 'text-on-surface-variant line-through': order.status === 'cancelled' }">
              <span class="md:hidden text-on-surface-variant">Đơn hàng: </span>{{ order.order_code }}
            </div>
            
            <div class="col-span-1 md:col-span-3 flex items-center gap-md">
              <div 
                v-if="order.items && order.items.length > 0 && order.items[0].book?.cover_image"
                class="w-10 h-14 bg-surface-variant rounded border border-outline-variant flex-shrink-0 bg-cover bg-center" 
                :class="{ 'grayscale': order.status === 'cancelled' }"
                :style="{ backgroundImage: `url(${order.items[0].book.cover_image})` }"
              ></div>
              <div v-else class="w-10 h-14 bg-surface-container-high rounded border border-outline-variant flex-shrink-0 flex items-center justify-center text-outline">
                <span class="material-symbols-outlined text-sm">menu_book</span>
              </div>
              <span class="font-body-md text-body-md font-medium text-on-surface truncate" :class="{ 'text-on-surface-variant': order.status === 'cancelled' }">
                {{ order.items && order.items.length > 0 ? order.items[0].book?.title : 'Sản phẩm' }}
                <span v-if="order.items && order.items.length > 1" class="text-on-surface-variant text-sm font-normal"> (+{{ order.items.length - 1 }})</span>
              </span>
            </div>
            
            <div class="col-span-1 md:col-span-2 font-body-md text-body-md text-on-surface-variant">
              <span class="md:hidden text-sm">Khách hàng: </span>{{ order.user?.name || '—' }}
            </div>
            
            <div class="col-span-1 md:col-span-2 font-body-md text-body-md text-on-surface-variant">
              <span class="md:hidden text-sm">Ngày đặt: </span>{{ formatDate(order.created_at) }}
            </div>
            
            <div class="col-span-1 md:col-span-1 font-body-md text-body-md font-medium text-on-surface md:text-right" :class="{ 'text-on-surface-variant': order.status === 'cancelled' }">
              <span class="md:hidden text-sm">Giá trị: </span>{{ formatPrice(order.total_amount) }}
            </div>
            
            <div class="col-span-1 md:col-span-2 flex justify-between md:justify-center items-center">
              <span class="md:hidden text-on-surface-variant text-sm">Trạng thái: </span>
              <span :class="['inline-flex items-center px-2 py-1 rounded-full font-label-md text-[12px]', getStatus(order.status).bg, getStatus(order.status).text]">
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
            class="w-8 h-8 flex items-center justify-center rounded border border-outline-variant text-on-surface-variant hover:bg-surface-container-high disabled:opacity-50"
          >
            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
          </button>
          
          <button 
            v-for="p in pagesArray" :key="p"
            @click="onPage(p)"
            :class="p === lazyParams.page ? 'bg-primary text-on-primary' : 'border border-outline-variant text-on-surface hover:bg-surface-container-high'"
            class="w-8 h-8 flex items-center justify-center rounded font-label-md text-sm"
          >
            {{ p }}
          </button>
          
          <button 
            @click="onPage(lazyParams.page + 1)" 
            :disabled="lazyParams.page === totalPages"
            class="w-8 h-8 flex items-center justify-center rounded border border-outline-variant text-on-surface hover:bg-surface-container-high disabled:opacity-50"
          >
            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* No specific scoped styles needed as we use Tailwind */
</style>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import InfoTip from '@/components/InfoTip.vue'

const toast = useToast()
const router = useRouter()

// --- State ---
const warehouses = ref([])
const primaryWarehouseId = ref(null)
const primaryWarehouseSaving = ref(false)
const stocks = ref([])
const stats = ref({
  total_items: 0,
  low_stock_items: 0,
  out_of_stock_items: 0,
  low_stock_books: [],
  out_of_stock_books: [],
})

const loading = ref(false)
const pagination = ref({
  total: 0,
  current_page: 1,
  last_page: 1
})

// Filters
const selectedWarehouse = ref(null)
const selectedType = ref('Tất cả loại sách')
const selectedStatus = ref('Tất cả trạng thái')

// Expanded rows
const expandedBookIds = ref([])

// Modals
const isAddModalOpen = ref(false)
const isAdjustModalOpen = ref(false)
const isTransferModalOpen = ref(false)

// Form states
const newWarehouse = ref({ name: '', address: '', capacity: '0%', status: 'Hoạt động' })
const adjustForm = ref({ book_id: '', source_warehouse_id: '', quantity: 1 })
const transferForm = ref({ book_id: '', source_warehouse_id: '', target_warehouse_id: '', quantity: 1 })

// List of books for stock adjustments
const allBooksList = ref([])

// --- API Calls ---
const fetchStats = async () => {
  try {
    const res = await apiClient.get('/api/vendor/warehouses/stats')
    stats.value = res.data
  } catch (err) {
    console.error(err)
  }
}

const fetchWarehousesAndStocks = async (page = 1) => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/vendor/warehouses', {
      params: {
        page,
        warehouse_id: selectedWarehouse.value,
        type: selectedType.value,
        status: selectedStatus.value
      }
    })
    warehouses.value = res.data.warehouses
    primaryWarehouseId.value = res.data.primary_warehouse_id
    stocks.value = res.data.stocks
    pagination.value = res.data.pagination
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Không thể lọc kho', detail: err.response?.data?.message || 'Không thể tải dữ liệu kho hàng.', life: 4000 })
  } finally {
    loading.value = false
  }
}

const setPrimaryWarehouse = async () => {
  if (!primaryWarehouseId.value) return
  primaryWarehouseSaving.value = true
  try {
    await apiClient.patch(`/api/vendor/warehouses/${primaryWarehouseId.value}/primary`)
    toast.add({ severity: 'success', summary: 'Đã chọn kho tổng', detail: 'Sách mới sẽ tạo phiếu nhập tại kho này.', life: 3500 })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Không thể chọn kho tổng', detail: err.response?.data?.message || 'Vui lòng thử lại.', life: 4000 })
    await fetchWarehousesAndStocks()
  } finally {
    primaryWarehouseSaving.value = false
  }
}

const fetchBooksForSelect = async () => {
  try {
    const res = await apiClient.get('/api/vendor/books')
    allBooksList.value = res.data.data || res.data
  } catch (err) {
    console.error(err)
  }
}

const handleAddWarehouse = async () => {
  if (!newWarehouse.value.name || !newWarehouse.value.address) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng điền đầy đủ thông tin.', life: 3000 })
    return
  }
  try {
    await apiClient.post('/api/vendor/warehouses', newWarehouse.value)
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã tạo kho hàng mới.', life: 3000 })
    isAddModalOpen.value = false
    newWarehouse.value = { name: '', address: '', capacity: '0%', status: 'Hoạt động' }
    fetchWarehousesAndStocks()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tạo kho hàng.', life: 3000 })
  }
}

const handleAdjustStock = async () => {
  if (!adjustForm.value.book_id || !adjustForm.value.source_warehouse_id || adjustForm.value.quantity < 0) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng điền đầy đủ thông tin hợp lệ.', life: 3000 })
    return
  }
  try {
    await apiClient.post('/api/vendor/warehouses/adjust', {
      type: 'adjust',
      ...adjustForm.value
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã điều chỉnh tồn kho.', life: 3000 })
    isAdjustModalOpen.value = false
    fetchWarehousesAndStocks()
    fetchStats()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Điều chỉnh tồn kho thất bại.', life: 3000 })
  }
}

const handleTransferStock = async () => {
  if (!transferForm.value.book_id || !transferForm.value.source_warehouse_id || !transferForm.value.target_warehouse_id) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng điền đầy đủ thông tin.', life: 3000 })
    return
  }
  if (transferForm.value.source_warehouse_id === transferForm.value.target_warehouse_id) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Kho nguồn và kho đích không được giống nhau.', life: 3000 })
    return
  }
  try {
    await apiClient.post('/api/vendor/warehouses/adjust', {
      type: 'transfer',
      ...transferForm.value
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã chuyển kho thành công.', life: 3000 })
    isTransferModalOpen.value = false
    fetchWarehousesAndStocks()
    fetchStats()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Điều chuyển kho thất bại.', life: 3000 })
  }
}

const toggleExpand = (bookId) => {
  const index = expandedBookIds.value.indexOf(bookId)
  if (index > -1) {
    expandedBookIds.value.splice(index, 1)
  } else {
    expandedBookIds.value.push(bookId)
  }
}

const openWarehouseDocument = type => {
  router.push({
    name: 'vendor-warehouse-documents',
    query: {
      type,
      ...(selectedWarehouse.value ? { warehouse_id: selectedWarehouse.value } : {}),
    },
  })
}

const applyStockFilter = status => {
  selectedStatus.value = status
  fetchWarehousesAndStocks(1)
}

// Image fallback helper
const getBookCover = (url) => {
  if (!url) return '/images/book-placeholder.svg'
  if (url.startsWith('http') || url.startsWith('/storage/') || url.startsWith('/images/')) return url
  if (url.includes('/storage/')) return url.substring(url.indexOf('/storage/'))
  if (url.startsWith('/')) return url
  return `/storage/${url}`
}

const handleCoverError = (event) => {
  if (event.currentTarget.dataset.fallbackApplied) return
  event.currentTarget.dataset.fallbackApplied = 'true'
  event.currentTarget.src = '/images/book-placeholder.svg'
}

onMounted(() => {
  fetchStats()
  fetchWarehousesAndStocks()
  fetchBooksForSelect()
})
</script>

<template>
  <div class="pb-xl w-full pt-6">
    <div>
      <!-- Page Header -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-lg gap-md">
        <div>
          <h1 class="text-headline-lg font-headline-lg font-bold text-on-background">Quản lý kho hàng</h1>
          <p class="font-body-md text-body-md text-on-surface-variant mt-sm">Quản lý tồn kho sách vật lý tại các địa điểm lưu trữ.</p>
        </div>
        <div class="flex flex-wrap gap-md">
          <button
            v-if="warehouses.length >= 2"
            @click="openWarehouseDocument('transfer')"
            class="px-4 py-2 border-[1.5px] border-primary text-primary rounded-lg font-label-md text-label-md hover:bg-primary-fixed-dim transition-colors flex items-center gap-2"
          >
            <span class="material-symbols-outlined">sync_alt</span> Tạo phiếu điều chuyển
          </button>
          <button 
            @click="openWarehouseDocument('count')"
            class="px-4 py-2 border-[1.5px] border-primary text-primary rounded-lg font-label-md text-label-md hover:bg-primary-fixed-dim transition-colors flex items-center gap-2"
          >
            <span class="material-symbols-outlined">fact_check</span> Kiểm kê / điều chỉnh
          </button>
          <button 
            @click="isAddModalOpen = true"
            class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-opacity-90 transition-colors flex items-center gap-2"
          >
            <span class="material-symbols-outlined">add_home</span> Thêm kho mới
          </button>
        </div>
      </div>

      <section class="mb-lg flex flex-col gap-4 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-5 md:flex-row md:items-end md:justify-between" aria-labelledby="primary-warehouse-title">
        <div class="flex items-center gap-2">
          <h2 id="primary-warehouse-title" class="text-lg font-bold text-on-surface">Kho tổng của gian hàng</h2>
          <InfoTip text="Mọi sách vật lý mới sẽ tạo phiếu nhập nháp tại kho này. Có thể đổi kho tổng trước khi thêm sách tiếp theo." label="Cách sử dụng kho tổng" />
        </div>
        <div class="flex w-full flex-col gap-2 sm:flex-row md:w-auto">
          <label for="primary-warehouse" class="sr-only">Chọn kho tổng</label>
          <select id="primary-warehouse" v-model="primaryWarehouseId" class="min-h-11 min-w-64 rounded-lg border border-outline bg-surface-container-lowest px-3 text-on-surface">
            <option :value="null" disabled>Chọn kho tổng</option>
            <option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :disabled="!['active', 'Hoạt động'].includes(wh.status)">{{ wh.name }}</option>
          </select>
          <button type="button" class="min-h-11 rounded-lg bg-primary px-4 font-bold text-on-primary disabled:opacity-50" :disabled="!primaryWarehouseId || primaryWarehouseSaving" @click="setPrimaryWarehouse">{{ primaryWarehouseSaving ? 'Đang lưu...' : 'Lưu kho tổng' }}</button>
        </div>
      </section>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
        <div class="bg-surface-container-lowest rounded-xl p-lg soft-shadow border border-surface-container-high">
          <div class="flex justify-between items-start gap-2 mb-sm">
            <h3 class="font-body-md text-body-md text-on-surface-variant">Tổng mặt hàng (Tất cả kho)</h3>
            <InfoTip text="Tổng số đầu sách đang được quản lý trong toàn bộ kho của gian hàng." label="Giải thích tổng mặt hàng" />
          </div>
          <p class="text-display-lg font-display-lg text-on-surface">{{ stats.total_items }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg soft-shadow border border-surface-container-high">
          <div class="flex justify-between items-start gap-2 mb-sm">
            <h3 class="font-body-md text-body-md text-on-surface-variant">Sách sắp hết (Dưới 10)</h3>
            <InfoTip text="Danh sách sách còn từ 1 đến 9 cuốn trên toàn gian hàng." label="Tiêu chí sách sắp hết" />
          </div>
          <p class="text-display-lg font-display-lg text-on-surface">{{ stats.low_stock_items }}</p>
          <ul v-if="stats.low_stock_books?.length" class="mt-3 space-y-2 border-t border-outline-variant/40 pt-3">
            <li v-for="book in stats.low_stock_books.slice(0, 3)" :key="book.id" class="flex items-start justify-between gap-3 text-sm"><span class="min-w-0"><span class="block truncate font-medium">{{ book.title }}</span><span class="block truncate text-xs text-on-surface-variant">{{ book.warehouse_names?.length ? book.warehouse_names.join(', ') : 'Chưa phân bổ vào kho' }}</span></span><strong class="shrink-0 text-amber-800">{{ book.stock }} cuốn</strong></li>
          </ul>
          <button type="button" class="mt-3 min-h-11 text-sm font-bold text-primary hover:underline" @click="applyStockFilter('Sắp hết')">Xem toàn bộ sách sắp hết</button>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg soft-shadow border border-surface-container-high">
          <div class="flex justify-between items-start gap-2 mb-sm">
            <h3 class="font-body-md text-body-md text-on-surface-variant">Đã hết hàng</h3>
            <InfoTip text="Các sách không còn số lượng khả dụng để bán trong toàn bộ kho." label="Tiêu chí sách hết hàng" />
          </div>
          <p class="text-display-lg font-display-lg text-on-surface">{{ stats.out_of_stock_items }}</p>
          <ul v-if="stats.out_of_stock_books?.length" class="mt-3 space-y-2 border-t border-outline-variant/40 pt-3">
            <li v-for="book in stats.out_of_stock_books.slice(0, 3)" :key="book.id" class="flex items-center justify-between gap-3 text-sm"><span class="truncate font-medium">{{ book.title }}</span><span class="shrink-0 text-xs text-on-surface-variant">{{ book.is_unallocated ? 'Chưa phân bổ kho' : 'Đã về 0' }}</span></li>
          </ul>
          <button type="button" class="mt-3 min-h-11 text-sm font-bold text-primary hover:underline" @click="applyStockFilter('Hết hàng')">Xem toàn bộ sách hết hàng</button>
        </div>
      </div>

      <!-- Main Content Area -->
      <div class="bg-surface-container-lowest rounded-xl soft-shadow border border-surface-container-high overflow-hidden">
        <!-- Filters & Search -->
        <div class="p-lg flex flex-wrap gap-md justify-between items-center border-b border-surface-container-high bg-surface-container-lowest">
          <div class="flex flex-wrap gap-md">
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">warehouse</span>
              <select 
                v-model="selectedWarehouse"
                @change="fetchWarehousesAndStocks(1)"
                class="appearance-none bg-surface-container-lowest border border-outline rounded-lg pl-10 pr-8 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              >
                <option :value="null">Tất cả kho</option>
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
              </select>
            </div>
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">filter_list</span>
              <select 
                v-model="selectedType"
                @change="fetchWarehousesAndStocks(1)"
                class="appearance-none bg-surface-container-lowest border border-outline rounded-lg pl-10 pr-8 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              >
                <option value="Tất cả loại sách">Tất cả loại sách</option>
                <option value="Sách vật lý">Sách vật lý</option>
                <option value="Ebook">Ebook</option>
              </select>
            </div>
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">inventory</span>
              <select 
                v-model="selectedStatus"
                @change="fetchWarehousesAndStocks(1)"
                class="appearance-none bg-surface-container-lowest border border-outline rounded-lg pl-10 pr-8 py-2 font-body-md text-body-md text-on-surface focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
              >
                <option value="Tất cả trạng thái">Tất cả trạng thái</option>
                <option value="Còn hàng">Còn hàng</option>
                <option value="Sắp hết">Sắp hết</option>
                <option value="Hết hàng">Hết hàng</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-surface-container-low text-on-surface-variant font-label-md text-label-md border-b border-surface-container-high">
                <th class="p-md font-medium w-8"></th>
                <th class="p-md font-medium">Mã SKU/ISBN</th>
                <th class="p-md font-medium">Tên sách</th>
                <th class="p-md font-medium">Loại</th>
                <th class="p-md font-medium">{{ selectedWarehouse ? 'Tồn tại kho đã chọn' : 'Tổng tồn kho' }}</th>
                <th class="p-md font-medium">Vị trí kho chính</th>
                <th class="p-md font-medium">Trạng thái</th>
              </tr>
            </thead>
            <tbody class="font-body-md text-body-md divide-y divide-surface-container-high">
              <template v-for="item in stocks" :key="item.id">
                <tr class="hover:bg-surface-container-lowest/50 transition-colors group">
                  <td class="p-md text-center">
                    <button 
                      v-if="item.type === 'Sách vật lý'"
                      @click="toggleExpand(item.id)" 
                      class="text-outline hover:text-primary transition-colors p-1"
                    >
                      <span class="material-symbols-outlined">{{ expandedBookIds.includes(item.id) ? 'expand_less' : 'expand_more' }}</span>
                    </button>
                  </td>
                  <td class="p-md text-outline">{{ item.sku }}</td>
                  <td class="p-md font-medium text-on-surface flex items-center gap-3">
                    <div class="w-10 h-14 bg-surface-container rounded overflow-hidden flex-shrink-0">
                      <img :alt="`Bìa ${item.title}`" class="h-full w-full object-contain" loading="lazy" :src="getBookCover(item.cover_image)" @error="handleCoverError"/>
                    </div>
                    <span class="truncate max-w-[250px]">{{ item.title }}</span>
                  </td>
                  <td class="p-md">
                    <span 
                      :class="item.type === 'Ebook' ? 'bg-primary-container text-primary' : 'bg-[#f1f5f9] text-[#1e293b]'"
                      class="px-3 py-1 rounded-full text-sm font-label-md"
                    >
                      {{ item.type }}
                    </span>
                  </td>
                  <td class="p-md font-medium" :class="{'text-secondary': item.stock === 0, 'text-[#d97706]': item.stock > 0 && item.stock < 10}">
                    {{ item.stock }}
                  </td>
                  <td class="p-md text-on-surface-variant">{{ item.main_location }}</td>
                  <td class="p-md">
                    <span 
                      v-if="item.status === 'Còn hàng'"
                      class="inline-flex items-center gap-1 text-[#059669] bg-[#d1fae5] px-2 py-1 rounded-md text-sm font-medium"
                    >
                      <span class="material-symbols-outlined text-[16px]">check_circle</span> Còn hàng
                    </span>
                    <span 
                      v-else-if="item.status === 'Sắp hết'"
                      class="inline-flex items-center gap-1 text-[#d97706] bg-[#fef3c7] px-2 py-1 rounded-md text-sm font-medium"
                    >
                      <span class="material-symbols-outlined text-[16px]">warning</span> Sắp hết
                    </span>
                    <span 
                      v-else
                      class="inline-flex items-center gap-1 text-secondary bg-secondary-fixed px-2 py-1 rounded-md text-sm font-medium"
                    >
                      <span class="material-symbols-outlined text-[16px]">error</span> Hết hàng
                    </span>
                  </td>
                </tr>

                <!-- Expanded Breakdown Row -->
                <tr v-if="expandedBookIds.includes(item.id) && item.type === 'Sách vật lý'" class="bg-surface-container-low/30 border-b border-surface-container-high">
                  <td class="p-0" colspan="7">
                    <div class="px-xl py-4 flex flex-wrap gap-lg">
                      <div 
                        v-for="b in item.breakdown" 
                        :key="b.warehouse_id"
                        class="bg-surface-container-lowest p-3 rounded-lg border border-surface-container-high flex-1 min-w-[200px] flex justify-between items-center"
                        :class="{'opacity-50': b.quantity === 0}"
                      >
                        <div class="flex items-center gap-2">
                          <span class="material-symbols-outlined text-outline">warehouse</span>
                          <span class="font-medium text-on-surface">{{ b.warehouse_name }}</span>
                        </div>
                        <span class="font-bold text-primary">{{ b.quantity }} cuốn</span>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="stocks.length === 0">
                <td colspan="7" class="p-xl text-center text-on-surface-variant">Không tìm thấy tồn kho nào phù hợp.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="p-md border-t border-surface-container-high flex justify-between items-center bg-surface-container-lowest">
          <span class="text-sm text-on-surface-variant">Hiển thị trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <div class="flex gap-2">
            <button 
              @click="fetchWarehousesAndStocks(pagination.current_page - 1)"
              :disabled="pagination.current_page === 1"
              class="p-2 border border-outline rounded-md text-on-surface-variant hover:bg-surface-container-low disabled:opacity-50"
            >
              <span class="material-symbols-outlined text-[20px]">chevron_left</span>
            </button>
            <button 
              @click="fetchWarehousesAndStocks(pagination.current_page + 1)"
              :disabled="pagination.current_page === pagination.last_page"
              class="p-2 border border-outline rounded-md text-on-surface-variant hover:bg-surface-container-low disabled:opacity-50"
            >
              <span class="material-symbols-outlined text-[20px]">chevron_right</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ADD WAREHOUSE MODAL -->
    <div v-if="isAddModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-md">
      <div class="bg-surface-container-lowest rounded-xl max-w-md w-full overflow-hidden soft-shadow border border-outline-variant">
        <div class="p-lg border-b border-surface-container-high flex justify-between items-center">
          <h3 class="text-headline-md font-bold text-primary">Thêm kho hàng mới</h3>
          <button @click="isAddModalOpen = false" class="text-outline hover:text-on-surface"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="p-lg flex flex-col gap-lg">
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Tên kho hàng</label>
            <input v-model="newWarehouse.name" type="text" placeholder="Ví dụ: Kho Quận 1" class="border border-outline p-md rounded-lg focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"/>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Địa chỉ</label>
            <input v-model="newWarehouse.address" type="text" placeholder="Địa chỉ chi tiết" class="border border-outline p-md rounded-lg focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"/>
          </div>
        </div>
        <div class="p-lg bg-surface-container-low flex justify-end gap-md">
          <button @click="isAddModalOpen = false" class="px-md py-sm border border-outline rounded-lg hover:bg-surface-container-high text-on-surface">Hủy</button>
          <button @click="handleAddWarehouse" class="px-md py-sm bg-primary text-on-primary rounded-lg hover:opacity-90">Lưu kho</button>
        </div>
      </div>
    </div>

    <!-- ADJUST STOCK MODAL -->
    <div v-if="isAdjustModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-md">
      <div class="bg-surface-container-lowest rounded-xl max-w-md w-full overflow-hidden soft-shadow border border-outline-variant">
        <div class="p-lg border-b border-surface-container-high flex justify-between items-center">
          <h3 class="text-headline-md font-bold text-primary">Điều chỉnh tồn kho</h3>
          <button @click="isAdjustModalOpen = false" class="text-outline hover:text-on-surface"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="p-lg flex flex-col gap-lg">
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Chọn sách</label>
            <select v-model="adjustForm.book_id" class="border border-outline p-md rounded-lg focus:outline-none">
              <option value="">-- Chọn sách vật lý --</option>
              <option v-for="b in allBooksList.filter(x => x.type === 'physical' || x.type === 'Sách vật lý')" :key="b.id" :value="b.id">{{ b.title }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Chọn kho</label>
            <select v-model="adjustForm.source_warehouse_id" class="border border-outline p-md rounded-lg focus:outline-none">
              <option value="">-- Chọn kho hàng --</option>
              <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Số lượng tồn kho mới</label>
            <input v-model.number="adjustForm.quantity" type="number" min="0" class="border border-outline p-md rounded-lg focus:outline-none"/>
          </div>
        </div>
        <div class="p-lg bg-surface-container-low flex justify-end gap-md">
          <button @click="isAdjustModalOpen = false" class="px-md py-sm border border-outline rounded-lg hover:bg-surface-container-high text-on-surface">Hủy</button>
          <button @click="handleAdjustStock" class="px-md py-sm bg-primary text-on-primary rounded-lg hover:opacity-90">Lưu thay đổi</button>
        </div>
      </div>
    </div>

    <!-- TRANSFER STOCK MODAL -->
    <div v-if="isTransferModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-md">
      <div class="bg-surface-container-lowest rounded-xl max-w-md w-full overflow-hidden soft-shadow border border-outline-variant">
        <div class="p-lg border-b border-surface-container-high flex justify-between items-center">
          <h3 class="text-headline-md font-bold text-primary">Điều chuyển kho hàng</h3>
          <button @click="isTransferModalOpen = false" class="text-outline hover:text-on-surface"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="p-lg flex flex-col gap-lg">
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Chọn sách cần điều chuyển</label>
            <select v-model="transferForm.book_id" class="border border-outline p-md rounded-lg focus:outline-none">
              <option value="">-- Chọn sách vật lý --</option>
              <option v-for="b in allBooksList.filter(x => x.type === 'physical' || x.type === 'Sách vật lý')" :key="b.id" :value="b.id">{{ b.title }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Kho xuất hàng (Nguồn)</label>
            <select v-model="transferForm.source_warehouse_id" class="border border-outline p-md rounded-lg focus:outline-none">
              <option value="">-- Chọn kho xuất --</option>
              <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Kho nhập hàng (Đích)</label>
            <select v-model="transferForm.target_warehouse_id" class="border border-outline p-md rounded-lg focus:outline-none">
              <option value="">-- Chọn kho nhập --</option>
              <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Số lượng điều chuyển</label>
            <input v-model.number="transferForm.quantity" type="number" min="1" class="border border-outline p-md rounded-lg focus:outline-none"/>
          </div>
        </div>
        <div class="p-lg bg-surface-container-low flex justify-end gap-md">
          <button @click="isTransferModalOpen = false" class="px-md py-sm border border-outline rounded-lg hover:bg-surface-container-high text-on-surface">Hủy</button>
          <button @click="handleTransferStock" class="px-md py-sm bg-primary text-on-primary rounded-lg hover:opacity-90">Xác nhận chuyển</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.soft-shadow {
  box-shadow: 0px 4px 12px 0px rgba(0, 0, 0, 0.03);
}
</style>

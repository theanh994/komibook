<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()

// --- State ---
const warehouses = ref([])
const stocks = ref([])
const stats = ref({
  total_items: 0,
  low_stock_items: 0,
  outOfStockItems: 0
})

const loading = ref(false)
const pagination = ref({
  total: 0,
  current_page: 1,
  last_page: 1
})

// Filters
const selectedWarehouse = ref('Tất cả kho')
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
const adjustForm = ref({ book_id: '', source_warehouse_id: '', quantity: 1, shelf_location: '' })
const transferForm = ref({ book_id: '', source_warehouse_id: '', target_warehouse_id: '', quantity: 1, shelf_location: '' })

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
        warehouse_id: selectedWarehouse.value === 'Tất cả kho' ? null : selectedWarehouse.value,
        type: selectedType.value,
        status: selectedStatus.value
      }
    })
    warehouses.value = res.data.warehouses
    stocks.value = res.data.stocks
    pagination.value = res.data.pagination
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu kho hàng.', life: 3000 })
  } finally {
    loading.value = false
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

// Image fallback helper
const getBookCover = (url) => {
  if (!url) return 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?q=80&w=200&auto=format&fit=crop'
  if (url.startsWith('http')) return url
  return `/storage/${url}`
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
            @click="isTransferModalOpen = true"
            class="px-4 py-2 border-[1.5px] border-primary text-primary rounded-lg font-label-md text-label-md hover:bg-primary-fixed-dim transition-colors flex items-center gap-2"
          >
            <span class="material-symbols-outlined">sync_alt</span> Điều chuyển kho
          </button>
          <button 
            @click="isAdjustModalOpen = true"
            class="px-4 py-2 border-[1.5px] border-primary text-primary rounded-lg font-label-md text-label-md hover:bg-primary-fixed-dim transition-colors flex items-center gap-2"
          >
            <span class="material-symbols-outlined">edit_note</span> Điều chỉnh kho
          </button>
          <button 
            @click="isAddModalOpen = true"
            class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-opacity-90 transition-colors flex items-center gap-2"
          >
            <span class="material-symbols-outlined">add_home</span> Thêm kho mới
          </button>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
        <div class="bg-surface-container-lowest rounded-xl p-lg soft-shadow border border-surface-container-high">
          <div class="flex justify-between items-start mb-sm">
            <h3 class="font-body-md text-body-md text-on-surface-variant">Tổng mặt hàng (Tất cả kho)</h3>
            <span class="material-symbols-outlined text-primary bg-surface-container p-2 rounded-full">category</span>
          </div>
          <p class="text-display-lg font-display-lg text-on-surface">{{ stats.total_items }}</p>
          <p class="font-body-md text-body-md text-sm text-surface-tint mt-2">Đang được quản lý hệ thống</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg soft-shadow border border-surface-container-high">
          <div class="flex justify-between items-start mb-sm">
            <h3 class="font-body-md text-body-md text-on-surface-variant">Sách sắp hết (Dưới 10)</h3>
            <span class="material-symbols-outlined text-[#d97706] bg-[#fef3c7] p-2 rounded-full">warning</span>
          </div>
          <p class="text-display-lg font-display-lg text-on-surface">{{ stats.low_stock_items }}</p>
          <p class="font-body-md text-body-md text-sm text-[#d97706] mt-2">Cần nhập thêm ngay</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg soft-shadow border border-surface-container-high">
          <div class="flex justify-between items-start mb-sm">
            <h3 class="font-body-md text-body-md text-on-surface-variant">Đã hết hàng</h3>
            <span class="material-symbols-outlined text-secondary bg-secondary-fixed p-2 rounded-full">error</span>
          </div>
          <p class="text-display-lg font-display-lg text-on-surface">{{ stats.out_of_stock_items }}</p>
          <p class="font-body-md text-body-md text-sm text-secondary mt-2">Tạm ngừng hiển thị bán hàng</p>
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
                <option value="Tất cả kho">Tất cả kho</option>
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
                <th class="p-md font-medium">Tổng tồn kho</th>
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
                      <img alt="Book Cover" class="w-full h-full object-cover" :src="getBookCover(item.cover_image)"/>
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
                          <span class="text-sm text-on-surface-variant ml-2">{{ b.shelf_location }}</span>
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
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Vị trí kệ (Không bắt buộc)</label>
            <input v-model="adjustForm.shelf_location" type="text" placeholder="Ví dụ: Kệ A3" class="border border-outline p-md rounded-lg focus:outline-none"/>
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
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Vị trí kệ mới (Không bắt buộc)</label>
            <input v-model="transferForm.shelf_location" type="text" placeholder="Ví dụ: Kệ B2" class="border border-outline p-md rounded-lg focus:outline-none"/>
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

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import RadioButton from 'primevue/radiobutton'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const flashSaleId = route.params.id
const flashSale = ref(null)
const loading = ref(false)
const error = ref('')

const itemDialog = ref(false)
const deleteItemDialog = ref(false)
const deleteMultipleDialog = ref(false)
const submitted = ref(false)
const currentItem = ref({})

const books = ref([])
const categories = ref([])
const selectedItems = ref([]) // For bulk delete checkbox

const addMethod = ref('manual') // 'manual' or 'category'
const selectedCategory = ref(null)

const fetchFlashSale = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await apiClient.get(`/api/admin/flash-sales/${flashSaleId}`)
    flashSale.value = res.data.data
  } catch (e) {
    flashSale.value = null
    error.value = e.response?.data?.message || 'Không thể tải Flash Sale.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: error.value, life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchBooks = async () => {
  try {
    const res = await apiClient.get('/api/books?all=true')
    books.value = res.data.data.map(b => ({ label: b.title, value: b.id }))
  } catch (e) {
    console.error('Failed to fetch books')
  }
}

const fetchCategories = async () => {
  try {
    const res = await apiClient.get('/api/categories')
    categories.value = res.data.data.map(c => ({ label: c.name, value: c.id }))
  } catch (e) {
    console.error('Failed to fetch categories')
  }
}

const openNewItem = () => {
  currentItem.value = {
    book_ids: [],
    discount_percent: 10,
    max_quantity: 0
  }
  addMethod.value = 'manual'
  selectedCategory.value = null
  submitted.value = false
  itemDialog.value = true
}

const saveItem = async () => {
  submitted.value = true

  let bookIds

  if (addMethod.value === 'manual') {
    if (!currentItem.value.book_ids || currentItem.value.book_ids.length === 0) {
      toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng chọn ít nhất một cuốn sách.', life: 3000 })
      return
    }
    bookIds = currentItem.value.book_ids
  } else {
    if (!selectedCategory.value) {
      toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng chọn danh mục.', life: 3000 })
      return
    }
    loading.value = true
    try {
      const res = await apiClient.get(`/api/books?category_id=${selectedCategory.value}&all=true`)
      bookIds = res.data.data.map(b => b.id)
      if (bookIds.length === 0) {
        toast.add({ severity: 'warn', summary: 'Thông báo', detail: 'Danh mục được chọn không có sách nào.', life: 3000 })
        loading.value = false
        return
      }
    } catch (e) {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể lấy danh sách sách theo danh mục', life: 3000 })
      loading.value = false
      return
    }
  }

  if (bookIds.length > 0 && currentItem.value.discount_percent > 0) {
    loading.value = true
    try {
      await apiClient.post(`/api/admin/flash-sales/${flashSaleId}/items`, {
        book_ids: bookIds,
        discount_percent: currentItem.value.discount_percent,
        max_quantity: currentItem.value.max_quantity
      })
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm sản phẩm vào Flash Sale', life: 3000 })
      itemDialog.value = false
      fetchFlashSale()
    } catch (e) {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: e.response?.data?.message || 'Có lỗi xảy ra', life: 3000 })
    } finally {
      loading.value = false
    }
  }
}

const confirmDeleteItem = (item) => {
  currentItem.value = item
  deleteItemDialog.value = true
}

const deleteItem = async () => {
  loading.value = true
  try {
    await apiClient.delete(`/api/admin/flash-sales/${flashSaleId}/items/${currentItem.value.id}`)
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã xóa sản phẩm khỏi Flash Sale', life: 3000 })
    deleteItemDialog.value = false
    fetchFlashSale()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  } finally {
    loading.value = false
  }
}

const deleteSelectedItems = async () => {
  loading.value = true
  try {
    const ids = selectedItems.value.map(item => item.id)
    await apiClient.post(`/api/admin/flash-sales/${flashSaleId}/items/bulk-delete`, { ids })
    toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã xóa ${ids.length} sản phẩm`, life: 3000 })
    deleteMultipleDialog.value = false
    selectedItems.value = []
    fetchFlashSale()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  } finally {
    loading.value = false
  }
}

const getStatusLabel = (status) => {
  const map = { pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối' }
  return map[status] || status || 'Đã duyệt'
}

const getStatusTagClass = (status) => {
  const map = {
    pending: 'bg-amber-100 text-amber-700 border border-amber-200',
    approved: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    rejected: 'bg-red-100 text-red-700 border border-red-200'
  }
  return map[status] || 'bg-emerald-100 text-emerald-700 border border-emerald-200'
}

const approveItem = async (item) => {
  loading.value = true
  try {
    await apiClient.put(`/api/admin/flash-sales/items/${item.id}/approve`, { operation_key: `flash-ui:approve:${item.id}:${Date.now()}` })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã duyệt sản phẩm vào Flash Sale', life: 3000 })
    fetchFlashSale()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.response?.data?.message || 'Không thể duyệt sản phẩm', life: 3000 })
  } finally {
    loading.value = false
  }
}

const rejectItem = async (item) => {
  const reason = window.prompt('Nhập lý do từ chối:')
  if (!reason) return
  loading.value = true
  try {
    await apiClient.put(`/api/admin/flash-sales/items/${item.id}/reject`, { reason, operation_key: `flash-ui:reject:${item.id}:${Date.now()}` })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã từ chối đề xuất tham gia Flash Sale', life: 3000 })
    fetchFlashSale()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.response?.data?.message || 'Không thể từ chối sản phẩm', life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchFlashSale()
  fetchBooks()
  fetchCategories()
})
</script>

<template>
  <div class="pb-xxl w-full pt-6">
    <button type="button" @click="router.back()" class="min-h-11 text-on-surface-variant hover:text-primary transition-colors flex items-center gap-1 mb-md text-sm font-medium">
      <span class="material-symbols-outlined text-[18px]">arrow_back</span>
      Quay lại danh sách
    </button>

    <div v-if="loading && !flashSale" role="status" aria-live="polite" class="rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-10 text-center text-on-surface-variant">
      Đang tải chi tiết Flash Sale...
    </div>

    <div v-else-if="error" role="alert" class="rounded-xl border border-error/20 bg-error/5 p-6 text-center">
      <h1 class="text-xl font-bold text-error">Không thể tải Flash Sale</h1>
      <p class="mt-2 text-sm text-on-surface-variant">{{ error }}</p>
      <button type="button" class="mt-4 min-h-11 rounded-xl bg-error px-5 font-bold text-white" @click="fetchFlashSale">Thử lại</button>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-md mb-xl animate-fade-in" v-else-if="flashSale">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">{{ flashSale.title }}</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-xs">
          Quản lý các sản phẩm trong Flash Sale. Thời gian: {{ flashSale.start_time ? new Date(flashSale.start_time).toLocaleString('vi-VN') : '' }} - {{ flashSale.end_time ? new Date(flashSale.end_time).toLocaleString('vi-VN') : '' }}
        </p>
      </div>
      <div class="flex gap-2">
        <button
          v-if="selectedItems.length > 0"
          @click="deleteMultipleDialog = true"
          type="button"
          class="min-h-11 bg-error text-on-error font-label-md text-label-md px-lg py-sm rounded-lg hover:opacity-90 transition-opacity flex items-center gap-sm shadow-sm whitespace-nowrap"
        >
          <span class="material-symbols-outlined text-[18px]">delete</span>
          Xóa đã chọn ({{ selectedItems.length }})
        </button>
        <button
          @click="openNewItem"
          type="button"
          class="min-h-11 bg-primary text-on-primary font-label-md text-label-md px-lg py-sm rounded-lg hover:opacity-90 transition-opacity flex items-center gap-sm shadow-sm whitespace-nowrap"
        >
          <span class="material-symbols-outlined text-[18px]">add</span>
          Thêm sản phẩm
        </button>
      </div>
    </div>

    <div class="bg-surface-container-lowest rounded-xl shadow-soft overflow-hidden border border-outline-variant/30 animate-slide-up" v-if="flashSale">
      <DataTable
        v-model:selection="selectedItems"
        :value="flashSale.items"
        :loading="loading"
        stripedRows
        responsiveLayout="scroll"
        class="border-none"
        dataKey="id"
      >
        <template #empty>
          <div class="text-center py-xl text-on-surface-variant">
            <span class="material-symbols-outlined text-[48px] block mb-2 text-outline">inventory_2</span>
            <p>Chưa có sản phẩm nào trong Flash Sale.</p>
          </div>
        </template>

        <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

        <Column header="Sản phẩm" style="min-width: 250px">
          <template #body="{ data }">
            <div class="flex items-center gap-3">
              <img v-if="data.book && data.book.cover_image" :src="data.book.cover_image" alt="cover" class="w-10 h-14 object-cover rounded shadow-sm" />
              <div v-else class="w-10 h-14 bg-surface-variant rounded flex items-center justify-center">
                <span class="material-symbols-outlined text-outline">book</span>
              </div>
              <span class="font-medium text-sm text-on-surface line-clamp-2">{{ data.book ? data.book.title : 'Sản phẩm không tồn tại' }}</span>
            </div>
          </template>
        </Column>

        <Column header="Giảm giá" style="min-width: 120px">
          <template #body="{ data }">
            <span class="text-red-500 font-bold bg-red-50 px-2 py-1 rounded text-sm">-{{ data.discount_percent }}%</span>
          </template>
        </Column>

        <Column header="Đã bán/Giới hạn" style="min-width: 150px">
          <template #body="{ data }">
            <span class="text-sm font-medium whitespace-nowrap">{{ data.sold_quantity }} / {{ data.max_quantity === 0 ? '∞' : data.max_quantity }}</span>
          </template>
        </Column>

        <Column header="Gian hàng" style="min-width: 150px">
          <template #body="{ data }">
            <span class="text-sm font-bold text-on-surface-variant whitespace-nowrap">
              {{ data.book?.vendor?.shop_name || 'Admin hệ thống' }}
            </span>
          </template>
        </Column>

        <Column header="Trạng thái" style="min-width: 120px">
          <template #body="{ data }">
            <span :class="['px-2.5 py-1 rounded-full text-xs font-black uppercase tracking-wider shadow-sm whitespace-nowrap', getStatusTagClass(data.status)]">
              {{ getStatusLabel(data.status) }}
            </span>
          </template>
        </Column>

        <Column header="Hành động" :exportable="false" style="min-width: 180px">
          <template #body="slotProps">
            <div class="flex items-center gap-2">
              <Button 
                v-if="slotProps.data.status === 'pending'" 
                icon="pi pi-check" 
                severity="success" 
                rounded 
                outlined
                @click="approveItem(slotProps.data)" 
                v-tooltip="'Duyệt sản phẩm'"
              />
              <Button 
                v-if="slotProps.data.status === 'pending'" 
                icon="pi pi-times" 
                severity="warn" 
                rounded 
                outlined
                @click="rejectItem(slotProps.data)" 
                v-tooltip="'Từ chối sản phẩm'"
              />
              <Button 
                icon="pi pi-trash" 
                outlined 
                rounded 
                severity="danger" 
                @click="confirmDeleteItem(slotProps.data)" 
                v-tooltip="'Xóa khỏi Flash Sale'"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Dialog thêm sản phẩm -->
    <Dialog v-model:visible="itemDialog" :style="{ width: '500px' }" header="Thêm sản phẩm vào Flash Sale" :modal="true" class="p-fluid">
      <div class="flex flex-col gap-4">
        <!-- Phương thức chọn -->
        <div>
          <label class="font-bold text-sm block mb-2">Phương thức thêm</label>
          <div class="flex gap-4">
            <div class="flex items-center">
              <RadioButton v-model="addMethod" inputId="method1" name="addMethod" value="manual" />
              <label for="method1" class="ml-2 text-sm font-medium">Chọn từng sách</label>
            </div>
            <div class="flex items-center">
              <RadioButton v-model="addMethod" inputId="method2" name="addMethod" value="category" />
              <label for="method2" class="ml-2 text-sm font-medium">Theo danh mục</label>
            </div>
          </div>
        </div>

        <!-- Chọn sách thủ công (MultiSelect) -->
        <div v-if="addMethod === 'manual'">
          <label for="books" class="font-bold text-sm block mb-1">Chọn Sách (Có thể chọn nhiều)</label>
          <MultiSelect 
            id="books" 
            v-model="currentItem.book_ids" 
            :options="books" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Tìm và chọn sách..." 
            filter 
            :maxSelectedLabels="3"
            class="w-full"
          />
          <small class="p-error" v-if="submitted && (!currentItem.book_ids || currentItem.book_ids.length === 0)">Bắt buộc chọn ít nhất một cuốn sách.</small>
        </div>

        <!-- Chọn theo danh mục -->
        <div v-else>
          <label for="category" class="font-bold text-sm block mb-1">Chọn Danh mục</label>
          <Select 
            id="category" 
            v-model="selectedCategory" 
            :options="categories" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn danh mục..." 
            filter 
            class="w-full"
          />
          <small class="p-error" v-if="submitted && !selectedCategory">Bắt buộc chọn danh mục.</small>
        </div>

        <!-- Thông số chung cho các sách được thêm -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="discount" class="font-bold text-sm block mb-1">Phần trăm giảm (%)</label>
            <InputNumber id="discount" v-model="currentItem.discount_percent" :min="1" :max="100" suffix="%" />
          </div>
          <div>
            <label for="limit" class="font-bold text-sm block mb-1">Giới hạn số lượng</label>
            <InputNumber id="limit" v-model="currentItem.max_quantity" :min="0" placeholder="0 = Không giới hạn" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Hủy" icon="pi pi-times" text @click="itemDialog = false" />
        <Button label="Thêm vào" icon="pi pi-check" severity="primary" @click="saveItem" :loading="loading" />
      </template>
    </Dialog>

    <!-- Dialog xóa đơn lẻ -->
    <Dialog v-model:visible="deleteItemDialog" :style="{ width: '450px' }" header="Xác nhận xóa" :modal="true">
      <div class="flex items-center gap-4">
        <span class="material-symbols-outlined text-[40px] text-red-500">warning</span>
        <span v-if="currentItem">Xóa sản phẩm khỏi Flash Sale?</span>
      </div>
      <template #footer>
        <Button label="Không" icon="pi pi-times" text @click="deleteItemDialog = false" />
        <Button label="Xóa ngay" icon="pi pi-check" severity="danger" @click="deleteItem" :loading="loading" />
      </template>
    </Dialog>

    <!-- Dialog xóa hàng loạt -->
    <Dialog v-model:visible="deleteMultipleDialog" :style="{ width: '450px' }" header="Xác nhận xóa hàng loạt" :modal="true">
      <div class="flex items-center gap-4">
        <span class="material-symbols-outlined text-[40px] text-red-500">warning</span>
        <span>Bạn có chắc chắn muốn xóa {{ selectedItems.length }} sản phẩm đã chọn khỏi Flash Sale này không?</span>
      </div>
      <template #footer>
        <Button label="Không" icon="pi pi-times" text @click="deleteMultipleDialog = false" />
        <Button label="Xóa tất cả" icon="pi pi-check" severity="danger" @click="deleteSelectedItems" :loading="loading" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.shadow-soft { box-shadow: 0 4px 12px rgba(26, 58, 90, 0.04); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
@media (prefers-reduced-motion: reduce) {
  .animate-fade-in,
  .animate-slide-up {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>

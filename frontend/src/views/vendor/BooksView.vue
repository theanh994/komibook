<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import Tag from 'primevue/tag'
import ConfirmDialog from 'primevue/confirmdialog'
import { useConfirm } from 'primevue/useconfirm'
import Skeleton from 'primevue/skeleton'
import Menu from 'primevue/menu'

const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

// ─── State ───
const books = ref([])
const selectedBooks = ref([])
const categories = ref([])
const existingSeriesList = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyParams = ref({ first: 0, rows: 10, page: 1 })

const bulkSeriesDialogVisible = ref(false)
const bulkSeriesName = ref('')
const isSubmittingBulkSeries = ref(false)

const bulkDiscountDialogVisible = ref(false)
const bulkDiscountPercent = ref(10)
const isSubmittingBulkDiscount = ref(false)

// Dummy skeleton rows for clean loading state
const skeletonRows = Array.from({ length: 5 }, (_, i) => ({ id: `sk-${i}` }))

// Filters
const filterTitle = ref('')
const filterCategoryIds = ref([])
const filterType = ref(null)
const filterStatus = ref(null)
const filterSort = ref('created_at_desc')

const sortOptions = [
  { label: 'Thêm mới nhất', value: 'created_at_desc' },
  { label: 'Thêm cũ nhất', value: 'created_at_asc' },
  { label: 'Tên A - Z', value: 'title_asc' },
  { label: 'Tên Z - A', value: 'title_desc' },
  { label: 'Giá cao đến thấp', value: 'price_desc' },
  { label: 'Giá thấp đến cao', value: 'price_asc' },
]

// Action Menu
const actionMenuRef = ref()
const activeRow = ref(null)

const actionMenuItems = computed(() => [
  {
    label: 'Chỉnh sửa',
    icon: 'pi pi-pencil',
    command: () => {
      if (activeRow.value) {
        router.push(`/vendor/books/${activeRow.value.id}/edit`)
      }
    },
  },
  {
    label: 'Xóa sách',
    icon: 'pi pi-trash',
    class: 'text-red-500',
    command: () => {
      if (activeRow.value) confirmDelete(activeRow.value)
    },
  },
])

const toggleActionMenu = (event, data) => {
  activeRow.value = data
  actionMenuRef.value.toggle(event)
}

const bookTypes = [
  { label: 'Sách vật lý', value: 'physical' },
  { label: 'E-book', value: 'ebook' },
]
const bookStatuses = [
  { label: 'Đã xuất bản', value: 'published' },
  { label: 'Nháp', value: 'draft' },
]

// ─── Methods ───
const fetchBooks = async () => {
  loading.value = true
  try {
    const params = {
      page: lazyParams.value.page,
      per_page: lazyParams.value.rows,
    }
    if (filterTitle.value) params.search = filterTitle.value
    if (filterCategoryIds.value && filterCategoryIds.value.length > 0) {
      params.category_ids = filterCategoryIds.value.join(',')
    }
    if (filterType.value) params.type = filterType.value
    if (filterStatus.value) params.status = filterStatus.value
    if (filterSort.value) params.sort = filterSort.value

    const res = await apiClient.get('/api/vendor/books', { params })

    if (res.data.data) {
      books.value = res.data.data
      totalRecords.value = res.data.total || res.data.meta?.total || res.data.data.length
    } else {
      books.value = Array.isArray(res.data) ? res.data : []
      totalRecords.value = books.value.length
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách sách.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchCategories = async () => {
  try {
    const res = await apiClient.get('/api/categories')
    const raw = res.data.data || res.data
    categories.value = raw.map(c => ({ label: c.name, value: c.id }))
  } catch (e) {
    console.warn('Không tải được danh mục', e)
  }
}

const onPage = (event) => {
  lazyParams.value.first = event.first
  lazyParams.value.rows = event.rows
  lazyParams.value.page = event.page + 1
  fetchBooks()
}

const onFilter = () => {
  lazyParams.value.page = 1
  lazyParams.value.first = 0
  fetchBooks()
}

const resetFilter = () => {
  filterTitle.value = ''
  filterCategoryIds.value = []
  filterType.value = null
  filterStatus.value = null
  filterSort.value = 'created_at_desc'
  onFilter()
}

const navigateToCreate = () => {
  router.push('/vendor/books/create')
}

const confirmDelete = (book) => {
  confirm.require({
    message: `Bạn có chắc chắn muốn xóa sách "${book.title}"?`,
    header: 'Xác nhận xóa sách',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Hủy',
    acceptLabel: 'Xóa ngay',
    acceptClass: 'p-button-danger',
    accept: () => deleteBook(book.id),
  })
}

const deleteBook = async (id) => {
  try {
    await apiClient.delete(`/api/vendor/books/${id}`)
    toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Sách đã được xóa thành công.', life: 3000 })
    fetchBooks()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa sách.', life: 3000 })
  }
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  return `/storage/${path}`
}

const getTypeSeverity = (type) => type === 'ebook' ? 'info' : 'success'
const getTypeLabel = (type) => type === 'ebook' ? 'E-book' : 'Vật lý'
const getStatusSeverity = (status) => status === 'published' ? 'success' : 'warn'
const getStatusLabel = (status) => status === 'published' ? 'Đã xuất bản' : 'Nháp'

const formatPrice = (val) => {
  if (!val && val !== 0) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const fetchExistingSeries = async () => {
  try {
    const res = await apiClient.get('/api/series')
    existingSeriesList.value = res.data.data || []
  } catch (e) {
    console.warn('Không tải được danh sách series', e)
  }
}

const openBulkSeriesDialog = () => {
  bulkSeriesName.value = ''
  bulkSeriesDialogVisible.value = true
}

const submitBulkSeries = async () => {
  if (!bulkSeriesName.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập tên Bộ sách.', life: 3000 })
    return
  }
  isSubmittingBulkSeries.value = true
  try {
    const bookIds = selectedBooks.value.map(b => b.id)
    await apiClient.post('/api/vendor/books/bulk-series', {
      book_ids: bookIds,
      action: 'assign',
      series_name: bulkSeriesName.value.trim()
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã gán bộ sách "${bulkSeriesName.value}" cho ${bookIds.length} cuốn sách.`, life: 3000 })
    bulkSeriesDialogVisible.value = false
    selectedBooks.value = []
    fetchBooks()
    fetchExistingSeries()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể thực hiện thao tác hàng loạt.', life: 3000 })
  } finally {
    isSubmittingBulkSeries.value = false
  }
}

const openBulkDiscountDialog = () => {
  bulkDiscountPercent.value = 10
  bulkDiscountDialogVisible.value = true
}

const submitBulkDiscount = async () => {
  if (bulkDiscountPercent.value === null || bulkDiscountPercent.value === undefined) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập % giảm giá.', life: 3000 })
    return
  }
  if (bulkDiscountPercent.value < 0 || bulkDiscountPercent.value > 15) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Mức giảm giá tối đa cho Gian hàng là 15%.', life: 3000 })
    return
  }

  isSubmittingBulkDiscount.value = true
  try {
    const bookIds = selectedBooks.value.map(b => b.id)
    await apiClient.post('/api/vendor/books/bulk-discount', {
      book_ids: bookIds,
      discount_percent: bulkDiscountPercent.value
    })
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: bulkDiscountPercent.value > 0
        ? `Đã gán giảm giá ${bulkDiscountPercent.value}% cho ${bookIds.length} cuốn sách.`
        : 'Đã gỡ bỏ giảm giá cho các cuốn sách được chọn.',
      life: 3000
    })
    bulkDiscountDialogVisible.value = false
    selectedBooks.value = []
    fetchBooks()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể thực hiện thao tác giảm giá hàng loạt.', life: 3000 })
  } finally {
    isSubmittingBulkDiscount.value = false
  }
}

const confirmBulkRemoveSeries = () => {
  if (!selectedBooks.value.length) return
  confirm.require({
    message: `Bạn có chắc chắn muốn XÓA BỘ SÁCH của ${selectedBooks.value.length} cuốn sách đã chọn?`,
    header: 'Xác nhận gỡ bộ sách',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Hủy',
    acceptLabel: 'Gỡ bộ sách',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        const bookIds = selectedBooks.value.map(b => b.id)
        await apiClient.post('/api/vendor/books/bulk-series', {
          book_ids: bookIds,
          action: 'remove'
        })
        toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã gỡ bộ sách thành công.', life: 3000 })
        selectedBooks.value = []
        fetchBooks()
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể gỡ bộ sách.', life: 3000 })
      }
    }
  })
}

onMounted(() => {
  fetchBooks()
  fetchCategories()
  fetchExistingSeries()
})
</script>

<template>
  <div class="vendor-books">
    <ConfirmDialog />

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Sách</h1>
        <p class="page-subtitle">Quản lý tất cả sách trong gian hàng của bạn</p>
      </div>
      <Button
        label="Thêm sách mới"
        icon="pi pi-plus"
        class="btn-primary"
        @click="navigateToCreate"
      />
    </div>

    <!-- Bulk Actions Toolbar (Shown when books are selected) -->
    <div v-if="selectedBooks.length > 0" class="mb-4 p-4 bg-indigo-50 border border-indigo-200 rounded-xl flex items-center justify-between shadow-xs animate-fade-in">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-indigo-600">checklist</span>
        <span class="font-bold text-sm text-indigo-900">Đã chọn {{ selectedBooks.length }} cuốn sách</span>
      </div>

      <div class="flex items-center gap-2">
        <Button
          label="Giảm giá đồng loạt"
          icon="pi pi-percentage"
          class="p-button-sm p-button-warning !rounded-md"
          @click="openBulkDiscountDialog"
        />
        <Button
          label="Gán vào Bộ sách"
          icon="pi pi-bookmark"
          class="p-button-sm p-button-primary !rounded-md"
          @click="openBulkSeriesDialog"
        />
        <Button
          label="Xóa khỏi Bộ sách"
          icon="pi pi-bookmark-fill"
          class="p-button-sm p-button-secondary !rounded-md"
          @click="confirmBulkRemoveSeries"
        />
        <Button
          icon="pi pi-times"
          text
          rounded
          class="p-button-sm text-slate-500"
          v-tooltip.top="'Bỏ chọn'"
          @click="selectedBooks = []"
        />
      </div>
    </div>

    <!-- Data Table Card -->
    <div class="table-card">
      <!-- Toolbar / Filters -->
      <div class="table-toolbar">
        <div class="filter-group">
          <div class="search-input-wrapper">
            <i class="pi pi-search search-icon" />
            <InputText
              v-model="filterTitle"
              placeholder="Tìm theo tên sách, tác giả..."
              class="search-input-field"
              @keyup.enter="onFilter"
            />
          </div>

          <!-- MultiSelect Category Filter -->
          <MultiSelect
            v-model="filterCategoryIds"
            :options="categories"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc theo thể loại"
            :maxSelectedLabels="2"
            class="filter-select min-w-[200px]"
            @change="onFilter"
          />

          <!-- Sort Filter -->
          <Select
            v-model="filterSort"
            :options="sortOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Sắp xếp theo"
            class="filter-select min-w-[175px]"
            @change="onFilter"
          />

          <Select
            v-model="filterType"
            :options="bookTypes"
            optionLabel="label"
            optionValue="value"
            placeholder="Loại sách"
            showClear
            class="filter-select filter-select-sm"
            @change="onFilter"
          />

          <Select
            v-model="filterStatus"
            :options="bookStatuses"
            optionLabel="label"
            optionValue="value"
            placeholder="Trạng thái"
            showClear
            class="filter-select filter-select-sm"
            @change="onFilter"
          />

          <Button
            icon="pi pi-filter-slash"
            severity="secondary"
            text
            class="h-[42px] w-[42px]"
            v-tooltip.top="'Xóa bộ lọc'"
            @click="resetFilter"
          />
        </div>
      </div>

      <!-- PrimeVue DataTable with Selection & Clean Inline Skeletons -->
      <DataTable
        v-model:selection="selectedBooks"
        :value="loading ? skeletonRows : books"
        lazy
        paginator
        :rows="lazyParams.rows"
        :totalRecords="totalRecords"
        :first="lazyParams.first"
        @page="onPage"
        dataKey="id"
        responsiveLayout="scroll"
        class="custom-datatable"
        emptyMessage="Chưa có sách nào trong gian hàng."
      >
        <!-- Selection Checkbox Column -->
        <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
        <!-- Column: Cover & Info -->
        <Column header="Sách" style="min-width: 280px">
          <template #body="{ data }">
            <div v-if="loading" class="flex items-center gap-3">
              <Skeleton width="44px" height="60px" borderRadius="8px" />
              <div class="space-y-2 flex-1">
                <Skeleton width="75%" height="1rem" />
                <Skeleton width="45%" height="0.75rem" />
              </div>
            </div>
            <div v-else class="book-info-cell">
              <div class="book-cover-wrap">
                <img
                  v-if="data.cover_image"
                  :src="getCoverUrl(data.cover_image)"
                  :alt="data.title"
                  class="book-cover-img"
                />
                <div v-else class="book-cover-placeholder">
                  <i class="pi pi-book" />
                </div>
              </div>
              <div class="book-text-info">
                <router-link :to="`/book/${data.slug}`" class="book-title-link" target="_blank">
                  {{ data.title }}
                </router-link>
                <div class="book-meta">
                  <span class="book-author">{{ data.author }}</span>
                  <span v-if="data.isbn" class="book-isbn"> • ISBN: {{ data.isbn }}</span>
                </div>
              </div>
            </div>
          </template>
        </Column>

        <!-- Column: Categories -->
        <Column header="Danh mục" style="min-width: 140px">
          <template #body="{ data }">
            <Skeleton v-if="loading" width="90px" height="1.25rem" borderRadius="6px" />
            <template v-else>
              <div v-if="data.categories && data.categories.length > 0" class="flex flex-wrap gap-1">
                <span v-for="cat in data.categories" :key="cat.id" class="category-badge">
                  {{ cat.name }}
                </span>
              </div>
              <span v-else-if="data.category" class="category-badge">
                {{ data.category.name }}
              </span>
              <span v-else class="text-gray-400 text-xs">—</span>
            </template>
          </template>
        </Column>

        <!-- Column: Type -->
        <Column header="Loại sách" style="min-width: 110px">
          <template #body="{ data }">
            <Skeleton v-if="loading" width="60px" height="1.5rem" borderRadius="12px" />
            <Tag v-else :value="getTypeLabel(data.type)" :severity="getTypeSeverity(data.type)" />
          </template>
        </Column>

        <!-- Column: Price -->
        <Column header="Giá bán" style="min-width: 130px">
          <template #body="{ data }">
            <div v-if="loading" class="space-y-1">
              <Skeleton width="75px" height="0.9rem" />
              <Skeleton width="50px" height="0.75rem" />
            </div>
            <div v-else class="price-cell">
              <span v-if="data.sale_price" class="sale-price">{{ formatPrice(data.sale_price) }}</span>
              <span :class="['original-price', { 'line-through': data.sale_price }]">
                {{ formatPrice(data.price) }}
              </span>
            </div>
          </template>
        </Column>

        <!-- Column: Stock -->
        <Column header="Tồn kho" style="min-width: 90px">
          <template #body="{ data }">
            <Skeleton v-if="loading" width="40px" height="1rem" />
            <span v-else :class="['stock-badge', { 'out-of-stock': data.stock <= 0 }]">
              {{ data.stock }}
            </span>
          </template>
        </Column>

        <!-- Column: Status -->
        <Column header="Trạng thái" style="min-width: 120px">
          <template #body="{ data }">
            <Skeleton v-if="loading" width="80px" height="1.5rem" borderRadius="12px" />
            <Tag v-else :value="getStatusLabel(data.status)" :severity="getStatusSeverity(data.status)" />
          </template>
        </Column>

        <!-- Column: Actions -->
        <Column header="Thao tác" style="width: 80px; text-align: center">
          <template #body="{ data }">
            <Skeleton v-if="loading" width="28px" height="28px" shape="circle" class="mx-auto" />
            <div v-else class="actions-cell">
              <Button
                icon="pi pi-ellipsis-v"
                text
                rounded
                severity="secondary"
                @click="(e) => toggleActionMenu(e, data)"
                v-tooltip.top="'Tác vụ'"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Action Menu -->
      <Menu ref="actionMenuRef" :model="actionMenuItems" :popup="true" />
    </div>

    <!-- BULK SERIES DIALOG -->
    <Dialog
      v-model:visible="bulkSeriesDialogVisible"
      modal
      header="Gán Bộ sách Hàng Loạt"
      class="!max-w-md !w-[90vw] !rounded-2xl"
    >
      <div class="space-y-4 pt-2">
        <p class="text-xs text-slate-600 leading-relaxed">
          Đang thao tác gán bộ sách cho <strong class="text-indigo-600">{{ selectedBooks.length }}</strong> cuốn sách đã chọn.
        </p>

        <div>
          <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tên Bộ Sách (Series)</label>
          <InputText
            v-model="bulkSeriesName"
            list="bulk-existing-series"
            placeholder="Ví dụ: Komi - Nữ Thần Sợ Giao Tiếp"
            class="w-full !h-11 !p-3 !rounded-xl text-sm"
          />
          <datalist id="bulk-existing-series">
            <option v-for="s in existingSeriesList" :key="s.id" :value="s.title"></option>
          </datalist>
          <p class="text-[11px] text-slate-500 mt-1">Chọn từ danh sách gợi ý hoặc nhập tên bộ sách mới.</p>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-3">
          <Button label="Hủy" text severity="secondary" @click="bulkSeriesDialogVisible = false" />
          <Button
            label="Xác nhận Gán"
            icon="pi pi-check"
            :loading="isSubmittingBulkSeries"
            @click="submitBulkSeries"
          />
        </div>
      </template>
    </Dialog>

    <!-- BULK DISCOUNT DIALOG (CAPPED AT 15%) -->
    <Dialog
      v-model:visible="bulkDiscountDialogVisible"
      modal
      header="Giảm Giá Đồng Loạt"
      class="!max-w-md !w-[90vw] !rounded-2xl"
    >
      <div class="space-y-4 pt-2">
        <p class="text-xs text-slate-600 leading-relaxed">
          Đang thao tác gán mức giảm giá cho <strong class="text-indigo-600">{{ selectedBooks.length }}</strong> cuốn sách đã chọn.
        </p>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Mức Giảm Giá (%)</label>
            <span class="text-xs font-extrabold text-amber-600">Tối đa 15%</span>
          </div>
          <InputNumber
            v-model="bulkDiscountPercent"
            :min="0"
            :max="15"
            suffix="%"
            class="w-full !h-11 text-sm"
            placeholder="Nhập 0% để xóa giảm giá"
          />
          <p class="text-[11px] text-slate-500 mt-1">Nhập 0% để đưa tất cả các cuốn sách đã chọn về giá gốc ban đầu.</p>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-3">
          <Button label="Hủy" text severity="secondary" @click="bulkDiscountDialogVisible = false" />
          <Button
            label="Áp dụng Giảm Giá"
            icon="pi pi-check"
            :loading="isSubmittingBulkDiscount"
            @click="submitBulkDiscount"
            class="!rounded-md"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.vendor-books {
  padding: 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #0f172a;
}

.page-subtitle {
  color: #64748b;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.btn-primary {
  background: #4f46e5 !important;
  border-color: #4f46e5 !important;
  border-radius: 8px !important;
  font-weight: 600 !important;
}

.table-card {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 1px border-slate-200;
  overflow: hidden;
}

.table-toolbar {
  padding: 1rem 1.5rem;
  border-bottom: 1px border-slate-200;
  background: #f8fafc;
}

.filter-group {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  flex-wrap: wrap;
}

.search-input-wrapper {
  position: relative;
  flex: 1;
  min-width: 260px;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 0.85rem;
  color: #94a3b8;
  z-index: 2;
  pointer-events: none;
  font-size: 0.95rem;
}

.search-input-field {
  width: 100%;
  height: 40px !important;
  border-radius: 8px !important;
  padding-left: 2.4rem !important;
  border: 1px solid #cbd5e1 !important;
}

.filter-select {
  height: 40px !important;
  min-width: 170px;
  border-radius: 8px !important;
  display: inline-flex;
  align-items: center;
}

.filter-select-sm {
  min-width: 130px;
}

/* Book Cell */
.book-info-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.book-cover-wrap {
  width: 44px;
  height: 60px;
  border-radius: 0px !important;
  overflow: hidden;
  background: #f1f5f9;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.book-cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 0px !important;
}

.book-cover-placeholder {
  color: #94a3b8;
  font-size: 1.25rem;
}

.book-text-info {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.book-title-link {
  font-weight: 600;
  color: #0f172a;
  text-decoration: none;
  font-size: 0.9rem;
  line-height: 1.25;
}

.book-title-link:hover {
  color: #4f46e5;
}

.book-meta {
  font-size: 0.75rem;
  color: #64748b;
}

.category-badge {
  display: inline-block;
  background: #f1f5f9;
  color: #334155;
  padding: 0.2rem 0.5rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 500;
}

.price-cell {
  display: flex;
  flex-direction: column;
}

.sale-price {
  font-weight: 700;
  color: #ef4444;
  font-size: 0.875rem;
}

.original-price {
  font-size: 0.8rem;
  color: #64748b;
}

.original-price.line-through {
  text-decoration: line-through;
  font-size: 0.75rem;
  color: #94a3b8;
}

.stock-badge {
  font-weight: 600;
  color: #059669;
}

.stock-badge.out-of-stock {
  color: #ef4444;
}

.actions-cell {
  display: flex;
  justify-content: center;
}

/* Skeleton loader */
.skeleton-container {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.skeleton-row {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.skeleton-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}
</style>

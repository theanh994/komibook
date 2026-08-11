<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

const toast = useToast()
const confirm = useConfirm()

// ─── State ───
const books = ref([])
const categories = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyParams = ref({ first: 0, rows: 15, page: 1 })

// Filtering State
const filterTitle = ref('')
const filterCategory = ref(null)
const filterType = ref(null)
const filterStatus = ref(null)

// Updating status loading state
const updatingStatusId = ref(null)

// ─── Options ───
const bookTypes = [
  { label: 'Sách vật lý', value: 'physical' },
  { label: 'E-book Digital', value: 'ebook' },
]

const bookStatuses = [
  { label: 'Đã xuất bản', value: 'published' },
  { label: 'Bản nháp', value: 'draft' },
]

// ─── Formatters & Helpers ───
const formatPrice = (val) => {
  if (!val && val !== 0) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  return `/storage/${path}`
}

const getTypeSeverity = (type) => (type === 'ebook' ? 'info' : 'success')
const getTypeLabel = (type) => (type === 'ebook' ? 'E-book' : 'Vật lý')
// ─── API Calls ───
const fetchBooks = async () => {
  loading.value = true
  try {
    const params = {
      page: lazyParams.value.page,
      per_page: lazyParams.value.rows,
    }
    if (filterTitle.value) params.search = filterTitle.value
    if (filterCategory.value) params.category_id = filterCategory.value
    if (filterType.value) params.type = filterType.value
    if (filterStatus.value) params.status = filterStatus.value

    const res = await apiClient.get('/api/admin/books', { params })

    if (res.data.data) {
      books.value = res.data.data
      totalRecords.value = res.data.meta?.total || res.data.data.length
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể tải danh sách sách hệ thống.',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const fetchCategories = async () => {
  try {
    const res = await apiClient.get('/api/categories')
    const raw = res.data.data || res.data
    categories.value = raw.map((c) => ({ label: c.name, value: c.id }))
  } catch (e) {
    console.warn('Không tải được danh mục', e)
  }
}

const updateStatus = async (book, newStatus) => {
  if (book.status === newStatus) return
  updatingStatusId.value = book.id
  try {
    await apiClient.patch(`/api/admin/books/${book.id}/status`, {
      status: newStatus,
    })
    book.status = newStatus
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: `Đã cập nhật trạng thái sách "${book.title}".`,
      life: 3000,
    })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể cập nhật trạng thái.',
      life: 3000,
    })
  } finally {
    updatingStatusId.value = null
  }
}

const confirmDeleteBook = (book) => {
  confirm.require({
    message: `Bạn có chắc chắn muốn xóa sách "${book.title}" khỏi hệ thống?`,
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
    await apiClient.delete(`/api/admin/books/${id}`)
    toast.add({
      severity: 'success',
      summary: 'Đã xóa',
      detail: 'Sách đã được xóa thành công.',
      life: 3000,
    })
    fetchBooks()
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể xóa sách khỏi hệ thống.',
      life: 3000,
    })
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
  filterCategory.value = null
  filterType.value = null
  filterStatus.value = null
  onFilter()
}

onMounted(() => {
  fetchBooks()
  fetchCategories()
})
</script>

<template>
  <div class="admin-books space-y-6">

    <!-- ═══ PAGE HEADER ═══ -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight">
          Quản lý toàn bộ sách
        </h1>
        <p class="text-sm text-slate-500 mt-1">
          Quản lý và kiểm duyệt tất cả các tựa sách trên toàn hệ thống Komibook
        </p>
      </div>

      <div class="flex items-center gap-2 bg-indigo-50 text-indigo-700 px-4 py-2 rounded-xl text-sm font-semibold border border-indigo-100">
        <i class="pi pi-book text-base"></i>
        <span>{{ totalRecords }} sách trên hệ thống</span>
      </div>
    </div>

    <!-- ═══ DATA TABLE CARD ═══ -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
      <!-- Toolbar Filters -->
      <div class="p-4 border-b border-slate-100 bg-slate-50/50">
        <div class="flex flex-wrap items-center gap-3">
          <!-- Search input -->
          <div class="relative flex-1 min-w-[260px]">
            <i class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <InputText
              v-model="filterTitle"
              aria-label="Tìm sách theo tên, tác giả, gian hàng hoặc ISBN"
              placeholder="Tìm theo tên sách, tác giả, gian hàng, ISBN..."
              class="w-full !pl-10 !pr-4 !py-2 !h-11 !bg-white !border-slate-200 !rounded-xl !text-sm"
              @keyup.enter="onFilter"
            />
          </div>

          <!-- Category filter -->
          <Select
            v-model="filterCategory"
            :options="categories"
            optionLabel="label"
            optionValue="value"
            placeholder="Tất cả danh mục"
            aria-label="Lọc theo danh mục"
            showClear
            class="!h-11 min-w-[170px] !rounded-xl"
            @change="onFilter"
          />

          <!-- Type filter -->
          <Select
            v-model="filterType"
            :options="bookTypes"
            optionLabel="label"
            optionValue="value"
            placeholder="Loại sách"
            aria-label="Lọc theo loại sách"
            showClear
            class="!h-11 min-w-[130px] !rounded-xl"
            @change="onFilter"
          />

          <!-- Status filter -->
          <Select
            v-model="filterStatus"
            :options="bookStatuses"
            optionLabel="label"
            optionValue="value"
            placeholder="Trạng thái"
            aria-label="Lọc theo trạng thái sách"
            showClear
            class="!h-11 min-w-[140px] !rounded-xl"
            @change="onFilter"
          />

          <Button
            icon="pi pi-filter-slash"
            severity="secondary"
            text
            aria-label="Xóa bộ lọc sách"
            class="!h-11 !w-11 !rounded-xl"
            v-tooltip.top="'Xóa bộ lọc'"
            @click="resetFilter"
          />
        </div>
      </div>

      <!-- DataTable -->
      <DataTable
        :value="books"
        :loading="loading"
        lazy
        paginator
        :rows="lazyParams.rows"
        :totalRecords="totalRecords"
        :first="lazyParams.first"
        @page="onPage"
        dataKey="id"
        stripedRows
        class="admin-books-table"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
      >
        <template #empty>
          <div class="text-center py-12 text-slate-400">
            <i class="pi pi-book text-4xl mb-3 block"></i>
            <p class="font-medium text-sm">Chưa có cuốn sách nào khớp với điều kiện tìm kiếm.</p>
          </div>
        </template>

        <!-- Column: Cover & Book Info -->
        <Column header="Tựa sách & Tác giả" style="min-width: 280px">
          <template #body="{ data }">
            <div class="flex items-center gap-3">
              <div class="w-11 h-15 rounded-lg overflow-hidden bg-slate-100 shrink-0 border border-slate-200 shadow-sm flex items-center justify-center">
                <img
                  v-if="data.cover_image"
                  :src="getCoverUrl(data.cover_image)"
                  :alt="data.title"
                  class="w-full h-full object-cover"
                />
                <i v-else class="pi pi-book text-slate-400 text-lg"></i>
              </div>
              <div class="min-w-0">
                <a
                  :href="`/book/${data.slug}`"
                  target="_blank"
                  class="min-h-11 font-bold text-sm text-slate-800 hover:text-indigo-600 transition-colors line-clamp-1 text-decoration-none flex items-center"
                >
                  {{ data.title }}
                </a>
                <p class="text-xs text-slate-500 mt-0.5">
                  <span class="font-medium text-slate-700">{{ data.author }}</span>
                  <span v-if="data.isbn" class="text-slate-400"> • ISBN: {{ data.isbn }}</span>
                </p>
              </div>
            </div>
          </template>
        </Column>

        <!-- Column: Vendor -->
        <Column header="Gian hàng (Vendor)" style="min-width: 160px">
          <template #body="{ data }">
            <div v-if="data.vendor" class="flex items-center gap-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50/70 px-2.5 py-1 rounded-lg w-fit border border-indigo-100">
              <i class="pi pi-shop text-[11px]"></i>
              <span>{{ data.vendor.shop_name }}</span>
            </div>
            <span v-else class="text-slate-400 text-xs italic">—</span>
          </template>
        </Column>

        <!-- Column: Categories -->
        <Column header="Danh mục" style="min-width: 150px">
          <template #body="{ data }">
            <div v-if="data.categories && data.categories.length > 0" class="flex flex-wrap gap-1">
              <span v-for="cat in data.categories" :key="cat.id" class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-[11px] font-medium">
                {{ cat.name }}
              </span>
            </div>
            <span v-else-if="data.category" class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-[11px] font-medium">
              {{ data.category.name }}
            </span>
            <span v-else class="text-slate-400 text-xs">—</span>
          </template>
        </Column>

        <!-- Column: Type -->
        <Column header="Loại sách" style="min-width: 110px">
          <template #body="{ data }">
            <Tag :value="getTypeLabel(data.type)" :severity="getTypeSeverity(data.type)" rounded />
          </template>
        </Column>

        <!-- Column: Price -->
        <Column header="Giá bán" style="min-width: 130px">
          <template #body="{ data }">
            <div class="flex flex-col">
              <span v-if="data.sale_price" class="font-bold text-red-500 text-xs">{{ formatPrice(data.sale_price) }}</span>
              <span :class="['text-xs text-slate-600', { 'line-through text-slate-400 text-[11px]': data.sale_price }]">
                {{ formatPrice(data.price) }}
              </span>
            </div>
          </template>
        </Column>

        <!-- Column: Stock -->
        <Column header="Tồn kho" style="min-width: 90px">
          <template #body="{ data }">
            <span :class="['font-semibold text-xs', data.stock <= 0 ? 'text-red-500' : 'text-emerald-600']">
              {{ data.type === 'ebook' ? 'Vô hạn' : data.stock }}
            </span>
          </template>
        </Column>

        <!-- Column: Status -->
        <Column header="Trạng thái" style="min-width: 140px">
          <template #body="{ data }">
            <Select
              :modelValue="data.status"
              @update:modelValue="(val) => updateStatus(data, val)"
              :options="bookStatuses"
              optionLabel="label"
              optionValue="value"
              :loading="updatingStatusId === data.id"
              class="!h-8 !text-xs !w-full !rounded-lg"
            />
          </template>
        </Column>

        <!-- Column: Actions -->
        <Column header="Thao tác" style="min-width: 100px; text-align: right">
          <template #body="{ data }">
            <div class="flex items-center justify-end gap-1">
              <a
                :href="`/book/${data.slug}`"
                target="_blank"
                class="p-button p-button-text p-button-secondary p-button-rounded p-button-icon-only flex items-center justify-center w-8 h-8 rounded-full hover:bg-slate-100 text-slate-500"
                v-tooltip.top="'Xem trước'"
              >
                <i class="pi pi-eye text-sm"></i>
              </a>
              <Button
                icon="pi pi-trash"
                text
                rounded
                severity="danger"
                @click="confirmDeleteBook(data)"
                v-tooltip.top="'Xóa khỏi hệ thống'"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>
  </div>
</template>

<style scoped>
:deep(.p-select) {
  display: inline-flex;
  align-items: center;
}
:deep(.p-select-label) {
  display: flex;
  align-items: center;
  font-size: 0.75rem !important;
  font-weight: 600;
}
</style>

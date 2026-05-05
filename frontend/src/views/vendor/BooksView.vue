<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import FileUpload from 'primevue/fileupload'
import ConfirmDialog from 'primevue/confirmdialog'
import { useConfirm } from 'primevue/useconfirm'
import Skeleton from 'primevue/skeleton'
import Menu from 'primevue/menu'

const toast = useToast()
const confirm = useConfirm()

// ─── State ───
const books = ref([])
const categories = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyParams = ref({ first: 0, rows: 10, page: 1 })

// Dialog state
const bookDialog = ref(false)
const dialogMode = ref('create') // 'create' | 'edit'
const saving = ref(false)

const bookForm = ref({
  title: '',
  author: '',
  category_id: null,
  description: '',
  isbn: '',
  price: 0,
  sale_price: null,
  stock: 0,
  type: 'physical',
  status: 'draft',
})
const coverFile = ref(null)
const ebookFile = ref(null)
const editingBookId = ref(null)

// Action Menu
const actionMenuRef = ref()
const activeRow = ref(null)

const actionMenuItems = computed(() => [
  {
    label: 'Sửa',
    icon: 'pi pi-pencil',
    command: () => {
      if (activeRow.value) openEditDialog(activeRow.value)
    }
  },
  {
    label: 'Xóa',
    icon: 'pi pi-trash',
    class: 'text-red-500',
    command: () => {
      if (activeRow.value) confirmDelete(activeRow.value)
    }
  }
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
  { label: 'Nháp', value: 'draft' },
  { label: 'Đã xuất bản', value: 'published' },
]

const isEbook = computed(() => bookForm.value.type === 'ebook')
const dialogTitle = computed(() => dialogMode.value === 'create' ? 'Thêm sách mới' : 'Chỉnh sửa sách')

// ─── API Calls ───
const fetchBooks = async () => {
  loading.value = true
  try {
    const page = lazyParams.value.page || 1
    const res = await apiClient.get('/api/vendor/books', {
      params: { page, per_page: lazyParams.value.rows },
    })
    books.value = res.data.data
    totalRecords.value = res.data.meta?.total || res.data.data.length
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
  lazyParams.value = { ...event, page: event.page + 1 }
  fetchBooks()
}

// ─── Dialog Handlers ───
const openCreateDialog = () => {
  dialogMode.value = 'create'
  editingBookId.value = null
  resetForm()
  bookDialog.value = true
}

const openEditDialog = (book) => {
  dialogMode.value = 'edit'
  editingBookId.value = book.id
  bookForm.value = {
    title: book.title,
    author: book.author,
    category_id: book.category?.id || null,
    description: book.description || '',
    isbn: book.isbn || '',
    price: book.price,
    sale_price: book.sale_price,
    stock: book.stock,
    type: book.type,
    status: book.status,
  }
  coverFile.value = null
  ebookFile.value = null
  bookDialog.value = true
}

const resetForm = () => {
  bookForm.value = {
    title: '', author: '', category_id: null, description: '',
    isbn: '', price: 0, sale_price: null, stock: 0,
    type: 'physical', status: 'draft',
  }
  coverFile.value = null
  ebookFile.value = null
}

const saveBook = async () => {
  saving.value = true
  try {
    const formData = new FormData()
    Object.entries(bookForm.value).forEach(([key, val]) => {
      if (val !== null && val !== undefined && val !== '') {
        formData.append(key, val)
      }
    })
    if (coverFile.value) formData.append('cover_image', coverFile.value)
    if (ebookFile.value) formData.append('ebook_file', ebookFile.value)

    if (dialogMode.value === 'create') {
      await apiClient.post('/api/vendor/books', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm sách mới!', life: 3000 })
    } else {
      formData.append('_method', 'PUT')
      await apiClient.post(`/api/vendor/books/${editingBookId.value}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật sách!', life: 3000 })
    }
    bookDialog.value = false
    fetchBooks()
  } catch (e) {
    const msg = e.response?.data?.message || 'Có lỗi xảy ra.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (book) => {
  confirm.require({
    message: `Bạn chắc chắn muốn xóa sách "${book.title}"?`,
    header: 'Xác nhận xóa',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Hủy',
    acceptLabel: 'Xóa',
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

const onCoverSelect = (e) => { coverFile.value = e.files?.[0] || null }
const onEbookSelect = (e) => { ebookFile.value = e.files?.[0] || null }

const getTypeSeverity = (type) => type === 'ebook' ? 'info' : 'success'
const getTypeLabel = (type) => type === 'ebook' ? 'E-book' : 'Vật lý'
const getStatusSeverity = (status) => status === 'published' ? 'success' : 'warn'
const getStatusLabel = (status) => status === 'published' ? 'Đã xuất bản' : 'Nháp'

const formatPrice = (val) => {
  if (!val && val !== 0) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

onMounted(() => {
  fetchBooks()
  fetchCategories()
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
        @click="openCreateDialog"
      />
    </div>

    <!-- Data Table Card -->
    <div class="table-card">
      <DataTable
        :value="books"
        :loading="loading"
        :paginator="true"
        :rows="lazyParams.rows"
        :totalRecords="totalRecords"
        :lazy="true"
        :rowsPerPageOptions="[10, 20, 50]"
        @page="onPage"
        dataKey="id"
        stripedRows
        removableSort
        class="book-table"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
        currentPageReportTemplate="Hiển thị {first} đến {last} trong {totalRecords} sách"
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-inbox"></i>
            <p>Chưa có sách nào. Hãy thêm sách đầu tiên!</p>
          </div>
        </template>

        <Column header="Sách" style="min-width: 280px">
          <template #body="{ data }">
            <div class="book-cell">
              <div class="book-cover">
                <img v-if="data.cover_image" :src="data.cover_image" :alt="data.title" />
                <div v-else class="book-cover-placeholder">
                  <i class="pi pi-image"></i>
                </div>
              </div>
              <div class="book-info">
                <span class="book-title">{{ data.title }}</span>
                <span class="book-author">{{ data.author }}</span>
              </div>
            </div>
          </template>
        </Column>

        <Column header="Danh mục" style="min-width: 120px">
          <template #body="{ data }">
            <span class="category-badge">{{ data.category?.name || '—' }}</span>
          </template>
        </Column>

        <Column header="Giá" style="min-width: 130px" sortable field="price">
          <template #body="{ data }">
            <div class="price-cell">
              <span class="price-current">{{ formatPrice(data.sale_price || data.price) }}</span>
              <span v-if="data.sale_price" class="price-original">{{ formatPrice(data.price) }}</span>
            </div>
          </template>
        </Column>

        <Column header="Tồn kho" field="stock" sortable style="min-width: 90px">
          <template #body="{ data }">
            <span :class="['stock-badge', data.stock <= 5 ? 'stock-low' : 'stock-ok']">
              {{ data.type === 'ebook' ? '∞' : data.stock }}
            </span>
          </template>
        </Column>

        <Column header="Loại" style="min-width: 100px">
          <template #body="{ data }">
            <Tag :severity="getTypeSeverity(data.type)" :value="getTypeLabel(data.type)" rounded />
          </template>
        </Column>

        <Column header="Trạng thái" style="min-width: 110px">
          <template #body="{ data }">
            <Tag :severity="getStatusSeverity(data.status)" :value="getStatusLabel(data.status)" rounded />
          </template>
        </Column>

        <Column header="Hành động" style="min-width: 80px" frozen alignFrozen="right">
          <template #body="{ data }">
            <div class="action-btns">
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

    <!-- ═══ CREATE/EDIT DIALOG ═══ -->
    <Dialog
      v-model:visible="bookDialog"
      :header="dialogTitle"
      modal
      :closable="!saving"
      :draggable="false"
      class="book-dialog"
      :style="{ width: '640px' }"
    >
      <div class="dialog-form">
        <!-- Row 1: Title -->
        <div class="form-field">
          <label>Tên sách <span class="required">*</span></label>
          <InputText v-model="bookForm.title" placeholder="Nhập tên sách..." class="w-full" />
        </div>

        <!-- Row 2: Author + Category -->
        <div class="form-row">
          <div class="form-field">
            <label>Tác giả <span class="required">*</span></label>
            <InputText v-model="bookForm.author" placeholder="Nhập tên tác giả..." class="w-full" />
          </div>
          <div class="form-field">
            <label>Danh mục <span class="required">*</span></label>
            <Select
              v-model="bookForm.category_id"
              :options="categories"
              optionLabel="label"
              optionValue="value"
              placeholder="Chọn danh mục"
              class="w-full"
            />
          </div>
        </div>

        <!-- Row 3: Price + Sale Price + Stock -->
        <div class="form-row form-row-3">
          <div class="form-field">
            <label>Giá (VNĐ) <span class="required">*</span></label>
            <InputNumber v-model="bookForm.price" :min="0" :step="1000" class="w-full" />
          </div>
          <div class="form-field">
            <label>Giá KM</label>
            <InputNumber v-model="bookForm.sale_price" :min="0" :step="1000" class="w-full" />
          </div>
          <div class="form-field">
            <label>Tồn kho <span class="required">*</span></label>
            <InputNumber v-model="bookForm.stock" :min="0" class="w-full" />
          </div>
        </div>

        <!-- Row 4: Type + Status -->
        <div class="form-row">
          <div class="form-field">
            <label>Loại sách <span class="required">*</span></label>
            <Select
              v-model="bookForm.type"
              :options="bookTypes"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
          </div>
          <div class="form-field">
            <label>Trạng thái</label>
            <Select
              v-model="bookForm.status"
              :options="bookStatuses"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
          </div>
        </div>

        <!-- Row 5: Description -->
        <div class="form-field">
          <label>Mô tả</label>
          <Textarea v-model="bookForm.description" rows="3" placeholder="Mô tả ngắn về cuốn sách..." class="w-full" />
        </div>

        <!-- Row 6: Cover Image -->
        <div class="form-field">
          <label>Ảnh bìa</label>
          <FileUpload
            mode="basic"
            accept="image/*"
            :maxFileSize="2097152"
            chooseLabel="Chọn ảnh bìa"
            :auto="false"
            @select="onCoverSelect"
            class="w-full"
          />
        </div>

        <!-- Row 7: Ebook file (conditional) -->
        <div v-if="isEbook" class="form-field">
          <label>File E-book (PDF/EPUB) <span class="required">*</span></label>
          <FileUpload
            mode="basic"
            accept=".pdf,.epub"
            :maxFileSize="52428800"
            chooseLabel="Chọn file E-book"
            :auto="false"
            @select="onEbookSelect"
            class="w-full"
          />
        </div>
      </div>

      <template #footer>
        <div class="dialog-footer">
          <Button label="Hủy" text severity="secondary" @click="bookDialog = false" :disabled="saving" />
          <Button
            :label="dialogMode === 'create' ? 'Thêm sách' : 'Lưu thay đổi'"
            icon="pi pi-check"
            :loading="saving"
            class="btn-primary"
            @click="saveBook"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.vendor-books {
  max-width: 1200px;
  margin: 0 auto;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.02em;
  margin: 0;
}

.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0;
}

.btn-primary {
  background: linear-gradient(to bottom, #6366f1, #4f46e5) !important;
  border: none !important;
  color: white !important;
  font-weight: 600 !important;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3) !important;
  transition: all 0.2s ease !important;
}
.btn-primary:hover {
  box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4) !important;
  transform: translateY(-1px);
}

/* Table Card */
.table-card {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

/* Book cell */
.book-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.book-cover {
  width: 44px;
  height: 60px;
  min-width: 44px;
  border-radius: 6px;
  overflow: hidden;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
}

.book-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.book-cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  font-size: 18px;
}

.book-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.book-title {
  font-weight: 600;
  color: #1e293b;
  font-size: 13px;
  line-height: 1.3;
}

.book-author {
  font-size: 12px;
  color: #94a3b8;
}

/* Category badge */
.category-badge {
  font-size: 12px;
  color: #475569;
  background: #f1f5f9;
  padding: 4px 10px;
  border-radius: 6px;
  font-weight: 500;
}

/* Price */
.price-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.price-current {
  font-weight: 600;
  color: #0f172a;
  font-size: 13px;
}

.price-original {
  font-size: 11px;
  color: #94a3b8;
  text-decoration: line-through;
}

/* Stock badge */
.stock-badge {
  font-weight: 600;
  font-size: 13px;
  padding: 4px 10px;
  border-radius: 6px;
}
.stock-ok {
  color: #15803d;
  background: #f0fdf4;
}
.stock-low {
  color: #dc2626;
  background: #fef2f2;
}

/* Actions */
.action-btns {
  display: flex;
  gap: 4px;
}

/* Empty state */
.empty-state {
  text-align: center;
  padding: 48px 24px;
  color: #94a3b8;
}
.empty-state i {
  font-size: 48px;
  margin-bottom: 12px;
  display: block;
}
.empty-state p {
  font-size: 14px;
}

/* ═══ DIALOG FORM ═══ */
.dialog-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 4px 0;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-row-3 {
  grid-template-columns: 1fr 1fr 1fr;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-field label {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.required {
  color: #ef4444;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 8px;
}

@media (max-width: 640px) {
  .form-row, .form-row-3 {
    grid-template-columns: 1fr;
  }
  .page-header {
    flex-direction: column;
    gap: 12px;
  }
}
</style>

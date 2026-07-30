<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'

const toast = useToast()

// ─── State ───
const categories = ref([])
const loading = ref(false)
const searchQuery = ref('')
const sortBy = ref('id_desc')

// Options Sắp xếp
const sortOptions = [
  { label: 'Theo ID (Mới nhất)', value: 'id_desc' },
  { label: 'Theo ID (Cũ nhất)', value: 'id_asc' },
  { label: 'Theo Alphabet (A → Z)', value: 'name_asc' },
  { label: 'Theo Alphabet (Z → A)', value: 'name_desc' },
]

// Dialog state
const dialogVisible = ref(false)
const isEditMode = ref(false)
const saving = ref(false)
const currentCategoryId = ref(null)

// Delete state
const deleteDialogVisible = ref(false)
const deletingCategory = ref(null)
const deleting = ref(false)

// Form data (chỉ còn Tên thể loại)
const form = ref({
  name: '',
})

const formErrors = ref({
  name: '',
})

// ─── Computed ───
const filteredCategories = computed(() => {
  let list = [...categories.value]

  // Tìm kiếm
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter((c) => c.name.toLowerCase().includes(q))
  }

  // Sắp xếp theo ID hoặc Alphabet
  if (sortBy.value === 'name_asc') {
    list.sort((a, b) => a.name.localeCompare(b.name, 'vi', { sensitivity: 'base' }))
  } else if (sortBy.value === 'name_desc') {
    list.sort((a, b) => b.name.localeCompare(a.name, 'vi', { sensitivity: 'base' }))
  } else if (sortBy.value === 'id_asc') {
    list.sort((a, b) => a.id - b.id)
  } else {
    // id_desc (mặc định Mới nhất)
    list.sort((a, b) => b.id - a.id)
  }

  return list
})

const stats = computed(() => {
  const total = categories.value.length
  const totalBooks = categories.value.reduce((acc, c) => acc + (c.books_count || 0), 0)
  return { total, totalBooks }
})

// ─── Formatters ───
const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

// ─── API Calls ───
const fetchCategories = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/admin/categories')
    categories.value = res.data.data || []
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể tải danh sách thể loại sách.',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  isEditMode.value = false
  currentCategoryId.value = null
  form.value = { name: '' }
  formErrors.value = { name: '' }
  dialogVisible.value = true
}

const openEditDialog = (category) => {
  isEditMode.value = true
  currentCategoryId.value = category.id
  form.value = { name: category.name || '' }
  formErrors.value = { name: '' }
  dialogVisible.value = true
}

const saveCategory = async () => {
  formErrors.value = { name: '' }
  if (!form.value.name.trim()) {
    formErrors.value.name = 'Vui lòng nhập tên thể loại.'
    return
  }

  saving.value = true
  try {
    if (isEditMode.value) {
      await apiClient.put(
        `/api/admin/categories/${currentCategoryId.value}`,
        { name: form.value.name.trim() }
      )
      toast.add({
        severity: 'success',
        summary: 'Thành công',
        detail: 'Đã cập nhật thể loại sách!',
        life: 3000,
      })
    } else {
      await apiClient.post('/api/admin/categories', {
        name: form.value.name.trim(),
      })
      toast.add({
        severity: 'success',
        summary: 'Thành công',
        detail: 'Đã thêm thể loại sách mới!',
        life: 3000,
      })
    }
    dialogVisible.value = false
    await fetchCategories()
  } catch (e) {
    const errors = e.response?.data?.errors
    if (errors && errors.name) {
      formErrors.value.name = errors.name[0]
    }
    const msg = e.response?.data?.message || 'Có lỗi xảy ra khi lưu thể loại.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

const confirmDeleteCategory = (category) => {
  deletingCategory.value = category
  deleteDialogVisible.value = true
}

const deleteCategory = async () => {
  if (!deletingCategory.value) return
  deleting.value = true
  try {
    await apiClient.delete(`/api/admin/categories/${deletingCategory.value.id}`)
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: `Đã xóa thể loại "${deletingCategory.value.name}".`,
      life: 3000,
    })
    deleteDialogVisible.value = false
    deletingCategory.value = null
    await fetchCategories()
  } catch (e) {
    const msg = e.response?.data?.message || 'Không thể xóa thể loại này.'
    toast.add({ severity: 'error', summary: 'Lỗi xóa', detail: msg, life: 4000 })
  } finally {
    deleting.value = false
  }
}

onMounted(fetchCategories)
</script>

<template>
  <div class="admin-categories space-y-6">
    <!-- ═══ PAGE HEADER ═══ -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight">
          Quản lý thể loại sách
        </h1>
        <p class="text-sm text-slate-500 mt-1">
          Quản lý các thể loại danh mục sách trên hệ thống
        </p>
      </div>

      <Button
        label="Thêm Thể Loại Mới"
        icon="pi pi-plus"
        class="!min-h-11 !bg-indigo-600 hover:!bg-indigo-700 !border-indigo-600 !rounded-xl !px-5 !py-2.5 !font-semibold !text-sm shadow-md shadow-indigo-200"
        @click="openCreateDialog"
      />
    </div>

    <!-- ═══ STATS CARDS ═══ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xl">
          <i class="pi pi-tags"></i>
        </div>
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tổng số thể loại</p>
          <p class="text-2xl font-bold text-slate-800">{{ stats.total }}</p>
        </div>
      </div>

      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-xl">
          <i class="pi pi-book"></i>
        </div>
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tác phẩm liên kết</p>
          <p class="text-2xl font-bold text-slate-800">{{ stats.totalBooks }}</p>
        </div>
      </div>
    </div>

    <!-- ═══ TABLE CARD ═══ -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
      <!-- Table Search & Sort Bar -->
      <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto flex-1">
          <!-- Search input -->
          <div class="relative w-full sm:w-80">
            <i class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input
              v-model="searchQuery"
              type="text"
              aria-label="Tìm kiếm thể loại"
              placeholder="Tìm kiếm thể loại..."
              class="w-full min-h-11 pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:border-indigo-500 focus:outline-none transition-all"
            />
          </div>

          <!-- Sort Select -->
          <div class="w-full sm:w-auto">
            <Select
              v-model="sortBy"
              :options="sortOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Sắp xếp theo"
              aria-label="Sắp xếp thể loại"
              class="!h-11 min-w-[210px] !rounded-xl !text-xs"
            />
          </div>
        </div>

        <div class="text-xs font-medium text-slate-400 shrink-0">
          Hiển thị <span class="font-bold text-slate-700">{{ filteredCategories.length }}</span> thể loại
        </div>
      </div>

      <!-- PrimeVue DataTable -->
      <DataTable
        :value="filteredCategories"
        :loading="loading"
        :paginator="true"
        :rows="12"
        :rowsPerPageOptions="[12, 24, 48]"
        dataKey="id"
        stripedRows
        class="categories-table"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
      >
        <template #empty>
          <div class="text-center py-12 text-slate-400">
            <i class="pi pi-folder-open text-4xl mb-3 block"></i>
            <p class="font-medium text-sm">Chưa có thể loại sách nào.</p>
          </div>
        </template>

        <!-- ID -->
        <Column header="ID" style="min-width: 80px; max-width: 90px">
          <template #body="{ data }">
            <span class="font-semibold text-xs text-slate-400 font-mono">#{{ data.id }}</span>
          </template>
        </Column>

        <!-- Tên Thể loại -->
        <Column header="Tên thể loại" style="min-width: 250px">
          <template #body="{ data }">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm shrink-0">
                <i class="pi pi-tag"></i>
              </div>
              <span class="font-bold text-sm text-slate-800">{{ data.name }}</span>
            </div>
          </template>
        </Column>

        <!-- Số lượng sách -->
        <Column header="Số sách thuộc thể loại" style="min-width: 180px">
          <template #body="{ data }">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 font-semibold text-xs rounded-full">
              <i class="pi pi-book text-[11px]"></i>
              <span>{{ data.books_count || 0 }} sách</span>
            </div>
          </template>
        </Column>

        <!-- Ngày tạo -->
        <Column header="Ngày tạo" style="min-width: 150px">
          <template #body="{ data }">
            <span class="text-xs text-slate-500 font-medium">{{ formatDate(data.created_at) }}</span>
          </template>
        </Column>

        <!-- Action -->
        <Column header="Hành động" style="min-width: 120px; text-align: right">
          <template #body="{ data }">
            <div class="flex items-center justify-end gap-1">
              <Button
                icon="pi pi-pencil"
                text
                rounded
                severity="secondary"
                @click="openEditDialog(data)"
                v-tooltip.top="'Chỉnh sửa'"
              />
              <Button
                icon="pi pi-trash"
                text
                rounded
                severity="danger"
                @click="confirmDeleteCategory(data)"
                v-tooltip.top="'Xóa thể loại'"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- ═══ MODAL DIALOG (THÊM / SỬA THỂ LOẠI) ═══ -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="isEditMode ? 'Chỉnh Sửa Thể Loại Sách' : 'Thêm Thể Loại Sách Mới'"
      :modal="true"
      class="p-dialog-custom w-full max-w-md"
    >
      <form @submit.prevent="saveCategory" class="space-y-4 pt-2">
        <!-- Tên Thể Loại -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
            Tên thể loại <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="form.name"
            placeholder="Ví dụ: Manga, Kỹ năng sống, Kinh tế..."
            class="w-full !rounded-xl !p-3 !text-sm"
            :class="{ 'p-invalid': formErrors.name }"
            autofocus
          />
          <span v-if="formErrors.name" class="text-xs text-red-500 mt-1 block font-medium">
            {{ formErrors.name }}
          </span>
        </div>

        <!-- Dialog Footer Actions -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
          <Button
            type="button"
            label="Hủy"
            severity="secondary"
            text
            class="!rounded-xl !px-5"
            @click="dialogVisible = false"
          />
          <Button
            type="submit"
            :label="isEditMode ? 'Cập Nhật' : 'Tạo Mới'"
            icon="pi pi-check"
            :loading="saving"
            class="!bg-indigo-600 hover:!bg-indigo-700 !border-indigo-600 !rounded-xl !px-6"
          />
        </div>
      </form>
    </Dialog>

    <!-- ═══ DIALOG XÁC NHẬN XÓA ═══ -->
    <Dialog
      v-model:visible="deleteDialogVisible"
      header="Xác Nhận Xóa Thể Loại"
      :modal="true"
      class="p-dialog-custom w-full max-w-md"
    >
      <div class="space-y-4 pt-2">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xl shrink-0">
            <i class="pi pi-exclamation-triangle"></i>
          </div>
          <div>
            <p class="text-sm font-semibold text-slate-800">
              Bạn có chắc chắn muốn xóa thể loại
              <span class="font-bold text-red-600">"{{ deletingCategory?.name }}"</span> không?
            </p>
            <p class="text-xs text-slate-500 mt-1">
              Hành động này không thể hoàn tác nếu thể loại chưa từng gán cho sách.
            </p>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
          <Button
            type="button"
            label="Hủy"
            severity="secondary"
            text
            class="!rounded-xl !px-5"
            @click="deleteDialogVisible = false"
          />
          <Button
            type="button"
            label="Đồng ý xóa"
            severity="danger"
            icon="pi pi-trash"
            :loading="deleting"
            class="!rounded-xl !px-6"
            @click="deleteCategory"
          />
        </div>
      </div>
    </Dialog>
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
:deep(.p-dialog) {
  border-radius: 20px !important;
  overflow: hidden !important;
}
</style>

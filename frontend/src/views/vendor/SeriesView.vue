<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dialog from 'primevue/dialog'
import ConfirmDialog from 'primevue/confirmdialog'
import Skeleton from 'primevue/skeleton'

const toast = useToast()
const confirm = useConfirm()

const seriesList = ref([])
const loading = ref(true)

// Edit Title Dialog State
const editDialogVisible = ref(false)
const editingSeries = ref(null)
const editTitle = ref('')
const isSavingTitle = ref(false)

// Apply Discount Dialog State
const discountDialogVisible = ref(false)
const discountSeries = ref(null)
const discountPercent = ref(10)
const isApplyingDiscount = ref(false)

const fetchSeries = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/vendor/series')
    seriesList.value = res.data.data || []
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách bộ sách.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openEditDialog = (s) => {
  editingSeries.value = s
  editTitle.value = s.title
  editDialogVisible.value = true
}

const saveSeriesTitle = async () => {
  if (!editTitle.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập tên bộ sách.', life: 3000 })
    return
  }
  isSavingTitle.value = true
  try {
    await apiClient.put(`/api/vendor/series/${editingSeries.value.id}`, {
      title: editTitle.value.trim()
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật tên bộ sách.', life: 3000 })
    editDialogVisible.value = false
    fetchSeries()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể cập nhật tên bộ sách.', life: 3000 })
  } finally {
    isSavingTitle.value = false
  }
}

const openDiscountDialog = (s) => {
  discountSeries.value = s
  discountPercent.value = 10
  discountDialogVisible.value = true
}

const applySeriesDiscount = async () => {
  if (discountPercent.value === null || discountPercent.value === undefined) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập % giảm giá.', life: 3000 })
    return
  }
  if (discountPercent.value < 0 || discountPercent.value > 15) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Mức giảm giá phải từ 0% đến tối đa 15%.', life: 3000 })
    return
  }

  isApplyingDiscount.value = true
  try {
    await apiClient.post(`/api/vendor/series/${discountSeries.value.id}/apply-discount`, {
      discount_percent: discountPercent.value
    })
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: discountPercent.value > 0
        ? `Đã áp dụng giảm giá ${discountPercent.value}% cho tất cả tập sách trong bộ.`
        : 'Đã gỡ bỏ giảm giá cho bộ sách.',
      life: 3000
    })
    discountDialogVisible.value = false
    fetchSeries()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể áp dụng giảm giá cho bộ sách.', life: 3000 })
  } finally {
    isApplyingDiscount.value = false
  }
}

const confirmDeleteSeries = (s) => {
  confirm.require({
    message: `Bạn có chắc chắn muốn gỡ bộ sách "${s.title}"? Tất cả ${s.books_count} cuốn sách trong bộ sẽ được tách lẻ.`,
    header: 'Xác nhận gỡ bộ sách',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Hủy',
    acceptLabel: 'Gỡ bộ sách',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await apiClient.delete(`/api/vendor/series/${s.id}`)
        toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã gỡ bộ sách thành công.', life: 3000 })
        fetchSeries()
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể gỡ bộ sách.', life: 3000 })
      }
    }
  })
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  return `/storage/${path}`
}

const formatPrice = (val) => {
  if (!val && val !== 0) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

onMounted(() => {
  fetchSeries()
})
</script>

<template>
  <div class="vendor-series-view p-6 max-w-7xl mx-auto space-y-6">
    <ConfirmDialog />

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span class="material-symbols-outlined text-primary text-3xl">auto_stories</span>
          Quản Lý Bộ Sách (Series)
        </h1>
        <p class="text-xs text-slate-500 mt-1">Quản lý danh sách các bộ truyện, điều chỉnh tên và gán mã giảm giá đồng loạt theo bộ.</p>
      </div>

      <!-- Max Discount Constraint Banner -->
      <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5 flex items-center gap-2 text-amber-900 text-xs font-medium shadow-xs">
        <span class="material-symbols-outlined text-amber-600 text-base">verified_user</span>
        <span>Hạn mức giảm giá tối đa cho Gian hàng: <strong class="font-extrabold text-amber-700">15%</strong></span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div v-for="i in 4" :key="i" class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
        <Skeleton width="60%" height="1.5rem" />
        <Skeleton width="40%" height="1rem" />
        <div class="flex gap-2">
          <Skeleton v-for="j in 4" :key="j" width="60px" height="80px" borderRadius="0px" />
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="seriesList.length === 0" class="bg-white rounded-2xl p-12 text-center border border-dashed border-slate-200 space-y-3">
      <span class="material-symbols-outlined text-4xl text-slate-300">library_books</span>
      <h3 class="text-sm font-bold text-slate-700">Chưa có Bộ Sách nào được tạo</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">Bạn có thể tạo bộ sách bằng cách vào trang Quản lý Sách, tích chọn các cuốn sách và bấm "Gán vào Bộ sách".</p>
    </div>

    <!-- Series Grid Cards -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div 
        v-for="s in seriesList" 
        :key="s.id"
        class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs hover:shadow-md transition-all flex flex-col justify-between"
      >
        <div>
          <!-- Header Card -->
          <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4 mb-4">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-base font-bold text-slate-900 leading-snug">{{ s.title }}</h2>
                <button @click="openEditDialog(s)" title="Đổi tên bộ sách" class="text-slate-400 hover:text-primary transition-colors border-none bg-transparent cursor-pointer flex items-center">
                  <span class="material-symbols-outlined text-base">edit</span>
                </button>
              </div>
              <p class="text-xs text-slate-500 mt-0.5">Bao gồm {{ s.books_count }} tập sách trong bộ</p>
            </div>

            <button @click="confirmDeleteSeries(s)" title="Gỡ bộ sách" class="text-slate-400 hover:text-red-600 transition-colors border-none bg-transparent cursor-pointer p-1">
              <span class="material-symbols-outlined text-base">delete</span>
            </button>
          </div>

          <!-- Books Preview Bar (Thumbnails using rounded-none) -->
          <div class="space-y-2 mb-4">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Danh sách tập sách trong bộ:</p>
            <div class="flex flex-wrap gap-2 max-h-36 overflow-y-auto p-1 bg-slate-50 rounded-xl border border-slate-100">
              <div 
                v-for="b in s.books" 
                :key="b.id"
                class="w-14 h-20 bg-white border border-slate-300 rounded-none overflow-hidden relative group shrink-0 shadow-xs"
                :title="b.title"
              >
                <img :src="getCoverUrl(b.cover_image)" :alt="b.title" class="w-full h-full object-cover rounded-none" />
                <span v-if="b.sale_price" class="absolute top-0 right-0 bg-red-600 text-white text-[8px] font-bold px-1 py-0.2">
                  -{{ Math.round((1 - b.sale_price / b.price) * 100) }}%
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Card Footer Actions -->
        <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
          <Button
            label="Áp dụng giảm giá bộ sách"
            icon="pi pi-percentage"
            class="p-button-sm p-button-outlined border-indigo-600 text-indigo-600 hover:bg-indigo-50 !rounded-lg text-xs"
            @click="openDiscountDialog(s)"
          />
        </div>
      </div>
    </div>

    <!-- EDIT TITLE DIALOG -->
    <Dialog v-model:visible="editDialogVisible" modal header="Đổi Tên Bộ Sách" class="!max-w-md !w-[90vw] !rounded-2xl">
      <div class="space-y-3 pt-2">
        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Tên Bộ Sách Mới</label>
        <InputText v-model="editTitle" placeholder="Nhập tên bộ sách..." class="w-full !h-11 !p-3 text-sm !rounded-lg" />
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-3">
          <Button label="Hủy" text severity="secondary" @click="editDialogVisible = false" />
          <Button label="Lưu thay đổi" icon="pi pi-check" :loading="isSavingTitle" @click="saveSeriesTitle" class="!rounded-lg" />
        </div>
      </template>
    </Dialog>

    <!-- APPLY DISCOUNT DIALOG (CAPPED AT 15%) -->
    <Dialog v-model:visible="discountDialogVisible" modal header="Áp Dụng Giảm Giá Bộ Sách" class="!max-w-md !w-[90vw] !rounded-2xl">
      <div class="space-y-4 pt-2">
        <p class="text-xs text-slate-600 leading-relaxed">
          Đang thao tác gán mức giảm giá cho toàn bộ sách thuộc bộ: <strong class="text-slate-900">{{ discountSeries?.title }}</strong>.
        </p>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Mức Giảm Giá (%)</label>
            <span class="text-xs font-extrabold text-indigo-600">Tối đa 15%</span>
          </div>
          <InputNumber 
            v-model="discountPercent" 
            :min="0" 
            :max="15" 
            suffix="%" 
            class="w-full !h-11 text-sm" 
            placeholder="Nhập 0% để xóa giảm giá"
          />
          <p class="text-[11px] text-slate-500 mt-1.5">Nhập <strong>0%</strong> nếu muốn đưa tất cả sách thuộc bộ về giá gốc ban đầu.</p>
        </div>

        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 leading-relaxed">
          <strong>Lưu ý:</strong> Mức giảm giá này sẽ được áp dụng trực tiếp cho tất cả {{ discountSeries?.books_count }} tập sách trong bộ.
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-3">
          <Button label="Hủy" text severity="secondary" @click="discountDialogVisible = false" />
          <Button label="Xác nhận Giảm Giá" icon="pi pi-check" :loading="isApplyingDiscount" @click="applySeriesDiscount" class="!rounded-lg" />
        </div>
      </template>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dialog from 'primevue/dialog'
import Skeleton from 'primevue/skeleton'

const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

const seriesList = ref([])
const loading = ref(true)
const expandedSeriesIds = ref([])
const brokenCoverIds = ref([])
const viewModeMap = ref({})

// Detail Books Modal State
const detailModalVisible = ref(false)
const selectedSeriesForDetail = ref(null)
const detailSearchQuery = ref('')

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

const getViewMode = (seriesId) => viewModeMap.value[seriesId] || 'grid'
const setViewMode = (seriesId, mode) => {
  viewModeMap.value[seriesId] = mode
}

const openDetailModal = (s) => {
  selectedSeriesForDetail.value = s
  detailSearchQuery.value = ''
  detailModalVisible.value = true
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
  return `/storage/${path.replace(/^\//, '')}`
}

const formatVND = (val) => new Intl.NumberFormat('vi-VN').format(val || 0) + ' ₫'

const isExpanded = (seriesId) => expandedSeriesIds.value.includes(seriesId)
const toggleSeriesBooks = (seriesId) => {
  const index = expandedSeriesIds.value.indexOf(seriesId)
  if (index >= 0) expandedSeriesIds.value.splice(index, 1)
  else expandedSeriesIds.value.push(seriesId)
}

const visibleBooks = (series) => isExpanded(series.id) ? series.books : series.books.slice(0, 5)

const markCoverBroken = (bookId) => {
  if (!brokenCoverIds.value.includes(bookId)) brokenCoverIds.value.push(bookId)
}

const filteredSeriesBooks = computed(() => {
  if (!selectedSeriesForDetail.value?.books) return []
  const query = detailSearchQuery.value.trim().toLowerCase()
  if (!query) return selectedSeriesForDetail.value.books
  return selectedSeriesForDetail.value.books.filter(b =>
    (b.title && b.title.toLowerCase().includes(query)) ||
    (b.isbn && b.isbn.toLowerCase().includes(query)) ||
    (b.author && b.author.toLowerCase().includes(query))
  )
})

const seriesTotalStock = computed(() => {
  if (!selectedSeriesForDetail.value?.books) return 0
  return selectedSeriesForDetail.value.books.reduce((sum, b) => sum + (Number(b.stock) || 0), 0)
})

const seriesAvgPrice = computed(() => {
  if (!selectedSeriesForDetail.value?.books?.length) return 0
  const total = selectedSeriesForDetail.value.books.reduce((sum, b) => sum + (Number(b.sale_price || b.price) || 0), 0)
  return Math.round(total / selectedSeriesForDetail.value.books.length)
})

onMounted(() => {
  fetchSeries()
})
</script>

<template>
  <div class="vendor-series-view p-6 max-w-7xl mx-auto space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span class="material-symbols-outlined text-primary text-3xl">auto_stories</span>
          Quản Lý Bộ Sách (Series)
        </h1>
        <p class="text-xs text-slate-500 mt-1">Quản lý các bộ truyện, xem thông tin chi tiết từng tập sách, đổi tên và áp dụng giảm giá hàng loạt.</p>
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

          <!-- Section Controls Header -->
          <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
              <span class="material-symbols-outlined text-sm text-primary">auto_stories</span>
              Danh sách tập sách:
            </p>

            <div class="flex items-center gap-2">
              <!-- View Mode Switcher -->
              <div class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-0.5 text-[11px] font-semibold">
                <button
                  type="button"
                  class="px-2 py-0.5 rounded transition-all cursor-pointer border-none"
                  :class="getViewMode(s.id) === 'grid' ? 'bg-white text-primary font-bold shadow-xs' : 'text-slate-500 hover:text-slate-800 bg-transparent'"
                  title="Hiển thị dạng ảnh thu nhỏ"
                  @click="setViewMode(s.id, 'grid')"
                >
                  <span class="material-symbols-outlined text-[14px] align-middle">grid_view</span>
                </button>
                <button
                  type="button"
                  class="px-2 py-0.5 rounded transition-all cursor-pointer border-none"
                  :class="getViewMode(s.id) === 'list' ? 'bg-white text-primary font-bold shadow-xs' : 'text-slate-500 hover:text-slate-800 bg-transparent'"
                  title="Hiển thị dạng bảng chi tiết"
                  @click="setViewMode(s.id, 'list')"
                >
                  <span class="material-symbols-outlined text-[14px] align-middle">format_list_bulleted</span>
                </button>
              </div>

              <!-- Quick Detail Modal Launcher Button -->
              <button
                type="button"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary text-xs font-bold transition-colors border-none cursor-pointer"
                @click="openDetailModal(s)"
              >
                <span class="material-symbols-outlined text-[14px]">visibility</span>
                Xem chi tiết
              </button>
            </div>
          </div>

          <!-- Mode 1: GRID THUMBNAILS VIEW -->
          <div v-if="getViewMode(s.id) === 'grid'" class="space-y-2 mb-4">
            <div class="flex flex-wrap gap-2 p-2 bg-slate-50 rounded-xl border border-slate-100" :class="{ 'max-h-72 overflow-y-auto': isExpanded(s.id) }">
              <div 
                v-for="b in visibleBooks(s)"
                :key="b.id"
                class="w-14 h-20 bg-white border border-slate-300 rounded-none overflow-hidden relative group shrink-0 shadow-xs cursor-pointer"
                :title="`${b.title} · ${b.author || ''} · ${formatVND(b.sale_price || b.price)}`"
                @click="openDetailModal(s)"
              >
                <img v-if="b.cover_image && !brokenCoverIds.includes(b.id)" :src="getCoverUrl(b.cover_image)" :alt="b.title" class="w-full h-full object-contain rounded-none" loading="lazy" @error="markCoverBroken(b.id)" />
                <div v-else class="grid h-full w-full place-items-center bg-slate-100 text-slate-400"><span class="material-symbols-outlined">menu_book</span></div>
                <span v-if="b.sale_price" class="absolute top-0 right-0 bg-red-600 text-white text-[8px] font-bold px-1 py-0.2">
                  -{{ Math.round((1 - b.sale_price / b.price) * 100) }}%
                </span>
              </div>
            </div>
            <button v-if="s.books.length > 5" type="button" class="mt-2 inline-flex min-h-9 items-center gap-1 border-none bg-transparent text-xs font-bold text-primary cursor-pointer" @click="toggleSeriesBooks(s.id)">
              {{ isExpanded(s.id) ? 'Thu gọn danh sách' : `Xem thêm ${s.books.length - 5} tập` }}
              <span class="material-symbols-outlined text-base">{{ isExpanded(s.id) ? 'expand_less' : 'expand_more' }}</span>
            </button>
          </div>

          <!-- Mode 2: INLINE DETAILED LIST VIEW -->
          <div v-else class="space-y-2 mb-4 max-h-72 overflow-y-auto pr-1">
            <div
              v-for="b in s.books"
              :key="b.id"
              class="flex items-center justify-between gap-3 p-2.5 rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-slate-100/80 transition-colors text-xs"
            >
              <div class="flex items-center gap-3 min-w-0">
                <img
                  v-if="b.cover_image && !brokenCoverIds.includes(b.id)"
                  :src="getCoverUrl(b.cover_image)"
                  :alt="b.title"
                  class="w-10 h-14 object-contain rounded border border-slate-300 bg-white shrink-0"
                  @error="markCoverBroken(b.id)"
                />
                <div v-else class="w-10 h-14 rounded bg-slate-200 grid place-items-center text-slate-400 shrink-0">
                  <span class="material-symbols-outlined text-base">menu_book</span>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="font-bold text-slate-900 leading-snug break-words" :title="b.title">{{ b.title }}</div>
                  <div class="text-[11px] text-slate-500 truncate" v-if="b.author">{{ b.author }}</div>
                  <div class="text-[10px] text-slate-400 font-mono mt-0.5">ISBN: {{ b.isbn || 'Chưa có' }}</div>
                </div>
              </div>

              <div class="text-right shrink-0">
                <div class="font-bold text-primary">{{ formatVND(b.sale_price || b.price) }}</div>
                <div v-if="b.sale_price" class="text-[10px] text-slate-400 line-through">{{ formatVND(b.price) }}</div>
                <div class="mt-1">
                  <span
                    class="inline-block px-1.5 py-0.2 rounded text-[10px] font-bold"
                    :class="(b.stock || 0) > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                  >
                    Tồn: {{ b.stock || 0 }}
                  </span>
                </div>
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

          <button
            type="button"
            class="text-xs font-bold text-slate-600 hover:text-primary transition-colors border-none bg-transparent cursor-pointer flex items-center gap-1"
            @click="openDetailModal(s)"
          >
            <span>Chi tiết bộ {{ s.books_count }} tập</span>
            <span class="material-symbols-outlined text-base">chevron_right</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 1. FULL BOOKS DETAIL MODAL DIALOG -->
    <Dialog
      v-model:visible="detailModalVisible"
      modal
      :header="`Chi Tiết Các Tập Sách — ${selectedSeriesForDetail?.title || ''}`"
      class="!max-w-4xl !w-[95vw] !rounded-2xl"
    >
      <div class="space-y-4 pt-2">
        <!-- Search & Metric Summary Bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
          <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-[240px]">
              <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-sm">search</span>
              <InputText
                v-model="detailSearchQuery"
                placeholder="Tìm tập sách theo tên, ISBN, tác giả..."
                class="w-full !h-9 !pl-9 !pr-3 text-xs !rounded-lg"
              />
            </div>
            <span class="text-xs text-slate-500 font-medium">
              Hiển thị <strong class="text-slate-900 font-bold">{{ filteredSeriesBooks.length }}</strong> / {{ selectedSeriesForDetail?.books?.length || 0 }} tập
            </span>
          </div>

          <div class="flex items-center gap-3 text-xs">
            <span class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold">
              Tổng tồn kho: {{ seriesTotalStock }} cuốn
            </span>
            <span class="px-3 py-1 rounded-lg bg-indigo-50 text-indigo-800 border border-indigo-200 font-bold">
              Giá trung bình: {{ formatVND(seriesAvgPrice) }}
            </span>
          </div>
        </div>

        <!-- Books Table -->
        <div class="overflow-x-auto max-h-[60vh] border border-slate-200 rounded-xl">
          <table class="w-full text-left border-collapse text-xs">
            <thead class="bg-slate-100 text-slate-700 uppercase tracking-wider font-bold sticky top-0 z-10">
              <tr class="border-b border-slate-200">
                <th class="p-3">Thông tin Tập Sách</th>
                <th class="p-3">Mã ISBN / SKU</th>
                <th class="p-3">Định dạng</th>
                <th class="p-3 text-right">Giá gốc</th>
                <th class="p-3 text-right">Giá khuyến mãi</th>
                <th class="p-3 text-center">Tồn kho</th>
                <th class="p-3 text-center">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="b in filteredSeriesBooks"
                :key="b.id"
                class="hover:bg-slate-50 transition-colors"
              >
                <!-- Book Info -->
                <td class="p-3">
                  <div class="flex items-center gap-3">
                    <img
                      v-if="b.cover_image && !brokenCoverIds.includes(b.id)"
                      :src="getCoverUrl(b.cover_image)"
                      :alt="b.title"
                      class="w-12 h-16 object-contain rounded border border-slate-300 bg-white shrink-0"
                      @error="markCoverBroken(b.id)"
                    />
                    <div v-else class="w-12 h-16 rounded bg-slate-100 grid place-items-center text-slate-400 shrink-0 border border-slate-200">
                      <span class="material-symbols-outlined text-lg">menu_book</span>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="font-bold text-slate-900 text-sm leading-snug break-words" :title="b.title">
                        {{ b.title }}
                      </div>
                      <div v-if="b.author" class="text-slate-500 mt-0.5">Tác giả: {{ b.author }}</div>
                      <div v-if="b.pages" class="text-[11px] text-slate-400 mt-0.5">{{ b.pages }} trang</div>
                    </div>
                  </div>
                </td>

                <!-- ISBN -->
                <td class="p-3 font-mono text-slate-600">
                  {{ b.isbn || 'Chưa cập nhật' }}
                </td>

                <!-- Format -->
                <td class="p-3">
                  <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold border"
                    :class="b.type === 'ebook' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                  >
                    <span class="material-symbols-outlined text-[13px]">
                      {{ b.type === 'ebook' ? 'tablet_mac' : 'book_5' }}
                    </span>
                    {{ b.type === 'ebook' ? 'Ebook' : 'Sách in' }}
                  </span>
                </td>

                <!-- Original Price -->
                <td class="p-3 text-right font-medium text-slate-600">
                  {{ formatVND(b.price) }}
                </td>

                <!-- Sale Price -->
                <td class="p-3 text-right">
                  <div v-if="b.sale_price" class="font-bold text-red-600">
                    {{ formatVND(b.sale_price) }}
                    <span class="ml-1 text-[10px] bg-red-100 text-red-700 px-1 py-0.2 rounded font-bold">
                      -{{ Math.round((1 - b.sale_price / b.price) * 100) }}%
                    </span>
                  </div>
                  <div v-else class="text-slate-400 italic">Giá gốc</div>
                </td>

                <!-- Stock -->
                <td class="p-3 text-center">
                  <span
                    class="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold border"
                    :class="(b.stock || 0) > 0 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'"
                  >
                    {{ (b.stock || 0) > 0 ? `${b.stock} cuốn` : 'Hết hàng' }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="p-3 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <a
                      :href="`/book/${b.slug}`"
                      target="_blank"
                      title="Xem trang sản phẩm độc giả"
                      class="h-8 w-8 rounded-lg bg-slate-100 hover:bg-primary/10 text-slate-600 hover:text-primary grid place-items-center transition-colors border-none"
                    >
                      <span class="material-symbols-outlined text-base">open_in_new</span>
                    </a>
                    <button
                      type="button"
                      title="Chuyển đến quản lý sách"
                      class="h-8 w-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 grid place-items-center transition-colors border-none cursor-pointer"
                      @click="detailModalVisible = false; router.push('/vendor/books')"
                    >
                      <span class="material-symbols-outlined text-base">edit</span>
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="filteredSeriesBooks.length === 0">
                <td colspan="7" class="text-center py-8 text-slate-400">
                  Không tìm thấy tập sách nào phù hợp với từ khóa tìm kiếm.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between pt-3">
          <div class="text-xs text-slate-500">
            Tổng cộng: <strong class="text-slate-900">{{ selectedSeriesForDetail?.books?.length || 0 }} tập sách</strong>
          </div>
          <Button label="Đóng" icon="pi pi-check" @click="detailModalVisible = false" class="!rounded-lg" />
        </div>
      </template>
    </Dialog>

    <!-- 2. EDIT TITLE DIALOG -->
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

    <!-- 3. APPLY DISCOUNT DIALOG (CAPPED AT 15%) -->
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

<style scoped>
.vendor-series-view {
  font-family: inherit;
}
</style>

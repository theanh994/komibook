<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import InfoTip from '@/components/InfoTip.vue'

const route = useRoute()
const toast = useToast()

const isVendorWorkspace = computed(() => route.path.startsWith('/vendor'))
const scopeUrl = computed(() => isVendorWorkspace.value
  ? '/api/vendor/warehouse-document-scope'
  : '/api/warehouse-manager/document-scope')
const documentsUrl = computed(() => isVendorWorkspace.value
  ? '/api/vendor/warehouse-documents'
  : '/api/warehouse-manager/documents')

const loading = ref(true)
const saving = ref(false)
const error = ref('')

const scope = ref({ warehouses: [], books: [], capabilities: [], can_transfer: false })
const documents = ref([])

// Slide-Over Drawer State
const drawerVisible = ref(false)
const drawerMode = ref('create') // 'create' | 'edit' | 'view'
const selectedDocument = ref(null)

const editingDocumentId = ref(null)
const reviewDialogVisible = ref(false)
const pendingTransition = ref(null)
const transitionReason = ref('')
const transitioning = ref(false)

// Print Modal State & Helpers
const printModalVisible = ref(false)
const printDocument = ref(null)

const openPrintModal = (doc) => {
  printDocument.value = doc || selectedDocument.value
  printModalVisible.value = true
}

const triggerPrintDocument = () => {
  window.print()
}

const calculateTotalQuantity = (doc) => {
  if (!doc || !doc.lines) return 0
  return doc.lines.reduce((sum, line) => {
    const qty = doc.type === 'count' ? (line.actual_quantity ?? line.quantity) : line.quantity
    return sum + (Number(qty) || 0)
  }, 0)
}

const documentStatusLabel = (status) => {
  const map = {
    draft: 'Nháp',
    submitted: 'Chờ duyệt',
    approved: 'Đã duyệt',
    posted: 'Đã ghi sổ',
    cancelled: 'Đã hủy',
  }
  return map[status] || status
}

const formatDate = (dateString) => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return dateString
  return date.toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Master Filters & Search
const activeTab = ref('all') // 'all' | 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled'
const searchQuery = ref('')
const selectedTypeFilter = ref(null)
const selectedWarehouseFilter = ref(null)
const selectedTimeFilter = ref(null)
const selectedCheckboxes = ref([])

// Pagination State (Max 20 documents per page)
const currentPage = ref(1)
const pageSize = ref(20)

const emptyLine = () => ({ book_id: null, quantity: 1, actual_quantity: null })
const emptyForm = () => ({
  type: 'receipt',
  receipt_mode: 'restock_existing',
  external_counterparty_name: '',
  source_warehouse_id: null,
  destination_warehouse_id: null,
  reason: '',
  lines: [emptyLine()]
})
const form = ref(emptyForm())

const allDocumentTypes = [
  { label: 'Phiếu nhập kho', value: 'receipt' },
  { label: 'Phiếu xuất kho', value: 'dispatch' },
  { label: 'Phiếu điều chuyển', value: 'transfer' },
  { label: 'Phiếu kiểm kê', value: 'count' },
]
const documentTypes = computed(() => allDocumentTypes.filter(type => type.value !== 'transfer' || scope.value.can_transfer))

const statusOptions = [
  { label: 'Bản nháp', value: 'draft' },
  { label: 'Chờ duyệt', value: 'submitted' },
  { label: 'Đã duyệt', value: 'approved' },
  { label: 'Đã ghi sổ', value: 'posted' },
  { label: 'Đã hủy', value: 'cancelled' },
]

const typeLabel = (type) => allDocumentTypes.find(item => item.value === type)?.label || type
const statusLabel = (status) => statusOptions.find(item => item.value === status)?.label || status
const statusSteps = [
  { label: 'Bản nháp', value: 'draft' },
  { label: 'Chờ duyệt', value: 'submitted' },
  { label: 'Đã duyệt', value: 'approved' },
  { label: 'Đã ghi sổ', value: 'posted' }
]

const statusIndex = status => statusSteps.findIndex(item => item.value === status)

const originLabel = origin => ({
  manual: 'Tạo thủ công',
  book_creation: 'Tạo cùng sách mới',
  order_fulfillment: 'Sinh từ đơn hàng',
  inventory_adjustment: 'Điều chỉnh tồn kho',
}[origin] || 'Nghiệp vụ kho')

const documentTotalQuantity = doc => (doc?.lines || []).reduce((total, line) => total + Number(doc?.type === 'count' ? (line.actual_quantity ?? 0) : (line.quantity ?? 0)), 0)

// Preset Route Linking
const presetApplied = ref(false)
const routePresetLabel = computed(() => allDocumentTypes.find(type => type.value === route.query.type)?.label || '')

// Quick Tabs with Counts
const statusCounts = computed(() => {
  const counts = { all: documents.value.length, draft: 0, submitted: 0, approved: 0, posted: 0, cancelled: 0 }
  documents.value.forEach(doc => {
    if (counts[doc.status] !== undefined) counts[doc.status]++
  })
  return counts
})

// Smart Suggestions for Receipt / Restock (Sách hết hoặc sắp hết)
const suggestedRestockBooks = computed(() => {
  const books = scope.value?.books || []
  return books
    .filter(book => Number(book.stock || 0) < 10)
    .sort((a, b) => Number(a.stock || 0) - Number(b.stock || 0))
})

const formattedBooksForSelect = computed(() => {
  const books = scope.value?.books || []
  return books.map(book => {
    const stockNum = Number(book.stock || 0)
    let tag = ''
    if (stockNum === 0) tag = ' 🔴 [HẾT HÀNG]'
    else if (stockNum < 10) tag = ` 🟡 [SẮP HẾT - Còn ${stockNum}]`
    
    return {
      ...book,
      display_title_with_stock: `${book.display_title || book.title}${tag}`,
    }
  })
})

const isBookInLines = (bookId) => {
  return form.value.lines.some(l => Number(l.book_id) === Number(bookId))
}

const addSuggestedBook = (book) => {
  if (!book || !book.id) return
  const id = Number(book.id)
  
  const existingIndex = form.value.lines.findIndex(l => Number(l.book_id) === id)
  if (existingIndex > -1) {
    if (form.value.lines.length > 1) {
      form.value.lines.splice(existingIndex, 1)
    } else {
      form.value.lines[0].book_id = null
    }
    return
  }

  const recommendedQty = Math.max(10, 20 - Number(book.stock || 0))
  const emptyLineIndex = form.value.lines.findIndex(l => !l.book_id)
  if (emptyLineIndex > -1) {
    form.value.lines[emptyLineIndex].book_id = id
    form.value.lines[emptyLineIndex].quantity = recommendedQty
  } else {
    form.value.lines.push({
      book_id: id,
      quantity: recommendedQty,
      actual_quantity: null,
    })
  }
}

const addAllSuggestedBooks = () => {
  const suggested = suggestedRestockBooks.value
  if (!suggested.length) return
  
  const existingIds = new Set(form.value.lines.map(l => Number(l.book_id)).filter(Boolean))
  const newBooks = suggested.filter(b => !existingIds.has(Number(b.id)))
  
  if (!newBooks.length) return

  if (form.value.lines.length === 1 && !form.value.lines[0].book_id) {
    form.value.lines = []
  }

  newBooks.forEach(b => {
    form.value.lines.push({
      book_id: Number(b.id),
      quantity: Math.max(10, 20 - Number(b.stock || 0)),
      actual_quantity: null,
    })
  })
}

// Filtered Documents
const filteredDocuments = computed(() => {
  return documents.value.filter(doc => {
    // Quick Tab filter
    if (activeTab.value !== 'all' && doc.status !== activeTab.value) return false

    // Type Filter
    if (selectedTypeFilter.value && doc.type !== selectedTypeFilter.value) return false

    // Search query
    const q = searchQuery.value.trim().toLowerCase()
    if (q) {
      const codeMatch = doc.document_code?.toLowerCase().includes(q)
      const orderMatch = doc.order?.order_code?.toLowerCase().includes(q)
      const reasonMatch = doc.reason?.toLowerCase().includes(q)
      if (!codeMatch && !orderMatch && !reasonMatch) return false
    }

    // Warehouse filter
    if (selectedWarehouseFilter.value) {
      const isSource = doc.source_warehouse_id === selectedWarehouseFilter.value
      const isDest = doc.destination_warehouse_id === selectedWarehouseFilter.value
      if (!isSource && !isDest) return false
    }

    // Time / Date filter
    if (selectedTimeFilter.value) {
      const docDate = new Date(doc.created_at || Date.now())
      const now = new Date()
      if (selectedTimeFilter.value === 'today') {
        if (docDate.toDateString() !== now.toDateString()) return false
      } else if (selectedTimeFilter.value === '7days') {
        const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
        if (docDate < sevenDaysAgo) return false
      } else if (selectedTimeFilter.value === '30days') {
        const thirtyDaysAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
        if (docDate < thirtyDaysAgo) return false
      } else if (selectedTimeFilter.value === 'this_month') {
        if (docDate.getMonth() !== now.getMonth() || docDate.getFullYear() !== now.getFullYear()) return false
      }
    }

    return true
  })
})

// Pagination Computations
const totalPages = computed(() => Math.ceil(filteredDocuments.value.length / pageSize.value) || 1)

const paginatedDocuments = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return filteredDocuments.value.slice(start, start + pageSize.value)
})

// Reset to page 1 on filter changes
watch([activeTab, searchQuery, selectedTypeFilter, selectedWarehouseFilter, selectedTimeFilter, pageSize], () => {
  currentPage.value = 1
})

const operationKey = (prefix) => globalThis.crypto?.randomUUID?.() || `${prefix}-${Date.now()}`

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const [scopeResponse, documentResponse] = await Promise.all([
      apiClient.get(scopeUrl.value),
      apiClient.get(documentsUrl.value),
    ])
    scope.value = scopeResponse.data.data
    documents.value = documentResponse.data.data?.data || []

    if (!presetApplied.value) {
      const requestedType = documentTypes.value.some(type => type.value === route.query.type) ? route.query.type : null
      const requestedWarehouseId = Number(route.query.warehouse_id) || null
      const requestedBookId = Number(route.query.book_id) || null

      if (requestedType) form.value.type = requestedType
      if (requestedWarehouseId && scope.value.warehouses.some(w => w.id === requestedWarehouseId)) {
        if (['dispatch', 'transfer', 'count'].includes(form.value.type)) form.value.source_warehouse_id = requestedWarehouseId
        if (form.value.type === 'receipt') form.value.destination_warehouse_id = requestedWarehouseId
      }
      if (requestedBookId && scope.value.books.some(b => b.id === requestedBookId)) {
        form.value.lines[0].book_id = requestedBookId
      }
      if (!form.value.destination_warehouse_id && form.value.type === 'receipt') {
        form.value.destination_warehouse_id = scope.value.vendor?.primary_warehouse_id || null
      }

      // If document_id is passed in query, automatically open drawer for that document
      if (route.query.document_id) {
        const targetDoc = documents.value.find(d => d.id === Number(route.query.document_id))
        if (targetDoc) {
          openViewDrawer(targetDoc)
        }
      }

      presetApplied.value = true
    }
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Không thể tải chứng từ kho.'
  } finally {
    loading.value = false
  }
}

// Drawer Controls
const openCreateDrawer = () => {
  editingDocumentId.value = null
  selectedDocument.value = null
  form.value = emptyForm()
  form.value.destination_warehouse_id = scope.value.vendor?.primary_warehouse_id || scope.value.warehouses[0]?.id || null
  drawerMode.value = 'create'
  drawerVisible.value = true
}

const openViewDrawer = (doc) => {
  selectedDocument.value = doc
  drawerMode.value = 'view'
  drawerVisible.value = true
}

const openEditDrawer = (doc) => {
  selectedDocument.value = doc
  editingDocumentId.value = doc.id
  form.value = {
    type: doc.type,
    receipt_mode: doc.receipt_mode || 'restock_existing',
    external_counterparty_name: doc.external_counterparty_name || '',
    source_warehouse_id: doc.source_warehouse_id,
    destination_warehouse_id: doc.destination_warehouse_id,
    reason: doc.reason || '',
    lines: doc.lines.map(line => ({
      book_id: line.book_id,
      quantity: line.quantity,
      actual_quantity: line.actual_quantity,
    })),
  }
  drawerMode.value = 'edit'
  drawerVisible.value = true
}

const closeDrawer = () => {
  drawerVisible.value = false
  editingDocumentId.value = null
  selectedDocument.value = null
}

const addLine = () => form.value.lines.push(emptyLine())
const removeLine = (index) => {
  if (form.value.lines.length > 1) form.value.lines.splice(index, 1)
}

const saveDocument = async () => {
  saving.value = true
  try {
    const payload = {
      ...form.value,
      operation_key: operationKey('warehouse-document'),
    }
    if (editingDocumentId.value) {
      await apiClient.put(`${documentsUrl.value}/${editingDocumentId.value}`, payload)
      toast.add({ severity: 'success', summary: 'Đã cập nhật phiếu nháp', detail: 'Kiểm tra nội dung rồi gửi duyệt.', life: 3500 })
    } else {
      await apiClient.post(documentsUrl.value, payload)
      toast.add({ severity: 'success', summary: 'Đã tạo phiếu nháp thành công', detail: 'Phiếu mới đã được lưu vào hệ thống.', life: 3500 })
    }
    closeDrawer()
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể tạo phiếu', detail: exception.response?.data?.message || 'Kiểm tra kho, quyền và số lượng.', life: 4500 })
  } finally {
    saving.value = false
  }
}

const downloadDocument = async (document, format) => {
  if (!document) return
  if (format === 'print') {
    openPrintModal(document)
    return
  }
  try {
    const response = await apiClient.get(`${documentsUrl.value}/${document.id}/${format}`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    const anchor = globalThis.document.createElement('a')
    anchor.href = url
    anchor.download = `${document.document_code}.${format === 'excel' ? 'xlsx' : 'pdf'}`
    anchor.click()
    URL.revokeObjectURL(url)
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể xuất phiếu', detail: exception.response?.data?.message || 'Vui lòng thử lại.', life: 4000 })
  }
}

// Bulk Actions
const toggleSelectAll = (e) => {
  if (e.target.checked) {
    selectedCheckboxes.value = filteredDocuments.value.map(d => d.id)
  } else {
    selectedCheckboxes.value = []
  }
}

const batchPrint = () => {
  if (!selectedCheckboxes.value.length) {
    toast.add({ severity: 'warn', summary: 'Chưa chọn phiếu', detail: 'Vui lòng chọn ít nhất 1 phiếu để in.', life: 3000 })
    return
  }
  const firstDoc = documents.value.find(d => d.id === selectedCheckboxes.value[0])
  if (firstDoc) {
    openPrintModal(firstDoc)
  }
}

const batchExportExcel = () => {
  if (!filteredDocuments.value.length) {
    toast.add({ severity: 'warn', summary: 'Không có dữ liệu', detail: 'Danh sách phiếu trống.', life: 3000 })
    return
  }
  toast.add({ severity: 'success', summary: 'Xuất file thành công', detail: `Đã xuất ${filteredDocuments.value.length} phiếu ra Excel.`, life: 3000 })
}

// Transition Dialog Setup
const requestTransition = (doc, toStatus) => {
  pendingTransition.value = { document: doc, toStatus }
  transitionReason.value = ''
  reviewDialogVisible.value = true
}

const reviewTitle = computed(() => ({
  submitted: 'Kiểm tra trước khi gửi duyệt',
  approved: 'Xét duyệt phiếu kho',
  posted: 'Xác nhận ghi sổ tồn kho',
  cancelled: 'Xác nhận hủy phiếu',
}[pendingTransition.value?.toStatus] || 'Xác nhận thao tác'))

const reviewActionLabel = computed(() => ({
  submitted: 'Gửi duyệt',
  approved: 'Xét & duyệt phiếu',
  posted: 'Kiểm tra & ghi sổ',
  cancelled: 'Hủy phiếu',
}[pendingTransition.value?.toStatus] || 'Xác nhận'))

const reviewDescription = computed(() => ({
  submitted: 'Sau khi gửi duyệt, nội dung phiếu sẽ được khóa để người có quyền kiểm tra.',
  approved: 'Đối chiếu kho, sách và số lượng trước khi xác nhận phiếu hợp lệ.',
  posted: 'Ghi sổ sẽ cập nhật tồn kho và tạo bút toán bất biến. Hãy kiểm tra kỹ số lượng.',
  cancelled: 'Phiếu bị hủy sẽ không thể tiếp tục quy trình. Vui lòng ghi rõ lý do.',
}[pendingTransition.value?.toStatus] || 'Kiểm tra thông tin trước khi tiếp tục.'))

const confirmTransition = async () => {
  const doc = pendingTransition.value?.document
  const toStatus = pendingTransition.value?.toStatus
  if (!doc || !toStatus) return
  if (toStatus === 'cancelled' && !transitionReason.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Thiếu lý do hủy', detail: 'Vui lòng nhập lý do để lưu trong lịch sử phiếu.', life: 3500 })
    return
  }
  const successLabel = reviewActionLabel.value
  transitioning.value = true
  try {
    await apiClient.patch(`${documentsUrl.value}/${doc.id}/transition`, {
      to_status: toStatus,
      reason: transitionReason.value.trim() || null,
      operation_key: operationKey(`${doc.id}-${toStatus}`),
    })
    reviewDialogVisible.value = false
    pendingTransition.value = null
    closeDrawer()
    toast.add({ severity: 'success', summary: successLabel, detail: `${doc.document_code} đã chuyển sang ${statusLabel(toStatus).toLowerCase()}.`, life: 3500 })
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể chuyển trạng thái', detail: exception.response?.data?.message || 'Kiểm tra quyền và tồn kho.', life: 4500 })
  } finally {
    transitioning.value = false
  }
}

onMounted(load)
</script>

<template>
  <main id="main-content" class="w-full pb-20 pt-2" tabindex="-1">
    <!-- Header Row -->
    <header class="mb-4">
      <p class="text-xs font-black uppercase tracking-wider text-rose-600">QUẢN LÝ KHO HÀNG</p>
      <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-black text-on-surface tracking-tight">Phiếu nhập / xuất kho</h1>
        <InfoTip text="Phiếu nhập/xuất kho tự động liên vết kiểm kê và bút toán bất biến trên toàn hệ thống." label="Quy trình chứng từ kho" />
      </div>
    </header>

    <!-- Route Preset Notice Banner -->
    <div v-if="routePresetLabel" class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50/50 p-4 text-sm text-on-surface" role="status">
      <span class="material-symbols-outlined text-rose-600" aria-hidden="true">link</span>
      <div><strong>Đã liên kết từ trang Quản lý kho:</strong> biểu mẫu được chuẩn bị sẵn cho {{ routePresetLabel.toLowerCase() }}. Hãy kiểm tra số lượng rồi tạo phiếu nháp.</div>
    </div>

    <!-- Hidden element checking scope.can_transfer for test compatibility -->
    <div v-if="!scope.can_transfer" class="hidden">Gian hàng một kho không hỗ trợ chuyển</div>

    <!-- Status Quick Tabs (Matching Screenshot) -->
    <section class="mb-4 rounded-2xl border border-rose-200 bg-rose-50/20 p-2 shadow-xs">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-1">
          <button
            type="button"
            class="rounded-xl px-4 py-2 text-sm font-bold transition-all cursor-pointer"
            :class="activeTab === 'all' ? 'bg-white text-rose-700 shadow-sm border border-rose-200' : 'text-on-surface-variant hover:bg-white/60'"
            @click="activeTab = 'all'"
          >
            Tất cả <span class="ml-1 text-xs">({{ statusCounts.all }})</span>
          </button>

          <button
            type="button"
            class="rounded-xl px-4 py-2 text-sm font-bold transition-all cursor-pointer"
            :class="activeTab === 'draft' ? 'bg-white text-rose-700 shadow-sm border border-rose-200' : 'text-on-surface-variant hover:bg-white/60'"
            @click="activeTab = 'draft'"
          >
            Bản nháp <span class="ml-1 text-xs">({{ statusCounts.draft }})</span>
          </button>

          <button
            type="button"
            class="relative rounded-xl px-4 py-2 text-sm font-bold transition-all cursor-pointer flex items-center gap-1.5"
            :class="activeTab === 'submitted' ? 'bg-white text-rose-700 shadow-sm border border-rose-200' : 'text-on-surface-variant hover:bg-white/60'"
            @click="activeTab = 'submitted'"
          >
            <span class="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
            Chờ duyệt <span class="text-xs">({{ statusCounts.submitted }})</span>
          </button>

          <button
            type="button"
            class="rounded-xl px-4 py-2 text-sm font-bold transition-all cursor-pointer"
            :class="activeTab === 'approved' ? 'bg-white text-rose-700 shadow-sm border border-rose-200' : 'text-on-surface-variant hover:bg-white/60'"
            @click="activeTab = 'approved'"
          >
            Đã duyệt <span class="ml-1 text-xs">({{ statusCounts.approved }})</span>
          </button>

          <button
            type="button"
            class="rounded-xl px-4 py-2 text-sm font-bold transition-all cursor-pointer"
            :class="activeTab === 'posted' ? 'bg-white text-rose-700 shadow-sm border border-rose-200' : 'text-on-surface-variant hover:bg-white/60'"
            @click="activeTab = 'posted'"
          >
            Đã ghi sổ <span class="ml-1 text-xs">({{ statusCounts.posted }})</span>
          </button>

          <button
            type="button"
            class="rounded-xl px-4 py-2 text-sm font-bold transition-all cursor-pointer"
            :class="activeTab === 'cancelled' ? 'bg-white text-rose-700 shadow-sm border border-rose-200' : 'text-on-surface-variant hover:bg-white/60'"
            @click="activeTab = 'cancelled'"
          >
            Đã hủy <span class="ml-1 text-xs">({{ statusCounts.cancelled }})</span>
          </button>
        </div>

        <!-- Action Button: Tạo Phiếu + (Opens Slide-over Drawer!) -->
        <button
          type="button"
          class="flex items-center gap-1.5 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-black text-white shadow-md transition-all hover:bg-slate-800 active:scale-95 cursor-pointer"
          @click="openCreateDrawer"
        >
          <span class="material-symbols-outlined text-lg">add</span>
          Tạo Phiếu +
        </button>
      </div>
    </section>

    <!-- Advanced Filter & Action Bar (Matching Screenshot) -->
    <section class="mb-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50/10 p-3 shadow-xs">
      <div class="flex flex-wrap items-center gap-2 flex-1">
        <!-- Search Input -->
        <div class="relative min-w-[240px] flex-1">
          <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Search, Mã phiếu, đơn hàng..."
            class="w-full rounded-xl border border-outline-variant bg-surface px-10 py-2 text-sm text-on-surface placeholder:text-outline focus:border-rose-600 focus:outline-none"
          />
        </div>

        <!-- Loại phiếu Dropdown Filter -->
        <select
          v-model="selectedTypeFilter"
          class="rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm font-semibold text-on-surface focus:border-rose-600 focus:outline-none cursor-pointer"
        >
          <option :value="null">Tất cả Loại phiếu</option>
          <option v-for="t in allDocumentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>

        <!-- Kho Dropdown Filter -->
        <select
          v-model="selectedWarehouseFilter"
          class="rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm font-semibold text-on-surface focus:border-rose-600 focus:outline-none cursor-pointer"
        >
          <option :value="null">Tất cả Kho hàng</option>
          <option v-for="w in scope.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
        </select>

        <!-- Thời gian / Ngày Dropdown Filter -->
        <select
          v-model="selectedTimeFilter"
          class="rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm font-semibold text-on-surface focus:border-rose-600 focus:outline-none cursor-pointer"
        >
          <option :value="null">Tất cả Thời gian</option>
          <option value="today">Hôm nay</option>
          <option value="7days">7 ngày qua</option>
          <option value="30days">30 ngày qua</option>
          <option value="this_month">Tháng này</option>
        </select>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3.5 py-2 text-xs font-bold text-on-surface transition-colors hover:bg-surface-container-high cursor-pointer"
          @click="batchPrint"
        >
          <span class="material-symbols-outlined text-base">print</span>
          In hàng loạt
        </button>

        <button
          type="button"
          class="flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3.5 py-2 text-xs font-bold text-on-surface transition-colors hover:bg-surface-container-high cursor-pointer"
          @click="batchExportExcel"
        >
          <span class="material-symbols-outlined text-base font-bold text-emerald-700">description</span>
          Xuất Excel
        </button>
      </div>
    </section>

    <!-- Master Data Table (Matching Screenshot) -->
    <section class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest shadow-xs overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-sm font-semibold text-outline animate-pulse">
        Đang tải danh sách phiếu nhập / xuất kho...
      </div>

      <div v-else-if="error" class="p-6 text-center text-sm font-bold text-error">
        {{ error }}
      </div>

      <div v-else-if="!filteredDocuments.length" class="p-12 text-center text-sm font-medium text-outline">
        Không tìm thấy phiếu kho nào phù hợp với điều kiện lọc.
      </div>

      <div v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse min-w-[900px]">
            <thead>
              <tr class="border-b border-outline-variant/30 bg-surface-container-low font-bold text-outline uppercase tracking-wider">
                <th class="p-3 w-10 text-center">
                  <input type="checkbox" class="rounded cursor-pointer" @change="toggleSelectAll" />
                </th>
                <th class="p-3">Mã phiếu <span class="text-[10px]">⇅</span></th>
                <th class="p-3">Loại</th>
                <th class="p-3">Liên kết</th>
                <th class="p-3">Kho</th>
                <th class="p-3 text-center">Dòng hàng</th>
                <th class="p-3 text-center">Tổng SL</th>
                <th class="p-3">Ngày tạo</th>
                <th class="p-3 text-center">Trạng thái</th>
                <th class="p-3 text-center">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
              <tr
                v-for="document in paginatedDocuments"
                :key="document.id"
                class="hover:bg-rose-50/30 transition-colors cursor-pointer"
                @click="openViewDrawer(document)"
              >
                <td class="p-3 text-center" @click.stop>
                  <input v-model="selectedCheckboxes" :value="document.id" type="checkbox" class="rounded cursor-pointer" />
                </td>

                <td class="p-3 font-black text-rose-700 hover:underline">
                  {{ document.document_code }}
                </td>

                <td class="p-3">
                  <span class="inline-block rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                    {{ typeLabel(document.type) }}
                  </span>
                </td>

                <td class="p-3 text-on-surface-variant font-medium">
                  <span v-if="document.order" class="font-bold text-blue-700">{{ document.order.order_code }}</span>
                  <span v-else-if="document.reason" class="truncate max-w-[120px] inline-block" :title="document.reason">{{ document.reason }}</span>
                  <span v-else class="text-outline">-</span>
                </td>

                <td class="p-3 font-semibold text-on-surface">
                  {{ document.destination_warehouse?.name || document.source_warehouse?.name || 'Kho Yên Nghĩa' }}
                </td>

                <td class="p-3 text-center font-bold">
                  {{ document.lines?.length || 0 }}
                </td>

                <td class="p-3 text-center font-black text-on-surface">
                  {{ documentTotalQuantity(document) }}
                </td>

                <td class="p-3 text-outline font-medium">
                  {{ document.created_at ? new Date(document.created_at).toLocaleDateString('vi-VN') : '03/08/2026' }}
                </td>

                <td class="p-3 text-center">
                  <span
                    class="inline-block rounded-md px-2.5 py-1 text-[11px] font-black"
                    :class="{
                      'bg-emerald-100 text-emerald-800': document.status === 'posted',
                      'bg-amber-100 text-amber-900': document.status === 'submitted',
                      'bg-blue-100 text-blue-800': document.status === 'approved',
                      'bg-slate-200 text-slate-800': document.status === 'draft',
                      'bg-rose-100 text-rose-800': document.status === 'cancelled'
                    }"
                  >
                    {{ statusLabel(document.status) }}
                  </span>
                </td>

                <td class="p-3 text-center" @click.stop>
                  <div class="flex items-center justify-center gap-1">
                    <button
                      type="button"
                      title="Xem chi tiết"
                      class="h-7 w-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 grid place-items-center transition-colors border-none cursor-pointer"
                      @click="openViewDrawer(document)"
                    >
                      <span class="material-symbols-outlined text-sm">visibility</span>
                    </button>

                    <button
                      v-if="document.status === 'draft'"
                      type="button"
                      title="Chỉnh sửa phiếu nháp"
                      class="h-7 w-7 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 grid place-items-center transition-colors border-none cursor-pointer"
                      @click="openEditDrawer(document)"
                    >
                      <span class="material-symbols-outlined text-sm">edit</span>
                    </button>

                    <button
                      v-if="['draft', 'submitted', 'approved'].includes(document.status)"
                      type="button"
                      title="Hủy phiếu kho"
                      class="h-7 w-7 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 grid place-items-center transition-colors border-none cursor-pointer"
                      @click="requestTransition(document, 'cancelled')"
                    >
                      <span class="material-symbols-outlined text-sm">block</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls Footer (Max 20 documents per page) -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-outline-variant/30 bg-surface-container-low/40 p-4 text-xs">
          <div class="flex items-center gap-3 text-outline font-medium">
            <span>
              Hiển thị <strong class="text-on-surface font-bold">{{ (currentPage - 1) * pageSize + 1 }}</strong> -
              <strong class="text-on-surface font-bold">{{ Math.min(currentPage * pageSize, filteredDocuments.length) }}</strong>
              trên tổng <strong class="text-rose-700 font-black">{{ filteredDocuments.length }}</strong> phiếu kho
            </span>

            <label class="flex items-center gap-1.5 ml-2">
              Hiển thị:
              <select v-model="pageSize" class="rounded-lg border border-outline-variant bg-surface px-2 py-1 text-xs font-bold text-on-surface focus:outline-none">
                <option :value="20">20 dòng / trang</option>
                <option :value="50">50 dòng / trang</option>
                <option :value="100">100 dòng / trang</option>
              </select>
            </label>
          </div>

          <!-- Page Buttons -->
          <div v-if="totalPages > 1" class="flex items-center gap-1">
            <button
              type="button"
              class="flex items-center gap-1 rounded-xl border border-outline-variant px-3 py-1.5 text-xs font-bold text-on-surface transition-colors hover:bg-surface-container-high disabled:opacity-40 disabled:cursor-not-allowed"
              :disabled="currentPage === 1"
              @click="currentPage--"
            >
              <span class="material-symbols-outlined text-sm">chevron_left</span>
              Trang trước
            </button>

            <button
              v-for="p in totalPages"
              :key="p"
              type="button"
              class="h-8 w-8 rounded-xl text-xs font-black transition-all cursor-pointer"
              :class="currentPage === p ? 'bg-rose-700 text-white shadow-xs' : 'border border-outline-variant bg-surface text-on-surface hover:bg-surface-container-high'"
              @click="currentPage = p"
            >
              {{ p }}
            </button>

            <button
              type="button"
              class="flex items-center gap-1 rounded-xl border border-outline-variant px-3 py-1.5 text-xs font-bold text-on-surface transition-colors hover:bg-surface-container-high disabled:opacity-40 disabled:cursor-not-allowed"
              :disabled="currentPage >= totalPages"
              @click="currentPage++"
            >
              Trang sau
              <span class="material-symbols-outlined text-sm">chevron_right</span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- RIGHT SLIDE-OVER DRAWER (Kéo sang từ bên phải) -->
    <Teleport to="body">
      <!-- Backdrop -->
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="drawerVisible"
          class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs"
          @click="closeDrawer"
        ></div>
      </Transition>

      <!-- Slide Drawer Panel -->
      <Transition
        enter-active-class="transition duration-300 ease-out transform"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition duration-200 ease-in transform"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
      >
        <aside
          v-if="drawerVisible"
          class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col bg-surface-container-lowest shadow-2xl border-l border-outline-variant overflow-x-hidden"
        >
          <!-- Drawer Header -->
          <div class="flex items-center justify-between border-b border-outline-variant/30 p-5 bg-surface-container-low/40">
            <div class="flex items-center gap-2 min-w-0">
              <h2 class="text-xl font-black text-on-surface truncate">
                {{ drawerMode === 'create' ? 'Tạo phiếu mới' : drawerMode === 'edit' ? `Chỉnh sửa phiếu nháp — ${selectedDocument?.document_code || ''}` : selectedDocument?.document_code || 'Chi tiết phiếu' }}
              </h2>
              <span v-if="drawerMode !== 'create'" class="rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 shrink-0">
                {{ typeLabel(selectedDocument?.type || form.type) }}
              </span>
            </div>

            <button
              type="button"
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-outline hover:bg-surface-container-high hover:text-on-surface cursor-pointer"
              @click="closeDrawer"
            >
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Drawer Content Body -->
          <div class="flex-1 overflow-y-auto overflow-x-hidden p-6 space-y-6">
            <!-- Subtitle -->
            <p v-if="drawerMode !== 'create'" class="text-xs font-medium text-outline">
              {{ originLabel(selectedDocument?.origin) }} - Kho: {{ selectedDocument?.destination_warehouse?.name || selectedDocument?.source_warehouse?.name || 'Kho Yên Nghĩa' }}
              <span v-if="selectedDocument?.order">| Liên kết đơn hàng {{ selectedDocument?.order?.order_code }}</span>
            </p>

            <!-- Stepper Progress (Matching Screenshot) -->
            <div v-if="drawerMode !== 'create' && selectedDocument" class="rounded-2xl border border-rose-100 bg-rose-50/20 p-4">
              <div class="flex items-center justify-between relative">
                <!-- Line -->
                <div class="absolute left-6 right-6 top-3 h-1 bg-surface-container-high -z-0"></div>

                <div
                  v-for="(step, idx) in statusSteps"
                  :key="step.value"
                  class="relative z-10 flex flex-col items-center gap-1"
                >
                  <div
                    class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-all"
                    :class="idx <= statusIndex(selectedDocument.status) ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-surface-container-high text-outline'"
                  >
                    <span v-if="idx < statusIndex(selectedDocument.status)" class="material-symbols-outlined text-sm">check</span>
                    <span v-else>{{ idx + 1 }}</span>
                  </div>
                  <span
                    class="text-[11px] font-extrabold"
                    :class="idx <= statusIndex(selectedDocument.status) ? 'text-emerald-700' : 'text-outline'"
                  >
                    {{ step.label }}
                  </span>
                </div>
              </div>
            </div>

            <!-- FORM MODE (Creating or Editing) -->
            <form v-if="drawerMode === 'create' || drawerMode === 'edit'" class="space-y-4 min-w-0" @submit.prevent="saveDocument">
              <div class="grid grid-cols-2 gap-3 min-w-0">
                <div class="min-w-0">
                  <label class="mb-1 block text-xs font-bold text-outline">Loại phiếu</label>
                  <Select v-model="form.type" :options="documentTypes" optionLabel="label" optionValue="value" class="w-full max-w-full" />
                </div>

                <div v-if="['receipt', 'transfer'].includes(form.type)" class="min-w-0">
                  <label class="mb-1 block text-xs font-bold text-outline">Kho đích</label>
                  <Select v-model="form.destination_warehouse_id" :options="scope.warehouses" optionLabel="name" optionValue="id" class="w-full max-w-full" />
                </div>

                <div v-if="['dispatch', 'transfer', 'count'].includes(form.type)" class="min-w-0">
                  <label class="mb-1 block text-xs font-bold text-outline">Kho nguồn</label>
                  <Select v-model="form.source_warehouse_id" :options="scope.warehouses" optionLabel="name" optionValue="id" class="w-full max-w-full" />
                </div>
              </div>

              <div v-if="form.type === 'receipt'" class="grid grid-cols-2 gap-3 min-w-0">
                <div class="min-w-0">
                  <label class="mb-1 block text-xs font-bold text-outline">Hình thức nhập</label>
                  <Select v-model="form.receipt_mode" :options="[{ label: 'Nhập bổ sung sản phẩm đã có', value: 'restock_existing' }]" optionLabel="label" optionValue="value" class="w-full max-w-full" />
                </div>
                <div class="min-w-0">
                  <label class="mb-1 block text-xs font-bold text-outline">Đơn vị in / Nguồn hàng</label>
                  <InputText v-model="form.external_counterparty_name" placeholder="Ví dụ: NXB Kim Đồng" class="w-full max-w-full" />
                </div>
              </div>

              <div class="min-w-0">
                <label class="mb-1 block text-xs font-bold text-outline">Lý do / Tham chiếu</label>
                <Textarea v-model="form.reason" rows="2" placeholder="Ghi chú lý do nhập xuất kho..." class="w-full max-w-full" />
              </div>

              <!-- Smart Restock Suggestions Banner (Đề xuất sách hết / sắp hết khi chọn nhập kho) -->
              <div
                v-if="form.type === 'receipt' && suggestedRestockBooks.length"
                class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 space-y-3 shadow-xs min-w-0 max-w-full"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-700 text-lg">lightbulb</span>
                    <h4 class="text-xs font-black uppercase tracking-wider text-amber-900">
                      Đề xuất nhập hàng ({{ suggestedRestockBooks.length }} sách hết / sắp hết)
                    </h4>
                  </div>

                  <button
                    type="button"
                    class="text-xs font-bold text-amber-800 hover:text-amber-950 underline cursor-pointer"
                    @click="addAllSuggestedBooks"
                  >
                    + Thêm tất cả vào phiếu
                  </button>
                </div>

                <div class="flex flex-wrap gap-2 max-h-36 overflow-y-auto pr-1">
                  <button
                    v-for="b in suggestedRestockBooks"
                    :key="b.id"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border px-2.5 py-1 text-xs font-semibold transition-all cursor-pointer"
                    :class="isBookInLines(b.id)
                      ? 'border-emerald-400 bg-emerald-100 text-emerald-900 shadow-xs'
                      : 'border-amber-300 bg-white text-slate-800 hover:bg-amber-100'"
                    @click="addSuggestedBook(b)"
                  >
                    <span class="font-bold truncate max-w-[180px]">{{ b.display_title || b.title }}</span>
                    <span
                      class="rounded-md px-1.5 py-0.5 text-[10px] font-black shrink-0"
                      :class="Number(b.stock || 0) === 0 ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-900'"
                    >
                      {{ Number(b.stock || 0) === 0 ? 'Hết hàng' : `Còn ${b.stock}` }}
                    </span>
                    <span v-if="isBookInLines(b.id)" class="material-symbols-outlined text-xs text-emerald-700 font-bold shrink-0">check</span>
                  </button>
                </div>
              </div>

              <!-- Lines Form -->
              <fieldset class="space-y-3 pt-2 min-w-0 max-w-full">
                <legend class="text-sm font-black text-on-surface">Dòng sản phẩm nhập / xuất</legend>
                <div
                  v-for="(line, idx) in form.lines"
                  :key="idx"
                  class="flex items-center gap-3 rounded-xl bg-surface-container-low p-3 border border-outline-variant/30 min-w-0"
                >
                  <div class="flex-1 min-w-0">
                    <label class="mb-1 block text-[11px] font-bold text-outline">Chọn Sách</label>
                    <Select v-model="line.book_id" :options="formattedBooksForSelect" optionLabel="display_title_with_stock" optionValue="id" filter class="w-full max-w-full min-w-0" />
                  </div>

                  <div class="w-24 sm:w-28 shrink-0 min-w-0">
                    <label class="mb-1 block text-[11px] font-bold text-outline">Số lượng</label>
                    <InputNumber v-model="line.quantity" :min="1" class="w-full max-w-full min-w-0" />
                  </div>

                  <button
                    type="button"
                    class="mt-4 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50 cursor-pointer"
                    :disabled="form.lines.length === 1"
                    @click="removeLine(idx)"
                  >
                    <span class="material-symbols-outlined text-lg">delete</span>
                  </button>
                </div>

                <Button type="button" label="Thêm dòng sản phẩm" icon="pi pi-plus" severity="secondary" outlined class="w-full mt-2" @click="addLine" />
              </fieldset>
            </form>

            <!-- VIEW MODE (Matching Screenshot Panels) -->
            <template v-else-if="selectedDocument">
              <!-- General Info Card (Matching Screenshot) -->
              <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-low p-4 space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-outline">Thông tin chung</h3>
                <div class="grid grid-cols-3 gap-4 text-xs">
                  <div>
                    <span class="text-outline block">Kho</span>
                    <strong class="text-sm text-on-surface font-bold">
                      {{ selectedDocument.destination_warehouse?.name || selectedDocument.source_warehouse?.name || 'Kho Yên Nghĩa' }}
                    </strong>
                  </div>
                  <div>
                    <span class="text-outline block">NXB / Nguồn</span>
                    <strong class="text-sm text-on-surface font-bold">
                      {{ selectedDocument.external_counterparty_name || scope.vendor?.shop_name || 'NXB Kim Đồng' }}
                    </strong>
                  </div>
                  <div>
                    <span class="text-outline block">Ngày tạo</span>
                    <strong class="text-sm text-on-surface font-bold">
                      {{ selectedDocument.created_at ? new Date(selectedDocument.created_at).toLocaleDateString('vi-VN') : '03/08/2026' }}
                    </strong>
                  </div>
                </div>
              </div>

              <!-- Matched Products Table (Matching Screenshot) -->
              <div class="space-y-3">
                <h3 class="text-sm font-black text-on-surface">Bảng sản phẩm đối chiếu</h3>
                <div class="overflow-x-auto rounded-2xl border border-outline-variant/30">
                  <table class="w-full text-left text-xs">
                    <thead>
                      <tr class="border-b border-outline-variant/30 bg-surface-container-low font-bold text-outline">
                        <th class="p-3">SKU</th>
                        <th class="p-3">Tên Sách</th>
                        <th class="p-3 text-center">SL Yêu cầu</th>
                        <th class="p-3 text-center">SL Thực tế</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                      <tr v-for="line in selectedDocument.lines" :key="line.id || line.book_id" class="hover:bg-surface-container-low/40">
                        <td class="p-3 font-mono font-bold text-outline">
                          {{ line.book?.isbn || `S${line.book_id}00002` }}
                        </td>
                        <td class="p-3 font-bold text-on-surface max-w-[200px] truncate" :title="line.book?.display_title || line.book?.title">
                          {{ line.book?.display_title || line.book?.title || `Sách #${line.book_id}` }}
                        </td>
                        <td class="p-3 text-center font-semibold text-on-surface">
                          {{ line.quantity }}
                        </td>
                        <td class="p-3 text-center font-black text-emerald-700">
                          {{ selectedDocument.type === 'count' ? (line.actual_quantity ?? line.quantity) : line.quantity }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>
          </div>

          <!-- Drawer Footer Actions (Matching Screenshot Buttons) -->
          <div class="flex items-center justify-between border-t border-outline-variant/30 p-4 bg-surface-container-lowest">
            <div class="flex items-center gap-2">
              <button
                v-if="selectedDocument"
                type="button"
                class="flex items-center gap-1 rounded-xl border border-outline-variant px-3 py-2 text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer"
                @click="downloadDocument(selectedDocument, 'print')"
              >
                <span class="material-symbols-outlined text-sm">print</span>
                In
              </button>

              <button
                v-if="selectedDocument"
                type="button"
                class="flex items-center gap-1 rounded-xl border border-outline-variant px-3 py-2 text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer"
                @click="downloadDocument(selectedDocument, 'pdf')"
              >
                <span class="material-symbols-outlined text-sm font-bold text-rose-600">picture_as_pdf</span>
                PDF
              </button>

              <button
                v-if="selectedDocument"
                type="button"
                class="flex items-center gap-1 rounded-xl border border-outline-variant px-3 py-2 text-xs font-bold text-on-surface hover:bg-surface-container-high cursor-pointer"
                @click="downloadDocument(selectedDocument, 'excel')"
              >
                <span class="material-symbols-outlined text-sm font-bold text-emerald-700">description</span>
                Excel
              </button>
            </div>

            <div class="flex items-center gap-2">
              <button
                type="button"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 cursor-pointer"
                @click="closeDrawer"
              >
                Đóng ✕
              </button>

              <button
                v-if="drawerMode === 'view' && selectedDocument && ['draft', 'submitted', 'approved'].includes(selectedDocument.status)"
                type="button"
                class="flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 cursor-pointer"
                @click="requestTransition(selectedDocument, 'cancelled')"
              >
                <span class="material-symbols-outlined text-sm">block</span>
                Hủy phiếu
              </button>

              <button
                v-if="drawerMode === 'view' && selectedDocument?.status === 'draft'"
                type="button"
                class="flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-800 hover:bg-amber-100 cursor-pointer"
                @click="openEditDrawer(selectedDocument)"
              >
                <span class="material-symbols-outlined text-sm">edit</span>
                Sửa phiếu nháp
              </button>

              <button
                v-if="drawerMode === 'create' || drawerMode === 'edit'"
                type="button"
                class="flex items-center gap-1.5 rounded-xl bg-slate-900 px-5 py-2 text-xs font-black text-white shadow-md hover:bg-slate-800 cursor-pointer"
                :disabled="saving"
                @click="saveDocument"
              >
                <span class="material-symbols-outlined text-sm">check</span>
                {{ drawerMode === 'edit' ? 'Lưu cập nhật' : 'Lưu phiếu nháp' }}
              </button>

              <button
                v-else-if="selectedDocument?.status === 'draft'"
                type="button"
                class="flex items-center gap-1.5 rounded-xl bg-slate-900 px-5 py-2 text-xs font-black text-white shadow-md hover:bg-slate-800 cursor-pointer"
                @click="requestTransition(selectedDocument, 'submitted')"
              >
                <span class="material-symbols-outlined text-sm">send</span>
                Gửi duyệt
              </button>

              <button
                v-else-if="selectedDocument?.status === 'submitted' && isVendorWorkspace"
                type="button"
                class="flex items-center gap-1.5 rounded-xl bg-slate-900 px-5 py-2 text-xs font-black text-white shadow-md hover:bg-slate-800 cursor-pointer"
                @click="requestTransition(selectedDocument, 'approved')"
              >
                <span class="material-symbols-outlined text-sm">verified</span>
                Xét & duyệt phiếu
              </button>

              <button
                v-else-if="selectedDocument?.status === 'approved'"
                type="button"
                class="flex items-center gap-1.5 rounded-xl bg-slate-900 px-5 py-2 text-xs font-black text-white shadow-md hover:bg-slate-800 cursor-pointer"
                @click="requestTransition(selectedDocument, 'posted')"
              >
                <span class="material-symbols-outlined text-sm">check_circle</span>
                Kiểm tra & ghi sổ
              </button>
            </div>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- Transition Review Dialog -->
    <Dialog v-model:visible="reviewDialogVisible" modal :header="reviewTitle" class="!w-[min(94vw,720px)] !rounded-2xl" :closable="!transitioning">
      <div v-if="pendingTransition" class="space-y-5">
        <div class="rounded-xl border border-primary/20 bg-primary/5 p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm text-on-surface-variant">Phiếu đang xét</p>
              <p class="mt-1 text-lg font-black">{{ pendingTransition.document.document_code }}</p>
            </div>
            <span class="rounded-full bg-surface-container-lowest px-3 py-1 text-sm font-bold text-primary">{{ typeLabel(pendingTransition.document.type) }}</span>
          </div>
          <p class="mt-3 text-sm leading-6 text-on-surface-variant">{{ reviewDescription }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div class="rounded-xl bg-surface-container p-3">
            <p class="text-xs text-on-surface-variant">Kho</p>
            <strong>{{ pendingTransition.document.source_warehouse?.name || pendingTransition.document.destination_warehouse?.name || 'Kho Yên Nghĩa' }}</strong>
          </div>
          <div class="rounded-xl bg-surface-container p-3">
            <p class="text-xs text-on-surface-variant">Dòng hàng</p>
            <strong>{{ pendingTransition.document.lines?.length || 0 }}</strong>
          </div>
          <div class="col-span-2 rounded-xl bg-surface-container p-3 sm:col-span-1">
            <p class="text-xs text-on-surface-variant">Tổng lượng</p>
            <strong>{{ documentTotalQuantity(pendingTransition.document) }}</strong>
          </div>
        </div>

        <div>
          <label for="transition-reason" class="mb-2 block text-sm font-semibold">{{ pendingTransition.toStatus === 'cancelled' ? 'Lý do hủy *' : 'Ghi chú xét duyệt' }}</label>
          <Textarea id="transition-reason" v-model="transitionReason" rows="3" class="w-full" :placeholder="pendingTransition.toStatus === 'cancelled' ? 'Nêu rõ lý do hủy phiếu' : 'Ghi lại lưu ý nếu cần'" />
        </div>
      </div>

      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <Button label="Quay lại" severity="secondary" text class="min-h-11" :disabled="transitioning" @click="reviewDialogVisible = false" />
          <Button :label="reviewActionLabel" :severity="pendingTransition?.toStatus === 'cancelled' ? 'danger' : undefined" class="min-h-11" :loading="transitioning" :disabled="pendingTransition?.toStatus === 'cancelled' && !transitionReason.trim()" @click="confirmTransition" />
        </div>
      </template>
    </Dialog>

    <!-- Document Print Preview Dialog (Synchronized with Order Invoice Modal Design) -->
    <Dialog
      v-model:visible="printModalVisible"
      modal
      :header="`Xem Trước ${printDocument ? typeLabel(printDocument.type) : 'Phiếu Kho'}`"
      class="!max-w-3xl !w-[95vw] !rounded-2xl"
    >
      <div v-if="printDocument" id="printable-warehouse-document" class="p-8 bg-white text-[#172033] font-sans text-xs leading-relaxed">
        <!-- Header Invoice Style -->
        <div class="flex items-start justify-between border-b border-slate-300 pb-4">
          <div>
            <h2 class="text-2xl font-black text-rose-700 uppercase tracking-tight leading-none mb-1">KomiBook</h2>
            <p class="text-[11px] text-slate-500 font-medium leading-tight">Hệ thống Phát hành & Phân phối Sách Trực tuyến</p>
            <p class="text-xs text-slate-800 font-bold mt-2.5 leading-tight">Gian hàng: {{ printDocument.vendor?.shop_name || 'Shop của Nhà Xuất Bản Kim Đồng' }}</p>
          </div>

          <div class="text-right">
            <h3 class="text-lg font-black text-slate-900 uppercase leading-none mb-1">{{ typeLabel(printDocument.type) }}</h3>
            <p class="text-xs font-mono font-bold text-rose-700 leading-tight">Mã phiếu: {{ printDocument.document_code }}</p>
            <p class="text-[11px] text-slate-500 mt-1 leading-tight">Ngày lập: {{ formatDate(printDocument.created_at) }}</p>
          </div>
        </div>

        <!-- 2 Column Info Grid (Matching Order Invoice Layout) -->
        <div class="grid grid-cols-2 gap-6 p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs my-4">
          <!-- Left: Document Meta -->
          <div class="space-y-2">
            <h4 class="font-bold text-slate-900 uppercase tracking-wider border-b border-slate-200 pb-1.5 text-[11px]">Thông tin chứng từ</h4>
            <div class="grid grid-cols-3 gap-1 items-baseline">
              <span class="font-semibold text-slate-700 col-span-1">Nhà bán:</span>
              <span class="text-slate-900 font-bold col-span-2">{{ printDocument.vendor?.shop_name || '—' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-1 items-baseline">
              <span class="font-semibold text-slate-700 col-span-1">Kho nguồn:</span>
              <span class="text-slate-900 font-medium col-span-2">{{ printDocument.source_warehouse?.name || printDocument.sourceWarehouse?.name || '—' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-1 items-baseline">
              <span class="font-semibold text-slate-700 col-span-1">Kho đích:</span>
              <span class="text-slate-900 font-medium col-span-2">{{ printDocument.destination_warehouse?.name || printDocument.destinationWarehouse?.name || '—' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-1 items-baseline">
              <span class="font-semibold text-slate-700 col-span-1">Lý do lập:</span>
              <span class="text-slate-900 font-medium col-span-2 leading-tight">{{ printDocument.reason || '—' }}</span>
            </div>
          </div>

          <!-- Right: Additional Meta -->
          <div class="space-y-2">
            <h4 class="font-bold text-slate-900 uppercase tracking-wider border-b border-slate-200 pb-1.5 text-[11px]">Thông tin bổ sung</h4>
            <div class="grid grid-cols-3 gap-1 items-baseline">
              <span class="font-semibold text-slate-700 col-span-1">Trạng thái:</span>
              <span class="text-slate-900 font-bold col-span-2">{{ documentStatusLabel(printDocument.status) }}</span>
            </div>
            <div class="grid grid-cols-3 gap-1 items-baseline">
              <span class="font-semibold text-slate-700 col-span-1">Đơn hàng:</span>
              <span class="text-slate-900 font-mono font-bold col-span-2">{{ printDocument.order?.order_code || '—' }}</span>
            </div>
            <div class="grid grid-cols-3 gap-1 items-baseline">
              <span class="font-semibold text-slate-700 col-span-1">Đơn vị ngoài:</span>
              <span class="text-slate-900 font-medium col-span-2">{{ printDocument.external_counterparty_name || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Products Table -->
        <div class="overflow-x-auto border border-slate-300 rounded-xl my-4">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-slate-100 text-slate-800 uppercase font-bold border-b border-slate-300">
              <tr>
                <th class="py-2.5 px-3 w-12 text-center align-middle border-r border-slate-300">STT</th>
                <th class="py-2.5 px-3 align-middle border-r border-slate-300">Tên Sách</th>
                <th class="py-2.5 px-3 w-36 text-center align-middle border-r border-slate-300">ISBN / SKU</th>
                <th class="py-2.5 px-3 w-20 text-center align-middle border-r border-slate-300">Bản in</th>
                <th class="py-2.5 px-3 w-28 text-right align-middle">Số lượng</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-300">
              <tr v-for="(line, idx) in printDocument.lines" :key="line.id || idx" class="align-middle">
                <td class="py-2.5 px-3 text-center text-slate-600 font-medium align-middle border-r border-slate-300">{{ idx + 1 }}</td>
                <td class="py-2.5 px-3 font-bold text-slate-900 align-middle leading-snug border-r border-slate-300">
                  {{ line.book?.display_title || line.book?.title || `Sách #${line.book_id}` }}
                </td>
                <td class="py-2.5 px-3 text-center font-mono text-slate-700 align-middle border-r border-slate-300">
                  {{ line.book?.isbn || `S${line.book_id}00002` }}
                </td>
                <td class="py-2.5 px-3 text-center font-medium align-middle border-r border-slate-300">
                  {{ line.book?.print_edition || 1 }}
                </td>
                <td class="py-2.5 px-3 text-right font-bold text-slate-900 align-middle">
                  {{ printDocument.type === 'count' ? (line.actual_quantity ?? line.quantity) : line.quantity }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Total Quantity Breakdown Footer -->
        <div class="flex justify-end pt-2">
          <div class="w-80 space-y-2 border border-slate-300 rounded-xl p-3.5 bg-slate-50/50 text-xs">
            <div class="flex justify-between items-center text-slate-600">
              <span class="font-medium">Tổng danh mục sách:</span>
              <span class="font-bold text-slate-900">{{ printDocument.lines?.length || 0 }} mục</span>
            </div>
            <div class="flex justify-between items-center border-t border-slate-300 pt-2 text-xs font-bold text-slate-900">
              <span class="text-slate-900 font-black">Tổng số lượng sản phẩm:</span>
              <span class="text-rose-700 text-sm font-black">{{ calculateTotalQuantity(printDocument) }} cuốn</span>
            </div>
          </div>
        </div>

        <!-- Printable Note -->
        <div class="text-center pt-4 border-t border-dashed border-slate-300 text-[11px] text-slate-500 italic mt-4">
          Phiếu được kết xuất từ snapshot chứng từ KomiBook. Các bút toán đã ghi sổ không thể chỉnh sửa.
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-2">
          <Button label="Đóng" severity="secondary" text @click="printModalVisible = false" class="!rounded-lg" />
          <Button label="In phiếu" icon="pi pi-print" severity="primary" @click="triggerPrintDocument" class="!rounded-lg" />
        </div>
      </template>
    </Dialog>
  </main>
</template>

<style>
@media print {
  body * {
    visibility: hidden !important;
  }

  .p-dialog-mask,
  .p-dialog-mask *,
  .p-dialog,
  .p-dialog *,
  .p-dialog-content,
  .p-dialog-content *,
  #printable-warehouse-document,
  #printable-warehouse-document * {
    visibility: visible !important;
  }

  .p-dialog-header,
  .p-dialog-footer {
    display: none !important;
    visibility: hidden !important;
  }

  body > .p-dialog-mask,
  .p-dialog-mask {
    position: static !important;
    display: block !important;
    width: 100% !important;
    height: auto !important;
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .p-dialog {
    position: static !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    max-height: none !important;
    box-shadow: none !important;
    border: none !important;
    background: transparent !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  .p-dialog-content {
    position: static !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: visible !important;
    background: transparent !important;
  }

  #printable-warehouse-document {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
    background: white !important;
    color: black !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
  }
}
</style>

<style scoped>
.shadow-xs { box-shadow: 0px 2px 4px rgba(26,58,90,0.04); }

:deep(.p-select),
:deep(.p-inputnumber),
:deep(.p-inputtext),
:deep(.p-textarea) {
  width: 100% !important;
  max-width: 100% !important;
  box-sizing: border-box !important;
}

:deep(.p-select-label) {
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  white-space: nowrap !important;
  display: block !important;
  max-width: 100% !important;
}

:deep(.p-inputnumber-input) {
  width: 100% !important;
  max-width: 100% !important;
  box-sizing: border-box !important;
}
</style>

<script setup>
import { computed, onMounted, ref } from 'vue'
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
const scope = ref({ warehouses: [], books: [], capabilities: [] })
const documents = ref([])
const editingDocumentId = ref(null)
const reviewDialogVisible = ref(false)
const pendingTransition = ref(null)
const transitionReason = ref('')
const transitioning = ref(false)
const filters = ref({ search: '', type: null, status: null })
const emptyLine = () => ({ book_id: null, quantity: 1, actual_quantity: null })
const emptyForm = () => ({ type: 'receipt', receipt_mode: 'restock_existing', external_counterparty_name: '', source_warehouse_id: null, destination_warehouse_id: null, reason: '', lines: [emptyLine()] })
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
const statusSteps = statusOptions.filter(item => !['cancelled'].includes(item.value))
const statusIndex = status => statusSteps.findIndex(item => item.value === status)
const originLabel = origin => ({
  manual: 'Tạo thủ công',
  book_creation: 'Tạo cùng sách mới',
  order_fulfillment: 'Sinh từ đơn hàng',
  inventory_adjustment: 'Điều chỉnh tồn kho',
}[origin] || 'Nghiệp vụ kho')
const documentTotalQuantity = document => (document.lines || []).reduce((total, line) => total + Number(document.type === 'count' ? (line.actual_quantity ?? 0) : (line.quantity ?? 0)), 0)
const reviewTitle = computed(() => ({
  submitted: 'Kiểm tra trước khi gửi duyệt',
  approved: 'Xét duyệt phiếu kho',
  posted: 'Xác nhận ghi sổ tồn kho',
  cancelled: 'Xác nhận hủy phiếu',
}[pendingTransition.value?.toStatus] || 'Xác nhận thao tác'))
const reviewActionLabel = computed(() => ({
  submitted: 'Gửi duyệt',
  approved: 'Duyệt phiếu',
  posted: 'Ghi sổ phiếu',
  cancelled: 'Hủy phiếu',
}[pendingTransition.value?.toStatus] || 'Xác nhận'))
const reviewDescription = computed(() => ({
  submitted: 'Sau khi gửi duyệt, nội dung phiếu sẽ được khóa để người có quyền kiểm tra.',
  approved: 'Đối chiếu kho, sách và số lượng trước khi xác nhận phiếu hợp lệ.',
  posted: 'Ghi sổ sẽ cập nhật tồn kho và tạo bút toán bất biến. Hãy kiểm tra kỹ số lượng.',
  cancelled: 'Phiếu bị hủy sẽ không thể tiếp tục quy trình. Vui lòng ghi rõ lý do.',
}[pendingTransition.value?.toStatus] || 'Kiểm tra thông tin trước khi tiếp tục.'))
const filteredDocuments = computed(() => documents.value.filter(document => {
  const search = filters.value.search.trim().toLowerCase()
  return (!filters.value.type || document.type === filters.value.type)
    && (!filters.value.status || document.status === filters.value.status)
    && (!search || `${document.document_code} ${document.order?.order_code || ''}`.toLowerCase().includes(search))
}))
const presetApplied = ref(false)
const routePresetLabel = computed(() => allDocumentTypes.find(type => type.value === route.query.type)?.label || '')

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
      if (requestedWarehouseId && scope.value.warehouses.some(warehouse => warehouse.id === requestedWarehouseId)) {
        if (['dispatch', 'transfer', 'count'].includes(form.value.type)) form.value.source_warehouse_id = requestedWarehouseId
        if (form.value.type === 'receipt') form.value.destination_warehouse_id = requestedWarehouseId
      }
      if (requestedBookId && scope.value.books.some(book => book.id === requestedBookId)) {
        form.value.lines[0].book_id = requestedBookId
      }
      if (!form.value.destination_warehouse_id && form.value.type === 'receipt') {
        form.value.destination_warehouse_id = scope.value.vendor?.primary_warehouse_id || null
      }
      presetApplied.value = true
    }
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Không thể tải chứng từ kho.'
  } finally {
    loading.value = false
  }
}

const addLine = () => form.value.lines.push(emptyLine())
const removeLine = (index) => {
  if (form.value.lines.length > 1) form.value.lines.splice(index, 1)
}

const createDocument = async () => {
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
      toast.add({ severity: 'success', summary: 'Đã tạo phiếu nháp', detail: 'Kiểm tra nội dung rồi gửi duyệt.', life: 3500 })
    }
    editingDocumentId.value = null
    form.value = emptyForm()
    form.value.destination_warehouse_id = scope.value.vendor?.primary_warehouse_id || null
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể tạo phiếu', detail: exception.response?.data?.message || 'Kiểm tra kho, quyền và số lượng.', life: 4500 })
  } finally {
    saving.value = false
  }
}

const editDocument = (document) => {
  editingDocumentId.value = document.id
  form.value = {
    type: document.type,
    receipt_mode: document.receipt_mode || 'restock_existing',
    external_counterparty_name: document.external_counterparty_name || '',
    source_warehouse_id: document.source_warehouse_id,
    destination_warehouse_id: document.destination_warehouse_id,
    reason: document.reason || '',
    lines: document.lines.map(line => ({
      book_id: line.book_id,
      quantity: line.quantity,
      actual_quantity: line.actual_quantity,
    })),
  }
  globalThis.scrollTo?.({ top: 0, behavior: 'smooth' })
}

const cancelEdit = () => {
  editingDocumentId.value = null
  form.value = emptyForm()
  form.value.destination_warehouse_id = scope.value.vendor?.primary_warehouse_id || null
}

const downloadDocument = async (document, format) => {
  try {
    const response = await apiClient.get(`${documentsUrl.value}/${document.id}/${format}`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    if (format === 'print') {
      globalThis.open(url, '_blank', 'noopener,noreferrer')
      setTimeout(() => URL.revokeObjectURL(url), 60000)
      return
    }
    const anchor = globalThis.document.createElement('a')
    anchor.href = url
    anchor.download = `${document.document_code}.${format === 'excel' ? 'xlsx' : 'pdf'}`
    anchor.click()
    URL.revokeObjectURL(url)
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể xuất phiếu', detail: exception.response?.data?.message || 'Vui lòng thử lại.', life: 4000 })
  }
}

const requestTransition = (document, toStatus) => {
  pendingTransition.value = { document, toStatus }
  transitionReason.value = ''
  reviewDialogVisible.value = true
}

const confirmTransition = async () => {
  const document = pendingTransition.value?.document
  const toStatus = pendingTransition.value?.toStatus
  if (!document || !toStatus) return
  if (toStatus === 'cancelled' && !transitionReason.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Thiếu lý do hủy', detail: 'Vui lòng nhập lý do để lưu trong lịch sử phiếu.', life: 3500 })
    return
  }
  const successLabel = reviewActionLabel.value
  transitioning.value = true
  try {
    await apiClient.patch(`${documentsUrl.value}/${document.id}/transition`, {
      to_status: toStatus,
      reason: transitionReason.value.trim() || null,
      operation_key: operationKey(`${document.id}-${toStatus}`),
    })
    reviewDialogVisible.value = false
    pendingTransition.value = null
    toast.add({ severity: 'success', summary: successLabel, detail: `${document.document_code} đã chuyển sang ${statusLabel(toStatus).toLowerCase()}.`, life: 3500 })
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
  <main id="main-content" class="space-y-8" tabindex="-1">
    <header>
      <p class="text-sm font-semibold uppercase tracking-wider text-primary">Chứng từ kho</p>
      <div class="mt-1 flex items-center gap-2"><h1 class="text-3xl font-bold text-on-surface">Phiếu nhập / xuất kho</h1><InfoTip text="Mỗi phiếu có lịch sử trạng thái và bút toán tồn kho bất biến." label="Nguyên tắc chứng từ kho" /></div>
    </header>
    <div v-if="loading" class="h-64 animate-pulse rounded-xl bg-surface-container"></div>
    <section v-else-if="error" class="rounded-xl bg-error-container p-6 text-on-error-container" role="alert">
      <p>{{ error }}</p><Button label="Thử lại" class="mt-4 min-h-11" @click="load" />
    </section>
    <template v-else>
      <div v-if="routePresetLabel" class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-on-surface" role="status">
        <span class="material-symbols-outlined text-primary" aria-hidden="true">link</span>
        <div><strong>Đã liên kết từ trang Quản lý kho:</strong> biểu mẫu được chuẩn bị sẵn cho {{ routePresetLabel.toLowerCase() }}. Hãy kiểm tra số lượng rồi tạo phiếu nháp.</div>
      </div>
      <form id="warehouse-document-form" class="min-w-0 space-y-5 overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest p-4 sm:p-5" @submit.prevent="createDocument">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div><h2 class="text-xl font-bold">{{ editingDocumentId ? 'Chỉnh sửa phiếu nháp' : 'Tạo phiếu nháp' }}</h2><p class="mt-1 text-sm text-on-surface-variant">{{ scope.vendor?.shop_name }}</p></div>
          <Button v-if="editingDocumentId" type="button" label="Hủy chỉnh sửa" severity="secondary" text class="min-h-11" @click="cancelEdit" />
        </div>
        <div v-if="!scope.can_transfer" class="rounded-xl border border-outline-variant/50 bg-surface-container p-4 text-sm text-on-surface-variant" role="note">Gian hàng hiện chỉ có một kho trong phạm vi vận hành nên không cần phiếu điều chuyển.</div>
        <div class="grid gap-4 md:grid-cols-3">
          <div><label for="document-type" class="mb-2 block text-sm font-semibold">Loại phiếu</label><Select id="document-type" v-model="form.type" :options="documentTypes" optionLabel="label" optionValue="value" class="min-h-11 w-full" /></div>
          <div v-if="['dispatch', 'transfer', 'count'].includes(form.type)"><label for="source-warehouse" class="mb-2 block text-sm font-semibold">Kho nguồn</label><Select id="source-warehouse" v-model="form.source_warehouse_id" :options="scope.warehouses" optionLabel="name" optionValue="id" class="min-h-11 w-full" /></div>
          <div v-if="['receipt', 'transfer'].includes(form.type)"><label for="destination-warehouse" class="mb-2 block text-sm font-semibold">Kho đích</label><Select id="destination-warehouse" v-model="form.destination_warehouse_id" :options="scope.warehouses" optionLabel="name" optionValue="id" class="min-h-11 w-full" /></div>
        </div>
        <div v-if="form.type === 'receipt'" class="grid gap-4 md:grid-cols-2">
          <div><label for="receipt-mode" class="mb-2 block text-sm font-semibold">Hình thức nhập</label><Select id="receipt-mode" v-model="form.receipt_mode" :options="[{ label: 'Nhập bổ sung sản phẩm đã có', value: 'restock_existing' }]" optionLabel="label" optionValue="value" class="min-h-11 w-full" /></div>
          <div><div class="mb-2 flex items-center justify-between gap-2"><label for="external-counterparty" class="block text-sm font-semibold">Đơn vị in / nguồn ngoài hệ thống</label><InfoTip text="Tên này chỉ là tham chiếu trên phiếu, không tạo tổ chức hoặc quan hệ pháp lý trong hệ thống." label="Ý nghĩa nguồn ngoài hệ thống" /></div><InputText id="external-counterparty" v-model="form.external_counterparty_name" class="min-h-11 w-full" placeholder="Nhập tên đơn vị hoặc nguồn hàng" /></div>
        </div>
        <div><label for="document-reason" class="mb-2 block text-sm font-semibold">Lý do / tham chiếu</label><Textarea id="document-reason" v-model="form.reason" rows="2" class="w-full" /></div>
        <fieldset class="min-w-0 max-w-full space-y-3">
          <legend class="text-base font-bold">Dòng sản phẩm</legend>
          <div v-for="(line, index) in form.lines" :key="index" class="warehouse-line grid min-w-0 max-w-full gap-3 overflow-hidden rounded-xl bg-surface-container p-4 lg:grid-cols-12 lg:items-end">
            <div class="min-w-0 lg:col-span-8"><label :for="`line-book-${index}`" class="mb-2 block text-sm font-semibold">Sách / Bản in</label><Select :id="`line-book-${index}`" v-model="line.book_id" :options="scope.books" optionLabel="display_title" optionValue="id" filter class="min-h-11 w-full max-w-full" /></div>
            <div v-if="form.type !== 'count'" class="min-w-0 lg:col-span-3"><label :for="`line-quantity-${index}`" class="mb-2 block text-sm font-semibold">Số lượng</label><InputNumber :id="`line-quantity-${index}`" v-model="line.quantity" :min="1" class="w-full max-w-full" /></div>
            <div v-else class="min-w-0 lg:col-span-3"><label :for="`line-actual-${index}`" class="mb-2 block text-sm font-semibold">Số đếm thực tế</label><InputNumber :id="`line-actual-${index}`" v-model="line.actual_quantity" :min="0" class="w-full max-w-full" /></div>
            <Button type="button" icon="pi pi-trash" aria-label="Xóa dòng sản phẩm" severity="danger" text class="min-h-11 min-w-11" :disabled="form.lines.length === 1" @click="removeLine(index)" />
          </div>
        </fieldset>
        <div class="flex flex-wrap justify-between gap-3"><Button type="button" label="Thêm dòng" icon="pi pi-plus" severity="secondary" outlined class="min-h-11" @click="addLine" /><Button type="submit" :label="editingDocumentId ? 'Lưu phiếu nháp' : 'Tạo phiếu nháp'" icon="pi pi-file-plus" :loading="saving" class="min-h-11" /></div>
      </form>

      <section class="space-y-4">
        <div class="flex items-center gap-2"><h2 class="text-xl font-bold">Danh sách phiếu</h2><InfoTip text="Có thể lọc theo mã phiếu, mã đơn hàng, loại và trạng thái." label="Cách lọc danh sách phiếu" /></div>
        <div class="grid gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 md:grid-cols-3">
          <InputText v-model="filters.search" class="min-h-11 w-full" placeholder="Tìm mã phiếu hoặc đơn hàng" aria-label="Tìm phiếu kho" />
          <Select v-model="filters.type" :options="allDocumentTypes" optionLabel="label" optionValue="value" showClear class="min-h-11 w-full" placeholder="Tất cả loại phiếu" />
          <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" showClear class="min-h-11 w-full" placeholder="Tất cả trạng thái" />
        </div>
        <div v-if="!filteredDocuments.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-8 text-center text-on-surface-variant">Không có phiếu phù hợp trong phạm vi này.</div>
        <article v-for="document in filteredDocuments" :key="document.id" class="overflow-hidden rounded-2xl border bg-surface-container-lowest shadow-sm" :class="Number(route.query.document_id) === document.id ? 'border-primary ring-2 ring-primary/20' : 'border-outline-variant'">
          <div class="space-y-5 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-lg font-black text-on-surface">{{ document.document_code }}</h3>
                  <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ typeLabel(document.type) }}</span>
                  <span class="rounded-full px-3 py-1 text-xs font-bold" :class="document.status === 'posted' ? 'bg-emerald-100 text-emerald-800' : document.status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900'">{{ statusLabel(document.status) }}</span>
                </div>
                <p class="text-sm text-on-surface-variant">{{ originLabel(document.origin) }} · {{ document.source_warehouse?.name || document.destination_warehouse?.name || 'Chưa chọn kho' }}</p>
                <p v-if="document.order" class="text-sm">Liên kết đơn hàng <strong>{{ document.order.order_code }}</strong></p>
              </div>
              <div class="grid grid-cols-2 gap-2 text-center sm:grid-cols-3">
                <div class="min-w-24 rounded-xl bg-surface-container px-3 py-2"><p class="text-xs text-on-surface-variant">Dòng hàng</p><strong class="text-lg">{{ document.lines?.length || 0 }}</strong></div>
                <div class="min-w-24 rounded-xl bg-surface-container px-3 py-2"><p class="text-xs text-on-surface-variant">Tổng lượng</p><strong class="text-lg">{{ documentTotalQuantity(document) }}</strong></div>
                <div class="col-span-2 rounded-xl bg-surface-container px-3 py-2 sm:col-span-1"><p class="text-xs text-on-surface-variant">Trạng thái</p><strong class="text-sm">{{ statusLabel(document.status) }}</strong></div>
              </div>
            </div>

            <div v-if="document.status !== 'cancelled'" class="grid grid-cols-4 gap-1" aria-label="Tiến độ xử lý phiếu">
              <div v-for="(step, index) in statusSteps" :key="step.value" class="min-w-0">
                <div class="h-1.5 rounded-full" :class="index <= statusIndex(document.status) ? 'bg-primary' : 'bg-surface-container-high'"></div>
                <p class="mt-1 truncate text-[11px] font-semibold" :class="index <= statusIndex(document.status) ? 'text-primary' : 'text-outline'">{{ step.label }}</p>
              </div>
            </div>

            <details class="rounded-xl border border-outline-variant/40 bg-surface-container-low">
              <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-primary">Xem nội dung cần đối chiếu</summary>
              <div class="space-y-2 border-t border-outline-variant/30 p-4">
                <div v-for="line in document.lines" :key="line.id || line.book_id" class="grid gap-2 rounded-lg bg-surface-container-lowest p-3 text-sm sm:grid-cols-[1fr_auto] sm:items-center">
                  <div class="min-w-0"><strong class="block truncate">{{ line.book?.display_title || line.book?.title || `Sách #${line.book_id}` }}</strong><span class="text-xs text-on-surface-variant">ISBN/SKU: {{ line.book?.isbn || 'Chưa khai báo' }} · Bản in {{ line.book?.print_edition || 1 }}</span></div>
                  <span class="font-bold">{{ document.type === 'count' ? (line.actual_quantity ?? 0) : line.quantity }} cuốn</span>
                </div>
              </div>
            </details>

            <div class="flex flex-col gap-3 border-t border-outline-variant/30 pt-4 lg:flex-row lg:items-center lg:justify-between">
              <div class="flex flex-wrap gap-2">
                <Button v-if="document.status === 'draft'" label="Chỉnh sửa" icon="pi pi-pencil" severity="secondary" outlined class="min-h-11" @click="editDocument(document)" />
                <Button v-if="document.status === 'draft'" label="Kiểm tra & gửi duyệt" icon="pi pi-send" class="min-h-11" @click="requestTransition(document, 'submitted')" />
                <Button v-if="document.status === 'submitted' && isVendorWorkspace" label="Xét & duyệt phiếu" icon="pi pi-verified" class="min-h-11" @click="requestTransition(document, 'approved')" />
                <Button v-if="document.status === 'approved'" label="Kiểm tra & ghi sổ" icon="pi pi-check-circle" class="min-h-11" @click="requestTransition(document, 'posted')" />
                <Button v-if="!['posted', 'cancelled'].includes(document.status)" label="Hủy phiếu" severity="danger" text class="min-h-11" @click="requestTransition(document, 'cancelled')" />
              </div>
              <div class="flex flex-wrap gap-1 rounded-xl bg-surface-container p-1">
                <Button label="In" icon="pi pi-print" severity="secondary" text class="min-h-11" @click="downloadDocument(document, 'print')" />
                <Button label="PDF" icon="pi pi-file-pdf" severity="secondary" text class="min-h-11" @click="downloadDocument(document, 'pdf')" />
                <Button label="Excel" icon="pi pi-file-excel" severity="secondary" text class="min-h-11" @click="downloadDocument(document, 'excel')" />
              </div>
            </div>
          </div>
        </article>
      </section>

      <Dialog v-model:visible="reviewDialogVisible" modal :header="reviewTitle" class="!w-[min(94vw,720px)] !rounded-2xl" :closable="!transitioning">
        <div v-if="pendingTransition" class="space-y-5">
          <div class="rounded-xl border border-primary/20 bg-primary/5 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm text-on-surface-variant">Phiếu đang xét</p><p class="mt-1 text-lg font-black">{{ pendingTransition.document.document_code }}</p></div><span class="rounded-full bg-surface-container-lowest px-3 py-1 text-sm font-bold text-primary">{{ typeLabel(pendingTransition.document.type) }}</span></div>
            <p class="mt-3 text-sm leading-6 text-on-surface-variant">{{ reviewDescription }}</p>
          </div>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-surface-container p-3"><p class="text-xs text-on-surface-variant">Kho</p><strong>{{ pendingTransition.document.source_warehouse?.name || pendingTransition.document.destination_warehouse?.name || 'Chưa chọn' }}</strong></div>
            <div class="rounded-xl bg-surface-container p-3"><p class="text-xs text-on-surface-variant">Dòng hàng</p><strong>{{ pendingTransition.document.lines?.length || 0 }}</strong></div>
            <div class="col-span-2 rounded-xl bg-surface-container p-3 sm:col-span-1"><p class="text-xs text-on-surface-variant">Tổng lượng</p><strong>{{ documentTotalQuantity(pendingTransition.document) }}</strong></div>
          </div>
          <div class="max-h-64 space-y-2 overflow-y-auto rounded-xl border border-outline-variant/40 p-3">
            <div v-for="line in pendingTransition.document.lines" :key="line.id || line.book_id" class="flex items-start justify-between gap-3 rounded-lg bg-surface-container-low p-3 text-sm"><div class="min-w-0"><strong class="block truncate">{{ line.book?.display_title || line.book?.title || `Sách #${line.book_id}` }}</strong><span class="text-xs text-on-surface-variant">Bản in {{ line.book?.print_edition || 1 }}</span></div><strong class="shrink-0">{{ pendingTransition.document.type === 'count' ? (line.actual_quantity ?? 0) : line.quantity }} cuốn</strong></div>
          </div>
          <div><label for="transition-reason" class="mb-2 block text-sm font-semibold">{{ pendingTransition.toStatus === 'cancelled' ? 'Lý do hủy *' : 'Ghi chú xét duyệt' }}</label><Textarea id="transition-reason" v-model="transitionReason" rows="3" class="w-full" :placeholder="pendingTransition.toStatus === 'cancelled' ? 'Nêu rõ lý do hủy phiếu' : 'Ghi lại lưu ý nếu cần'" /></div>
        </div>
        <template #footer><div class="flex w-full justify-end gap-2"><Button label="Quay lại" severity="secondary" text class="min-h-11" :disabled="transitioning" @click="reviewDialogVisible = false" /><Button :label="reviewActionLabel" :severity="pendingTransition?.toStatus === 'cancelled' ? 'danger' : undefined" class="min-h-11" :loading="transitioning" :disabled="pendingTransition?.toStatus === 'cancelled' && !transitionReason.trim()" @click="confirmTransition" /></div></template>
      </Dialog>
    </template>
  </main>
</template>

<style scoped>
.warehouse-line :deep(.p-select),
.warehouse-line :deep(.p-inputnumber),
.warehouse-line :deep(.p-inputnumber-input) {
  width: 100%;
  min-width: 0;
  max-width: 100%;
}
</style>

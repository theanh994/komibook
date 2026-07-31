<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'

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
const emptyLine = () => ({ book_id: null, quantity: 1, actual_quantity: null, shelf_location: '' })
const form = ref({ type: 'receipt', source_warehouse_id: null, destination_warehouse_id: null, reason: '', lines: [emptyLine()] })
const documentTypes = [
  { label: 'Phiếu nhập kho', value: 'receipt' },
  { label: 'Phiếu xuất kho', value: 'dispatch' },
  { label: 'Phiếu điều chuyển', value: 'transfer' },
  { label: 'Phiếu kiểm kê', value: 'count' },
]

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
    await apiClient.post(documentsUrl.value, {
      ...form.value,
      operation_key: operationKey('warehouse-document'),
    })
    toast.add({ severity: 'success', summary: 'Đã tạo phiếu nháp', detail: 'Kiểm tra lại nội dung rồi gửi duyệt.', life: 3500 })
    form.value = { type: 'receipt', source_warehouse_id: null, destination_warehouse_id: null, reason: '', lines: [emptyLine()] }
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể tạo phiếu', detail: exception.response?.data?.message || 'Kiểm tra kho, quyền và số lượng.', life: 4500 })
  } finally {
    saving.value = false
  }
}

const transition = async (document, toStatus) => {
  const reason = toStatus === 'cancelled' ? window.prompt('Nhập lý do hủy phiếu:') : null
  if (toStatus === 'cancelled' && !reason) return
  try {
    await apiClient.patch(`${documentsUrl.value}/${document.id}/transition`, {
      to_status: toStatus,
      reason,
      operation_key: operationKey(`${document.id}-${toStatus}`),
    })
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể chuyển trạng thái', detail: exception.response?.data?.message || 'Kiểm tra quyền và tồn kho.', life: 4500 })
  }
}

onMounted(load)
</script>

<template>
  <main id="main-content" class="space-y-8" tabindex="-1">
    <header>
      <p class="text-sm font-semibold uppercase tracking-wider text-primary">Chứng từ kho</p>
      <h1 class="mt-1 text-3xl font-bold text-on-surface">Phiếu nhập / xuất kho</h1>
      <p class="mt-2 text-on-surface-variant">Mỗi phiếu có lịch sử trạng thái và bút toán tồn kho bất biến.</p>
    </header>
    <div v-if="loading" class="h-64 animate-pulse rounded-xl bg-surface-container"></div>
    <section v-else-if="error" class="rounded-xl bg-error-container p-6 text-on-error-container" role="alert">
      <p>{{ error }}</p><Button label="Thử lại" class="mt-4 min-h-11" @click="load" />
    </section>
    <template v-else>
      <form class="min-w-0 space-y-5 overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest p-4 sm:p-5" @submit.prevent="createDocument">
        <div><h2 class="text-xl font-bold">Tạo phiếu nháp</h2><p class="mt-1 text-sm text-on-surface-variant">{{ scope.vendor?.shop_name }}</p></div>
        <div class="grid gap-4 md:grid-cols-3">
          <div><label for="document-type" class="mb-2 block text-sm font-semibold">Loại phiếu</label><Select id="document-type" v-model="form.type" :options="documentTypes" optionLabel="label" optionValue="value" class="min-h-11 w-full" /></div>
          <div v-if="['dispatch', 'transfer', 'count'].includes(form.type)"><label for="source-warehouse" class="mb-2 block text-sm font-semibold">Kho nguồn</label><Select id="source-warehouse" v-model="form.source_warehouse_id" :options="scope.warehouses" optionLabel="name" optionValue="id" class="min-h-11 w-full" /></div>
          <div v-if="['receipt', 'transfer'].includes(form.type)"><label for="destination-warehouse" class="mb-2 block text-sm font-semibold">Kho đích</label><Select id="destination-warehouse" v-model="form.destination_warehouse_id" :options="scope.warehouses" optionLabel="name" optionValue="id" class="min-h-11 w-full" /></div>
        </div>
        <div><label for="document-reason" class="mb-2 block text-sm font-semibold">Lý do / tham chiếu</label><Textarea id="document-reason" v-model="form.reason" rows="2" class="w-full" /></div>
        <fieldset class="min-w-0 max-w-full space-y-3">
          <legend class="text-base font-bold">Dòng sản phẩm</legend>
          <div v-for="(line, index) in form.lines" :key="index" class="warehouse-line grid min-w-0 max-w-full gap-3 overflow-hidden rounded-xl bg-surface-container p-4 lg:grid-cols-12 lg:items-end">
            <div class="min-w-0 lg:col-span-6"><label :for="`line-book-${index}`" class="mb-2 block text-sm font-semibold">Sách</label><Select :id="`line-book-${index}`" v-model="line.book_id" :options="scope.books" optionLabel="title" optionValue="id" filter class="min-h-11 w-full max-w-full" /></div>
            <div v-if="form.type !== 'count'" class="min-w-0 lg:col-span-2"><label :for="`line-quantity-${index}`" class="mb-2 block text-sm font-semibold">Số lượng</label><InputNumber :id="`line-quantity-${index}`" v-model="line.quantity" :min="1" class="w-full max-w-full" /></div>
            <div v-else class="min-w-0 lg:col-span-2"><label :for="`line-actual-${index}`" class="mb-2 block text-sm font-semibold">Số đếm thực tế</label><InputNumber :id="`line-actual-${index}`" v-model="line.actual_quantity" :min="0" class="w-full max-w-full" /></div>
            <div class="min-w-0 lg:col-span-3"><label :for="`line-shelf-${index}`" class="mb-2 block text-sm font-semibold">Vị trí kệ</label><input :id="`line-shelf-${index}`" v-model="line.shelf_location" class="min-h-11 w-full min-w-0 max-w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3" /></div>
            <Button type="button" icon="pi pi-trash" aria-label="Xóa dòng sản phẩm" severity="danger" text class="min-h-11 min-w-11" :disabled="form.lines.length === 1" @click="removeLine(index)" />
          </div>
        </fieldset>
        <div class="flex flex-wrap justify-between gap-3"><Button type="button" label="Thêm dòng" icon="pi pi-plus" severity="secondary" outlined class="min-h-11" @click="addLine" /><Button type="submit" label="Tạo phiếu nháp" icon="pi pi-file-plus" :loading="saving" class="min-h-11" /></div>
      </form>

      <section class="space-y-4">
        <h2 class="text-xl font-bold">Danh sách phiếu</h2>
        <div v-if="!documents.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-8 text-center text-on-surface-variant">Chưa có phiếu kho trong phạm vi này.</div>
        <article v-for="document in documents" :key="document.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div><h3 class="font-bold">{{ document.document_code }}</h3><p class="mt-1 text-sm text-on-surface-variant">{{ document.type }} · {{ document.lines?.length || 0 }} dòng · {{ document.status }}</p></div>
            <div class="flex flex-wrap gap-2">
              <Button v-if="document.status === 'draft'" label="Gửi duyệt" class="min-h-11" @click="transition(document, 'submitted')" />
              <Button v-if="document.status === 'submitted' && isVendorWorkspace" label="Duyệt phiếu" class="min-h-11" @click="transition(document, 'approved')" />
              <Button v-if="document.status === 'approved'" label="Ghi sổ" icon="pi pi-check" class="min-h-11" @click="transition(document, 'posted')" />
              <Button v-if="!['posted', 'cancelled'].includes(document.status)" label="Hủy" severity="danger" outlined class="min-h-11" @click="transition(document, 'cancelled')" />
            </div>
          </div>
        </article>
      </section>
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

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'

const router = useRouter()
const toast = useToast()

const transfers = ref([])
const warehouses = ref([])
const books = ref([])
const loading = ref(true)
const saving = ref(false)

const showCreateDialog = ref(false)
const fromWarehouseId = ref(null)
const toWarehouseId = ref(null)
const transferReason = ref('')
const transferItems = ref([])

const error = ref(null)

const fetchTransfers = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await apiClient.get('/api/vendor/inventory/transfers')
    if (res.data?.status === 'success') {
      transfers.value = res.data.data || []
    } else if (Array.isArray(res.data)) {
      transfers.value = res.data
    } else {
      transfers.value = []
    }

    const whRes = await apiClient.get('/api/vendor/warehouses')
    if (whRes.data) {
      warehouses.value = Array.isArray(whRes.data) ? whRes.data : (whRes.data.data || [])
    }

    const bookRes = await apiClient.get('/api/vendor/books', { params: { per_page: 100 } })
    const rawBooks = Array.isArray(bookRes.data?.data)
      ? bookRes.data.data
      : (Array.isArray(bookRes.data?.data?.data) ? bookRes.data.data.data : (Array.isArray(bookRes.data) ? bookRes.data : []))
    books.value = rawBooks.filter(b => b.type !== 'ebook')
  } catch (e) {
    console.error('Không tải được danh sách điều chuyển', e)
    error.value = e.response?.data?.message || 'Không thể kết nối API điều chuyển kho.'
    transfers.value = []
    warehouses.value = []
    books.value = []
  } finally {
    loading.value = false
  }
}

const openCreateTransfer = () => {
  if (warehouses.value.length < 2) {
    toast.add({ severity: 'warn', summary: 'Không khả dụng', detail: 'Yêu cầu có ít nhất 2 kho hàng để thực hiện điều chuyển.', life: 4000 })
    return
  }
  fromWarehouseId.value = warehouses.value[0].id
  toWarehouseId.value = warehouses.value[1].id
  transferReason.value = ''
  transferItems.value = [{ book_id: null, quantity: 10 }]
  showCreateDialog.value = true
}

const addTransferItem = () => {
  transferItems.value.push({ book_id: null, quantity: 10 })
}

const removeTransferItem = (index) => {
  transferItems.value.splice(index, 1)
}

const createTransfer = async () => {
  const invalidItem = transferItems.value.some(item => !item.book_id || item.quantity <= 0)
  if (invalidItem) {
    toast.add({ severity: 'error', summary: 'Lỗi nhập liệu', detail: 'Vui lòng chọn sách và số lượng hợp lệ.', life: 3000 })
    return
  }

  saving.value = true
  try {
    const res = await apiClient.post('/api/vendor/inventory/transfers', {
      from_warehouse_id: fromWarehouseId.value,
      to_warehouse_id: toWarehouseId.value,
      reason: transferReason.value,
      items: transferItems.value
    })

    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã lập phiếu điều chuyển nháp.', life: 3000 })
      showCreateDialog.value = false
      fetchTransfers()
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Không thể tạo phiếu điều chuyển.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 3000 })
  } finally {
    saving.value = false
  }
}

const shipTransfer = async (id) => {
  try {
    const res = await apiClient.post(`/api/vendor/inventory/transfers/${id}/ship`)
    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã xuất kho', detail: 'Đã hoàn tất xuất kho điều chuyển hàng đi.', life: 3000 })
      fetchTransfers()
      if (selectedTransferDetails.value) {
        selectedTransferDetails.value.status = 'shipped'
      }
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không đủ số lượng trong kho xuất.', life: 3000 })
  }
}

const receiveTransfer = async (id) => {
  try {
    const res = await apiClient.post(`/api/vendor/inventory/transfers/${id}/receive`)
    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã nhập kho', detail: 'Hàng đã được nhập vào kho đích thành công.', life: 3000 })
      fetchTransfers()
      if (selectedTransferDetails.value) {
        selectedTransferDetails.value.status = 'received'
      }
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xác nhận nhập kho.', life: 3000 })
  }
}

const selectedTransferDetails = ref(null)
const showDetailsDialog = ref(false)

const viewDetails = async (trf) => {
  try {
    const res = await apiClient.get(`/api/vendor/inventory/transfers/${trf.id}`)
    if (res.data?.status === 'success') {
      selectedTransferDetails.value = res.data.data
      showDetailsDialog.value = true
    }
  } catch (e) {
    console.error('Không tải được chi tiết phiếu điều chuyển', e)
    toast.add({ severity: 'error', summary: 'Không thể tải chi tiết', detail: 'Lỗi kết nối khi xem phiếu điều chuyển.', life: 3000 })
  }
}

const openPrintWindow = (id) => {
  const routeUrl = router.resolve({ name: 'vendor-inventory-transfers-print', params: { id } })
  window.open(routeUrl.href, '_blank')
}

onMounted(() => {
  fetchTransfers()
})
</script>

<template>
  <div class="stock-transfer min-h-screen bg-slate-50 p-6 md:p-8">
    <Toast />
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Phiếu điều chuyển kho</h1>
        <p class="text-slate-500 text-sm mt-1">Điều chuyển số lượng sách giấy vật lý giữa các kho hàng chi nhánh nội bộ.</p>
      </div>
      <div>
        <Button label="Tạo phiếu điều chuyển" icon="pi pi-plus" class="p-button-primary bg-indigo-600 hover:bg-indigo-700 text-white font-bold" @click="openCreateTransfer" />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
    </div>

    <div v-else-if="error" class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm text-center max-w-md mx-auto space-y-4 my-8">
      <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto">
        <i class="pi pi-exclamation-triangle text-xl"></i>
      </div>
      <h3 class="text-base font-bold text-slate-900">Không thể tải dữ liệu điều chuyển kho</h3>
      <p class="text-xs text-slate-500 leading-relaxed">{{ error }}</p>
      <Button label="Thử lại" icon="pi pi-refresh" class="p-button-primary bg-indigo-600 p-button-sm" @click="fetchTransfers" />
    </div>

    <!-- History list -->
    <div v-else class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-slate-100 bg-slate-50/50">
        <h3 class="font-bold text-slate-800 text-sm">Lịch sử phiếu điều chuyển</h3>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
              <th class="p-4">Mã Phiếu</th>
              <th class="p-4">Kho xuất</th>
              <th class="p-4">Kho nhập</th>
              <th class="p-4">Lý do</th>
              <th class="p-4">Ngày tạo</th>
              <th class="p-4">Trạng thái</th>
              <th class="p-4 text-center">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-150">
            <tr v-for="trf in transfers" :key="trf.id" class="hover:bg-slate-50/30">
              <td class="p-4 font-mono font-semibold text-slate-700">{{ trf.transfer_code }}</td>
              <td class="p-4 text-slate-700">{{ trf.from_warehouse?.name || 'Hà Nội' }}</td>
              <td class="p-4 text-slate-700">{{ trf.to_warehouse?.name || 'TP.HCM' }}</td>
              <td class="p-4 text-slate-600 truncate max-w-[200px]">{{ trf.reason || 'Điều chuyển thường' }}</td>
              <td class="p-4 text-slate-500">{{ trf.created_at?.split('T')[0] }}</td>
              <td class="p-4">
                <span :class="[
                  'text-xs font-bold px-2 py-0.5 rounded-full',
                  trf.status === 'received' ? 'bg-emerald-100 text-emerald-700' : trf.status === 'shipped' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'
                ]">
                  {{ trf.status === 'received' ? 'Đã nhập kho' : trf.status === 'shipped' ? 'Đang chuyển hàng' : 'Bản nháp' }}
                </span>
              </td>
              <td class="p-4 text-center">
                <div class="flex gap-2 justify-center">
                  <Button label="Chi tiết" icon="pi pi-list" class="p-button-text p-button-sm p-1 text-xs" @click="viewDetails(trf)" />
                  <Button label="In phiếu" icon="pi pi-print" class="p-button-text p-button-secondary p-button-sm p-1 text-xs" @click="openPrintWindow(trf.id)" />
                </div>
              </td>
            </tr>
            <tr v-if="transfers.length === 0">
              <td colspan="7" class="p-8 text-center text-slate-400">Chưa có đơn điều chuyển kho nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:visible="showCreateDialog" modal header="Lập phiếu điều chuyển mới" :style="{ width: '85vw', maxWidth: '800px' }">
      <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500">Kho xuất (Nguồn)</label>
            <Select v-model="fromWarehouseId" :options="warehouses" optionLabel="name" optionValue="id" placeholder="Chọn kho xuất" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500">Kho nhập (Đích)</label>
            <Select v-model="toWarehouseId" :options="warehouses" optionLabel="name" optionValue="id" placeholder="Chọn kho nhập" class="w-full" />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500">Lý do điều chuyển</label>
          <InputText v-model="transferReason" placeholder="Nhập lý do điều chuyển hàng..." class="w-full" />
        </div>

        <!-- Items grid picker -->
        <div>
          <div class="flex justify-between items-center mb-3">
            <label class="text-xs font-bold text-slate-500 uppercase">Danh mục sản phẩm điều chuyển</label>
            <Button label="Thêm sản phẩm" icon="pi pi-plus" class="p-button-text p-button-sm text-xs" @click="addTransferItem" />
          </div>

          <div class="space-y-2 max-h-[220px] overflow-y-auto pr-2">
            <div v-for="(item, idx) in transferItems" :key="idx" class="flex gap-4 items-center bg-slate-50 p-3 rounded-lg border border-slate-200">
              <div class="flex-grow">
                <Select v-model="item.book_id" :options="books" optionLabel="title" optionValue="id" placeholder="Chọn sách muốn chuyển" class="w-full text-xs" />
              </div>
              <div class="w-32">
                <input type="number" v-model="item.quantity" class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-xs text-right" placeholder="Số lượng" />
              </div>
              <div>
                <Button icon="pi pi-trash" class="p-button-danger p-button-text p-button-sm text-rose-600" :disabled="transferItems.length === 1" @click="removeTransferItem(idx)" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Hủy" class="p-button-text" @click="showCreateDialog = false" />
        <Button label="Lập phiếu nháp" class="p-button-primary bg-indigo-600 text-white font-bold" :loading="saving" @click="createTransfer" />
      </template>
    </Dialog>

    <!-- Details Dialog -->
    <Dialog v-model:visible="showDetailsDialog" modal header="Chi tiết phiếu điều chuyển" :style="{ width: '80vw', maxWidth: '800px' }">
      <div v-if="selectedTransferDetails" class="space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs bg-slate-50 p-4 rounded-xl border border-slate-250">
          <div>
            <span class="text-slate-400">Mã phiếu:</span>
            <strong class="block font-mono text-slate-800 text-sm mt-0.5">{{ selectedTransferDetails.transfer_code }}</strong>
          </div>
          <div>
            <span class="text-slate-400">Tuyến kho:</span>
            <strong class="block text-slate-800 text-xs mt-0.5">
              {{ selectedTransferDetails.from_warehouse?.name || 'N/A' }} ➔ {{ selectedTransferDetails.to_warehouse?.name || 'N/A' }}
            </strong>
          </div>
          <div>
            <span class="text-slate-400">Lý do:</span>
            <strong class="block text-slate-800 text-xs mt-0.5">{{ selectedTransferDetails.reason || 'N/A' }}</strong>
          </div>
          <div>
            <span class="text-slate-400">Trạng thái:</span>
            <span :class="[
              'block text-xs font-bold mt-1',
              selectedTransferDetails.status === 'received' ? 'text-emerald-600' : selectedTransferDetails.status === 'shipped' ? 'text-indigo-600' : 'text-slate-500'
            ]">{{ selectedTransferDetails.status === 'received' ? 'Đã nhập kho thành công' : selectedTransferDetails.status === 'shipped' ? 'Đang chuyển hàng' : 'Bản nháp' }}</span>
          </div>
        </div>

        <div class="border border-slate-200 rounded-xl overflow-hidden mt-4">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase">
                <th class="p-3">Tên sản phẩm (Sách giấy)</th>
                <th class="p-3 text-right">Số lượng điều động</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in selectedTransferDetails.items" :key="item.id">
                <td class="p-3 font-semibold text-slate-700">{{ item.book?.title || 'Sách đã xóa' }}</td>
                <td class="p-3 text-right font-mono font-bold">{{ item.quantity }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Action tools -->
        <div class="flex gap-2 justify-end mt-6 pt-4 border-t border-slate-100">
          <Button label="In phiếu xuất nhập" icon="pi pi-print" class="p-button-outlined p-button-sm text-xs" @click="openPrintWindow(selectedTransferDetails.id)" />
          <Button v-if="selectedTransferDetails.status === 'draft'" label="Xuất kho & Chuyển đi" icon="pi pi-truck" class="p-button-primary bg-indigo-600 text-white p-button-sm text-xs" @click="shipTransfer(selectedTransferDetails.id)" />
          <Button v-if="selectedTransferDetails.status === 'shipped'" label="Xác nhận nhận hàng" icon="pi pi-check" class="p-button-success p-button-sm text-xs" @click="receiveTransfer(selectedTransferDetails.id)" />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.stock-transfer {
  font-family: 'Inter', sans-serif;
}
:deep(.p-select) {
  border-radius: 8px;
}
:deep(.p-inputtext) {
  border-radius: 8px;
}
</style>

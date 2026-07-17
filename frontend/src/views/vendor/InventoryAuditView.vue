<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'

const toast = useToast()
const loading = ref(true)
const audits = ref([])
const warehouses = ref([])
const books = ref([])

const showCreateDialog = ref(false)
const selectedWarehouseId = ref(null)
const auditPeriod = ref(nowMonthYear())
const newAuditItems = ref([])

function nowMonthYear() {
  const d = new Date()
  return `Tháng ${d.getMonth() + 1}/${d.getFullYear()}`
}

const fetchAudits = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/vendor/inventory/audits')
    if (res.data?.status === 'success') {
      audits.value = res.data.data
    }

    const whRes = await apiClient.get('/api/vendor/warehouses')
    if (whRes.data) {
      warehouses.value = whRes.data
    }

    const bookRes = await apiClient.get('/api/books')
    if (bookRes.data?.data) {
      books.value = bookRes.data.data.filter(b => b.type !== 'ebook')
    }
  } catch (e) {
    console.error('Không tải được dữ liệu kiểm kê', e)
    // Fallback Mock Data
    audits.value = [
      { id: 1, warehouse: { name: 'Kho Trung Tâm A' }, auditor: { name: 'Đặng Thế Anh' }, audit_period: 'Tháng 07/2026', status: 'completed', created_at: '2026-07-10' },
      { id: 2, warehouse: { name: 'Kho Trung Tâm A' }, auditor: { name: 'Đặng Thế Anh' }, audit_period: 'Tháng 07/2026', status: 'draft', created_at: '2026-07-13' },
    ]
  } finally {
    loading.value = false
  }
}

const openCreateAudit = () => {
  if (warehouses.value.length === 0) {
    toast.add({ severity: 'warn', summary: 'Không tìm thấy kho', detail: 'Vui lòng thêm kho hàng trước.', life: 3000 })
    return
  }
  selectedWarehouseId.value = warehouses.value[0].id
  newAuditItems.value = books.value.map(b => ({
    book_id: b.id,
    title: b.title,
    sku: b.sku || 'SKU-' + b.id,
    physical_qty: b.stock || 0,
  }))
  showCreateDialog.value = true
}

const createAudit = async () => {
  try {
    const res = await apiClient.post('/api/vendor/inventory/audits', {
      warehouse_id: selectedWarehouseId.value,
      audit_period: auditPeriod.value,
      items: newAuditItems.value.map(item => ({
        book_id: item.book_id,
        physical_qty: parseInt(item.physical_qty || 0),
      }))
    })

    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã lập phiếu kiểm kê nháp.', life: 3000 })
      showCreateDialog.value = false
      fetchAudits()
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tạo phiếu kiểm kê.', life: 3000 })
  }
}

const completeAudit = async (id) => {
  try {
    const res = await apiClient.post(`/api/vendor/inventory/audits/${id}/complete`)
    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã đối soát', detail: 'Số tồn hệ thống đã được đồng bộ với thực tế.', life: 3000 })
      fetchAudits()
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể đối soát phiếu.', life: 3000 })
  }
}

const selectedAuditDetails = ref(null)
const showDetailsDialog = ref(false)

const viewAuditDetails = async (audit) => {
  try {
    const res = await apiClient.get(`/api/vendor/inventory/audits/${audit.id}`)
    if (res.data?.status === 'success') {
      selectedAuditDetails.value = res.data.data
      showDetailsDialog.value = true
    }
  } catch (e) {
    // Mock detail fallback
    selectedAuditDetails.value = {
      ...audit,
      items: [
        { id: 10, book: { title: 'Đại Gia Gatsby' }, system_qty: 45, physical_qty: 43, difference: -2 },
        { id: 11, book: { title: 'Chiến Lược Đại Dương Xanh' }, system_qty: 20, physical_qty: 20, difference: 0 }
      ]
    }
    showDetailsDialog.value = true
  }
}

onMounted(() => {
  fetchAudits()
})
</script>

<template>
  <div class="inventory-audit min-h-screen bg-slate-50 p-6 md:p-8">
    <Toast />
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Kiểm kê kho hàng định kỳ</h1>
        <p class="text-slate-500 text-sm mt-1">Đối chiếu số lượng tồn kho vật lý thực tế và số lượng ghi nhận trên hệ thống.</p>
      </div>
      <div>
        <Button label="Lập phiếu kiểm kê mới" icon="pi pi-plus" class="p-button-primary bg-indigo-600 hover:bg-indigo-700 text-white font-bold" @click="openCreateAudit" />
      </div>
    </div>

    <!-- Audits list table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-slate-100 bg-slate-50/50">
        <h3 class="font-bold text-slate-800 text-sm">Lịch sử các phiếu kiểm kê</h3>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
              <th class="p-4">Mã Phiếu</th>
              <th class="p-4">Kho hàng</th>
              <th class="p-4">Kỳ kiểm kê</th>
              <th class="p-4">Người thực hiện</th>
              <th class="p-4">Ngày tạo</th>
              <th class="p-4">Trạng thái</th>
              <th class="p-4 text-center">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-150">
            <tr v-for="audit in audits" :key="audit.id" class="hover:bg-slate-50/30">
              <td class="p-4 font-mono font-semibold text-slate-700">AUD-000{{ audit.id }}</td>
              <td class="p-4 text-slate-700">{{ audit.warehouse?.name || 'Chưa rõ' }}</td>
              <td class="p-4 text-slate-600">{{ audit.audit_period }}</td>
              <td class="p-4 text-slate-600">{{ audit.auditor?.name || 'Nhân viên kho' }}</td>
              <td class="p-4 text-slate-500">{{ audit.created_at?.split('T')[0] }}</td>
              <td class="p-4">
                <span :class="[
                  'text-xs font-bold px-2 py-0.5 rounded-full',
                  audit.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                ]">
                  {{ audit.status === 'completed' ? 'Đã đối soát' : 'Bản nháp' }}
                </span>
              </td>
              <td class="p-4 text-center">
                <div class="flex gap-2 justify-center">
                  <Button label="Chi tiết" icon="pi pi-list" class="p-button-text p-button-sm p-1 text-xs" @click="viewAuditDetails(audit)" />
                  <Button v-if="audit.status === 'draft'" label="Đối soát" icon="pi pi-check-circle" class="p-button-outlined p-button-success p-button-sm p-1 text-xs" @click="completeAudit(audit.id)" />
                </div>
              </td>
            </tr>
            <tr v-if="audits.length === 0">
              <td colspan="7" class="p-8 text-center text-slate-400">Không tìm thấy phiếu kiểm kê nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:visible="showCreateDialog" modal header="Lập phiếu kiểm kê mới" :style="{ width: '80vw', maxWidth: '800px' }">
      <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500">Chọn kho hàng</label>
            <Select v-model="selectedWarehouseId" :options="warehouses" optionLabel="name" optionValue="id" placeholder="Chọn kho hàng" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500">Kỳ kiểm kê</label>
            <InputText v-model="auditPeriod" placeholder="Ví dụ: Tháng 07/2026" class="w-full" />
          </div>
        </div>

        <div class="border border-slate-200 rounded-xl overflow-hidden mt-4">
          <div class="max-h-[300px] overflow-y-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-250 text-slate-500 font-bold uppercase">
                  <th class="p-3">Sách / SKU</th>
                  <th class="p-3 text-right w-40">Số lượng thực tế (Vật lý)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="item in newAuditItems" :key="item.book_id">
                  <td class="p-3">
                    <span class="font-bold text-slate-700 block">{{ item.title }}</span>
                    <span class="text-slate-400 font-mono text-[10px]">{{ item.sku }}</span>
                  </td>
                  <td class="p-3 text-right">
                    <input type="number" v-model="item.physical_qty" class="w-24 text-right px-2 py-1 bg-white border border-slate-300 rounded text-xs" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Hủy" class="p-button-text" @click="showCreateDialog = false" />
        <Button label="Tạo phiếu kiểm kê nháp" class="p-button-primary bg-indigo-600 text-white" @click="createAudit" />
      </template>
    </Dialog>

    <!-- Details Dialog -->
    <Dialog v-model:visible="showDetailsDialog" modal header="Chi tiết phiếu kiểm kê" :style="{ width: '80vw', maxWidth: '850px' }">
      <div v-if="selectedAuditDetails" class="space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs bg-slate-50 p-4 rounded-xl border border-slate-250">
          <div>
            <span class="text-slate-400">Mã phiếu:</span>
            <strong class="block font-mono text-slate-800 text-sm mt-0.5">AUD-000{{ selectedAuditDetails.id }}</strong>
          </div>
          <div>
            <span class="text-slate-400">Kho hàng:</span>
            <strong class="block text-slate-800 text-sm mt-0.5">{{ selectedAuditDetails.warehouse?.name || 'N/A' }}</strong>
          </div>
          <div>
            <span class="text-slate-400">Người kiểm:</span>
            <strong class="block text-slate-800 text-sm mt-0.5">{{ selectedAuditDetails.auditor?.name || 'N/A' }}</strong>
          </div>
          <div>
            <span class="text-slate-400">Trạng thái:</span>
            <span :class="[
              'block text-xs font-bold mt-1',
              selectedAuditDetails.status === 'completed' ? 'text-emerald-600' : 'text-amber-600'
            ]">{{ selectedAuditDetails.status === 'completed' ? 'Đã đối soát & Đồng bộ' : 'Bản nháp' }}</span>
          </div>
        </div>

        <div class="border border-slate-200 rounded-xl overflow-hidden mt-4">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase">
                <th class="p-3">Sách</th>
                <th class="p-3 text-right">Số lượng hệ thống</th>
                <th class="p-3 text-right">Số lượng thực tế</th>
                <th class="p-3 text-right">Chênh lệch</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in selectedAuditDetails.items" :key="item.id">
                <td class="p-3 font-semibold text-slate-700">{{ item.book?.title || 'Sách đã xóa' }}</td>
                <td class="p-3 text-right font-mono">{{ item.system_qty }}</td>
                <td class="p-3 text-right font-mono">{{ item.physical_qty }}</td>
                <td class="p-3 text-right">
                  <span :class="[
                    'font-mono font-bold px-2 py-0.5 rounded-full',
                    item.difference === 0 ? 'bg-slate-100 text-slate-600' : item.difference > 0 ? 'bg-green-100 text-green-700' : 'bg-rose-100 text-rose-700'
                  ]">
                    {{ item.difference > 0 ? '+' : '' }}{{ item.difference }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.inventory-audit {
  font-family: 'Inter', sans-serif;
}
:deep(.p-select) {
  border-radius: 8px;
}
:deep(.p-inputtext) {
  border-radius: 8px;
}
</style>

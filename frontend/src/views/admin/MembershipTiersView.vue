<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'

const toast = useToast()
const tiers = ref([])
const loading = ref(true)
const error = ref(null)

const showEditDialog = ref(false)
const selectedTier = ref({
  id: null,
  name: '',
  min_points: 0,
  discount_percent: 0,
  benefits: '',
})

const fetchTiers = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await apiClient.get('/api/admin/membership-tiers')
    if (res.data?.status === 'success') {
      tiers.value = res.data.data || []
    } else if (Array.isArray(res.data)) {
      tiers.value = res.data
    } else {
      tiers.value = []
    }
  } catch (e) {
    console.error('Không tải được danh sách hạng thành viên', e)
    tiers.value = []
    error.value = e.response?.data?.message || 'Không thể kết nối API hạng thành viên.'
  } finally {
    loading.value = false
  }
}

const openCreateTier = () => {
  selectedTier.value = { id: null, name: '', min_points: 0, discount_percent: 0, benefits: '' }
  showEditDialog.value = true
}

const openEditTier = (tier) => {
  selectedTier.value = { ...tier }
  showEditDialog.value = true
}

const saveTier = async () => {
  if (!selectedTier.value.name) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Tên hạng thành viên không được để trống.', life: 3000 })
    return
  }

  try {
    const t = selectedTier.value
    let res
    if (t.id) {
      res = await apiClient.put(`/api/admin/membership-tiers/${t.id}`, t)
    } else {
      res = await apiClient.post('/api/admin/membership-tiers', t)
    }

    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã lưu cấu hình hạng thành viên.', life: 3000 })
      showEditDialog.value = false
      fetchTiers()
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi lưu dữ liệu', detail: 'Không thể lưu cấu hình hạng thành viên.', life: 3000 })
  }
}

const deleteTier = async (id) => {
  if (!confirm('Bạn có chắc chắn muốn xóa hạng thành viên này?')) return
  try {
    const res = await apiClient.delete(`/api/admin/membership-tiers/${id}`)
    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Xóa hạng thành viên thành công.', life: 3000 })
      fetchTiers()
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa hạng thành viên.', life: 3000 })
  }
}

onMounted(() => {
  fetchTiers()
})
</script>

<template>
  <div class="membership-tiers min-h-screen bg-slate-50 p-6 md:p-8">
    <Toast />
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Phân khúc & Hạng thành viên (CRM)</h1>
        <p class="text-slate-500 text-sm mt-1">Quản lý tích điểm khách hàng thân thiết, ưu đãi chiết khấu và lợi ích VIP.</p>
      </div>
      <div class="flex gap-2">
        <Button label="Thêm hạng mới" icon="pi pi-plus" class="min-h-11 border-brand-green-strong bg-brand-green-strong text-white hover:bg-commerce font-bold" @click="openCreateTier" />
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" role="alert" class="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-center space-y-3 mb-8">
      <h3 class="text-base font-bold text-rose-800">Không thể tải danh sách hạng thành viên</h3>
      <p class="text-xs text-rose-600">{{ error }}</p>
      <Button label="Thử lại" icon="pi pi-refresh" class="p-button-danger p-button-sm text-xs bg-rose-600 text-white" @click="fetchTiers" />
    </div>

    <!-- Tiers list -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
        <h3 class="font-bold text-slate-800 text-sm">Cấu hình cấp độ VIP & Quyền lợi</h3>
      </div>

      <div v-if="loading" role="status" aria-live="polite" class="flex justify-center p-12">
        <i class="pi pi-spin pi-spinner text-3xl text-brand-green-strong"></i>
      </div>

      <div v-else-if="!error" class="overflow-x-auto" role="region" aria-label="Danh sách hạng thành viên" tabindex="0">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
              <th class="p-4">Tên hạng</th>
              <th class="p-4">Điểm tối thiểu</th>
              <th class="p-4">Chiết khấu (%)</th>
              <th class="p-4">Quyền lợi đặc quyền</th>
              <th class="p-4 text-center">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-150">
            <tr v-for="tier in tiers" :key="tier.id" class="hover:bg-slate-50/30">
              <td class="p-4 font-bold text-slate-800">
                <span class="ui-badge-commerce inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-semibold">
                  <i class="pi pi-star-fill text-xs"></i> {{ tier.name }}
                </span>
              </td>
              <td class="p-4 font-mono font-semibold text-slate-700">{{ (tier.min_points || 0).toLocaleString() }} điểm</td>
              <td class="p-4 font-bold text-rose-600">{{ tier.discount_percent || 0 }}%</td>
              <td class="p-4 text-slate-600 text-xs max-w-sm">{{ tier.benefits || '—' }}</td>
              <td class="p-4 text-center">
                <div class="flex gap-2 justify-center">
                  <Button label="Sửa" icon="pi pi-pencil" class="min-h-11 p-button-text p-button-sm px-3 text-xs" @click="openEditTier(tier)" />
                  <Button label="Xóa" icon="pi pi-trash" class="min-h-11 p-button-text p-button-danger p-button-sm px-3 text-xs text-rose-600" @click="deleteTier(tier.id)" />
                </div>
              </td>
            </tr>
            <tr v-if="tiers.length === 0">
              <td colspan="5" class="p-8 text-center text-slate-400">Chưa có hạng thành viên nào. Bấm "Thêm hạng mới" để tạo.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit Dialog -->
    <Dialog v-model:visible="showEditDialog" modal :header="selectedTier.id ? 'Sửa hạng thành viên' : 'Thêm hạng thành viên mới'" :style="{ width: '90vw', maxWidth: '500px' }">
      <div class="space-y-4">
        <div class="flex flex-col gap-1.5">
          <label for="membership-name" class="text-xs font-bold text-slate-500">Tên hạng thành viên <span class="text-rose-500">*</span></label>
          <InputText id="membership-name" v-model="selectedTier.name" placeholder="Ví dụ: Vàng, Kim Cương" class="w-full min-h-11 text-sm" />
        </div>
        
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label for="membership-points" class="text-xs font-bold text-slate-500">Điểm tích lũy tối thiểu</label>
            <input id="membership-points" type="number" v-model="selectedTier.min_points" class="w-full min-h-11 px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label for="membership-discount" class="text-xs font-bold text-slate-500">Chiết khấu đơn hàng (%)</label>
            <input id="membership-discount" type="number" v-model="selectedTier.discount_percent" class="w-full min-h-11 px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs" />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label for="membership-benefits" class="text-sm font-bold text-slate-700">Mô tả chương trình</label>
          <InputText id="membership-benefits" v-model="selectedTier.benefits" aria-describedby="membership-benefits-help" placeholder="Ví dụ: Quà tặng theo chương trình đã công bố" class="w-full min-h-11 text-base" />
          <p id="membership-benefits-help" class="text-sm leading-5 text-slate-600">Đây là nội dung mô tả. Chỉ chiết khấu và tích điểm có dấu xác minh ở trang khách hàng mới được xem là quyền lợi tự động đang hoạt động.</p>
        </div>
      </div>
      
      <template #footer>
        <Button label="Hủy" class="p-button-text p-button-sm text-xs" @click="showEditDialog = false" />
        <Button label="Lưu thiết lập" class="min-h-11 border-brand-green-strong bg-brand-green-strong text-white font-bold" @click="saveTier" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.membership-tiers {
  font-family: 'Inter', sans-serif;
}
:deep(.p-inputtext) {
  border-radius: 8px;
}
</style>

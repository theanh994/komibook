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
  try {
    const res = await apiClient.get('/api/admin/membership-tiers')
    if (res.data?.status === 'success') {
      tiers.value = res.data.data
    }
  } catch (e) {
    console.error('Không tải được danh sách hạng thành viên', e)
    // Fallback Mock Tiers
    tiers.value = [
      { id: 1, name: 'Bạc', min_points: 0, discount_percent: 0, benefits: 'Tích điểm đổi quà (1%), Theo dõi lịch sử đọc' },
      { id: 2, name: 'Vàng', min_points: 2000, discount_percent: 10, benefits: 'Giảm 10% mọi đơn hàng, Voucher sinh nhật' },
      { id: 3, name: 'Kim Cương (VIP)', min_points: 5000, discount_percent: 15, benefits: 'Miễn phí Vận chuyển, Quà tặng đặc quyền, Hỗ trợ 24/7' },
    ]
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
  } catch (e) {
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
  } catch (e) {
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
        <Button label="Thêm hạng mới" icon="pi pi-plus" class="p-button-primary bg-indigo-600 hover:bg-indigo-700 text-white font-bold" @click="openCreateTier" />
      </div>
    </div>

    <!-- Segments Bento Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden">
        <div class="flex justify-between items-start mb-4">
          <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center">
            <i class="pi pi-book"></i>
          </div>
          <span class="bg-emerald-100 text-emerald-700 text-[10px] px-2 py-0.5 rounded-full font-bold">+15% tháng này</span>
        </div>
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Độc giả Tích cực</h4>
        <h2 class="text-2xl font-black text-slate-900 mt-1">12,450 độc giả</h2>
        <p class="text-[10px] text-slate-400 mt-2">Đọc trên 3 cuốn sách mỗi tháng</p>
      </div>

      <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden">
        <div class="flex justify-between items-start mb-4">
          <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center">
            <i class="pi pi-moon"></i>
          </div>
          <span class="bg-rose-100 text-rose-700 text-[10px] px-2 py-0.5 rounded-full font-bold">+2% tháng này</span>
        </div>
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Người dùng Ngủ đông</h4>
        <h2 class="text-2xl font-black text-slate-900 mt-1">3,200 tài khoản</h2>
        <p class="text-[10px] text-slate-400 mt-2">Không hoạt động trong 60 ngày</p>
      </div>

      <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden">
        <div class="flex justify-between items-start mb-4">
          <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
            <i class="pi pi-wallet"></i>
          </div>
          <span class="bg-emerald-100 text-emerald-700 text-[10px] px-2 py-0.5 rounded-full font-bold">+8% tháng này</span>
        </div>
        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Chi tiêu cao (VIP)</h4>
        <h2 class="text-2xl font-black text-slate-900 mt-1">850 thành viên</h2>
        <p class="text-[10px] text-slate-400 mt-2">Mua sắm trên 2.000.000đ/tháng</p>
      </div>
    </div>

    <!-- Tiers list -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-4 border-b border-slate-100 bg-slate-50/50">
        <h3 class="font-bold text-slate-800 text-sm">Cấu hình cấp độ VIP & Quyền lợi</h3>
      </div>

      <div v-if="loading" class="flex justify-center p-12">
        <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
      </div>

      <div v-else class="overflow-x-auto">
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
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full font-semibold">
                  <i class="pi pi-star-fill text-xs"></i> {{ tier.name }}
                </span>
              </td>
              <td class="p-4 font-mono font-semibold text-slate-700">{{ tier.min_points.toLocaleString() }} điểm</td>
              <td class="p-4 font-bold text-rose-600">{{ tier.discount_percent }}%</td>
              <td class="p-4 text-slate-600 text-xs max-w-sm">{{ tier.benefits }}</td>
              <td class="p-4 text-center">
                <div class="flex gap-2 justify-center">
                  <Button label="Sửa" icon="pi pi-pencil" class="p-button-text p-button-sm p-1 text-xs" @click="openEditTier(tier)" />
                  <Button label="Xóa" icon="pi pi-trash" class="p-button-text p-button-danger p-button-sm p-1 text-xs text-rose-600" @click="deleteTier(tier.id)" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit Dialog -->
    <Dialog v-model:visible="showEditDialog" modal :header="selectedTier.id ? 'Sửa hạng thành viên' : 'Thêm hạng thành viên mới'" :style="{ width: '90vw', maxWidth: '500px' }">
      <div class="space-y-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500">Tên hạng thành viên <span class="text-rose-500">*</span></label>
          <InputText v-model="selectedTier.name" placeholder="Ví dụ: Vàng, Kim Cương" class="w-full text-sm" />
        </div>
        
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500">Điểm tích lũy tối thiểu</label>
            <input type="number" v-model="selectedTier.min_points" class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500">Chiết khấu đơn hàng (%)</label>
            <input type="number" v-model="selectedTier.discount_percent" class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs" />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500">Quyền lợi và Lợi ích nổi bật</label>
          <InputText v-model="selectedTier.benefits" placeholder="Nhập các quyền lợi, cách nhau bằng dấu phẩy..." class="w-full text-sm" />
        </div>
      </div>
      
      <template #footer>
        <Button label="Hủy" class="p-button-text p-button-sm text-xs" @click="showEditDialog = false" />
        <Button label="Lưu thiết lập" class="p-button-primary bg-indigo-600 text-white p-button-sm text-xs font-bold" @click="saveTier" />
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

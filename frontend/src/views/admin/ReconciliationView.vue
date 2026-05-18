<script setup>
import { ref, computed } from 'vue'
import { useToast } from 'primevue/usetoast'

const toast = useToast()

const periodFilter = ref('Tháng 10/2023')
const statusFilter = ref('')
const activeTab = ref('all')

const reconciliations = ref([
  { id: 'RS-001', vendor: 'NXB Kim Đồng', period: '01/10 – 31/10/2023', totalOrders: 156, totalRevenue: 124500000, commission: 24900000, netPayable: 99600000, status: 'pending' },
  { id: 'RS-002', vendor: 'NXB Trẻ', period: '01/10 – 31/10/2023', totalOrders: 98, totalRevenue: 78200000, commission: 15640000, netPayable: 62560000, status: 'pending' },
  { id: 'RS-003', vendor: 'Nhã Nam', period: '01/10 – 31/10/2023', totalOrders: 67, totalRevenue: 53600000, commission: 10720000, netPayable: 42880000, status: 'approved' },
  { id: 'RS-004', vendor: 'NXB Thanh Niên', period: '01/09 – 30/09/2023', totalOrders: 134, totalRevenue: 107200000, commission: 21440000, netPayable: 85760000, status: 'paid' },
  { id: 'RS-005', vendor: 'IPM', period: '01/09 – 30/09/2023', totalOrders: 89, totalRevenue: 71200000, commission: 14240000, netPayable: 56960000, status: 'paid' },
])

const tabs = [
  { key: 'all', label: 'Tất cả', icon: 'list' },
  { key: 'pending', label: 'Chờ duyệt', icon: 'pending_actions' },
  { key: 'approved', label: 'Đã duyệt', icon: 'check_circle' },
  { key: 'paid', label: 'Đã thanh toán', icon: 'payments' },
]

const filteredData = computed(() => {
  if (activeTab.value === 'all') return reconciliations.value
  return reconciliations.value.filter(r => r.status === activeTab.value)
})

const kpis = computed(() => ({
  totalRevenue: reconciliations.value.reduce((s, r) => s + r.totalRevenue, 0),
  totalCommission: reconciliations.value.reduce((s, r) => s + r.commission, 0),
  pendingCount: reconciliations.value.filter(r => r.status === 'pending').length,
  paidCount: reconciliations.value.filter(r => r.status === 'paid').length,
}))

const formatVND = (val) => new Intl.NumberFormat('vi-VN').format(val) + ' ₫'

const statusConfig = {
  pending: { label: 'Chờ duyệt', bg: 'bg-yellow-100', text: 'text-yellow-800' },
  approved: { label: 'Đã duyệt', bg: 'bg-blue-100', text: 'text-blue-800' },
  paid: { label: 'Đã thanh toán', bg: 'bg-green-100', text: 'text-green-800' },
}

const approveItem = (item) => {
  item.status = 'approved'
  toast.add({ severity: 'success', summary: 'Đã duyệt', detail: `Đối soát ${item.id} đã được duyệt.`, life: 3000 })
}
const markPaid = (item) => {
  item.status = 'paid'
  toast.add({ severity: 'success', summary: 'Hoàn tất', detail: `${item.vendor} đã được thanh toán.`, life: 3000 })
}
</script>

<template>
  <div class="px-lg md:px-xl pb-xxl max-w-container-max mx-auto w-full pt-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-md mb-xl animate-fade-in">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Đối soát & Báo cáo</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Quản lý quy trình đối soát doanh thu với các nhà cung cấp.</p>
      </div>
      <div class="flex gap-sm">
        <button class="px-4 py-2 border border-outline-variant text-on-surface-variant rounded-lg font-label-md text-label-md hover:bg-surface-container-low transition-colors flex items-center gap-sm">
          <span class="material-symbols-outlined text-[18px]">download</span> Xuất Excel
        </button>
        <button class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:opacity-90 transition-opacity flex items-center gap-sm">
          <span class="material-symbols-outlined text-[18px]">send</span> Tạo đối soát mới
        </button>
      </div>
    </div>

    <!-- KPI Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-md mb-xl animate-slide-up">
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30">
        <div class="flex items-center gap-sm mb-2">
          <span class="material-symbols-outlined text-primary bg-primary-container/30 p-1 rounded-md text-[20px]">account_balance_wallet</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Tổng doanh thu</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ formatVND(kpis.totalRevenue) }}</span>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30">
        <div class="flex items-center gap-sm mb-2">
          <span class="material-symbols-outlined text-primary bg-primary-container/30 p-1 rounded-md text-[20px]">percent</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Hoa hồng nền tảng</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ formatVND(kpis.totalCommission) }}</span>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30">
        <div class="flex items-center gap-sm mb-2">
          <span class="material-symbols-outlined text-yellow-600 bg-yellow-100 p-1 rounded-md text-[20px]">pending_actions</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Chờ duyệt</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ kpis.pendingCount }}</span>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30">
        <div class="flex items-center gap-sm mb-2">
          <span class="material-symbols-outlined text-green-600 bg-green-100 p-1 rounded-md text-[20px]">check_circle</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Đã thanh toán</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ kpis.paidCount }}</span>
      </div>
    </div>

    <!-- Tabs + Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft overflow-hidden border border-outline-variant/30 animate-slide-up delay-100">
      <!-- Tabs -->
      <div class="border-b border-outline-variant/30 px-lg bg-surface flex items-center gap-0 overflow-x-auto">
        <button
          v-for="tab in tabs" :key="tab.key"
          @click="activeTab = tab.key"
          class="py-md px-lg font-label-md text-label-md whitespace-nowrap flex items-center gap-xs border-b-2 transition-colors"
          :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'"
        >
          <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
          {{ tab.label }}
          <span v-if="tab.key === 'pending'" class="ml-1 bg-yellow-100 text-yellow-800 text-[11px] px-1.5 py-0.5 rounded-full font-medium">{{ kpis.pendingCount }}</span>
        </button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-surface-container-low text-on-surface-variant font-label-md text-[13px]">
              <th class="py-3 px-lg font-semibold">Mã</th>
              <th class="py-3 px-lg font-semibold">Nhà cung cấp</th>
              <th class="py-3 px-lg font-semibold">Kỳ đối soát</th>
              <th class="py-3 px-lg font-semibold text-right">Tổng ĐH</th>
              <th class="py-3 px-lg font-semibold text-right">Tổng DT</th>
              <th class="py-3 px-lg font-semibold text-right">Hoa hồng</th>
              <th class="py-3 px-lg font-semibold text-right">Thực chi NXB</th>
              <th class="py-3 px-lg font-semibold">Trạng thái</th>
              <th class="py-3 px-lg font-semibold text-center">Hành động</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/20">
            <tr v-for="row in filteredData" :key="row.id" class="hover:bg-surface-variant/30 transition-colors">
              <td class="py-3.5 px-lg font-medium text-primary text-sm">#{{ row.id }}</td>
              <td class="py-3.5 px-lg text-sm text-on-surface font-medium">{{ row.vendor }}</td>
              <td class="py-3.5 px-lg text-sm text-on-surface-variant">{{ row.period }}</td>
              <td class="py-3.5 px-lg text-sm text-on-surface text-right">{{ row.totalOrders }}</td>
              <td class="py-3.5 px-lg text-sm text-on-surface text-right font-medium">{{ formatVND(row.totalRevenue) }}</td>
              <td class="py-3.5 px-lg text-sm text-primary text-right font-medium">{{ formatVND(row.commission) }}</td>
              <td class="py-3.5 px-lg text-sm text-on-surface text-right font-bold">{{ formatVND(row.netPayable) }}</td>
              <td class="py-3.5 px-lg">
                <span :class="[statusConfig[row.status].bg, statusConfig[row.status].text]" class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium">
                  {{ statusConfig[row.status].label }}
                </span>
              </td>
              <td class="py-3.5 px-lg text-center">
                <div class="flex items-center justify-center gap-1">
                  <button v-if="row.status === 'pending'" @click="approveItem(row)" v-tooltip="'Duyệt'" class="text-green-600 hover:bg-green-50 p-1 rounded-md transition-colors">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                  </button>
                  <button v-if="row.status === 'approved'" @click="markPaid(row)" v-tooltip="'Đánh dấu đã thanh toán'" class="text-blue-600 hover:bg-blue-50 p-1 rounded-md transition-colors">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                  </button>
                  <button v-tooltip="'Xem chi tiết'" class="text-outline hover:text-primary hover:bg-surface-variant p-1 rounded-md transition-colors">
                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredData.length === 0">
              <td colspan="9" class="py-xl text-center text-on-surface-variant">Không có dữ liệu đối soát nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shadow-soft { box-shadow: 0 4px 12px rgba(26, 58, 90, 0.04); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
</style>

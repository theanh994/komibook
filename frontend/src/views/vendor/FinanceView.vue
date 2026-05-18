<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()

// --- State ---
const balance = ref({
  available: 0,
  pending: 0,
  totalWithdrawn: 0
})

const payoutRequests = ref([])
const loading = ref(false)

// Modal state
const isWithdrawModalOpen = ref(false)
const withdrawForm = ref({
  amount: 100000,
  bank_name: '',
  account_number: '',
  account_name: ''
})

// --- API Calls ---
const fetchFinanceData = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/vendor/finance')
    balance.value = res.data.balance
    payoutRequests.value = res.data.payout_requests
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu tài chính.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const handleWithdrawRequest = async () => {
  if (withdrawForm.value.amount > balance.value.available) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Số dư khả dụng của bạn không đủ.', life: 3000 })
    return
  }
  if (!withdrawForm.value.bank_name || !withdrawForm.value.account_number || !withdrawForm.value.account_name) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng điền đầy đủ thông tin nhận tiền.', life: 3000 })
    return
  }

  try {
    await apiClient.post('/api/vendor/finance/payout', withdrawForm.value)
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Yêu cầu rút tiền đã được gửi thành công.', life: 3000 })
    isWithdrawModalOpen.value = false
    // Reset form
    withdrawForm.value = { amount: 100000, bank_name: '', account_number: '', account_name: '' }
    fetchFinanceData()
  } catch (err) {
    const errMsg = err.response?.data?.message || 'Không thể tạo yêu cầu rút tiền.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: errMsg, life: 3000 })
  }
}

const formatVND = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

onMounted(() => {
  fetchFinanceData()
})
</script>

<template>
  <div class="px-lg md:px-xl pb-xxl max-w-container-max mx-auto pt-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-xl gap-lg animate-fade-in">
      <div>
        <h2 class="font-display-lg text-display-lg font-bold text-primary mb-xs">Quản lý Doanh thu</h2>
        <p class="font-body-lg text-body-lg text-on-surface-variant">Theo dõi và yêu cầu rút tiền từ doanh thu bán sách của bạn.</p>
      </div>
      <button 
        @click="isWithdrawModalOpen = true"
        class="bg-primary text-on-primary font-headline-md text-headline-md px-lg py-md rounded-lg shadow-sm hover:bg-opacity-90 transition-all flex items-center gap-sm"
      >
        <span class="material-symbols-outlined">account_balance_wallet</span>
        Gửi yêu cầu rút tiền
      </button>
    </div>

    <!-- Bento Grid: Financial Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-lg mb-xl animate-slide-up">
      <!-- Available Balance Card -->
      <div class="bg-surface-container-lowest rounded-xl p-lg shadow-sm border border-surface-container-high flex flex-col justify-between">
        <div class="flex justify-between items-start mb-md">
          <div class="p-md bg-surface-container-low rounded-full text-primary flex items-center">
            <span class="material-symbols-outlined text-3xl">account_balance</span>
          </div>
          <span class="bg-surface-container px-md py-xs rounded-full font-label-md text-label-md text-primary font-bold">Khả dụng</span>
        </div>
        <div>
          <p class="font-label-md text-label-md text-on-surface-variant mb-xs">Số dư khả dụng</p>
          <h3 class="font-display-lg text-display-lg text-on-background font-bold">
            {{ formatVND(balance.available) }}
          </h3>
        </div>
      </div>

      <!-- Total Revenue Card -->
      <div class="bg-surface-container-lowest rounded-xl p-lg shadow-sm border border-surface-container-high flex flex-col justify-between">
        <div class="flex justify-between items-start mb-md">
          <div class="p-md bg-surface-container-low rounded-full text-tertiary flex items-center">
            <span class="material-symbols-outlined text-3xl">trending_up</span>
          </div>
          <span class="bg-surface-container px-md py-xs rounded-full font-label-md text-label-md text-outline font-bold">Toàn thời gian</span>
        </div>
        <div>
          <p class="font-label-md text-label-md text-on-surface-variant mb-xs">Tổng doanh thu đã rút thành công</p>
          <h3 class="font-display-lg text-display-lg text-on-background font-bold">
            {{ formatVND(balance.totalWithdrawn) }}
          </h3>
        </div>
      </div>
    </div>

    <!-- Payout History Table Section -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm border border-surface-container-high overflow-hidden animate-slide-up delay-200">
      <div class="p-lg border-b border-surface-container-high flex justify-between items-center bg-surface-bright">
        <h4 class="font-headline-md text-headline-md font-bold text-primary">Lịch sử yêu cầu rút tiền</h4>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-surface-container-low border-b border-surface-container-high">
              <th class="py-md px-lg font-label-md text-label-md text-on-surface-variant font-medium">Mã yêu cầu</th>
              <th class="py-md px-lg font-label-md text-label-md text-on-surface-variant font-medium">Ngày yêu cầu</th>
              <th class="py-md px-lg font-label-md text-label-md text-on-surface-variant font-medium">Số tiền</th>
              <th class="py-md px-lg font-label-md text-label-md text-on-surface-variant font-medium">Ngân hàng nhận</th>
              <th class="py-md px-lg font-label-md text-label-md text-on-surface-variant font-medium">Trạng thái</th>
            </tr>
          </thead>
          <tbody class="font-body-md text-body-md text-on-background divide-y divide-surface-container-high">
            <tr v-for="pr in payoutRequests" :key="pr.id" class="hover:bg-surface-bright transition-colors">
              <td class="py-md px-lg text-outline">{{ pr.id }}</td>
              <td class="py-md px-lg">{{ pr.created_at }}</td>
              <td class="py-md px-lg font-medium text-primary">{{ formatVND(pr.amount) }}</td>
              <td class="py-md px-lg">
                <div class="flex items-center gap-sm">
                  <span class="material-symbols-outlined text-outline">account_balance</span>
                  {{ pr.bank_name }} ({{ pr.account_number }}) - {{ pr.account_name }}
                </div>
              </td>
              <td class="py-md px-lg">
                <span 
                  v-if="pr.status === 'pending'"
                  class="inline-flex items-center gap-xs bg-surface-container-highest text-primary px-sm py-xs rounded-full font-label-md text-label-md"
                >
                  <span class="material-symbols-outlined text-sm">pending</span> Đang duyệt
                </span>
                <span 
                  v-else-if="pr.status === 'completed'"
                  class="inline-flex items-center gap-xs bg-[#e6f4ea] text-[#137333] px-sm py-xs rounded-full font-label-md text-label-md"
                >
                  <span class="material-symbols-outlined text-sm">check_circle</span> Thành công
                </span>
                <span 
                  v-else
                  class="inline-flex items-center gap-xs bg-error-container text-on-error-container px-sm py-xs rounded-full font-label-md text-label-md"
                >
                  <span class="material-symbols-outlined text-sm">cancel</span> Từ chối
                </span>
              </td>
            </tr>
            <tr v-if="payoutRequests.length === 0">
              <td colspan="5" class="py-xl px-lg text-center text-on-surface-variant">Không tìm thấy yêu cầu rút tiền nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- WITHDRAWAL REQUEST MODAL -->
    <div v-if="isWithdrawModalOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-md">
      <div class="bg-surface-container-lowest rounded-xl max-w-md w-full overflow-hidden shadow-lg border border-outline-variant">
        <div class="p-lg border-b border-surface-container-high flex justify-between items-center">
          <h3 class="text-headline-md font-bold text-primary">Yêu cầu rút tiền</h3>
          <button @click="isWithdrawModalOpen = false" class="text-outline hover:text-on-surface"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="p-lg flex flex-col gap-lg">
          <div class="bg-surface-container-low p-md rounded-lg mb-xs">
            <p class="text-sm text-on-surface-variant mb-1">Số dư khả dụng:</p>
            <p class="text-xl font-bold text-primary">{{ formatVND(balance.available) }}</p>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Số tiền rút (VND)</label>
            <input v-model.number="withdrawForm.amount" type="number" min="50000" class="border border-outline p-md rounded-lg focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"/>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Tên ngân hàng</label>
            <input v-model="withdrawForm.bank_name" type="text" placeholder="Ví dụ: Vietcombank, Techcombank" class="border border-outline p-md rounded-lg focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"/>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Số tài khoản nhận</label>
            <input v-model="withdrawForm.account_number" type="text" placeholder="Nhập số tài khoản" class="border border-outline p-md rounded-lg focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"/>
          </div>
          <div class="flex flex-col gap-xs">
            <label class="text-label-md font-medium text-on-surface">Tên chủ tài khoản</label>
            <input v-model="withdrawForm.account_name" type="text" placeholder="VIET HOA KHONG DAU" class="border border-outline p-md rounded-lg focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"/>
          </div>
        </div>
        <div class="p-lg bg-surface-container-low flex justify-end gap-md">
          <button @click="isWithdrawModalOpen = false" class="px-md py-sm border border-outline rounded-lg hover:bg-surface-container-high text-on-surface">Hủy</button>
          <button @click="handleWithdrawRequest" class="px-md py-sm bg-primary text-on-primary rounded-lg hover:opacity-90">Gửi yêu cầu</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.animate-slide-up {
  opacity: 0;
  transform: translateY(15px);
  animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

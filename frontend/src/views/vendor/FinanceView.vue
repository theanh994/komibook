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
const feePolicy = ref(null)
const payoutAccount = ref({ status: 'unverified', bank_name: null, masked_account: null, account_holder: null })
const loading = ref(false)
const errorMessage = ref('')

// Modal state
const isWithdrawModalOpen = ref(false)
const withdrawForm = ref({
  amount: 100000,
  idempotency_key: null
})

// --- API Calls ---
const fetchFinanceData = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await apiClient.get('/api/vendor/finance')
    balance.value = res.data.balance
    payoutRequests.value = res.data.payout_requests
    feePolicy.value = res.data.fee_policy || null
    payoutAccount.value = res.data.payout_account || payoutAccount.value
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu tài chính.', life: 3000 })
    errorMessage.value = 'Không thể tải số dư và lịch sử payout. Không sử dụng dữ liệu minh họa thay thế.'
  } finally {
    loading.value = false
  }
}

const handleWithdrawRequest = async () => {
  if (withdrawForm.value.amount > balance.value.available) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Số dư khả dụng của bạn không đủ.', life: 3000 })
    return
  }
  if (payoutAccount.value.status !== 'verified') {
    toast.add({ severity: 'warn', summary: 'Chưa thể rút tiền', detail: 'Tài khoản ngân hàng nhận doanh thu chưa được xác minh.', life: 3000 })
    return
  }

  try {
    withdrawForm.value.idempotency_key ||= crypto.randomUUID()
    await apiClient.post('/api/vendor/finance/payout', withdrawForm.value)
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Yêu cầu rút tiền đã được gửi thành công.', life: 3000 })
    isWithdrawModalOpen.value = false
    // Reset form
    withdrawForm.value = { amount: 100000, idempotency_key: null }
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
  <div class="pb-xxl w-full pt-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-xl gap-lg animate-fade-in">
      <div>
        <h2 class="font-display-lg text-display-lg font-bold text-primary mb-xs">Quản lý Doanh thu</h2>
        <p class="font-body-lg text-body-lg text-on-surface-variant">Theo dõi và yêu cầu rút tiền từ doanh thu bán sách của bạn.</p>
      </div>
      <button 
        :disabled="payoutAccount.status !== 'verified'"
        @click="isWithdrawModalOpen = true"
        class="flex min-h-11 items-center gap-sm rounded-lg bg-primary px-lg py-md font-headline-md text-headline-md text-on-primary shadow-sm transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
      >
        <span class="material-symbols-outlined">account_balance_wallet</span>
        Gửi yêu cầu rút tiền
      </button>
    </div>

    <section class="mb-xl rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-lg" aria-labelledby="payout-account-title">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 id="payout-account-title" class="text-xl font-black text-on-surface">Tài khoản nhận doanh thu</h3>
          <p v-if="payoutAccount.masked_account" class="mt-2 text-sm text-on-surface-variant">
            {{ payoutAccount.bank_name }} · {{ payoutAccount.masked_account }} · {{ payoutAccount.account_holder }}
          </p>
          <p v-else class="mt-2 text-sm text-on-surface-variant">Chưa có tài khoản ngân hàng trong hồ sơ Nhà bán.</p>
        </div>
        <span class="rounded-full bg-surface-container px-3 py-2 text-sm font-bold text-on-surface">
          {{ payoutAccount.status === 'verified' ? 'Đã xác minh' : 'Chưa xác minh' }}
        </span>
      </div>
      <p v-if="payoutAccount.status !== 'verified'" class="mt-4 rounded-lg bg-error-container/30 p-3 text-sm text-on-error-container" role="alert">
        Hãy cập nhật hồ sơ Nhà bán và chờ Admin xác minh tài khoản nhận tiền trước khi tạo yêu cầu rút.
      </p>
    </section>

    <div v-if="errorMessage" class="mb-lg flex flex-col gap-3 rounded-xl border border-error/30 bg-error-container/30 p-4 text-on-error-container sm:flex-row sm:items-center sm:justify-between" role="alert">
      <span>{{ errorMessage }}</span>
      <button type="button" class="min-h-11 rounded-lg border border-error/40 px-4 font-bold" @click="fetchFinanceData">Thử lại</button>
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

    <section class="mb-xl rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-lg shadow-sm" aria-labelledby="vendor-fee-title">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h3 id="vendor-fee-title" class="text-xl font-black text-on-surface">Commission và phí dịch vụ</h3>
          <p class="mt-1 text-sm leading-6 text-on-surface-variant">Commission được trừ từ doanh thu gộp của Nhà bán. Phí dịch vụ được cộng vào số tiền khách thanh toán.</p>
        </div>
        <RouterLink to="/help-center" class="inline-flex min-h-11 items-center font-bold text-primary no-underline hover:underline">Xem trợ giúp</RouterLink>
      </div>
      <div v-if="feePolicy" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Giá sách ví dụ</p><strong class="mt-1 block text-lg text-on-surface">{{ formatVND(feePolicy.example.seller_gross) }}</strong></div>
        <div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Commission ({{ feePolicy.schedule.commission_rate }}%)</p><strong class="mt-1 block text-lg text-on-surface">− {{ formatVND(feePolicy.example.commission_amount) }}</strong></div>
        <div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Nhà bán dự kiến nhận</p><strong class="mt-1 block text-lg text-primary">{{ formatVND(feePolicy.example.seller_net) }}</strong></div>
        <div class="rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Khách thanh toán</p><strong class="mt-1 block text-lg text-on-surface">{{ formatVND(feePolicy.example.customer_pays) }}</strong></div>
      </div>
      <p v-else class="mt-4 rounded-xl bg-surface-container-low p-4 text-sm text-on-surface-variant">Bản backend hiện tại chưa cung cấp preview phí. Số dư và payout vẫn hiển thị theo dữ liệu ledger; không tự suy đoán tỷ lệ.</p>
    </section>

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
                  v-else-if="pr.status === 'approved' || pr.status === 'processing'"
                  class="inline-flex items-center gap-xs bg-blue-100 text-blue-800 px-sm py-xs rounded-full font-label-md text-label-md"
                >
                  <span class="material-symbols-outlined text-sm">sync</span> {{ pr.status === 'approved' ? 'Đã duyệt' : 'Đang chuyển khoản' }}
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
          <div class="rounded-lg border border-outline-variant bg-surface-container-low p-md">
            <p class="text-sm font-semibold text-on-surface">Tài khoản đã xác minh</p>
            <p class="mt-1 text-sm text-on-surface-variant">{{ payoutAccount.bank_name }} · {{ payoutAccount.masked_account }} · {{ payoutAccount.account_holder }}</p>
            <p class="mt-2 text-xs leading-5 text-on-surface-variant">Yêu cầu sẽ chụp lại tài khoản này để đối soát. Không thể thay số tài khoản ngay trong lúc rút tiền.</p>
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

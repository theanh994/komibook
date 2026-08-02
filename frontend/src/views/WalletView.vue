<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import UserSidebar from '@/components/profile/UserSidebar.vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/axios'

const authStore = useAuthStore()
const toast = useToast()
const loading = ref(true)
const savingAccount = ref(false)
const withdrawing = ref(false)
const data = ref({ wallet: {}, payout_account: null, entries: [], payout_requests: [], policy: {} })
const accountForm = reactive({ bank_name: '', account_number: '', account_name: '' })
const amount = ref(50000)
const operationKey = ref(null)
const available = computed(() => Number(data.value.wallet?.balance || 0))
const canWithdraw = computed(() => data.value.payout_account?.status === 'verified' && available.value >= 50000)

const formatVND = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0))
const formatDate = (value) => value ? new Date(value).toLocaleString('vi-VN') : '—'
const entryLabel = (type) => ({ refund_credit: 'Hoàn tiền đơn hàng', vendor_earning_credit: 'Doanh thu bán sách', vendor_balance_import: 'Số dư doanh thu chuyển đổi', payment_debit: 'Thanh toán đơn hàng', payout_reservation: 'Giữ tiền chờ rút', payout_release: 'Hoàn lại yêu cầu bị từ chối', payout_completed: 'Đã chuyển về ngân hàng', vendor_refund_debit: 'Khấu trừ hoàn tiền' }[type] || type)
const statusLabel = (status) => ({ unverified: 'Chờ xác minh', verified: 'Đã xác minh', rejected: 'Cần cập nhật', pending: 'Chờ duyệt', approved: 'Đã duyệt', processing: 'Đang chuyển khoản', completed: 'Đã hoàn tất' }[status] || status)
const isDebit = (type) => ['payment_debit', 'vendor_refund_debit', 'payout_reservation', 'payout_completed'].includes(type)

const loadWallet = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/wallet')
    data.value = response.data.data
    if (data.value.payout_account) {
      accountForm.bank_name = data.value.payout_account.bank_name || ''
      accountForm.account_name = data.value.payout_account.account_name || ''
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể tải Ví KomiBook', detail: error.response?.data?.message || 'Vui lòng thử lại.', life: 4000 })
  } finally { loading.value = false }
}

const saveAccount = async () => {
  if (!accountForm.bank_name.trim() || !/^\d{6,64}$/.test(accountForm.account_number) || !accountForm.account_name.trim()) {
    toast.add({ severity: 'warn', summary: 'Thông tin chưa hợp lệ', detail: 'Hãy nhập đủ ngân hàng, tên chủ tài khoản và số tài khoản chỉ gồm chữ số.', life: 4000 })
    return
  }
  savingAccount.value = true
  try {
    const response = await apiClient.put('/api/wallet/payout-account', accountForm)
    data.value.payout_account = response.data.data
    accountForm.account_number = ''
    toast.add({ severity: 'success', summary: 'Đã lưu tài khoản', detail: response.data.message, life: 3500 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể lưu', detail: error.response?.data?.message || 'Vui lòng kiểm tra lại thông tin.', life: 4000 })
  } finally { savingAccount.value = false }
}

const requestWithdrawal = async () => {
  if (!canWithdraw.value || amount.value < 50000 || amount.value > available.value || withdrawing.value) return
  withdrawing.value = true
  operationKey.value ||= crypto.randomUUID()
  try {
    const response = await apiClient.post('/api/wallet/withdrawals', { amount: amount.value, idempotency_key: operationKey.value })
    toast.add({ severity: 'success', summary: 'Đã gửi yêu cầu', detail: response.data.message, life: 4000 })
    operationKey.value = null
    await loadWallet()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể rút tiền', detail: error.response?.data?.message || 'Vui lòng thử lại.', life: 4500 })
  } finally { withdrawing.value = false }
}

onMounted(loadWallet)
</script>

<template>
  <main class="min-h-screen bg-surface py-8" aria-labelledby="wallet-title">
    <div class="container mx-auto flex max-w-7xl flex-col gap-6 px-4 lg:flex-row">
      <UserSidebar :user="authStore.user" />
      <section class="min-w-0 flex-1 space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div><p class="text-sm font-bold uppercase tracking-wider text-primary">Tài khoản tài chính</p><h1 id="wallet-title" class="mt-1 text-3xl font-black text-on-surface">Ví KomiBook</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-on-surface-variant">Nhận tiền hoàn từ COD/VNPAY, doanh thu sách cũ và doanh thu Nhà bán. Ví không hỗ trợ nạp tiền từ bên ngoài.</p></div>
          <button type="button" class="min-h-11 rounded-xl border border-outline-variant px-4 font-bold text-on-surface transition-colors hover:bg-surface-container disabled:opacity-50" :disabled="loading" @click="loadWallet">{{ loading ? 'Đang tải…' : 'Làm mới' }}</button>
        </header>

        <div v-if="loading" class="grid gap-4 md:grid-cols-3" aria-live="polite"><div v-for="index in 3" :key="index" class="h-36 animate-pulse rounded-2xl bg-surface-container"></div></div>
        <template v-else>
          <section class="grid gap-4 md:grid-cols-3" aria-label="Số dư Ví KomiBook">
            <article class="rounded-2xl bg-primary p-5 text-on-primary shadow-sm"><p class="text-sm font-semibold opacity-80">Số dư khả dụng</p><strong class="mt-3 block text-3xl font-black tabular-nums">{{ formatVND(data.wallet.balance) }}</strong><p class="mt-3 text-sm opacity-80">Có thể thanh toán hoặc tạo yêu cầu rút.</p></article>
            <article class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5"><p class="text-sm font-semibold text-on-surface-variant">Đang giữ chờ xử lý</p><strong class="mt-3 block text-3xl font-black tabular-nums text-on-surface">{{ formatVND(data.wallet.reserved_balance) }}</strong><p class="mt-3 text-sm text-on-surface-variant">Không thể dùng cho tới khi yêu cầu rút kết thúc.</p></article>
            <article class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5"><p class="text-sm font-semibold text-on-surface-variant">Nạp tiền bên ngoài</p><strong class="mt-3 block text-xl font-black text-on-surface">Không hỗ trợ</strong><p class="mt-3 text-sm text-on-surface-variant">Số dư chỉ đến từ hoạt động hợp lệ trong KomiBook.</p></article>
          </section>

          <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5">
              <div class="flex items-start justify-between gap-3"><div><h2 class="text-xl font-black text-on-surface">Tài khoản nhận tiền</h2><p class="mt-1 text-sm text-on-surface-variant">Mỗi lần thay đổi đều cần Admin xác minh lại.</p></div><span v-if="data.payout_account" class="rounded-full bg-surface-container px-3 py-1.5 text-xs font-bold text-on-surface">{{ statusLabel(data.payout_account.status) }}</span></div>
              <p v-if="data.payout_account?.masked_account" class="mt-4 rounded-xl bg-surface-container-low p-3 text-sm font-semibold text-on-surface">{{ data.payout_account.bank_name }} · {{ data.payout_account.masked_account }} · {{ data.payout_account.account_name }}</p>
              <p v-if="data.payout_account?.review_reason" class="mt-3 rounded-xl bg-error-container/30 p-3 text-sm text-on-error-container" role="alert">{{ data.payout_account.review_reason }}</p>
              <form class="mt-5 grid gap-4" @submit.prevent="saveAccount">
                <label class="grid gap-1.5 text-sm font-bold text-on-surface">Ngân hàng<input v-model.trim="accountForm.bank_name" class="min-h-11 rounded-xl border border-outline-variant bg-surface px-3 font-normal" autocomplete="organization" /></label>
                <label class="grid gap-1.5 text-sm font-bold text-on-surface">Số tài khoản<input v-model.trim="accountForm.account_number" inputmode="numeric" class="min-h-11 rounded-xl border border-outline-variant bg-surface px-3 font-normal" placeholder="Nhập lại đầy đủ khi cập nhật" autocomplete="off" /></label>
                <label class="grid gap-1.5 text-sm font-bold text-on-surface">Tên chủ tài khoản<input v-model.trim="accountForm.account_name" class="min-h-11 rounded-xl border border-outline-variant bg-surface px-3 font-normal uppercase" autocomplete="name" /></label>
                <button type="submit" class="min-h-11 rounded-xl border border-primary px-4 font-bold text-primary disabled:cursor-wait disabled:opacity-50" :disabled="savingAccount">{{ savingAccount ? 'Đang lưu…' : 'Lưu và gửi xác minh' }}</button>
              </form>
            </article>

            <article class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5">
              <h2 class="text-xl font-black text-on-surface">Tạo yêu cầu rút tiền</h2><p class="mt-1 text-sm leading-6 text-on-surface-variant">Số tiền được giữ ngay khi gửi yêu cầu. Admin chỉ xác nhận hoàn tất khi có mã và bằng chứng chuyển khoản.</p>
              <div class="mt-5 rounded-xl bg-surface-container-low p-4"><p class="text-sm text-on-surface-variant">Có thể rút</p><strong class="mt-1 block text-2xl font-black text-primary tabular-nums">{{ formatVND(available) }}</strong></div>
              <form class="mt-5 grid gap-4" @submit.prevent="requestWithdrawal">
                <label class="grid gap-1.5 text-sm font-bold text-on-surface">Số tiền rút<input v-model.number="amount" type="number" min="50000" :max="available" step="1000" class="min-h-11 rounded-xl border border-outline-variant bg-surface px-3 font-normal" /></label>
                <p v-if="data.payout_account?.status !== 'verified'" class="rounded-xl bg-amber-50 p-3 text-sm font-medium text-amber-800" role="status">Cần có tài khoản nhận tiền đã được xác minh trước khi rút.</p>
                <button type="submit" class="min-h-11 rounded-xl bg-primary px-4 font-bold text-on-primary disabled:cursor-not-allowed disabled:opacity-40" :disabled="!canWithdraw || withdrawing || amount < 50000 || amount > available">{{ withdrawing ? 'Đang gửi…' : 'Gửi yêu cầu rút tiền' }}</button>
              </form>
            </article>
          </section>

          <section class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5"><h2 class="text-xl font-black text-on-surface">Yêu cầu rút gần đây</h2><div class="mt-4 overflow-x-auto" tabindex="0"><table class="w-full min-w-[680px] text-left"><thead><tr class="border-b border-outline-variant/30 text-sm text-on-surface-variant"><th class="p-3">Mã</th><th class="p-3">Ngày gửi</th><th class="p-3">Tài khoản</th><th class="p-3 text-right">Số tiền</th><th class="p-3">Trạng thái</th></tr></thead><tbody class="divide-y divide-outline-variant/20"><tr v-for="item in data.payout_requests" :key="item.id"><td class="p-3 font-bold text-primary">{{ item.code }}</td><td class="p-3">{{ formatDate(item.created_at) }}</td><td class="p-3">{{ item.bank_name }} · {{ item.masked_account }}</td><td class="p-3 text-right font-bold tabular-nums">{{ formatVND(item.amount) }}</td><td class="p-3"><span class="rounded-full bg-surface-container px-2.5 py-1 text-xs font-bold">{{ statusLabel(item.status) }}</span><p v-if="item.review_reason" class="mt-1 text-xs text-error">{{ item.review_reason }}</p></td></tr><tr v-if="!data.payout_requests.length"><td colspan="5" class="p-8 text-center text-on-surface-variant">Chưa có yêu cầu rút tiền.</td></tr></tbody></table></div></section>

          <section class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-5"><h2 class="text-xl font-black text-on-surface">Lịch sử biến động ví</h2><div class="mt-4 space-y-2"><div v-for="entry in data.entries" :key="entry.id" class="flex flex-col gap-2 rounded-xl bg-surface-container-low p-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-bold text-on-surface">{{ entryLabel(entry.entry_type) }}</p><p class="text-xs text-on-surface-variant">{{ formatDate(entry.created_at) }}</p></div><strong class="tabular-nums" :class="isDebit(entry.entry_type) ? 'text-error' : 'text-emerald-700'">{{ isDebit(entry.entry_type) ? '−' : '+' }} {{ formatVND(entry.amount) }}</strong></div><p v-if="!data.entries.length" class="py-8 text-center text-on-surface-variant">Ví chưa có biến động.</p></div></section>
        </template>
      </section>
    </div>
  </main>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) { .animate-pulse { animation: none; } }
</style>

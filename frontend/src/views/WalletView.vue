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
  <div class="min-h-screen bg-background font-inter antialiased">
    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl flex flex-col lg:flex-row items-stretch gap-xl">
      <!-- Left Sidebar -->
      <UserSidebar :user="authStore.user" />

      <!-- Main Content Container -->
      <main class="flex-1 min-w-0 w-full flex flex-col" aria-labelledby="wallet-title">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden flex-1 flex flex-col">
          <!-- Standard Banner Header -->
          <div class="p-lg md:p-xl border-b border-outline-variant/10 bg-gradient-to-r from-surface-container-low to-surface-container-lowest flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <h1 id="wallet-title" class="text-2xl font-black text-on-surface tracking-tight">Ví KomiBook & Rút tiền</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-primary/10 text-primary">
                  Ví điện tử
                </span>
              </div>
              <p class="text-xs text-on-surface-variant font-medium">Nhận tiền hoàn từ COD/VNPAY, doanh thu bán sách cũ và doanh thu Kênh bán hàng. Rút tiền về tài khoản ngân hàng dễ dàng.</p>
            </div>
            <button 
              type="button" 
              class="min-h-11 rounded-xl bg-primary/10 px-5 py-2 text-sm font-bold text-primary hover:bg-primary/20 transition-all flex items-center gap-2 shrink-0 cursor-pointer disabled:opacity-50" 
              :disabled="loading" 
              @click="loadWallet"
            >
              <span class="material-symbols-outlined text-lg" :class="{ 'animate-spin': loading }">refresh</span>
              <span>{{ loading ? 'Đang tải…' : 'Làm mới' }}</span>
            </button>
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="p-lg md:p-xl grid gap-4 md:grid-cols-3" aria-live="polite">
            <div v-for="index in 3" :key="index" class="h-36 animate-pulse rounded-2xl bg-surface-container-low"></div>
          </div>

          <!-- Main Content Body -->
          <div v-else class="p-lg md:p-xl space-y-8 flex-1">
            <!-- 3 Stat Cards -->
            <section class="grid gap-4 md:grid-cols-3" aria-label="Số dư Ví KomiBook">
              <article class="rounded-2xl bg-primary p-6 text-on-primary shadow-md relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform"></div>
                <p class="text-xs font-bold uppercase tracking-wider opacity-80">Số dư khả dụng</p>
                <strong class="mt-2 block text-3xl font-black tabular-nums tracking-tight">{{ formatVND(data.wallet.balance) }}</strong>
                <p class="mt-3 text-xs opacity-80 font-medium">Có thể dùng thanh toán đơn mới hoặc rút tiền.</p>
              </article>

              <article class="rounded-2xl border border-outline-variant/30 bg-surface-container-low/40 p-6 flex flex-col justify-between">
                <div>
                  <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Đang giữ chờ xử lý</p>
                  <strong class="mt-2 block text-3xl font-black tabular-nums text-on-surface tracking-tight">{{ formatVND(data.wallet.reserved_balance) }}</strong>
                </div>
                <p class="mt-3 text-xs text-on-surface-variant font-medium">Số tiền tạm giữ trong khi yêu cầu rút tiền đang chờ duyệt.</p>
              </article>

              <article class="rounded-2xl border border-outline-variant/30 bg-surface-container-low/40 p-6 flex flex-col justify-between">
                <div>
                  <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Nạp tiền bên ngoài</p>
                  <strong class="mt-2 block text-xl font-black text-on-surface">Không hỗ trợ</strong>
                </div>
                <p class="mt-3 text-xs text-on-surface-variant font-medium">Số dư chỉ nạp tự động từ tiền hoàn hoặc doanh thu hợp lệ.</p>
              </article>
            </section>

            <!-- Grid 2 Column: Payout Account & Withdrawal Form -->
            <section class="grid gap-6 xl:grid-cols-2">
              <!-- Account Form Card -->
              <article class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 flex flex-col justify-between shadow-xs">
                <div>
                  <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                      <h2 class="text-lg font-black text-on-surface">Tài khoản nhận tiền</h2>
                      <p class="text-xs text-on-surface-variant font-medium">Admin sẽ xác minh mỗi khi có thay đổi tài khoản.</p>
                    </div>
                    <span v-if="data.payout_account" class="rounded-full px-3 py-1 text-xs font-bold" :class="data.payout_account.status === 'verified' ? 'bg-emerald-500/10 text-emerald-700' : 'bg-surface-container text-on-surface'">
                      {{ statusLabel(data.payout_account.status) }}
                    </span>
                  </div>

                  <p v-if="data.payout_account?.masked_account" class="rounded-xl bg-surface-container-low p-3.5 text-xs font-bold text-on-surface border border-outline-variant/20 mb-4">
                    🏦 {{ data.payout_account.bank_name }} · {{ data.payout_account.masked_account }} · {{ data.payout_account.account_name }}
                  </p>
                  <p v-if="data.payout_account?.review_reason" class="rounded-xl bg-error/10 p-3.5 text-xs text-error font-medium mb-4" role="alert">
                    ⚠️ {{ data.payout_account.review_reason }}
                  </p>

                  <form class="space-y-4" @submit.prevent="saveAccount">
                    <label class="block text-xs font-bold text-on-surface">
                      Tên Ngân hàng
                      <input v-model.trim="accountForm.bank_name" class="mt-1 w-full min-h-11 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3.5 text-xs font-medium focus:border-primary outline-none" placeholder="VD: Vietcombank, Techcombank, MB Bank..." autocomplete="organization" />
                    </label>
                    <label class="block text-xs font-bold text-on-surface">
                      Số tài khoản
                      <input v-model.trim="accountForm.account_number" inputmode="numeric" class="mt-1 w-full min-h-11 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3.5 text-xs font-medium focus:border-primary outline-none" placeholder="Nhập lại đầy đủ khi cập nhật" autocomplete="off" />
                    </label>
                    <label class="block text-xs font-bold text-on-surface">
                      Tên chủ tài khoản (Viết hoa không dấu)
                      <input v-model.trim="accountForm.account_name" class="mt-1 w-full min-h-11 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3.5 text-xs font-medium uppercase focus:border-primary outline-none" placeholder="VD: NGUYEN VAN A" autocomplete="name" />
                    </label>
                    <button type="submit" class="w-full min-h-11 rounded-xl border border-primary px-4 text-xs font-bold text-primary hover:bg-primary/10 transition-colors disabled:cursor-wait disabled:opacity-50 cursor-pointer" :disabled="savingAccount">
                      {{ savingAccount ? 'Đang lưu…' : 'Lưu và gửi xác minh' }}
                    </button>
                  </form>
                </div>
              </article>

              <!-- Withdrawal Form Card -->
              <article class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 flex flex-col justify-between shadow-xs">
                <div>
                  <h2 class="text-lg font-black text-on-surface">Tạo yêu cầu rút tiền</h2>
                  <p class="text-xs text-on-surface-variant font-medium mt-1">Số tiền rút được giữ ngay khi gửi. Admin sẽ kiểm tra và xác nhận chuyển khoản.</p>

                  <div class="mt-4 rounded-xl bg-primary/5 border border-primary/10 p-4">
                    <p class="text-xs font-bold text-primary">Có thể rút</p>
                    <strong class="mt-1 block text-2xl font-black text-primary tabular-nums">{{ formatVND(available) }}</strong>
                  </div>

                  <form class="mt-4 space-y-4" @submit.prevent="requestWithdrawal">
                    <label class="block text-xs font-bold text-on-surface">
                      Số tiền rút (Tối thiểu 50.000 VNĐ)
                      <input v-model.number="amount" type="number" min="50000" :max="available" step="1000" class="mt-1 w-full min-h-11 rounded-xl border border-outline-variant/30 bg-surface-container-lowest px-3.5 text-xs font-bold focus:border-primary outline-none" />
                    </label>
                    <p v-if="data.payout_account?.status !== 'verified'" class="rounded-xl bg-amber-500/10 p-3 text-xs font-medium text-amber-800" role="status">
                      ⚠️ Cần bổ sung tài khoản ngân hàng đã xác minh trước khi rút tiền.
                    </p>
                    <button type="submit" class="w-full min-h-11 rounded-xl bg-primary px-4 text-xs font-bold text-on-primary shadow-md hover:bg-primary/90 transition-all disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer" :disabled="!canWithdraw || withdrawing || amount < 50000 || amount > available">
                      {{ withdrawing ? 'Đang gửi…' : 'Gửi yêu cầu rút tiền' }}
                    </button>
                  </form>
                </div>
              </article>
            </section>

            <!-- Table 1: Payout Requests -->
            <section class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-xs">
              <h2 class="text-lg font-black text-on-surface">Yêu cầu rút tiền gần đây</h2>
              <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-xs">
                  <thead>
                    <tr class="border-b border-outline-variant/30 text-on-surface-variant font-bold uppercase tracking-wider">
                      <th class="py-3 px-3">Mã rút tiền</th>
                      <th class="py-3 px-3">Ngày gửi</th>
                      <th class="py-3 px-3">Tài khoản</th>
                      <th class="py-3 px-3 text-right">Số tiền</th>
                      <th class="py-3 px-3">Trạng thái</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-outline-variant/20">
                    <tr v-for="item in data.payout_requests" :key="item.id" class="hover:bg-surface-container-low/50 transition-colors">
                      <td class="py-3 px-3 font-bold text-primary">{{ item.code }}</td>
                      <td class="py-3 px-3 font-medium text-on-surface-variant">{{ formatDate(item.created_at) }}</td>
                      <td class="py-3 px-3 font-medium">{{ item.bank_name }} · {{ item.masked_account }}</td>
                      <td class="py-3 px-3 text-right font-bold tabular-nums text-on-surface">{{ formatVND(item.amount) }}</td>
                      <td class="py-3 px-3">
                        <span class="rounded-full bg-surface-container px-2.5 py-1 text-[11px] font-bold text-on-surface">
                          {{ statusLabel(item.status) }}
                        </span>
                        <p v-if="item.review_reason" class="mt-1 text-[10px] text-error font-medium">{{ item.review_reason }}</p>
                      </td>
                    </tr>
                    <tr v-if="!data.payout_requests.length">
                      <td colspan="5" class="py-8 text-center text-on-surface-variant font-medium">Chưa có lịch sử yêu cầu rút tiền.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <!-- Table 2: Balance History -->
            <section class="rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-xs">
              <h2 class="text-lg font-black text-on-surface">Lịch sử biến động số dư Ví</h2>
              <div class="mt-4 space-y-2">
                <div v-for="entry in data.entries" :key="entry.id" class="flex flex-col gap-2 rounded-xl bg-surface-container-low/50 p-3.5 sm:flex-row sm:items-center sm:justify-between border border-outline-variant/20">
                  <div>
                    <p class="font-bold text-xs text-on-surface">{{ entryLabel(entry.entry_type) }}</p>
                    <p class="text-[11px] text-on-surface-variant mt-0.5">{{ formatDate(entry.created_at) }}</p>
                  </div>
                  <strong class="tabular-nums text-sm font-bold" :class="isDebit(entry.entry_type) ? 'text-error' : 'text-emerald-700'">
                    {{ isDebit(entry.entry_type) ? '−' : '+' }} {{ formatVND(entry.amount) }}
                  </strong>
                </div>
                <p v-if="!data.entries.length" class="py-8 text-center text-xs text-on-surface-variant font-medium">Ví chưa có lịch sử biến động.</p>
              </div>
            </section>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) { .animate-pulse { animation: none; } }
</style>

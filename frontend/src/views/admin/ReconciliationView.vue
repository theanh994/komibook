<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()

const activeTab = ref('all')
const loading = ref(true)

const kpis = ref({
  pending_payout: 0,
  approved_payout: 0,
  total_settled: 0,
  unreconciled: 0,
})

const payoutRequests = ref([])
const payoutAccounts = ref([])
const accountReview = ref(null)
const accountReviewReason = ref('')
const reviewingAccount = ref(false)
const currentPage = ref(1)
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })

const tabs = [
  { key: 'all', label: 'Tất cả', icon: 'list' },
  { key: 'pending', label: 'Chờ duyệt', icon: 'pending_actions' },
  { key: 'approved', label: 'Đã duyệt', icon: 'check_circle' },
  { key: 'processing', label: 'Đang chuyển khoản', icon: 'sync' },
  { key: 'completed', label: 'Hoàn tất', icon: 'payments' },
  { key: 'rejected', label: 'Từ chối', icon: 'cancel' },
]

const formatVND = (val) => new Intl.NumberFormat('vi-VN').format(val || 0) + ' ₫'

const statusConfig = {
  pending: { label: 'Chờ duyệt', bg: 'bg-yellow-100', text: 'text-yellow-800', icon: 'pending' },
  approved: { label: 'Đã duyệt', bg: 'bg-green-100', text: 'text-green-800', icon: 'check_circle' },
  processing: { label: 'Đang chuyển khoản', bg: 'bg-blue-100', text: 'text-blue-800', icon: 'sync' },
  completed: { label: 'Hoàn tất', bg: 'bg-emerald-100', text: 'text-emerald-800', icon: 'payments' },
  rejected: { label: 'Từ chối', bg: 'bg-red-100', text: 'text-red-800', icon: 'cancel' },
}

const fetchReconciliations = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/admin/reconciliation', {
      params: { status: activeTab.value, page: currentPage.value }
    })
    kpis.value = res.data.data.kpi
    payoutRequests.value = res.data.data.payout_requests
    payoutAccounts.value = res.data.data.payout_accounts || []
    pagination.value = res.data.data.meta
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu đối soát.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openAccountReview = (account, target) => {
  accountReview.value = { account, target }
  accountReviewReason.value = target === 'verified' ? 'Đã đối chiếu đúng chủ tài khoản và thông tin ngân hàng.' : ''
}

const submitAccountReview = async () => {
  if (!accountReviewReason.value.trim() || reviewingAccount.value) return
  reviewingAccount.value = true
  try {
    await apiClient.patch(`/api/admin/reconciliation/payout-accounts/${accountReview.value.account.id}/review`, {
      target: accountReview.value.target,
      reason: accountReviewReason.value.trim(),
    })
    toast.add({ severity: 'success', summary: 'Đã cập nhật tài khoản', detail: 'Trạng thái tài khoản nhận tiền đã được lưu.', life: 3000 })
    accountReview.value = null
    await fetchReconciliations()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể xét tài khoản', detail: error.response?.data?.message || 'Vui lòng thử lại.', life: 3500 })
  } finally { reviewingAccount.value = false }
}

const changeTab = (tabKey) => {
  activeTab.value = tabKey
  currentPage.value = 1
  fetchReconciliations()
}

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page || page === currentPage.value) return
  currentPage.value = page
  fetchReconciliations()
}

const reconciliationConfig = (row) => row.reconciliation?.is_consistent
  ? { label: 'Khớp sổ', classes: 'bg-emerald-100 text-emerald-800', icon: 'verified' }
  : { label: 'Cần kiểm tra', classes: 'bg-orange-100 text-orange-800', icon: 'warning' }

const transitionItem = async (item, target) => {
  const payload = { target, idempotency_key: crypto.randomUUID() }
  if (target === 'approved' || target === 'rejected') {
    payload.reason = window.prompt(target === 'approved' ? 'Ghi chú duyệt payout:' : 'Lý do từ chối payout:')
    if (!payload.reason) return
  }
  if (target === 'processing' || target === 'completed') {
    payload.transfer_reference = window.prompt('Mã tham chiếu chuyển khoản:', item.transfer_reference || '')
    if (!payload.transfer_reference) return
  }
  if (target === 'completed') {
    payload.transfer_evidence = window.prompt('Đường dẫn bằng chứng chuyển khoản:')
    if (!payload.transfer_evidence) return
  }
  try {
    await apiClient.patch(`/api/admin/reconciliation/payouts/${item.id}/transition`, payload)
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: `Payout #${item.id} đã chuyển sang ${target}.`, life: 3000 })
    fetchReconciliations()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Không thể chuyển trạng thái', detail: err.response?.data?.message || 'Vui lòng thử lại.', life: 3500 })
  }
}

onMounted(() => {
  fetchReconciliations()
})
</script>

<template>
  <div class="pb-xxl w-full pt-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-md mb-xl animate-fade-in">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Quản lý yêu cầu rút tiền</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Xác minh tài khoản nhận tiền và xử lý yêu cầu của khách hàng, người bán sách cũ và Nhà bán.</p>
      </div>
      <div class="flex gap-sm">
        <button type="button" @click="fetchReconciliations" class="min-h-11 px-4 py-2 border border-outline-variant text-on-surface-variant rounded-lg font-label-md text-label-md hover:bg-surface-container-low transition-colors flex items-center gap-sm">
          <span class="material-symbols-outlined text-[18px]">refresh</span> Làm mới
        </button>
      </div>
    </div>

    <section class="mb-xl rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-lg" aria-labelledby="payout-account-review-title">
      <div class="flex items-center justify-between gap-3"><div><h2 id="payout-account-review-title" class="text-xl font-black text-on-surface">Tài khoản nhận tiền chờ xác minh</h2><p class="mt-1 text-sm text-on-surface-variant">Chỉ tài khoản đã xác minh mới có thể tạo yêu cầu rút.</p></div><span class="rounded-full bg-surface-container px-3 py-1 text-sm font-bold">{{ payoutAccounts.length }}</span></div>
      <div class="mt-4 grid gap-3 lg:grid-cols-2"><article v-for="account in payoutAccounts" :key="account.id" class="rounded-xl border border-outline-variant/30 bg-surface-container-low p-4"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p class="font-bold text-on-surface">{{ account.user?.name }}</p><p class="text-sm text-on-surface-variant">{{ account.user?.email }}</p><p class="mt-2 text-sm font-semibold">{{ account.bank_name }} · {{ account.masked_account }}</p><p class="text-sm uppercase text-on-surface-variant">{{ account.account_name }}</p></div><div class="flex gap-2"><button type="button" class="min-h-11 rounded-lg border border-error px-3 text-sm font-bold text-error" @click="openAccountReview(account, 'rejected')">Yêu cầu sửa</button><button type="button" class="min-h-11 rounded-lg bg-primary px-3 text-sm font-bold text-on-primary" @click="openAccountReview(account, 'verified')">Xác minh</button></div></div><p v-if="account.review_reason" class="mt-3 text-xs text-error">Lần trước: {{ account.review_reason }}</p></article><p v-if="!payoutAccounts.length" class="col-span-full rounded-xl bg-surface-container-low p-6 text-center text-on-surface-variant">Không có tài khoản nào đang chờ xử lý.</p></div>
    </section>

    <!-- KPI Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-md mb-xl animate-slide-up">
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30">
        <div class="flex items-center gap-sm mb-2">
          <span class="material-symbols-outlined text-yellow-600 bg-yellow-100 p-1 rounded-md text-[20px]">pending_actions</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Đang chờ duyệt</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ formatVND(kpis.pending_payout) }}</span>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30">
        <div class="flex items-center gap-sm mb-2">
          <span class="material-symbols-outlined text-green-600 bg-green-100 p-1 rounded-md text-[20px]">check_circle</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Đã duyệt (Lũy kế)</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ formatVND(kpis.approved_payout) }}</span>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30">
        <div class="flex items-center gap-sm mb-2">
          <span class="material-symbols-outlined text-primary bg-primary-container/30 p-1 rounded-md text-[20px]">payments</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Đã hoàn thành thanh toán</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ formatVND(kpis.total_settled) }}</span>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30">
        <div class="flex items-center gap-sm mb-2">
          <span class="material-symbols-outlined text-on-surface-variant bg-surface-container-high p-1 rounded-md text-[20px]">shopping_bag</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Payout cần kiểm tra</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ kpis.unreconciled }}</span>
      </div>
    </div>

    <!-- Tabs + Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft overflow-hidden border border-outline-variant/30 animate-slide-up delay-100">
      <!-- Tabs -->
      <div class="border-b border-outline-variant/30 px-lg bg-surface flex items-center gap-0 overflow-x-auto">
        <button
          v-for="tab in tabs" :key="tab.key"
          type="button"
          @click="changeTab(tab.key)"
          class="min-h-11 py-md px-lg font-label-md text-label-md whitespace-nowrap flex items-center gap-xs border-b-2 transition-colors"
          :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'"
        >
          <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto" role="region" aria-label="Danh sách yêu cầu rút tiền" tabindex="0">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-surface-container-low text-on-surface-variant font-label-md text-[13px]">
              <th class="py-3 px-lg font-semibold">Mã YC</th>
              <th class="py-3 px-lg font-semibold">Chủ ví</th>
              <th class="py-3 px-lg font-semibold">Ngày tạo</th>
              <th class="py-3 px-lg font-semibold text-right">Số tiền rút</th>
              <th class="py-3 px-lg font-semibold">Thông tin nhận tiền</th>
              <th class="py-3 px-lg font-semibold">Trạng thái</th>
              <th class="py-3 px-lg font-semibold">Đối soát</th>
              <th class="py-3 px-lg font-semibold text-center">Hành động</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/20">
            <tr v-if="loading" class="text-center">
              <td colspan="8" class="py-xl text-on-surface-variant">Đang tải dữ liệu...</td>
            </tr>
            <tr v-else-if="payoutRequests.length === 0">
              <td colspan="8" class="py-xl text-center text-on-surface-variant">Không có yêu cầu nào.</td>
            </tr>
            <tr v-for="row in payoutRequests" :key="row.id" class="hover:bg-surface-variant/30 transition-colors">
              <td class="py-3.5 px-lg font-medium text-primary text-sm">#{{ row.id }}</td>
              <td class="py-3.5 px-lg text-sm text-on-surface"><p class="font-medium">{{ row.vendor?.shop_name || row.user?.name || 'Tài khoản KomiBook' }}</p><p class="text-xs text-on-surface-variant">{{ row.user?.email }}</p></td>
              <td class="py-3.5 px-lg text-sm text-on-surface-variant">{{ new Date(row.created_at).toLocaleDateString('vi-VN') }}</td>
              <td class="py-3.5 px-lg text-sm text-primary text-right font-bold">{{ formatVND(row.amount) }}</td>
              <td class="py-3.5 px-lg text-sm text-on-surface">
                <div>{{ row.bank_name }} - {{ row.account_number }}</div>
                <div class="text-xs text-on-surface-variant">{{ row.account_name }}</div>
              </td>
              <td class="py-3.5 px-lg">
                <span :class="[statusConfig[row.status]?.bg || 'bg-gray-100', statusConfig[row.status]?.text || 'text-gray-800']" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium">
                  <span class="material-symbols-outlined text-[14px]">{{ statusConfig[row.status]?.icon }}</span>
                  {{ statusConfig[row.status]?.label || row.status }}
                </span>
              </td>
              <td class="py-3.5 px-lg">
                <span
                  :class="reconciliationConfig(row).classes"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap"
                  :title="row.reconciliation?.issues?.join(', ') || 'Ledger và transition hợp lệ'"
                >
                  <span class="material-symbols-outlined text-[14px]">{{ reconciliationConfig(row).icon }}</span>
                  {{ reconciliationConfig(row).label }}
                </span>
                <div v-if="row.reconciliation?.latest_transition" class="mt-1 text-xs text-on-surface-variant">
                  Cập nhật: {{ statusConfig[row.reconciliation.latest_transition.to_status]?.label || row.reconciliation.latest_transition.to_status }}
                </div>
              </td>
              <td class="py-3.5 px-lg text-center">
                <div class="flex items-center justify-center gap-2">
                  <button v-if="row.status === 'pending'" type="button" @click="transitionItem(row, 'approved')" class="min-h-11 min-w-11 text-green-600 hover:bg-green-50 p-1.5 rounded-md transition-colors" aria-label="Duyệt yêu cầu rút tiền">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                  </button>
                  <button v-if="row.status === 'pending'" type="button" @click="transitionItem(row, 'rejected')" class="min-h-11 min-w-11 text-red-600 hover:bg-red-50 p-1.5 rounded-md transition-colors" aria-label="Từ chối yêu cầu rút tiền">
                    <span class="material-symbols-outlined text-[20px]">cancel</span>
                  </button>
                  <button v-if="row.status === 'approved'" type="button" @click="transitionItem(row, 'processing')" class="min-h-11 min-w-11 text-blue-600 hover:bg-blue-50 p-1.5 rounded-md transition-colors" aria-label="Bắt đầu chuyển khoản"><span class="material-symbols-outlined text-[20px]">sync</span></button>
                  <button v-if="row.status === 'processing'" type="button" @click="transitionItem(row, 'completed')" class="min-h-11 min-w-11 text-emerald-600 hover:bg-emerald-50 p-1.5 rounded-md transition-colors" aria-label="Xác nhận hoàn tất chuyển khoản"><span class="material-symbols-outlined text-[20px]">payments</span></button>
                  <span v-if="row.status === 'rejected' || row.status === 'completed'" class="text-on-surface-variant text-xs">Đã xử lý</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between gap-md border-t border-outline-variant/30 px-lg py-md">
        <span class="text-sm text-on-surface-variant">Trang {{ pagination.current_page }} / {{ pagination.last_page }} · {{ pagination.total }} yêu cầu</span>
        <div class="flex gap-sm">
          <button type="button" class="min-h-11 px-4 rounded-lg border border-outline-variant disabled:opacity-40" :disabled="currentPage <= 1" @click="changePage(currentPage - 1)">Trang trước</button>
          <button type="button" class="min-h-11 px-4 rounded-lg border border-outline-variant disabled:opacity-40" :disabled="currentPage >= pagination.last_page" @click="changePage(currentPage + 1)">Trang sau</button>
        </div>
      </div>
    </div>

    <div v-if="accountReview" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="account-review-title">
      <form class="w-full max-w-lg rounded-2xl bg-surface-container-lowest p-5 shadow-xl" @submit.prevent="submitAccountReview"><h2 id="account-review-title" class="text-xl font-black text-on-surface">{{ accountReview.target === 'verified' ? 'Xác minh tài khoản nhận tiền' : 'Yêu cầu cập nhật tài khoản' }}</h2><p class="mt-2 text-sm text-on-surface-variant">{{ accountReview.account.user?.name }} · {{ accountReview.account.bank_name }} · {{ accountReview.account.masked_account }}</p><label class="mt-5 grid gap-2 text-sm font-bold">Ghi chú xét duyệt<textarea v-model.trim="accountReviewReason" rows="4" class="rounded-xl border border-outline-variant bg-surface p-3 font-normal" required></textarea></label><div class="mt-5 flex justify-end gap-2"><button type="button" class="min-h-11 rounded-xl border border-outline-variant px-4 font-bold" @click="accountReview = null">Hủy</button><button type="submit" class="min-h-11 rounded-xl bg-primary px-4 font-bold text-on-primary disabled:opacity-50" :disabled="reviewingAccount || !accountReviewReason.trim()">{{ reviewingAccount ? 'Đang lưu…' : 'Xác nhận' }}</button></div></form>
    </div>
  </div>
</template>

<style scoped>
.shadow-soft { box-shadow: 0 4px 12px rgba(26, 58, 90, 0.04); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
@media (prefers-reduced-motion: reduce) {
  .animate-fade-in,
  .animate-slide-up {
    animation: none;
    opacity: 1;
    transform: none;
  }
}
</style>

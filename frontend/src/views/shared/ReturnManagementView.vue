<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import apiClient from '@/services/axios'
import { formatMoney, formatReturnDate, operationKey, returnStatus, workflowActions } from '@/services/returnWorkflow'

const route = useRoute()
const toast = useToast()
const entries = ref([])
const loading = ref(true)
const submitting = ref(false)
const selected = ref(null)
const selectedAction = ref(null)
const note = ref('')
const filter = ref('active')
const error = ref('')

const isAdmin = computed(() => route.meta.role === 'admin')
const apiPrefix = computed(() => isAdmin.value ? '/api/admin/returns' : '/api/vendor/returns')
const title = computed(() => isAdmin.value ? 'Giám sát trả hàng & hoàn tiền' : 'Xử lý trả hàng & hoàn tiền')
const visibleEntries = computed(() => {
  if (filter.value === 'all') return entries.value
  if (filter.value === 'closed') return entries.value.filter((entry) => ['refunded', 'rejected'].includes(entry.status))
  return entries.value.filter((entry) => !['refunded', 'rejected'].includes(entry.status))
})
const actionNeedsNote = computed(() => selectedAction.value?.requiresReason
  || (selectedAction.value?.kind === 'refund' && selected.value?.order_payment_method === 'cod'))
const canSubmit = computed(() => selectedAction.value && (!actionNeedsNote.value || note.value.trim().length > 0) && !submitting.value)

const statusOf = (status) => returnStatus[status] || { label: status, tone: 'bg-slate-100 text-slate-700' }
const actionsFor = (entry) => (workflowActions[entry.status] || []).filter((action) =>
  action.kind !== 'reconcile' || entry.order_payment_method !== 'cod'
)

const fetchEntries = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get(apiPrefix.value)
    entries.value = Array.isArray(response.data?.data) ? response.data.data : []
  } catch (requestError) {
    const message = requestError.response?.data?.message || 'Vui lòng thử lại.'
    entries.value = []
    error.value = message
    toast.add({ severity: 'error', summary: 'Không thể tải yêu cầu', detail: message, life: 3500 })
  } finally {
    loading.value = false
  }
}

const openAction = (entry, action) => {
  selected.value = entry
  selectedAction.value = action
  note.value = ''
}

const closeAction = () => {
  selected.value = null
  selectedAction.value = null
  note.value = ''
}

const submitAction = async () => {
  if (!canSubmit.value) return
  submitting.value = true
  const key = operationKey(`${route.meta.role}-return-${selected.value.id}-${selectedAction.value.target}`)
  try {
    if (selectedAction.value.kind === 'reconcile') {
      await apiClient.post(`${apiPrefix.value}/${selected.value.id}/refund/reconcile`, {
        idempotency_key: key,
      })
    } else if (selectedAction.value.kind === 'refund') {
      await apiClient.post(`${apiPrefix.value}/${selected.value.id}/refund`, {
        idempotency_key: key,
        evidence: note.value.trim() || null,
      })
    } else {
      await apiClient.patch(`${apiPrefix.value}/${selected.value.id}/transition`, {
        target: selectedAction.value.target,
        reason: note.value.trim() || null,
        idempotency_key: key,
      })
    }
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: 'Trạng thái yêu cầu đã được ghi nhận.', life: 3000 })
    closeAction()
    await fetchEntries()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể cập nhật', detail: error.response?.data?.message || 'Vui lòng kiểm tra lại.', life: 4000 })
  } finally {
    submitting.value = false
  }
}

onMounted(fetchEntries)
</script>

<template>
  <div class="w-full py-6 space-y-6">
    <header class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-on-background">{{ title }}</h1>
        <p class="text-sm text-on-surface-variant mt-1">Mọi quyết định đều lưu người thực hiện, thời gian và khóa chống lặp.</p>
      </div>
      <label for="return-status-filter" class="sr-only">Lọc yêu cầu trả hàng theo trạng thái</label>
      <select id="return-status-filter" v-model="filter" class="min-h-11 rounded-xl border border-outline-variant bg-surface px-4 py-2 text-sm">
        <option value="active">Đang xử lý</option>
        <option value="closed">Đã kết thúc</option>
        <option value="all">Tất cả</option>
      </select>
    </header>

    <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest overflow-hidden">
      <div v-if="loading" role="status" aria-live="polite" class="p-12 text-center text-on-surface-variant">Đang tải yêu cầu...</div>
      <div v-else-if="error" role="alert" class="p-8 text-center">
        <p class="font-bold text-error">Không thể tải yêu cầu trả hàng</p>
        <p class="mt-2 text-sm text-on-surface-variant">{{ error }}</p>
        <button type="button" class="mt-4 min-h-11 rounded-xl bg-error px-5 font-bold text-white" @click="fetchEntries">Thử lại</button>
      </div>
      <div v-else-if="visibleEntries.length === 0" class="p-12 text-center text-on-surface-variant">Không có yêu cầu trong nhóm này.</div>
      <div v-else class="divide-y divide-outline-variant/30">
        <article v-for="entry in visibleEntries" :key="entry.id" class="p-5 space-y-4">
          <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <strong class="text-on-surface">Đơn #{{ entry.order_code || entry.order_id }}</strong>
                <span :class="['rounded-full px-3 py-1 text-xs font-bold', statusOf(entry.status).tone]">{{ statusOf(entry.status).label }}</span>
              </div>
              <p class="text-xs text-on-surface-variant mt-2">
                {{ entry.customer?.name || `Khách #${entry.user_id}` }}
                <template v-if="isAdmin"> · {{ entry.vendor?.shop_name || `Vendor #${entry.vendor_id}` }}</template>
                · {{ formatReturnDate(entry.requested_at) }}
              </p>
              <p class="text-sm text-on-surface mt-2">{{ entry.reason }}</p>
            </div>
            <strong class="text-lg text-primary whitespace-nowrap">{{ formatMoney(entry.refund_amount, entry.currency) }}</strong>
          </div>

          <div class="flex flex-wrap gap-2 text-xs text-on-surface-variant">
            <span v-for="item in entry.items" :key="item.id" class="rounded-lg bg-surface-container-low px-3 py-2">
              {{ item.book?.title || 'Sách' }} ×{{ item.quantity }}
            </span>
          </div>

          <p v-if="entry.refund_transaction?.failure_reason" class="rounded-lg bg-error-container px-3 py-2 text-xs text-error">
            Lần hoàn gần nhất lỗi: {{ entry.refund_transaction.failure_reason }}
          </p>

          <div v-if="actionsFor(entry).length" class="flex flex-wrap gap-2">
            <button v-for="action in actionsFor(entry)" :key="action.target" type="button" class="min-h-11 rounded-xl border border-primary px-4 py-2 text-xs font-bold text-primary hover:bg-primary hover:text-on-primary" @click="openAction(entry, action)">
              {{ action.label }}
            </button>
          </div>
        </article>
      </div>
    </div>

    <Dialog :visible="Boolean(selectedAction)" modal :header="selectedAction?.label || 'Cập nhật yêu cầu'" :style="{ width: 'min(92vw, 520px)' }" @update:visible="!$event && closeAction()">
      <div class="space-y-4">
        <p class="text-sm text-on-surface-variant">Yêu cầu cho đơn #{{ selected?.order_code || selected?.order_id }}</p>
        <label class="block">
          <span class="block text-sm font-bold text-on-surface mb-2">
            {{ selectedAction?.kind === 'refund' && selected?.order_payment_method === 'cod' ? 'Mã/chứng từ hoàn tiền COD' : 'Ghi chú quyết định' }}
          </span>
          <textarea v-model="note" rows="4" maxlength="2000" class="w-full rounded-xl border border-outline-variant px-4 py-3" :placeholder="actionNeedsNote ? 'Bắt buộc nhập thông tin' : 'Không bắt buộc'"></textarea>
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" class="min-h-11 rounded-xl px-4 py-2 text-sm font-bold text-on-surface-variant" @click="closeAction">Đóng</button>
          <button type="button" :disabled="!canSubmit" class="min-h-11 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary disabled:opacity-50" @click="submitAction">
            {{ submitting ? 'Đang xử lý...' : 'Xác nhận' }}
          </button>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import UserSidebar from '@/components/profile/UserSidebar.vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/axios'
import { readApiList } from '@/services/apiContract'
import { formatMoney, formatReturnDate, operationKey, returnStatus } from '@/services/returnWorkflow'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const returns = ref([])
const orders = ref([])
const loading = ref(true)
const submitting = ref(false)
const selectedOrderId = ref(null)
const quantities = ref({})
const reason = ref('')
const error = ref('')
const returnOperationKey = ref('')

const eligibleOrders = computed(() => orders.value.filter((order) =>
  order.status === 'completed'
  && order.shipping_status === 'delivered'
  && order.items?.some((item) => item.book?.type === 'physical' && item.return_policy?.is_returnable)
  && !['refunding', 'refunded'].includes(order.refund_status)
))
const selectedOrder = computed(() => eligibleOrders.value.find((order) => Number(order.id) === Number(selectedOrderId.value)))
const physicalItems = computed(() => selectedOrder.value?.items?.filter((item) =>
  item.book?.type === 'physical' && item.return_policy?.is_returnable
) || [])
const selectedItems = computed(() => physicalItems.value
  .map((item) => ({ order_item_id: item.id, quantity: Number(quantities.value[item.id] || 0) }))
  .filter((item) => item.quantity > 0))
const canSubmit = computed(() => reason.value.trim().length >= 10 && selectedItems.value.length > 0 && !submitting.value)

const statusOf = (status) => returnStatus[status] || { label: status, tone: 'bg-slate-100 text-slate-700' }

const selectOrder = (value) => {
  selectedOrderId.value = value ? Number(value) : null
  quantities.value = {}
  returnOperationKey.value = ''
}

const fetchData = async () => {
  loading.value = true
  error.value = ''
  try {
    const [returnsResponse, ordersResponse] = await Promise.all([
      apiClient.get('/api/returns'),
      apiClient.get('/api/my-orders'),
    ])
    returns.value = readApiList(returnsResponse.data)
    orders.value = readApiList(ordersResponse.data)
    const requestedOrder = Number(route.query.order)
    if (requestedOrder && eligibleOrders.value.some((order) => Number(order.id) === requestedOrder)) {
      selectOrder(requestedOrder)
    }
  } catch (requestError) {
    returns.value = []
    orders.value = []
    const message = requestError.response?.data?.message || 'Vui lòng kiểm tra kết nối và thử lại.'
    error.value = message
    toast.add({ severity: 'error', summary: 'Không thể tải dữ liệu', detail: message, life: 3500 })
  } finally {
    loading.value = false
  }
}

const submitReturn = async () => {
  if (!canSubmit.value) return
  submitting.value = true
  returnOperationKey.value ||= operationKey(`customer-return-${selectedOrderId.value}`)
  try {
    await apiClient.post(`/api/orders/${selectedOrderId.value}/returns`, {
      reason: reason.value.trim(),
      items: selectedItems.value,
      idempotency_key: returnOperationKey.value,
    })
    toast.add({ severity: 'success', summary: 'Đã gửi yêu cầu', detail: 'KomiBook đã ghi nhận yêu cầu trả hàng.', life: 3500 })
    reason.value = ''
    selectOrder(null)
    await router.replace({ query: {} })
    await fetchData()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể gửi yêu cầu', detail: error.response?.data?.message || 'Vui lòng kiểm tra lại thông tin.', life: 4000 })
  } finally {
    submitting.value = false
  }
}

watch(() => route.query.order, (value) => {
  if (value && eligibleOrders.value.some((order) => Number(order.id) === Number(value))) selectOrder(value)
})

onMounted(fetchData)
</script>

<template>
  <div class="min-h-screen bg-background">
    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl flex flex-col lg:flex-row items-stretch gap-xl">
      <UserSidebar :user="authStore.user" />
      <main class="flex-1 min-w-0 space-y-6" aria-labelledby="returns-title">
        <section class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow p-lg md:p-xl">
          <div class="mb-6">
            <h1 id="returns-title" class="text-2xl font-black text-on-surface">Trả hàng & hoàn tiền</h1>
            <p class="text-sm text-on-surface-variant mt-1">Áp dụng cho sách vật lý có chính sách trả hàng trong đơn mua. Thời hạn được tính từ lúc bạn xác nhận đã nhận hàng; ebook không thuộc luồng trả hàng vật lý.</p>
          </div>

          <div v-if="loading" class="py-12 text-center text-on-surface-variant" role="status" aria-live="polite">Đang tải dữ liệu...</div>
          <div v-else-if="error" class="rounded-2xl border border-error/20 bg-error-container/30 p-6 text-center" role="alert">
            <h2 class="text-lg font-bold text-on-surface">Không thể tải dữ liệu trả hàng</h2>
            <p class="mt-2 text-sm text-on-surface-variant">{{ error }}</p>
            <button type="button" class="mt-4 min-h-11 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-on-primary" @click="fetchData">Thử lại</button>
          </div>
          <form v-else class="space-y-5" @submit.prevent="submitReturn">
            <label class="block">
              <span class="block text-sm font-bold text-on-surface mb-2">Đơn hàng đủ điều kiện</span>
              <select :value="selectedOrderId || ''" class="min-h-11 w-full rounded-xl border border-outline-variant bg-surface px-4 py-3" @change="selectOrder($event.target.value)">
                <option value="">Chọn đơn hàng</option>
                <option v-for="order in eligibleOrders" :key="order.id" :value="order.id">
                  #{{ order.order_code || order.id }} · {{ formatMoney(order.total_amount) }}
                </option>
              </select>
            </label>

            <div v-if="selectedOrder" class="space-y-3">
              <p class="text-sm font-bold text-on-surface">Chọn sách và số lượng trả</p>
              <div v-for="item in physicalItems" :key="item.id" class="flex items-center justify-between gap-4 rounded-xl border border-outline-variant/40 p-4">
                <div>
                  <p class="font-bold text-sm text-on-surface">{{ item.book?.title || 'Sách giấy' }}</p>
                  <p class="text-xs text-on-surface-variant">Đã mua: {{ item.quantity }} · {{ formatMoney(item.price) }}/quyển</p>
                  <p class="mt-1 text-xs font-medium text-primary">Thời hạn yêu cầu: {{ item.return_policy?.return_window_days }} ngày sau khi nhận</p>
                </div>
                <input v-model.number="quantities[item.id]" type="number" min="0" :max="item.quantity" class="min-h-11 w-20 rounded-lg border border-outline-variant px-3 py-2 text-center" :aria-label="`Số lượng trả ${item.book?.title || ''}`">
              </div>
              <label class="block">
                <span class="block text-sm font-bold text-on-surface mb-2">Lý do trả hàng</span>
                <textarea v-model="reason" rows="4" minlength="10" maxlength="2000" class="w-full rounded-xl border border-outline-variant bg-surface px-4 py-3" placeholder="Mô tả tình trạng sản phẩm (ít nhất 10 ký tự)"></textarea>
              </label>
              <button type="submit" :disabled="!canSubmit" class="min-h-11 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-on-primary disabled:opacity-50">
                {{ submitting ? 'Đang gửi...' : 'Gửi yêu cầu trả hàng' }}
              </button>
            </div>
            <p v-else-if="eligibleOrders.length === 0" class="rounded-xl bg-surface-container-low p-4 text-sm text-on-surface-variant">
              Hiện không có đơn sách vật lý đã nhận nào còn đủ điều kiện trả hàng theo chính sách lúc mua.
            </p>
          </form>
        </section>

        <section class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow p-lg md:p-xl">
          <h2 class="text-lg font-black text-on-surface mb-4">Lịch sử yêu cầu</h2>
          <p v-if="!loading && returns.length === 0" class="py-8 text-center text-sm text-on-surface-variant">Bạn chưa có yêu cầu trả hàng.</p>
          <div v-else class="space-y-3">
            <article v-for="entry in returns" :key="entry.id" class="rounded-2xl border border-outline-variant/30 p-4">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-black text-on-surface">Đơn #{{ entry.order_code || entry.order_id }}</p>
                  <p class="text-xs text-on-surface-variant mt-1">{{ formatReturnDate(entry.requested_at) }} · {{ entry.reason }}</p>
                </div>
                <span :class="['rounded-full px-3 py-1 text-xs font-bold', statusOf(entry.status).tone]">{{ statusOf(entry.status).label }}</span>
              </div>
              <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm">
                <span class="text-on-surface-variant">{{ entry.items?.map((item) => `${item.book?.title || 'Sách'} ×${item.quantity}`).join(', ') }}</span>
                <strong class="text-primary">{{ formatMoney(entry.refund_amount, entry.currency) }}</strong>
              </div>
              <ol v-if="entry.transitions?.length" class="mt-4 space-y-2 border-l-2 border-outline-variant/40 pl-4">
                <li v-for="transition in entry.transitions" :key="`${transition.to}-${transition.occurred_at}`" class="text-xs text-on-surface-variant">
                  <strong class="text-on-surface">{{ statusOf(transition.to).label }}</strong>
                  · {{ formatReturnDate(transition.occurred_at) }}
                  <span v-if="transition.reason" class="block mt-0.5">{{ transition.reason }}</span>
                </li>
              </ol>
              <p v-if="entry.resolution_reason" class="mt-3 rounded-lg bg-surface-container-low px-3 py-2 text-xs text-on-surface-variant"><strong>Kết quả xử lý:</strong> {{ entry.resolution_reason }}</p>
              <p v-if="entry.status === 'refunded'" class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700">Khoản hoàn đã được chuyển vào Ví KomiBook của bạn.</p>
              <p v-if="entry.refund_transaction?.failure_reason" class="mt-3 rounded-lg bg-error-container px-3 py-2 text-xs text-error">{{ entry.refund_transaction.failure_reason }}</p>
            </article>
          </div>
        </section>
      </main>
    </div>
  </div>
</template>

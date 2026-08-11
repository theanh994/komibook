<template>
  <div class="min-h-screen bg-background">
    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl flex flex-col lg:flex-row items-stretch gap-xl">
      
      <!-- Sidebar -->
      <UserSidebar :user="authStore.user" />

      <!-- Main Content -->
      <main class="flex-1 min-w-0 w-full flex flex-col" aria-labelledby="orders-title">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden flex-1 flex flex-col">
          <div class="p-lg md:p-xl border-b border-outline-variant/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h1 ref="ordersHeading" id="orders-title" tabindex="-1" class="text-2xl font-black text-on-surface tracking-tight mb-1">Đơn hàng của tôi</h1>
              <p class="text-sm text-on-surface-variant font-medium">Theo dõi lịch sử mua sắm và trạng thái đơn hàng của bạn.</p>
            </div>
            
            <div class="flex max-w-full gap-1 overflow-x-auto p-1 bg-surface-container-low rounded-xl border border-outline-variant/20" role="group" aria-label="Lọc đơn hàng theo trạng thái">
              <button 
                v-for="filter in statusFilters" 
                :key="filter.value"
                type="button"
                :aria-pressed="currentFilter === filter.value"
                @click="currentFilter = filter.value"
                class="min-h-11 px-3.5 py-2 rounded-lg text-sm font-bold transition-colors border-none cursor-pointer whitespace-nowrap"
                :class="currentFilter === filter.value ? 'bg-primary text-on-primary shadow-xs' : 'text-outline hover:text-on-surface'"
              >
                {{ filter.label }}
              </button>
            </div>
          </div>

          <div class="p-lg md:p-xl">
            <!-- Loading -->
            <div v-if="loading" class="py-20 flex flex-col items-center gap-4" role="status" aria-live="polite">
              <div class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></div>
              <p class="text-sm font-bold text-outline">Đang tải đơn hàng...</p>
            </div>

            <div v-else-if="error" class="py-16 text-center" role="alert">
              <span class="material-symbols-outlined text-5xl text-error" aria-hidden="true">inventory</span>
              <h2 class="mt-3 text-xl font-bold text-on-surface">Không thể tải đơn hàng</h2>
              <p class="mx-auto mt-2 max-w-sm text-sm text-on-surface-variant">{{ error }}</p>
              <button type="button" class="mt-5 min-h-11 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-on-primary" @click="fetchOrders">
                Thử lại
              </button>
            </div>

            <!-- Empty State -->
            <div v-else-if="filteredOrders.length === 0" class="py-16 text-center animate-fade-in">
              <div class="w-20 h-20 bg-surface-container-high rounded-3xl flex items-center justify-center mx-auto mb-4 text-outline/40 border border-outline-variant/10">
                <span class="material-symbols-outlined text-4xl">inventory_2</span>
              </div>
              <h2 class="text-lg font-bold text-on-surface mb-1 tracking-tight">{{ orders.length ? 'Không có đơn phù hợp bộ lọc' : 'Chưa có đơn hàng nào' }}</h2>
              <p class="text-sm text-on-surface-variant mb-6 max-w-sm mx-auto font-medium leading-relaxed">
                {{ orders.length ? 'Hãy chọn trạng thái khác để xem các đơn hiện có.' : 'Khám phá danh mục để tìm cuốn sách phù hợp với bạn.' }}
              </p>
              <button v-if="orders.length === 0" type="button" @click="$router.push('/catalog')" class="min-h-11 bg-primary text-on-primary px-5 py-2.5 rounded-xl font-bold text-sm shadow-md hover:bg-primary/90 transition-colors border-none cursor-pointer flex items-center gap-2 mx-auto">
                <span>Khám phá KomiBook</span>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
              </button>
            </div>

            <!-- Orders List -->
            <div v-else class="space-y-lg">
              <div v-for="order in filteredOrders" :key="order.id" class="bg-surface-container-low/30 rounded-2xl border border-outline-variant/20 overflow-hidden hover:border-primary/30 transition-all group animate-fade-in">
                <!-- Order Header -->
                <div class="p-lg bg-surface-container-low/50 border-b border-outline-variant/10 flex flex-wrap items-center justify-between gap-4">
                  <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                      <span class="material-symbols-outlined">inventory_2</span>
                    </div>
                    <div>
                      <router-link
                        :to="`/tracking/${order.id}`"
                        class="inline-flex min-h-11 items-center text-sm font-black text-on-surface uppercase tracking-wider hover:text-primary hover:underline underline-offset-4 transition-colors"
                      >
                        #{{ order.order_code || order.id }}
                      </router-link>
                      <div class="text-xs font-bold text-outline">{{ formatDate(order.created_at) }}</div>
                    </div>
                  </div>
                  <span :class="['px-3 py-1.5 rounded-lg text-xs font-black tracking-wide shadow-sm', getConsolidatedStatusStyle(order)]">
                    {{ getConsolidatedStatusText(order) }}
                  </span>
                </div>

                <!-- Order Items -->
                <div class="p-lg space-y-md">
                  <div v-for="item in order.items" :key="item.id" class="flex items-start gap-md">
                    <div class="w-16 h-24 overflow-hidden bg-surface-container-high shrink-0 shadow-sm border border-outline-variant/10">
                      <img v-if="item.book?.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="`Bìa sách ${item.book.title}`" class="w-full h-full object-contain" />
                      <div v-else class="w-full h-full flex items-center justify-center text-outline">
                        <span class="material-symbols-outlined text-xl">image</span>
                      </div>
                    </div>
                    <div class="flex-1 min-w-0">
                      <h4 class="text-sm font-bold text-on-surface truncate leading-tight mb-1">{{ item.book?.title || 'Sách không còn tồn tại' }}</h4>
                      <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-black text-outline px-1.5 py-0.5 bg-surface-container-low rounded border border-outline-variant/20">
                          {{ item.book?.type === 'ebook' ? 'E-book' : 'Sách giấy' }}
                        </span>
                        <span class="text-xs text-on-surface-variant font-medium">x{{ item.quantity }}</span>
                      </div>
                      <div class="text-sm font-bold text-primary">{{ formatCurrency(item.price) }}</div>
                    </div>
                    <div v-if="order.status !== 'cancelled' && item.book?.type === 'ebook' && order.status === 'completed'" class="shrink-0">
                      <button 
                        @click="$router.push(`/reader/${order.id}/${item.book.id}`)" 
                        class="px-4 py-2 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-on-primary transition-all border-none cursor-pointer flex items-center gap-1 text-xs font-bold"
                      >
                        <span class="material-symbols-outlined text-[18px]">auto_stories</span>
                        Đọc ngay
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Order Footer -->
                <div class="p-lg pt-0 flex items-center justify-between">
                  <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-outline uppercase tracking-wider mb-1">Tổng thanh toán</span>
                    <span class="text-lg font-black text-primary">{{ formatCurrency(order.total_amount) }}</span>
                  </div>
                  <div class="flex items-center gap-3 flex-wrap justify-end">
                    <button
                      v-if="order.can_confirm_receipt"
                      @click="$router.push(`/tracking/${order.id}`)"
                      class="min-h-11 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-on-primary transition-opacity hover:opacity-90 flex items-center gap-1 cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-[18px]">package_2</span>
                      Xác nhận đã nhận hàng
                    </button>
                    <button
                      v-if="canRequestReturn(order)"
                      @click="$router.push({ path: '/returns', query: { order: order.id } })"
                      class="px-3 py-1.5 rounded-xl border border-primary text-xs font-bold text-primary hover:bg-primary hover:text-on-primary transition-all flex items-center gap-1 cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-[16px]">assignment_return</span>
                      Yêu cầu trả hàng
                    </button>
                    <button
                      v-if="canPayOrder(order)"
                      @click="payNow(order)"
                      :disabled="payingOrderId !== null"
                      class="px-4 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary/90 transition-all border-none cursor-pointer flex items-center gap-1 text-xs font-bold shadow-sm disabled:opacity-50 animate-pulse"
                    >
                      <span v-if="payingOrderId === order.id" class="w-4 h-4 border-2 border-on-primary/20 border-t-on-primary rounded-full animate-spin"></span>
                      <span v-else class="material-symbols-outlined text-[18px]">credit_card</span>
                      Thanh toán ngay
                    </button>
                    <button
                      v-if="canCancelOrder(order)"
                      @click="openCancelModal(order, $event)"
                      class="px-3 py-1.5 rounded-xl border border-error/30 text-xs font-bold text-error hover:bg-error/10 transition-all flex items-center gap-1 cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-[16px]">cancel</span>
                      Hủy đơn
                    </button>
                    <button
                      v-if="order.status !== 'cancelled' && hasPhysicalItems(order)"
                      @click="$router.push(`/tracking/${order.id}`)"
                      class="px-3 py-1.5 rounded-xl bg-secondary/10 text-secondary hover:bg-secondary hover:text-on-secondary transition-all border-none cursor-pointer flex items-center gap-1 text-xs font-bold"
                    >
                      <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                      Theo dõi
                    </button>
                    <button @click="$router.push(`/orders/invoice/${order.id}`)" class="px-3 py-1.5 rounded-xl border border-outline-variant/30 text-xs font-bold text-on-surface hover:bg-surface-container-high transition-all flex items-center gap-1 cursor-pointer">
                      <span class="material-symbols-outlined text-[16px]">print</span>
                      In hóa đơn
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Modal Xác nhận Hủy Đơn -->
    <div v-if="showCancelModal && canCancelOrder(selectedOrderToCancel)" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in" role="presentation">
      <section
        ref="cancelDialog"
        class="bg-surface rounded-3xl p-6 max-w-md w-full shadow-2xl border border-outline-variant/20 space-y-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cancel-order-title"
        aria-describedby="cancel-order-description"
        tabindex="-1"
        @keydown="trapCancelFocus"
      >
        <div class="flex items-center gap-3 text-error">
          <span class="material-symbols-outlined text-[32px]">warning</span>
          <h3 id="cancel-order-title" class="text-lg font-bold text-on-surface">Xác nhận Hủy Đơn Hàng</h3>
        </div>
        <p id="cancel-order-description" class="text-sm text-on-surface-variant leading-relaxed">
          Bạn có chắc chắn muốn hủy đơn hàng <strong class="text-on-surface">#{{ selectedOrderToCancel?.order_code || selectedOrderToCancel?.id }}</strong> không?
          <span v-if="selectedOrderToCancel?.cancellation_scope" class="block mt-2">
            Phạm vi hủy gồm {{ selectedOrderToCancel.cancellation_scope.count }} đơn: {{ formatCancellationScope(selectedOrderToCancel.cancellation_scope) }}.
          </span>
          <span v-if="selectedOrderToCancel?.cancellation_scope?.type === 'checkout_session'" class="block mt-2 font-semibold text-error">
            Thao tác này áp dụng cho toàn bộ đơn hàng trong cùng phiên thanh toán.
          </span>
        </p>
        <div class="flex items-center justify-end gap-3 pt-2">
          <button ref="cancelCloseButton" type="button" :disabled="cancelling" @click="closeCancelModal" class="px-4 py-2 rounded-xl border border-outline-variant/30 text-xs font-bold text-on-surface hover:bg-surface-container-high transition-all cursor-pointer disabled:opacity-50">
            Bỏ qua
          </button>
          <button type="button" @click="confirmCancelOrder" :disabled="cancelling" class="px-4 py-2 rounded-xl bg-error text-on-error hover:bg-error/90 text-xs font-bold transition-all border-none cursor-pointer flex items-center gap-1 disabled:opacity-50">
            <span v-if="cancelling" class="w-4 h-4 border-2 border-on-error/20 border-t-on-error rounded-full animate-spin"></span>
            <span>Xác nhận Hủy</span>
          </button>
        </div>
      </section>
    </div>



  </div>
</template>

<script>
import { canCancelOrder, formatCancellationScope, isValidCancellationScope } from '@/utils/buyerCancellation'

const normalizeOrderField = (value) => (
  typeof value === 'string' ? value.trim().toLowerCase() : ''
)

export { canCancelOrder, isValidCancellationScope }

export const canPayOrder = (order) => (
  normalizeOrderField(order?.status) === 'pending'
  && normalizeOrderField(order?.payment_status) === 'unpaid'
  && ['online', 'vnpay'].includes(normalizeOrderField(order?.payment_method))
)

const paymentResultHints = Object.freeze({
  success: {
    severity: 'info',
    summary: 'Đã nhận phản hồi thanh toán',
    detail: 'KomiBook đang đối chiếu phản hồi thanh toán với trạng thái đơn hàng.'
  },
  failed: {
    severity: 'warn',
    summary: 'Đã nhận phản hồi thanh toán',
    detail: 'KomiBook đang đối chiếu phản hồi không thành công với trạng thái đơn hàng.'
  },
  pending: {
    severity: 'info',
    summary: 'Đang đối chiếu thanh toán',
    detail: 'KomiBook đang đối chiếu phản hồi thanh toán với trạng thái đơn hàng.'
  },
  invalid_signature: {
    severity: 'warn',
    summary: 'Không thể xác thực phản hồi thanh toán',
    detail: 'KomiBook đang đối chiếu trạng thái đơn hàng trước khi hiển thị kết quả.'
  },
  invalid_transaction: {
    severity: 'warn',
    summary: 'Không thể đối chiếu giao dịch',
    detail: 'KomiBook đang đối chiếu trạng thái đơn hàng trước khi hiển thị kết quả.'
  }
})

export const getPaymentResultHint = (value) => (
  typeof value === 'string' ? paymentResultHints[value] || null : null
)
</script>

<script setup>
import { ref, onBeforeUnmount, onMounted, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import { readApiList } from '@/services/apiContract'

import UserSidebar from '@/components/profile/UserSidebar.vue'

const authStore = useAuthStore()
const toast = useToast()
const route = useRoute()
const router = useRouter()

const orders = ref([])
const loading = ref(true)
const currentFilter = ref('all')
const payingOrderId = ref(null)
const error = ref('')

const showCancelModal = ref(false)
const selectedOrderToCancel = ref(null)
const cancelling = ref(false)
const cancelDialog = ref(null)
const cancelCloseButton = ref(null)
const returnFocusElement = ref(null)
const ordersHeading = ref(null)

const openCancelModal = (order, event) => {
  if (!canCancelOrder(order) || cancelling.value) return
  returnFocusElement.value = event?.currentTarget || document.activeElement
  selectedOrderToCancel.value = order
  showCancelModal.value = true
  nextTick(() => cancelCloseButton.value?.focus())
}

const closeCancelModal = ({ force = false, restoreFocus = true } = {}) => {
  if (cancelling.value && !force) return false

  showCancelModal.value = false
  selectedOrderToCancel.value = null

  if (restoreFocus) {
    nextTick(() => {
      if (returnFocusElement.value instanceof HTMLElement && returnFocusElement.value.isConnected) {
        returnFocusElement.value.focus()
      }
      returnFocusElement.value = null
    })
  } else {
    returnFocusElement.value = null
  }

  return true
}

const focusOrdersHeading = () => nextTick(() => ordersHeading.value?.focus())

const trapCancelFocus = (event) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    closeCancelModal()
    return
  }

  if (event.key !== 'Tab') return

  const focusableElements = cancelDialog.value?.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  )
  const focusable = Array.from(focusableElements || [])
  if (!focusable.length) {
    event.preventDefault()
    cancelDialog.value?.focus()
    return
  }

  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

const confirmCancelOrder = async () => {
  if (!canCancelOrder(selectedOrderToCancel.value) || cancelling.value) return
  cancelling.value = true
  try {
    const res = await apiClient.post(`/api/orders/${selectedOrderToCancel.value.id}/cancel`)
    toast.add({ severity: 'success', summary: 'Thành công', detail: res.data?.message || 'Hủy đơn hàng thành công.', life: 4000 })
    cancelling.value = false
    closeCancelModal({ restoreFocus: false })
    await fetchOrders()
    focusOrdersHeading()
  } catch (err) {
    const errorMsg = err.response?.data?.message || 'Không thể hủy đơn hàng này.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: errorMsg, life: 4000 })
  } finally {
    cancelling.value = false
  }
}

const statusFilters = [
  { label: 'Tất cả', value: 'all' },
  { label: 'Chờ xử lý', value: 'pending' },
  { label: 'Đã hoàn thành', value: 'completed' },
  { label: 'Đã hủy', value: 'cancelled' }
]

const filteredOrders = computed(() => {
  if (currentFilter.value === 'all') return orders.value
  if (currentFilter.value === 'pending') {
    return orders.value.filter(o => ['pending', 'confirmed', 'processing'].includes(o.status))
  }
  return orders.value.filter(o => o.status === currentFilter.value)
})

const fetchOrders = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await apiClient.get('/api/my-orders')
    const fetched = readApiList(res.data)
    orders.value = fetched.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
    return true
  } catch {
    orders.value = []
    error.value = 'Vui lòng kiểm tra kết nối và thử lại.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải đơn hàng', life: 3000 })
    return false
  } finally { loading.value = false }
}

const hasPhysicalItems = (order) => {
  return order.items?.some(item => item.book?.type === 'physical')
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
  }).format(new Date(dateString))
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

// Consolidated status: ưu tiên cancelled > unpaid > pending/confirmed/processing -> Chờ xử lý
const getConsolidatedStatusText = (order) => {
  if (order.status === 'cancelled') return 'Đã hủy'
  if (order.payment_status === 'unpaid' && order.status === 'pending') return 'Chưa thanh toán'
  if (['pending', 'confirmed', 'processing'].includes(order.status)) return 'Chờ xử lý'
  if (order.status === 'shipped') return 'Đang vận chuyển'
  if (order.status === 'completed') return 'Hoàn thành'
  return 'Trạng thái chưa xác định'
}

const getConsolidatedStatusStyle = (order) => {
  if (order.status === 'cancelled') return 'bg-error-container text-error border border-error/20'
  if (order.payment_status === 'unpaid' && order.status === 'pending') return 'bg-rose-100 text-rose-700 border border-rose-200'
  if (['pending', 'confirmed', 'processing'].includes(order.status)) return 'bg-amber-100 text-amber-700 border border-amber-200'
  if (order.status === 'shipped') return 'bg-indigo-100 text-indigo-700 border border-indigo-200'
  if (order.status === 'completed') return 'bg-emerald-100 text-emerald-700 border border-emerald-200'
  return 'bg-surface-container-high text-on-surface-variant'
}

const canRequestReturn = (order) => order.status === 'completed'
  && order.shipping_status === 'delivered'
  && order.items?.some((item) => item.book?.type === 'physical' && item.return_policy?.is_returnable)
  && !['refunding', 'refunded'].includes(order.refund_status)

const payNow = async (order) => {
  if (!canPayOrder(order) || payingOrderId.value !== null) return

  payingOrderId.value = order.id
  try {
    const res = await apiClient.post('/api/vnpay/create', { order_id: order.id })
    if (res.data && res.data.url) {
      window.location.href = res.data.url
    } else {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tạo liên kết thanh toán', life: 3000 })
    }
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Lỗi kết nối đến máy chủ thanh toán'
    toast.add({ severity: 'error', summary: 'Lỗi thanh toán', detail: errorMsg, life: 3000 })
  } finally {
    payingOrderId.value = null
  }
}

const removePaymentQuery = () => {
  if (!Object.prototype.hasOwnProperty.call(route.query, 'payment')) return

  const preservedQuery = { ...route.query }
  delete preservedQuery.payment
  router.replace({ query: preservedQuery })
}

onMounted(async () => {
  const reconciled = await fetchOrders()
  const paymentHint = getPaymentResultHint(route.query.payment)

  if (reconciled && paymentHint) {
    toast.add({ ...paymentHint, life: 5000 })
  }

  removePaymentQuery()
})

onBeforeUnmount(() => closeCancelModal({ force: true, restoreFocus: false }))
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.4s ease-out forwards;
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

button {
  min-height: 44px;
}

button:focus-visible,
a:focus-visible {
  outline: 3px solid var(--p-primary-color, currentColor);
  outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
  .animate-fade-in,
  .animate-spin {
    animation: none !important;
  }
}
</style>

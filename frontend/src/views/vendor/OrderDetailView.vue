<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const orderId = route.params.id
const selectedOrder = ref(null)
const loading = ref(true)

const updatingShipping = ref(false)

// ─── Status Config ───
const statusMap = {
  pending:    { label: 'Chờ xử lý',    bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary', icon: 'pending_actions' },
  processing: { label: 'Đang xử lý',   bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary', icon: 'pending_actions' },
  shipped:    { label: 'Đang giao',    bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary', icon: 'local_shipping' },
  completed:  { label: 'Hoàn thành',   bg: 'bg-[#E6F4EA]', text: 'text-[#137333]', dot: 'bg-[#137333]', icon: 'check_circle' },
  cancelled:  { label: 'Đã hủy',       bg: 'bg-error-container', text: 'text-on-error-container', dot: 'bg-error', icon: 'cancel' },
}

const getStatus = (status) => statusMap[status] || statusMap.pending

const isTerminal = computed(() => {
  if (!selectedOrder.value) return true
  return ['completed', 'cancelled'].includes(selectedOrder.value.status)
})

const shippingStatusMap = {
  pending_pickup: { label: 'Chờ đơn vị vận chuyển nhận', icon: 'inventory_2' },
  picked_up: { label: 'Đơn vị vận chuyển đã nhận', icon: 'local_shipping' },
  delivering: { label: 'Đang giao đến khách hàng', icon: 'route' },
  awaiting_customer_confirmation: { label: 'Chờ khách xác nhận đã nhận', icon: 'hourglass_top' },
  delivered: { label: 'Đã giao thành công', icon: 'verified' },
  failed: { label: 'Giao hàng thất bại', icon: 'error' },
}

const currentShippingStatus = computed(() => {
  if (!selectedOrder.value) return null
  return shippingStatusMap[selectedOrder.value.shipping_status] || null
})

const shippingAction = computed(() => {
  const order = selectedOrder.value
  if (!order || isTerminal.value || order.shipping_status === 'failed') return null

  if (order.status === 'processing') {
    return { label: 'Bàn giao cho KomiBook Express', type: 'order', next: 'shipped', icon: 'package_2' }
  }
  if (order.status === 'shipped' && (!order.shipping_status || order.shipping_status === 'pending_pickup')) {
    return { label: 'Xác nhận đơn vị vận chuyển đã nhận', type: 'shipping', next: 'picked_up', icon: 'local_shipping' }
  }
  if (order.shipping_status === 'picked_up') {
    return { label: 'Bắt đầu giao hàng', type: 'shipping', next: 'delivering', icon: 'route' }
  }
  if (order.shipping_status === 'delivering') {
    return { label: 'Xác nhận ĐVVC đã giao tới khách', type: 'shipping', next: 'awaiting_customer_confirmation', icon: 'where_to_vote' }
  }
  return null
})

// ─── Formatters ───
const formatPrice = (val) => {
  if (!val && val !== 0) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
  })
}

// ─── API Calls ───
const fetchOrder = async () => {
  loading.value = true
  try {
    const res = await apiClient.get(`/api/vendor/orders/${orderId}`)
    selectedOrder.value = res.data.data
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải chi tiết đơn hàng.', life: 3000 })
    router.push({ name: 'vendor-orders' })
  } finally {
    loading.value = false
  }
}

const advanceShipping = async () => {
  const action = shippingAction.value
  if (!action || !selectedOrder.value) return
  updatingShipping.value = true
  try {
    const endpoint = action.type === 'order'
      ? `/api/vendor/orders/${selectedOrder.value.id}/status`
      : `/api/vendor/orders/${selectedOrder.value.id}/shipping`
    const payload = action.type === 'order'
      ? { status: action.next }
      : { shipping_status: action.next }
    const response = await apiClient.patch(endpoint, payload)
    selectedOrder.value = response.data.data
    toast.add({ severity: 'success', summary: 'Đã cập nhật hành trình', detail: action.label, life: 3000 })
  } catch (e) {
    const msg = e.response?.data?.message || 'Không thể cập nhật hành trình giao hàng.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {
    updatingShipping.value = false
  }
}

const goBack = () => {
  router.push({ name: 'vendor-orders' })
}

onMounted(() => {
  fetchOrder()
})
</script>

<template>
  <div class="pb-xl w-full pt-6">
    <div>
      
      <!-- Back button & Page Header -->
      <button type="button" @click="goBack" class="flex min-h-11 items-center gap-xs text-on-surface-variant hover:text-primary mb-md font-label-md transition-colors">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        Quay lại danh sách
      </button>

      <div v-if="loading" role="status" aria-live="polite" class="flex flex-col items-center justify-center py-20">
        <span class="material-symbols-outlined animate-spin text-primary text-4xl mb-sm">progress_activity</span>
        <span class="text-on-surface-variant">Đang tải chi tiết đơn hàng...</span>
      </div>

      <template v-else-if="selectedOrder">
        <!-- Page Header (Order Header) -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-md mb-xl">
          <div>
            <div class="flex items-center gap-sm mb-xs">
              <h1 class="font-headline-lg text-headline-lg text-on-surface">Đơn hàng {{ selectedOrder.order_code }}</h1>
              <span :class="['font-label-md text-label-md px-sm py-xs rounded-full inline-flex items-center gap-xs', getStatus(selectedOrder.status).bg, getStatus(selectedOrder.status).text]">
                <span class="material-symbols-outlined text-[16px]">{{ getStatus(selectedOrder.status).icon }}</span>
                {{ getStatus(selectedOrder.status).label }}
              </span>
            </div>
            <p class="font-body-md text-body-md text-on-surface-variant flex items-center gap-xs">
              <span class="material-symbols-outlined text-[18px]">calendar_today</span>
              Ngày đặt: {{ formatDate(selectedOrder.created_at) }}
            </p>
          </div>
          <div class="flex flex-wrap gap-sm">
            <button type="button" class="flex min-h-11 items-center gap-xs px-md py-sm border-outline border rounded-lg text-on-surface hover:bg-surface-container transition-colors bg-surface-container-lowest font-label-md text-label-md shadow-sm">
              <span class="material-symbols-outlined text-[18px]">print</span>
              In hóa đơn
            </button>
            <button type="button" class="flex min-h-11 items-center gap-xs px-md py-sm border border-secondary text-secondary rounded-lg hover:bg-secondary/10 transition-colors bg-transparent font-label-md text-label-md" @click="router.push({ name: 'vendor-returns' })">
              <span class="material-symbols-outlined text-[18px]">undo</span>
              Hoàn tiền
            </button>
          </div>
        </div>

        <!-- 2 Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
          <!-- Main Column (Left) -->
          <div class="lg:col-span-2 space-y-lg">
            
            <!-- Product List -->
            <div class="bg-surface-container-lowest rounded-xl shadow-[0_4px_12px_rgba(26,58,90,0.04)] border border-outline-variant/30 overflow-hidden">
              <div class="px-lg py-md border-b border-outline-variant/30 bg-surface-container-lowest flex justify-between items-center">
                <h3 class="font-headline-md text-headline-md text-on-surface flex items-center gap-sm">
                  <span class="material-symbols-outlined">inventory_2</span>
                  Danh sách sản phẩm
                </h3>
                <span class="font-label-md text-label-md text-on-surface-variant bg-surface-container-low px-sm py-xs rounded-full">{{ selectedOrder.items?.length || 0 }} Sản phẩm</span>
              </div>
              
              <div class="divide-y divide-outline-variant/20">
                <!-- Items -->
                <div v-for="item in selectedOrder.items" :key="item.id" class="p-lg flex gap-md items-start">
                  <div class="w-24 h-32 flex-shrink-0 rounded-lg overflow-hidden border border-outline-variant/20 bg-surface-container-low">
                    <img v-if="item.book?.cover_image" :src="item.book.cover_image" :alt="item.book.title" class="w-full h-full object-cover"/>
                    <div v-else class="w-full h-full flex justify-center items-center text-outline">
                      <span class="material-symbols-outlined text-3xl">image</span>
                    </div>
                  </div>
                  <div class="flex-grow flex flex-col h-full justify-between">
                    <div>
                      <div class="flex justify-between items-start mb-xs">
                        <h4 class="font-headline-md text-[18px] font-semibold text-on-surface line-clamp-2">{{ item.book?.title }}</h4>
                        <span class="font-label-md text-label-md font-bold text-on-surface whitespace-nowrap ml-md">{{ formatPrice(item.price) }}</span>
                      </div>
                      <p class="font-body-md text-body-md text-on-surface-variant mb-sm">{{ item.book?.author || 'Không rõ tác giả' }}</p>
                      <span class="inline-flex items-center gap-xs px-2 py-1 rounded-full font-label-md text-[12px]" :class="item.book?.is_physical ? 'bg-surface-container text-on-surface-variant' : 'bg-surface-container-high text-primary'">
                        <span class="material-symbols-outlined text-[14px]">{{ item.book?.is_physical ? 'book' : 'devices' }}</span> 
                        {{ item.book?.is_physical ? 'Sách vật lý' : 'Ebook' }}
                      </span>
                    </div>
                    <div class="flex justify-between items-center mt-md">
                      <div class="text-on-surface-variant font-body-md text-body-md">Số lượng: <span class="font-medium text-on-surface">{{ item.quantity }}</span></div>
                      <div class="font-headline-md text-[16px] text-primary">Thành tiền: {{ formatPrice(item.price * item.quantity) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Payment Summary -->
            <div class="bg-surface-container-lowest rounded-xl shadow-[0_4px_12px_rgba(26,58,90,0.04)] border border-outline-variant/30 overflow-hidden">
              <div class="px-lg py-md border-b border-outline-variant/30 bg-surface-container-lowest">
                <h3 class="font-headline-md text-headline-md text-on-surface flex items-center gap-sm">
                  <span class="material-symbols-outlined">receipt_long</span>
                  Tóm tắt thanh toán
                </h3>
              </div>
              <div class="p-lg space-y-sm font-body-md text-body-md">
                <div class="flex justify-between items-center text-on-surface-variant">
                  <span>Tạm tính</span>
                  <span class="text-on-surface font-medium">{{ formatPrice(selectedOrder.total_amount) }}</span>
                </div>
                <!-- VNPAY does not return separate discount info here natively in this basic structure, assuming total is final -->
                <div class="flex justify-between items-center text-on-surface-variant">
                  <span>Phương thức</span>
                  <span class="text-on-surface font-medium">{{ selectedOrder.payment_method === 'vnpay' ? 'VNPAY' : selectedOrder.payment_method }}</span>
                </div>
                <div class="pt-md mt-sm border-t border-outline-variant/30 flex justify-between items-center">
                  <span class="font-headline-md text-[18px] text-on-surface">Tổng cộng</span>
                  <span class="font-headline-md text-[24px] text-primary">{{ formatPrice(selectedOrder.total_amount) }}</span>
                </div>
              </div>
            </div>

          </div>

          <!-- Side Column (Right) -->
          <div class="space-y-lg">
            
            <!-- Customer Info -->
            <div class="bg-surface-container-lowest rounded-xl shadow-[0_4px_12px_rgba(26,58,90,0.04)] border border-outline-variant/30 overflow-hidden p-lg">
              <h3 class="font-headline-md text-[18px] font-semibold text-on-surface mb-md flex items-center gap-sm">
                <span class="material-symbols-outlined text-outline">person</span>
                Thông tin khách hàng
              </h3>
              <div class="flex items-center gap-md mb-md">
                <div class="w-12 h-12 bg-primary-container text-on-primary-container rounded-full flex items-center justify-center font-headline-md text-[20px] font-bold">
                  {{ selectedOrder.user?.name?.charAt(0).toUpperCase() || 'U' }}
                </div>
                <div>
                  <div class="font-label-md text-[16px] font-bold text-on-surface">{{ selectedOrder.user?.name || 'Khách vãng lai' }}</div>
                  <div class="font-body-md text-[14px] text-on-surface-variant">Khách hàng</div>
                </div>
              </div>
              <div class="space-y-sm font-body-md text-body-md">
                <div v-if="selectedOrder.user?.email" class="flex items-center gap-sm text-on-surface-variant">
                  <span class="material-symbols-outlined text-[18px]">mail</span>
                  <a :href="'mailto:' + selectedOrder.user?.email" class="hover:text-primary transition-colors">{{ selectedOrder.user?.email }}</a>
                </div>
                <div v-if="selectedOrder.phone" class="flex items-center gap-sm text-on-surface-variant">
                  <span class="material-symbols-outlined text-[18px]">call</span>
                  <a :href="'tel:' + selectedOrder.phone" class="hover:text-primary transition-colors">{{ selectedOrder.phone }}</a>
                </div>
              </div>
            </div>

            <!-- Shipping Address -->
            <div class="bg-surface-container-lowest rounded-xl shadow-[0_4px_12px_rgba(26,58,90,0.04)] border border-outline-variant/30 overflow-hidden p-lg">
              <div class="flex justify-between items-center mb-md">
                <h3 class="font-headline-md text-[18px] font-semibold text-on-surface flex items-center gap-sm">
                  <span class="material-symbols-outlined text-outline">local_shipping</span>
                  Địa chỉ nhận hàng
                </h3>
              </div>
              <p v-if="selectedOrder.shipping_address" class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                <span class="font-medium text-on-surface block mb-xs">Địa chỉ</span>
                {{ selectedOrder.shipping_address }}
              </p>
              <p v-else class="font-body-md text-body-md text-outline italic">Không có thông tin giao hàng (Đơn hàng điện tử)</p>
            </div>

            <!-- Payment Method -->
            <div class="bg-surface-container-lowest rounded-xl shadow-[0_4px_12px_rgba(26,58,90,0.04)] border border-outline-variant/30 overflow-hidden p-lg">
              <h3 class="font-headline-md text-[18px] font-semibold text-on-surface mb-md flex items-center gap-sm">
                <span class="material-symbols-outlined text-outline">account_balance_wallet</span>
                Phương thức thanh toán
              </h3>
              <div class="flex items-center gap-md p-md bg-surface-container-low rounded-lg border border-outline-variant/20">
                <span v-if="selectedOrder.payment_method === 'vnpay'" class="font-bold text-primary text-xl">VNPAY</span>
                <span v-else class="material-symbols-outlined text-[32px] text-primary">credit_card</span>
                <div>
                  <div class="font-label-md text-[16px] font-medium text-on-surface">
                    {{ selectedOrder.payment_method === 'vnpay' ? 'Thanh toán qua VNPAY' : 'Thanh toán khi nhận hàng' }}
                  </div>
                  <div class="font-body-md text-[14px]" :class="selectedOrder.payment_status === 'paid' ? 'text-green-600' : 'text-amber-600'">
                    {{ selectedOrder.payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Demo shipping workflow -->
            <div class="bg-surface-container-lowest rounded-xl shadow-[0_4px_12px_rgba(26,58,90,0.04)] border border-outline-variant/30 overflow-hidden p-lg">
              <div class="mb-md flex items-start justify-between gap-sm">
                <h3 class="font-headline-md text-[18px] font-semibold text-on-surface flex items-center gap-sm">
                  <span class="material-symbols-outlined text-outline">local_shipping</span>
                  Vận chuyển đơn hàng
                </h3>
                <span class="rounded-full bg-surface-container-high px-2 py-1 text-[11px] font-bold text-primary">MÔ PHỎNG</span>
              </div>

              <div class="space-y-sm rounded-lg border border-outline-variant/20 bg-surface-container-low p-md">
                <div class="flex items-start gap-sm">
                  <span class="material-symbols-outlined text-primary">{{ currentShippingStatus?.icon || 'package_2' }}</span>
                  <div class="min-w-0">
                    <p class="font-label-md font-semibold text-on-surface">{{ currentShippingStatus?.label || 'Chưa bàn giao vận chuyển' }}</p>
                    <p class="mt-1 text-sm text-on-surface-variant">
                      {{ selectedOrder.shipping_carrier || 'Hệ thống sẽ tự gán KomiBook Express khi bàn giao.' }}
                    </p>
                  </div>
                </div>
                <div v-if="selectedOrder.shipping_tracking_code" class="flex items-center justify-between gap-sm border-t border-outline-variant/20 pt-sm text-sm">
                  <span class="text-on-surface-variant">Mã vận đơn</span>
                  <span class="font-mono font-semibold text-on-surface">{{ selectedOrder.shipping_tracking_code }}</span>
                </div>
              </div>

              <button
                v-if="shippingAction"
                type="button"
                @click="advanceShipping"
                :disabled="updatingShipping"
                class="mt-md flex min-h-11 w-full items-center justify-center gap-xs rounded-lg bg-primary px-md py-sm font-label-md text-on-primary transition-opacity hover:opacity-90 disabled:cursor-wait disabled:opacity-50"
              >
                <span class="material-symbols-outlined text-[18px]" :class="{ 'animate-spin': updatingShipping }">
                  {{ updatingShipping ? 'progress_activity' : shippingAction.icon }}
                </span>
                {{ updatingShipping ? 'Đang cập nhật...' : shippingAction.label }}
              </button>

              <p v-else-if="selectedOrder.shipping_status === 'delivered' || selectedOrder.status === 'completed'" class="mt-md rounded-lg bg-emerald-50 p-sm text-sm font-medium text-emerald-700">
                Đơn hàng đã giao thành công và hoàn tất hành trình.
              </p>
              <p v-else-if="selectedOrder.shipping_status === 'awaiting_customer_confirmation'" class="mt-md rounded-lg bg-amber-50 p-sm text-sm font-medium text-amber-800">
                Đơn vị vận chuyển đã giao kiện hàng. Đơn chỉ hoàn tất sau khi khách hàng xác nhận đã nhận.
              </p>
              <p v-else-if="selectedOrder.shipping_status === 'failed'" class="mt-md rounded-lg bg-red-50 p-sm text-sm font-medium text-red-700">
                Giao hàng thất bại. Vui lòng liên hệ hỗ trợ để xử lý ngoại lệ.
              </p>
            </div>

          </div>
        </div>
      </template>
    </div>
  </div>
</template>

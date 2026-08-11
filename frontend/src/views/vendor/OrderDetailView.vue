<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const orderId = route.params.id
const selectedOrder = ref(null)
const loading = ref(true)
const invoiceModalVisible = ref(false)

const openInvoicePreview = () => {
  invoiceModalVisible.value = true
}

const printInvoice = () => {
  if (!invoiceModalVisible.value) {
    invoiceModalVisible.value = true
    setTimeout(() => {
      window.print()
    }, 300)
  } else {
    window.print()
  }
}

const updatingShipping = ref(false)

// ─── Status Config ───
const statusMap = {
  pending:    { label: 'Chờ xử lý',    bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary', icon: 'pending_actions' },
  confirmed:  { label: 'Đã xác nhận',  bg: 'bg-surface-container-high', text: 'text-primary', dot: 'bg-primary', icon: 'pending_actions' },
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

  if (['pending', 'confirmed', 'processing'].includes(order.status)) {
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

const copyToClipboard = (text, label) => {
  if (!text) return
  navigator.clipboard.writeText(text)
  toast.add({ severity: 'success', summary: 'Đã sao chép', detail: `Đã sao chép ${label} vào bộ nhớ tạm!`, life: 2500 })
}

const currentStepIndex = computed(() => {
  if (!selectedOrder.value) return 1
  const st = selectedOrder.value.status
  if (st === 'cancelled') return -1
  if (st === 'completed') return 4
  if (st === 'shipped') return 3
  if (st === 'confirmed' || st === 'processing') return 2
  return 1
})

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
            <button
              type="button"
              class="flex min-h-11 items-center gap-xs px-md py-sm border-outline border rounded-lg text-on-surface hover:bg-surface-container transition-colors bg-surface-container-lowest font-label-md text-label-md shadow-sm cursor-pointer"
              @click="openInvoicePreview"
            >
              <span class="material-symbols-outlined text-[18px]">visibility</span>
              Xem trước hóa đơn
            </button>
            <button
              type="button"
              class="flex min-h-11 items-center gap-xs px-md py-sm bg-primary text-on-primary rounded-lg hover:opacity-90 transition-colors font-label-md text-label-md shadow-sm cursor-pointer"
              @click="printInvoice"
            >
              <span class="material-symbols-outlined text-[18px]">print</span>
              In hóa đơn
            </button>
            <button type="button" class="flex min-h-11 items-center gap-xs px-md py-sm border border-secondary text-secondary rounded-lg hover:bg-secondary/10 transition-colors bg-transparent font-label-md text-label-md" @click="router.push({ name: 'vendor-returns' })">
              <span class="material-symbols-outlined text-[18px]">undo</span>
              Hoàn tiền
            </button>
          </div>
        </div>

        <!-- Order Stepper Timeline -->
        <div v-if="selectedOrder.status !== 'cancelled'" class="bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant/30 shadow-xs mb-xl">
          <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-base">alt_route</span>
            Tiến trình xử lý đơn hàng
          </h3>
          <div class="grid grid-cols-4 gap-2 relative">
            <div
              v-for="step in [
                { label: 'Đặt hàng', icon: 'shopping_bag', index: 1 },
                { label: 'Xác nhận & Xử lý', icon: 'inventory', index: 2 },
                { label: 'Đang giao hàng', icon: 'local_shipping', index: 3 },
                { label: 'Hoàn thành', icon: 'check_circle', index: 4 }
              ]"
              :key="step.index"
              class="flex flex-col items-center text-center relative z-10"
            >
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm mb-2 transition-all"
                :class="currentStepIndex >= step.index ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant'"
              >
                <span class="material-symbols-outlined text-xl">{{ step.icon }}</span>
              </div>
              <span
                class="text-xs font-bold"
                :class="currentStepIndex >= step.index ? 'text-primary' : 'text-on-surface-variant'"
              >
                {{ step.label }}
              </span>
            </div>
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
                        <h4 class="font-headline-md text-[18px] font-semibold text-on-surface leading-snug">{{ item.book?.title || item.title || `Sách #${item.book_id}` }}</h4>
                        <span class="font-label-md text-label-md font-bold text-on-surface whitespace-nowrap ml-md">{{ formatPrice(item.price) }}</span>
                      </div>
                      <p v-if="item.book?.author" class="font-body-md text-body-md text-on-surface-variant mb-sm">Tác giả: {{ item.book.author }}</p>
                      <span class="inline-flex items-center gap-xs px-2 py-1 rounded-full font-label-md text-[12px]" :class="(item.book?.is_physical ?? item.book?.type === 'physical') ? 'bg-surface-container text-on-surface-variant' : 'bg-surface-container-high text-primary'">
                        <span class="material-symbols-outlined text-[14px]">{{ (item.book?.is_physical ?? item.book?.type === 'physical') ? 'book' : 'devices' }}</span> 
                        {{ (item.book?.is_physical ?? item.book?.type === 'physical') ? 'Sách vật lý' : 'Ebook' }}
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
                  <span>Tạm tính (Tiền hàng)</span>
                  <span class="text-on-surface font-medium">{{ formatPrice(selectedOrder.subtotal_amount) }}</span>
                </div>
                <div class="flex justify-between items-center text-on-surface-variant">
                  <span>Phí vận chuyển (Phí ship)</span>
                  <span class="text-on-surface font-medium">
                    {{ selectedOrder.shipping_fee_amount > 0 ? formatPrice(selectedOrder.shipping_fee_amount) : 'Miễn phí' }}
                  </span>
                </div>
                <div v-if="(selectedOrder.discount_amount || 0) > 0" class="flex justify-between items-center text-emerald-700 font-medium">
                  <span>Mã giảm giá / Ưu đãi</span>
                  <span>- {{ formatPrice(selectedOrder.discount_amount) }}</span>
                </div>
                <div class="flex justify-between items-center text-on-surface-variant">
                  <span>Phương thức thanh toán</span>
                  <span class="text-on-surface font-medium">
                    {{ selectedOrder.payment_method === 'vnpay' ? 'VNPAY' : (selectedOrder.payment_method === 'online' ? 'Thanh toán trực tuyến' : 'Thanh toán khi nhận hàng (COD)') }}
                  </span>
                </div>
                <div class="pt-md mt-sm border-t border-outline-variant/30 flex justify-between items-center">
                  <span class="font-headline-md text-[18px] text-on-surface">Tổng cộng thanh toán</span>
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
                <div v-if="selectedOrder.phone" class="flex items-center justify-between text-on-surface-variant">
                  <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[18px]">call</span>
                    <a :href="'tel:' + selectedOrder.phone" class="hover:text-primary transition-colors">{{ selectedOrder.phone }}</a>
                  </div>
                  <button type="button" class="text-xs text-primary hover:underline cursor-pointer border-none bg-transparent flex items-center gap-0.5" @click="copyToClipboard(selectedOrder.phone, 'Số điện thoại')">
                    <span class="material-symbols-outlined text-xs">content_copy</span>
                    Sao chép
                  </button>
                </div>
                <div v-if="selectedOrder.shipping_address" class="flex items-start justify-between text-on-surface-variant pt-xs border-t border-outline-variant/20">
                  <div class="flex items-start gap-sm">
                    <span class="material-symbols-outlined text-[18px] mt-0.5">location_on</span>
                    <span>{{ selectedOrder.shipping_address }}</span>
                  </div>
                  <button type="button" class="text-xs text-primary hover:underline cursor-pointer border-none bg-transparent flex items-center gap-0.5 whitespace-nowrap ml-2" @click="copyToClipboard(selectedOrder.shipping_address, 'Địa chỉ giao hàng')">
                    <span class="material-symbols-outlined text-xs">content_copy</span>
                    Sao chép
                  </button>
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

        <!-- 3. INVOICE PREVIEW DIALOG -->
        <Dialog
          v-model:visible="invoiceModalVisible"
          modal
          header="Xem Trước Hóa Đơn Bán Hàng"
          class="!max-w-3xl !w-[95vw] !rounded-2xl"
        >
          <div id="printable-invoice" class="p-6 bg-white space-y-6 text-slate-800 text-xs">
            <!-- Header Invoice -->
            <div class="flex items-start justify-between border-b pb-4 border-slate-200">
              <div>
                <h2 class="text-2xl font-black text-rose-700 uppercase tracking-tight">KomiBook</h2>
                <p class="text-[11px] text-slate-500 font-medium mt-0.5">Hệ thống Phát hành & Phân phối Sách Trực tuyến</p>
                <p class="text-xs text-slate-800 font-bold mt-2">Gian hàng: {{ selectedOrder.invoice?.seller?.shop_name || 'Gian hàng KomiBook' }}</p>
              </div>

              <div class="text-right">
                <h3 class="text-lg font-black text-slate-900 uppercase">HÓA ĐƠN BÁN HÀNG</h3>
                <p class="text-xs font-mono font-bold text-rose-700 mt-1">Mã đơn: {{ selectedOrder.order_code }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Ngày lập: {{ formatDate(selectedOrder.created_at) }}</p>
              </div>
            </div>

            <!-- Buyer & Seller Info Grid -->
            <div class="grid grid-cols-2 gap-4 p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs">
              <div>
                <h4 class="font-bold text-slate-900 uppercase tracking-wider mb-1.5">Thông tin khách hàng</h4>
                <p><strong class="text-slate-900">Họ tên:</strong> {{ selectedOrder.user?.name || 'Khách vãng lai' }}</p>
                <p><strong class="text-slate-900">Số điện thoại:</strong> {{ selectedOrder.phone || '—' }}</p>
                <p class="mt-1 leading-relaxed"><strong class="text-slate-900">Địa chỉ giao:</strong> {{ selectedOrder.shipping_address || '—' }}</p>
              </div>

              <div>
                <h4 class="font-bold text-slate-900 uppercase tracking-wider mb-1.5">Thông tin thanh toán</h4>
                <p><strong class="text-slate-900">Phương thức:</strong> {{ selectedOrder.payment_method === 'vnpay' ? 'VNPAY' : 'COD (Thanh toán khi nhận hàng)' }}</p>
                <p><strong class="text-slate-900">Trạng thái TT:</strong> {{ selectedOrder.payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}</p>
                <p><strong class="text-slate-900">Trạng thái đơn:</strong> {{ getStatus(selectedOrder.status).label }}</p>
              </div>
            </div>

            <!-- Items Table -->
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-100 text-slate-700 uppercase font-bold border-b border-slate-200">
                  <tr>
                    <th class="p-2.5 w-10 text-center">STT</th>
                    <th class="p-2.5">Tên Sách</th>
                    <th class="p-2.5 text-center">SL</th>
                    <th class="p-2.5 text-right">Đơn giá</th>
                    <th class="p-2.5 text-right">Thành tiền</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(item, idx) in selectedOrder.items" :key="item.id">
                    <td class="p-2.5 text-center text-slate-500">{{ idx + 1 }}</td>
                    <td class="p-2.5 font-bold text-slate-900">
                      {{ item.book?.title || item.title || `Sách #${item.book_id}` }}
                    </td>
                    <td class="p-2.5 text-center font-semibold">{{ item.quantity }}</td>
                    <td class="p-2.5 text-right">{{ formatPrice(item.price) }}</td>
                    <td class="p-2.5 text-right font-bold text-slate-900">{{ formatPrice(item.price * item.quantity) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Financial Breakdown Footer -->
            <div class="flex justify-end pt-2">
              <div class="w-72 space-y-2 text-xs">
                <div class="flex justify-between text-slate-600">
                  <span>Tạm tính (Tiền hàng):</span>
                  <span class="font-semibold text-slate-900">{{ formatPrice(selectedOrder.subtotal_amount) }}</span>
                </div>

                <div class="flex justify-between text-slate-600">
                  <span>Phí vận chuyển (Phí ship):</span>
                  <span class="font-semibold text-slate-900">{{ selectedOrder.shipping_fee_amount > 0 ? formatPrice(selectedOrder.shipping_fee_amount) : 'Miễn phí' }}</span>
                </div>

                <div v-if="(selectedOrder.discount_amount || 0) > 0" class="flex justify-between text-emerald-700 font-medium">
                  <span>Mã giảm giá / Ưu đãi:</span>
                  <span>- {{ formatPrice(selectedOrder.discount_amount) }}</span>
                </div>

                <div class="flex justify-between border-t border-slate-200 pt-2 text-xs font-bold text-slate-900">
                  <span>Tổng cộng thanh toán:</span>
                  <span class="text-rose-700 text-sm font-black">{{ formatPrice(selectedOrder.total_amount) }}</span>
                </div>
              </div>
            </div>

            <!-- Printable Note -->
            <div class="text-center pt-4 border-t border-dashed border-slate-200 text-[11px] text-slate-500 italic">
              Cảm ơn quý khách đã mua sắm tại KomiBook!
            </div>
          </div>

          <template #footer>
            <div class="flex justify-end gap-2 pt-2">
              <Button label="Đóng" severity="secondary" text @click="invoiceModalVisible = false" class="!rounded-lg" />
              <Button label="In hóa đơn" icon="pi pi-print" severity="primary" @click="printInvoice" class="!rounded-lg" />
            </div>
          </template>
        </Dialog>
      </template>
    </div>
  </div>
</template>

<style>
@media print {
  /* 1. Hide everything on screen using visibility */
  body * {
    visibility: hidden !important;
  }

  /* 2. Make PrimeVue portal containers and printable invoice visible */
  .p-dialog-mask,
  .p-dialog-mask *,
  .p-dialog,
  .p-dialog *,
  .p-dialog-content,
  .p-dialog-content *,
  #printable-invoice,
  #printable-invoice * {
    visibility: visible !important;
  }

  /* 3. Hide non-invoice dialog elements (header bar, close button, footer buttons) */
  .p-dialog-header,
  .p-dialog-footer {
    display: none !important;
    visibility: hidden !important;
  }

  /* 4. Reset dialog containers to fit printed page */
  body > .p-dialog-mask,
  .p-dialog-mask {
    position: static !important;
    display: block !important;
    width: 100% !important;
    height: auto !important;
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .p-dialog {
    position: static !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    max-height: none !important;
    box-shadow: none !important;
    border: none !important;
    background: transparent !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  .p-dialog-content {
    position: static !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: visible !important;
    background: transparent !important;
  }

  /* 5. Position printable invoice cleanly on the printed paper */
  #printable-invoice {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
    background: white !important;
    color: black !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
  }
}
</style>

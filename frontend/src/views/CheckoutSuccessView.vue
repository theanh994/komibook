<template>
  <main
    class="min-h-screen bg-background flex items-center justify-center py-12 md:py-20 px-gutter font-inter antialiased"
    aria-labelledby="checkout-result-title"
  >
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-40">
       <div class="absolute top-[-10%] right-[-10%] w-[800px] h-[800px] bg-primary/10 blur-[150px] rounded-full animate-pulse"></div>
       <div class="absolute bottom-[-10%] left-[-10%] w-[600px] h-[600px] bg-secondary/10 blur-[120px] rounded-full"></div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center animate-fade-in relative z-10" role="status" aria-live="polite">
       <div class="w-14 h-14 border-4 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-6" aria-hidden="true"></div>
       <p class="text-sm font-bold text-on-surface-variant">Đang xác nhận thông tin đơn hàng...</p>
    </div>

    <!-- Invalid or Missing Order ID -->
    <div v-else-if="invalidId" class="max-w-[600px] w-full relative z-10">
      <div class="bg-surface-container-lowest rounded-3xl p-6 md:p-10 border border-outline-variant/20 shadow-xl text-center space-y-6">
        <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center text-amber-700 mx-auto" aria-hidden="true">
           <span class="material-symbols-outlined text-[48px]">warning</span>
        </div>
        <h1 id="checkout-result-title" class="text-2xl md:text-3xl font-bold text-on-surface">Thiếu thông tin mã đơn hàng</h1>
        <p class="text-on-surface-variant text-base leading-relaxed max-w-md mx-auto">Không thể hiển thị kết quả đặt hàng do thiếu tham số mã đơn hàng.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center pt-2">
           <button @click="router.push('/orders')" class="min-h-11 px-8 py-3 rounded-xl bg-primary text-on-primary font-bold text-sm border-none cursor-pointer">
             Danh sách đơn hàng
           </button>
           <button @click="router.push('/')" class="min-h-11 px-8 py-3 rounded-xl border border-outline-variant/30 bg-surface-container-low text-on-surface font-bold text-sm cursor-pointer">
             Về trang chủ
           </button>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="max-w-[600px] w-full relative z-10">
      <div class="bg-surface-container-lowest rounded-3xl p-6 md:p-10 border border-outline-variant/20 shadow-xl text-center space-y-6" role="alert">
        <div class="w-20 h-20 bg-rose-50 rounded-full flex items-center justify-center text-rose-700 mx-auto" aria-hidden="true">
           <span class="material-symbols-outlined text-[48px]">error</span>
        </div>
        <h1 id="checkout-result-title" class="text-2xl md:text-3xl font-bold text-on-surface">Không thể tải thông tin đơn hàng</h1>
        <p class="text-on-surface-variant text-base leading-relaxed max-w-md mx-auto">{{ error }}</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center pt-2">
           <button @click="fetchOrderDetails" class="min-h-11 px-8 py-3 rounded-xl bg-rose-700 text-white font-bold text-sm border-none cursor-pointer">
             Thử lại
           </button>
           <button @click="router.push('/orders')" class="min-h-11 px-8 py-3 rounded-xl border border-outline-variant/30 bg-surface-container-low text-on-surface font-bold text-sm cursor-pointer">
             Xem đơn hàng của tôi
           </button>
        </div>
      </div>
    </div>

    <!-- Success / Confirmed Order Content -->
    <div v-else-if="order" class="max-w-[800px] w-full relative z-10">
      <div class="bg-surface-container-lowest rounded-3xl p-6 md:p-12 border border-outline-variant/20 shadow-xl overflow-hidden relative text-center">
        <!-- Success/Info Icon Animation -->
        <div class="relative w-24 h-24 md:w-28 md:h-28 mx-auto mb-8 animate-success-pop" aria-hidden="true">
           <div class="absolute inset-0 bg-primary/10 rounded-3xl rotate-6 scale-105"></div>
           <div class="absolute inset-0 bg-primary rounded-2xl flex items-center justify-center shadow-lg shadow-primary/30">
              <span class="material-symbols-outlined text-white text-6xl">
                {{ isCancelledOrRefunded ? 'cancel' : (isConfirmedPaid ? 'check_circle' : 'receipt_long') }}
              </span>
           </div>
        </div>

        <h1 id="checkout-result-title" class="text-3xl md:text-5xl font-bold text-on-surface tracking-tight leading-tight mb-5">
           <template v-if="isCancelledOrRefunded">
             Đơn hàng <span class="text-primary italic">không còn hiệu lực</span>
           </template>
           <template v-else-if="isConfirmedPaid">
             Đặt hàng <span class="text-primary italic">thành công!</span>
           </template>
           <template v-else>
             Đơn hàng <span class="text-primary italic">đã ghi nhận</span>
           </template>
        </h1>
        <p class="text-on-surface-variant max-w-[36rem] mx-auto text-base md:text-lg leading-relaxed font-medium mb-10">
          <template v-if="isCancelledOrRefunded">
            Đơn hàng đã bị hủy hoặc hoàn tiền. Vui lòng xem trạng thái chi tiết trong danh sách đơn hàng.
          </template>
          <template v-else-if="isConfirmedPaid">
            Cảm ơn bạn đã tin tưởng KomiBook. Đơn hàng của bạn đã hoàn tất thanh toán và đang được xử lý.
          </template>
          <template v-else>
            Đơn hàng của bạn đang được hệ thống tiếp nhận và cập nhật trạng thái xử lý.
          </template>
        </p>

        <!-- Order Detail Summary Card -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 mb-10 text-left">
           <div class="p-6 bg-surface-container-low rounded-2xl border border-outline-variant/10">
              <p class="text-xs font-bold text-on-surface-variant uppercase tracking-wide mb-2">Mã đơn hàng</p>
              <h3 class="text-2xl font-bold text-on-surface select-all">#{{ order.order_code || order.id }}</h3>
              <p class="text-sm text-on-surface-variant font-medium mt-2">{{ order.items?.length || 0 }} sản phẩm trong đơn</p>
           </div>
           <div class="p-6 bg-surface-container-low rounded-2xl border border-outline-variant/10">
              <p class="text-xs font-bold text-on-surface-variant uppercase tracking-wide mb-2">Tổng thanh toán</p>
              <h3 class="text-2xl font-bold text-primary">{{ formatCurrency(order.grand_total || order.total_amount) }}</h3>
              <p class="text-sm text-on-surface-variant font-medium mt-2">
                Trạng thái thanh toán: <strong class="capitalize">{{ getPaymentStatusLabel(order) }}</strong>
              </p>
           </div>
        </div>

        <!-- Action Buttons (Smart Routing) -->
        <div class="flex flex-col md:flex-row gap-4 items-center justify-center">
           <!-- Case: Order has Ebook -->
           <button 
             v-if="hasReadableEbook"
             @click="goToReader"
             class="min-h-11 w-full md:w-auto px-8 py-3 rounded-xl bg-primary text-on-primary font-bold text-sm shadow-lg shadow-primary/30 border-none cursor-pointer flex items-center justify-center gap-3 transition-colors hover:bg-primary/90"
           >
              <span class="material-symbols-outlined text-[20px]">auto_stories</span>
              Đọc sách ngay
           </button>
           
           <!-- Case: Order has Physical Book -->
           <button 
             v-if="hasPhysicalBook"
             @click="goToTracking"
             class="min-h-11 w-full md:w-auto px-8 py-3 rounded-xl bg-secondary text-on-secondary font-bold text-sm shadow-lg shadow-secondary/30 border-none cursor-pointer flex items-center justify-center gap-3 transition-colors hover:bg-secondary/90"
           >
              <span class="material-symbols-outlined text-[20px]">local_shipping</span>
              Theo dõi vận chuyển
           </button>

           <button 
             @click="router.push('/')"
             class="min-h-11 w-full md:w-auto px-8 py-3 rounded-xl border border-outline-variant/30 bg-surface-container-low text-on-surface font-bold text-sm hover:bg-surface-container-high transition-colors flex items-center justify-center gap-3 cursor-pointer"
           >
              <span class="material-symbols-outlined text-[20px]">home</span>
              Về trang chủ
           </button>
        </div>

        <!-- Footer Note -->
        <p class="mt-10 text-sm font-medium text-on-surface-variant">Bạn có thể theo dõi chi tiết đơn hàng bất kỳ lúc nào trong Tủ sách hoặc Đơn hàng của tôi.</p>
      </div>
    </div>
  </main>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()
const router = useRouter()
const order = ref(null)
const loading = ref(true)
const error = ref(null)
const invalidId = ref(false)

const fetchOrderDetails = async () => {
  const orderId = route.query.order_id
  if (!orderId || !/^[1-9]\d*$/.test(String(orderId))) {
    order.value = null
    invalidId.value = true
    loading.value = false
    return
  }

  loading.value = true
  error.value = null
  invalidId.value = false

  try {
    const res = await apiClient.get(`/api/my-orders/${orderId}`)
    if (res.data?.status === 'success' && res.data?.data) {
      order.value = res.data.data
    } else {
      order.value = null
      error.value = res.data?.message || 'Không tìm thấy thông tin đơn hàng.'
    }
  } catch (err) {
    console.error('Error fetching order detail:', err)
    order.value = null
    error.value = err.response?.data?.message || 'Không thể tải thông tin chi tiết đơn hàng.'
  } finally {
    loading.value = false
  }
}

const isConfirmedPaid = computed(() => {
  if (!order.value || order.value.payment_status !== 'paid') return false
  return !['cancelled', 'refunded'].includes(order.value.status)
})

const isCancelledOrRefunded = computed(() => {
  return order.value?.status === 'cancelled' || order.value?.payment_status === 'refunded'
})

const getPaymentStatusLabel = (ord) => {
  if (ord.payment_status === 'paid') return 'Đã thanh toán'
  if (ord.payment_status === 'refunded') return 'Đã hoàn tiền'
  if (ord.payment_status === 'failed') return 'Thanh toán thất bại'
  if (ord.payment_status === 'pending') return 'Đang chờ thanh toán'
  return 'Chưa thanh toán / Đang xử lý'
}

const hasReadableEbook = computed(() => {
  return isConfirmedPaid.value && !isCancelledOrRefunded.value
    && (order.value?.items?.some(item => item.book?.type === 'ebook') || false)
})

const hasPhysicalBook = computed(() => {
  return order.value?.items?.some(item => item.book?.type === 'paper' || item.book?.type === 'book') || false
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)
}

const goToReader = () => {
  const ebookItem = order.value?.items?.find(item => item.book?.type === 'ebook')
  if (ebookItem) {
    router.push({
      name: 'ebook-reader',
      params: { 
        orderId: order.value.id,
        bookId: ebookItem.book.id
      }
    })
  }
}

const goToTracking = () => {
  if (order.value?.id) {
    router.push({
      name: 'order-tracking',
      params: { orderId: order.value.id }
    })
  }
}

onMounted(() => {
  fetchOrderDetails()
})
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes success-pop {
  0% { transform: scale(0.5) rotate(-20deg); opacity: 0; }
  70% { transform: scale(1.1) rotate(5deg); opacity: 1; }
  100% { transform: scale(1) rotate(0deg); opacity: 1; }
}

.animate-fade-in {
  animation: fade-in 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-success-pop {
  animation: success-pop 1s cubic-bezier(0.17, 0.67, 0.83, 0.67) forwards;
}

@media (prefers-reduced-motion: reduce) {
  .animate-fade-in,
  .animate-success-pop,
  .animate-spin {
    animation: none !important;
  }
}
</style>

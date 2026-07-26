<template>
  <div class="min-h-screen bg-background flex items-center justify-center py-20 px-gutter font-inter antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-40">
       <div class="absolute top-[-10%] right-[-10%] w-[800px] h-[800px] bg-primary/10 blur-[150px] rounded-full animate-pulse"></div>
       <div class="absolute bottom-[-10%] left-[-10%] w-[600px] h-[600px] bg-secondary/10 blur-[120px] rounded-full"></div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center animate-fade-in relative z-10">
       <div class="w-16 h-16 border-8 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-6"></div>
       <p class="text-[11px] font-bold text-outline uppercase tracking-[0.3em]">Đang xác nhận thông tin đơn hàng...</p>
    </div>

    <!-- Invalid or Missing Order ID -->
    <div v-else-if="invalidId" class="max-w-[600px] w-full relative z-10">
      <div class="bg-surface-container-lowest rounded-[48px] p-8 md:p-12 border border-outline-variant/10 shadow-2xl text-center space-y-6">
        <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center text-amber-500 mx-auto">
           <span class="material-symbols-outlined text-[48px]">warning</span>
        </div>
        <h1 class="text-2xl font-bold text-on-surface">Thiếu thông tin mã đơn hàng</h1>
        <p class="text-on-surface-variant text-sm max-w-md mx-auto">Không thể hiển thị kết quả đặt hàng do thiếu tham số mã đơn hàng.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center pt-2">
           <button @click="router.push('/orders')" class="px-8 py-4 rounded-2xl bg-primary text-on-primary font-bold text-xs uppercase tracking-widest border-none cursor-pointer">
             Danh sách đơn hàng
           </button>
           <button @click="router.push('/')" class="px-8 py-4 rounded-2xl border border-outline-variant/30 bg-surface-container-low text-on-surface font-bold text-xs uppercase tracking-widest cursor-pointer">
             Về trang chủ
           </button>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="max-w-[600px] w-full relative z-10">
      <div class="bg-surface-container-lowest rounded-[48px] p-8 md:p-12 border border-outline-variant/10 shadow-2xl text-center space-y-6">
        <div class="w-20 h-20 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 mx-auto">
           <span class="material-symbols-outlined text-[48px]">error</span>
        </div>
        <h1 class="text-2xl font-bold text-on-surface">Không thể tải thông tin đơn hàng</h1>
        <p class="text-on-surface-variant text-sm max-w-md mx-auto">{{ error }}</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center pt-2">
           <button @click="fetchOrderDetails" class="px-8 py-4 rounded-2xl bg-rose-600 text-white font-bold text-xs uppercase tracking-widest border-none cursor-pointer">
             Thử lại
           </button>
           <button @click="router.push('/orders')" class="px-8 py-4 rounded-2xl border border-outline-variant/30 bg-surface-container-low text-on-surface font-bold text-xs uppercase tracking-widest cursor-pointer">
             Xem đơn hàng của tôi
           </button>
        </div>
      </div>
    </div>

    <!-- Success / Confirmed Order Content -->
    <div v-else-if="order" class="max-w-[800px] w-full relative z-10">
      <div class="bg-surface-container-lowest rounded-[48px] p-8 md:p-16 border border-outline-variant/10 shadow-[0_32px_80px_rgba(0,0,0,0.08)] overflow-hidden relative text-center">
        <!-- Success/Info Icon Animation -->
        <div class="relative w-32 h-32 mx-auto mb-12 animate-success-pop">
           <div class="absolute inset-0 bg-primary/10 rounded-[40px] rotate-12 scale-110"></div>
           <div class="absolute inset-0 bg-primary rounded-[32px] flex items-center justify-center shadow-2xl shadow-primary/40">
              <span class="material-symbols-outlined text-white text-6xl">
                {{ isCancelledOrRefunded ? 'cancel' : (isConfirmedPaid ? 'check_circle' : 'receipt_long') }}
              </span>
           </div>
        </div>

        <h1 class="text-4xl md:text-6xl font-bold text-on-surface tracking-tighter leading-none mb-6">
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
        <p class="text-on-surface-variant max-w-[32rem] mx-auto text-lg md:text-xl leading-relaxed font-medium opacity-70 mb-12">
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-16 text-left">
           <div class="p-8 bg-surface-container-low rounded-[32px] border border-outline-variant/5">
              <p class="text-[10px] font-bold text-outline uppercase tracking-widest mb-2">Mã đơn hàng</p>
              <h3 class="text-2xl font-bold text-on-surface select-all">#{{ order.order_code || order.id }}</h3>
              <p class="text-xs text-on-surface-variant font-medium mt-2">{{ order.items?.length || 0 }} sản phẩm trong đơn</p>
           </div>
           <div class="p-8 bg-surface-container-low rounded-[32px] border border-outline-variant/5">
              <p class="text-[10px] font-bold text-outline uppercase tracking-widest mb-2">Tổng thanh toán</p>
              <h3 class="text-2xl font-bold text-primary">{{ formatCurrency(order.grand_total || order.total_amount) }}</h3>
              <p class="text-xs text-on-surface-variant font-medium mt-2">
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
             class="w-full md:w-auto px-10 py-5 rounded-[24px] bg-primary text-on-primary font-bold text-xs uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-2xl shadow-primary/40 border-none cursor-pointer flex items-center justify-center gap-3"
           >
              <span class="material-symbols-outlined text-[20px]">auto_stories</span>
              Đọc sách ngay
           </button>
           
           <!-- Case: Order has Physical Book -->
           <button 
             v-if="hasPhysicalBook"
             @click="goToTracking"
             class="w-full md:w-auto px-10 py-5 rounded-[24px] bg-secondary text-on-secondary font-bold text-xs uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-2xl shadow-secondary/40 border-none cursor-pointer flex items-center justify-center gap-3"
           >
              <span class="material-symbols-outlined text-[20px]">local_shipping</span>
              Theo dõi vận chuyển
           </button>

           <button 
             @click="router.push('/')"
             class="w-full md:w-auto px-10 py-5 rounded-[24px] border border-outline-variant/30 bg-surface-container-low text-on-surface font-bold text-xs uppercase tracking-[0.2em] hover:bg-surface-container-high transition-all flex items-center justify-center gap-3 cursor-pointer"
           >
              <span class="material-symbols-outlined text-[20px]">home</span>
              Về trang chủ
           </button>
        </div>

        <!-- Footer Note -->
        <p class="mt-16 text-[10px] font-bold text-outline uppercase tracking-[0.3em] opacity-40">Bạn có thể theo dõi chi tiết đơn hàng bất kỳ lúc nào trong Tủ sách hoặc Đơn hàng của tôi.</p>
      </div>
    </div>
  </div>
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
</style>

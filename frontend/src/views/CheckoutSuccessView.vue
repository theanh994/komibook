<template>
  <div class="min-h-screen bg-background flex items-center justify-center py-20 px-gutter font-outfit antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-40">
       <div class="absolute top-[-10%] right-[-10%] w-[800px] h-[800px] bg-primary/10 blur-[150px] rounded-full animate-pulse"></div>
       <div class="absolute bottom-[-10%] left-[-10%] w-[600px] h-[600px] bg-secondary/10 blur-[120px] rounded-full"></div>
    </div>

    <div v-if="loading" class="text-center animate-fade-in">
       <div class="w-16 h-16 border-8 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-6"></div>
       <p class="text-[11px] font-black text-outline uppercase tracking-[0.3em]">Đang xác nhận đơn hàng...</p>
    </div>

    <div v-else-if="order" class="max-w-[800px] w-full relative z-10">
      <div class="bg-surface-container-lowest rounded-[48px] p-8 md:p-16 border border-outline-variant/10 shadow-[0_32px_80px_rgba(0,0,0,0.08)] overflow-hidden relative text-center">
        <!-- Success Animation -->
        <div class="relative w-32 h-32 mx-auto mb-12 animate-success-pop">
           <div class="absolute inset-0 bg-primary/10 rounded-[40px] rotate-12 scale-110"></div>
           <div class="absolute inset-0 bg-primary rounded-[32px] flex items-center justify-center shadow-2xl shadow-primary/40">
              <span class="material-symbols-outlined text-white text-6xl">check_circle</span>
           </div>
           <!-- Confetti-like bits -->
           <div class="absolute -top-4 -right-4 w-6 h-6 bg-secondary rounded-full animate-ping"></div>
           <div class="absolute -bottom-2 -left-2 w-4 h-4 bg-primary-fixed rounded-full animate-bounce"></div>
        </div>

        <h1 class="text-4xl md:text-6xl font-black text-on-surface tracking-tighter leading-none mb-6">
           Đặt hàng <span class="text-primary italic">thành công!</span>
        </h1>
        <p class="text-on-surface-variant max-w-[32rem] mx-auto text-lg md:text-xl leading-relaxed font-medium opacity-70 mb-12">
          Cảm ơn bạn đã tin tưởng KomiBook. Đơn hàng của bạn đã được ghi nhận và đang được xử lý thần tốc.
        </p>

        <!-- Order Detail Summary Card -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-16 text-left">
           <div class="p-8 bg-surface-container-low rounded-[32px] border border-outline-variant/5">
              <p class="text-[10px] font-black text-outline uppercase tracking-widest mb-2">Mã đơn hàng</p>
              <h3 class="text-2xl font-black text-on-surface select-all">#{{ order.order_code || order.id }}</h3>
              <p class="text-xs text-on-surface-variant font-medium mt-2">{{ order.items?.length }} sản phẩm đã chọn</p>
           </div>
           <div class="p-8 bg-surface-container-low rounded-[32px] border border-outline-variant/5">
              <p class="text-[10px] font-black text-outline uppercase tracking-widest mb-2">Tổng thanh toán</p>
              <h3 class="text-2xl font-black text-primary">{{ formatCurrency(order.total_amount) }}</h3>
              <p class="text-xs text-on-surface-variant font-medium mt-2">{{ order.payment_method === 'vnpay' ? 'Đã thanh toán qua VNPAY' : 'Thanh toán khi nhận hàng' }}</p>
           </div>
        </div>

        <!-- Action Buttons (Smart Routing) -->
        <div class="flex flex-col md:flex-row gap-4 items-center justify-center">
           <!-- Case: Order has Ebook -->
           <button 
             v-if="hasEbook"
             @click="goToReader"
             class="w-full md:w-auto px-10 py-5 rounded-[24px] bg-primary text-on-primary font-black text-xs uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-2xl shadow-primary/40 border-none cursor-pointer flex items-center justify-center gap-3"
           >
              <span class="material-symbols-outlined text-[20px]">auto_stories</span>
              Đọc sách ngay
           </button>
           
           <!-- Case: Order has Physical Book -->
           <button 
             v-if="hasPhysicalBook"
             @click="goToTracking"
             class="w-full md:w-auto px-10 py-5 rounded-[24px] bg-secondary text-on-secondary font-black text-xs uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-2xl shadow-secondary/40 border-none cursor-pointer flex items-center justify-center gap-3"
           >
              <span class="material-symbols-outlined text-[20px]">local_shipping</span>
              Theo dõi vận chuyển
           </button>

           <button 
             @click="router.push('/')"
             class="w-full md:w-auto px-10 py-5 rounded-[24px] border border-outline-variant/30 bg-surface-container-low text-on-surface font-black text-xs uppercase tracking-[0.2em] hover:bg-surface-container-high transition-all flex items-center justify-center gap-3 cursor-pointer"
           >
              <span class="material-symbols-outlined text-[20px]">home</span>
              Về trang chủ
           </button>
        </div>

        <!-- Footer Note -->
        <p class="mt-16 text-[10px] font-black text-outline uppercase tracking-[0.3em] opacity-40">Hệ thống đã gửi hóa đơn chi tiết vào email của bạn.</p>
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

const fetchOrderDetails = async () => {
  const orderId = route.query.order_id
  if (!orderId) {
    router.push('/')
    return
  }

  try {
    const res = await apiClient.get('/api/my-orders')
    const orders = res.data.data || res.data || []
    order.value = orders.find(o => o.id == orderId)
    
    if (!order.value) {
      // Fallback: Nếu không tìm thấy trong list (vừa mới tạo, có thể delay)
      // Thử lại sau 1s hoặc show error
      console.warn('Order not found in history, might be delay')
    }
  } catch (error) {
    console.error('Error fetching order:', error)
  } finally {
    loading.value = false
  }
}

const hasEbook = computed(() => {
  return order.value?.items?.some(item => item.book?.type === 'ebook') || false
})

const hasPhysicalBook = computed(() => {
  return order.value?.items?.some(item => item.book?.type === 'paper') || false
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)
}

const goToReader = () => {
  const ebookItem = order.value.items.find(item => item.book?.type === 'ebook')
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
  router.push({
    name: 'order-tracking',
    params: { orderId: order.value.id }
  })
}

onMounted(() => {
  fetchOrderDetails()
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100;400;900&display=swap');

.font-outfit {
  font-family: 'Outfit', sans-serif;
}

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

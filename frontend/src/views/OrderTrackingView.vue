<template>
  <div class="min-h-screen bg-background py-xl px-gutter font-inter antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-30">
       <div class="absolute top-[-5%] right-[-5%] w-[500px] h-[500px] bg-primary/10 blur-[120px] rounded-full"></div>
       <div class="absolute bottom-[-5%] left-[-5%] w-[400px] h-[400px] bg-secondary/10 blur-[100px] rounded-full"></div>
    </div>

    <div class="max-w-[1200px] mx-auto relative z-10">
      
      <!-- Premium Header Section -->
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end mb-xxl gap-10 animate-fade-in">
        <div class="space-y-4">
          <nav class="flex items-center gap-3 text-[10px] font-bold uppercase tracking-[0.3em] text-outline mb-4">
            <router-link to="/orders" class="hover:text-primary transition-colors">Đơn hàng</router-link>
            <span class="material-symbols-outlined text-[14px] opacity-40">chevron_right</span>
            <span class="text-on-surface">Theo dõi vận chuyển</span>
          </nav>
          
          <div class="flex items-center gap-4">
             <div class="w-2 h-10 bg-primary rounded-full"></div>
             <h1 class="text-4xl md:text-5xl font-bold text-on-surface tracking-tighter leading-none">Hành trình đơn hàng</h1>
          </div>
          
          <div v-if="order" class="flex flex-wrap items-center gap-4 pt-2">
             <div class="px-5 py-2.5 bg-surface-container-high rounded-2xl border border-outline-variant/10 shadow-sm">
                <span class="text-xs font-bold text-on-surface select-all">#{{ order.order_code || order.id }}</span>
             </div>
            <span :class="['px-5 py-2 rounded-2xl text-[10px] font-bold uppercase tracking-widest shadow-lg flex items-center gap-2 border border-white/10', getStatusStyle(order)]">
               <span class="w-2 h-2 rounded-full bg-current animate-pulse"></span>
              {{ getStatusText(order) }}
             </span>
          </div>
        </div>
        
        <div class="flex gap-4 w-full lg:w-auto">
          <button @click="$router.push('/orders')" class="flex-1 lg:flex-none px-8 py-4 rounded-2xl border border-outline-variant/20 bg-surface-container-lowest text-on-surface font-bold text-xs uppercase tracking-widest hover:bg-surface-container-low transition-all flex items-center justify-center gap-3 cursor-pointer shadow-sm">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            Danh sách đơn
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="py-32 flex flex-col items-center gap-8 animate-fade-in">
        <div class="relative w-20 h-20">
          <div class="absolute inset-0 border-8 border-primary/10 rounded-full"></div>
          <div class="absolute inset-0 border-8 border-t-primary rounded-full animate-spin"></div>
        </div>
        <p class="text-[11px] font-bold text-outline uppercase tracking-[0.3em] animate-pulse">Đang tải thông tin vận chuyển...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="py-24 flex flex-col items-center text-center gap-6 bg-surface-container-lowest rounded-[48px] border border-outline-variant/10 shadow-2xl animate-fade-in p-8">
        <div class="w-20 h-20 bg-rose-50 rounded-full flex items-center justify-center text-rose-500">
           <span class="material-symbols-outlined text-[48px]">error</span>
        </div>
        <div class="space-y-2">
          <h2 class="text-2xl font-bold text-on-surface">Không thể tải thông tin hành trình</h2>
          <p class="text-on-surface-variant text-sm max-w-md mx-auto">{{ error }}</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-4 justify-center pt-2">
          <button @click="fetchOrder" class="px-8 py-4 rounded-2xl bg-rose-600 text-white font-bold text-xs uppercase tracking-widest border-none cursor-pointer">
            Thử lại
          </button>
          <button @click="$router.push('/orders')" class="px-8 py-4 rounded-2xl border border-outline-variant/30 bg-surface-container-low text-on-surface font-bold text-xs uppercase tracking-widest cursor-pointer">
            Về danh sách đơn hàng
          </button>
        </div>
      </div>

      <div v-else-if="order" class="grid grid-cols-1 lg:grid-cols-3 gap-xl animate-fade-in">
        
        <!-- Left Column: Interactive Stepper & History -->
        <div class="lg:col-span-2 space-y-xl">
          
          <!-- Modern Stepper Card -->
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-[48px] p-8 md:p-14 border border-outline-variant/10 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full -mr-32 -mt-32 blur-3xl"></div>
            
            <h2 class="text-2xl font-bold text-on-surface mb-14 tracking-tight">
              Dự kiến giao hàng: <span class="text-primary italic">{{ estimatedDelivery }}</span>
            </h2>

            <div v-if="journeyException" class="mb-10 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm font-semibold text-rose-700">
              {{ journeyException }}
            </div>
            
            <!-- Horizontal Stepper (Desktop) -->
            <div v-if="!journeyException" class="hidden md:flex justify-between items-start relative w-full px-8 pb-10">
              <!-- Background Line -->
              <div class="absolute left-16 right-16 top-7 h-2 bg-surface-container-high rounded-full overflow-hidden">
                <div class="h-full bg-primary transition-all duration-1000 ease-out" :style="{ width: progressWidth }"></div>
              </div>

              <!-- Steps -->
              <div v-for="(step, index) in trackingSteps" :key="index" class="relative z-10 flex flex-col items-center gap-6 w-32 group">
                <div 
                  class="w-14 h-14 rounded-[22px] flex items-center justify-center transition-all duration-700 border-8 border-surface-container-lowest transform group-hover:scale-110"
                  :class="[
                    index <= currentStepIndex ? 'bg-primary text-on-primary shadow-2xl shadow-primary/40' : 'bg-surface-container-high text-outline opacity-60'
                  ]"
                >
                  <span class="material-symbols-outlined text-[24px] fill-1">{{ step.icon }}</span>
                </div>
                <div class="text-center space-y-1">
                   <span 
                     class="block text-[11px] font-bold uppercase tracking-widest leading-tight transition-colors"
                     :class="index <= currentStepIndex ? 'text-on-surface' : 'text-outline opacity-40'"
                   >
                     {{ step.label }}
                   </span>
                   <span v-if="index === currentStepIndex" class="text-[9px] font-bold text-primary animate-pulse">Hiện tại</span>
                </div>
              </div>
            </div>

            <!-- Vertical Stepper (Mobile) -->
            <div v-if="!journeyException" class="md:hidden space-y-12 relative pl-12 pb-6">
               <div class="absolute left-6 top-3 bottom-3 w-1.5 bg-surface-container-high rounded-full overflow-hidden">
                  <div class="w-full bg-primary rounded-full transition-all duration-1000 ease-out" :style="{ height: progressWidth }"></div>
               </div>
               <div v-for="(step, index) in trackingSteps" :key="index" class="relative flex items-center gap-6 group">
                  <div 
                    class="absolute -left-[42px] w-10 h-10 rounded-2xl flex items-center justify-center border-4 border-surface-container-lowest transition-all duration-500 shadow-lg"
                    :class="index <= currentStepIndex ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-outline opacity-40'"
                  >
                    <span class="material-symbols-outlined text-[18px] fill-1">{{ step.icon }}</span>
                  </div>
                  <div class="space-y-1">
                    <span 
                      class="block text-xs font-bold uppercase tracking-[0.2em]"
                      :class="index <= currentStepIndex ? 'text-on-surface' : 'text-outline opacity-40'"
                    >
                      {{ step.label }}
                    </span>
                    <span v-if="index === currentStepIndex" class="text-[9px] font-bold text-primary italic">Trạng thái hiện tại</span>
                  </div>
               </div>
            </div>
          </div>

          <!-- Timeline History Card -->
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-[48px] p-8 md:p-14 border border-outline-variant/10 shadow-2xl">
            <div class="flex items-center justify-between mb-12">
               <h3 class="text-2xl font-bold text-on-surface tracking-tight">Lịch sử đơn hàng</h3>
               <div class="px-4 py-2 bg-primary/5 rounded-xl border border-primary/10">
                  <span class="text-[10px] font-bold text-primary uppercase tracking-widest">Thời gian thực</span>
               </div>
            </div>

            <div class="relative pl-10 space-y-12">
              <!-- Timeline Line -->
              <div class="absolute left-[15px] top-3 bottom-3 w-px bg-outline-variant/20"></div>
              
              <!-- Timeline Events -->
              <div v-for="(event, index) in trackingEvents" :key="index" class="relative animate-slide-up">
                <!-- Dot Decor -->
                <div 
                  class="absolute -left-[45px] top-1.5 w-7 h-7 rounded-full border-4 border-surface-container-lowest flex items-center justify-center transition-all duration-500"
                  :class="index === 0 ? 'bg-secondary scale-125 shadow-xl shadow-secondary/20' : 'bg-outline-variant/40'"
                >
                   <div v-if="index === 0" class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></div>
                </div>

                <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 bg-surface-container-low/20 p-6 rounded-3xl border border-outline-variant/5 hover:bg-surface-container-low/40 transition-all group">
                  <div class="space-y-2">
                    <p class="text-base font-bold text-on-surface group-hover:text-primary transition-colors" :class="{ 'text-primary': index === 0 }">{{ event.status }}</p>
                    <div class="flex items-center gap-2 text-on-surface-variant/60 font-medium text-sm">
                      <span class="material-symbols-outlined text-[18px]">info</span>
                      {{ event.location }}
                    </div>
                  </div>
                  <div class="shrink-0 flex md:flex-col items-center md:items-end gap-3 md:gap-1">
                    <p class="text-[11px] font-bold text-on-surface bg-surface-container-high px-3 py-1 rounded-lg">{{ event.date }}</p>
                    <p class="text-[10px] font-bold text-outline opacity-60">{{ event.time }}</p>
                  </div>
                </div>
              </div>
              <div v-if="trackingEvents.length === 0" class="rounded-3xl border border-outline-variant/10 bg-surface-container-low/30 p-8 text-center text-sm text-on-surface-variant">
                Chưa có lịch sử hành trình từ đơn vị vận chuyển.
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Shipping Details & Summary -->
        <div class="space-y-xl">
          <!-- Delivery Info Card -->
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-[40px] p-8 border border-outline-variant/10 shadow-2xl flex flex-col gap-8">
            <h3 class="text-xl font-bold text-on-surface mb-2 flex items-center gap-3">
              <span class="material-symbols-outlined text-primary text-[28px]">local_shipping</span>
              Vận chuyển
            </h3>
            
            <div class="space-y-8">
              <div class="group">
                <p class="text-[10px] font-bold text-outline uppercase tracking-[0.2em] mb-3">Người nhận</p>
                <div class="flex items-center gap-4">
                   <div class="w-12 h-12 bg-primary-container rounded-2xl flex items-center justify-center text-on-primary-container font-bold text-lg">
                      {{ (order.user?.name || 'C')?.charAt(0) }}
                   </div>
                   <div>
                      <p class="text-sm font-bold text-on-surface">{{ order.user?.name || '—' }}</p>
                      <p class="text-xs text-on-surface-variant font-medium mt-0.5">{{ order.user?.phone || 'Chưa cập nhật' }}</p>
                   </div>
                </div>
              </div>

              <div class="h-px bg-outline-variant/10"></div>

              <div>
                <p class="text-[10px] font-bold text-outline uppercase tracking-[0.2em] mb-3">Địa chỉ giao hàng</p>
                <p class="text-sm font-medium text-on-surface leading-relaxed">{{ order.shipping_address || '—' }}</p>
              </div>

              <div class="h-px bg-outline-variant/10"></div>

              <div class="p-5 bg-surface-container-high/50 rounded-3xl border border-outline-variant/10 relative overflow-hidden group">
                <p class="text-[10px] font-bold text-outline uppercase tracking-[0.2em] mb-3">Đơn vị vận chuyển</p>
                <p class="text-base font-bold text-primary italic">{{ order.shipping_carrier || 'Chưa cập nhật' }}</p>
                <p class="text-[10px] text-on-surface-variant font-bold mt-2 uppercase tracking-widest">
                  Mã vận đơn: {{ order.shipping_tracking_code || '—' }}
                </p>
              </div>
            </div>

            <button
              disabled
              title="Chưa có thông tin liên hệ tài xế cho đơn hàng này."
              class="w-full mt-4 px-8 py-4.5 rounded-2xl bg-outline-variant/30 text-on-surface-variant font-bold text-xs uppercase tracking-[0.2em] border-none cursor-not-allowed opacity-60 flex items-center justify-center gap-3"
            >
              <span class="material-symbols-outlined text-[20px]">call</span>
              Liên hệ tài xế (Chưa hỗ trợ)
            </button>
          </div>

          <!-- Order Summary Mini Card -->
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-[40px] p-8 border border-outline-variant/10 shadow-2xl">
            <h3 class="text-xl font-bold text-on-surface mb-8 tracking-tight">Kiện hàng ({{ order.items?.length || 0 }})</h3>
            <div class="space-y-6 max-h-[400px] overflow-y-auto no-scrollbar pr-2">
              <div v-for="item in order.items" :key="item.id" class="flex gap-5 items-center group cursor-pointer">
                <div class="w-16 h-20 rounded-2xl overflow-hidden shrink-0 shadow-lg border border-outline-variant/10 transform group-hover:scale-105 transition-all duration-500">
                  <img v-if="item.book?.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="item.book.title" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center text-outline/30 bg-surface-container-high">
                    <span class="material-symbols-outlined text-2xl">book</span>
                  </div>
                </div>
                <div class="min-w-0 space-y-1">
                  <p class="text-sm font-bold text-on-surface truncate leading-tight group-hover:text-primary transition-colors">{{ item.book?.title }}</p>
                  <div class="flex items-center gap-3">
                     <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">SL: {{ item.quantity }}</span>
                     <div class="w-1 h-1 rounded-full bg-outline-variant/30"></div>
                     <span class="text-[11px] font-bold text-primary">{{ formatCurrency(item.price) }}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="mt-10 pt-8 border-t border-outline-variant/10 flex justify-between items-end">
               <div>
                  <p class="text-[10px] font-bold text-outline uppercase tracking-widest mb-1">Tổng giá trị</p>
                  <p class="text-2xl font-bold text-on-surface tracking-tighter">{{ formatCurrency(order.grand_total || order.total_amount) }}</p>
               </div>
               <span v-if="order.shipping_fee === 0 || order.is_freeship" class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest px-3 py-1 bg-emerald-50 rounded-lg border border-emerald-100">Freeship</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Footer Support -->
      <div class="mt-20 text-center animate-fade-in">
         <p class="text-[11px] font-bold text-outline uppercase tracking-[0.3em] opacity-40">KomiBook Order Tracking System</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()
const order = ref(null)
const loading = ref(true)
const error = ref(null)

const trackingSteps = [
  { label: 'Đã đặt hàng', icon: 'check_circle' },
  { label: 'Đang xử lý', icon: 'pending' },
  { label: 'Giao cho ĐVVC', icon: 'local_shipping' },
  { label: 'Đang giao', icon: 'route' },
  { label: 'Thành công', icon: 'verified' }
]

const currentStepIndex = computed(() => {
  if (!order.value) return 0
  const sStatus = order.value.shipping_status
  const status = order.value.status
  if (status === 'cancelled' || sStatus === 'failed') return 0
  if (status === 'completed' || sStatus === 'delivered') return 4
  if (sStatus === 'delivering') return 3
  if (sStatus === 'picked_up') return 2
  if (sStatus === 'pending_pickup') return 1
  if (status === 'processing') return 1
  if (status === 'pending') return 0
  return 0
})

const journeyException = computed(() => {
  if (order.value?.status === 'cancelled') return 'Đơn hàng đã bị hủy; hành trình giao hàng không tiếp tục.'
  if (order.value?.shipping_status === 'failed') return 'Việc giao hàng đã thất bại. Vui lòng liên hệ bộ phận hỗ trợ.'
  return null
})

const progressWidth = computed(() => {
  const steps = trackingSteps.length - 1
  return `${(currentStepIndex.value / steps) * 100}%`
})

const estimatedDelivery = computed(() => {
  if (!order.value || !order.value.estimated_delivery_at) {
    return 'Chưa có dự kiến giao hàng'
  }
  const date = new Date(order.value.estimated_delivery_at)
  return new Intl.DateTimeFormat('vi-VN', {
    weekday: 'long', day: '2-digit', month: 'long'
  }).format(date)
})

const trackingEvents = computed(() => [])

const fetchOrder = async () => {
  loading.value = true
  error.value = null
  const orderId = route.params.orderId
  if (!orderId) {
    error.value = 'Mã đơn hàng không hợp lệ.'
    loading.value = false
    return
  }

  try {
    const res = await apiClient.get(`/api/my-orders/${orderId}`)
    if (res.data?.status === 'success' && res.data?.data) {
      order.value = res.data.data
    } else {
      order.value = null
      error.value = res.data?.message || 'Không tìm thấy thông tin đơn hàng.'
    }
  } catch (err) {
    console.error('Lỗi tải thông tin vận chuyển:', err)
    order.value = null
    error.value = err.response?.data?.message || 'Không thể kết nối máy chủ để tải thông tin đơn hàng.'
  } finally {
    loading.value = false
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

const getStatusText = (orderData) => {
  if (orderData.shipping_status === 'failed') return 'Giao hàng thất bại'
  if (orderData.shipping_status === 'delivered') return 'Đã giao hàng'
  if (orderData.shipping_status === 'delivering') return 'Đang giao hàng'
  if (orderData.shipping_status === 'picked_up') return 'Đơn vị vận chuyển đã nhận'
  if (orderData.shipping_status === 'pending_pickup') return 'Chờ đơn vị vận chuyển nhận'
  const status = orderData.status
  const map = { pending: 'Chờ xử lý', processing: 'Đang xử lý', completed: 'Đã hoàn thành', cancelled: 'Đã hủy' }
  return map[status] || status
}

const getStatusStyle = (orderData) => {
  if (orderData.shipping_status === 'failed') return 'bg-rose-500 text-white shadow-rose-500/20'
  if (orderData.shipping_status === 'delivered') return 'bg-emerald-500 text-white shadow-emerald-500/20'
  const status = orderData.status
  const map = {
    pending: 'bg-amber-500 text-white shadow-amber-500/20',
    processing: 'bg-primary text-on-primary shadow-primary/20',
    completed: 'bg-emerald-500 text-white shadow-emerald-500/20',
    cancelled: 'bg-rose-500 text-white shadow-rose-500/20'
  }
  return map[status] || 'bg-outline text-white'
}

onMounted(() => {
  fetchOrder()
})
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slide-up {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-up {
  animation: slide-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.fill-1 {
  font-variation-settings: 'FILL' 1;
}
</style>

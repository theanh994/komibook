<template>
  <div class="min-h-screen bg-background py-xl px-gutter font-outfit antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-30">
       <div class="absolute top-[-5%] right-[-5%] w-[500px] h-[500px] bg-primary/10 blur-[120px] rounded-full"></div>
       <div class="absolute bottom-[-5%] left-[-5%] w-[400px] h-[400px] bg-secondary/10 blur-[100px] rounded-full"></div>
    </div>

    <div class="max-w-[1200px] mx-auto relative z-10">
      
      <!-- Premium Header Section -->
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end mb-xxl gap-10 animate-fade-in">
        <div class="space-y-4">
          <nav class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.3em] text-outline mb-4">
            <router-link to="/orders" class="hover:text-primary transition-colors">Đơn hàng</router-link>
            <span class="material-symbols-outlined text-[14px] opacity-40">chevron_right</span>
            <span class="text-on-surface">Theo dõi vận chuyển</span>
          </nav>
          
          <div class="flex items-center gap-4">
             <div class="w-2 h-10 bg-primary rounded-full"></div>
             <h1 class="text-4xl md:text-5xl font-black text-on-surface tracking-tighter leading-none">Hành trình đơn hàng</h1>
          </div>
          
          <div v-if="order" class="flex flex-wrap items-center gap-4 pt-2">
            <div class="px-5 py-2.5 bg-surface-container-high rounded-2xl border border-outline-variant/10 shadow-sm">
               <span class="text-xs font-black text-on-surface select-all">#{{ order.order_code || order.id }}</span>
            </div>
            <span :class="['px-5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg flex items-center gap-2 border border-white/10', getStatusStyle(order.status)]">
              <span class="w-2 h-2 rounded-full bg-current animate-pulse"></span>
              {{ getStatusText(order.status) }}
            </span>
          </div>
        </div>
        
        <div class="flex gap-4 w-full lg:w-auto">
          <button @click="$router.push('/orders')" class="flex-1 lg:flex-none px-8 py-4 rounded-2xl border border-outline-variant/20 bg-surface-container-lowest text-on-surface font-black text-xs uppercase tracking-widest hover:bg-surface-container-low transition-all flex items-center justify-center gap-3 cursor-pointer shadow-sm">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            Danh sách đơn
          </button>
          <button class="flex-1 lg:flex-none px-8 py-4 rounded-2xl bg-primary text-on-primary font-black text-xs uppercase tracking-widest hover:opacity-90 transition-all flex items-center justify-center gap-3 shadow-2xl shadow-primary/20 border-none cursor-pointer">
            <span class="material-symbols-outlined text-[20px]">support_agent</span>
            Trợ giúp
          </button>
        </div>
      </div>

      <!-- Loading State (MD3) -->
      <div v-if="loading" class="py-32 flex flex-col items-center gap-8 animate-fade-in">
        <div class="relative w-20 h-20">
          <div class="absolute inset-0 border-8 border-primary/10 rounded-full"></div>
          <div class="absolute inset-0 border-8 border-t-primary rounded-full animate-spin"></div>
        </div>
        <p class="text-[11px] font-black text-outline uppercase tracking-[0.3em] animate-pulse">Đang kết nối đơn vị vận chuyển...</p>
      </div>

      <div v-else-if="order" class="grid grid-cols-1 lg:grid-cols-3 gap-xl animate-fade-in delay-100">
        
        <!-- Left Column: Interactive Stepper & History -->
        <div class="lg:col-span-2 space-y-xl">
          
          <!-- Modern Stepper Card -->
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-[48px] p-8 md:p-14 border border-outline-variant/10 shadow-2xl relative overflow-hidden">
            <!-- Decorative gradient -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full -mr-32 -mt-32 blur-3xl"></div>
            
            <h2 class="text-2xl font-black text-on-surface mb-14 tracking-tight">
              Dự kiến giao hàng: <span class="text-primary italic">{{ estimatedDelivery }}</span>
            </h2>
            
            <!-- Horizontal Stepper (Desktop) -->
            <div class="hidden md:flex justify-between items-start relative w-full px-8 pb-10">
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
                     class="block text-[11px] font-black uppercase tracking-widest leading-tight transition-colors"
                     :class="index <= currentStepIndex ? 'text-on-surface' : 'text-outline opacity-40'"
                   >
                     {{ step.label }}
                   </span>
                   <span v-if="index === currentStepIndex" class="text-[9px] font-bold text-primary animate-pulse">Hiện tại</span>
                </div>
              </div>
            </div>

            <!-- Vertical Stepper (Mobile) -->
            <div class="md:hidden space-y-12 relative pl-12 pb-6">
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
                      class="block text-xs font-black uppercase tracking-[0.2em]"
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
               <h3 class="text-2xl font-black text-on-surface tracking-tight">Lịch sử hành trình</h3>
               <div class="px-4 py-2 bg-primary/5 rounded-xl border border-primary/10">
                  <span class="text-[10px] font-black text-primary uppercase tracking-widest">Thời gian thực</span>
               </div>
            </div>

            <div class="relative pl-10 space-y-12">
              <!-- Timeline Line -->
              <div class="absolute left-[15px] top-3 bottom-3 w-px bg-outline-variant/20"></div>
              
              <!-- Timeline Events -->
              <div v-for="(event, index) in trackingEvents" :key="index" class="relative animate-slide-up" :style="{ animationDelay: (index * 100 + 300) + 'ms' }">
                <!-- Dot Decor -->
                <div 
                  class="absolute -left-[45px] top-1.5 w-7 h-7 rounded-full border-4 border-surface-container-lowest flex items-center justify-center transition-all duration-500"
                  :class="index === 0 ? 'bg-secondary scale-125 shadow-xl shadow-secondary/20' : 'bg-outline-variant/40'"
                >
                   <div v-if="index === 0" class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></div>
                </div>

                <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 bg-surface-container-low/20 p-6 rounded-3xl border border-outline-variant/5 hover:bg-surface-container-low/40 transition-all group">
                  <div class="space-y-2">
                    <p class="text-base font-black text-on-surface group-hover:text-primary transition-colors" :class="{ 'text-primary': index === 0 }">{{ event.status }}</p>
                    <div class="flex items-center gap-2 text-on-surface-variant/60 font-medium text-sm">
                      <span class="material-symbols-outlined text-[18px]">location_on</span>
                      {{ event.location }}
                    </div>
                  </div>
                  <div class="shrink-0 flex md:flex-col items-center md:items-end gap-3 md:gap-1">
                    <p class="text-[11px] font-black text-on-surface bg-surface-container-high px-3 py-1 rounded-lg">{{ event.date }}</p>
                    <p class="text-[10px] font-bold text-outline opacity-60">{{ event.time }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Shipping Details & Summary -->
        <div class="space-y-xl">
          <!-- Delivery Info Card -->
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-[40px] p-8 border border-outline-variant/10 shadow-2xl flex flex-col gap-8">
            <h3 class="text-xl font-black text-on-surface mb-2 flex items-center gap-3">
              <span class="material-symbols-outlined text-primary text-[28px]">local_shipping</span>
              Vận chuyển
            </h3>
            
            <div class="space-y-8">
              <div class="group">
                <p class="text-[10px] font-black text-outline uppercase tracking-[0.2em] mb-3">Người nhận</p>
                <div class="flex items-center gap-4">
                   <div class="w-12 h-12 bg-primary-container rounded-2xl flex items-center justify-center text-on-primary-container font-black text-lg">
                      {{ order.user?.name?.charAt(0) || 'K' }}
                   </div>
                   <div>
                      <p class="text-sm font-black text-on-surface">{{ order.user?.name || 'Khách hàng Komibook' }}</p>
                      <p class="text-xs text-on-surface-variant font-medium mt-0.5">{{ order.user?.phone || 'Chưa cập nhật SĐT' }}</p>
                   </div>
                </div>
              </div>

              <div class="h-px bg-outline-variant/10"></div>

              <div>
                <p class="text-[10px] font-black text-outline uppercase tracking-[0.2em] mb-3">Địa chỉ giao hàng</p>
                <p class="text-sm font-medium text-on-surface leading-relaxed">{{ order.shipping_address }}</p>
              </div>

              <div class="h-px bg-outline-variant/10"></div>

              <div class="p-5 bg-surface-container-high/50 rounded-3xl border border-outline-variant/10 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                   <span class="material-symbols-outlined text-6xl">verified</span>
                </div>
                <p class="text-[10px] font-black text-outline uppercase tracking-[0.2em] mb-3">Đơn vị vận chuyển</p>
                <p class="text-base font-black text-primary italic">Komi Logistics Express</p>
                <p class="text-[10px] text-on-surface-variant font-bold mt-2 uppercase tracking-widest">MVĐ: VN-{{ order.id }}-EXP</p>
              </div>
            </div>

            <button class="w-full mt-4 px-8 py-4.5 rounded-2xl bg-on-surface text-surface font-black text-xs uppercase tracking-[0.2em] hover:bg-on-surface/90 transition-all border-none cursor-pointer flex items-center justify-center gap-3 shadow-2xl active:scale-95">
              <span class="material-symbols-outlined text-[20px]">call</span>
              Liên hệ tài xế
            </button>
          </div>

          <!-- Order Summary Mini Card -->
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-[40px] p-8 border border-outline-variant/10 shadow-2xl">
            <h3 class="text-xl font-black text-on-surface mb-8 tracking-tight">Kiện hàng ({{ order.items?.length || 0 }})</h3>
            <div class="space-y-6 max-h-[400px] overflow-y-auto no-scrollbar pr-2">
              <div v-for="item in order.items" :key="item.id" class="flex gap-5 items-center group cursor-pointer">
                <div class="w-16 h-20 rounded-2xl overflow-hidden shrink-0 shadow-lg border border-outline-variant/10 transform group-hover:scale-105 transition-all duration-500">
                  <img v-if="item.book?.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="item.book.title" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center text-outline/30 bg-surface-container-high">
                    <span class="material-symbols-outlined text-2xl">book</span>
                  </div>
                </div>
                <div class="min-w-0 space-y-1">
                  <p class="text-sm font-black text-on-surface truncate leading-tight group-hover:text-primary transition-colors">{{ item.book?.title }}</p>
                  <div class="flex items-center gap-3">
                     <span class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Qty: {{ item.quantity }}</span>
                     <div class="w-1 h-1 rounded-full bg-outline-variant/30"></div>
                     <span class="text-[11px] font-black text-primary">{{ formatCurrency(item.price) }}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="mt-10 pt-8 border-t border-outline-variant/10 flex justify-between items-end">
               <div>
                  <p class="text-[10px] font-black text-outline uppercase tracking-widest mb-1">Tổng giá trị</p>
                  <p class="text-2xl font-black text-on-surface tracking-tighter">{{ formatCurrency(order.total_amount) }}</p>
               </div>
               <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest px-3 py-1 bg-emerald-50 rounded-lg border border-emerald-100">Freeship</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Footer Support -->
      <div class="mt-20 text-center animate-fade-in delay-500">
         <p class="text-[11px] font-black text-outline uppercase tracking-[0.3em] opacity-40">KomiBook Delivery Intelligence System v2.0</p>
      </div>
    </div>

    <!-- PrimeVue Toast for notifications -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import Toast from 'primevue/toast'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const order = ref(null)
const loading = ref(true)

const trackingSteps = [
  { label: 'Đã đặt hàng', icon: 'check_circle' },
  { label: 'Đang xử lý', icon: 'pending' },
  { label: 'Giao cho ĐVVC', icon: 'local_shipping' },
  { label: 'Đang giao', icon: 'route' },
  { label: 'Thành công', icon: 'verified' }
]

const currentStepIndex = computed(() => {
  if (!order.value) return 0
  const status = order.value.status
  if (status === 'pending') return 0
  if (status === 'processing') return 1
  if (status === 'shipping' || status === 'delivering') return 3 // Simplified for visual
  if (status === 'completed') return 4
  return 1 
})

const progressWidth = computed(() => {
  const steps = trackingSteps.length - 1
  return `${(currentStepIndex.value / steps) * 100}%`
})

const estimatedDelivery = computed(() => {
  if (!order.value) return 'Đang tính toán...'
  const date = new Date(order.value.created_at)
  date.setDate(date.getDate() + 4)
  return new Intl.DateTimeFormat('vi-VN', {
    weekday: 'long', day: '2-digit', month: 'long'
  }).format(date)
})

const trackingEvents = ref([])

const fetchOrder = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/my-orders')
    const orders = res.data.data || res.data || []
    const found = orders.find(o => o.id == route.params.orderId)
    
    if (found) {
      order.value = found
      generateTrackingHistory(found)
    } else {
      toast.add({ severity: 'warn', summary: 'Không tìm thấy', detail: 'Đơn hàng không tồn tại', life: 3000 })
      router.push('/orders')
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể kết nối máy chủ', life: 3000 })
  } finally {
    loading.value = false
  }
}

const generateTrackingHistory = (orderData) => {
  const baseDate = new Date(orderData.created_at)
  const events = []
  
  // Always have "Ordered"
  events.push({ 
    status: 'Đặt hàng thành công', 
    location: 'Hệ thống Komibook', 
    date: formatDateShort(baseDate), 
    time: formatTime(baseDate) 
  })

  // Processing
  if (orderData.status !== 'pending') {
    const procDate = new Date(baseDate)
    procDate.setHours(procDate.getHours() + 2)
    events.unshift({ 
      status: 'Đơn hàng đã được xác nhận và đang đóng gói', 
      location: 'Kho tổng Komibook Hà Nội', 
      date: formatDateShort(procDate), 
      time: formatTime(procDate) 
    })
  }

  // Shipping (Mocked for visual impact if processing+)
  if (orderData.status === 'processing' || orderData.status === 'completed') {
    const shipDate = new Date(baseDate)
    shipDate.setHours(shipDate.getHours() + 12)
    events.unshift({ 
      status: 'Đã giao cho đơn vị vận chuyển', 
      location: 'Bưu cục trung chuyển Cầu Giấy', 
      date: formatDateShort(shipDate), 
      time: formatTime(shipDate) 
    })
  }

  // Completed
  if (orderData.status === 'completed') {
    const deliveryDate = new Date(baseDate)
    deliveryDate.setDate(deliveryDate.getDate() + 2)
    events.unshift({ 
      status: 'Giao hàng thành công', 
      location: orderData.shipping_address, 
      date: formatDateShort(deliveryDate), 
      time: '10:45 AM' 
    })
    events.unshift({ 
      status: 'Tài xế đang trên đường giao đến bạn', 
      location: 'Khu vực người nhận', 
      date: formatDateShort(deliveryDate), 
      time: '08:15 AM' 
    })
  }

  trackingEvents.value = events
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)
}

const formatDateShort = (date) => {
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
}

const formatTime = (date) => {
  return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit' }).format(date)
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  const baseUrl = import.meta.env.VITE_API_URL || 'http://komibook.test'
  return `${baseUrl}/storage/${path}`
}

const getStatusText = (status) => {
  const map = { pending: 'Chờ xử lý', processing: 'Đang vận chuyển', completed: 'Đã nhận hàng', cancelled: 'Đã hủy' }
  return map[status] || status
}

const getStatusStyle = (status) => {
  const map = {
    pending: 'bg-amber-500 text-white shadow-amber-500/20',
    processing: 'bg-primary text-on-primary shadow-primary/20',
    completed: 'bg-emerald-500 text-white shadow-emerald-500/20',
    cancelled: 'bg-error text-on-error shadow-error/20'
  }
  return map[status] || 'bg-outline text-white'
}

onMounted(() => {
  fetchOrder()
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100;400;900&display=swap');

.font-outfit {
  font-family: 'Outfit', sans-serif;
}

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
  opacity: 0;
}

.animate-slide-up {
  animation: slide-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  opacity: 0;
}

.delay-100 { animation-delay: 0.1s; }
.delay-500 { animation-delay: 0.5s; }

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.fill-1 {
  font-variation-settings: 'FILL' 1;
}

/* Custom Progress Animation */
@keyframes progress-loading {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

.animate-progress-loading {
  animation: progress-loading 2s infinite linear;
}
</style>

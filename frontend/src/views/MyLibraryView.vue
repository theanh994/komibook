<template>
  <div class="min-h-screen bg-background font-outfit antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-30">
       <div class="absolute top-[-5%] left-[-5%] w-[400px] h-[400px] bg-primary/10 blur-[100px] rounded-full"></div>
       <div class="absolute bottom-[-5%] right-[-5%] w-[500px] h-[500px] bg-secondary/10 blur-[120px] rounded-full"></div>
    </div>

    <div class="w-full px-gutter max-w-[1400px] mx-auto py-xl relative z-10">
      <div class="flex flex-col lg:flex-row gap-xl">
        
        <!-- Sidebar -->
        <UserSidebar :user="authStore.user" class="lg:w-80 shrink-0" />

        <!-- Main Content -->
        <main class="flex-1 min-w-0">
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-[48px] border border-outline-variant/10 shadow-2xl overflow-hidden min-h-[700px] flex flex-col">
            
            <!-- Hero Header Section (Improved MD3) -->
            <div class="p-8 md:p-12 border-b border-outline-variant/5 bg-surface-container-low/20">
              <div class="flex flex-col gap-10">
                <!-- Row 1: Title & Description -->
                <div class="space-y-4">
                  <div class="flex items-center gap-4">
                     <div class="w-1.5 h-10 bg-primary rounded-full shadow-[0_0_15px_rgba(var(--primary),0.3)]"></div>
                     <h1 class="text-3xl md:text-4xl font-black text-on-surface tracking-tight leading-tight">Tủ sách cá nhân</h1>
                  </div>
                  <p class="text-on-surface-variant font-medium opacity-70 max-w-3xl text-base md:text-lg leading-relaxed">
                    Kho tri thức tinh hoa bạn đã sưu tầm. Tiếp tục hành trình khám phá những trang sách dang dở của riêng bạn.
                  </p>
                </div>

                <!-- Row 2: Badges & Filters -->
                <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6">
                  <!-- Badges -->
                  <div class="flex flex-wrap gap-3">
                     <div class="flex items-center gap-2.5 bg-surface-container-high/60 px-5 py-2.5 rounded-2xl border border-outline-variant/10 shadow-sm">
                        <span class="material-symbols-outlined text-[20px] text-primary fill-1">auto_stories</span>
                        <span class="text-[11px] font-black uppercase tracking-widest">{{ ebookCount }} E-BOOKS</span>
                     </div>
                     <div class="flex items-center gap-2.5 bg-surface-container-high/60 px-5 py-2.5 rounded-2xl border border-outline-variant/10 shadow-sm">
                        <span class="material-symbols-outlined text-[20px] text-secondary fill-1">menu_book</span>
                        <span class="text-[11px] font-black uppercase tracking-widest">{{ physicalCount }} SÁCH GIẤY</span>
                     </div>
                  </div>
                  
                  <!-- Filters -->
                  <div class="flex p-2 bg-surface-container-highest/50 backdrop-blur-md rounded-[28px] border border-outline-variant/10 shadow-inner w-fit">
                    <button 
                      v-for="type in typeFilters" 
                      :key="type.value"
                      @click="currentType = type.value"
                      class="px-8 py-4 rounded-2xl text-[10px] font-black transition-all border-none cursor-pointer uppercase tracking-[0.2em] whitespace-nowrap"
                      :class="currentType === type.value ? 'bg-primary text-on-primary shadow-2xl shadow-primary/30 scale-105' : 'text-outline hover:text-on-surface'"
                    >
                      {{ type.label }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-10 md:p-14 flex-grow">
              <!-- Loading MD3 -->
              <div v-if="loading" class="py-32 flex flex-col items-center gap-8 animate-fade-in">
                <div class="relative w-20 h-20">
                  <div class="absolute inset-0 border-8 border-primary/10 rounded-full"></div>
                  <div class="absolute inset-0 border-8 border-t-primary rounded-full animate-spin"></div>
                </div>
                <p class="text-[11px] font-black text-outline uppercase tracking-[0.3em] animate-pulse">Đang đồng bộ thư viện...</p>
              </div>

              <!-- Empty State (MD3) -->
              <div v-else-if="filteredItems.length === 0" class="py-32 text-center animate-fade-in">
                <div class="w-32 h-32 bg-surface-container-high rounded-[48px] flex items-center justify-center mx-auto mb-10 text-outline/20 transform rotate-6 border border-outline-variant/10">
                  <span class="material-symbols-outlined text-7xl">local_library</span>
                </div>
                <h3 class="text-3xl font-black text-on-surface mb-4 tracking-tight">Kệ sách còn đang trống</h3>
                <p class="text-on-surface-variant mb-12 max-w-sm mx-auto text-lg leading-relaxed font-medium opacity-60">
                  Hãy bắt đầu hành trình bằng việc sở hữu những tác phẩm đầu tiên.
                </p>
                <button @click="$router.push('/catalog')" class="bg-primary text-on-primary px-12 py-5 rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-2xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all border-none cursor-pointer flex items-center gap-4 mx-auto">
                  Khám phá KomiBook
                  <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                </button>
              </div>

              <!-- Premium Library Grid -->
              <div v-else class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-x-8 gap-y-12">
                <div v-for="(item, index) in filteredItems" :key="item.book.id" class="group animate-slide-up" :style="{ animationDelay: (index * 50) + 'ms' }">
                  <div class="relative aspect-[3/4.5] rounded-[32px] overflow-hidden shadow-lg group-hover:shadow-[0_40px_80px_rgba(0,0,0,0.15)] group-hover:-translate-y-4 transition-all duration-700 ease-out border border-outline-variant/10 mb-6 bg-surface-container-low">
                    <img v-if="item.book.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="item.book.title" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000" />
                    <div v-else class="w-full h-full flex items-center justify-center text-outline/20">
                      <span class="material-symbols-outlined text-[80px]">auto_stories</span>
                    </div>
                    
                    <!-- Type Indicator Float -->
                    <div class="absolute top-4 left-4 z-10">
                       <div class="px-4 py-2 bg-black/60 backdrop-blur-xl text-white text-[9px] font-black uppercase tracking-widest rounded-xl border border-white/10 flex items-center gap-2">
                          <span class="material-symbols-outlined text-[16px]">{{ item.book.type === 'ebook' ? 'bolt' : 'package_2' }}</span>
                          {{ item.book.type === 'ebook' ? 'Digital' : 'Hardcover' }}
                       </div>
                    </div>

                    <!-- Progress Indicator -->
                    <div v-if="item.book.type === 'ebook'" class="absolute bottom-4 inset-x-4 bg-black/40 backdrop-blur-md p-3 rounded-2xl border border-white/5 z-10">
                       <div class="flex justify-between items-center mb-2">
                          <span class="text-[8px] font-black text-white/60 uppercase tracking-widest">Progress</span>
                          <span class="text-[9px] font-black text-white">{{ readingProgress }}%</span>
                       </div>
                       <div class="w-full h-1 bg-white/20 rounded-full overflow-hidden">
                          <div class="bg-primary h-full transition-all duration-1000" :style="{ width: readingProgress + '%' }"></div>
                       </div>
                    </div>

                    <!-- Hover Quick Actions -->
                    <div class="absolute inset-0 bg-gradient-to-t from-primary/90 via-primary/40 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-500 flex flex-col items-center justify-center p-8 gap-4 z-20">
                      <button 
                        v-if="item.book.type === 'ebook'"
                        @click="readEbook(item.order_id, item.book.id)"
                        class="w-full bg-white text-primary py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-2xl active:scale-95 transition-all flex items-center justify-center gap-3 border-none cursor-pointer"
                      >
                        <span class="material-symbols-outlined text-[24px] fill-1">auto_stories</span>
                        Đọc Ngay
                      </button>
                      <button 
                        v-if="item.book.type === 'physical'"
                        @click="$router.push(`/tracking/${item.order_id}`)"
                        class="w-full bg-white text-on-surface py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-2xl active:scale-95 transition-all flex items-center justify-center gap-3 border-none cursor-pointer"
                      >
                        <span class="material-symbols-outlined text-[24px]">local_shipping</span>
                        Theo dõi
                      </button>
                      <button 
                        @click="$router.push(`/book/${item.book.slug || item.book.id}`)"
                        class="w-full bg-surface-container-lowest/10 backdrop-blur-xl text-white border border-white/30 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-white/20 transition-all flex items-center justify-center gap-3 cursor-pointer"
                      >
                        Thông tin
                      </button>
                    </div>
                  </div>
                  
                  <div class="px-2 space-y-2 text-center sm:text-left">
                    <h4 class="text-lg font-black text-on-surface leading-tight line-clamp-1 group-hover:text-primary transition-colors tracking-tight">{{ item.book.title }}</h4>
                    <div class="flex items-center justify-center sm:justify-start gap-2">
                       <p class="text-[10px] text-outline font-black uppercase tracking-[0.2em] opacity-60">{{ item.book.author || 'KomiBook Author' }}</p>
                    </div>
                    
                    <div v-if="item.book.type === 'physical'" class="flex items-center justify-center sm:justify-start gap-2 pt-1">
                       <span class="w-1.5 h-1.5 rounded-full" :class="item.status === 'completed' ? 'bg-success' : 'bg-amber-500'"></span>
                       <span class="text-[9px] font-black text-outline uppercase tracking-widest">
                         {{ item.status === 'completed' ? 'Đã sở hữu' : 'Đang tới' }}
                       </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Premium Footer Tip -->
            <div class="p-8 md:p-10 bg-surface-container-low/30 border-t border-outline-variant/5">
              <div class="flex items-center gap-4 bg-primary/5 p-6 rounded-3xl border border-primary/10">
                <div class="w-12 h-12 bg-primary rounded-2xl flex items-center justify-center text-on-primary shrink-0">
                   <span class="material-symbols-outlined text-[24px]">lightbulb</span>
                </div>
                <div>
                   <p class="text-sm font-black text-on-surface mb-0.5">Mẹo nhỏ cho bạn</p>
                   <p class="text-xs text-on-surface-variant font-medium opacity-70 leading-relaxed">
                     Sách giấy đang vận chuyển sẽ tự động hoàn tất hành trình trong tủ sách khi bạn xác nhận đã nhận hàng thành công.
                   </p>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>

    <Toast />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import UserSidebar from '@/components/profile/UserSidebar.vue'
import Toast from 'primevue/toast'

const authStore = useAuthStore()
const router = useRouter()
const toast = useToast()

const libraryItems = ref([])
const loading = ref(true)
const currentType = ref('all')
const readingProgress = ref(Math.floor(Math.random() * 80) + 10) // Mock for visual impact

const typeFilters = [
  { label: 'Tất cả', value: 'all' },
  { label: 'E-book', value: 'ebook' },
  { label: 'Sách giấy', value: 'physical' }
]

const ebookCount = computed(() => libraryItems.value.filter(i => i.book.type === 'ebook').length)
const physicalCount = computed(() => libraryItems.value.filter(i => i.book.type === 'physical').length)

const filteredItems = computed(() => {
  if (currentType.value === 'all') return libraryItems.value
  return libraryItems.value.filter(item => item.book.type === currentType.value)
})

const fetchLibrary = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/my-orders')
    const orders = res.data.data || res.data || []
    
    const itemsMap = new Map()
    
    orders.forEach(order => {
      if (order.status === 'cancelled') return

      order.items?.forEach(item => {
        if (!item.book) return

        const isEbook = item.book.type === 'ebook'
        const isPaper = item.book.type === 'physical' && (order.status === 'completed' || order.payment_status === 'paid' || order.status === 'processing' || order.status === 'shipping')

        if (isEbook || isPaper) {
          const existing = itemsMap.get(item.book_id)
          // Deduplication: Always prioritize completed order or most recent
          // If multiple orders for the same book, pick the one that is 'completed' or 'shipping'
          if (!existing || (order.status === 'completed' && existing.status !== 'completed')) {
            itemsMap.set(item.book_id, {
              ...item,
              order_id: order.id,
              status: order.status,
              payment_status: order.payment_status
            })
          }
        }
      })
    })
    
    libraryItems.value = Array.from(itemsMap.values()).sort((a, b) => {
      if (a.book.type === 'ebook' && b.book.type !== 'ebook') return -1
      if (a.book.type !== 'ebook' && b.book.type === 'ebook') return 1
      return b.order_id - a.order_id
    })
  } catch (error) {
    console.error('Library fetch error:', error)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải tủ sách', life: 3000 })
  } finally { 
    loading.value = false 
  }
}

const readEbook = (orderId, bookId) => {
  router.push({ name: 'ebook-reader', params: { orderId, bookId } })
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  const baseUrl = import.meta.env.VITE_API_URL || 'http://komibook.test'
  return `${baseUrl}/storage/${path}`
}

onMounted(() => {
  fetchLibrary()
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100;400;900&display=swap');

.font-outfit {
  font-family: 'Outfit', sans-serif;
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slide-up {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-up {
  animation: slide-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  opacity: 0;
}

.fill-1 {
  font-variation-settings: 'FILL' 1;
}

::-webkit-scrollbar {
  width: 0px;
}
</style>

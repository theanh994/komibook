<template>
  <div class="min-h-screen bg-background font-outfit antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-40">
       <div class="absolute top-[-10%] right-[-10%] w-[600px] h-[600px] bg-primary/10 blur-[120px] rounded-full"></div>
       <div class="absolute bottom-[-10%] left-[-10%] w-[500px] h-[500px] bg-secondary/10 blur-[100px] rounded-full"></div>
    </div>

    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl relative z-10">
      
      <!-- Premium Breadcrumb -->
      <nav class="mb-xl flex items-center gap-3 text-[11px] font-black uppercase tracking-[0.2em] text-outline/60 animate-fade-in">
        <router-link to="/" class="hover:text-primary transition-all flex items-center gap-1 group">
          <span class="material-symbols-outlined text-[20px] group-hover:scale-110 transition-transform">home</span>
        </router-link>
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
        <span class="text-primary">Chương trình Flash Sale</span>
      </nav>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-8">
        <div class="h-64 bg-surface-container-low rounded-[48px] animate-pulse"></div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
          <div v-for="i in 4" :key="i" class="aspect-[3/4.5] bg-surface-container-low rounded-[32px] animate-pulse"></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!activeSale" class="flex flex-col items-center justify-center py-32 bg-surface-container-lowest rounded-[48px] shadow-2xl border border-outline-variant/10 text-center animate-fade-in">
        <div class="w-24 h-24 bg-primary/10 rounded-full flex items-center justify-center mb-8">
           <span class="material-symbols-outlined text-[56px] text-primary">bolt</span>
        </div>
        <h2 class="text-3xl font-black text-on-surface mb-4 tracking-tight">Không có Flash Sale nào đang diễn ra</h2>
        <p class="text-on-surface-variant mb-10 max-w-md mx-auto font-medium leading-relaxed">Hãy quay lại sau để đón chờ những ưu đãi cực lớn nhé!</p>
        <router-link to="/" class="bg-primary text-on-primary px-10 py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all">
          Về trang chủ
        </router-link>
      </div>

      <!-- Flash Sale Content -->
      <div v-else class="animate-fade-in space-y-12">
        <!-- Hero Header -->
        <div class="relative overflow-hidden bg-gradient-to-br from-primary to-primary-container text-on-primary rounded-[48px] p-8 md:p-16 shadow-xl border border-white/10 flex flex-col md:flex-row items-center justify-between gap-8">
          <div class="absolute inset-0 pointer-events-none opacity-20">
             <div class="absolute top-[-50%] right-[-30%] w-[800px] h-[800px] bg-white blur-[150px] rounded-full"></div>
          </div>

          <div class="space-y-4 text-center md:text-left relative z-10 max-w-2xl">
            <span class="inline-flex items-center gap-2 px-5 py-2 bg-white/25 backdrop-blur-md rounded-full text-[10px] font-black uppercase tracking-widest text-white border border-white/20">
              <span class="material-symbols-outlined text-[16px] animate-pulse" style="font-variation-settings: 'FILL' 1;">bolt</span> Đang diễn ra
            </span>
            <h1 class="text-4xl md:text-6xl font-black tracking-tighter leading-none">{{ activeSale.title }}</h1>
            <p class="text-white/80 font-medium text-sm md:text-base">Săn sách yêu thích với mức ưu đãi cực sâu. Số lượng có hạn!</p>
          </div>

          <!-- Countdown Timer -->
          <div class="bg-black/25 backdrop-blur-xl rounded-[32px] p-6 md:p-8 border border-white/15 text-center space-y-4 min-w-[260px] relative z-10">
            <p class="text-[9px] uppercase font-black tracking-[0.2em] text-white/70">Thời gian còn lại</p>
            <div class="flex items-center justify-center gap-3">
              <div class="flex flex-col items-center">
                <span class="text-3xl font-black tracking-tight">{{ countdown.hours }}</span>
                <span class="text-[9px] uppercase font-bold text-white/60 mt-1">Giờ</span>
              </div>
              <span class="text-2xl font-bold -mt-4 text-white/60">:</span>
              <div class="flex flex-col items-center">
                <span class="text-3xl font-black tracking-tight">{{ countdown.minutes }}</span>
                <span class="text-[9px] uppercase font-bold text-white/60 mt-1">Phút</span>
              </div>
              <span class="text-2xl font-bold -mt-4 text-white/60">:</span>
              <div class="flex flex-col items-center">
                <span class="text-3xl font-black tracking-tight">{{ countdown.seconds }}</span>
                <span class="text-[9px] uppercase font-bold text-white/60 mt-1">Giây</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
          <div v-for="item in activeSale.items" :key="item.id" class="group bg-surface-container-lowest hover:bg-surface rounded-[32px] border border-outline-variant/15 hover:border-primary/20 hover:shadow-2xl transition-all duration-500 overflow-hidden flex flex-col p-4">
            <!-- Cover image -->
            <div class="relative aspect-[3/4.2] rounded-[24px] overflow-hidden bg-surface-container-low mb-6">
              <img :src="getCoverUrl(item.book.cover_image)" :alt="item.book.title" @error="handleImageError" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
              <!-- Sale Badge -->
              <span class="absolute top-3 left-3 bg-primary text-on-primary text-xs font-black px-3 py-1.5 rounded-xl shadow-lg">
                -{{ item.discount_percent }}%
              </span>
            </div>

            <!-- Content -->
            <div class="flex-1 flex flex-col justify-between space-y-4 px-2">
              <div class="space-y-1">
                <p class="text-[10px] font-black text-outline uppercase tracking-wider">{{ item.book.category?.name || 'Sách' }}</p>
                <h3 @click="$router.push({ name: 'book-detail', params: { slug: item.book.slug } })" class="text-base font-black text-on-surface line-clamp-2 cursor-pointer hover:text-primary transition-colors tracking-tight leading-snug">
                  {{ item.book.title }}
                </h3>
                <p class="text-xs text-outline/80 font-medium">{{ item.book.author }}</p>
              </div>

              <!-- Price & Progress & Button -->
              <div class="space-y-4 pt-2 border-t border-outline-variant/5">
                <div class="flex items-center justify-between">
                  <div class="flex flex-col">
                    <span class="text-lg font-black text-primary tracking-tight">{{ formatCurrency(item.book.sale_price) }}</span>
                    <span class="text-xs text-outline line-through opacity-60">{{ formatCurrency(item.book.price) }}</span>
                  </div>
                </div>

                <!-- Limited Indicator / Sold quantity bar -->
                <div class="space-y-1.5">
                  <div class="flex justify-between items-center text-[10px] font-bold text-outline">
                    <span>Đã bán {{ getSoldPercent(item) }}%</span>
                    <span>Còn lại {{ item.book.stock }}</span>
                  </div>
                  <div class="w-full h-1.5 bg-surface-container-high rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-primary to-secondary" :style="{ width: getSoldPercent(item) + '%' }"></div>
                  </div>
                </div>

                <button @click="addToCart(item.book)" class="w-full py-3.5 rounded-2xl bg-primary text-on-primary text-[11px] font-black uppercase tracking-widest hover:scale-[1.03] active:scale-95 transition-all shadow-md shadow-primary/10 flex items-center justify-center gap-2">
                  <span class="material-symbols-outlined text-[16px]">shopping_cart</span> Thêm vào giỏ
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useCartStore } from '@/stores/cart'
import apiClient from '@/services/axios'

const toast = useToast()
const cartStore = useCartStore()

const activeSale = ref(null)
const loading = ref(true)
const timer = ref(null)
const countdown = ref({ hours: '00', minutes: '00', seconds: '00' })

const fetchActiveSale = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/flash-sales/active')
    if (res.data && res.data.data) {
      activeSale.value = res.data.data
      startCountdown(new Date(activeSale.value.end_time))
    }
  } catch (error) {
    console.error('Lỗi tải thông tin Flash Sale:', error)
  } finally {
    loading.value = false
  }
}

const startCountdown = (endTime) => {
  if (timer.value) clearInterval(timer.value)
  
  const updateTimer = () => {
    const now = new Date()
    const diff = endTime - now
    if (diff <= 0) {
      countdown.value = { hours: '00', minutes: '00', seconds: '00' }
      activeSale.value = null
      clearInterval(timer.value)
      return
    }
    
    const h = Math.floor(diff / (1000 * 60 * 60))
    const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
    const s = Math.floor((diff % (1000 * 60)) / 1000)
    
    countdown.value = {
      hours: h < 10 ? '0' + h : h,
      minutes: m < 10 ? '0' + m : m,
      seconds: s < 10 ? '0' + s : s
    }
  }
  
  updateTimer()
  timer.value = setInterval(updateTimer, 1000)
}

const getSoldPercent = (item) => {
  const sold = item.sold_quantity || 0
  const stock = item.book?.stock || 0
  const max = item.max_quantity || 0
  
  if (max > 0) {
    return Math.round((sold / max) * 100)
  }
  // Nếu không giới hạn số lượng tham gia, tính dựa trên tồn kho giả định
  if (sold === 0) return 12 // Giả lập chút đã bán cho đẹp mắt
  const total = sold + stock
  return Math.min(95, Math.round((sold / total) * 100))
}

const addToCart = (book) => {
  cartStore.addToCart({
    id: book.id,
    title: book.title,
    slug: book.slug,
    author: book.author,
    cover_image: book.cover_image,
    price: book.price,
    sale_price: book.sale_price,
    type: book.type,
    vendor: book.vendor,
    vendor_id: book.vendor?.id
  })
  toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã thêm "${book.title}" vào giỏ hàng!`, life: 3000 })
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

const handleImageError = (e) => {
  e.target.src = 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=600&auto=format&fit=crop'
}

onMounted(() => {
  fetchActiveSale()
})

onUnmounted(() => {
  if (timer.value) clearInterval(timer.value)
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

.animate-fade-in {
  animation: fade-in 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>

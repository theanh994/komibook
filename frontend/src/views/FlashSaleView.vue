<template>
  <div class="min-h-screen bg-background font-inter antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-40">
       <div class="absolute top-[-10%] right-[-10%] w-[600px] h-[600px] bg-primary/10 blur-[120px] rounded-full"></div>
       <div class="absolute bottom-[-10%] left-[-10%] w-[500px] h-[500px] bg-secondary/10 blur-[100px] rounded-full"></div>
    </div>

    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl relative z-10">
      
      <!-- Premium Breadcrumb -->
      <nav class="mb-xl flex items-center gap-3 text-xs font-bold uppercase tracking-[0.16em] text-outline animate-fade-in" aria-label="Đường dẫn">
        <router-link to="/" class="flex min-h-11 min-w-11 items-center justify-center transition-colors hover:text-primary" aria-label="Trang chủ">
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

      <!-- Error State -->
      <div v-else-if="error" class="flex flex-col items-center justify-center py-24 bg-surface-container-lowest rounded-[48px] shadow-2xl border border-outline-variant/10 text-center animate-fade-in space-y-6">
        <div class="w-20 h-20 bg-rose-50 rounded-full flex items-center justify-center text-rose-500">
           <span class="material-symbols-outlined text-[48px]">warning</span>
        </div>
        <div class="space-y-2">
          <h2 class="text-2xl font-bold text-on-surface">Không thể tải chương trình Flash Sale</h2>
          <p class="text-on-surface-variant text-sm max-w-md mx-auto">{{ error }}</p>
        </div>
        <button @click="fetchActiveSale" class="ui-button ui-button-primary">
          <span class="material-symbols-outlined text-[18px]">refresh</span> Thử lại
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="!activeSale || !activeSale.items || activeSale.items.length === 0" class="flex flex-col items-center justify-center py-32 bg-surface-container-lowest rounded-[48px] shadow-2xl border border-outline-variant/10 text-center animate-fade-in">
        <div class="w-24 h-24 bg-primary/10 rounded-full flex items-center justify-center mb-8">
           <span class="material-symbols-outlined text-[56px] text-primary">bolt</span>
        </div>
        <h2 class="text-3xl font-bold text-on-surface mb-4 tracking-tight">Không có Flash Sale nào đang diễn ra</h2>
        <p class="text-on-surface-variant mb-10 max-w-md mx-auto font-medium leading-relaxed">Hãy quay lại sau để đón chờ những ưu đãi cực lớn nhé!</p>
        <router-link to="/" class="ui-button ui-button-primary no-underline">
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
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/25 px-5 py-2 text-xs font-bold uppercase tracking-widest text-white backdrop-blur-md">
              <span class="material-symbols-outlined text-[16px] animate-pulse" style="font-variation-settings: 'FILL' 1;">bolt</span> Đang diễn ra
            </span>
            <h1 class="text-4xl md:text-6xl font-bold tracking-tighter leading-none">{{ activeSale.title }}</h1>
            <p class="text-white/80 font-medium text-sm md:text-base">Săn sách yêu thích với mức ưu đãi cực sâu. Số lượng có hạn!</p>
          </div>

          <!-- Countdown Timer -->
          <div class="bg-black/25 backdrop-blur-xl rounded-[32px] p-6 md:p-8 border border-white/15 text-center space-y-4 min-w-[260px] relative z-10">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-white/80">Thời gian còn lại</p>
            <div class="flex items-center justify-center gap-3">
              <div class="flex flex-col items-center">
                <span class="text-3xl font-bold tracking-tight">{{ countdown.hours }}</span>
                <span class="mt-1 text-xs font-bold uppercase text-white/80">Giờ</span>
              </div>
              <span class="text-2xl font-bold -mt-4 text-white/60">:</span>
              <div class="flex flex-col items-center">
                <span class="text-3xl font-bold tracking-tight">{{ countdown.minutes }}</span>
                <span class="mt-1 text-xs font-bold uppercase text-white/80">Phút</span>
              </div>
              <span class="text-2xl font-bold -mt-4 text-white/60">:</span>
              <div class="flex flex-col items-center">
                <span class="text-3xl font-bold tracking-tight">{{ countdown.seconds }}</span>
                <span class="mt-1 text-xs font-bold uppercase text-white/80">Giây</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 xl:grid-cols-4">
          <article v-for="item in activeSale.items" :key="item.id" class="group flex flex-col overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-3 shadow-sm transition-shadow hover:shadow-card-hover md:p-4">
            <!-- Cover image -->
            <div class="relative mb-5 aspect-[2/3] overflow-hidden bg-surface-container">
              <img v-if="item.book.cover_image && !failedCoverIds.has(item.book.id)" :src="getCoverUrl(item.book.cover_image)" :alt="`Bìa sách ${item.book.title}`" @error="markCoverFailed(item.book.id)" class="h-full w-full rounded-none object-contain p-2" />
              <div v-else class="flex h-full w-full items-center justify-center text-outline" aria-hidden="true">
                <span class="material-symbols-outlined text-4xl">image</span>
              </div>
              <!-- Sale Badge -->
              <span class="absolute top-3 left-3 bg-primary text-on-primary text-xs font-bold px-3 py-1.5 rounded-xl shadow-lg">
                -{{ item.discount_percent }}%
              </span>
            </div>

            <!-- Content -->
            <div class="flex-1 flex flex-col justify-between space-y-4 px-2">
              <div class="space-y-1">
                <p class="text-xs font-bold uppercase tracking-wider text-outline">{{ item.book.category?.name || 'Sách' }}</p>
                <h3 class="line-clamp-2 text-base font-bold leading-snug tracking-tight text-on-surface">
                  <router-link :to="{ name: 'book-detail', params: { slug: item.book.slug } }" class="text-inherit no-underline transition-colors hover:text-primary">{{ item.book.title }}</router-link>
                </h3>
                <p class="text-xs text-outline/80 font-medium">{{ item.book.author }}</p>
              </div>

              <!-- Price & Progress & Button -->
              <div class="space-y-4 pt-2 border-t border-outline-variant/5">
                <div class="flex items-center justify-between">
                  <div class="flex flex-col">
                    <span class="text-lg font-bold text-primary tracking-tight">{{ formatCurrency(item.book.sale_price) }}</span>
                    <span class="text-xs text-outline line-through opacity-60">{{ formatCurrency(item.book.price) }}</span>
                  </div>
                </div>

                <!-- Limited Indicator / Sold quantity bar -->
                <div class="space-y-1.5">
                  <div class="flex items-center justify-between text-xs font-bold text-outline">
                    <span>Đã bán {{ getSoldPercent(item) }}%</span>
                    <span>Còn lại {{ item.book.stock }}</span>
                  </div>
                  <div class="w-full h-1.5 bg-surface-container-high rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-primary to-secondary" :style="{ width: getSoldPercent(item) + '%' }"></div>
                  </div>
                </div>

                <button @click="addToCart(item.book)" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border-none bg-primary px-3 text-xs font-bold uppercase tracking-wider text-on-primary shadow-md transition-colors hover:bg-primary/90">
                  <span class="material-symbols-outlined text-[16px]">shopping_cart</span> Thêm vào giỏ
                </button>
              </div>
            </div>
          </article>
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
import { readApiData } from '@/services/apiContract'

const toast = useToast()
const cartStore = useCartStore()

const activeSale = ref(null)
const loading = ref(true)
const error = ref(null)
const timer = ref(null)
const countdown = ref({ hours: '00', minutes: '00', seconds: '00' })
const failedCoverIds = ref(new Set())

const fetchActiveSale = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await apiClient.get('/api/flash-sales/active')
    const sale = readApiData(res.data)
    if (sale) {
      activeSale.value = sale
      if (activeSale.value.end_time) {
        startCountdown(new Date(activeSale.value.end_time))
      }
    } else {
      activeSale.value = null
    }
  } catch (err) {
    console.error('Lỗi tải thông tin Flash Sale:', err)
    activeSale.value = null
    error.value = err.response?.data?.message || 'Không thể kết nối chương trình Flash Sale.'
    if (timer.value) clearInterval(timer.value)
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
      hours: h < 10 ? '0' + h : String(h),
      minutes: m < 10 ? '0' + m : String(m),
      seconds: s < 10 ? '0' + s : String(s)
    }
  }
  
  updateTimer()
  timer.value = setInterval(updateTimer, 1000)
}

const getSoldPercent = (item) => {
  const sold = item.sold_quantity || 0
  const stock = item.book?.stock || 0
  const max = item.max_quantity || 0
  
  if (sold === 0) return 0

  let pct = 0
  if (max > 0) {
    pct = Math.round((sold / max) * 100)
  } else if (sold + stock > 0) {
    pct = Math.round((sold / (sold + stock)) * 100)
  }
  return Math.max(0, Math.min(100, pct))
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
    vendor_id: book.vendor?.id,
    category: book.category,
    categories: book.categories,
    ...(Object.prototype.hasOwnProperty.call(book, 'category_id') ? { category_id: book.category_id } : {})
  })
  toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã thêm "${book.title}" vào giỏ hàng!`, life: 3000 })
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

const markCoverFailed = (bookId) => {
  failedCoverIds.value = new Set([...failedCoverIds.value, bookId])
}

onMounted(() => {
  fetchActiveSale()
})

onUnmounted(() => {
  if (timer.value) clearInterval(timer.value)
})
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>

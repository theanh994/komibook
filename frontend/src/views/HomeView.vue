<template>
  <div class="min-h-screen bg-background">

    <!-- ═══ Flash Sale Banner ═══ -->
    <Transition name="slide-down">
      <div v-if="activeFlashSale" class="flash-sale-banner">
        <div class="max-w-[1280px] mx-auto px-gutter py-3 flex flex-col md:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="flash-icon-box">
              <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">bolt</span>
            </div>
            <span class="text-white text-sm font-medium">
              Nhập mã <span class="code-badge">{{ activeFlashSale.code }}</span> để giảm <span class="font-extrabold text-white">{{ activeFlashSale.discount_percent }}%</span>
              <span v-if="activeFlashSale.category"> cho danh mục <b>{{ activeFlashSale.category?.name }}</b></span>!
            </span>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-white/80 text-[10px] md:text-xs font-bold uppercase tracking-widest hidden sm:inline">Kết thúc sau</span>
            <div class="flex items-center gap-1.5">
              <div class="timer-box"><span class="timer-num">{{ countdown.hours }}</span><span class="timer-unit">Giờ</span></div>
              <span class="text-white font-bold text-lg -mt-3">:</span>
              <div class="timer-box"><span class="timer-num">{{ countdown.minutes }}</span><span class="timer-unit">Phút</span></div>
              <span class="text-white font-bold text-lg -mt-3">:</span>
              <div class="timer-box"><span class="timer-num">{{ countdown.seconds }}</span><span class="timer-unit">Giây</span></div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ═══ HERO SECTION ═══ -->
    <section class="w-full px-gutter max-w-[1280px] mx-auto py-xxl">
      <div class="relative rounded-xl overflow-hidden soft-shadow h-[420px] md:h-[500px] flex items-center justify-center bg-primary-container">
        <img
          alt="Komibook hero"
          class="absolute inset-0 w-full h-full object-cover opacity-30 mix-blend-overlay"
          src="https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-1.2.1&auto=format&fit=crop&w=2000&q=80"
        />
        <div class="relative z-10 text-center max-w-2xl px-md">
          <h1 class="font-inter text-4xl md:text-5xl font-bold text-on-primary mb-md tracking-tight leading-tight">
            Khám phá thế giới sách
          </h1>
          <p class="font-inter text-lg text-primary-fixed-dim mb-lg leading-relaxed">
            Trải nghiệm đọc sách cao cấp, không gian tĩnh lặng và bộ sưu tập được tuyển chọn kỹ lưỡng dành riêng cho bạn.
          </p>
          <router-link
            to="/catalog"
            class="inline-flex items-center gap-2 bg-secondary text-on-secondary px-xl py-sm rounded-lg text-sm font-medium hover:bg-secondary-container transition-colors shadow-sm no-underline"
          >
            Bắt đầu khám phá
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
          </router-link>
        </div>
      </div>
    </section>

    <!-- ═══ TOP SELLING SECTION ═══ -->
    <section class="w-full px-gutter max-w-[1280px] mx-auto py-xl">
      <div class="flex justify-between items-end mb-lg">
        <h2 class="font-inter text-3xl font-semibold text-primary tracking-tight">Sách Bán Chạy Nhất</h2>
        <router-link to="/catalog" class="text-sm font-medium text-secondary hover:underline flex items-center gap-1 no-underline">
          Xem tất cả <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        </router-link>
      </div>

      <!-- Loading Skeleton -->
      <div v-if="loadingTopSelling" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-gutter">
        <div v-for="i in 5" :key="i" class="bg-surface-container-lowest rounded-lg overflow-hidden border border-outline-variant/30">
          <Skeleton height="220px" borderRadius="0" />
          <div class="p-md flex flex-col gap-2"><Skeleton height="14px" width="80%" /><Skeleton height="12px" width="60%" /><Skeleton height="16px" width="40%" /></div>
        </div>
      </div>

      <!-- Book Grid -->
      <div v-else-if="topSellingBooks.length > 0" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-gutter">
        <div
          v-for="book in topSellingBooks"
          :key="book.id"
          class="group bg-surface-container-lowest rounded-lg soft-shadow overflow-hidden cursor-pointer border border-outline-variant/30 flex flex-col h-full transition-all duration-300 hover:-translate-y-1 hover:shadow-card-hover"
          @click="goToDetail(book.slug)"
        >
          <!-- Cover -->
          <div class="relative pt-[140%] bg-surface-variant/30">
            <img v-if="book.cover_image" :src="book.cover_image" :alt="book.title" class="absolute inset-0 w-full h-full object-cover p-2 rounded-t-lg transition-transform duration-500 group-hover:scale-105" loading="lazy" />
            <div v-else class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-outline text-4xl">image</span></div>
            <!-- Sold Badge -->
            <div v-if="book.total_sold" class="absolute bottom-2 left-2 right-2 bg-inverse-surface/70 backdrop-blur-sm rounded-md py-1 px-2 text-center">
              <span class="text-inverse-on-surface text-[11px] font-medium">Đã bán: {{ book.total_sold }}</span>
            </div>
          </div>
          <!-- Info -->
          <div class="p-md flex flex-col flex-grow">
            <span class="text-[10px] font-bold text-outline uppercase tracking-wider mb-1">{{ book.category?.name || 'Sách' }}</span>
            <h3 class="text-sm font-medium text-on-surface line-clamp-2 mb-1 leading-snug group-hover:text-primary transition-colors">{{ book.title }}</h3>
            <p class="text-[13px] text-on-surface-variant mb-md flex-grow">{{ book.author || 'Đang cập nhật' }}</p>
            <div class="flex flex-col gap-2 mt-auto">
              <div class="flex justify-between items-center">
                <span class="text-sm font-bold text-primary">{{ formatCurrency(book.sale_price || book.price) }}</span>
                <button
                  class="text-primary hover:text-secondary transition-colors p-1 bg-surface-container rounded-full hover:bg-surface-variant"
                  @click.stop="addToCart(book)"
                  title="Thêm vào giỏ"
                >
                  <span class="material-symbols-outlined text-[20px]">add_shopping_cart</span>
                </button>
              </div>
              <button
                class="w-full py-2 px-md bg-primary text-on-primary rounded-lg text-xs font-bold hover:bg-primary/90 transition-all shadow-sm active:scale-95"
                @click.stop="buyNow(book)"
              >
                Mua ngay
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ SÁCH MỚI NHẤT ═══ -->
    <section class="w-full px-gutter max-w-[1280px] mx-auto py-xl">
      <div class="flex justify-between items-end mb-lg">
        <h2 class="font-inter text-3xl font-semibold text-primary tracking-tight">Sách Mới Nhất</h2>
        <router-link to="/catalog" class="text-sm font-medium text-secondary hover:underline flex items-center gap-1 no-underline">
          Xem tất cả <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        </router-link>
      </div>

      <!-- Loading State -->
      <div v-if="loadingBooks" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-gutter">
        <div v-for="i in 10" :key="i" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
          <Skeleton height="220px" borderRadius="0" />
          <div class="p-md flex flex-col gap-2"><Skeleton height="14px" width="85%" /><Skeleton height="12px" width="60%" /><Skeleton height="16px" width="40%" /></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="books.length === 0" class="flex flex-col items-center justify-center py-xxl text-center bg-surface-container-lowest rounded-xl border border-outline-variant/20">
        <div class="w-20 h-20 rounded-full bg-surface-container flex items-center justify-center mb-lg">
          <span class="material-symbols-outlined text-outline text-4xl">search_off</span>
        </div>
        <p class="text-lg font-medium text-on-surface mb-1">Chưa có sách nào</p>
        <p class="text-sm text-outline">Hãy quay lại sau nhé.</p>
      </div>

      <!-- Book Grid -->
      <div v-else>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-gutter">
          <div
            v-for="book in books"
            :key="book.id"
            class="group bg-surface-container-lowest rounded-lg soft-shadow overflow-hidden cursor-pointer border border-outline-variant/30 flex flex-col h-full transition-all duration-300 hover:-translate-y-1 hover:shadow-card-hover"
            @click="goToDetail(book.slug)"
          >
            <!-- Cover -->
            <div class="relative pt-[140%] bg-surface-variant/30">
              <img v-if="book.cover_image" :src="book.cover_image" :alt="book.title" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" />
              <div v-else class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-outline text-4xl">image</span></div>
              <!-- Sale Badge -->
              <span
                v-if="book.sale_price && book.price > book.sale_price"
                class="absolute top-2 left-2 bg-secondary text-on-secondary text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm"
              >-{{ Math.round((1 - book.sale_price / book.price) * 100) }}%</span>
            </div>
            <!-- Info -->
            <div class="p-md flex flex-col flex-grow">
              <span class="text-[10px] font-bold text-outline uppercase tracking-wider mb-1">{{ book.category?.name || 'Sách' }}</span>
              <h3 class="text-sm font-medium text-on-surface line-clamp-2 leading-snug mb-1 group-hover:text-primary transition-colors">{{ book.title }}</h3>
              <p class="text-[13px] text-on-surface-variant mb-md flex-grow">
                {{ book.author || 'Đang cập nhật' }}
              </p>
              <!-- Price & Cart -->
              <div class="flex flex-col gap-2 mt-auto">
                <div class="flex items-center justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-primary">{{ formatCurrency(book.sale_price || book.price) }}</span>
                    <span v-if="book.sale_price && book.price > book.sale_price" class="text-xs text-outline line-through">{{ formatCurrency(book.price) }}</span>
                  </div>
                  <button
                    class="text-primary hover:text-secondary transition-colors p-1 bg-surface-container rounded-full hover:bg-surface-variant"
                    @click.stop="addToCart(book)"
                    title="Thêm vào giỏ"
                  >
                    <span class="material-symbols-outlined text-[20px]">add_shopping_cart</span>
                  </button>
                </div>
                <button
                  class="w-full py-2 px-md bg-primary text-on-primary rounded-lg text-xs font-bold hover:bg-primary/90 transition-all shadow-sm active:scale-95"
                  @click.stop="buyNow(book)"
                >
                  Mua ngay
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalRecords > 12" class="mt-xl flex justify-center">
          <Paginator
            :rows="12"
            :totalRecords="totalRecords"
            :first="first"
            @page="onPageChange"
            template="PrevPageLink PageLinks NextPageLink"
            class="!border-none !bg-transparent"
          />
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'
import { useCartStore } from '@/stores/cart'
import { useToast } from 'primevue/usetoast'
import Skeleton from 'primevue/skeleton'
import Paginator from 'primevue/paginator'

const router = useRouter()
const cartStore = useCartStore()
const toast = useToast()

// ─── State ──────────────────────────────────────────────────────────
const activeFlashSale = ref(null)
const countdown = ref({ hours: '00', minutes: '00', seconds: '00' })
let countdownInterval = null

const books = ref([])
const loadingBooks = ref(false)

const topSellingBooks = ref([])
const loadingTopSelling = ref(false)

// Pagination
const totalRecords = ref(0)
const first = ref(0)
const currentPage = ref(1)

// ─── Fetch API ──────────────────────────────────────────────────────
const fetchBooks = async () => {
  loadingBooks.value = true
  try {
    const params = { page: currentPage.value }
    const response = await apiClient.get('/api/books', { params })
    books.value = response.data.data || response.data
    const meta = response.data.meta || {}
    totalRecords.value = meta.total || 0
  } catch (error) {
    console.error('Lỗi tải sách:', error)
  } finally {
    loadingBooks.value = false
  }
}

const fetchFlashSales = async () => {
  try {
    const res = await apiClient.get('/api/flash-sales')
    if (!res.data || !res.data.data) return
    const now = new Date()
    const active = res.data.data.find(fs => {
      const start = new Date(fs.start_time)
      const end = new Date(fs.end_time)
      return (start <= now && end > now)
    })
    if (active) {
      activeFlashSale.value = active
      startCountdown(new Date(active.end_time))
    }
  } catch (e) {
    console.error('Failed to fetch flash sales', e)
  }
}

const startCountdown = (endTime) => {
  if (countdownInterval) clearInterval(countdownInterval)
  const update = () => {
    const now = new Date()
    const diff = endTime - now
    if (diff <= 0) {
      clearInterval(countdownInterval)
      activeFlashSale.value = null
      return
    }
    const hours = Math.floor(diff / (1000 * 60 * 60))
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
    const seconds = Math.floor((diff % (1000 * 60)) / 1000)
    countdown.value = {
      hours: String(hours).padStart(2, '0'),
      minutes: String(minutes).padStart(2, '0'),
      seconds: String(seconds).padStart(2, '0')
    }
  }
  update()
  countdownInterval = setInterval(update, 1000)
}

const fetchTopSellingBooks = async () => {
  loadingTopSelling.value = true
  try {
    const response = await apiClient.get('/api/books/top-selling')
    topSellingBooks.value = response.data.data.slice(0, 5)
  } catch (error) {
    console.error('Lỗi tải sách bán chạy:', error)
  } finally {
    loadingTopSelling.value = false
  }
}

// ─── User Actions ───────────────────────────────────────────────────
const onPageChange = (event) => {
  first.value = event.first
  currentPage.value = event.page + 1
  fetchBooks()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const goToDetail = (slug) => {
  router.push({ name: 'book-detail', params: { slug } })
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const addToCart = (book) => {
  cartStore.addToCart(book, 1)
  toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm vào giỏ hàng!', life: 3000 })
}

const buyNow = (book) => {
  cartStore.addToCart(book, 1)
  router.push('/cart')
}

// ─── Init ───────────────────────────────────────────────────────────
onMounted(() => {
  fetchFlashSales()
  fetchTopSellingBooks()
  fetchBooks()
})
</script>

<style scoped>
/* ═══ FLASH SALE BANNER ═══ */
.flash-sale-banner {
  background: linear-gradient(90deg, #ba0035 0%, #e21e49 100%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: relative;
  z-index: 100;
}
.flash-icon-box {
  width: 32px; height: 32px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  color: white;
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 0.8; }
  100% { transform: scale(1); opacity: 1; }
}
.code-badge {
  background: white; color: #ba0035;
  padding: 2px 8px; border-radius: 4px;
  font-weight: 800; font-family: 'JetBrains Mono', monospace;
  margin: 0 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.timer-box {
  background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 8px; padding: 4px 8px; min-width: 44px;
  display: flex; flex-direction: column; align-items: center;
  backdrop-filter: blur(4px);
}
.timer-num { color: white; font-weight: 800; font-size: 15px; font-family: 'JetBrains Mono', monospace; line-height: 1; }
.timer-unit { color: rgba(255, 255, 255, 0.7); font-size: 8px; text-transform: uppercase; font-weight: 700; margin-top: 2px; }

/* ═══ Paginator override ═══ */
:deep(.p-paginator) { gap: 0.25rem; }
:deep(.p-paginator .p-paginator-page),
:deep(.p-paginator .p-paginator-prev),
:deep(.p-paginator .p-paginator-next) { border-radius: 0.5rem; font-size: 0.875rem; min-width: 36px; height: 36px; }
:deep(.p-paginator .p-paginator-page.p-highlight) { background-color: #002442; color: white; border-color: #002442; }

/* ═══ Animations ═══ */
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-100%); opacity: 0; }
</style>

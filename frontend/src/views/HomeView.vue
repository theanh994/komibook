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
              ⚡ Flash Sale: <span class="font-extrabold text-white text-base mr-1">{{ activeFlashSale.title }}</span> đang diễn ra! Giảm giá trực tiếp cực sâu trên từng đầu sách.
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
            <router-link to="/flash-sale" class="bg-white text-primary text-[11px] font-black uppercase tracking-widest px-4 py-2 rounded-xl shadow-lg hover:scale-105 active:scale-95 transition-all flex items-center gap-0.5 ml-2">
              Săn ngay <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            </router-link>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ═══ HERO SECTION ═══ -->
    <section class="w-full px-gutter mx-auto py-xxl">
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
        <router-link to="/catalog" class="font-inter text-3xl font-semibold text-primary tracking-tight hover:text-[#00b14f] transition-all duration-200 no-underline uppercase">
          SÁCH BÁN CHẠY
        </router-link>
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
            <img v-if="book.cover_image" :src="getCoverUrl(book.cover_image)" :alt="book.title" class="absolute inset-0 w-full h-full object-cover p-2 rounded-t-lg transition-transform duration-500 group-hover:scale-105" loading="lazy" />
            <div v-else class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-outline text-4xl">image</span></div>
            
            <!-- Wishlist Button -->
            <button 
              @click.stop="toggleWishlist(book.id)"
              class="absolute top-2.5 right-2.5 flex items-center justify-center transition-all hover:scale-120 active:scale-90 z-10 bg-transparent border-none cursor-pointer p-0"
              :class="wishlistStore.isFavorite(book.id) ? 'text-error' : 'text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.6)] hover:text-error'"
            >
              <span class="material-symbols-outlined text-[22px]" :class="wishlistStore.isFavorite(book.id) ? 'fill-1' : ''">favorite</span>
            </button>
            <!-- Sold Badge -->
            <div v-if="book.total_sold" class="absolute bottom-2 left-2 right-2 bg-inverse-surface/70 backdrop-blur-sm rounded-md py-1 px-2 text-center z-10">
              <span class="text-inverse-on-surface text-[11px] font-medium">Đã bán: {{ book.total_sold }}</span>
            </div>

            <!-- Cover Hover Buttons (Quick View, Add to Cart, Buy Now) -->
            <div class="absolute bottom-3 right-3 flex flex-col gap-2 z-20 opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
              <!-- Xem nhanh -->
              <button
                type="button"
                @click.stop="openQuickView(book)"
                title="Xem nhanh"
                class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
              >
                <span class="material-symbols-outlined text-[20px]">visibility</span>
              </button>
              <!-- Thêm vào giỏ hàng -->
              <button
                type="button"
                @click.stop="addToCart(book)"
                title="Thêm vào giỏ hàng"
                class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
              >
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
              </button>
              <!-- Mua ngay -->
              <button
                type="button"
                @click.stop="buyNow(book)"
                title="Mua ngay"
                class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
              >
                <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
              </button>
            </div>
          </div>
          <!-- Info -->
          <div class="p-md flex flex-col justify-between flex-grow">
            <h3 class="text-center text-sm font-medium text-on-surface line-clamp-2 leading-snug mb-1 group-hover:text-primary transition-colors">{{ book.title }}</h3>
            <div class="text-center text-sm font-bold text-[#00b14f] flex items-center justify-center gap-1.5 mt-auto">
              <span>{{ formatCurrency(book.sale_price || book.price) }}</span>
              <span v-if="book.sale_price && book.price > book.sale_price" class="text-xs text-outline line-through font-normal">{{ formatCurrency(book.price) }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ SÁCH MỚI NHẤT ═══ -->
    <section class="w-full px-gutter max-w-[1280px] mx-auto py-xl">
      <div class="flex justify-between items-end mb-lg">
        <router-link to="/catalog" class="font-inter text-3xl font-semibold text-primary tracking-tight hover:text-[#00b14f] transition-all duration-200 no-underline uppercase">
          SÁCH MỚI NHẤT
        </router-link>
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
              <img v-if="book.cover_image" :src="getCoverUrl(book.cover_image)" :alt="book.title" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" />
              <div v-else class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-outline text-4xl">image</span></div>
              
              <!-- Wishlist Button -->
              <button 
                @click.stop="toggleWishlist(book.id)"
                class="absolute top-2.5 right-2.5 flex items-center justify-center transition-all hover:scale-120 active:scale-90 z-10 bg-transparent border-none cursor-pointer p-0"
                :class="wishlistStore.isFavorite(book.id) ? 'text-error' : 'text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.6)] hover:text-error'"
              >
                <span class="material-symbols-outlined text-[22px]" :class="wishlistStore.isFavorite(book.id) ? 'fill-1' : ''">favorite</span>
              </button>
              <!-- Sale Badge -->
              <span
                v-if="book.sale_price && book.price > book.sale_price"
                class="absolute top-2 left-2 bg-secondary text-on-secondary text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm z-10"
              >-{{ Math.round((1 - book.sale_price / book.price) * 100) }}%</span>

              <!-- Cover Hover Buttons (Quick View, Add to Cart, Buy Now) -->
              <div class="absolute bottom-3 right-3 flex flex-col gap-2 z-20 opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                <!-- Xem nhanh -->
                <button
                  type="button"
                  @click.stop="openQuickView(book)"
                  title="Xem nhanh"
                  class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
                >
                  <span class="material-symbols-outlined text-[20px]">visibility</span>
                </button>
                <!-- Thêm vào giỏ hàng -->
                <button
                  type="button"
                  @click.stop="addToCart(book)"
                  title="Thêm vào giỏ hàng"
                  class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
                >
                  <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                </button>
                <!-- Mua ngay -->
                <button
                  type="button"
                  @click.stop="buyNow(book)"
                  title="Mua ngay"
                  class="w-9 h-9 rounded-full bg-[#00b14f] text-white flex items-center justify-center shadow-md hover:bg-[#009e46] hover:scale-115 active:scale-95 transition-all cursor-pointer border-none"
                >
                  <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
                </button>
              </div>
            </div>
            <!-- Info -->
            <div class="p-md flex flex-col justify-between flex-grow">
              <h3 class="text-center text-sm font-medium text-on-surface line-clamp-2 leading-snug mb-1 group-hover:text-primary transition-colors">{{ book.title }}</h3>
              <div class="text-center text-sm font-bold text-[#00b14f] flex items-center justify-center gap-1.5 mt-auto">
                <span>{{ formatCurrency(book.sale_price || book.price) }}</span>
                <span v-if="book.sale_price && book.price > book.sale_price" class="text-xs text-outline line-through font-normal">{{ formatCurrency(book.price) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ QUICK VIEW DIALOG ═══ -->
    <Dialog 
      v-model:visible="quickViewVisible" 
      :modal="true" 
      :show-header="false"
      class="!max-w-4xl !w-[95vw] !rounded-xl !border-2 !border-[#00b14f] !bg-white !shadow-2xl overflow-hidden relative"
      contentClass="!p-0"
    >
      <div v-if="quickViewBook" class="flex flex-col md:flex-row min-h-[500px]">
        <!-- Close Button -->
        <button 
          @click="quickViewVisible = false"
          class="absolute top-3 right-3 w-8 h-8 rounded-full border border-outline-variant/60 bg-white flex items-center justify-center cursor-pointer hover:border-[#00b14f] hover:scale-105 active:scale-95 transition-all z-50 text-xl font-bold text-gray-500"
          type="button"
        >
          <span class="material-symbols-outlined text-[20px] text-gray-700">close</span>
        </button>

        <!-- Left Column: Image and slider indicator -->
        <div class="w-full md:w-1/2 p-6 bg-surface-variant/10 flex flex-col items-center justify-center border-r border-outline-variant/30 relative">
          <!-- Sale Badge on image top-right -->
          <div class="relative w-full max-w-[280px] pt-[140%] shadow-lg rounded-lg overflow-hidden bg-white">
            <img 
              v-if="quickViewBook.cover_image" 
              :src="getCoverUrl(quickViewBook.cover_image)" 
              :alt="quickViewBook.title" 
              class="absolute inset-0 w-full h-full object-cover p-2" 
            />
            <div v-else class="absolute inset-0 flex items-center justify-center">
              <span class="material-symbols-outlined text-outline text-5xl">image</span>
            </div>
            
            <!-- Sale Percent Badge -->
            <span
              v-if="quickViewBook.sale_price && quickViewBook.price > quickViewBook.sale_price"
              class="absolute top-3 right-3 bg-secondary text-on-secondary text-xs font-black px-2.5 py-1 rounded-md shadow-md z-10"
            >
              -{{ Math.round((1 - quickViewBook.sale_price / quickViewBook.price) * 100) }}%
            </span>
          </div>
        </div>

        <!-- Right Column: Info & Details -->
        <div class="w-full md:w-1/2 p-6 flex flex-col justify-between">
          <div>
            <!-- Book Title -->
            <h2 class="text-xl md:text-2xl font-bold text-on-surface mb-2 leading-tight pr-6">{{ quickViewBook.title }}</h2>
            
            <!-- SKU -->
            <div class="text-xs text-outline mb-4">SKU: 978632{{ String(quickViewBook.id).padStart(7, '0') }}</div>

            <!-- Price -->
            <div class="flex items-center gap-3 mb-5">
              <span class="text-2xl font-extrabold text-[#00b14f]">{{ formatCurrency(quickViewBook.sale_price || quickViewBook.price) }}</span>
              <span v-if="quickViewBook.sale_price && quickViewBook.price > quickViewBook.sale_price" class="text-sm text-outline line-through">{{ formatCurrency(quickViewBook.price) }}</span>
            </div>

            <!-- Metadata table grid -->
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 border-t border-b border-outline-variant/40 py-4 mb-5 text-xs text-on-surface-variant">
              <div><strong>Tác giả:</strong> <span class="text-on-surface ml-1">{{ quickViewBook.author || 'Đang cập nhật' }}</span></div>
              <div><strong>Dịch giả:</strong> <span class="text-on-surface ml-1">Đang cập nhật</span></div>
              <div><strong>Nhà xuất bản:</strong> <span class="text-on-surface ml-1">{{ quickViewBook.vendor?.name || 'Đang cập nhật' }}</span></div>
              <div><strong>Năm xuất bản:</strong> <span class="text-on-surface ml-1">{{ quickViewBook.publish_year || '2026' }}</span></div>
              <div><strong>Hình thức:</strong> <span class="text-on-surface ml-1">Bìa mềm</span></div>
              <div><strong>Kích thước:</strong> <span class="text-on-surface ml-1">13 x 18 cm</span></div>
            </div>

            <!-- Description -->
            <div class="mb-5">
              <div class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1.5">Nội dung:</div>
              <p class="text-xs text-on-surface-variant leading-relaxed line-clamp-3">
                {{ cleanDescriptionText(quickViewBook.description) || 'Chưa có mô tả chi tiết cho cuốn sách này.' }}
              </p>
            </div>



            <!-- Quantity Selector & Actions -->
            <div class="flex items-center gap-3 mt-5">
              <div class="flex items-center border border-outline-variant/60 rounded-xl h-10 overflow-hidden bg-surface-container-lowest">
                <button 
                  type="button"
                  @click="decrementQty"
                  class="w-8 h-full flex items-center justify-center hover:bg-surface-variant transition-colors text-sm font-bold border-none"
                >-</button>
                <input 
                  type="number" 
                  v-model.number="quickViewQty" 
                  min="1" 
                  class="w-10 h-full text-center text-xs font-bold bg-transparent border-none focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                />
                <button 
                  type="button"
                  @click="incrementQty"
                  class="w-8 h-full flex items-center justify-center hover:bg-surface-variant transition-colors text-sm font-bold border-none"
                >+</button>
              </div>

              <!-- Xem Thêm -->
              <button 
                type="button"
                @click="goToDetail(quickViewBook.slug); quickViewVisible = false"
                class="flex-grow h-10 bg-[#00b14f] text-white rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-[#009e46] transition-all cursor-pointer border-none shadow-sm flex items-center justify-center"
              >
                Xem thêm
              </button>

              <!-- Thêm vào giỏ -->
              <button 
                type="button"
                @click="quickViewAddToCart"
                class="flex-grow h-10 bg-[#00b14f] text-white rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-[#009e46] transition-all cursor-pointer border-none shadow-sm flex items-center justify-center"
              >
                Thêm vào giỏ
              </button>
            </div>
          </div>

          <!-- Bottom Categories / Tags metadata -->
          <div class="mt-6 pt-4 border-t border-outline-variant/20 text-[10px] text-outline uppercase tracking-wider flex flex-col gap-1">
            <div><strong>Danh mục:</strong> {{ quickViewBook.category?.name || 'Sách mới' }}</div>
            <div><strong>Tags:</strong> {{ getBookTags(quickViewBook).join(', ') }}</div>
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'
import { useCartStore } from '@/stores/cart'
import { useToast } from 'primevue/usetoast'
import Skeleton from 'primevue/skeleton'
import Dialog from 'primevue/dialog'
import { useWishlistStore } from '@/stores/wishlist'

const router = useRouter()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()
const toast = useToast()

// ─── State ──────────────────────────────────────────────────────────
const activeFlashSale = ref(null)
const countdown = ref({ hours: '00', minutes: '00', seconds: '00' })
let countdownInterval = null

const books = ref([])
const loadingBooks = ref(false)

const topSellingBooks = ref([])
const loadingTopSelling = ref(false)

// Quick View State
const quickViewVisible = ref(false)
const quickViewBook = ref(null)
const quickViewVersion = ref('standard')
const quickViewQty = ref(1)

// ─── Fetch API ──────────────────────────────────────────────────────
const fetchBooks = async () => {
  loadingBooks.value = true
  try {
    const params = { per_page: 15 }
    const response = await apiClient.get('/api/books', { params })
    books.value = (response.data.data || response.data).slice(0, 15)
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
const cleanDescriptionText = (html) => {
  if (!html) return ''
  let text = html.replace(/<[^>]*>/g, '') // Xóa toàn bộ thẻ HTML
  return text.replace(/&nbsp;/g, ' ').replace(/\u00a0/g, ' ') // Thay thế khoảng trắng không ngắt
}

const getBookTags = (book) => {
  if (!book) return []
  const tags = []
  
  if (book.author && book.author !== 'Đang cập nhật' && book.author !== 'Nhiều Tác Giả') {
    tags.push(book.author)
  }
  if (book.category?.name) {
    tags.push(book.category.name)
  } else if (book.categories && book.categories.length > 0) {
    tags.push(book.categories[0].name)
  }
  if (book.type === 'ebook') {
    tags.push('E-book')
  } else {
    tags.push(book.cover_format || 'Sách giấy')
  }
  if (book.vendor?.name) {
    tags.push(book.vendor.name)
  }
  if (book.sale_price && book.price > book.sale_price) {
    tags.push('Khuyến mãi')
  }
  const releaseYear = parseInt(book.release_date)
  const currentYear = new Date().getFullYear()
  if (releaseYear && releaseYear >= currentYear - 1) {
    tags.push('Sách mới')
  }
  return [...new Set(tags)]
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

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

const toggleWishlist = async (bookId) => {
  try {
    const res = await wishlistStore.toggleWishlist(bookId)
    if (res.status === 'added') {
      toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Đã thêm vào danh sách yêu thích', life: 2000 })
    } else if (res.status === 'removed') {
      toast.add({ severity: 'info', summary: 'Đã bỏ', detail: 'Đã xóa khỏi danh sách yêu thích', life: 2000 })
    } else if (res.status === 'unauthorized') {
      toast.add({ severity: 'warn', summary: 'Thông báo', detail: 'Vui lòng đăng nhập để lưu yêu thích', life: 3000 })
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  }
}

// Quick View Actions
const openQuickView = (book) => {
  quickViewBook.value = book
  quickViewQty.value = 1
  quickViewVersion.value = 'standard'
  quickViewVisible.value = true
}

const decrementQty = () => {
  if (quickViewQty.value > 1) {
    quickViewQty.value--
  }
}

const incrementQty = () => {
  quickViewQty.value++
}

const quickViewAddToCart = () => {
  if (!quickViewBook.value) return
  cartStore.addToCart(quickViewBook.value, quickViewQty.value)
  toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã thêm ${quickViewQty.value} cuốn vào giỏ hàng!`, life: 3000 })
  quickViewVisible.value = false
}

// ─── Init ───────────────────────────────────────────────────────────
onMounted(() => {
  fetchFlashSales()
  fetchTopSellingBooks()
  fetchBooks()
  wishlistStore.fetchWishlistIds()
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

/* ═══ Animations ═══ */
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-100%); opacity: 0; }
</style>

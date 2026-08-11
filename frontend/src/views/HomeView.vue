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
            <router-link to="/flash-sale" class="min-h-11 bg-white text-primary text-[11px] font-black uppercase tracking-widest px-4 py-2 rounded-xl shadow-lg hover:scale-105 active:scale-95 transition-all flex items-center gap-0.5 ml-2">
              Săn ngay <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            </router-link>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ═══ HERO CAROUSEL ═══ -->
    <section class="relative w-full overflow-hidden" aria-labelledby="home-hero-title">
      <div
        class="relative isolate flex w-full items-center justify-center overflow-hidden h-[calc(93vh-140px)] min-h-[490px] max-h-[830px] group"
        aria-roledescription="carousel"
        aria-label="Sự kiện nổi bật"
      >
        <img
          v-if="activeHero.cover_image"
          :src="getCoverUrl(activeHero.cover_image)"
          :alt="activeHero.title"
          class="absolute inset-0 h-full w-full object-cover object-top transition-transform duration-700 group-hover:scale-[1.02]"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-slate-950/40 to-transparent"></div>

        <!-- Inner Content Grid -->
        <div class="relative z-10 mx-auto flex w-full max-w-[1280px] items-center px-4 md:px-gutter text-white h-full">
          <div class="max-w-3xl space-y-4">
            <!-- Eyebrow Tagline Text (Clean - NO background frame) -->
            <p class="text-xs sm:text-sm font-extrabold uppercase tracking-[0.22em] text-emerald-400 drop-shadow-xs">
              {{ activeHero.eyebrow || 'KOMIBOOK · KHÔNG GIAN ĐỌC DÀNH CHO BẠN' }}
            </p>

            <!-- Hero Main Title -->
            <h1 id="home-hero-title" class="font-inter text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black text-white tracking-tight leading-[1.08] drop-shadow-md">
              {{ activeHero.title }}
            </h1>

            <!-- Hero Subtitle / Excerpt -->
            <p class="font-inter text-base sm:text-lg md:text-xl text-slate-200 font-medium leading-relaxed max-w-2xl drop-shadow-xs pt-1">
              {{ activeHero.excerpt }}
            </p>

            <p v-if="heroError" class="pt-2 text-xs font-medium text-slate-300/80" role="status">
              Tin nổi bật chưa tải được; nội dung giới thiệu KomiBook đang được hiển thị.
            </p>
            <RouterLink
              :to="activeHero.to || '/catalog'"
              class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-secondary px-5 py-3 text-sm font-bold text-on-secondary no-underline shadow-lg transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim"
            >
              {{ activeHero.cta || 'Khám phá ngay' }}
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
            </RouterLink>
          </div>
        </div>

        <template v-if="heroSlides.length > 1">
          <button type="button" class="absolute left-4 top-1/2 z-20 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-slate-950/60 text-white backdrop-blur-md transition-all hover:bg-slate-950 hover:scale-110 border-none cursor-pointer" aria-label="Nội dung nổi bật trước" @click.stop="previousHero">
            <span class="material-symbols-outlined text-2xl" aria-hidden="true">chevron_left</span>
          </button>
          <button type="button" class="absolute right-4 top-1/2 z-20 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-slate-950/60 text-white backdrop-blur-md transition-all hover:bg-slate-950 hover:scale-110 border-none cursor-pointer" aria-label="Nội dung nổi bật tiếp theo" @click.stop="nextHero">
            <span class="material-symbols-outlined text-2xl" aria-hidden="true">chevron_right</span>
          </button>
          <div class="absolute bottom-6 right-6 z-20 flex gap-2" aria-label="Chọn nội dung nổi bật" @click.stop>
            <button
              v-for="(slide, index) in heroSlides"
              :key="slide.key"
              type="button"
              class="h-11 w-11 rounded-full border-0 bg-transparent p-3 cursor-pointer"
              :aria-label="`Hiển thị nội dung ${index + 1}: ${slide.title}`"
              :aria-current="currentHeroIndex === index ? 'true' : undefined"
              @click.stop="currentHeroIndex = index"
            >
              <span class="block h-2.5 w-2.5 rounded-full border border-white transition-all" :class="currentHeroIndex === index ? 'bg-white scale-125' : 'bg-white/30'"></span>
            </button>
          </div>
        </template>
      </div>
    </section>

    <section v-if="upcomingFlashSale" class="mx-auto w-full max-w-[1280px] px-4 py-8 md:px-gutter" aria-labelledby="upcoming-flash-title">
      <div class="overflow-hidden rounded-2xl border border-secondary/30 bg-gradient-to-r from-primary to-slate-800 p-5 text-white shadow-lg md:p-7">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
          <div>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-rose-200">Flash Sale sắp diễn ra</p>
            <h2 id="upcoming-flash-title" class="mt-2 text-2xl font-bold md:text-3xl">{{ upcomingFlashSale.title }}</h2>
            <p class="mt-2 text-sm text-slate-200">Bắt đầu lúc {{ formatFlashTime(upcomingFlashSale.start_time) }}. Tối đa 4 gian hàng được xếp theo lượt truy cập.</p>
            <div v-if="upcomingFlashSale.vendor_spotlights?.length" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <router-link v-for="vendor in upcomingFlashSale.vendor_spotlights" :key="vendor.id" :to="{ name: 'vendor-storefront', params: { slug: vendor.slug } }" class="flex min-h-16 items-center gap-3 rounded-xl bg-white/10 p-3 text-white no-underline transition-colors hover:bg-white/20">
                <img v-if="vendor.logo" :src="vendor.logo" :alt="`Logo ${vendor.shop_name}`" class="h-10 w-10 rounded-lg bg-white object-contain" />
                <span v-else class="material-symbols-outlined grid h-10 w-10 place-items-center rounded-lg bg-white/10" aria-hidden="true">storefront</span>
                <span class="min-w-0 truncate text-sm font-bold">{{ vendor.shop_name }}</span>
              </router-link>
            </div>
          </div>
          <div class="rounded-xl bg-white/10 p-4 text-center" aria-live="polite">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-200">Còn lại</p>
            <div class="mt-2 flex items-center justify-center gap-2 font-mono text-2xl font-black">
              <span>{{ upcomingCountdown.days }} ngày</span><span aria-hidden="true">:</span><span>{{ upcomingCountdown.hours }}</span><span aria-hidden="true">:</span><span>{{ upcomingCountdown.minutes }}</span><span aria-hidden="true">:</span><span>{{ upcomingCountdown.seconds }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ RECOMMENDATION ═══ -->
    <section class="mx-auto w-full max-w-[1280px] px-4 py-8 md:px-gutter md:py-xl" aria-labelledby="recommendation-title">
      <div class="relative mb-6 text-center">
        <h2 id="recommendation-title" class="text-2xl font-bold tracking-tight text-primary md:text-3xl">Gợi ý dành riêng cho bạn</h2>
        <router-link to="/catalog" class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm font-bold text-primary no-underline sm:absolute sm:right-0 sm:top-1/2 sm:mt-0 sm:-translate-y-1/2">
          Xem thêm <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
        </router-link>
      </div>

      <div v-if="loadingRecommendations" class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5" role="status" aria-label="Đang tải gợi ý sách">
        <div v-for="i in 5" :key="i" class="overflow-hidden rounded-xl border border-outline-variant/30 bg-surface-container-lowest">
          <Skeleton height="240px" borderRadius="0" />
          <div class="space-y-3 p-4"><Skeleton height="16px" /><Skeleton height="14px" width="65%" /></div>
        </div>
      </div>
      <div v-else-if="recommendationError" class="ui-state-panel" role="alert">
        <span class="material-symbols-outlined text-3xl text-error" aria-hidden="true">error</span>
        <p class="mt-2 font-bold">Chưa thể tải gợi ý sách</p>
        <button type="button" class="ui-button ui-button-secondary mt-4" @click="fetchRecommendations">Thử lại</button>
      </div>
      <div v-else-if="recommendations.length === 0" class="ui-state-panel">
        <span class="material-symbols-outlined text-3xl text-outline" aria-hidden="true">auto_stories</span>
        <p class="mt-2 font-bold">Chưa có sách phù hợp để gợi ý</p>
        <router-link to="/catalog" class="ui-button ui-button-secondary mt-4 no-underline">Khám phá danh mục</router-link>
      </div>
      <div v-else class="recommendation-grid">
        <div v-for="book in recommendations" :key="book.id" class="recommendation-item min-w-0">
          <BookCard
          :book="book"
          show-wishlist
          :is-favorite="wishlistStore.isFavorite(book.id)"
          @quick-view="openQuickView"
          @add-to-cart="addToCart"
          @buy-now="buyNow"
          @toggle-wishlist="toggleWishlist"
          />
        </div>
      </div>
    </section>

    <!-- ═══ NEWS & EDITORIAL FEED ═══ -->
    <section class="mx-auto w-full max-w-[1280px] px-4 py-8 md:px-gutter md:py-xl" aria-labelledby="vendor-feed-title">
      <div class="relative mb-6 text-center">
        <h2 id="vendor-feed-title" class="text-2xl font-bold tracking-tight text-primary md:text-3xl">Tin tức mới nhất</h2>
        <router-link to="/blog" class="mt-2 inline-flex min-h-11 shrink-0 items-center gap-1 text-sm font-bold text-primary no-underline sm:absolute sm:right-0 sm:top-1/2 sm:mt-0 sm:-translate-y-1/2">
          Xem tất cả <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
        </router-link>
      </div>
      <div v-if="loadingVendorFeed" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3" role="status" aria-label="Đang tải bản tin"><div v-for="i in 3" :key="i" class="overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container-lowest"><Skeleton height="180px" borderRadius="0" /><div class="space-y-3 p-5"><Skeleton height="16px" width="40%" /><Skeleton height="24px" /><Skeleton height="14px" /></div></div></div>
      <div v-else-if="vendorFeedError" class="ui-state-panel" role="alert"><p class="font-bold">Chưa thể tải tin tức</p><button type="button" class="ui-button ui-button-secondary mt-4" @click="fetchEditorialFeed">Thử lại</button></div>
      <div v-else-if="vendorArticles.length === 0" class="ui-state-panel"><p class="font-bold">Chưa có bản tin đã xuất bản</p></div>
      <div v-else class="grid items-stretch gap-6 md:grid-cols-2 lg:grid-cols-3">
        <article v-for="article in vendorArticles" :key="article.id" class="group flex flex-col overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container-lowest shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
          <router-link :to="`/blog/${article.slug}`" class="flex min-h-56 max-h-[380px] items-center justify-center overflow-hidden bg-surface-container-low no-underline">
            <img v-if="article.cover_image" :src="getCoverUrl(article.cover_image)" :alt="article.title" class="h-auto max-h-[380px] w-auto max-w-full object-contain transition-transform duration-300 group-hover:scale-105" loading="lazy" />
            <div v-else class="flex h-full w-full items-center justify-center bg-surface-container-low">
              <span class="material-symbols-outlined text-5xl text-on-surface-variant/40" aria-hidden="true">newspaper</span>
            </div>
          </router-link>
          <div class="flex flex-1 flex-col p-5">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-on-surface-variant">
              <span class="font-bold text-primary">{{ articlePublisher(article) }}</span>
              <span aria-hidden="true">•</span>
              <span>{{ article.category?.name || 'Bản tin' }}</span>
            </div>
            <h3 class="mt-2 text-lg font-bold leading-tight text-on-surface">
              <router-link :to="`/blog/${article.slug}`" class="text-inherit no-underline hover:text-primary transition-colors line-clamp-2">
                {{ article.title }}
              </router-link>
            </h3>
            <p v-if="article.excerpt" class="mt-2 line-clamp-3 text-sm leading-relaxed text-on-surface-variant">{{ article.excerpt }}</p>
            <div class="mt-auto pt-4">
              <router-link :to="`/blog/${article.slug}`" class="inline-flex min-h-11 items-center gap-1 text-sm font-bold text-primary no-underline transition-colors hover:text-primary/80">
                Đọc bài viết <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-1" aria-hidden="true">arrow_forward</span>
              </router-link>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- ═══ COMMERCE & CONTENT FEED ═══ -->
    <section class="mx-auto w-full max-w-[1280px] px-4 py-8 md:px-gutter md:py-xl" aria-labelledby="commerce-feed-title">
      <div class="mb-8 text-center">
        <h2 id="commerce-feed-title" class="text-2xl font-bold tracking-tight text-primary md:text-3xl">Sách nổi bật trên KomiBook</h2>
      </div>

      <div class="space-y-12">
        <section v-for="group in commerceGroups" :key="group.key" :aria-labelledby="`home-${group.key}`">
          <div class="relative mb-5 text-center">
            <h3 :id="`home-${group.key}`" class="text-xl font-bold text-on-surface md:text-2xl">{{ group.title }}</h3>
            <router-link :to="group.to" class="mt-2 inline-flex min-h-11 shrink-0 items-center gap-1 text-sm font-bold text-primary no-underline sm:absolute sm:right-0 sm:top-1/2 sm:mt-0 sm:-translate-y-1/2">
              Xem thêm <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
            </router-link>
          </div>

          <div v-if="commerceState[group.key].loading" class="recommendation-grid" role="status" :aria-label="`Đang tải ${group.title}`">
            <div v-for="i in 5" :key="i" class="recommendation-item min-w-0 overflow-hidden rounded-xl border border-outline-variant/30 bg-surface-container-lowest">
              <Skeleton height="220px" borderRadius="0" />
              <div class="space-y-3 p-4"><Skeleton height="16px" /><Skeleton height="60%" width="60%" /></div>
            </div>
          </div>
          <div v-else-if="commerceState[group.key].error" class="ui-state-panel" role="alert">
            <p class="font-bold">Chưa thể tải {{ group.title.toLowerCase() }}</p>
            <button type="button" class="ui-button ui-button-secondary mt-4" @click="fetchCommerceGroup(group)">Thử lại</button>
          </div>
          <div v-else-if="commerceState[group.key].items.length === 0" class="ui-state-panel">
            <p class="font-bold">Chưa có {{ group.title.toLowerCase() }}</p>
            <p class="mt-2 text-sm text-on-surface-variant">Nội dung sẽ xuất hiện khi có sách đủ điều kiện.</p>
          </div>
          <div v-else class="recommendation-grid">
            <div
              v-for="book in commerceState[group.key].items"
              :key="book.id"
              class="recommendation-item min-w-0"
            >
              <BookCard
                :book="book"
                show-wishlist
                :is-favorite="wishlistStore.isFavorite(book.id)"
                @quick-view="openQuickView"
                @add-to-cart="addToCart"
                @buy-now="buyNow"
                @toggle-wishlist="toggleWishlist"
              />
            </div>
          </div>
        </section>
      </div>
    </section>

    <!-- ═══ QUICK VIEW DIALOG ═══ -->
    <BookQuickViewDialog
      v-model:visible="quickViewVisible"
      :book="quickViewBook"
      @add-to-cart="addToCart"
      @buy-now="buyNow"
    />
  </div>
</template>

<script setup>
import { computed, ref, onBeforeUnmount, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'
import { readApiList } from '@/services/apiContract'
import { useCartStore } from '@/stores/cart'
import { useToast } from 'primevue/usetoast'
import Skeleton from 'primevue/skeleton'
import { useWishlistStore } from '@/stores/wishlist'
import BookCard from '@/components/BookCard.vue'
import BookQuickViewDialog from '@/components/BookQuickViewDialog.vue'
import bookshelfHero from '@/assets/komibook-bookshelf-hero.webp'

const router = useRouter()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()
const toast = useToast()

const heroArticles = ref([])
const heroError = ref(false)
const currentHeroIndex = ref(0)
const fallbackHero = {
  key: 'komibook-intro',
  eyebrow: 'KomiBook · Không gian đọc dành cho bạn',
  title: 'Khám phá thế giới sách',
  excerpt: 'Tìm ebook, sách giấy và những tác phẩm được tuyển chọn trong một trải nghiệm đọc rõ ràng, yên tĩnh.',
  cover_image: bookshelfHero,
  to: '/catalog',
  cta: 'Bắt đầu khám phá',
}
const heroSlides = computed(() => {
  if (heroArticles.value.length === 0) return [fallbackHero]

  return heroArticles.value.map((article) => ({
    key: `article-${article.id}`,
    eyebrow: article.category?.name || 'Bản tin KomiBook',
    title: article.title,
    excerpt: article.excerpt || 'Khám phá nội dung mới từ cộng đồng xuất bản KomiBook.',
    cover_image: article.cover_image,
    to: `/blog/${article.slug}`,
    cta: 'Đọc bài viết',
  }))
})
const activeHero = computed(() => heroSlides.value[currentHeroIndex.value] || heroSlides.value[0])

const recommendations = ref([])
const loadingRecommendations = ref(true)
const recommendationError = ref(false)
const recommendationExplanation = ref('Đang chuẩn bị gợi ý phù hợp…')
const vendorArticles = ref([])
const loadingVendorFeed = ref(true)
const vendorFeedError = ref(false)
const commerceGroups = [
  {
    key: 'bestselling-physical',
    title: 'Sách bán chạy',
    subtitle: 'Những đầu sách nổi bật trên toàn sàn.',
    params: { type: 'physical', sort: 'popular', per_page: 5 },
    to: { name: 'catalog', query: { type: 'physical', sort: 'popular' } },
  },
  {
    key: 'bestselling-ebook',
    title: 'Ebook bán chạy',
    subtitle: 'Nội dung số được độc giả lựa chọn nhiều.',
    params: { type: 'ebook', sort: 'popular', per_page: 5 },
    to: { name: 'catalog', query: { type: 'ebook', sort: 'popular' } },
  },
  {
    key: 'newest-physical',
    title: 'Sách mới nhất',
    subtitle: 'Các ấn phẩm sách mới lên kệ.',
    params: { type: 'physical', sort: 'newest', per_page: 5 },
    to: { name: 'catalog', query: { type: 'physical', sort: 'newest' } },
  },
  {
    key: 'newest-ebook',
    title: 'Ebook mới nhất',
    subtitle: 'Phiên bản số vừa được phát hành.',
    params: { type: 'ebook', sort: 'newest', per_page: 5 },
    to: { name: 'catalog', query: { type: 'ebook', sort: 'newest' } },
  },
  {
    key: 'used-books',
    title: 'Sách cũ giá tốt',
    subtitle: 'Sách đã qua sử dụng, được mô tả tình trạng rõ ràng.',
    params: { type: 'physical', provenance: 'used_resale', sort: 'price_asc', per_page: 5 },
    to: { name: 'catalog', query: { provenance: 'used_resale' } },
  },
]
const commerceState = ref(Object.fromEntries(
  commerceGroups.map((group) => [group.key, { items: [], loading: true, error: false }]),
))

// ─── State ──────────────────────────────────────────────────────────
const activeFlashSale = ref(null)
const upcomingFlashSale = ref(null)
const countdown = ref({ hours: '00', minutes: '00', seconds: '00' })
const upcomingCountdown = ref({ days: '00', hours: '00', minutes: '00', seconds: '00' })
let countdownInterval = null
let upcomingCountdownInterval = null

// Quick View State
const quickViewVisible = ref(false)
const quickViewBook = ref(null)

// ─── Fetch API ──────────────────────────────────────────────────────
const fetchEditorialFeed = async () => {
  heroError.value = false
  loadingVendorFeed.value = true
  vendorFeedError.value = false
  try {
    const response = await apiClient.get('/api/articles', {
      params: { per_page: 8 },
    })
    const articles = response.data?.data?.data || []
    heroArticles.value = articles.filter((article) => article.home_featured).slice(0, 5)
    vendorArticles.value = articles.slice(0, 6)
    currentHeroIndex.value = 0
  } catch {
    heroError.value = true
    vendorFeedError.value = true
    heroArticles.value = []
    vendorArticles.value = []
  } finally {
    loadingVendorFeed.value = false
  }
}

const fetchRecommendations = async () => {
  loadingRecommendations.value = true
  recommendationError.value = false
  try {
    const response = await apiClient.get('/api/books/recommendations')
    recommendations.value = readApiList(response.data).slice(0, 5)
    recommendationExplanation.value = response.data?.recommendation?.explanation || 'Sách nổi bật trên KomiBook'
  } catch {
    recommendationError.value = true
    recommendations.value = []
    recommendationExplanation.value = 'Gợi ý được tải độc lập với các nội dung khác.'
  } finally {
    loadingRecommendations.value = false
  }
}

const previousHero = () => {
  currentHeroIndex.value = (currentHeroIndex.value - 1 + heroSlides.value.length) % heroSlides.value.length
}

const nextHero = () => {
  currentHeroIndex.value = (currentHeroIndex.value + 1) % heroSlides.value.length
}

const articlePublisher = (article) => {
  return article.creator?.vendor?.shop_name
    || article.creator?.name
    || 'KomiBook'
}

const fetchCommerceGroup = async (group) => {
  const state = commerceState.value[group.key]
  state.loading = true
  state.error = false
  try {
    const response = await apiClient.get('/api/books', { params: group.params })
    state.items = readApiList(response.data).slice(0, 5)
  } catch {
    state.items = []
    state.error = true
  } finally {
    state.loading = false
  }
}

const fetchCommerceFeed = async () => {
  await Promise.all(commerceGroups.map((group) => fetchCommerceGroup(group)))
}

const fetchFlashSales = async () => {
  try {
    const res = await apiClient.get('/api/flash-sales')
    const flashSales = readApiList(res.data)
    const now = new Date()
    const active = flashSales.find(fs => {
      const start = new Date(fs.start_time)
      const end = new Date(fs.end_time)
      return fs.status === 'active' && start <= now && end > now
    })
    if (active) {
      activeFlashSale.value = active
      startCountdown(new Date(active.end_time))
    }
    const upcoming = flashSales.find((fs) => fs.status === 'enrollment_open' && new Date(fs.start_time) > now)
    upcomingFlashSale.value = upcoming || null
    if (upcoming) startUpcomingCountdown(new Date(upcoming.start_time))
  } catch (e) {
    console.error('Failed to fetch flash sales', e)
  }
}

const startUpcomingCountdown = (startTime) => {
  if (upcomingCountdownInterval) clearInterval(upcomingCountdownInterval)
  const update = () => {
    const diff = startTime - new Date()
    if (diff <= 0) {
      clearInterval(upcomingCountdownInterval)
      upcomingFlashSale.value = null
      fetchFlashSales()
      return
    }
    upcomingCountdown.value = {
      days: String(Math.floor(diff / 86400000)).padStart(2, '0'),
      hours: String(Math.floor((diff % 86400000) / 3600000)).padStart(2, '0'),
      minutes: String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0'),
      seconds: String(Math.floor((diff % 60000) / 1000)).padStart(2, '0'),
    }
  }
  update()
  upcomingCountdownInterval = setInterval(update, 1000)
}

const formatFlashTime = (value) => new Intl.DateTimeFormat('vi-VN', {
  hour: '2-digit',
  minute: '2-digit',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
}).format(new Date(value))

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

// ─── User Actions ───────────────────────────────────────────────────

const addToCart = (book, quantity = 1) => {
  cartStore.addToCart(book, quantity)
  toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm vào giỏ hàng!', life: 3000 })
}

const buyNow = (book, quantity = 1) => {
  cartStore.addToCart(book, quantity)
  router.push('/cart')
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  if (path.startsWith('/')) return path
  return `/storage/${path}`
}


const toggleWishlist = async (bookId) => {
  try {
    const res = await wishlistStore.toggleWishlist(bookId)
    if (res.state === 'added') {
      toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Đã thêm vào danh sách yêu thích', life: 2000 })
    } else if (res.state === 'removed') {
      toast.add({ severity: 'info', summary: 'Đã bỏ', detail: 'Đã xóa khỏi danh sách yêu thích', life: 2000 })
    } else if (res.state === 'unauthorized') {
      toast.add({ severity: 'warn', summary: 'Thông báo', detail: 'Vui lòng đăng nhập để lưu yêu thích', life: 3000 })
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  }
}

// Quick View Actions
const openQuickView = (book) => {
  quickViewBook.value = book
  quickViewVisible.value = true
}

// ─── Init ───────────────────────────────────────────────────────────
onMounted(() => {
  fetchEditorialFeed()
  fetchRecommendations()
  fetchCommerceFeed()
  fetchFlashSales()
  wishlistStore.fetchWishlistIds()
})

onBeforeUnmount(() => {
  if (countdownInterval) clearInterval(countdownInterval)
  if (upcomingCountdownInterval) clearInterval(upcomingCountdownInterval)
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

.recommendation-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.recommendation-item:nth-child(n + 3) { display: none; }

@media (min-width: 640px) {
  .recommendation-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .recommendation-item:nth-child(3) { display: block; }
}

@media (min-width: 768px) {
  .recommendation-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .recommendation-item:nth-child(4) { display: block; }
}

@media (min-width: 1024px) {
  .recommendation-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
  .recommendation-item:nth-child(5) { display: block; }
}
</style>

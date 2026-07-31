<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import BookCard from '@/components/BookCard.vue'
import apiClient from '@/services/axios'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()

const loading = ref(true)
const error = ref('')
const vendor = ref(null)
const books = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const following = ref(false)
const followAvailable = ref(true)
const followLoading = ref(false)

const canFollow = computed(() => (
  vendor.value
  && authStore.user?.vendor_profile?.id !== vendor.value.id
))

const loadStorefront = async (page = 1) => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get(`/api/vendors/${route.params.slug}`, { params: { page } })
    const data = response.data.data
    vendor.value = data.vendor
    books.value = data.books || []
    pagination.value = data.pagination || pagination.value
    apiClient.post(`/api/vendors/${data.vendor.id}/visit`).catch(() => {})
    if (authStore.isAuthenticated && canFollow.value) {
      const statusResponse = await apiClient.get(`/api/vendors/${data.vendor.id}/follow`)
      following.value = Boolean(statusResponse.data.following)
      followAvailable.value = statusResponse.data.available !== false
      vendor.value.followers_count = Number(statusResponse.data.followers_count || data.vendor.followers_count || 0)
    }
  } catch (requestError) {
    error.value = requestError.response?.status === 404
      ? 'Gian hàng không tồn tại hoặc chưa được phép hoạt động.'
      : 'Chưa thể tải gian hàng. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}

const toggleFollow = async () => {
  if (!authStore.isAuthenticated) {
    await router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  if (!vendor.value || followLoading.value || !canFollow.value) return
  followLoading.value = true
  try {
    const response = await apiClient.post(`/api/vendors/${vendor.value.id}/follow`)
    following.value = Boolean(response.data.following)
    vendor.value.followers_count = Number(response.data.followers_count || 0)
    toast.add({
      severity: 'success',
      summary: following.value ? 'Đã theo dõi gian hàng' : 'Đã bỏ theo dõi',
      detail: response.data.message,
      life: 3500,
    })
  } catch (requestError) {
    toast.add({
      severity: 'error',
      summary: 'Không thể cập nhật theo dõi',
      detail: requestError.response?.data?.message || 'Vui lòng thử lại.',
      life: 3500,
    })
  } finally {
    followLoading.value = false
  }
}

const addToCart = (book) => {
  const added = cartStore.addToCart(book)
  toast.add({
    severity: added ? 'success' : 'warn',
    summary: added ? 'Đã thêm vào giỏ' : 'Sách hiện không thể mua',
    detail: book.title,
    life: 2500,
  })
}

const buyNow = async (book) => {
  if (cartStore.addToCart(book)) await router.push('/cart')
}

const toggleWishlist = async (bookId) => {
  if (!authStore.isAuthenticated) {
    await router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  await wishlistStore.toggleWishlist(bookId)
}

onMounted(() => {
  loadStorefront()
  wishlistStore.fetchWishlistIds()
})

watch(() => route.params.slug, () => loadStorefront())
</script>

<template>
  <main class="min-h-screen bg-background px-4 py-8 md:px-8 md:py-10">
    <div class="mx-auto max-w-[1280px]">
      <p v-if="loading" class="rounded-2xl bg-surface-container p-8 text-center text-on-surface-variant" role="status">
        Đang tải gian hàng…
      </p>

      <section v-else-if="error" class="rounded-2xl border border-error/30 bg-error-container p-8 text-center text-on-error-container" role="alert">
        <h1 class="text-2xl font-bold">Chưa thể mở gian hàng</h1>
        <p class="mt-2">{{ error }}</p>
        <button type="button" class="mt-5 min-h-11 rounded-xl bg-primary px-5 font-bold text-on-primary" @click="loadStorefront()">
          Thử lại
        </button>
      </section>

      <template v-else-if="vendor">
        <section class="overflow-hidden rounded-3xl border border-outline-variant/30 bg-surface-container-lowest shadow-sm" aria-labelledby="storefront-title">
          <div class="h-28 bg-gradient-to-r from-primary via-primary-container to-secondary md:h-36"></div>
          <div class="-mt-12 flex flex-col gap-5 px-5 pb-6 md:-mt-14 md:flex-row md:items-end md:px-8">
            <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-4 border-surface bg-white shadow-md md:h-28 md:w-28">
              <img v-if="vendor.logo" :src="vendor.logo" :alt="`Logo ${vendor.shop_name}`" class="h-full w-full object-contain" />
              <span v-else class="material-symbols-outlined text-5xl text-primary" aria-hidden="true">storefront</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-bold uppercase tracking-wider text-secondary">Gian hàng đã xác minh</p>
              <h1 id="storefront-title" class="mt-1 text-3xl font-bold text-on-surface">{{ vendor.shop_name }}</h1>
              <p class="mt-2 max-w-3xl leading-7 text-on-surface-variant">{{ vendor.description || 'Gian hàng sách trên KomiBook.' }}</p>
              <div class="mt-3 flex flex-wrap gap-4 text-sm text-on-surface-variant">
                <span><strong class="text-on-surface">{{ vendor.followers_count }}</strong> người theo dõi</span>
                <span><strong class="text-on-surface">{{ vendor.views_count }}</strong> lượt ghé thăm</span>
                <span><strong class="text-on-surface">{{ pagination.total }}</strong> sách đang bán</span>
              </div>
            </div>
            <button
              v-if="canFollow"
              type="button"
              class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl px-5 font-bold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
              :class="following ? 'border border-primary bg-primary-container text-on-primary-container' : 'bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container'"
              :disabled="followLoading || !followAvailable"
              :aria-pressed="following"
              @click="toggleFollow"
            >
              <span class="material-symbols-outlined" aria-hidden="true">{{ following ? 'notifications_active' : 'add_alert' }}</span>
              {{ following ? 'Đang theo dõi' : 'Theo dõi gian hàng' }}
            </button>
          </div>
        </section>

        <section class="mt-10" aria-labelledby="store-books-title">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="text-sm font-bold uppercase tracking-wider text-secondary">Danh mục gian hàng</p>
              <h2 id="store-books-title" class="mt-1 text-2xl font-bold text-on-surface">Sách đang bán</h2>
            </div>
            <router-link :to="{ name: 'catalog', query: { vendor: vendor.slug } }" class="inline-flex min-h-11 items-center gap-1 font-bold text-primary no-underline">
              Xem trong danh mục
              <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
            </router-link>
          </div>

          <div v-if="books.length" class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            <BookCard
              v-for="book in books"
              :key="book.id"
              :book="book"
              show-wishlist
              :is-favorite="wishlistStore.isFavorite(book.id)"
              @quick-view="router.push({ name: 'book-detail', params: { slug: book.slug } })"
              @add-to-cart="addToCart"
              @buy-now="buyNow"
              @toggle-wishlist="toggleWishlist"
            />
          </div>
          <p v-else class="mt-5 rounded-2xl border border-dashed border-outline-variant p-10 text-center text-on-surface-variant">
            Gian hàng chưa có sách đang bán.
          </p>

          <nav v-if="pagination.last_page > 1" class="mt-8 flex items-center justify-center gap-3" aria-label="Phân trang gian hàng">
            <button type="button" class="min-h-11 rounded-xl border border-outline-variant px-4 font-bold disabled:opacity-40" :disabled="pagination.current_page <= 1" @click="loadStorefront(pagination.current_page - 1)">Trang trước</button>
            <span class="text-sm text-on-surface-variant">Trang {{ pagination.current_page }}/{{ pagination.last_page }}</span>
            <button type="button" class="min-h-11 rounded-xl border border-outline-variant px-4 font-bold disabled:opacity-40" :disabled="pagination.current_page >= pagination.last_page" @click="loadStorefront(pagination.current_page + 1)">Trang sau</button>
          </nav>
        </section>
      </template>
    </div>
  </main>
</template>

<template>
  <div class="min-h-screen bg-background">
    <div class="mx-auto flex w-full max-w-[1280px] flex-col items-stretch gap-xl px-gutter py-xl lg:flex-row">
      <UserSidebar :user="authStore.user" />

      <main class="flex min-w-0 flex-1 flex-col" aria-labelledby="wishlist-title">
        <section class="flex flex-1 flex-col overflow-hidden rounded-3xl border border-outline-variant/30 bg-surface-container-lowest soft-shadow">
          <header class="border-b border-outline-variant/10 p-lg md:p-xl">
            <h1 id="wishlist-title" class="mb-1 text-2xl font-black tracking-tight text-on-surface">
              Danh sách yêu thích
            </h1>
            <p class="text-sm font-medium text-on-surface-variant">
              Lưu những cuốn sách bạn quan tâm để quay lại nhanh hơn.
            </p>
          </header>

          <div class="p-lg md:p-xl">
            <div v-if="loading" class="flex flex-col items-center gap-4 py-20" role="status" aria-live="polite">
              <div class="h-12 w-12 animate-spin rounded-full border-4 border-primary/20 border-t-primary" aria-hidden="true"></div>
              <p class="text-sm font-bold text-on-surface-variant">Đang tải danh sách yêu thích...</p>
            </div>

            <div v-else-if="error" class="py-16 text-center" role="alert">
              <span class="material-symbols-outlined text-5xl text-error" aria-hidden="true">heart_broken</span>
              <h2 class="mt-3 text-xl font-bold text-on-surface">Không thể tải danh sách yêu thích</h2>
              <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-on-surface-variant">{{ error }}</p>
              <button
                type="button"
                class="mt-6 min-h-11 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-on-primary"
                @click="fetchWishlist"
              >
                Thử lại
              </button>
            </div>

            <div v-else-if="wishlist.length === 0" class="py-16 text-center">
              <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-3xl border border-outline-variant/20 bg-error-container/20 text-error">
                <span class="material-symbols-outlined text-4xl" aria-hidden="true">favorite</span>
              </div>
              <h2 class="mb-1 text-xl font-bold tracking-tight text-on-surface">Chưa có cuốn sách nào</h2>
              <p class="mx-auto mb-6 max-w-sm text-sm font-medium leading-relaxed text-on-surface-variant">
                Chọn biểu tượng trái tim trên thẻ sách để lưu lại tại đây.
              </p>
              <router-link
                to="/catalog"
                class="mx-auto inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-on-primary no-underline shadow-md"
              >
                Khám phá sách
                <span class="material-symbols-outlined text-xl" aria-hidden="true">arrow_forward</span>
              </router-link>
            </div>

            <div v-else class="grid grid-cols-1 gap-gutter sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
              <BookCard
                v-for="book in wishlist"
                :key="book.id"
                :book="book"
                show-wishlist
                :is-favorite="true"
                @toggle-wishlist="toggleWishlist"
                @quick-view="openBook"
                @add-to-cart="addToCart"
                @buy-now="buyNow"
              />
            </div>
          </div>
        </section>
      </main>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import { readApiList } from '@/services/apiContract'
import BookCard from '@/components/BookCard.vue'
import UserSidebar from '@/components/profile/UserSidebar.vue'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'

const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()

const wishlist = ref([])
const loading = ref(true)
const error = ref('')

const fetchWishlist = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/wishlist')
    wishlist.value = readApiList(response.data)
  } catch {
    wishlist.value = []
    error.value = 'Vui lòng kiểm tra kết nối và thử lại.'
  } finally {
    loading.value = false
  }
}

const toggleWishlist = async (bookId) => {
  try {
    await wishlistStore.toggleWishlist(bookId)
    wishlist.value = wishlist.value.filter(book => book.id !== bookId)
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: 'Đã bỏ sách khỏi danh sách yêu thích.', life: 2000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Có lỗi', detail: 'Chưa thể cập nhật danh sách yêu thích.', life: 3000 })
  }
}

const addToCart = (book) => {
  cartStore.addToCart(book)
  toast.add({ severity: 'success', summary: 'Đã thêm vào giỏ', detail: book.title, life: 2000 })
}

const buyNow = (book) => {
  cartStore.addToCart(book)
  router.push('/cart')
}

const openBook = (book) => {
  router.push({ name: 'book-detail', params: { slug: book.slug } })
}

onMounted(fetchWishlist)
</script>

<style scoped>
@media (prefers-reduced-motion: reduce) {
  .animate-spin {
    animation: none !important;
  }
}
</style>

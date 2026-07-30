<template>
  <div class="min-h-screen bg-background font-inter antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-30">
       <div class="absolute top-[-5%] left-[-5%] w-[400px] h-[400px] bg-primary/10 blur-[100px] rounded-full"></div>
       <div class="absolute bottom-[-5%] right-[-5%] w-[500px] h-[500px] bg-secondary/10 blur-[120px] rounded-full"></div>
    </div>

    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl relative z-10">
      <div class="flex flex-col lg:flex-row items-stretch gap-xl">
        <!-- Sidebar -->
        <UserSidebar :user="authStore.user" />

        <!-- Main Content -->
        <main class="flex-1 min-w-0 w-full flex flex-col" aria-labelledby="library-title">
          <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden flex-1 flex flex-col">
            <!-- Hero Header Section -->
            <div class="p-lg md:p-xl border-b border-outline-variant/10 bg-surface-container-low/20 space-y-4">
              <!-- Title & Subtitle -->
              <div>
                <h1 id="library-title" class="text-2xl font-black text-on-surface tracking-tight mb-1">Tủ sách cá nhân</h1>
                <p class="text-sm text-on-surface-variant font-medium">Các sách bạn đã sở hữu và quyền đọc ebook tương ứng.</p>
              </div>

              <!-- Moved Badges & Filters directly below Title/Subtitle -->
              <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-outline-variant/10">
                <!-- Badges -->
                <div class="flex items-center gap-2">
                  <span class="px-3 py-1 bg-surface-container-high text-on-surface text-xs font-bold rounded-xl border border-outline-variant/20">
                    {{ ebookCount }} E-Books
                  </span>
                  <span class="px-3 py-1 bg-surface-container-high text-on-surface text-xs font-bold rounded-xl border border-outline-variant/20">
                    {{ physicalCount }} Sách Giấy
                  </span>
                </div>
                <!-- Filters -->
                <div class="flex max-w-full gap-1 overflow-x-auto p-1 bg-surface-container-low rounded-xl border border-outline-variant/20" role="group" aria-label="Lọc Tủ sách theo định dạng">
                  <button                    v-for="type in typeFilters"                    :key="type.value"
                    type="button"
                    :aria-pressed="currentType === type.value"
                    @click="currentType = type.value"
                    class="min-h-11 px-3.5 py-2 rounded-lg text-sm font-bold transition-colors border-none cursor-pointer whitespace-nowrap"
                    :class="currentType === type.value ? 'bg-primary text-on-primary shadow-xs' : 'text-outline hover:text-on-surface'"
                  >
                    {{ type.label }}
                  </button>
                </div>
              </div>
            </div>

            <div class="p-lg md:p-xl flex-grow flex flex-col justify-center">
              <!-- Loading -->
              <div v-if="loading" class="py-16 flex flex-col items-center gap-4 animate-fade-in" role="status" aria-live="polite">
                <div class="w-10 h-10 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></div>
                <p class="text-sm font-bold text-on-surface-variant">Đang đồng bộ Tủ sách...</p>
              </div>

              <div v-else-if="error" class="py-14 text-center" role="alert">
                <span class="material-symbols-outlined text-5xl text-error" aria-hidden="true">library_books</span>
                <h2 class="mt-3 text-xl font-bold text-on-surface">Không thể tải Tủ sách</h2>
                <p class="mx-auto mt-2 max-w-sm text-sm text-on-surface-variant">{{ error }}</p>
                <button type="button" class="mt-5 min-h-11 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-on-primary" @click="fetchMyLibrary">Thử lại</button>
              </div>

              <!-- Compact Empty State (Aligned with Left Sidebar height) -->
              <div v-else-if="filteredItems.length === 0" class="py-8 text-center animate-fade-in flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-surface-container-high rounded-2xl flex items-center justify-center mx-auto mb-3 text-outline/40 border border-outline-variant/10">
                  <span class="material-symbols-outlined text-3xl">local_library</span>
                </div>
                <h2 class="text-lg font-bold text-on-surface mb-1 tracking-tight">{{ libraryItems.length ? 'Không có sách phù hợp bộ lọc' : 'Tủ sách còn trống' }}</h2>
                <p class="text-sm text-on-surface-variant mb-5 max-w-sm mx-auto font-medium">
                  {{ libraryItems.length ? 'Hãy chọn định dạng khác để xem sách đã sở hữu.' : 'Khám phá danh mục để sở hữu tác phẩm đầu tiên.' }}
                </p>
                <button v-if="libraryItems.length === 0" type="button" @click="$router.push('/catalog')" class="min-h-11 bg-primary text-on-primary px-5 py-2.5 rounded-xl font-bold text-sm shadow-md hover:bg-primary/90 transition-colors border-none cursor-pointer flex items-center gap-2">
                  <span>Khám phá KomiBook</span>
                  <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </button>
              </div>

              <!-- Premium Library Grid -->
              <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-8">
                <div v-for="(item, index) in filteredItems" :key="item.book.id" class="group animate-slide-up" :style="{ animationDelay: (index * 50) + 'ms' }">
                  <div class="relative aspect-[2/3] overflow-hidden shadow-sm group-hover:shadow-md transition-shadow duration-300 border border-outline-variant/10 mb-3 bg-surface-container-low">
                    <img v-if="item.book.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="`Bìa sách ${item.book.title}`" class="w-full h-full object-contain p-2" />
                    <div v-else class="w-full h-full flex items-center justify-center text-outline/20">
                      <span class="material-symbols-outlined text-5xl">auto_stories</span>
                    </div>
                    <!-- Wishlist Float -->
                    <div class="absolute top-2.5 right-2.5 z-30">
                       <button type="button" @click.stop="toggleWishlist(item.book.id)" class="w-11 h-11 rounded-xl bg-black/60 backdrop-blur-md border border-white/20 flex items-center justify-center text-white transition-colors group/heart shadow-md"
                         :aria-label="wishlistStore.isFavorite(item.book.id) ? `Bỏ ${item.book.title} khỏi yêu thích` : `Thêm ${item.book.title} vào yêu thích`"
                         :aria-pressed="wishlistStore.isFavorite(item.book.id)"
                       >
                          <span                            class="material-symbols-outlined text-base transition-all duration-300"
                            :class="wishlistStore.isFavorite(item.book.id) ? 'text-error fill-1 scale-110' : 'group-hover/heart:text-error'"
                          >
                            favorite
                          </span>
                       </button>
                    </div>

                    <!-- Type Indicator Float -->
                    <div class="absolute top-2.5 left-2.5 z-10">
                       <div class="w-7 h-7 bg-black/40 backdrop-blur-md rounded-lg border border-white/10 flex items-center justify-center text-white/90 shadow-md">
                          <span class="material-symbols-outlined text-sm">{{ item.book.type === 'ebook' ? 'bolt' : 'menu_book' }}</span>
                       </div>
                    </div>

                    <!-- Refined Progress Line (Shown only when real progress data exists) -->
                    <div v-if="item.book.type === 'ebook' && item.reading_progress != null" class="absolute bottom-0 left-0 right-0 h-1 bg-black/20 z-10 overflow-hidden">
                       <div class="h-full bg-primary transition-all duration-500" :style="{ width: item.reading_progress + '%' }"></div>
                    </div>

                    <!-- Hover Quick Actions -->
                    <div class="library-actions absolute inset-0 bg-gradient-to-t from-primary/95 via-primary/50 to-transparent transition-opacity duration-300 flex flex-col items-center justify-center p-4 gap-2.5 z-20">
                      <button
                        v-if="item.book.type === 'ebook' && item.has_access && item.order_id"
                        @click="readEbook(item.order_id, item.book.id)"
                        class="min-h-11 w-full bg-white text-primary py-2.5 rounded-xl font-bold text-sm shadow-md transition-colors flex items-center justify-center gap-2 border-none cursor-pointer"
                      >
                        <span class="material-symbols-outlined text-lg fill-1">auto_stories</span>
                        Đọc Ngay
                      </button>
                      <button
                        v-if="item.book.type === 'physical'"
                        @click="$router.push(`/tracking/${item.order_id}`)"
                        class="min-h-11 w-full bg-white text-on-surface py-2.5 rounded-xl font-bold text-sm shadow-md transition-colors flex items-center justify-center gap-2 border-none cursor-pointer"
                      >
                        <span class="material-symbols-outlined text-lg">local_shipping</span>
                        Theo dõi
                      </button>
                      <button
                        @click="$router.push(`/book/${item.book.slug || item.book.id}`)"
                        class="min-h-11 w-full bg-surface-container-lowest/10 backdrop-blur-xl text-white border border-white/30 py-2.5 rounded-xl font-bold text-sm hover:bg-white/20 transition-colors flex items-center justify-center gap-2 cursor-pointer"
                      >
                        Thông tin
                      </button>
                    </div>
                  </div>
                  <div class="px-1 space-y-1 text-left">
                    <h4 class="text-sm font-bold text-on-surface leading-tight line-clamp-1 group-hover:text-primary transition-colors tracking-tight">{{ item.book.title }}</h4>
                    <p class="text-sm text-outline font-medium truncate">{{ item.book.author || 'Đang cập nhật tác giả' }}</p>
                    <div v-if="item.book.type === 'ebook' && item.purchase_version" class="pt-2 text-xs text-outline">
                      <span class="font-bold">Đã mua: phiên bản {{ item.purchase_version }}</span>
                      <span v-if="item.latest_version && item.latest_version !== item.purchase_version"> · Mới nhất: phiên bản {{ item.latest_version }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useWishlistStore } from '@/stores/wishlist'
import apiClient from '@/services/axios'
import { readApiList } from '@/services/apiContract'
import UserSidebar from '@/components/profile/UserSidebar.vue'

const router = useRouter()
const authStore = useAuthStore()
const wishlistStore = useWishlistStore()

const loading = ref(true)
const libraryItems = ref([])
const currentType = ref('all')
const error = ref('')

const typeFilters = [
  { label: 'Tất cả', value: 'all' },
  { label: 'E-book', value: 'ebook' },
  { label: 'Sách giấy', value: 'physical' }
]

const fetchMyLibrary = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await apiClient.get('/api/my-library')
    libraryItems.value = readApiList(res.data)
  } catch (requestError) {
    console.error('Failed to fetch my library:', requestError)
    libraryItems.value = []
    error.value = 'Vui lòng kiểm tra kết nối và thử lại.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchMyLibrary()
  if (typeof wishlistStore.fetchWishlistIds === 'function') {
    wishlistStore.fetchWishlistIds()
  }
})

const ebookCount = computed(() => {
  return libraryItems.value.filter(item => item.book?.type === 'ebook').length
})

const physicalCount = computed(() => {
  return libraryItems.value.filter(item => item.book?.type === 'physical').length
})

const filteredItems = computed(() => {
  if (currentType.value === 'all') return libraryItems.value
  return libraryItems.value.filter(item => item.book?.type === currentType.value)
})

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http') || path.startsWith('/storage/')) return path
  return `/storage/${path}`
}

const toggleWishlist = async (bookId) => {
  await wishlistStore.toggleWishlist(bookId)
}

const readEbook = (orderId, bookId) => {
  if (orderId && bookId) {
    router.push({ name: 'ebook-reader', params: { orderId, bookId } })
  }
}
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes slide-up {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in { animation: fade-in 0.4s ease-out forwards; }
.animate-slide-up { animation: slide-up 0.5s ease-out forwards; }

.library-actions {
  opacity: 1;
  pointer-events: auto;
}

@media (min-width: 640px) and (hover: hover) and (pointer: fine) {
  .library-actions {
    opacity: 0;
    pointer-events: none;
  }

  .group:hover .library-actions,
  .group:focus-within .library-actions {
    opacity: 1;
    pointer-events: auto;
  }
}

@media (prefers-reduced-motion: reduce) {
  .animate-fade-in,
  .animate-slide-up,
  .animate-spin,
  .animate-pulse {
    animation: none !important;
  }
}
</style>

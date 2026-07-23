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
        <main class="flex-1 min-w-0 w-full flex flex-col">
          <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden flex-1 flex flex-col">
            <!-- Hero Header Section -->
            <div class="p-lg md:p-xl border-b border-outline-variant/10 bg-surface-container-low/20 space-y-4">
              <!-- Title & Subtitle -->
              <div>
                <h1 class="text-2xl font-black text-on-surface tracking-tight mb-1">Tủ sách cá nhân</h1>
                <p class="text-xs text-on-surface-variant font-medium">Kho tri thức tinh hoa bạn đã sưu tầm. Tiếp tục hành trình khám phá những trang sách của bạn.</p>
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
                <div class="flex p-1 bg-surface-container-low rounded-xl border border-outline-variant/20">
                  <button                    v-for="type in typeFilters"                    :key="type.value"
                    @click="currentType = type.value"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all border-none cursor-pointer whitespace-nowrap"
                    :class="currentType === type.value ? 'bg-primary text-on-primary shadow-xs' : 'text-outline hover:text-on-surface'"
                  >
                    {{ type.label }}
                  </button>
                </div>
              </div>
            </div>

            <div class="p-lg md:p-xl flex-grow flex flex-col justify-center">
              <!-- Loading -->
              <div v-if="loading" class="py-16 flex flex-col items-center gap-4 animate-fade-in">
                <div class="w-10 h-10 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></div>
                <p class="text-xs font-bold text-outline animate-pulse">Đang đồng bộ thư viện...</p>
              </div>

              <!-- Compact Empty State (Aligned with Left Sidebar height) -->
              <div v-else-if="filteredItems.length === 0" class="py-8 text-center animate-fade-in flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-surface-container-high rounded-2xl flex items-center justify-center mx-auto mb-3 text-outline/40 border border-outline-variant/10">
                  <span class="material-symbols-outlined text-3xl">local_library</span>
                </div>
                <h3 class="text-base font-bold text-on-surface mb-1 tracking-tight">Kệ sách còn đang trống</h3>
                <p class="text-xs text-on-surface-variant mb-5 max-w-xs mx-auto font-medium">
                  Hãy bắt đầu hành trình bằng việc sở hữu những tác phẩm đầu tiên.
                </p>
                <button @click="$router.push('/catalog')" class="bg-primary text-on-primary px-5 py-2.5 rounded-xl font-bold text-xs shadow-md hover:bg-primary/90 active:scale-95 transition-all border-none cursor-pointer flex items-center gap-2">
                  <span>Khám phá KomiBook</span>
                  <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </button>
              </div>

              <!-- Premium Library Grid -->
              <div v-else class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-8">
                <div v-for="(item, index) in filteredItems" :key="item.book.id" class="group animate-slide-up" :style="{ animationDelay: (index * 50) + 'ms' }">
                  <div class="relative aspect-[3/4.5] rounded-2xl overflow-hidden shadow-sm group-hover:shadow-md group-hover:-translate-y-1 transition-all duration-300 border border-outline-variant/10 mb-3 bg-surface-container-low">
                    <img v-if="item.book.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="item.book.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                    <div v-else class="w-full h-full flex items-center justify-center text-outline/20">
                      <span class="material-symbols-outlined text-5xl">auto_stories</span>
                    </div>
                    <!-- Wishlist Float -->
                    <div class="absolute top-2.5 right-2.5 z-30">
                       <button                         @click.stop="toggleWishlist(item.book.id)"                         class="w-8 h-8 rounded-xl bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white hover:scale-110 active:scale-95 transition-all group/heart shadow-md"
                         :title="wishlistStore.isFavorite(item.book.id) ? 'Xóa khỏi yêu thích' : 'Thêm vào yêu thích'"
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

                    <!-- Refined Progress Line -->
                    <div v-if="item.book.type === 'ebook'" class="absolute bottom-0 left-0 right-0 h-1 bg-black/20 z-10 overflow-hidden">
                       <div class="h-full bg-primary transition-all duration-500" :style="{ width: readingProgress + '%' }"></div>
                    </div>

                    <!-- Hover Quick Actions -->
                    <div class="absolute inset-0 bg-gradient-to-t from-primary/90 via-primary/40 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-300 flex flex-col items-center justify-center p-4 gap-2.5 z-20">
                      <button                        v-if="item.book.type === 'ebook'"
                        @click="readEbook(item.order_id, item.book.id)"
                        class="w-full bg-white text-primary py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider shadow-md active:scale-95 transition-all flex items-center justify-center gap-2 border-none cursor-pointer"
                      >
                        <span class="material-symbols-outlined text-lg fill-1">auto_stories</span>
                        Đọc Ngay
                      </button>
                      <button                        v-if="item.book.type === 'physical'"
                        @click="$router.push(`/tracking/${item.order_id}`)"
                        class="w-full bg-white text-on-surface py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider shadow-md active:scale-95 transition-all flex items-center justify-center gap-2 border-none cursor-pointer"
                      >
                        <span class="material-symbols-outlined text-lg">local_shipping</span>
                        Theo dõi
                      </button>
                      <button                        @click="$router.push(`/book/${item.book.slug || item.book.id}`)"
                        class="w-full bg-surface-container-lowest/10 backdrop-blur-xl text-white border border-white/30 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider hover:bg-white/20 transition-all flex items-center justify-center gap-2 cursor-pointer"
                      >
                        Thông tin
                      </button>
                    </div>
                  </div>
                  <div class="px-1 space-y-1 text-left">
                    <h4 class="text-sm font-bold text-on-surface leading-tight line-clamp-1 group-hover:text-primary transition-colors tracking-tight">{{ item.book.title }}</h4>
                    <p class="text-[11px] text-outline font-medium truncate">{{ item.book.author || 'KomiBook Author' }}</p>
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
import UserSidebar from '@/components/profile/UserSidebar.vue'

const router = useRouter()
const authStore = useAuthStore()
const wishlistStore = useWishlistStore()

const loading = ref(true)
const libraryItems = ref([])
const currentType = ref('all')
const readingProgress = ref(45)

const typeFilters = [
  { label: 'Tất cả', value: 'all' },
  { label: 'E-book', value: 'ebook' },
  { label: 'Sách giấy', value: 'physical' }
]

const fetchMyLibrary = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/my-library')
    libraryItems.value = res.data.data || res.data || []
  } catch (error) {
    console.error('Failed to fetch my library:', error)
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
  router.push(`/read/${orderId}/${bookId}`)
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
</style>

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
              <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                  <h1 id="library-title" class="text-2xl font-black text-on-surface tracking-tight mb-1">Tủ sách cá nhân</h1>
                  <p class="text-sm text-on-surface-variant font-medium">Các sách bạn đã sở hữu và quyền đọc ebook tương ứng.</p>
                </div>
                
                <!-- View Mode Switcher -->
                <div class="flex items-center gap-1 bg-surface-container-low p-1 rounded-xl border border-outline-variant/20 shrink-0 self-start md:self-auto">
                  <button 
                    type="button" 
                    @click="viewMode = 'grid'" 
                    :class="['w-9 h-9 rounded-lg flex items-center justify-center transition-colors border-none cursor-pointer', viewMode === 'grid' ? 'bg-primary text-on-primary shadow-xs' : 'text-outline hover:text-on-surface']"
                    title="Hiển thị dạng Lưới"
                    aria-label="Chuyển sang dạng Lưới"
                  >
                    <span class="material-symbols-outlined text-[20px]">grid_view</span>
                  </button>
                  <button 
                    type="button" 
                    @click="viewMode = 'list'" 
                    :class="['w-9 h-9 rounded-lg flex items-center justify-center transition-colors border-none cursor-pointer', viewMode === 'list' ? 'bg-primary text-on-primary shadow-xs' : 'text-outline hover:text-on-surface']"
                    title="Hiển thị dạng Danh sách"
                    aria-label="Chuyển sang dạng Danh sách"
                  >
                    <span class="material-symbols-outlined text-[20px]">view_list</span>
                  </button>
                </div>
              </div>

              <!-- Search & Controls Bar -->
              <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 pt-2">
                <!-- Search Box -->
                <div class="sm:col-span-6 md:col-span-7 relative">
                  <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
                  <input 
                    v-model="searchQuery" 
                    type="text" 
                    placeholder="Tìm tên sách hoặc tác giả..." 
                    class="w-full h-11 pl-10 pr-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm font-medium text-on-surface placeholder:text-outline/60 focus:outline-none focus:border-primary/50 transition-colors"
                  />
                  <button 
                    v-if="searchQuery" 
                    @click="searchQuery = ''" 
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface border-none bg-transparent cursor-pointer flex items-center justify-center"
                  >
                    <span class="material-symbols-outlined text-[18px]">cancel</span>
                  </button>
                </div>

                <!-- Sort Dropdown -->
                <div class="sm:col-span-6 md:col-span-5 relative">
                  <select 
                    v-model="sortBy" 
                    class="w-full h-11 px-4 pr-10 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm font-bold text-on-surface focus:outline-none focus:border-primary/50 appearance-none cursor-pointer"
                  >
                    <option value="purchased_desc">📅 Ngày mua: Mới nhất</option>
                    <option value="purchased_asc">📅 Ngày mua: Cũ nhất</option>
                    <option value="title_asc">🔤 Tên sách: A ➔ Z</option>
                    <option value="title_desc">🔤 Tên sách: Z ➔ A</option>
                  </select>
                  <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-outline pointer-events-none text-[20px]">unfold_more</span>
                </div>
              </div>

              <!-- Badges & Format Filters -->
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
                <!-- Format Filters -->
                <div class="flex max-w-full gap-1 overflow-x-auto p-1 bg-surface-container-low rounded-xl border border-outline-variant/20" role="group" aria-label="Lọc Tủ sách theo định dạng">
                  <button 
                    v-for="type in typeFilters" 
                    :key="type.value"
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

              <!-- Compact Empty State -->
              <div v-else-if="filteredItems.length === 0" class="py-12 text-center animate-fade-in flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-surface-container-high rounded-2xl flex items-center justify-center mx-auto mb-3 text-outline/40 border border-outline-variant/10">
                  <span class="material-symbols-outlined text-3xl">local_library</span>
                </div>
                <h2 class="text-lg font-bold text-on-surface mb-1 tracking-tight">
                  {{ libraryItems.length ? 'Không tìm thấy sách phù hợp' : 'Tủ sách còn trống' }}
                </h2>
                <p class="text-sm text-on-surface-variant mb-5 max-w-sm mx-auto font-medium">
                  {{ libraryItems.length ? 'Hãy thử thay đổi từ khóa tìm kiếm hoặc bộ lọc định dạng.' : 'Khám phá danh mục để sở hữu tác phẩm đầu tiên.' }}
                </p>
                <button v-if="libraryItems.length === 0" type="button" @click="$router.push('/catalog')" class="min-h-11 bg-primary text-on-primary px-5 py-2.5 rounded-xl font-bold text-sm shadow-md hover:bg-primary/90 transition-colors border-none cursor-pointer flex items-center gap-2">
                  <span>Khám phá KomiBook</span>
                  <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </button>
                <button v-else type="button" @click="resetFilters" class="min-h-11 bg-surface-container-high text-on-surface px-5 py-2.5 rounded-xl font-bold text-sm border border-outline-variant/20 hover:bg-surface-container-highest transition-colors border-none cursor-pointer">
                  Xóa tìm kiếm / bộ lọc
                </button>
              </div>

              <!-- Premium Library: GRID VIEW -->
              <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-8">
                <div v-for="(item, index) in filteredItems" :key="item.book.id" class="group animate-slide-up" :style="{ animationDelay: (index * 40) + 'ms' }">
                  <div class="relative aspect-[2/3] overflow-hidden shadow-sm group-hover:shadow-md transition-shadow duration-300 border border-outline-variant/10 mb-3 bg-surface-container-low">
                    <img v-if="item.book.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="`Bìa sách ${item.book.title}`" class="w-full h-full object-contain p-2" />
                    <div v-else class="w-full h-full flex items-center justify-center text-outline/20">
                      <span class="material-symbols-outlined text-5xl">auto_stories</span>
                    </div>

                    <!-- Wishlist Float -->
                    <div class="absolute top-2.5 right-2.5 z-30">
                       <button type="button" @click.stop="toggleWishlist(item.book.id)" class="w-11 h-11 rounded-xl bg-black/60 backdrop-blur-md border border-white/20 flex items-center justify-center text-white transition-colors group/heart shadow-md cursor-pointer"
                         :aria-label="wishlistStore.isFavorite(item.book.id) ? `Bỏ ${item.book.title} khỏi yêu thích` : `Thêm ${item.book.title} vào yêu thích`"
                         :aria-pressed="wishlistStore.isFavorite(item.book.id)"
                       >
                          <span 
                            class="material-symbols-outlined text-base transition-all duration-300"
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
                        @click="$router.push(`/book/${item.book.slug || item.book.id}#reviews`)"
                        class="min-h-11 w-full bg-white text-primary hover:bg-primary hover:text-on-primary py-2.5 rounded-xl font-bold text-sm shadow-md transition-all flex items-center justify-center gap-2 border-none cursor-pointer"
                      >
                        <span class="material-symbols-outlined text-lg">rate_review</span>
                        Đánh giá
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
                    <p class="text-xs text-outline font-medium truncate">{{ item.book.author || 'Chưa cập nhật tác giả' }}</p>
                    <div class="flex items-center justify-between text-[11px] text-outline/80 pt-1">
                      <span>{{ formatDate(item.purchased_at) }}</span>
                      <span v-if="item.book.type === 'ebook' && item.purchase_version" class="font-bold">v{{ item.purchase_version }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Premium Library: LIST VIEW -->
              <div v-else-if="viewMode === 'list'" class="space-y-4">
                <div 
                  v-for="(item, index) in filteredItems" 
                  :key="item.book.id" 
                  class="bg-surface-container-low/30 rounded-2xl p-4 border border-outline-variant/20 hover:border-primary/30 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-slide-up"
                  :style="{ animationDelay: (index * 30) + 'ms' }"
                >
                  <div class="flex items-center gap-4 min-w-0">
                    <div class="w-16 h-24 overflow-hidden shrink-0 shadow-sm border border-outline-variant/10 rounded-xl bg-surface-container-low relative">
                      <img v-if="item.book.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="`Bìa sách ${item.book.title}`" class="w-full h-full object-contain p-1" />
                      <div v-else class="w-full h-full flex items-center justify-center text-outline/30">
                        <span class="material-symbols-outlined text-2xl">book</span>
                      </div>
                    </div>

                    <div class="min-w-0 space-y-1">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span 
                          class="px-2 py-0.5 rounded text-[10px] font-black tracking-wide border"
                          :class="item.book.type === 'ebook' ? 'bg-primary/10 text-primary border-primary/20' : 'bg-secondary/10 text-secondary border-secondary/20'"
                        >
                          {{ item.book.type === 'ebook' ? 'E-book' : 'Sách giấy' }}
                        </span>
                        <span class="text-xs text-outline font-medium">Sở hữu ngày: {{ formatDate(item.purchased_at) }}</span>
                      </div>
                      <h4 class="text-base font-bold text-on-surface leading-snug truncate hover:text-primary transition-colors">{{ item.book.title }}</h4>
                      <p class="text-xs text-outline font-medium">{{ item.book.author || 'Chưa cập nhật tác giả' }}</p>
                      
                      <div v-if="item.book.type === 'ebook' && item.purchase_version" class="text-xs text-outline/80 pt-0.5">
                        <span>Phiên bản đã mua: <strong>v{{ item.purchase_version }}</strong></span>
                        <span v-if="item.latest_version && item.latest_version !== item.purchase_version" class="text-primary font-bold ml-2">· Có bản mới: v{{ item.latest_version }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center gap-3 shrink-0 self-end sm:self-center">
                    <button 
                      type="button" 
                      @click="toggleWishlist(item.book.id)" 
                      class="w-10 h-10 rounded-xl bg-surface-container-high border border-outline-variant/20 flex items-center justify-center text-on-surface transition-colors cursor-pointer"
                      :title="wishlistStore.isFavorite(item.book.id) ? 'Bỏ yêu thích' : 'Thêm yêu thích'"
                    >
                      <span class="material-symbols-outlined text-lg" :class="wishlistStore.isFavorite(item.book.id) ? 'text-error fill-1' : ''">favorite</span>
                    </button>
                    <button
                      v-if="item.book.type === 'ebook' && item.has_access && item.order_id"
                      @click="readEbook(item.order_id, item.book.id)"
                      class="min-h-11 px-5 py-2.5 bg-primary text-on-primary rounded-xl font-bold text-xs flex items-center gap-1.5 cursor-pointer border-none shadow-sm hover:bg-primary/90 transition-colors"
                    >
                      <span class="material-symbols-outlined text-[18px] fill-1">auto_stories</span>
                      Đọc Ngay
                    </button>
                    <button
                      @click="$router.push(`/book/${item.book.slug || item.book.id}#reviews`)"
                      class="min-h-11 px-4 py-2.5 bg-primary/10 text-primary hover:bg-primary hover:text-on-primary rounded-xl font-bold text-xs flex items-center gap-1.5 cursor-pointer border border-primary/20 transition-all"
                    >
                      <span class="material-symbols-outlined text-[18px]">rate_review</span>
                      Đánh giá
                    </button>
                    <button
                      @click="$router.push(`/book/${item.book.slug || item.book.id}`)"
                      class="min-h-11 px-4 py-2.5 border border-outline-variant/30 rounded-xl font-bold text-xs text-on-surface hover:bg-surface-container-high transition-colors cursor-pointer bg-transparent"
                    >
                      Thông tin
                    </button>
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
const searchQuery = ref('')
const sortBy = ref('purchased_desc')
const viewMode = ref('grid')
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
  let list = libraryItems.value

  // 1. Lọc theo định dạng
  if (currentType.value !== 'all') {
    list = list.filter(item => item.book?.type === currentType.value)
  }

  // 2. Tìm kiếm theo tên sách hoặc tác giả
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(item => {
      const title = (item.book?.title || '').toLowerCase()
      const author = (item.book?.author || '').toLowerCase()
      return title.includes(q) || author.includes(q)
    })
  }

  // 3. Sắp xếp
  return [...list].sort((a, b) => {
    if (sortBy.value === 'purchased_desc') {
      return new Date(b.purchased_at || 0) - new Date(a.purchased_at || 0)
    }
    if (sortBy.value === 'purchased_asc') {
      return new Date(a.purchased_at || 0) - new Date(b.purchased_at || 0)
    }
    if (sortBy.value === 'title_asc') {
      return (a.book?.title || '').localeCompare(b.book?.title || '', 'vi')
    }
    if (sortBy.value === 'title_desc') {
      return (b.book?.title || '').localeCompare(a.book?.title || '', 'vi')
    }
    return 0
  })
})

const resetFilters = () => {
  searchQuery.value = ''
  currentType.value = 'all'
  sortBy.value = 'purchased_desc'
}

const formatDate = (dateString) => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric'
  }).format(date)
}

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

<template>
  <div class="min-h-screen bg-background">
    <!-- Page Header -->
    <section class="w-full px-gutter max-w-[1280px] mx-auto pt-xl pb-lg">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
          <h1 class="font-inter text-3xl font-semibold text-primary tracking-tight mb-xs">Danh mục sách</h1>
          <p class="text-base text-on-surface-variant">Khám phá bộ sưu tập sách được tuyển chọn kỹ lưỡng.</p>
        </div>
        <!-- Sort Control -->
        <div class="flex items-center gap-sm">
          <span class="text-sm text-on-surface-variant">Sắp xếp:</span>
          <select
            v-model="sortBy"
            @change="applyFilters"
            class="px-md py-2 bg-surface-container-lowest border border-outline-variant rounded-lg text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary-fixed-dim transition-all cursor-pointer"
          >
            <option value="newest">Mới nhất</option>
            <option value="price_asc">Giá thấp → cao</option>
            <option value="price_desc">Giá cao → thấp</option>
            <option value="popular">Phổ biến nhất</option>
          </select>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <div class="max-w-[1280px] mx-auto px-gutter pb-xxl">
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-xl">

        <!-- ─── SIDEBAR ─── -->
        <aside class="lg:col-span-1">
          <div class="bg-surface-container-lowest rounded-xl soft-shadow border border-outline-variant/20 p-lg sticky top-24">
            <!-- Categories -->
            <h2 class="text-sm font-semibold text-on-surface tracking-tight mb-md">
              Thể loại
            </h2>
            <div v-if="loadingCategories" class="flex flex-col gap-2">
              <Skeleton v-for="i in 6" :key="i" height="36px" borderRadius="8px" />
            </div>
            <ul v-else class="flex flex-col gap-0.5 mb-lg">
              <li>
                <button @click="selectCategory(null)" :class="catClass(null)">Tất cả</button>
              </li>
              <li v-for="category in categories" :key="category.id">
                <button @click="selectCategory(category.id)" :class="catClass(category.id)">{{ category.name }}</button>
              </li>
            </ul>

            <!-- Loại sách -->
            <div class="border-t border-outline-variant/30 pt-lg mb-lg">
              <h3 class="text-sm font-semibold text-on-surface mb-md">Định dạng</h3>
              <div class="flex flex-col gap-2">
                <div class="flex items-center">
                  <RadioButton v-model="filterType" inputId="ft1" name="ftype" value="all" @change="applyFilters" />
                  <label for="ft1" class="ml-2 text-sm text-on-surface-variant cursor-pointer">Tất cả</label>
                </div>
                <div class="flex items-center">
                  <RadioButton v-model="filterType" inputId="ft2" name="ftype" value="physical" @change="applyFilters" />
                  <label for="ft2" class="ml-2 text-sm text-on-surface-variant cursor-pointer">Sách giấy</label>
                </div>
                <div class="flex items-center">
                  <RadioButton v-model="filterType" inputId="ft3" name="ftype" value="ebook" @change="applyFilters" />
                  <label for="ft3" class="ml-2 text-sm text-on-surface-variant cursor-pointer">E-book</label>
                </div>
              </div>
            </div>

            <!-- Khoảng giá -->
            <div class="border-t border-outline-variant/30 pt-lg">
              <h3 class="text-sm font-semibold text-on-surface mb-md">Khoảng giá</h3>
              <div class="flex items-center gap-2">
                <InputNumber v-model="filterMinPrice" placeholder="Từ" class="flex-1" inputClass="w-full text-sm !rounded-lg" @keyup.enter="applyFilters" />
                <span class="text-outline-variant">-</span>
                <InputNumber v-model="filterMaxPrice" placeholder="Đến" class="flex-1" inputClass="w-full text-sm !rounded-lg" @keyup.enter="applyFilters" />
              </div>
              <button @click="applyFilters" class="w-full mt-md py-2 px-md rounded-lg border border-primary text-primary text-sm font-medium hover:bg-surface-container-low transition-colors">
                Áp dụng lọc
              </button>
            </div>
          </div>
        </aside>

        <!-- ─── MAIN GRID ─── -->
        <main class="lg:col-span-3">
          <!-- Active Filters & Count -->
          <div class="flex items-center justify-between mb-lg">
            <div class="flex items-center gap-sm flex-wrap">
              <h2 class="font-inter text-xl font-semibold text-on-surface tracking-tight">{{ activeTitle }}</h2>
              <span v-if="searchQuery" class="inline-flex items-center gap-1 px-sm py-1 bg-surface-variant rounded-full text-xs font-medium text-primary">
                "{{ searchQuery }}"
                <button @click="clearSearch" class="hover:text-secondary"><span class="material-symbols-outlined text-[14px]">close</span></button>
              </span>
            </div>
            <span v-if="!loadingBooks" class="text-sm text-outline">{{ totalRecords }} kết quả</span>
          </div>

          <!-- Loading -->
          <div v-if="loadingBooks" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-gutter">
            <div v-for="i in 8" :key="i" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
              <Skeleton height="220px" borderRadius="0" />
              <div class="p-md flex flex-col gap-2"><Skeleton height="14px" width="85%" /><Skeleton height="12px" width="60%" /><Skeleton height="16px" width="40%" /></div>
            </div>
          </div>

          <!-- Empty -->
          <div v-else-if="books.length === 0" class="flex flex-col items-center justify-center py-xxl text-center bg-surface-container-lowest rounded-xl border border-outline-variant/20">
            <div class="w-20 h-20 rounded-full bg-surface-container flex items-center justify-center mb-lg">
              <span class="material-symbols-outlined text-outline text-4xl">search_off</span>
            </div>
            <p class="text-lg font-medium text-on-surface mb-1">Không tìm thấy sách</p>
            <p class="text-sm text-outline">Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm.</p>
          </div>

          <!-- Book Grid -->
          <div v-else>
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-gutter">
              <div
                v-for="book in books"
                :key="book.id"
                class="group bg-surface-container-lowest rounded-lg soft-shadow overflow-hidden cursor-pointer border border-outline-variant/30 transition-all duration-300 hover:-translate-y-1 hover:shadow-card-hover flex flex-col h-full"
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
                  <span v-if="book.sale_price && book.price > book.sale_price" class="absolute top-2 left-2 bg-secondary text-on-secondary text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm z-10">
                    -{{ Math.round((1 - book.sale_price / book.price) * 100) }}%
                  </span>

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

            <!-- Pagination -->
            <div v-if="totalRecords > 12" class="mt-xl flex justify-center">
              <Paginator :rows="12" :totalRecords="totalRecords" :first="first" @page="onPageChange" template="PrevPageLink PageLinks NextPageLink" class="!border-none !bg-transparent" />
            </div>
          </div>
        </main>
      </div>
    </div>

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
              class="absolute top-3 right-3 bg-secondary text-on-secondary text-[11px] font-bold px-2.5 py-1 rounded-md shadow-md z-10"
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
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import { readApiList, readApiPagination } from '@/services/apiContract'
import { useCartStore } from '@/stores/cart'
import { useToast } from 'primevue/usetoast'
import Skeleton from 'primevue/skeleton'
import Paginator from 'primevue/paginator'
import RadioButton from 'primevue/radiobutton'
import InputNumber from 'primevue/inputnumber'
import Dialog from 'primevue/dialog'
import { useWishlistStore } from '@/stores/wishlist'

const router = useRouter()
const route = useRoute()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()
const toast = useToast()

const categories = ref([])
const loadingCategories = ref(false)
const selectedCategoryId = ref(null)
const books = ref([])
const loadingBooks = ref(false)
const searchQuery = ref('')
const totalRecords = ref(0)
const first = ref(0)
const currentPage = ref(1)
const filterType = ref('all')
const filterMinPrice = ref(null)
const filterMaxPrice = ref(null)
const sortBy = ref('newest')

// Quick View State
const quickViewVisible = ref(false)
const quickViewBook = ref(null)
const quickViewVersion = ref('standard')
const quickViewQty = ref(1)

const activeTitle = computed(() => {
  if (searchQuery.value) return `Kết quả tìm kiếm`
  if (selectedCategoryId.value) {
    const cat = categories.value.find(c => c.id === selectedCategoryId.value)
    return cat ? cat.name : 'Danh mục'
  }
  return 'Tất cả sách'
})

const catClass = (id) => [
  'w-full text-left px-md py-2.5 rounded-lg text-sm font-medium transition-all duration-200',
  selectedCategoryId.value === id
    ? 'bg-surface-variant text-primary font-bold'
    : 'text-on-surface-variant hover:bg-surface-container-low hover:text-primary'
]

const fetchCategories = async () => {
  loadingCategories.value = true
  try {
    const res = await apiClient.get('/api/categories')
    categories.value = readApiList(res.data)
  } catch (e) { console.error('Lỗi tải danh mục:', e) }
  finally { loadingCategories.value = false }
}

const fetchBooks = async () => {
  loadingBooks.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: 12, // 12 items per page means exactly 3 rows of 4 items!
      ...(selectedCategoryId.value && { category_id: selectedCategoryId.value }),
      ...(searchQuery.value.trim() && { search: searchQuery.value.trim() }),
      ...(filterType.value !== 'all' && { type: filterType.value }),
      ...(filterMinPrice.value !== null && { min_price: filterMinPrice.value }),
      ...(filterMaxPrice.value !== null && { max_price: filterMaxPrice.value }),
      ...(sortBy.value !== 'newest' && { sort: sortBy.value }),
    }
    const res = await apiClient.get('/api/books', { params })
    books.value = readApiList(res.data)
    totalRecords.value = readApiPagination(res.data).total
  } catch (e) { console.error('Lỗi tải sách:', e) }
  finally { loadingBooks.value = false }
}

const selectCategory = (id) => {
  selectedCategoryId.value = id
  currentPage.value = 1; first.value = 0
  fetchBooks()
}
const applyFilters = () => { currentPage.value = 1; first.value = 0; fetchBooks() }
const clearSearch = () => { searchQuery.value = ''; applyFilters() }
const onPageChange = (event) => {
  first.value = event.first
  currentPage.value = event.page + 1
  fetchBooks()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

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

const goToDetail = (slug) => router.push({ name: 'book-detail', params: { slug } })
const formatCurrency = (v) => !v ? '0 đ' : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v)
const addToCart = (book) => {
  cartStore.addToCart(book, 1)
  toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm vào giỏ hàng!', life: 3000 })
}

const buyNow = (book) => {
  cartStore.addToCart(book, 1)
  router.push('/cart')
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

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
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

// Handle search and category_id queries from navigation/tags
watch(() => route.query, (query) => {
  if (query.search) {
    searchQuery.value = query.search
  }
  if (query.category_id) {
    selectedCategoryId.value = Number(query.category_id)
  }
  currentPage.value = 1
  first.value = 0
  fetchBooks()
}, { immediate: true, deep: true })

onMounted(() => {
  fetchCategories()
  wishlistStore.fetchWishlistIds()
})
</script>

<style scoped>
:deep(.p-paginator) { gap: 0.25rem; }
:deep(.p-paginator .p-paginator-page),
:deep(.p-paginator .p-paginator-prev),
:deep(.p-paginator .p-paginator-next) { border-radius: 0.5rem; font-size: 0.875rem; min-width: 36px; height: 36px; }
:deep(.p-paginator .p-paginator-page.p-highlight) { background-color: #002442; color: white; border-color: #002442; }
</style>

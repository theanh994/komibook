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
        <div class="flex flex-wrap items-center gap-sm">
          <button
            type="button"
            class="ui-button ui-button-secondary !min-h-11 lg:hidden"
            :aria-expanded="filtersOpen"
            aria-controls="catalog-filters"
            @click="filtersOpen = true"
          >
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">tune</span>
            Bộ lọc
          </button>
          <span class="text-sm text-on-surface-variant">Sắp xếp:</span>
          <select
            v-model="sortBy"
            @change="applyFilters"
            aria-label="Sắp xếp sách"
            class="min-h-11 cursor-pointer rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-2 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary-fixed-dim"
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
        <div v-if="filtersOpen" class="fixed inset-0 z-[70] bg-slate-950/50 lg:hidden" aria-hidden="true" @click="filtersOpen = false"></div>
        <aside
          id="catalog-filters"
          class="fixed inset-y-0 left-0 z-[80] w-[min(90vw,22rem)] overflow-y-auto bg-surface-container-lowest transition-transform lg:static lg:z-auto lg:col-span-1 lg:block lg:w-auto lg:overflow-visible lg:bg-transparent"
          :class="filtersOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
          :aria-hidden="!filtersOpen && isMobileFilters"
          :inert="!filtersOpen && isMobileFilters"
          aria-label="Bộ lọc danh mục"
        >
          <div class="min-h-full border border-outline-variant/20 bg-surface-container-lowest p-lg soft-shadow lg:sticky lg:top-24 lg:min-h-0 lg:rounded-xl">
            <div class="mb-5 flex items-center justify-between lg:hidden">
              <h2 class="text-lg font-bold text-on-surface">Bộ lọc</h2>
              <button type="button" class="ui-icon-button !h-11 !w-11 !min-h-11 !min-w-11" aria-label="Đóng bộ lọc" @click="filtersOpen = false">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
              </button>
            </div>
            <!-- Categories -->
            <h2 class="text-sm font-semibold text-on-surface tracking-tight mb-md">
              Thể loại
            </h2>
            <div v-if="loadingCategories" class="flex flex-col gap-2">
              <Skeleton v-for="i in 6" :key="i" height="36px" borderRadius="8px" />
            </div>
            <div v-else-if="categoryError" class="mb-lg rounded-lg bg-error-container p-3 text-sm text-on-error-container" role="alert">
              <p>Không thể tải danh mục.</p>
              <button type="button" class="mt-2 min-h-11 rounded-lg border border-current px-3 font-semibold" @click="fetchCategories">Thử lại</button>
            </div>
            <ul v-else class="flex flex-col gap-0.5 mb-lg">
              <li>
                <button @click="selectCategory(null)" :class="catClass(null)" :aria-pressed="selectedCategoryId === null">Tất cả</button>
              </li>
              <li v-for="category in categories" :key="category.id">
                <button @click="selectCategory(category.id)" :class="catClass(category.id)" :aria-pressed="selectedCategoryId === category.id">{{ category.name }}</button>
              </li>
            </ul>

            <!-- Loại sách -->
            <div class="border-t border-outline-variant/30 pt-lg mb-lg">
              <h3 class="text-sm font-semibold text-on-surface mb-md">Định dạng</h3>
              <div class="grid gap-2">
                <button v-for="option in productOptions" :key="option.value" type="button" :class="filterButtonClass(filterProduct === option.value)" :aria-pressed="filterProduct === option.value" @click="filterProduct = option.value; applyFilters()">
                  {{ option.label }}
                </button>
              </div>
            </div>

            <!-- Lứa tuổi -->
            <div class="border-t border-outline-variant/30 pt-lg mb-lg">
              <h3 class="text-sm font-semibold text-on-surface mb-md">Lứa tuổi</h3>
              <div class="grid gap-2">
                <button v-for="option in ageOptions" :key="option.value" type="button" :class="filterButtonClass(filterAge === option.value)" :aria-pressed="filterAge === option.value" @click="filterAge = option.value; applyFilters()">
                  {{ option.label }}
                </button>
              </div>
            </div>

            <!-- Khoảng giá -->
            <div class="border-t border-outline-variant/30 pt-lg">
              <h3 class="text-sm font-semibold text-on-surface mb-md">Khoảng giá</h3>
              <div class="grid gap-2">
                <button type="button" :class="filterButtonClass(filterPrice === '')" :aria-pressed="filterPrice === ''" @click="filterPrice = ''; applyFilters()">Tất cả mức giá</button>
                <button v-for="band in priceBands" :key="band.value" type="button" :class="filterButtonClass(filterPrice === band.value)" :aria-pressed="filterPrice === band.value" @click="filterPrice = band.value; applyFilters()">
                  {{ band.label }}
                </button>
              </div>
              <button @click="resetFilters" class="mt-md min-h-11 w-full rounded-lg border border-primary px-md py-2 text-sm font-medium text-primary transition-colors hover:bg-surface-container-low">
                Xóa bộ lọc
              </button>
            </div>
          </div>
        </aside>

        <!-- ─── MAIN GRID ─── -->
        <section class="lg:col-span-3" aria-labelledby="catalog-results-title">
          <!-- Active Filters & Count -->
          <div class="flex items-center justify-between mb-lg">
            <div class="flex items-center gap-sm flex-wrap">
              <h2 id="catalog-results-title" class="font-inter text-xl font-semibold text-on-surface tracking-tight">{{ activeTitle }}</h2>
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

          <div v-else-if="booksError" class="flex flex-col items-center justify-center py-xxl text-center bg-surface-container-lowest rounded-xl border border-outline-variant/20" role="alert">
            <p class="text-lg font-medium text-on-surface">Không thể tải sách.</p>
            <button type="button" class="mt-4 min-h-11 rounded-lg border border-primary px-4 font-semibold text-primary" @click="fetchBooks">Thử lại</button>
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
              <BookCard
                v-for="book in books"
                :key="book.id"
                :book="book"
                show-wishlist
                :is-favorite="wishlistStore.isFavorite(book.id)"
                @quick-view="openQuickView"
                @add-to-cart="addToCart"
                @buy-now="buyNow"
                @toggle-wishlist="toggleWishlist"
              />
            </div>

            <!-- Pagination -->
            <div v-if="totalRecords > PAGE_SIZE" class="mt-xl flex justify-center">
              <Paginator :rows="PAGE_SIZE" :totalRecords="totalRecords" :first="first" @page="onPageChange" template="PrevPageLink PageLinks NextPageLink" class="!border-none !bg-transparent" />
            </div>
          </div>
        </section>
      </div>
    </div>

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
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import { readApiList, readApiPagination } from '@/services/apiContract'
import { useCartStore } from '@/stores/cart'
import { useToast } from 'primevue/usetoast'
import Skeleton from 'primevue/skeleton'
import Paginator from 'primevue/paginator'
import { useWishlistStore } from '@/stores/wishlist'
import BookCard from '@/components/BookCard.vue'
import BookQuickViewDialog from '@/components/BookQuickViewDialog.vue'

const router = useRouter()
const route = useRoute()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()
const toast = useToast()

const categories = ref([])
const loadingCategories = ref(false)
const categoryError = ref(false)
const selectedCategoryId = ref(null)
const books = ref([])
const loadingBooks = ref(false)
const booksError = ref(false)
const searchQuery = ref('')
const totalRecords = ref(0)
const first = ref(0)
const currentPage = ref(1)
const filterProduct = ref('all')
const filterAge = ref('')
const filterPrice = ref('')
const sortBy = ref('newest')
const filtersOpen = ref(false)
const isMobileFilters = ref(false)

const productOptions = [
  { value: 'all', label: 'Tất cả' },
  { value: 'physical', label: 'Sách giấy' },
  { value: 'ebook', label: 'Ebook' },
  { value: 'used_resale', label: 'Sách cũ' },
]

const ageOptions = [
  { value: '', label: 'Mọi lứa tuổi' },
  { value: 'Nhà trẻ - mẫu giáo (0 - 6)', label: 'Nhà trẻ - mẫu giáo (0 - 6)' },
  { value: 'Nhi đồng (6 - 11)', label: 'Nhi đồng (6 - 11)' },
  { value: 'Thiếu niên (11 - 15)', label: 'Thiếu niên (11 - 15)' },
  { value: 'Tuổi mới lớn (15 - 18)', label: 'Tuổi mới lớn (15 - 18)' },
  { value: 'Tuổi trưởng thành (Trên 18 tuổi)', label: 'Tuổi trưởng thành (Trên 18 tuổi)' },
]

const priceBands = [
  { value: 'under-50000', label: 'Dưới 50.000đ', min: null, max: 49999 },
  { value: '50000-100000', label: '50.000–100.000đ', min: 50000, max: 100000 },
  { value: '100000-200000', label: '100.000–200.000đ', min: 100001, max: 200000 },
  { value: '200000-300000', label: '200.000–300.000đ', min: 200001, max: 300000 },
  { value: '300000-500000', label: '300.000–500.000đ', min: 300001, max: 500000 },
  { value: 'over-500000', label: 'Trên 500.000đ', min: 500001, max: null },
]

const selectedPriceBand = computed(() => priceBands.find((band) => band.value === filterPrice.value))
const PAGE_SIZE = 16

// Quick View State
const quickViewVisible = ref(false)
const quickViewBook = ref(null)

const activeTitle = computed(() => {
  if (filterProduct.value === 'used_resale') return 'Sách cũ'
  if (searchQuery.value) return `Kết quả tìm kiếm`
  if (selectedCategoryId.value) {
    const cat = categories.value.find(c => c.id === selectedCategoryId.value)
    return cat ? cat.name : 'Danh mục'
  }
  return 'Tất cả sách'
})

const catClass = (id) => [
  'min-h-11 w-full rounded-lg px-md py-2.5 text-left text-sm font-medium transition-colors duration-200',
  selectedCategoryId.value === id
    ? 'bg-surface-variant text-primary font-bold'
    : 'text-on-surface-variant hover:bg-surface-container-low hover:text-primary'
]

const filterButtonClass = (active) => [
  'min-h-11 rounded-lg border px-3 py-2 text-left text-sm font-semibold transition-colors',
  active
    ? 'border-primary bg-primary text-on-primary'
    : 'border-outline-variant/50 bg-surface-container-lowest text-on-surface-variant hover:border-primary hover:text-primary',
]

const fetchCategories = async () => {
  loadingCategories.value = true
  categoryError.value = false
  try {
    const res = await apiClient.get('/api/categories')
    categories.value = readApiList(res.data)
  } catch (e) { categoryError.value = true; console.error('Lỗi tải danh mục:', e) }
  finally { loadingCategories.value = false }
}

const fetchBooks = async () => {
  loadingBooks.value = true
  booksError.value = false
  try {
    const params = {
      page: currentPage.value,
      per_page: PAGE_SIZE,
      ...(selectedCategoryId.value && { category_id: selectedCategoryId.value }),
      ...(searchQuery.value.trim() && { search: searchQuery.value.trim() }),
      ...(['physical', 'ebook'].includes(filterProduct.value) && { type: filterProduct.value }),
      ...(filterProduct.value === 'used_resale' && { type: 'physical', provenance: 'used_resale' }),
      ...(filterAge.value && { target_age: filterAge.value }),
      ...(selectedPriceBand.value?.min !== null && selectedPriceBand.value?.min !== undefined && { min_price: selectedPriceBand.value.min }),
      ...(selectedPriceBand.value?.max !== null && selectedPriceBand.value?.max !== undefined && { max_price: selectedPriceBand.value.max }),
      ...(sortBy.value !== 'newest' && { sort: sortBy.value }),
    }
    const res = await apiClient.get('/api/books', { params })
    books.value = readApiList(res.data)
    totalRecords.value = readApiPagination(res.data).total
  } catch (e) { booksError.value = true; console.error('Lỗi tải sách:', e) }
  finally { loadingBooks.value = false }
}

const selectCategory = (id) => {
  selectedCategoryId.value = id
  currentPage.value = 1; first.value = 0
  updateRouteQuery()
}
const applyFilters = () => {
  currentPage.value = 1
  first.value = 0
  filtersOpen.value = false
  updateRouteQuery()
}
const clearSearch = () => {
  searchQuery.value = ''
  applyFilters()
}
const resetFilters = () => {
  selectedCategoryId.value = null
  filterProduct.value = 'all'
  filterAge.value = ''
  filterPrice.value = ''
  applyFilters()
}
const onPageChange = (event) => {
  first.value = event.first
  currentPage.value = event.page + 1
  updateRouteQuery()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const catalogQuery = () => ({
  ...(searchQuery.value.trim() && { search: searchQuery.value.trim() }),
  ...(selectedCategoryId.value && { category_id: String(selectedCategoryId.value) }),
  ...(['physical', 'ebook'].includes(filterProduct.value) && { type: filterProduct.value }),
  ...(filterProduct.value === 'used_resale' && { provenance: 'used_resale' }),
  ...(filterAge.value && { target_age: filterAge.value }),
  ...(filterPrice.value && { price: filterPrice.value }),
  ...(sortBy.value !== 'newest' && { sort: sortBy.value }),
  ...(currentPage.value > 1 && { page: String(currentPage.value) }),
})

const updateRouteQuery = () => {
  const nextQuery = catalogQuery()
  const current = JSON.stringify(route.query)
  const next = JSON.stringify(nextQuery)
  if (current === next) {
    fetchBooks()
    return
  }
  router.push({ name: 'catalog', query: nextQuery })
}


const addToCart = (book, quantity = 1) => {
  cartStore.addToCart(book, quantity)
  toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm vào giỏ hàng!', life: 3000 })
}

const buyNow = (book, quantity = 1) => {
  cartStore.addToCart(book, quantity)
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

// Quick View Actions
const openQuickView = (book) => {
  quickViewBook.value = book
  quickViewVisible.value = true
}

// Handle search and category_id queries from navigation/tags
watch(() => route.query, (query) => {
  searchQuery.value = typeof query.search === 'string' ? query.search : ''
  selectedCategoryId.value = query.category_id ? Number(query.category_id) : null
  filterProduct.value = query.provenance === 'used_resale'
    ? 'used_resale'
    : (['physical', 'ebook'].includes(query.type) ? query.type : 'all')
  filterAge.value = ageOptions.some((option) => option.value === query.target_age) ? query.target_age : ''
  filterPrice.value = priceBands.some((band) => band.value === query.price) ? query.price : ''
  sortBy.value = ['price_asc', 'price_desc', 'popular'].includes(query.sort) ? query.sort : 'newest'
  currentPage.value = Math.max(Number(query.page) || 1, 1)
  first.value = (currentPage.value - 1) * PAGE_SIZE
  filtersOpen.value = false
  fetchBooks()
}, { immediate: true, deep: true })

const syncFilterViewport = () => {
  isMobileFilters.value = window.innerWidth < 1024
  if (!isMobileFilters.value) filtersOpen.value = false
}

const handleCatalogKeydown = (event) => {
  if (event.key === 'Escape') filtersOpen.value = false
}

onMounted(() => {
  fetchCategories()
  wishlistStore.fetchWishlistIds()
  syncFilterViewport()
  window.addEventListener('resize', syncFilterViewport)
  window.addEventListener('keydown', handleCatalogKeydown)
})

onUnmounted(() => {
  window.removeEventListener('resize', syncFilterViewport)
  window.removeEventListener('keydown', handleCatalogKeydown)
})
</script>

<style scoped>
:deep(.p-paginator) { gap: 0.25rem; }
:deep(.p-paginator .p-paginator-page),
:deep(.p-paginator .p-paginator-prev),
:deep(.p-paginator .p-paginator-next) { border-radius: 0.5rem; font-size: 0.875rem; min-width: 44px; height: 44px; }
:deep(.p-paginator .p-paginator-page.p-highlight) { background-color: #002442; color: white; border-color: #002442; }
</style>

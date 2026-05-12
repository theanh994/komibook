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
            <h2 class="text-sm font-semibold text-on-surface tracking-tight mb-md flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-[20px]">category</span>
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
          <div v-if="loadingBooks" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-gutter">
            <div v-for="i in 9" :key="i" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
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
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-gutter">
              <div
                v-for="book in books"
                :key="book.id"
                class="group bg-surface-container-lowest rounded-lg soft-shadow overflow-hidden cursor-pointer border border-outline-variant/30 transition-all duration-300 hover:-translate-y-1 hover:shadow-card-hover flex flex-col h-full"
                @click="goToDetail(book.slug)"
              >
                <div class="relative pt-[140%] bg-surface-variant/30">
                  <img v-if="book.cover_image" :src="book.cover_image" :alt="book.title" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" />
                  <div v-else class="absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined text-outline text-4xl">image</span></div>
                  <span v-if="book.sale_price && book.price > book.sale_price" class="absolute top-2 left-2 bg-secondary text-on-secondary text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm">
                    -{{ Math.round((1 - book.sale_price / book.price) * 100) }}%
                  </span>
                  <div v-if="book.type === 'ebook'" class="absolute top-2 right-2 bg-surface-container-lowest/90 backdrop-blur-sm px-sm py-xs rounded text-primary text-[11px] font-semibold flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[14px]">menu_book</span> Ebook
                  </div>
                </div>
                <div class="p-md flex flex-col flex-grow">
                  <span class="text-[10px] font-bold text-outline uppercase tracking-wider mb-1">{{ book.category?.name || 'Sách' }}</span>
                  <h3 class="text-sm font-medium text-on-surface line-clamp-2 leading-snug mb-1 group-hover:text-primary transition-colors">{{ book.title }}</h3>
                  <p class="text-[13px] text-on-surface-variant mb-md flex-grow">{{ book.author || 'Đang cập nhật' }}</p>
                  <div class="flex flex-col gap-2 mt-auto">
                    <div class="flex items-center justify-between gap-2">
                      <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-primary">{{ formatCurrency(book.sale_price || book.price) }}</span>
                        <span v-if="book.sale_price && book.price > book.sale_price" class="text-xs text-outline line-through">{{ formatCurrency(book.price) }}</span>
                      </div>
                      <button class="text-primary hover:text-secondary transition-colors p-1 bg-surface-container rounded-full hover:bg-surface-variant" @click.stop="addToCart(book)" title="Thêm vào giỏ">
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
              <Paginator :rows="12" :totalRecords="totalRecords" :first="first" @page="onPageChange" template="PrevPageLink PageLinks NextPageLink" class="!border-none !bg-transparent" />
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import { useCartStore } from '@/stores/cart'
import { useToast } from 'primevue/usetoast'
import Skeleton from 'primevue/skeleton'
import Paginator from 'primevue/paginator'
import RadioButton from 'primevue/radiobutton'
import InputNumber from 'primevue/inputnumber'

const router = useRouter()
const route = useRoute()
const cartStore = useCartStore()
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
    categories.value = res.data.data || res.data
  } catch (e) { console.error('Lỗi tải danh mục:', e) }
  finally { loadingCategories.value = false }
}

const fetchBooks = async () => {
  loadingBooks.value = true
  try {
    const params = {
      page: currentPage.value,
      ...(selectedCategoryId.value && { category_id: selectedCategoryId.value }),
      ...(searchQuery.value.trim() && { search: searchQuery.value.trim() }),
      ...(filterType.value !== 'all' && { type: filterType.value }),
      ...(filterMinPrice.value !== null && { min_price: filterMinPrice.value }),
      ...(filterMaxPrice.value !== null && { max_price: filterMaxPrice.value }),
      ...(sortBy.value !== 'newest' && { sort: sortBy.value }),
    }
    const res = await apiClient.get('/api/books', { params })
    books.value = res.data.data || res.data
    totalRecords.value = res.data.meta?.total || 0
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

// Handle search query from header
watch(() => route.query.search, (newSearch) => {
  if (newSearch) {
    searchQuery.value = newSearch
    currentPage.value = 1; first.value = 0
    fetchBooks()
  }
}, { immediate: true })

onMounted(() => {
  fetchCategories()
  if (!route.query.search) fetchBooks()
})
</script>

<style scoped>
:deep(.p-paginator) { gap: 0.25rem; }
:deep(.p-paginator .p-paginator-page),
:deep(.p-paginator .p-paginator-prev),
:deep(.p-paginator .p-paginator-next) { border-radius: 0.5rem; font-size: 0.875rem; min-width: 36px; height: 36px; }
:deep(.p-paginator .p-paginator-page.p-highlight) { background-color: #002442; color: white; border-color: #002442; }
</style>

<template>
  <div class="min-h-screen bg-slate-50">

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- HERO / HEADER SECTION                                         -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <section class="bg-gradient-to-br from-indigo-600 via-indigo-500 to-purple-500 py-14 px-4">
      <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-3xl md:text-4xl font-bold text-white tracking-tight mb-3">
          Khám phá thế giới sách
        </h1>
        <p class="text-indigo-100 text-base md:text-lg mb-8">
          Hàng ngàn cuốn sách hay đang chờ bạn tại KomiBook
        </p>

        <!-- Search Bar -->
        <div class="relative max-w-xl mx-auto">
          <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
          <InputText
            v-model="searchQuery"
            placeholder="Tìm kiếm theo tên sách hoặc tác giả..."
            class="search-input w-full !pl-12 !pr-4 !py-3.5 !rounded-xl !text-sm !bg-white/95 !backdrop-blur-sm !border-white/30 !shadow-lg focus:!ring-2 focus:!ring-white/40 focus:!border-white/50"
          />
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- MAIN CONTENT — CSS Grid 2 cột                                 -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div class="max-w-7xl mx-auto px-4 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

        <!-- ─────────── SIDEBAR (1/4) ─────────── -->
        <aside class="lg:col-span-1">
          <div class="bg-white rounded-xl shadow-sm shadow-slate-200/50 border border-slate-200/60 p-5 sticky top-6">
            <h2 class="text-base font-semibold text-slate-900 tracking-tight mb-4 flex items-center gap-2">
              <i class="pi pi-list text-indigo-500"></i>
              Danh mục sách
            </h2>

            <!-- Loading Categories -->
            <div v-if="loadingCategories" class="flex flex-col gap-2">
              <Skeleton v-for="i in 6" :key="i" height="36px" borderRadius="8px" />
            </div>

            <!-- Category List -->
            <ul v-else class="flex flex-col gap-0.5">
              <li>
                <button
                  @click="selectCategory(null)"
                  :class="[
                    'w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200',
                    selectedCategoryId === null
                      ? 'bg-indigo-50 text-indigo-600 font-semibold'
                      : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  Tất cả sách
                </button>
              </li>
              <li v-for="category in categories" :key="category.id">
                <button
                  @click="selectCategory(category.id)"
                  :class="[
                    'w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200',
                    selectedCategoryId === category.id
                      ? 'bg-indigo-50 text-indigo-600 font-semibold'
                      : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  {{ category.name }}
                </button>
              </li>
            </ul>
          </div>
        </aside>

        <!-- ─────────── MAIN GRID (3/4) ─────────── -->
        <main class="lg:col-span-3">

          <!-- Result Info Bar -->
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-slate-900 tracking-tight">
              {{ activeTitle }}
            </h2>
            <span v-if="!loadingBooks" class="text-sm text-slate-400">
              {{ totalRecords }} kết quả
            </span>
          </div>

          <!-- ── LOADING STATE: Skeleton Grid ── -->
          <div v-if="loadingBooks" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
            <div
              v-for="i in 6"
              :key="i"
              class="bg-white rounded-xl border border-slate-200/60 overflow-hidden"
            >
              <Skeleton height="220px" borderRadius="0" />
              <div class="p-4 flex flex-col gap-2.5">
                <Skeleton height="18px" width="85%" borderRadius="6px" />
                <Skeleton height="14px" width="60%" borderRadius="6px" />
                <Skeleton height="22px" width="40%" borderRadius="6px" />
              </div>
            </div>
          </div>

          <!-- ── EMPTY STATE ── -->
          <div
            v-else-if="books.length === 0"
            class="flex flex-col items-center justify-center py-24 text-center"
          >
            <div class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center mb-5">
              <i class="pi pi-book text-3xl text-slate-300"></i>
            </div>
            <p class="text-lg font-medium text-slate-600 mb-1">
              Không tìm thấy cuốn sách nào
            </p>
            <p class="text-sm text-slate-400">
              Thử thay đổi từ khoá hoặc chọn danh mục khác.
            </p>
          </div>

          <!-- ── BOOK GRID ── -->
          <div v-else>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
              <div
                v-for="book in books"
                :key="book.id"
                class="book-card group bg-white rounded-xl border border-slate-200/60 shadow-sm shadow-slate-200/50 overflow-hidden cursor-pointer hover:-translate-y-1 hover:shadow-md transition-all duration-300 ease-out"
                @click="goToDetail(book.slug)"
              >
                <!-- Cover Image -->
                <div class="relative w-full pt-[140%] overflow-hidden bg-slate-100">
                  <img
                    v-if="book.cover_image"
                    :src="book.cover_image"
                    :alt="book.title"
                    class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                    loading="lazy"
                  />
                  <div
                    v-else
                    class="absolute inset-0 flex items-center justify-center"
                  >
                    <i class="pi pi-image text-4xl text-slate-300"></i>
                  </div>

                  <!-- Badge: Sale -->
                  <span
                    v-if="book.sale_price && book.price > book.sale_price"
                    class="absolute top-2.5 left-2.5 bg-rose-500 text-white text-[11px] font-bold px-2 py-0.5 rounded-md shadow-sm"
                  >
                    -{{ Math.round((1 - book.sale_price / book.price) * 100) }}%
                  </span>
                </div>

                <!-- Info -->
                <div class="p-4">
                  <h3 class="text-sm font-semibold text-slate-900 line-clamp-2 leading-snug mb-1.5 group-hover:text-indigo-600 transition-colors">
                    {{ book.title }}
                  </h3>
                  <p class="text-xs text-slate-400 line-clamp-1 mb-3">
                    {{ book.author || 'Đang cập nhật' }}
                    <span v-if="book.vendor" class="ml-1">
                      · <i class="pi pi-shop text-[10px]"></i> {{ book.vendor.name }}
                    </span>
                  </p>

                  <!-- Price & Cart -->
                  <div class="flex items-center justify-between gap-2 mt-auto">
                    <div class="flex items-center gap-2">
                      <span class="text-base font-bold text-indigo-600">
                        {{ formatCurrency(book.sale_price || book.price) }}
                      </span>
                      <span
                        v-if="book.sale_price && book.price > book.sale_price"
                        class="text-xs text-slate-400 line-through"
                      >
                        {{ formatCurrency(book.price) }}
                      </span>
                    </div>
                    <Button 
                      icon="pi pi-cart-plus" 
                      class="!w-8 !h-8 !p-0 !rounded-full !bg-indigo-50 !text-indigo-600 !border-none hover:!bg-indigo-600 hover:!text-white transition-colors"
                      @click.stop="addToCart(book)"
                      title="Thêm vào giỏ"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- Phân trang -->
            <div v-if="totalRecords > 12" class="mt-10 flex justify-center">
              <Paginator
                :rows="12"
                :totalRecords="totalRecords"
                :first="first"
                @page="onPageChange"
                template="PrevPageLink PageLinks NextPageLink"
                class="!border-none !bg-transparent"
              />
            </div>
          </div>
        </main>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'
import { useCartStore } from '@/stores/cart'
import { useToast } from 'primevue/usetoast'
import InputText from 'primevue/inputtext'
import Skeleton from 'primevue/skeleton'
import Paginator from 'primevue/paginator'
import Button from 'primevue/button'

const router = useRouter()
const cartStore = useCartStore()
const toast = useToast()

// ─── State ──────────────────────────────────────────────────────────
const categories = ref([])
const loadingCategories = ref(false)
const selectedCategoryId = ref(null)

const books = ref([])
const loadingBooks = ref(false)

const searchQuery = ref('')

// Pagination
const totalRecords = ref(0)
const first = ref(0)
const currentPage = ref(1)

// ─── Computed ───────────────────────────────────────────────────────
const activeTitle = computed(() => {
  if (searchQuery.value) {
    return `Kết quả cho "${searchQuery.value}"`
  }
  if (selectedCategoryId.value) {
    const cat = categories.value.find((c) => c.id === selectedCategoryId.value)
    return cat ? cat.name : 'Danh mục'
  }
  return 'Tất cả sách'
})

// ─── Fetch API ──────────────────────────────────────────────────────
const fetchCategories = async () => {
  loadingCategories.value = true
  try {
    const response = await apiClient.get('/api/categories')
    categories.value = response.data.data || response.data
  } catch (error) {
    console.error('Lỗi tải danh mục:', error)
  } finally {
    loadingCategories.value = false
  }
}

const fetchBooks = async () => {
  loadingBooks.value = true
  try {
    const params = {
      page: currentPage.value,
      ...(selectedCategoryId.value && { category_id: selectedCategoryId.value }),
      ...(searchQuery.value.trim() && { search: searchQuery.value.trim() }),
    }

    const response = await apiClient.get('/api/books', { params })
    books.value = response.data.data || response.data

    // Set meta pagination
    const meta = response.data.meta || {}
    totalRecords.value = meta.total || 0
  } catch (error) {
    console.error('Lỗi tải sách:', error)
  } finally {
    loadingBooks.value = false
  }
}

// ─── User Actions ───────────────────────────────────────────────────
const selectCategory = (id) => {
  selectedCategoryId.value = id
  currentPage.value = 1
  first.value = 0
  fetchBooks()
}

const onPageChange = (event) => {
  first.value = event.first
  currentPage.value = event.page + 1
  fetchBooks()
  window.scrollTo({ top: 0, behavior: 'smooth' })
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

// ─── Watchers ───────────────────────────────────────────────────────
// Debounce search: gọi lại API sau 400ms khi user ngừng gõ
let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    first.value = 0
    fetchBooks()
  }, 400)
})

// ─── Init ───────────────────────────────────────────────────────────
onMounted(() => {
  fetchCategories()
  fetchBooks()
})
</script>

<style scoped>
/* UUPM: Search input override */
:deep(.search-input) {
  color: var(--color-slate-900);
}
:deep(.search-input::placeholder) {
  color: var(--color-slate-400);
}
:deep(.search-input:focus) {
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
}

/* UUPM: Paginator override */
:deep(.p-paginator) {
  gap: 0.25rem;
}
:deep(.p-paginator .p-paginator-page),
:deep(.p-paginator .p-paginator-prev),
:deep(.p-paginator .p-paginator-next) {
  border-radius: 0.5rem;
  font-size: 0.875rem;
  min-width: 36px;
  height: 36px;
}
:deep(.p-paginator .p-paginator-page.p-highlight) {
  background-color: var(--color-indigo-600);
  color: white;
  border-color: var(--color-indigo-600);
}
</style>

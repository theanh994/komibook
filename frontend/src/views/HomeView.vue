<template>
  <div class="min-h-screen bg-surface-50 dark:bg-surface-900 p-4 md:p-8">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row gap-6">
      
      <!-- Sidebar Danh mục -->
      <aside class="w-full md:w-64 shrink-0">
        <div class="bg-white dark:bg-surface-800 rounded-xl shadow-sm border border-surface-200 dark:border-surface-700 p-4 sticky top-6">
          <h2 class="text-lg font-bold mb-4 flex items-center gap-2 border-b border-surface-100 dark:border-surface-700 pb-2">
            <i class="pi pi-list"></i> Danh mục sách
          </h2>
          
          <div v-if="loadingCategories" class="flex justify-center p-4">
            <i class="pi pi-spin pi-spinner text-xl text-primary"></i>
          </div>
          
          <ul v-else class="flex flex-col gap-1">
            <li>
              <button
                @click="selectCategory(null)"
                :class="['w-full text-left px-3 py-2 rounded-lg transition-colors', !selectedCategoryId ? 'bg-primary text-primary-contrast font-bold' : 'hover:bg-surface-100 dark:hover:bg-surface-700']"
              >
                Tất cả sách
              </button>
            </li>
            <li v-for="category in categories" :key="category.id">
              <button
                @click="selectCategory(category.id)"
                :class="['w-full text-left px-3 py-2 rounded-lg transition-colors', selectedCategoryId === category.id ? 'bg-primary text-primary-contrast font-bold' : 'hover:bg-surface-100 dark:hover:bg-surface-700']"
              >
                {{ category.name }}
              </button>
            </li>
          </ul>
        </div>
      </aside>

      <!-- Main Catalog -->
      <main class="flex-1">
        <div class="bg-white dark:bg-surface-800 rounded-xl shadow-sm border border-surface-200 dark:border-surface-700 p-4 md:p-6 min-h-[600px]">
          <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Khám phá thư viện</h1>
            <!-- Có thể thêm sort dropdown ở đây -->
          </div>

          <div v-if="loadingBooks" class="flex justify-center items-center h-64">
            <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
          </div>

          <div v-else-if="books.length === 0" class="flex flex-col items-center justify-center h-64 text-surface-500">
            <i class="pi pi-book text-6xl mb-4 opacity-50"></i>
            <p class="text-lg">Không tìm thấy cuốn sách nào trong danh mục này.</p>
          </div>

          <div v-else>
            <!-- Grid hiển thị sách -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
              <BookCard v-for="book in books" :key="book.id" :book="book" />
            </div>

            <!-- Phân trang -->
            <div class="mt-8 flex justify-center">
              <Paginator 
                :rows="12" 
                :totalRecords="totalRecords" 
                :first="first" 
                @page="onPageChange" 
                template="PrevPageLink PageLinks NextPageLink"
              ></Paginator>
            </div>
          </div>
        </div>
      </main>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import apiClient from '@/services/axios'
import BookCard from '@/components/BookCard.vue'
import Paginator from 'primevue/paginator'

// State
const categories = ref([])
const loadingCategories = ref(false)
const selectedCategoryId = ref(null)

const books = ref([])
const loadingBooks = ref(false)

// Pagination
const totalRecords = ref(0)
const first = ref(0)
const currentPage = ref(1)

// Fetch API
const fetchCategories = async () => {
  loadingCategories.value = true
  try {
    const response = await apiClient.get('/api/categories')
    const data = response.data.data || response.data
    categories.value = data
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
      ...(selectedCategoryId.value && { category_id: selectedCategoryId.value })
    }
    
    const response = await apiClient.get('/api/books', { params })
    // Cấu trúc Pagination resource
    const responseData = response.data.data || response.data
    books.value = responseData
    
    // Set meta pagination
    const meta = response.data.meta || {}
    totalRecords.value = meta.total || 0
  } catch (error) {
    console.error('Lỗi tải sách:', error)
  } finally {
    loadingBooks.value = false
  }
}

// Logic
const selectCategory = (id) => {
  selectedCategoryId.value = id
  currentPage.value = 1
  first.value = 0
  fetchBooks()
}

const onPageChange = (event) => {
  first.value = event.first
  // event.page (0-indexed) => currentPage = event.page + 1
  currentPage.value = event.page + 1
  fetchBooks()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// Init
onMounted(() => {
  fetchCategories()
  fetchBooks()
})
</script>

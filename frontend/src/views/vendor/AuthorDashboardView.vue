<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import ProgressBar from 'primevue/progressbar'

const router = useRouter()

const stats = ref({
  total_earnings: 0,
  total_withdrawn: 0,
  balance: 0,
  books_count: 0,
  chapters_count: 0,
  views_count: 0,
})

const books = ref([])
const loading = ref(true)
const error = ref(null)

const fetchStats = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await apiClient.get('/api/author/dashboard-stats')
    if (res.data?.status === 'success') {
      stats.value = res.data.data.stats || stats.value
      books.value = res.data.data.books || []
    } else if (res.data) {
      stats.value = res.data.stats || stats.value
      books.value = res.data.books || []
    } else {
      books.value = []
    }
  } catch (e) {
    console.error('Không tải được thông tin dashboard tác giả', e)
    books.value = []
    stats.value = {
      total_earnings: 0,
      total_withdrawn: 0,
      balance: 0,
      books_count: 0,
      chapters_count: 0,
      views_count: 0,
    }
    error.value = e.response?.data?.message || 'Không thể kết nối API thống kê tác giả.'
  } finally {
    loading.value = false
  }
}

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

onMounted(() => {
  fetchStats()
})
</script>

<template>
  <div class="author-dashboard min-h-screen bg-slate-50 p-6 md:p-8">
    <Toast />
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Bảng điều khiển Tác giả</h1>
        <p class="text-slate-500 text-sm mt-1">Quản lý sáng tác, theo dõi doanh thu và cấu hình bản quyền DRM tác phẩm.</p>
      </div>
      <div class="flex gap-3">
        <Button label="Viết sách mới" icon="pi pi-plus" class="p-button-primary bg-indigo-600 hover:bg-indigo-700 text-white font-bold" @click="router.push({ name: 'vendor-books' })" />
        <Button label="Quản lý Kho" icon="pi pi-home" class="p-button-outlined p-button-secondary" @click="router.push({ name: 'vendor-warehouses' })" />
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-rose-50 border border-rose-200 rounded-2xl p-8 text-center space-y-4 my-6">
      <i class="pi pi-exclamation-triangle text-4xl text-rose-500"></i>
      <h3 class="text-lg font-bold text-rose-800">Không thể tải thông tin bảng điều khiển</h3>
      <p class="text-sm text-rose-600 max-w-md mx-auto">{{ error }}</p>
      <div class="flex justify-center gap-3 pt-2">
        <Button label="Thử lại" icon="pi pi-refresh" class="p-button-danger p-button-sm text-xs bg-rose-600 text-white" @click="fetchStats" />
      </div>
    </div>

    <template v-else>
      <!-- Stats Bento Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Wallet / Balance Card -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between relative overflow-hidden">
          <div class="absolute right-0 top-0 h-24 w-24 bg-indigo-50/50 rounded-full translate-x-8 -translate-y-8 flex items-center justify-center">
            <i class="pi pi-wallet text-indigo-200 text-4xl"></i>
          </div>
          <div class="z-10">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Số dư khả dụng</span>
            <h2 class="text-3xl font-black text-slate-900 mt-2">{{ formatCurrency(stats.balance) }}</h2>
          </div>
          <div class="mt-6 flex items-center justify-between text-xs font-medium text-slate-500">
            <span>Tổng thu nhập: {{ formatCurrency(stats.total_earnings) }}</span>
          </div>
        </div>

        <!-- Total Views -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between relative overflow-hidden">
          <div class="absolute right-0 top-0 h-24 w-24 bg-emerald-50/50 rounded-full translate-x-8 -translate-y-8 flex items-center justify-center">
            <i class="pi pi-eye text-emerald-200 text-4xl"></i>
          </div>
          <div class="z-10">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Lượt đọc tác phẩm</span>
            <h2 class="text-3xl font-black text-slate-900 mt-2">{{ stats.views_count.toLocaleString() }}</h2>
          </div>
          <div class="mt-6 flex items-center justify-between text-xs font-medium text-slate-500">
            <span>Tương tác chương: {{ stats.chapters_count }} chương</span>
          </div>
        </div>

        <!-- Published works -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between relative overflow-hidden">
          <div class="absolute right-0 top-0 h-24 w-24 bg-amber-50/50 rounded-full translate-x-8 -translate-y-8 flex items-center justify-center">
            <i class="pi pi-book text-amber-200 text-4xl"></i>
          </div>
          <div class="z-10">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tác phẩm xuất bản</span>
            <h2 class="text-3xl font-black text-slate-900 mt-2">{{ stats.books_count }} sách</h2>
          </div>
          <div class="mt-6 flex items-center justify-between text-xs font-medium text-slate-500">
            <span>Bản quyền DRM: Hệ thống quản lý</span>
          </div>
        </div>
      </div>

      <!-- Main Section: My Works & Fast Action -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Works List -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-base">Tác phẩm đang phát hành</h3>
            <Button label="Xem tất cả" class="p-button-text p-button-sm text-indigo-600" @click="router.push({ name: 'vendor-books' })" />
          </div>

          <div v-if="loading" class="p-6">
            <ProgressBar mode="indeterminate" style="height: 6px"></ProgressBar>
          </div>

          <div v-else class="divide-y divide-slate-100">
            <div v-for="book in books" :key="book.id" class="p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:bg-slate-50 transition-colors">
              <div class="flex items-center gap-4">
                <div class="w-12 h-16 bg-slate-100 border border-slate-200 rounded-lg flex items-center justify-center flex-shrink-0">
                  <i class="pi pi-book text-slate-400 text-xl"></i>
                </div>
                <div>
                  <h4 class="font-bold text-slate-800 text-sm">{{ book.title }}</h4>
                  <p class="text-xs text-slate-400 mt-1">
                    Phân loại: <span class="capitalize font-semibold text-slate-600">{{ book.type === 'ebook' ? 'Ebook tự viết' : 'Sách giấy cũ' }}</span>
                    <span v-if="book.chapters_count"> | {{ book.chapters_count }} chương</span>
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <!-- Price tag -->
                <span class="text-sm font-bold text-slate-700 mr-2">{{ formatCurrency(book.price) }}</span>

                <!-- Status Badge -->
                <span :class="[
                  'text-xs font-semibold px-2 py-1 rounded-full mr-2',
                  book.status === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'
                ]">
                  {{ book.status === 'published' ? 'Đã xuất bản' : 'Bản nháp' }}
                </span>

                <!-- Action buttons -->
                <div class="flex gap-2">
                  <Button v-if="book.type === 'ebook'" label="Soạn thảo" icon="pi pi-pencil" class="p-button-outlined p-button-sm text-xs p-1" @click="router.push({ name: 'author-live-editor', params: { bookId: book.id } })" />
                  <Button v-if="book.type === 'ebook'" label="Cấu hình DRM" icon="pi pi-shield" class="p-button-outlined p-button-sm text-xs p-1" @click="router.push({ name: 'vendor-book-drm', params: { bookId: book.id } })" />
                  <Button v-if="book.type === 'ebook'" label="Giá & Đọc thử" icon="pi pi-cog" class="p-button-outlined p-button-sm text-xs p-1" @click="router.push({ name: 'vendor-book-chapters', params: { bookId: book.id } })" />
                </div>
              </div>
            </div>

            <div v-if="books.length === 0" class="p-8 text-center text-slate-400 text-xs">
              Chưa có tác phẩm nào được phát hành.
            </div>
          </div>
        </div>

        <!-- Quick Actions Sidebar -->
        <div class="space-y-6">
          <!-- Shortcut Tools -->
          <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
            <h3 class="font-bold text-slate-800 text-base mb-4">Lối tắt nghiệp vụ</h3>
            <div class="grid grid-cols-1 gap-3">
              <button class="w-full flex items-center justify-between p-3 border border-slate-200 rounded-xl hover:bg-indigo-50 hover:border-indigo-200 transition-all text-left text-sm font-semibold text-slate-700 group" @click="router.push({ name: 'vendor-inventory-audits' })">
                <span class="flex items-center gap-3">
                  <i class="pi pi-check-square text-indigo-600 text-lg"></i>
                  Kiểm kê kho hàng
                </span>
                <i class="pi pi-arrow-right text-slate-300 group-hover:text-indigo-600 transition-colors"></i>
              </button>

              <button class="w-full flex items-center justify-between p-3 border border-slate-200 rounded-xl hover:bg-indigo-50 hover:border-indigo-200 transition-all text-left text-sm font-semibold text-slate-700 group" @click="router.push({ name: 'vendor-inventory-transfers' })">
                <span class="flex items-center gap-3">
                  <i class="pi pi-directions text-indigo-600 text-lg"></i>
                  Điều chuyển kho
                </span>
                <i class="pi pi-arrow-right text-slate-300 group-hover:text-indigo-600 transition-colors"></i>
              </button>

              <button class="w-full flex items-center justify-between p-3 border border-slate-200 rounded-xl hover:bg-indigo-50 hover:border-indigo-200 transition-all text-left text-sm font-semibold text-slate-700 group" @click="router.push({ name: 'vendor-finance' })">
                <span class="flex items-center gap-3">
                  <i class="pi pi-money-bill text-indigo-600 text-lg"></i>
                  Yêu cầu rút tiền
                </span>
                <i class="pi pi-arrow-right text-slate-300 group-hover:text-indigo-600 transition-colors"></i>
              </button>
            </div>
          </div>

          <!-- Help & FAQ Section -->
          <div class="bg-indigo-900 text-white rounded-2xl p-6 relative overflow-hidden">
            <div class="absolute right-0 bottom-0 opacity-10 translate-x-4 translate-y-4">
              <i class="pi pi-question-circle text-8xl"></i>
            </div>
            <h3 class="font-bold text-base mb-2">Hỗ trợ & Hướng dẫn</h3>
            <p class="text-xs text-indigo-200 leading-relaxed mb-4">Bạn gặp khó khăn trong việc thiết lập bản quyền tác phẩm hay cần hỗ trợ kỹ thuật về trình đọc?</p>
            <div class="flex gap-2">
              <Button label="Trung tâm Trợ giúp" class="p-button-sm p-button-secondary bg-indigo-800 hover:bg-indigo-700 text-white border-none font-bold" @click="router.push({ name: 'help-center' })" />
              <Button label="Gửi Ticket" class="p-button-sm p-button-outlined text-white border-white hover:bg-indigo-800" @click="router.push({ name: 'customer-support' })" />
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.author-dashboard {
  font-family: 'Inter', sans-serif;
}
</style>

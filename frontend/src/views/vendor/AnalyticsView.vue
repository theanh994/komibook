<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()
const loading = ref(true)
const errorMessage = ref('')

const analyticsData = ref({
  top_selling_books: [],
  top_readers: [],
  sales_by_category: [],
  reviews_stats: []
})

const fetchAnalytics = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await apiClient.get('/api/vendor/analytics')
    if (res.data.status === 'success') {
      analyticsData.value = res.data.data
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải dữ liệu phân tích.', life: 3000 })
    errorMessage.value = 'Không thể tải dữ liệu phân tích của gian hàng. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchAnalytics()
})

const formatVND = (val) => new Intl.NumberFormat('vi-VN').format(val || 0) + ' ₫'

const totalReviews = computed(() => {
  return analyticsData.value.reviews_stats.reduce((sum, item) => sum + item.count, 0)
})

const avgRating = computed(() => {
  if (totalReviews.value === 0) return 0
  const sum = analyticsData.value.reviews_stats.reduce((acc, item) => acc + (item.rating * item.count), 0)
  return (sum / totalReviews.value).toFixed(1)
})
</script>

<template>
  <div class="pb-xl w-full pt-6">
    <div class="flex justify-between items-end mb-lg">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-primary">Phân tích bán hàng</h1>
        <p class="text-on-surface-variant mt-xs">Tổng quan hiệu suất bán hàng và khách hàng</p>
      </div>
      <div class="flex gap-sm">
        <button @click="fetchAnalytics" class="border border-outline text-on-surface px-md py-xs rounded-lg font-label-md text-label-md flex items-center gap-xs hover:bg-surface-container-low transition-colors">
          <span class="material-symbols-outlined text-[18px]">refresh</span>
          Làm mới
        </button>
      </div>
    </div>

    <div v-if="errorMessage" class="mb-lg flex flex-col gap-3 rounded-xl border border-error/30 bg-error-container/30 p-4 text-on-error-container sm:flex-row sm:items-center sm:justify-between" role="alert">
      <span>{{ errorMessage }}</span>
      <button type="button" class="min-h-11 rounded-lg border border-error/40 px-4 font-bold" @click="fetchAnalytics">Thử lại</button>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-12 gap-gutter animate-pulse">
      <div class="md:col-span-8 bg-surface-container-lowest h-64 rounded-xl"></div>
      <div class="md:col-span-4 bg-surface-container-lowest h-64 rounded-xl"></div>
    </div>

    <!-- Bento Grid Layout -->
    <div v-else-if="!errorMessage" class="grid grid-cols-1 md:grid-cols-12 gap-gutter animate-slide-up">
      <!-- Top Books (Span 8) -->
      <div class="md:col-span-8 bg-surface-container-lowest rounded-xl p-md shadow-[0px_12px_24px_0px_rgba(26,58,90,0.04)] border border-surface-container-high">
        <h3 class="font-headline-md text-[18px] text-primary mb-4">Sách bán chạy nhất</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="text-on-surface-variant text-sm border-b border-outline-variant/30">
                <th class="py-2 font-medium">Tên sách</th>
                <th class="py-2 font-medium text-right">Số lượng bán</th>
                <th class="py-2 font-medium text-right">Doanh thu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10 text-body-md">
              <tr v-for="book in analyticsData.top_selling_books" :key="book.id" class="hover:bg-surface-container-low">
                <td class="py-3 font-medium text-on-surface">
                  <span class="flex items-center gap-3">
                    <img v-if="book.cover_image" :src="book.cover_image" :alt="`Bìa ${book.title}`" class="h-14 w-10 shrink-0 object-contain" />
                    <span>{{ book.title }}</span>
                  </span>
                </td>
                <td class="py-3 text-right">{{ book.total_sold }}</td>
                <td class="py-3 text-right text-primary font-bold">{{ formatVND(book.total_revenue) }}</td>
              </tr>
              <tr v-if="analyticsData.top_selling_books.length === 0">
                <td colspan="3" class="text-center py-4 text-on-surface-variant">Chưa có dữ liệu</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Review Summary (Span 4) -->
      <div class="md:col-span-4 bg-surface-container-lowest rounded-xl p-lg shadow-[0px_12px_24px_0px_rgba(26,58,90,0.04)] border border-surface-container-high flex flex-col justify-center items-center text-center">
        <h4 class="font-label-md text-label-md text-on-surface-variant mb-xs">Đánh giá trung bình</h4>
        <div class="relative w-32 h-32 flex items-center justify-center my-md">
          <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
            <path class="text-surface-container-high" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-dasharray="100, 100" stroke-width="3"></path>
            <path class="text-yellow-400" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" :stroke-dasharray="`${(avgRating/5)*100}, 100`" stroke-linecap="round" stroke-width="3"></path>
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="font-headline-lg text-[28px] text-yellow-500 font-bold leading-none">{{ avgRating }}</span>
            <span class="text-[12px] text-on-surface-variant mt-1">/ 5.0</span>
          </div>
        </div>
        <p class="text-body-md text-on-surface-variant text-sm">Dựa trên <span class="text-on-surface font-medium">{{ totalReviews }}</span> đánh giá</p>
      </div>

      <!-- Top Readers (Span 7) -->
      <div class="md:col-span-7 bg-surface-container-lowest rounded-xl p-md shadow-[0px_12px_24px_0px_rgba(26,58,90,0.04)] border border-surface-container-high mt-4">
        <h3 class="font-headline-md text-[18px] text-primary mb-4">Khách hàng chi tiêu nhiều nhất</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="text-on-surface-variant text-sm border-b border-outline-variant/30">
                <th class="py-2 font-medium">Khách hàng</th>
                <th class="py-2 font-medium text-right">Tổng đơn</th>
                <th class="py-2 font-medium text-right">Tổng chi tiêu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10 text-body-md">
              <tr v-for="user in analyticsData.top_readers" :key="user.id" class="hover:bg-surface-container-low">
                <td class="py-3">
                  <div class="font-medium text-on-surface">{{ user.name }}</div>
                  <div class="text-[12px] text-on-surface-variant">{{ user.email }}</div>
                </td>
                <td class="py-3 text-right">{{ user.total_orders }}</td>
                <td class="py-3 text-right text-primary font-bold">{{ formatVND(user.total_spent) }}</td>
              </tr>
              <tr v-if="analyticsData.top_readers.length === 0">
                <td colspan="3" class="text-center py-4 text-on-surface-variant">Chưa có dữ liệu</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Categories (Span 5) -->
      <div class="md:col-span-5 bg-surface-container-lowest rounded-xl p-md shadow-[0px_12px_24px_0px_rgba(26,58,90,0.04)] border border-surface-container-high mt-4">
        <h3 class="font-headline-md text-[18px] text-primary mb-4">Doanh số theo danh mục</h3>
        <div class="space-y-4">
          <div v-for="cat in analyticsData.sales_by_category" :key="cat.id">
            <div class="flex justify-between text-[13px] mb-1">
              <span class="text-on-surface">{{ cat.name }}</span>
              <span class="font-medium text-primary">{{ cat.total_sold }} bản</span>
            </div>
            <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
              <div class="bg-primary-container h-full" :style="{ width: `${Math.min(100, cat.total_sold * 5)}%` }"></div>
            </div>
          </div>
          <div v-if="analyticsData.sales_by_category.length === 0" class="text-center py-4 text-on-surface-variant">
            Chưa có dữ liệu
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-slide-up {
  opacity: 0;
  transform: translateY(20px);
  animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

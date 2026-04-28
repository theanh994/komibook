<template>
  <div class="min-h-screen bg-surface-50 dark:bg-surface-900 p-4 md:p-8">
    <div class="max-w-5xl mx-auto">
      
      <!-- Back Button -->
      <button @click="$router.back()" class="mb-6 flex items-center gap-2 text-surface-500 hover:text-primary transition-colors font-semibold">
        <i class="pi pi-arrow-left"></i> Quay lại
      </button>

      <div v-if="loading" class="flex justify-center items-center h-64 bg-white dark:bg-surface-800 rounded-xl shadow-sm border border-surface-200 dark:border-surface-700">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
      </div>

      <div v-else-if="!book" class="flex flex-col items-center justify-center h-64 bg-white dark:bg-surface-800 rounded-xl shadow-sm border border-surface-200 dark:border-surface-700 text-surface-500">
        <i class="pi pi-exclamation-circle text-6xl mb-4 opacity-50 text-red-400"></i>
        <p class="text-lg">Không tìm thấy thông tin sách.</p>
        <Button label="Về trang chủ" class="mt-4" @click="$router.push('/')" />
      </div>

      <div v-else class="bg-white dark:bg-surface-800 rounded-xl shadow-sm border border-surface-200 dark:border-surface-700 overflow-hidden">
        <div class="flex flex-col md:flex-row">
          
          <!-- Image Section -->
          <div class="w-full md:w-2/5 md:border-r border-surface-200 dark:border-surface-700 p-8 flex justify-center bg-surface-100 dark:bg-surface-800/50">
            <div class="w-full max-w-sm rounded-lg overflow-hidden shadow-xl aspect-[2/3] bg-white relative">
              <img 
                v-if="book.cover_image" 
                :src="book.cover_image" 
                :alt="book.title" 
                class="w-full h-full object-cover"
              />
              <div v-else class="w-full h-full flex items-center justify-center text-surface-300">
                <i class="pi pi-image text-6xl"></i>
              </div>
            </div>
          </div>

          <!-- Info Section -->
          <div class="w-full md:w-3/5 p-6 md:p-10 flex flex-col">
            <!-- Badges -->
            <div class="flex gap-2 mb-4">
              <Badge v-if="book.category" :value="book.category.name" severity="secondary" />
              <Badge :value="book.type === 'ebook' ? 'E-Book' : 'Sách giấy'" :severity="book.type === 'ebook' ? 'info' : 'success'" />
            </div>

            <h1 class="text-3xl md:text-4xl font-bold mb-2">{{ book.title }}</h1>
            
            <div class="flex items-center gap-2 mb-2" v-if="book.reviews && book.reviews.length > 0">
              <Rating :modelValue="averageRating" readonly :cancel="false" />
              <span class="text-sm text-surface-500">({{ book.reviews.length }} đánh giá)</span>
            </div>

            <p class="text-lg text-surface-500 dark:text-surface-400 mb-6">Tác giả: <span class="font-semibold text-surface-800 dark:text-surface-100">{{ book.author || 'Đang cập nhật' }}</span></p>

            <!-- Price Box -->
            <div class="bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-xl p-6 mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <div class="text-sm text-surface-500 uppercase tracking-wider font-semibold mb-1">Giá bán</div>
                <div class="flex items-baseline gap-3">
                  <span class="text-3xl font-bold text-primary">{{ formatCurrency(book.sale_price || book.price) }}</span>
                  <span v-if="book.sale_price && book.price > book.sale_price" class="text-lg text-surface-400 line-through">
                    {{ formatCurrency(book.price) }}
                  </span>
                </div>
              </div>

              <Button 
                label="Thêm vào giỏ hàng" 
                icon="pi pi-shopping-cart" 
                size="large" 
                @click="addToCart"
              />
            </div>

            <!-- Details -->
            <div class="grid grid-cols-2 gap-4 mb-8 text-sm">
              <div class="p-3 bg-surface-50 dark:bg-surface-900 rounded-lg">
                <div class="text-surface-500 mb-1">Nhà bán (Vendor)</div>
                <div class="font-semibold">{{ book.vendor?.name || 'KomiBook' }}</div>
              </div>
              <div class="p-3 bg-surface-50 dark:bg-surface-900 rounded-lg">
                <div class="text-surface-500 mb-1">Tình trạng</div>
                <div class="font-semibold text-green-600">Còn hàng ({{ book.stock }})</div>
              </div>
              <div class="p-3 bg-surface-50 dark:bg-surface-900 rounded-lg">
                <div class="text-surface-500 mb-1">ISBN</div>
                <div class="font-semibold">{{ book.isbn || 'N/A' }}</div>
              </div>
            </div>

            <!-- Description -->
            <div class="mt-auto pt-6 border-t border-surface-200 dark:border-surface-700">
              <h3 class="text-lg font-bold mb-3">Giới thiệu sách</h3>
              <div class="prose dark:prose-invert max-w-none text-surface-600 dark:text-surface-300 leading-relaxed whitespace-pre-line">
                {{ book.description || 'Chưa có thông tin giới thiệu cho cuốn sách này.' }}
              </div>
            </div>

            <!-- Reviews Section -->
            <div class="mt-8 pt-8 border-t border-surface-200 dark:border-surface-700">
              <h3 class="text-xl font-bold mb-6">Đánh giá từ khách hàng</h3>
              
              <!-- Review Form -->
              <div class="bg-surface-50 dark:bg-surface-900 rounded-xl p-6 mb-8 border border-surface-200 dark:border-surface-700">
                <h4 class="font-semibold mb-3">Viết đánh giá của bạn</h4>
                <div class="flex flex-col gap-4">
                  <div>
                    <label class="block text-sm mb-2 text-surface-600">Đánh giá sao</label>
                    <Rating v-model="reviewForm.rating" :cancel="false" />
                  </div>
                  <div>
                    <label class="block text-sm mb-2 text-surface-600">Nội dung</label>
                    <Textarea v-model="reviewForm.comment" rows="3" class="w-full !rounded-lg" placeholder="Chia sẻ cảm nhận của bạn về cuốn sách này..."></Textarea>
                  </div>
                  <div class="flex justify-end">
                    <Button label="Gửi đánh giá" :loading="isSubmittingReview" @click="submitReview" />
                  </div>
                </div>
              </div>

              <!-- Reviews List -->
              <div v-if="book.reviews && book.reviews.length > 0" class="space-y-6">
                <div v-for="review in book.reviews" :key="review.id" class="border-b border-surface-100 dark:border-surface-800 pb-6 last:border-0 last:pb-0">
                  <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold">
                      {{ review.user?.name?.charAt(0) || 'U' }}
                    </div>
                    <div>
                      <div class="font-medium">{{ review.user?.name || 'Người dùng ẩn danh' }}</div>
                      <div class="text-xs text-surface-400">{{ new Date(review.created_at).toLocaleDateString('vi-VN') }}</div>
                    </div>
                  </div>
                  <Rating :modelValue="review.rating" readonly :cancel="false" class="mb-2 !text-sm" />
                  <p class="text-surface-700 dark:text-surface-300 text-sm leading-relaxed">{{ review.comment }}</p>
                </div>
              </div>
              <div v-else class="text-center py-8 text-surface-500 bg-surface-50 dark:bg-surface-900 rounded-xl">
                Chưa có đánh giá nào. Hãy là người đầu tiên đánh giá cuốn sách này!
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Badge from 'primevue/badge'
import Rating from 'primevue/rating'
import Textarea from 'primevue/textarea'

const route = useRoute()
const toast = useToast()

const book = ref(null)
const loading = ref(true)

const fetchBookDetail = async () => {
  loading.value = true
  try {
    const response = await apiClient.get(`/api/books/${route.params.slug}`)
    const responseData = response.data.data || response.data
    // Backend returns data => new BookResource(book)
    book.value = responseData
  } catch (error) {
    console.error('Lỗi tải chi tiết sách:', error)
  } finally {
    loading.value = false
  }
}

const averageRating = computed(() => {
  if (!book.value || !book.value.reviews || book.value.reviews.length === 0) return 0
  const sum = book.value.reviews.reduce((acc, curr) => acc + curr.rating, 0)
  return Math.round(sum / book.value.reviews.length)
})

const reviewForm = ref({ rating: 5, comment: '' })
const isSubmittingReview = ref(false)

const submitReview = async () => {
  if (!reviewForm.value.rating) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng chọn số sao đánh giá.', life: 3000 })
    return
  }

  isSubmittingReview.value = true
  try {
    const response = await apiClient.post(`/api/books/${book.value.id}/reviews`, reviewForm.value)
    toast.add({ severity: 'success', summary: 'Thành công', detail: response.data.message || 'Cảm ơn bạn đã đánh giá!', life: 3000 })
    
    // Thêm review mới vào danh sách hiện tại
    if (!book.value.reviews) book.value.reviews = []
    book.value.reviews.unshift(response.data.data)
    
    // Reset form
    reviewForm.value = { rating: 5, comment: '' }
  } catch (error) {
    console.error(error)
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi gửi đánh giá'
    if (error.response?.status === 401) {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Bạn cần đăng nhập để đánh giá.', life: 5000 })
    } else {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 5000 })
    }
  } finally {
    isSubmittingReview.value = false
  }
}

const addToCart = () => {
  toast.add({
    severity: 'success',
    summary: 'Thành công',
    detail: `Đã thêm "${book.value.title}" vào giỏ hàng!`,
    life: 3000
  })
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

onMounted(() => {
  if (route.params.slug) {
    fetchBookDetail()
  }
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center gap-3 mb-8">
      <i class="pi pi-book text-3xl text-primary"></i>
      <h1 class="text-3xl font-black text-surface-900 dark:text-surface-0 m-0 tracking-tight">
        Tủ Sách Của Tôi
      </h1>
    </div>

    <!-- Trạng thái loading -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
    </div>

    <!-- Khi không có đơn hàng -->
    <div v-else-if="orders.length === 0" class="text-center py-16 bg-white dark:bg-surface-800 rounded-2xl shadow-sm border border-surface-100 dark:border-surface-700">
      <div class="w-20 h-20 bg-surface-50 dark:bg-surface-700 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="pi pi-inbox text-4xl text-surface-400"></i>
      </div>
      <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50 mb-2">Chưa có đơn hàng nào</h3>
      <p class="text-surface-500 mb-6 max-w-md mx-auto">Bạn chưa mua cuốn sách nào. Hãy khám phá hàng ngàn cuốn sách hấp dẫn trên KomiBook nhé!</p>
      <Button label="Khám phá ngay" icon="pi pi-search" class="font-bold px-6 py-3 shadow-md" @click="$router.push('/')" />
    </div>

    <!-- Danh sách đơn hàng -->
    <div v-else class="space-y-6">
      <div v-for="order in orders" :key="order.id" class="bg-white dark:bg-surface-800 rounded-2xl shadow-sm border border-surface-100 dark:border-surface-700 overflow-hidden transition-all duration-300 hover:shadow-md">
        
        <!-- Order Header -->
        <div class="bg-surface-50 dark:bg-surface-800/50 p-4 sm:px-6 border-b border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="font-mono font-bold text-primary">{{ order.order_code }}</span>
              <span class="text-surface-400 text-sm">•</span>
              <span class="text-surface-500 text-sm">{{ formatDate(order.created_at) }}</span>
            </div>
            <div class="text-sm font-medium">
              Tổng tiền: <span class="text-red-500 font-bold">{{ formatCurrency(order.total_amount) }}</span>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span :class="['px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider', getStatusClass(order.status)]">
              {{ getStatusLabel(order.status) }}
            </span>
          </div>
        </div>

        <!-- Order Items -->
        <div class="p-4 sm:p-6">
          <div class="space-y-4">
            <div v-for="item in order.items" :key="item.id" class="flex flex-col sm:flex-row gap-4 py-4 first:pt-0 last:pb-0 border-b border-surface-100 dark:border-surface-700 last:border-0">
              
              <!-- Book Cover -->
              <div class="w-24 h-32 flex-shrink-0 rounded-lg overflow-hidden bg-surface-100 shadow-sm border border-surface-200">
                <img v-if="item.book.cover_image" :src="item.book.cover_image" :alt="item.book.title" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center text-surface-400">
                  <i class="pi pi-image text-2xl"></i>
                </div>
              </div>

              <!-- Book Details -->
              <div class="flex-1 flex flex-col justify-between">
                <div>
                  <h4 class="text-lg font-bold text-surface-900 dark:text-surface-50 mb-1 line-clamp-2">
                    {{ item.book.title }}
                  </h4>
                  <div class="flex items-center gap-2 text-sm text-surface-500 mb-2">
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-surface-100 dark:bg-surface-700">
                      {{ item.book.type === 'ebook' ? 'E-book' : 'Sách in' }}
                    </span>
                    <span>SL: {{ item.quantity }}</span>
                  </div>
                </div>

                <!-- Actions Based on Book Type -->
                <div class="mt-4 flex items-center">
                  <template v-if="item.book.type === 'ebook'">
                    <button 
                      @click="readEbook(order.id, item.book.id)"
                      :disabled="readingLoading === `${order.id}-${item.book.id}`"
                      class="relative overflow-hidden group px-6 py-2.5 rounded-xl font-bold text-white shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg disabled:opacity-70 disabled:cursor-not-allowed disabled:transform-none bg-gradient-to-r from-indigo-500 to-purple-600"
                    >
                      <span class="relative z-10 flex items-center gap-2">
                        <i v-if="readingLoading === `${order.id}-${item.book.id}`" class="pi pi-spin pi-spinner"></i>
                        <i v-else class="pi pi-book"></i>
                        Đọc E-book
                      </span>
                      <div class="absolute inset-0 h-full w-full bg-gradient-to-r from-purple-600 to-indigo-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    </button>
                  </template>
                  <template v-else>
                    <div class="flex items-center gap-2 text-surface-500 text-sm font-medium bg-surface-50 dark:bg-surface-800 px-4 py-2 rounded-lg">
                      <i class="pi pi-truck text-primary"></i>
                      <span>Trạng thái: {{ order.status === 'completed' ? 'Đã giao hàng' : 'Đang giao hàng' }}</span>
                    </div>
                  </template>
                </div>
              </div>
              
              <!-- Price -->
              <div class="sm:text-right font-bold text-surface-900 dark:text-surface-50">
                {{ formatCurrency(item.price) }}
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import apiClient from '@/services/axios'

const router = useRouter()
const toast = useToast()
const orders = ref([])
const loading = ref(true)
const readingLoading = ref(null)

const fetchOrders = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/my-orders')
    orders.value = response.data.data
  } catch (error) {
    console.error('Lỗi khi tải đơn hàng:', error)
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể tải danh sách đơn hàng. Vui lòng thử lại sau.',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const readEbook = async (orderId, bookId) => {
  const identifier = `${orderId}-${bookId}`
  readingLoading.value = identifier
  
  try {
    // Chuyển hướng sang trang đọc E-book (trang này sẽ tự call API generate-link)
    router.push({
      name: 'ebook-reader',
      params: { orderId, bookId }
    })
  } catch (error) {
    console.error('Lỗi khi mở ebook:', error)
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể mở E-book lúc này.',
      life: 3000
    })
  } finally {
    readingLoading.value = null
  }
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

const getStatusLabel = (status) => {
  const labels = {
    'pending': 'Chờ xử lý',
    'processing': 'Đang xử lý',
    'shipping': 'Đang giao hàng',
    'completed': 'Hoàn thành',
    'cancelled': 'Đã hủy'
  }
  return labels[status] || status
}

const getStatusClass = (status) => {
  const classes = {
    'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    'processing': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    'shipping': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
    'completed': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    'cancelled': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
  }
  return classes[status] || 'bg-surface-100 text-surface-800'
}

onMounted(() => {
  fetchOrders()
})
</script>

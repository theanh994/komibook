<template>
  <div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Lịch sử Đơn hàng</h1>
          <p class="text-gray-500 mt-1">Quản lý và theo dõi các đơn hàng bạn đã mua trên KomiBook</p>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center items-center py-12">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
      </div>

      <div v-else-if="orders.length === 0" class="text-center py-16 bg-gray-50 rounded-xl border border-dashed border-gray-200">
        <i class="pi pi-box text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">Chưa có đơn hàng nào</h3>
        <p class="text-gray-500 mb-6">Bạn chưa thực hiện giao dịch nào trên hệ thống.</p>
        <router-link to="/" class="inline-flex items-center justify-center px-6 py-3 bg-primary text-white rounded-xl font-medium hover:bg-primary/90 transition-colors">
          Khám phá sách ngay
        </router-link>
      </div>

      <div v-else class="space-y-6">
        <!-- Order Card -->
        <div v-for="order in orders" :key="order.id" class="border border-gray-100 rounded-xl overflow-hidden hover:border-primary/20 transition-colors shadow-sm">
          <!-- Order Header -->
          <div class="bg-gray-50/50 px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-6">
              <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Mã đơn hàng</p>
                <p class="font-bold text-gray-900">#{{ order.order_code || order.id }}</p>
              </div>
              <div class="hidden sm:block w-px h-8 bg-gray-200"></div>
              <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Ngày đặt</p>
                <p class="font-medium text-gray-700">{{ formatDate(order.created_at) }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <span class="px-3 py-1 text-sm font-medium rounded-full" :class="getStatusClass(order.status)">
                {{ getStatusText(order.status) }}
              </span>
              <span class="px-3 py-1 text-sm font-medium rounded-full bg-blue-50 text-blue-700 border border-blue-100">
                {{ order.payment_method }}
              </span>
            </div>
          </div>

          <!-- Order Items -->
          <div class="px-6 py-4">
            <div class="space-y-4">
              <div v-for="item in order.items" :key="item.id" class="flex gap-4 items-start">
                <div class="w-16 h-20 bg-gray-100 rounded-lg overflow-hidden shrink-0 shadow-sm border border-gray-100">
                  <img v-if="item.book?.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="item.book.title" class="w-full h-full object-cover">
                  <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                    <i class="pi pi-image text-xl"></i>
                  </div>
                </div>
                <div class="flex-grow min-w-0">
                  <h4 class="font-semibold text-gray-900 truncate">{{ item.book?.title || 'Sách không còn tồn tại' }}</h4>
                  <p class="text-sm text-gray-500 mt-1">Số lượng: {{ item.quantity }}</p>
                  <p class="text-sm font-medium text-primary mt-1">{{ formatCurrency(item.price) }}</p>
                </div>
                <div class="shrink-0 pt-1" v-if="item.book?.type === 'ebook' && order.status === 'completed'">
                  <router-link :to="`/reader/${order.id}/${item.book.id}`" class="text-sm font-medium text-primary hover:text-primary/80 flex items-center gap-1 bg-primary/5 px-3 py-1.5 rounded-lg">
                    <i class="pi pi-book text-xs"></i>
                    Đọc sách
                  </router-link>
                </div>
              </div>
            </div>
          </div>

          <!-- Order Footer -->
          <div class="bg-gray-50/50 px-6 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-sm text-gray-500">
              Giao đến: <span class="font-medium text-gray-700">{{ order.shipping_address || 'Không yêu cầu giao hàng' }}</span>
            </p>
            <div class="text-right">
              <p class="text-sm text-gray-500 mb-0.5">Tổng tiền</p>
              <p class="text-lg font-bold text-primary">{{ formatCurrency(order.total_amount) }}</p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import axios from '@/services/axios'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const orders = ref([])
const loading = ref(true)

const fetchOrders = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/my-orders')
    const fetchedOrders = response.data.data || response.data || []
    // Sắp xếp đơn hàng mới nhất lên đầu
    orders.value = fetchedOrders.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  } catch (error) {
    console.error('Lỗi khi tải lịch sử đơn hàng:', error)
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể tải lịch sử đơn hàng',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const checkPaymentStatus = () => {
  if (route.query.payment === 'success') {
    toast.add({
      severity: 'success',
      summary: 'Thanh toán thành công',
      detail: 'Cảm ơn bạn đã mua hàng bằng VNPAY!',
      life: 5000
    })
    // Xóa query parameter để không hiện lại khi f5
    router.replace({ query: {} })
  } else if (route.query.payment === 'failed') {
    toast.add({
      severity: 'error',
      summary: 'Thanh toán thất bại',
      detail: 'Giao dịch VNPAY đã bị hủy hoặc có lỗi xảy ra.',
      life: 5000
    })
    router.replace({ query: {} })
  } else if (route.query.payment === 'invalid_signature') {
    toast.add({
      severity: 'warn',
      summary: 'Lỗi bảo mật',
      detail: 'Chữ ký giao dịch không hợp lệ.',
      life: 5000
    })
    router.replace({ query: {} })
  }
}

onMounted(() => {
  checkPaymentStatus()
  fetchOrders()
})

// Helpers
const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('vi-VN', {
    hour: '2-digit', minute: '2-digit',
    day: '2-digit', month: '2-digit', year: 'numeric'
  }).format(date)
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  const baseUrl = import.meta.env.VITE_API_URL || ''
  return `${baseUrl}/storage/${path}`
}

const getStatusText = (status) => {
  const map = {
    'pending': 'Chờ xử lý',
    'processing': 'Đang xử lý / Đã thanh toán',
    'shipped': 'Đang giao hàng',
    'completed': 'Hoàn thành',
    'cancelled': 'Đã hủy'
  }
  return map[status] || status
}

const getStatusClass = (status) => {
  const map = {
    'pending': 'bg-amber-50 text-amber-700 border border-amber-200',
    'processing': 'bg-blue-50 text-blue-700 border border-blue-200',
    'shipped': 'bg-indigo-50 text-indigo-700 border border-indigo-200',
    'completed': 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    'cancelled': 'bg-red-50 text-red-700 border border-red-200'
  }
  return map[status] || 'bg-gray-50 text-gray-700 border border-gray-200'
}
</script>

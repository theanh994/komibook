<template>
  <div class="min-h-screen bg-background">
    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl flex flex-col lg:flex-row items-stretch gap-xl">
      
      <!-- Sidebar -->
      <UserSidebar :user="authStore.user" />

      <!-- Main Content -->
      <main class="flex-1 min-w-0 w-full flex flex-col">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden flex-1 flex flex-col">
          <div class="p-lg md:p-xl border-b border-outline-variant/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h1 class="text-2xl font-black text-on-surface tracking-tight mb-1">Đơn hàng của tôi</h1>
              <p class="text-sm text-on-surface-variant font-medium">Theo dõi lịch sử mua sắm và trạng thái đơn hàng của bạn.</p>
            </div>
            
            <div class="flex p-1 bg-surface-container-low rounded-xl border border-outline-variant/20">
              <button 
                v-for="filter in statusFilters" 
                :key="filter.value"
                @click="currentFilter = filter.value"
                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all border-none cursor-pointer whitespace-nowrap"
                :class="currentFilter === filter.value ? 'bg-primary text-on-primary shadow-xs' : 'text-outline hover:text-on-surface'"
              >
                {{ filter.label }}
              </button>
            </div>
          </div>

          <div class="p-lg md:p-xl">
            <!-- Loading -->
            <div v-if="loading" class="py-20 flex flex-col items-center gap-4">
              <div class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></div>
              <p class="text-sm font-bold text-outline">Đang tải đơn hàng...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="filteredOrders.length === 0" class="py-16 text-center animate-fade-in">
              <div class="w-20 h-20 bg-surface-container-high rounded-3xl flex items-center justify-center mx-auto mb-4 text-outline/40 border border-outline-variant/10">
                <span class="material-symbols-outlined text-4xl">inventory_2</span>
              </div>
              <h3 class="text-lg font-bold text-on-surface mb-1 tracking-tight">Chưa có đơn hàng nào</h3>
              <p class="text-xs text-on-surface-variant mb-6 max-w-xs mx-auto font-medium leading-relaxed">
                Có vẻ như bạn chưa đặt mua sản phẩm nào. Hãy khám phá kho sách khổng lồ của chúng tôi!
              </p>
              <button @click="$router.push('/catalog')" class="bg-primary text-on-primary px-5 py-2.5 rounded-xl font-bold text-xs shadow-md hover:bg-primary/90 active:scale-95 transition-all border-none cursor-pointer flex items-center gap-2 mx-auto">
                <span>Khám phá KomiBook</span>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
              </button>
            </div>

            <!-- Orders List -->
            <div v-else class="space-y-lg">
              <div v-for="order in filteredOrders" :key="order.id" class="bg-surface-container-low/30 rounded-2xl border border-outline-variant/20 overflow-hidden hover:border-primary/30 transition-all group animate-fade-in">
                <!-- Order Header -->
                <div class="p-lg bg-surface-container-low/50 border-b border-outline-variant/10 flex flex-wrap items-center justify-between gap-4">
                  <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                      <span class="material-symbols-outlined">inventory_2</span>
                    </div>
                    <div>
                      <div class="text-sm font-black text-on-surface uppercase tracking-wider">#{{ order.order_code || order.id }}</div>
                      <div class="text-[10px] font-bold text-outline">{{ formatDate(order.created_at) }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-3">
                    <span :class="['px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider shadow-sm', getStatusStyle(order.status)]">
                      {{ getStatusText(order.status) }}
                    </span>
                    <span :class="['px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider shadow-sm', getPaymentStatusStyle(order.payment_status)]">
                      {{ getPaymentStatusText(order.payment_status) }}
                    </span>
                    <span class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-surface-container-highest text-on-surface-variant border border-outline-variant/30">
                      {{ getPaymentMethodText(order.payment_method) }}
                    </span>
                  </div>
                </div>

                <!-- Order Items -->
                <div class="p-lg space-y-md">
                  <div v-for="item in order.items" :key="item.id" class="flex items-start gap-md">
                    <div class="w-16 h-20 rounded-lg overflow-hidden bg-surface-container-high shrink-0 shadow-sm border border-outline-variant/10">
                      <img v-if="item.book?.cover_image" :src="getCoverUrl(item.book.cover_image)" :alt="item.book.title" class="w-full h-full object-cover" />
                      <div v-else class="w-full h-full flex items-center justify-center text-outline">
                        <span class="material-symbols-outlined text-xl">image</span>
                      </div>
                    </div>
                    <div class="flex-1 min-w-0">
                      <h4 class="text-sm font-bold text-on-surface truncate leading-tight mb-1">{{ item.book?.title || 'Sách không còn tồn tại' }}</h4>
                      <div class="flex items-center gap-2 mb-1">
                        <span class="text-[10px] font-black text-outline uppercase tracking-tighter px-1.5 py-0.5 bg-surface-container-low rounded border border-outline-variant/20">
                          {{ item.book?.type === 'ebook' ? 'E-book' : 'Sách giấy' }}
                        </span>
                        <span class="text-xs text-on-surface-variant font-medium">x{{ item.quantity }}</span>
                      </div>
                      <div class="text-sm font-bold text-primary">{{ formatCurrency(item.price) }}</div>
                    </div>
                    <div v-if="order.status !== 'cancelled'" class="shrink-0 flex flex-col gap-2">
                      <button 
                        v-if="item.book?.type === 'ebook' && order.status === 'completed'" 
                        @click="$router.push(`/reader/${order.id}/${item.book.id}`)" 
                        class="px-4 py-2 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-on-primary transition-all border-none cursor-pointer flex items-center gap-1 text-xs font-bold"
                      >
                        <span class="material-symbols-outlined text-[18px]">auto_stories</span>
                        Đọc ngay
                      </button>
                      <button 
                        v-if="item.book?.type === 'physical'" 
                        @click="$router.push(`/tracking/${order.id}`)" 
                        class="px-4 py-2 rounded-xl bg-secondary/10 text-secondary hover:bg-secondary hover:text-on-secondary transition-all border-none cursor-pointer flex items-center gap-1 text-xs font-bold"
                      >
                        <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                        Theo dõi
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Order Footer -->
                <div class="p-lg pt-0 flex items-center justify-between">
                  <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-outline uppercase tracking-wider mb-1">Tổng thanh toán</span>
                    <span class="text-lg font-black text-primary">{{ formatCurrency(order.total_amount) }}</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <button 
                      v-if="order.status === 'pending' && order.payment_status === 'unpaid' && (order.payment_method === 'online' || order.payment_method === 'VNPAY')"
                      @click="payNow(order)"
                      :disabled="payingOrderId === order.id"
                      class="px-4 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary/90 transition-all border-none cursor-pointer flex items-center gap-1 text-xs font-bold shadow-sm disabled:opacity-50 animate-pulse"
                    >
                      <span v-if="payingOrderId === order.id" class="w-4 h-4 border-2 border-on-primary/20 border-t-on-primary rounded-full animate-spin"></span>
                      <span v-else class="material-symbols-outlined text-[18px]">credit_card</span>
                      Thanh toán ngay
                    </button>
                    <button @click="showOrderDetail(order)" class="text-xs font-black uppercase text-secondary hover:underline bg-transparent border-none cursor-pointer flex items-center gap-1">
                      Xem chi tiết
                      <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Order Detail Modal (Optional but good) -->
    <Dialog v-model:visible="orderDetailVisible" :header="selectedOrder ? 'Chi tiết đơn hàng #' + (selectedOrder.order_code || selectedOrder.id) : 'Chi tiết đơn hàng'" :modal="true" class="!rounded-3xl !border-none !shadow-2xl" :style="{width: '600px'}">
      <div v-if="selectedOrder" class="space-y-xl py-md animate-fade-in">
         <div class="p-lg bg-surface-container-low rounded-2xl border border-outline-variant/10 space-y-md">
            <div class="flex justify-between">
              <span class="text-sm text-on-surface-variant font-medium">Địa chỉ giao hàng</span>
              <span class="text-sm font-bold text-on-surface text-right max-w-[250px]">{{ selectedOrder.shipping_address }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-on-surface-variant font-medium">Phương thức</span>
              <span class="text-sm font-bold text-on-surface">{{ getPaymentMethodText(selectedOrder.payment_method) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-on-surface-variant font-medium">Trạng thái thanh toán</span>
              <span class="text-sm font-bold text-on-surface">{{ getPaymentStatusText(selectedOrder.payment_status) }}</span>
            </div>
            <div class="flex justify-between pt-md border-t border-outline-variant/10">
              <span class="text-base font-bold text-on-surface">Tổng cộng</span>
              <span class="text-xl font-black text-primary">{{ formatCurrency(selectedOrder.total_amount) }}</span>
            </div>
         </div>
      </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import UserSidebar from '@/components/profile/UserSidebar.vue'
import Dialog from 'primevue/dialog'

const authStore = useAuthStore()
const toast = useToast()
const route = useRoute()
const router = useRouter()

const orders = ref([])
const loading = ref(true)
const currentFilter = ref('all')
const orderDetailVisible = ref(false)
const selectedOrder = ref(null)
const payingOrderId = ref(null)

const statusFilters = [
  { label: 'Tất cả', value: 'all' },
  { label: 'Chờ xử lý', value: 'pending' },
  { label: 'Đã hoàn thành', value: 'completed' },
  { label: 'Đã hủy', value: 'cancelled' }
]

const filteredOrders = computed(() => {
  if (currentFilter.value === 'all') return orders.value
  return orders.value.filter(o => o.status === currentFilter.value)
})

const fetchOrders = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/my-orders')
    const fetched = res.data.data || res.data || []
    orders.value = fetched.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải đơn hàng', life: 3000 })
  } finally { loading.value = false }
}

const showOrderDetail = (order) => {
  selectedOrder.value = order
  orderDetailVisible.value = true
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
  }).format(new Date(dateString))
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

const getStatusText = (status) => {
  const map = { pending: 'Chờ xử lý', processing: 'Đang xử lý', completed: 'Hoàn thành', cancelled: 'Đã hủy' }
  return map[status] || status
}

const getStatusStyle = (status) => {
  const map = {
    pending: 'bg-amber-100 text-amber-700 border border-amber-200',
    processing: 'bg-blue-100 text-blue-700 border border-blue-200',
    completed: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    cancelled: 'bg-error-container text-error border border-error/20'
  }
  return map[status] || 'bg-surface-container-high text-on-surface-variant'
}

const getPaymentStatusText = (status) => {
  const map = { unpaid: 'Chưa thanh toán', paid: 'Đã thanh toán', refunded: 'Đã hoàn tiền' }
  return map[status] || status
}

const getPaymentStatusStyle = (status) => {
  const map = {
    unpaid: 'bg-error-container text-error border border-error/20',
    paid: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    refunded: 'bg-blue-100 text-blue-700 border border-blue-200'
  }
  return map[status] || 'bg-surface-container-high text-on-surface-variant'
}

const getPaymentMethodText = (method) => {
  const map = { cod: 'COD', online: 'Online (VNPAY)', vnpay: 'Online (VNPAY)', VNPAY: 'Online (VNPAY)' }
  return map[method] || method
}

const payNow = async (order) => {
  payingOrderId.value = order.id
  try {
    const res = await apiClient.post('/api/vnpay/create', { order_id: order.id })
    if (res.data && res.data.url) {
      window.location.href = res.data.url
    } else {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tạo liên kết thanh toán', life: 3000 })
    }
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Lỗi kết nối đến máy chủ thanh toán'
    toast.add({ severity: 'error', summary: 'Lỗi thanh toán', detail: errorMsg, life: 3000 })
  } finally {
    payingOrderId.value = null
  }
}

onMounted(() => {
  fetchOrders()

  // Xử lý thông báo kết quả thanh toán từ VNPAY redirect
  const paymentStatus = route.query.payment
  if (paymentStatus) {
    if (paymentStatus === 'success') {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Thanh toán đơn hàng thành công!', life: 5000 })
    } else if (paymentStatus === 'failed') {
      toast.add({ severity: 'error', summary: 'Thất bại', detail: 'Thanh toán đơn hàng không thành công hoặc đã bị hủy.', life: 5000 })
    } else if (paymentStatus === 'invalid_signature') {
      toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Chữ ký thanh toán không hợp lệ.', life: 5000 })
    }
    // Xóa query parameter trên URL để tránh hiển thị lại khi reload trang
    router.replace({ query: {} })
  }
})
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.4s ease-out forwards;
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

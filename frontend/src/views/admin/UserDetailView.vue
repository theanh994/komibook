<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/services/axios'

const route = useRoute()
const router = useRouter()
const userId = computed(() => route.params.id)
const loading = ref(true)
const error = ref(null)

const user = ref(null)

const membershipStats = computed(() => {
  if (!user.value) return { tier: '—', totalOrders: 0, totalSpent: 0, totalBooks: '—', reviews: '—' }
  return {
    tier: user.value.membership_tier?.name || 'Chưa xếp hạng',
    totalOrders: user.value.total_orders || 0,
    totalSpent: user.value.total_spent || 0,
    totalBooks: '—',
    reviews: '—',
  }
})

const recentOrders = ref([])
const readingHistory = ref([])

const getInitials = (name) => {
  if (!name) return '??'
  const parts = name.split(' ')
  return (parts[0]?.[0] || '') + (parts[parts.length - 1]?.[0] || '')
}

const formatVND = (val) => new Intl.NumberFormat('vi-VN').format(val || 0) + ' ₫'

const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const getStatusStyle = (s) => s === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
const getStatusLabel = (s) => s === 'completed' ? 'Hoàn thành' : 'Chờ xử lý'

const fetchUser = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await apiClient.get(`/api/admin/users/${userId.value || route.params.id}`)
    if (res.data?.data) {
      user.value = res.data.data
      recentOrders.value = res.data.data.orders || []
    } else if (res.data) {
      user.value = res.data
    } else {
      user.value = null
    }
  } catch (err) {
    console.error('Không tải được thông tin người dùng', err)
    user.value = null
    error.value = err.response?.data?.message || 'Không thể kết nối API thông tin người dùng.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchUser)
</script>

<template>
  <div class="pb-xxl w-full pt-6">
    <!-- Breadcrumb + Actions -->
    <div class="flex items-center justify-between mb-xl animate-fade-in">
      <div class="flex items-center gap-sm text-on-surface-variant font-body-md text-body-md">
        <button @click="router.push({ name: 'admin-users' })" class="hover:text-primary transition-colors flex items-center gap-1">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span>
          Quản lý Users
        </button>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-on-surface font-medium">{{ user?.name || 'Chi tiết người dùng' }}</span>
      </div>
      <div v-if="user" class="flex items-center gap-sm">
        <button class="px-4 py-2 border border-red-300 text-red-600 rounded-lg font-label-md text-label-md hover:bg-red-50 transition-colors flex items-center gap-sm">
          <span class="material-symbols-outlined text-[18px]">block</span> Tạm khóa
        </button>
        <button class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary-container transition-colors flex items-center gap-sm">
          <span class="material-symbols-outlined text-[18px]">mail</span> Gửi thông báo
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-24 flex justify-center items-center flex-col gap-3">
      <i class="pi pi-spin pi-spinner text-4xl text-indigo-600"></i>
      <span class="text-sm text-slate-500">Đang tải thông tin người dùng...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-rose-50 border border-rose-200 rounded-2xl p-8 text-center space-y-4 my-6">
      <i class="pi pi-exclamation-triangle text-4xl text-rose-500"></i>
      <h3 class="text-lg font-bold text-rose-800">Không thể tải hồ sơ người dùng</h3>
      <p class="text-sm text-rose-600 max-w-md mx-auto">{{ error }}</p>
      <div class="flex justify-center gap-3 pt-2">
        <button @click="router.push({ name: 'admin-users' })" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg font-bold text-xs hover:bg-slate-100">
          Quay lại danh sách
        </button>
        <button @click="fetchUser" class="px-4 py-2 bg-rose-600 text-white rounded-lg font-bold text-xs hover:bg-rose-700 flex items-center gap-1">
          <i class="pi pi-refresh"></i> Thử lại
        </button>
      </div>
    </div>

    <!-- Main Grid -->
    <div v-else-if="user" class="grid grid-cols-1 lg:grid-cols-12 gap-lg animate-slide-up">
      <!-- Left: Profile Card -->
      <div class="lg:col-span-4 space-y-lg">
        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/30 overflow-hidden">
          <div class="h-24 bg-gradient-to-r from-primary to-primary-container relative"></div>
          <div class="px-lg pb-lg -mt-12 text-center">
            <div class="mx-auto w-20 h-20 rounded-full bg-surface-container-lowest border-4 border-surface flex items-center justify-center text-primary font-headline-lg text-[28px] shadow-md">
              {{ getInitials(user.name) }}
            </div>
            <h2 class="mt-md font-headline-md text-headline-md text-on-surface font-bold">{{ user.name }}</h2>
            <p class="font-body-md text-body-md text-on-surface-variant">{{ user.email }}</p>
            <div class="mt-md flex justify-center gap-xs flex-wrap">
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-primary-container text-on-primary-container border border-primary/20">
                Hạng: {{ membershipStats.tier }}
              </span>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-tertiary-container text-on-tertiary-container border border-tertiary/20">
                Tích lũy: {{ user.points || 0 }} Điểm
              </span>
            </div>
          </div>
          <div class="border-t border-outline-variant/30 px-lg py-md space-y-md">
            <div class="flex items-center gap-sm">
              <span class="material-symbols-outlined text-on-surface-variant text-[18px]">phone</span>
              <span class="font-body-md text-body-md text-on-surface">{{ user.phone || '—' }}</span>
            </div>
            <div class="flex items-center gap-sm">
              <span class="material-symbols-outlined text-on-surface-variant text-[18px]">calendar_month</span>
              <span class="font-body-md text-body-md text-on-surface">Tham gia: {{ formatDate(user.created_at) }}</span>
            </div>
            <div class="flex items-center gap-sm">
              <span class="material-symbols-outlined text-on-surface-variant text-[18px]">schedule</span>
              <span class="font-body-md text-body-md text-on-surface">Đăng nhập lần cuối: {{ formatDate(user.last_login) }}</span>
            </div>
          </div>
        </div>

        <!-- Reading History -->
        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/30 overflow-hidden">
          <div class="px-lg py-md border-b border-outline-variant/30 bg-surface">
            <h3 class="font-headline-md text-headline-md text-on-surface flex items-center gap-sm">
              <span class="material-symbols-outlined text-primary text-[20px]">auto_stories</span>
              Đang đọc
            </h3>
          </div>
          <div class="divide-y divide-outline-variant/20">
            <div v-for="item in readingHistory" :key="item.book" class="px-lg py-md">
              <div class="flex justify-between items-start mb-2">
                <span class="font-label-md text-label-md text-on-surface">{{ item.book }}</span>
                <span class="text-[12px] text-on-surface-variant">{{ item.lastRead }}</span>
              </div>
              <div class="flex items-center gap-sm">
                <div class="flex-1 bg-surface-container-high rounded-full h-2 overflow-hidden">
                  <div class="h-full rounded-full transition-all" :class="item.progress === 100 ? 'bg-green-500' : 'bg-primary'" :style="{ width: item.progress + '%' }"></div>
                </div>
                <span class="text-[12px] font-medium" :class="item.progress === 100 ? 'text-green-600' : 'text-primary'">{{ item.progress }}%</span>
              </div>
            </div>
            <div v-if="readingHistory.length === 0" class="px-lg py-xl text-center text-on-surface-variant text-xs">
              Chưa có dữ liệu theo dõi đọc sách
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Stats + Orders -->
      <div class="lg:col-span-8 space-y-lg">
        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-md">
          <div class="bg-surface-container-lowest rounded-xl p-md text-center shadow-soft border border-outline-variant/30">
            <span class="material-symbols-outlined text-primary text-[28px]">shopping_bag</span>
            <p class="font-headline-md text-headline-md font-bold text-on-surface mt-1">{{ membershipStats.totalOrders }}</p>
            <p class="font-body-md text-[13px] text-on-surface-variant">Đơn hàng</p>
          </div>
          <div class="bg-surface-container-lowest rounded-xl p-md text-center shadow-soft border border-outline-variant/30">
            <span class="material-symbols-outlined text-primary text-[28px]">payments</span>
            <p class="font-headline-md text-headline-md font-bold text-on-surface mt-1">{{ formatVND(membershipStats.totalSpent) }}</p>
            <p class="font-body-md text-[13px] text-on-surface-variant">Chi tiêu</p>
          </div>
          <div class="bg-surface-container-lowest rounded-xl p-md text-center shadow-soft border border-outline-variant/30">
            <span class="material-symbols-outlined text-primary text-[28px]">menu_book</span>
            <p class="font-headline-md text-headline-md font-bold text-on-surface mt-1">{{ membershipStats.totalBooks }}</p>
            <p class="font-body-md text-[13px] text-on-surface-variant">Sách đã đọc</p>
          </div>
          <div class="bg-surface-container-lowest rounded-xl p-md text-center shadow-soft border border-outline-variant/30">
            <span class="material-symbols-outlined text-primary text-[28px]">reviews</span>
            <p class="font-headline-md text-headline-md font-bold text-on-surface mt-1">{{ membershipStats.reviews }}</p>
            <p class="font-body-md text-[13px] text-on-surface-variant">Đánh giá</p>
          </div>
        </div>

        <!-- Recent Orders -->
        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/30 overflow-hidden">
          <div class="px-lg py-md border-b border-outline-variant/30 bg-surface flex items-center justify-between">
            <h3 class="font-headline-md text-headline-md text-on-surface flex items-center gap-sm">
              <span class="material-symbols-outlined text-primary text-[20px]">receipt_long</span>
              Lịch sử đơn hàng
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-surface-container-low text-on-surface-variant font-label-md text-[13px]">
                  <th class="py-3 px-lg font-semibold">Mã ĐH</th>
                  <th class="py-3 px-lg font-semibold">Ngày đặt</th>
                  <th class="py-3 px-lg font-semibold">Sản phẩm</th>
                  <th class="py-3 px-lg font-semibold text-right">Thành tiền</th>
                  <th class="py-3 px-lg font-semibold">Trạng thái</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/20">
                <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-surface-variant/30 transition-colors">
                  <td class="py-3 px-lg font-medium text-primary text-sm">#{{ order.id }}</td>
                  <td class="py-3 px-lg text-sm text-on-surface-variant">{{ formatDate(order.created_at || order.date) }}</td>
                  <td class="py-3 px-lg text-sm text-on-surface">{{ order.book || order.order_code || '—' }}</td>
                  <td class="py-3 px-lg text-sm text-right font-medium text-on-surface">{{ formatVND(order.grand_total || order.amount) }}</td>
                  <td class="py-3 px-lg">
                    <span :class="getStatusStyle(order.status)" class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium">{{ getStatusLabel(order.status) }}</span>
                  </td>
                </tr>
                <tr v-if="recentOrders.length === 0">
                  <td colspan="5" class="py-xl px-lg text-center text-on-surface-variant text-xs">Không có đơn hàng nào.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shadow-soft { box-shadow: 0 4px 12px rgba(26, 58, 90, 0.04); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
</style>

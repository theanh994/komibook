<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const userId = computed(() => route.params.id)
const loading = ref(true)

const user = ref({
  id: null,
  name: 'Nguyễn Văn A',
  email: 'nguyenvana@email.com',
  phone: '0901 234 567',
  role: 'customer',
  created_at: '2023-01-15T08:30:00Z',
  last_login: '2023-10-28T14:20:00Z',
  avatar: null,
})

const membershipStats = ref({
  tier: 'Free',
  totalOrders: 15,
  totalSpent: 2450000,
  totalBooks: 42,
  reviews: 7,
})

const recentOrders = ref([
  { id: 'KB-0012', date: '2023-10-25', book: 'Doraemon Tập 45', amount: 89000, status: 'completed' },
  { id: 'KB-0009', date: '2023-10-18', book: 'Conan Tập 102', amount: 35000, status: 'completed' },
  { id: 'KB-0007', date: '2023-10-10', book: 'Dragon Ball Super', amount: 120000, status: 'pending' },
])

const readingHistory = ref([
  { book: 'One Piece Tập 107', progress: 90, lastRead: '28/10/2023' },
  { book: 'Doraemon Tập 45', progress: 100, lastRead: '25/10/2023' },
  { book: 'Naruto Tập 72', progress: 35, lastRead: '20/10/2023' },
])

const getInitials = (name) => {
  if (!name) return '??'
  const parts = name.split(' ')
  return (parts[0]?.[0] || '') + (parts[parts.length - 1]?.[0] || '')
}

const formatVND = (val) => new Intl.NumberFormat('vi-VN').format(val) + ' ₫'

const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const getStatusStyle = (s) => s === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
const getStatusLabel = (s) => s === 'completed' ? 'Hoàn thành' : 'Chờ xử lý'

const fetchUser = async () => {
  loading.value = true
  try {
    const res = await apiClient.get(`/api/admin/users/${userId.value || route.params.id}`)
    if (res.data?.data) {
      user.value = { ...user.value, ...res.data.data }
    }
  } catch {
    // Keep mock data if user not found
  } finally {
    loading.value = false
  }
}

onMounted(fetchUser)
</script>

<template>
  <div class="px-lg md:px-xl pb-xxl max-w-container-max mx-auto w-full pt-6">
    <!-- Breadcrumb + Actions -->
    <div class="flex items-center justify-between mb-xl animate-fade-in">
      <div class="flex items-center gap-sm text-on-surface-variant font-body-md text-body-md">
        <button @click="router.push({ name: 'admin-customers' })" class="hover:text-primary transition-colors flex items-center gap-1">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span>
          Khách hàng
        </button>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-on-surface font-medium">{{ user.name }}</span>
      </div>
      <div class="flex items-center gap-sm">
        <button class="px-4 py-2 border border-red-300 text-red-600 rounded-lg font-label-md text-label-md hover:bg-red-50 transition-colors flex items-center gap-sm">
          <span class="material-symbols-outlined text-[18px]">block</span> Tạm khóa
        </button>
        <button class="px-4 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary-container transition-colors flex items-center gap-sm">
          <span class="material-symbols-outlined text-[18px]">mail</span> Gửi thông báo
        </button>
      </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg animate-slide-up">
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
            <span class="mt-md inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#f1f5f9] text-[#475569] border border-[#e2e8f0]">
              {{ membershipStats.tier }}
            </span>
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
            <button class="text-primary hover:underline font-label-md text-label-md text-[13px]">Xem tất cả</button>
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
                  <td class="py-3 px-lg text-sm text-on-surface-variant">{{ order.date }}</td>
                  <td class="py-3 px-lg text-sm text-on-surface">{{ order.book }}</td>
                  <td class="py-3 px-lg text-sm text-right font-medium text-on-surface">{{ formatVND(order.amount) }}</td>
                  <td class="py-3 px-lg">
                    <span :class="getStatusStyle(order.status)" class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium">{{ getStatusLabel(order.status) }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/30 overflow-hidden">
          <div class="px-lg py-md border-b border-outline-variant/30 bg-surface">
            <h3 class="font-headline-md text-headline-md text-on-surface flex items-center gap-sm">
              <span class="material-symbols-outlined text-primary text-[20px]">bolt</span>
              Hành động nhanh
            </h3>
          </div>
          <div class="p-lg grid grid-cols-1 md:grid-cols-3 gap-md">
            <button class="p-md rounded-lg border border-outline-variant/30 bg-surface hover:bg-surface-variant transition-colors text-center group">
              <span class="material-symbols-outlined text-primary text-[28px] group-hover:scale-110 transition-transform block mx-auto mb-2">workspace_premium</span>
              <span class="font-label-md text-label-md text-on-surface block">Nâng hạng VIP</span>
            </button>
            <button class="p-md rounded-lg border border-outline-variant/30 bg-surface hover:bg-surface-variant transition-colors text-center group">
              <span class="material-symbols-outlined text-primary text-[28px] group-hover:scale-110 transition-transform block mx-auto mb-2">redeem</span>
              <span class="font-label-md text-label-md text-on-surface block">Tặng voucher</span>
            </button>
            <button class="p-md rounded-lg border border-outline-variant/30 bg-surface hover:bg-surface-variant transition-colors text-center group">
              <span class="material-symbols-outlined text-primary text-[28px] group-hover:scale-110 transition-transform block mx-auto mb-2">lock_reset</span>
              <span class="font-label-md text-label-md text-on-surface block">Reset mật khẩu</span>
            </button>
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

<template>
  <div class="min-h-screen bg-background py-xl px-gutter">
    <div class="max-w-[1200px] mx-auto flex flex-col lg:flex-row gap-xl">
      
      <!-- Sidebar -->
      <UserSidebar :user="authStore.user" />

      <!-- Main Content -->
      <main class="flex-1 space-y-lg">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden min-h-[600px] flex flex-col">
          <div class="p-lg md:p-xl border-b border-outline-variant/10 flex justify-between items-center">
            <div>
              <h1 class="text-2xl font-black text-on-surface tracking-tight mb-1">Thông báo</h1>
              <p class="text-sm text-on-surface-variant font-medium">Cập nhật những tin tức mới nhất từ KomiBook.</p>
            </div>
            <button
              v-if="hasUnread"
              @click="handleMarkAllAsRead"
              class="text-xs font-black uppercase text-primary hover:underline bg-transparent border-none cursor-pointer"
            >
              Đánh dấu đã đọc tất cả
            </button>
          </div>

          <!-- Notification Items list -->
          <div class="p-lg md:p-xl grow">
            <div v-if="loading && page === 1" class="flex flex-col items-center justify-center py-20 gap-3 text-slate-500">
              <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
              <span class="text-sm">Đang tải thông báo...</span>
            </div>

            <div v-else-if="notifications.length === 0" class="flex flex-col items-center justify-center py-20 text-center text-slate-400">
              <span class="material-symbols-outlined text-[64px] text-slate-300 dark:text-zinc-700">notifications_off</span>
              <h3 class="text-lg font-bold text-slate-800 dark:text-zinc-200 mt-4">Không có thông báo nào</h3>
              <p class="text-sm text-slate-500 max-w-xs mx-auto mt-1">Khi bạn có đơn hàng mới hoặc ưu đãi đặc biệt, chúng sẽ hiển thị tại đây.</p>
            </div>

            <div v-else class="space-y-md">
              <div
                v-for="noti in notifications"
                :key="noti.id"
                @click="handleMarkAsRead(noti)"
                class="p-lg rounded-2xl border-2 transition-all cursor-pointer flex gap-lg hover:shadow-sm"
                :class="!noti.read_at ? 'border-primary/20 bg-primary/5' : 'border-outline-variant/10 hover:border-outline-variant/40'"
              >
                <!-- Icon and Type Specific Colors -->
                <div :class="['w-12 h-12 rounded-2xl shrink-0 flex items-center justify-center', noti.data?.colorClass || 'bg-slate-100 text-slate-600']">
                  <span class="material-symbols-outlined text-2xl">{{ getMaterialIcon(noti.data?.icon) }}</span>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex justify-between items-start mb-1 gap-4">
                    <h4 :class="['text-sm font-bold leading-tight truncate', !noti.read_at ? 'text-on-surface' : 'text-on-surface-variant']">
                      {{ noti.title }}
                    </h4>
                    <span class="text-[10px] font-bold text-outline uppercase whitespace-nowrap pt-0.5">
                      {{ formatRelativeTime(noti.created_at) }}
                    </span>
                  </div>
                  <p class="text-xs text-on-surface-variant leading-relaxed">
                    {{ noti.content }}
                  </p>

                  <!-- Image Banner rich preview if campaign contains it -->
                  <div v-if="noti.data?.image_url" class="mt-3 rounded-xl overflow-hidden max-w-md max-h-40 border border-slate-100 shadow-sm">
                    <img :src="noti.data.image_url" class="w-full h-full object-cover" alt="Notification banner" />
                  </div>
                </div>
                <div v-if="!noti.read_at" class="w-2 h-2 rounded-full bg-primary mt-2 shrink-0"></div>
              </div>
            </div>

            <!-- Load More button -->
            <div v-if="hasMore" class="mt-xl text-center">
              <button
                @click="loadMore"
                :disabled="loading"
                class="px-xl py-md rounded-2xl text-xs font-black uppercase text-outline hover:bg-surface-container-high transition-all border-none bg-transparent cursor-pointer flex items-center gap-2 mx-auto"
              >
                <i v-if="loading" class="pi pi-spin pi-spinner"></i>
                Tải thêm thông báo
              </button>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import UserSidebar from '@/components/profile/UserSidebar.vue'
import apiClient from '@/services/axios'
import { useToast } from 'primevue/usetoast'

const authStore = useAuthStore()
const toast = useToast()

const getMaterialIcon = (iconName) => {
  if (!iconName) return 'notifications'
  if (iconName.includes('megaphone')) return 'campaign'
  if (iconName.includes('shopping-bag') || iconName.includes('shopping-cart')) return 'shopping_bag'
  if (iconName.includes('info')) return 'info'
  if (iconName.includes('check') || iconName.includes('success')) return 'check_circle'
  if (iconName.includes('ban') || iconName.includes('error')) return 'error'
  if (iconName.includes('user')) return 'person'
  if (iconName.includes('percentage') || iconName.includes('tag')) return 'percent'
  if (iconName.includes('wallet') || iconName.includes('money')) return 'payments'
  
  return iconName.replace(/^pi\s+pi-/, '').replace(/^pi-/, '').replace(/-/g, '_')
}

const notifications = ref([])
const loading = ref(false)
const page = ref(1)
const hasMore = ref(false)
const unreadCount = ref(0)

const hasUnread = computed(() => unreadCount.value > 0)

const fetchNotifications = async (isLoadMore = false) => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/notifications', {
      params: { page: page.value }
    })
    
    const newNotis = res.data.notifications.data || []
    if (isLoadMore) {
      notifications.value = [...notifications.value, ...newNotis]
    } else {
      notifications.value = newNotis
    }

    unreadCount.value = res.data.unread_count
    hasMore.value = res.data.notifications.next_page_url !== null
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải thông báo.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const handleMarkAsRead = async (noti) => {
  if (noti.read_at) return // Already read

  try {
    const res = await apiClient.patch(`/api/notifications/${noti.id}/read`)
    noti.read_at = new Date().toISOString()
    unreadCount.value = res.data.unread_count
  } catch (err) {
    console.error('Failed to mark notification as read:', err)
  }
}

const handleMarkAllAsRead = async () => {
  try {
    await apiClient.post('/api/notifications/read-all')
    notifications.value.forEach(n => {
      if (!n.read_at) n.read_at = new Date().toISOString()
    })
    unreadCount.value = 0
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã đánh dấu đọc tất cả thông báo.', life: 2500 })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể thực hiện thao tác.', life: 3000 })
  }
}

const loadMore = () => {
  if (loading.value || !hasMore.value) return
  page.value++
  fetchNotifications(true)
}

const formatRelativeTime = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMins / 60)
  const diffDays = Math.floor(diffHours / 24)

  if (diffMins < 1) return 'Vừa xong'
  if (diffMins < 60) return `${diffMins} phút trước`
  if (diffHours < 24) return `${diffHours} giờ trước`
  if (diffDays === 1) return 'Hôm qua'
  if (diffDays < 7) return `${diffDays} ngày trước`
  
  return date.toLocaleDateString('vi-VN', { month: '2-digit', day: '2-digit' })
}

onMounted(() => {
  fetchNotifications()
})
</script>

<style scoped>
</style>

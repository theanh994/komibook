<template>
  <div class="min-h-screen bg-background">
    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl flex flex-col lg:flex-row items-stretch gap-xl">
      
      <!-- Sidebar -->
      <UserSidebar :user="authStore.user" />

      <!-- Main Content -->
      <main class="flex-1 min-w-0 w-full flex flex-col" aria-labelledby="notifications-title">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden flex-1 flex flex-col">
          <div class="p-lg md:p-xl border-b border-outline-variant/10 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
            <div>
              <h1 id="notifications-title" class="text-2xl font-black text-on-surface tracking-tight mb-1">Thông báo</h1>
              <p class="text-sm text-on-surface-variant font-medium">Cập nhật những tin tức mới nhất từ KomiBook.</p>
            </div>
            <button
              v-if="hasUnread"
              @click="handleMarkAllAsRead"
              class="min-h-11 px-4 py-2 rounded-lg text-sm font-bold text-primary hover:bg-primary/10 transition-colors border-none bg-transparent cursor-pointer"
            >
              Đánh dấu đã đọc tất cả
            </button>
          </div>

          <!-- Notification Items list -->
          <div class="p-lg md:p-xl grow">
            <div v-if="loading && page === 1" class="flex flex-col items-center justify-center py-20 gap-3 text-on-surface-variant" role="status" aria-live="polite">
              <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
              <span class="text-sm">Đang tải thông báo...</span>
            </div>

            <div v-else-if="error" class="flex flex-col items-center justify-center py-20 text-center" role="alert">
              <span class="material-symbols-outlined text-[56px] text-error" aria-hidden="true">notifications_paused</span>
              <h2 class="text-lg font-bold text-on-surface mt-4">Không thể tải thông báo</h2>
              <p class="text-sm text-on-surface-variant max-w-sm mx-auto mt-1">{{ error }}</p>
              <button type="button" class="mt-5 min-h-11 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-on-primary" @click="retryFetch">
                Thử lại
              </button>
            </div>

            <div v-else-if="notifications.length === 0" class="flex flex-col items-center justify-center py-20 text-center text-on-surface-variant">
              <span class="material-symbols-outlined text-[64px] text-outline" aria-hidden="true">notifications_off</span>
              <h2 class="text-lg font-bold text-on-surface mt-4">Không có thông báo nào</h2>
              <p class="text-sm text-on-surface-variant max-w-xs mx-auto mt-1">Khi bạn có đơn hàng mới hoặc ưu đãi đặc biệt, chúng sẽ hiển thị tại đây.</p>
            </div>

            <div v-else class="space-y-md">
              <article
                v-for="noti in notifications"
                :key="noti.id"
                class="w-full min-h-11 p-lg rounded-2xl border-2 transition-all flex gap-lg hover:shadow-md text-left cursor-pointer group"
                :class="!noti.read_at ? 'border-primary/20 bg-primary/5' : 'border-outline-variant/10 hover:border-outline-variant/40 bg-surface-container-lowest'"
                role="button"
                tabindex="0"
                :aria-label="`Xem chi tiết thông báo: ${noti.title}`"
                @click="openNotificationDetail(noti)"
                @keydown.enter.self="openNotificationDetail(noti)"
                @keydown.space.prevent.self="openNotificationDetail(noti)"
              >
                <!-- Icon and Type Specific Colors -->
                <div :class="['w-12 h-12 rounded-2xl shrink-0 flex items-center justify-center transition-transform group-hover:scale-105', noti.data?.colorClass || 'bg-slate-100 text-slate-600']">
                  <span class="material-symbols-outlined text-2xl">{{ getMaterialIcon(noti.data?.icon) }}</span>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex justify-between items-start mb-1 gap-4">
                    <h4 :class="['text-sm font-bold leading-tight truncate group-hover:text-primary transition-colors', !noti.read_at ? 'text-on-surface' : 'text-on-surface-variant']">
                      {{ noti.title }}
                    </h4>
                    <span class="text-xs font-bold text-outline whitespace-nowrap pt-0.5">
                      {{ formatRelativeTime(noti.created_at) }}
                    </span>
                  </div>
                  <p class="text-sm text-on-surface-variant leading-relaxed line-clamp-2">
                    {{ noti.content }}
                  </p>

                  <!-- Image Banner rich preview if campaign contains it -->
                  <div v-if="noti.data?.image_url" class="mt-3 rounded-xl overflow-hidden max-w-md max-h-40 border border-slate-100 shadow-sm">
                    <img :src="noti.data.image_url" class="w-full h-full object-contain" :alt="`Ảnh minh họa cho ${noti.title}`" />
                  </div>

                  <div v-if="isPendingWarehouseInvitation(noti)" class="mt-4 flex flex-wrap gap-2" aria-label="Phản hồi lời mời quản lý kho">
                    <button
                      type="button"
                      class="min-h-11 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary disabled:cursor-wait disabled:opacity-60"
                      :disabled="respondingId === noti.id"
                      @click.stop="respondToWarehouseInvitation(noti, 'accept')"
                    >
                      <i v-if="respondingId === noti.id" class="pi pi-spin pi-spinner mr-2"></i>
                      Chấp nhận
                    </button>
                    <button
                      type="button"
                      class="min-h-11 rounded-xl border border-outline-variant bg-transparent px-4 py-2 text-sm font-bold text-on-surface disabled:cursor-wait disabled:opacity-60"
                      :disabled="respondingId === noti.id"
                      @click.stop="respondToWarehouseInvitation(noti, 'decline')"
                    >
                      Từ chối
                    </button>
                  </div>
                  <p v-else-if="isWarehouseInvitation(noti)" class="mt-3 text-xs font-bold" :class="noti.data?.invitation_status === 'active' ? 'text-emerald-700' : 'text-on-surface-variant'">
                    {{ warehouseInvitationStatus(noti) }}
                  </p>
                </div>
                <button
                  v-if="!noti.read_at"
                  type="button"
                  class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-xs font-bold text-primary hover:bg-primary/10"
                  :aria-label="`Đánh dấu đã đọc: ${noti.title}`"
                  @click.stop="handleMarkAsRead(noti)"
                >
                  <span class="h-2 w-2 rounded-full bg-primary" aria-hidden="true"></span>
                </button>
              </article>
            </div>

            <!-- Load More button -->
            <div v-if="hasMore" class="mt-xl text-center">
              <button
                @click="loadMore"
                :disabled="loading"
                class="min-h-11 px-xl py-md rounded-2xl text-sm font-bold text-outline hover:bg-surface-container-high transition-colors border-none bg-transparent cursor-pointer flex items-center gap-2 mx-auto"
              >
                <i v-if="loading" class="pi pi-spin pi-spinner"></i>
                Tải thêm thông báo
              </button>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Notification Detail Dialog -->
    <Dialog
      v-model:visible="showDetailModal"
      modal
      header="Chi tiết thông báo"
      class="w-full max-w-lg mx-4 rounded-2xl overflow-hidden"
      :breakpoints="{ '960px': '75vw', '641px': '90vw' }"
    >
      <div v-if="selectedNotification" class="flex flex-col gap-4 py-2">
        <div class="flex items-start gap-3.5">
          <div :class="['w-12 h-12 rounded-2xl shrink-0 flex items-center justify-center', selectedNotification.data?.colorClass || 'bg-slate-100 text-slate-600']">
            <span class="material-symbols-outlined text-2xl">{{ getMaterialIcon(selectedNotification.data?.icon) }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <h3 class="text-base font-bold text-on-surface leading-snug">{{ selectedNotification.title }}</h3>
            <p class="mt-1 text-xs font-bold text-outline">
              {{ formatFullDateTime(selectedNotification.created_at) }} ({{ formatRelativeTime(selectedNotification.created_at) }})
            </p>
          </div>
        </div>

        <div class="border-t border-b border-outline-variant/15 py-4 my-1">
          <p class="text-sm leading-relaxed text-on-surface whitespace-pre-line">{{ selectedNotification.content }}</p>

          <div v-if="selectedNotification.data?.image_url" class="mt-4 rounded-xl overflow-hidden max-h-64 border border-outline-variant/20 shadow-sm">
            <img :src="selectedNotification.data.image_url" class="w-full h-full object-contain" :alt="`Ảnh ${selectedNotification.title}`" />
          </div>
        </div>

        <div v-if="isPendingWarehouseInvitation(selectedNotification)" class="flex flex-wrap gap-2 pt-1" aria-label="Phản hồi lời mời quản lý kho">
          <button
            type="button"
            class="flex-1 min-h-11 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-on-primary disabled:cursor-wait disabled:opacity-60"
            :disabled="respondingId === selectedNotification.id"
            @click="respondToWarehouseInvitation(selectedNotification, 'accept')"
          >
            <i v-if="respondingId === selectedNotification.id" class="pi pi-spin pi-spinner mr-2"></i>
            Chấp nhận lời mời
          </button>
          <button
            type="button"
            class="flex-1 min-h-11 rounded-xl border border-outline-variant bg-transparent px-4 py-2 text-sm font-bold text-on-surface disabled:cursor-wait disabled:opacity-60"
            :disabled="respondingId === selectedNotification.id"
            @click="respondToWarehouseInvitation(selectedNotification, 'decline')"
          >
            Từ chối
          </button>
        </div>
        <p v-else-if="isWarehouseInvitation(selectedNotification)" class="text-xs font-bold" :class="selectedNotification.data?.invitation_status === 'active' ? 'text-emerald-700' : 'text-on-surface-variant'">
          {{ warehouseInvitationStatus(selectedNotification) }}
        </p>

        <div class="flex items-center justify-end gap-3 pt-2">
          <RouterLink
            v-if="getNotificationActionUrl(selectedNotification)"
            :to="getNotificationActionUrl(selectedNotification)"
            class="min-h-11 px-5 py-2.5 rounded-xl bg-primary text-on-primary font-bold text-sm no-underline flex items-center justify-center gap-1 shadow-sm"
            @click="showDetailModal = false"
          >
            Chuyển tới trang liên quan
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
          </RouterLink>
          <button
            type="button"
            class="min-h-11 px-5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-low font-bold text-sm text-on-surface cursor-pointer"
            @click="showDetailModal = false"
          >
            Đóng
          </button>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import UserSidebar from '@/components/profile/UserSidebar.vue'
import apiClient from '@/services/axios'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'

const authStore = useAuthStore()
const toast = useToast()

const selectedNotification = ref(null)
const showDetailModal = ref(false)

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
const error = ref('')
const respondingId = ref(null)

const hasUnread = computed(() => unreadCount.value > 0)

const isWarehouseInvitation = (noti) => noti.data?.action_type === 'warehouse_assignment_invitation'
const isPendingWarehouseInvitation = (noti) => isWarehouseInvitation(noti) && noti.data?.invitation_status === 'invited'

const warehouseInvitationStatus = (noti) => {
  if (noti.data?.invitation_status === 'active') return 'Bạn đã chấp nhận lời mời này.'
  if (noti.data?.invitation_status === 'declined') return 'Bạn đã từ chối lời mời này.'
  return 'Lời mời không còn hiệu lực.'
}

const openNotificationDetail = (noti) => {
  selectedNotification.value = noti
  showDetailModal.value = true
  if (!noti.read_at) {
    handleMarkAsRead(noti)
  }
}

const getNotificationActionUrl = (noti) => {
  if (!noti || !noti.data) return null
  if (noti.data.action_url) return noti.data.action_url
  if (noti.data.order_id) return { name: 'orders', query: { order_id: noti.data.order_id } }
  if (noti.data.book_slug) return { name: 'book-detail', params: { slug: noti.data.book_slug } }
  if (noti.data.vendor_slug) return { name: 'vendor-storefront', params: { slug: noti.data.vendor_slug } }
  return null
}

const formatFullDateTime = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

const respondToWarehouseInvitation = async (noti, decision) => {
  respondingId.value = noti.id
  try {
    const res = await apiClient.post(`/api/warehouse-manager/assignments/${noti.data.assignment_id}/respond`, {
      decision,
      operation_key: `notification:${noti.id}:${decision}`,
    })
    noti.data = {
      ...noti.data,
      invitation_status: decision === 'accept' ? 'active' : 'declined',
    }
    if (!noti.read_at) unreadCount.value = Math.max(0, unreadCount.value - 1)
    noti.read_at = new Date().toISOString()
    toast.add({ severity: 'success', summary: 'Đã phản hồi', detail: res.data.message, life: 3000 })
  } catch (err) {
    const detail = err.response?.data?.message || 'Không thể phản hồi lời mời này.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail, life: 4000 })
  } finally {
    respondingId.value = null
  }
}

const fetchNotifications = async (isLoadMore = false) => {
  loading.value = true
  error.value = ''
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
    if (!isLoadMore) notifications.value = []
    error.value = 'Vui lòng kiểm tra kết nối và thử lại.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải thông báo.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const retryFetch = () => {
  page.value = 1
  fetchNotifications()
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

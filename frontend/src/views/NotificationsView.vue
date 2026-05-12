<template>
  <div class="min-h-screen bg-background py-xl px-gutter">
    <div class="max-w-[1200px] mx-auto flex flex-col lg:flex-row gap-xl">
      
      <!-- Sidebar -->
      <UserSidebar :user="authStore.user" />

      <!-- Main Content -->
      <main class="flex-1 space-y-lg">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden min-h-[600px]">
          <div class="p-lg md:p-xl border-b border-outline-variant/10 flex justify-between items-center">
            <div>
              <h1 class="text-2xl font-black text-on-surface tracking-tight mb-1">Thông báo</h1>
              <p class="text-sm text-on-surface-variant font-medium">Cập nhật những tin tức mới nhất từ KomiBook.</p>
            </div>
            <button class="text-xs font-black uppercase text-primary hover:underline bg-transparent border-none cursor-pointer">Đánh dấu đã đọc tất cả</button>
          </div>

          <div class="p-lg md:p-xl">
            <div class="space-y-md">
              <div v-for="noti in notifications" :key="noti.id" class="p-lg rounded-2xl border-2 transition-all cursor-pointer flex gap-lg" :class="noti.unread ? 'border-primary/20 bg-primary/5' : 'border-outline-variant/10 hover:border-outline-variant/40'">
                <div :class="['w-12 h-12 rounded-2xl shrink-0 flex items-center justify-center', noti.colorClass]">
                  <span class="material-symbols-outlined text-2xl">{{ noti.icon }}</span>
                </div>
                <div class="flex-1">
                  <div class="flex justify-between items-start mb-1">
                    <h4 :class="['text-sm font-bold leading-tight', noti.unread ? 'text-on-surface' : 'text-on-surface-variant']">{{ noti.title }}</h4>
                    <span class="text-[10px] font-bold text-outline uppercase">{{ noti.time }}</span>
                  </div>
                  <p class="text-xs text-on-surface-variant leading-relaxed">{{ noti.content }}</p>
                </div>
                <div v-if="noti.unread" class="w-2 h-2 rounded-full bg-primary mt-2"></div>
              </div>
            </div>

            <!-- Load More -->
            <div class="mt-xl text-center">
               <button class="px-xl py-md rounded-2xl text-xs font-black uppercase text-outline hover:bg-surface-container-high transition-all border-none bg-transparent cursor-pointer">Tải thêm thông báo</button>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import UserSidebar from '@/components/profile/UserSidebar.vue'

const authStore = useAuthStore()

const notifications = ref([
  {
    id: 1,
    title: 'Đơn hàng #KOM-992 đã hoàn thành',
    content: 'Cuốn sách "Thám tử lừng danh Conan - Tập 102" đã được giao thành công. Hãy để lại đánh giá nhé!',
    time: '2 giờ trước',
    icon: 'local_shipping',
    unread: true,
    colorClass: 'bg-emerald-100 text-emerald-700'
  },
  {
    id: 2,
    title: 'Siêu ưu đãi Flash Sale sắp bắt đầu!',
    content: 'Đừng bỏ lỡ cơ hội sở hữu hàng ngàn cuốn sách với giá chỉ từ 1k vào lúc 12:00 hôm nay.',
    time: '5 giờ trước',
    icon: 'bolt',
    unread: true,
    colorClass: 'bg-amber-100 text-amber-700'
  },
  {
    id: 3,
    title: 'Chào mừng bạn đến với KomiBook',
    content: 'Cảm ơn bạn đã gia nhập cộng đồng yêu sách lớn nhất Việt Nam. Nhận ngay mã KOMINEW để giảm 20k cho đơn đầu tiên.',
    time: '1 ngày trước',
    icon: 'celebration',
    unread: false,
    colorClass: 'bg-primary-container text-on-primary-container'
  },
  {
    id: 4,
    title: 'Bảo trì hệ thống định kỳ',
    content: 'Hệ thống sẽ tạm dừng hoạt động từ 01:00 đến 03:00 sáng mai để nâng cấp hiệu năng.',
    time: '2 ngày trước',
    icon: 'settings',
    unread: false,
    colorClass: 'bg-surface-container-highest text-outline'
  }
])
</script>

<style scoped>
</style>

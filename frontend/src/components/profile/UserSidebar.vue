<template>
  <aside class="w-full lg:w-72 shrink-0">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden sticky top-24">
      <!-- User Info Header -->
      <div class="p-lg bg-surface-container-low/50 border-b border-outline-variant/20 flex flex-col items-center text-center">
        <div class="relative mb-md group cursor-pointer" @click="$emit('avatar-click')">
          <div class="w-20 h-20 rounded-full overflow-hidden border-4 border-surface shadow-sm">
            <img v-if="user?.avatar" :src="getAvatarUrl(user.avatar)" alt="Avatar" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full bg-primary-container flex items-center justify-center text-on-primary-container text-2xl font-bold">
              {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
            </div>
          </div>
          <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <span class="material-symbols-outlined text-white text-xl">photo_camera</span>
          </div>
        </div>
        <h3 class="font-bold text-on-surface leading-tight">{{ user?.name }}</h3>
        <p class="text-xs text-outline font-medium truncate w-full px-4">{{ user?.email }}</p>
      </div>

      <!-- Navigation Menu -->
      <nav class="p-md space-y-1">
        <router-link 
          v-for="item in menuItems" 
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-md px-lg py-md rounded-2xl transition-all duration-200 group no-underline"
          :class="[
            $route.path === item.to 
              ? 'bg-primary text-on-primary shadow-md' 
              : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface'
          ]"
        >
          <span class="material-symbols-outlined" :class="[$route.path === item.to ? '' : 'text-outline group-hover:text-primary transition-colors']">
            {{ item.icon }}
          </span>
          <span class="font-bold text-sm">{{ item.label }}</span>
          <div v-if="item.badge" class="ml-auto bg-error text-on-error text-[10px] px-1.5 py-0.5 rounded-full font-bold">
            {{ item.badge }}
          </div>
        </router-link>

        <div class="h-px bg-outline-variant/20 my-md mx-md"></div>

        <button 
          @click="handleLogout"
          class="w-full flex items-center gap-md px-lg py-md rounded-2xl text-error hover:bg-error-container/20 transition-all duration-200 border-none bg-transparent cursor-pointer font-bold text-sm"
        >
          <span class="material-symbols-outlined">logout</span>
          Đăng xuất
        </button>
      </nav>
    </div>
  </aside>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const props = defineProps({
  user: Object
})

const authStore = useAuthStore()
const router = useRouter()

const menuItems = [
  { label: 'Hồ sơ cá nhân', icon: 'person', to: '/profile' },
  { label: 'Tủ sách của tôi', icon: 'local_library', to: '/my-library' },
  { label: 'Đơn hàng của tôi', icon: 'shopping_bag', to: '/orders' },
  { label: 'Danh sách yêu thích', icon: 'favorite', to: '/wishlist' },
  { label: 'Thông báo', icon: 'notifications', to: '/notifications', badge: '2' },
]

const getAvatarUrl = (avatar) => {
  if (!avatar) return ''
  // Nếu là path đã có sẵn /storage/ (từ backend mới)
  if (avatar.startsWith('/storage/')) return avatar
  
  // Nếu là URL tuyệt đối từ domain cũ (ví dụ komibook.test), chuyển về tương đối
  if (avatar.includes('/storage/')) {
    return avatar.substring(avatar.indexOf('/storage/'))
  }

  // Fallback cho path cũ
  return `/storage/${avatar}`
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/')
}
</script>

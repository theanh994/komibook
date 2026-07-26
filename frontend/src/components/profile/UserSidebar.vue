<template>
  <aside class="w-full lg:w-68 shrink-0 self-start sticky top-20">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden space-y-3">
      <!-- User Info Header (Increased padding for 5% height boost) -->
      <div class="p-4.5 bg-surface-container-low/50 border-b border-outline-variant/20 flex flex-col items-center text-center relative">
        <router-link to="/" class="absolute top-3 left-3 w-7 h-7 rounded-lg overflow-hidden shadow-xs border border-white/60 hover:scale-105 transition-transform" title="Trang chủ KomiBook">
          <img src="@/assets/logo.png" alt="KomiBook Logo" class="w-full h-full object-cover" />
        </router-link>
        <!-- Larger Avatar Container -->
        <div class="relative mb-2 mt-1 group cursor-pointer" @click="emit('avatar-click')">
          <div class="w-[72px] h-[72px] rounded-full overflow-hidden border-3 border-surface shadow-md ring-2 ring-primary/20 transition-transform group-hover:scale-105">
            <img v-if="user?.avatar" :src="getAvatarUrl(user.avatar)" alt="Avatar" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full bg-primary-container flex items-center justify-center text-on-primary-container text-xl font-black">
              {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
            </div>
          </div>
          <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <span class="material-symbols-outlined text-white text-lg">photo_camera</span>
          </div>
        </div>

        <h3 class="font-bold text-sm text-on-surface leading-tight truncate w-full px-2 mt-0.5">{{ user?.name || 'Người dùng' }}</h3>
        <p class="text-[11px] text-outline font-medium truncate w-full px-2 mt-0.5">{{ user?.email || '' }}</p>
      </div>

      <!-- Khu vực: Role Workspace Switcher -->
      <div class="px-3.5">
        <!-- Admin Switcher -->
        <div v-if="authStore.isAdmin" class="p-3.5 bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-2xl shadow-xs border border-indigo-500/30 space-y-2.5">
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-amber-400 text-sm">admin_panel_settings</span>
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-300">Quản Trị Viên</span>
          </div>
          <p class="text-[10px] text-slate-300 leading-snug">Chuyển sang trang điều khiển hệ thống.</p>
          <button @click="router.push('/admin/dashboard')" class="w-full py-2 px-3 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition-all cursor-pointer border-none flex items-center justify-center gap-1 shadow-xs">
            <span>Trang Quản Trị</span>
            <span class="material-symbols-outlined text-xs">arrow_forward</span>
          </button>
        </div>

        <!-- Author Switcher -->
        <div v-else-if="authStore.isAuthor" class="p-3.5 bg-gradient-to-br from-emerald-900 to-teal-950 text-white rounded-2xl shadow-xs border border-emerald-500/30 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-emerald-300 text-sm">edit_note</span>
              <span class="text-[10px] font-black uppercase tracking-wider text-emerald-200">Kênh Tác Giả</span>
            </div>
            <span class="px-1.5 py-0.5 bg-emerald-500/40 text-emerald-200 text-[8px] font-bold rounded-md uppercase">Author</span>
          </div>
          <p class="text-[10px] text-slate-300 leading-snug truncate">Bút danh: {{ user?.author_profile?.pen_name || user?.name }}</p>
          <button @click="router.push('/author/dashboard')" class="w-full py-2 px-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition-all cursor-pointer border-none flex items-center justify-center gap-1 shadow-xs">
            <span>Chuyển Sang Kênh Tác Giả</span>
            <span class="material-symbols-outlined text-xs">edit</span>
          </button>
        </div>

        <!-- Vendor Switcher -->
        <div v-else-if="authStore.isVendor" class="p-3.5 bg-gradient-to-br from-indigo-900 to-purple-950 text-white rounded-2xl shadow-xs border border-indigo-500/30 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-indigo-300 text-sm">storefront</span>
              <span class="text-[10px] font-black uppercase tracking-wider text-indigo-200">Kênh Bán Hàng</span>
            </div>
            <span class="px-1.5 py-0.5 bg-indigo-500/40 text-indigo-200 text-[8px] font-bold rounded-md uppercase">Vendor</span>
          </div>
          <p class="text-[10px] text-slate-300 leading-snug truncate">{{ user?.vendor?.shop_name || 'Gian hàng của tôi' }}</p>
          <button @click="router.push('/vendor/dashboard')" class="w-full py-2 px-3 bg-indigo-500 hover:bg-indigo-400 text-white text-xs font-bold rounded-xl transition-all cursor-pointer border-none flex items-center justify-center gap-1 shadow-xs">
            <span>Chuyển Sang Kênh Bán Hàng</span>
            <span class="material-symbols-outlined text-xs">storefront</span>
          </button>
        </div>

        <!-- Non-Author/Vendor Register Link -->
        <div v-else class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2.5 text-slate-700">
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-primary text-sm">workspace_premium</span>
            <span class="text-xs font-bold text-slate-900">Mở Rộng Vai Trò</span>
          </div>
          <p class="text-[10px] text-slate-500 leading-snug">Đăng ký Tác giả để phát hành sách.</p>
          <button @click="router.push('/author/register')" class="w-full py-2 px-3 bg-primary/10 hover:bg-primary/20 text-primary text-xs font-bold rounded-xl transition-all cursor-pointer border-none flex items-center justify-center gap-1">
            <span>Đăng ký Tác giả</span>
            <span class="material-symbols-outlined text-xs">arrow_forward</span>
          </button>
        </div>
      </div>

      <!-- Navigation Menu -->
      <nav class="p-3 space-y-1.5">
        <router-link          v-for="item in menuItems"          :key="item.to"
          :to="item.to"
          class="flex items-center justify-between px-4 py-3 rounded-xl transition-all duration-200 group no-underline"
          :class="[
            isRouteActive(item.to)              ? 'bg-primary text-on-primary shadow-xs font-bold'              : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface'
          ]"
        >
          <span class="font-bold text-xs">{{ item.label }}</span>
          <div v-if="item.badge" class="bg-error text-on-error text-[9px] px-1.5 py-0.2 rounded-full font-bold">
            {{ item.badge }}
          </div>
        </router-link>

        <div class="h-px bg-outline-variant/20 my-2 mx-2"></div>

        <button          @click="handleLogout"
          class="w-full flex items-center gap-2 px-4 py-3 rounded-xl text-error hover:bg-error-container/20 transition-all duration-200 border-none bg-transparent cursor-pointer font-bold text-xs"
        >
          <span class="material-symbols-outlined text-lg">logout</span>
          Đăng xuất
        </button>
      </nav>

    </div>
  </aside>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import { useRouter, useRoute } from 'vue-router'

defineProps({
  user: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['avatar-click'])

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

// Mảng menu thanh gọn, chỉ giữ label & path
const menuItems = [
  { label: 'Thông tin cá nhân', to: '/profile' },
  { label: 'Tủ sách của tôi', to: '/my-library' },
  { label: 'Đơn hàng của tôi', to: '/orders' },
  { label: 'Trả hàng & hoàn tiền', to: '/returns' },
  { label: 'Danh sách yêu thích', to: '/wishlist' },
  { label: 'Thông báo', to: '/notifications' },
]

// Kiểm tra active route (bao gồm cả route con)
const isRouteActive = (pathToMatch) => {
  return route.path === pathToMatch || route.path.startsWith(`${pathToMatch}/`)
}

const getAvatarUrl = (avatar) => {
  if (!avatar) return ''
  if (avatar.startsWith('http://') || avatar.startsWith('https://')) return avatar
  if (avatar.startsWith('/storage/')) return avatar
  if (avatar.includes('/storage/')) {
    return avatar.substring(avatar.indexOf('/storage/'))
  }
  return `/storage/${avatar}`
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/')
}
</script>

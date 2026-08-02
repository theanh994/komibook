<template>
  <aside class="w-full shrink-0 self-start lg:sticky lg:top-20 lg:w-68" aria-label="Tài khoản của tôi">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden space-y-3">
      <div class="flex min-h-16 items-center gap-3 p-3 lg:hidden">
        <button
          type="button"
          class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-xl border-none bg-transparent px-2 text-left"
          :aria-expanded="mobileOpen"
          aria-controls="account-sidebar-content"
          @click="mobileOpen = !mobileOpen"
        >
          <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-container font-black text-on-primary-container">
            <img v-if="user?.avatar" :src="getAvatarUrl(user.avatar)" alt="" class="h-full w-full object-cover" />
            <span v-else aria-hidden="true">{{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}</span>
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-bold text-on-surface">{{ user?.name || 'Người dùng' }}</span>
            <span class="block truncate text-xs text-on-surface-variant">{{ currentMenuLabel }}</span>
          </span>
          <span class="material-symbols-outlined text-on-surface-variant" aria-hidden="true">
            {{ mobileOpen ? 'expand_less' : 'expand_more' }}
          </span>
        </button>
      </div>

      <div id="account-sidebar-content" :class="mobileOpen ? 'block' : 'hidden lg:block'">
      <!-- User Info Header (Increased padding for 5% height boost) -->
      <div class="p-4.5 bg-surface-container-low/50 border-b border-outline-variant/20 flex flex-col items-center text-center relative">
        <router-link to="/" class="absolute left-3 top-3 h-11 w-11 overflow-hidden rounded-lg border border-white/60 shadow-xs" aria-label="Trang chủ KomiBook">
          <img src="@/assets/logo.png" alt="KomiBook Logo" class="w-full h-full object-cover" />
        </router-link>
        <!-- Larger Avatar Container -->
        <button
          type="button"
          class="group relative mb-2 mt-1 cursor-pointer rounded-full"
          aria-label="Thay đổi ảnh đại diện"
          @click="emit('avatar-click')"
        >
          <div class="w-[72px] h-[72px] rounded-full overflow-hidden border-3 border-surface shadow-md ring-2 ring-primary/20 transition-transform group-hover:scale-105">
            <img v-if="user?.avatar" :src="getAvatarUrl(user.avatar)" alt="Avatar" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full bg-primary-container flex items-center justify-center text-on-primary-container text-xl font-black">
              {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
            </div>
          </div>
          <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <span class="material-symbols-outlined text-white text-lg">photo_camera</span>
          </div>
        </button>

        <h3 class="font-bold text-sm text-on-surface leading-tight truncate w-full px-2 mt-0.5">{{ user?.name || 'Người dùng' }}</h3>
        <p class="mt-0.5 w-full truncate px-2 text-sm font-medium text-outline">{{ user?.email || '' }}</p>
      </div>

      <!-- Khu vực: Role Workspace Switcher -->
      <div class="px-3.5">
        <!-- Admin Switcher -->
        <div v-if="authStore.isAdmin" class="p-3.5 bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-2xl shadow-xs border border-indigo-500/30 space-y-2.5">
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-amber-400 text-sm">admin_panel_settings</span>
            <span class="text-sm font-black uppercase tracking-wider text-amber-300">Quản Trị Viên</span>
          </div>
          <p class="text-sm text-slate-300 leading-snug">Chuyển sang trang điều khiển hệ thống.</p>
          <router-link to="/admin/dashboard" class="flex min-h-11 w-full items-center justify-center gap-1 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-bold text-white no-underline shadow-xs transition-colors hover:bg-indigo-500">
            <span>Trang Quản Trị</span>
            <span class="material-symbols-outlined text-xs">arrow_forward</span>
          </router-link>
        </div>

        <!-- Warehouse Manager Switcher -->
        <div v-else-if="authStore.isWarehouseManager" class="p-3.5 bg-gradient-to-br from-cyan-950 to-slate-950 text-white rounded-2xl shadow-xs border border-cyan-500/30 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-cyan-300 text-sm">warehouse</span>
              <span class="text-sm font-black uppercase tracking-wider text-cyan-100">Không gian Quản kho</span>
            </div>
            <span class="rounded-md bg-cyan-500/30 px-1.5 py-0.5 text-xs font-bold uppercase text-cyan-100">Kho</span>
          </div>
          <p class="text-sm leading-snug text-slate-300">Theo dõi tồn kho và xử lý phiếu tại các kho được Nhà bán phân công.</p>
          <router-link to="/warehouse-manager/dashboard" class="flex min-h-11 w-full items-center justify-center gap-1 rounded-xl bg-cyan-600 px-3 py-2 text-sm font-bold text-white no-underline shadow-xs transition-colors hover:bg-cyan-500">
            <span>Mở trang Quản kho</span>
            <span class="material-symbols-outlined text-xs">arrow_forward</span>
          </router-link>
        </div>

        <!-- Vendor Switcher -->
        <div v-else-if="authStore.isVendor" class="p-3.5 bg-gradient-to-br from-indigo-900 to-purple-950 text-white rounded-2xl shadow-xs border border-indigo-500/30 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-indigo-300 text-sm">storefront</span>
              <span class="text-sm font-black uppercase tracking-wider text-indigo-200">Kênh Bán Hàng</span>
            </div>
            <span class="rounded-md bg-indigo-500/40 px-1.5 py-0.5 text-xs font-bold uppercase text-indigo-100">Vendor</span>
          </div>
          <p class="truncate text-sm leading-snug text-slate-300">{{ user?.vendor?.shop_name || 'Gian hàng của tôi' }}</p>
          <router-link to="/vendor/dashboard" class="flex min-h-11 w-full items-center justify-center gap-1 rounded-xl bg-indigo-500 px-3 py-2 text-sm font-bold text-white no-underline shadow-xs transition-colors hover:bg-indigo-400">
            <span>Chuyển Sang Kênh Bán Hàng</span>
            <span class="material-symbols-outlined text-xs">storefront</span>
          </router-link>
        </div>

        <!-- Neutral role expansion for commerce and warehouse capabilities -->
        <div v-else class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2.5 text-slate-700">
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-primary text-sm">workspace_premium</span>
            <span class="text-xs font-bold text-slate-900">Kênh bán hàng</span>
          </div>
          <p class="text-sm leading-snug text-slate-500">Bạn có thể đăng ký gian hàng Nhà bán; tài khoản Quản kho được Nhà bán mời và phân quyền theo từng kho.</p>
          <router-link to="/vendor/register" class="flex min-h-11 w-full items-center justify-center gap-1 rounded-xl bg-primary/10 px-3 py-2 text-sm font-bold text-primary no-underline transition-colors hover:bg-primary/20">
            <span>Đăng ký Nhà bán</span>
            <span class="material-symbols-outlined text-xs">storefront</span>
          </router-link>
        </div>
      </div>

      <!-- Navigation Menu -->
      <nav class="p-3 space-y-1.5" aria-label="Điều hướng tài khoản">
        <router-link          v-for="item in menuItems"          :key="item.to"
          :to="item.to"
          class="group flex min-h-11 items-center justify-between rounded-xl px-4 py-3 no-underline transition-colors duration-200"
          :aria-current="isRouteActive(item.to) ? 'page' : undefined"
          :class="[
            isRouteActive(item.to)              ? 'bg-primary text-on-primary shadow-xs font-bold'              : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface'
          ]"
        >
          <span class="text-sm font-bold">{{ item.label }}</span>
          <div v-if="item.badge" class="bg-error text-on-error text-[9px] px-1.5 py-0.2 rounded-full font-bold">
            {{ item.badge }}
          </div>
        </router-link>

        <div class="h-px bg-outline-variant/20 my-2 mx-2"></div>

        <button          @click="handleLogout"
          class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-xl border-none bg-transparent px-4 py-3 text-sm font-bold text-error transition-colors duration-200 hover:bg-error-container/20"
        >
          <span class="material-symbols-outlined text-lg">logout</span>
          Đăng xuất
        </button>
      </nav>
      </div>

    </div>
  </aside>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import { computed, ref, watch } from 'vue'
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
const mobileOpen = ref(false)

// Mảng menu thanh gọn, chỉ giữ label & path
const menuItems = [
  { label: 'Thông tin cá nhân', to: '/profile' },
  { label: 'Tủ sách của tôi', to: '/my-library' },
  { label: 'Đơn hàng của tôi', to: '/orders' },
  { label: 'Trả hàng & hoàn tiền', to: '/returns' },
  { label: 'Ví KomiBook & Rút tiền', to: '/wallet' },
  { label: 'Sách cũ của tôi', to: '/used-books/manage' },
  { label: 'Danh sách yêu thích', to: '/wishlist' },
  { label: 'Thông báo', to: '/notifications' },
]

const currentMenuLabel = computed(() => (
  menuItems.find(item => isRouteActive(item.to))?.label || 'Tài khoản của tôi'
))

watch(() => route.fullPath, () => {
  mobileOpen.value = false
})

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

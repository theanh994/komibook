<template>
  <!-- TopAppBar - Material Design 3 -->
  <header class="bg-surface shadow-sm sticky top-0 z-50">
    <div class="flex justify-between items-center w-full px-gutter max-w-[1280px] mx-auto py-md">
      <!-- Brand Logo -->
      <router-link to="/" class="flex items-center gap-sm no-underline shrink-0">
        <div class="w-10 h-10 md:w-11 md:h-11 rounded-xl overflow-hidden shadow-md hover:scale-105 transition-transform duration-300">
          <img v-if="logoExists" src="@/assets/logo.png" alt="KomiBook Logo" class="w-full h-full object-cover" />
          <div v-else class="w-full h-full bg-primary flex items-center justify-center">
            <span class="material-symbols-outlined text-on-primary text-xl">auto_stories</span>
          </div>
        </div>
        <span class="font-inter text-xl md:text-2xl font-bold text-primary tracking-tight">Komibook</span>
      </router-link>

      <!-- Navigation Links (Desktop) -->
      <nav class="hidden md:flex gap-lg items-center">
        <router-link
          to="/"
          :class="[
            'font-inter text-sm font-medium transition-colors duration-200 pb-1',
            $route.name === 'home'
              ? 'text-secondary font-bold border-b-2 border-secondary'
              : 'text-outline hover:text-secondary'
          ]"
        >Trang chủ</router-link>
        <router-link
          to="/catalog"
          :class="[
            'font-inter text-sm font-medium transition-colors duration-200 pb-1',
            $route.name === 'catalog'
              ? 'text-secondary font-bold border-b-2 border-secondary'
              : 'text-outline hover:text-secondary'
          ]"
        >Danh mục</router-link>
        <router-link
          to="/blog"
          :class="[
            'font-inter text-sm font-medium transition-colors duration-200 pb-1',
            $route.name === 'blog'
              ? 'text-secondary font-bold border-b-2 border-secondary'
              : 'text-outline hover:text-secondary'
          ]"
        >Tin tức</router-link>
        <router-link
          v-if="authStore.isAuthenticated"
          to="/my-library"
          :class="[
            'font-inter text-sm font-medium transition-colors duration-200 pb-1',
            $route.name === 'my-library'
              ? 'text-secondary font-bold border-b-2 border-secondary'
              : 'text-outline hover:text-secondary'
          ]"
        >Tủ sách</router-link>
      </nav>

      <!-- Search + Actions -->
      <div class="flex items-center gap-md">
        <!-- Search Bar (Desktop) -->
        <div class="hidden md:flex items-center relative">
          <input
            v-model="searchQuery"
            class="pl-10 pr-4 py-2 bg-surface-container-low border border-outline-variant rounded-full text-sm font-inter font-medium focus:border-primary focus:ring-2 focus:ring-primary-fixed-dim transition-all w-56 lg:w-64 text-on-surface placeholder:text-outline"
            placeholder="Tìm kiếm sách..."
            type="text"
            @keyup.enter="doSearch"
          />
          <span class="material-symbols-outlined absolute left-3 text-outline text-[20px]">search</span>
        </div>

        <!-- Cart -->
        <button
          class="relative text-primary hover:text-secondary transition-colors duration-200 cursor-pointer"
          @click="$router.push('/cart')"
          aria-label="Giỏ hàng"
        >
          <span class="material-symbols-outlined">shopping_cart</span>
          <span
            v-if="cartStore.totalItems > 0"
            class="absolute -top-1.5 -right-1.5 bg-secondary text-on-secondary text-[10px] font-bold w-[18px] h-[18px] flex items-center justify-center rounded-full"
          >
            {{ cartStore.totalItems > 9 ? '9+' : cartStore.totalItems }}
          </span>
        </button>

        <!-- Notification Bell -->
        <button
          v-if="authStore.isAuthenticated"
          class="relative text-primary hover:text-secondary transition-colors duration-200 cursor-pointer"
          @click="$router.push('/notifications')"
          aria-label="Thông báo"
        >
          <span class="material-symbols-outlined">notifications</span>
          <span
            v-if="unreadNotificationsCount > 0"
            class="absolute -top-1.5 -right-1.5 bg-error text-on-error text-[10px] font-bold w-[18px] h-[18px] flex items-center justify-center rounded-full"
          >
            {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
          </span>
        </button>

        <!-- User Section -->
        <template v-if="authStore.isAuthenticated">
          <!-- User Avatar & Menu -->
          <div class="flex items-center gap-sm cursor-pointer" @click="toggleUserMenu" aria-haspopup="true" aria-controls="overlay_menu">
            <div class="hidden lg:flex flex-col items-end mr-1">
              <span class="text-[11px] text-outline font-medium">Xin chào,</span>
              <span class="text-sm font-bold text-on-surface">{{ authStore.user?.name }}</span>
            </div>
            <div v-if="authStore.user?.avatar" class="w-9 h-9 rounded-full overflow-hidden border-2 border-surface-container-high shadow-sm">
              <img :src="getAvatarUrl(authStore.user.avatar)" alt="Avatar" class="w-full h-full object-cover" />
            </div>
            <div v-else class="w-9 h-9 rounded-full bg-primary-container flex items-center justify-center">
              <span class="material-symbols-outlined text-on-primary-container text-[20px]">person</span>
            </div>
          </div>
          <Menu ref="userMenu" id="overlay_menu" :model="userMenuItems" :popup="true" />
        </template>

        <template v-else>
          <div class="flex gap-sm items-center">
            <router-link
              to="/login"
              class="hidden md:inline-flex text-sm font-bold text-primary hover:text-secondary transition-colors px-md py-sm"
            >Đăng nhập</router-link>
            <router-link
              to="/register"
              class="hidden md:inline-flex text-sm font-bold bg-primary text-on-primary px-md py-sm rounded-lg hover:bg-primary-container transition-colors shadow-sm"
            >Đăng ký</router-link>
          </div>
        </template>

        <!-- Mobile Menu Button -->
        <button class="md:hidden text-primary" @click="mobileMenuOpen = !mobileMenuOpen">
          <span class="material-symbols-outlined">{{ mobileMenuOpen ? 'close' : 'menu' }}</span>
        </button>
      </div>
    </div>

    <!-- Mobile Menu Dropdown -->
    <Transition name="slide-down">
      <div v-if="mobileMenuOpen" class="md:hidden bg-surface border-t border-outline-variant/20 px-gutter pb-lg">
        <!-- Mobile Search -->
        <div class="relative mt-md mb-md">
          <input
            v-model="searchQuery"
            class="w-full pl-10 pr-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm font-inter text-on-surface placeholder:text-outline"
            placeholder="Tìm kiếm sách..."
            type="text"
            @keyup.enter="doSearch"
          />
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
        </div>

        <!-- Mobile Nav Links -->
        <nav class="flex flex-col gap-xs">
          <router-link to="/" class="flex items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
            <span class="material-symbols-outlined text-[20px]">home</span> Trang chủ
          </router-link>
          <router-link to="/catalog" class="flex items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
            <span class="material-symbols-outlined text-[20px]">search</span> Danh mục sách
          </router-link>
          <router-link to="/blog" class="flex items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
            <span class="material-symbols-outlined text-[20px]">newspaper</span> Tin tức
          </router-link>
          <template v-if="authStore.isAuthenticated">
            <router-link to="/my-library" class="flex items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">local_library</span> Tủ sách
            </router-link>
            <router-link to="/orders" class="flex items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">shopping_bag</span> Đơn hàng
            </router-link>
            <router-link to="/notifications" class="flex items-center justify-between px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="flex items-center gap-md">
                <span class="material-symbols-outlined text-[20px]">notifications</span> Thông báo
              </span>
              <span v-if="unreadNotificationsCount > 0" class="bg-error text-on-error text-[10px] font-bold px-2 py-0.5 rounded-full">
                {{ unreadNotificationsCount }}
              </span>
            </router-link>
            <router-link to="/profile" class="flex items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">person</span> Tài khoản
            </router-link>
            <button @click="handleLogout" class="flex items-center gap-md px-md py-sm rounded-lg text-error hover:bg-error-container/30 transition-colors text-sm font-medium w-full text-left">
              <span class="material-symbols-outlined text-[20px]">logout</span> Đăng xuất
            </button>
          </template>
          <template v-else>
            <router-link to="/login" class="flex items-center gap-md px-md py-sm rounded-lg text-primary hover:bg-surface-container-low transition-colors text-sm font-bold" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">login</span> Đăng nhập
            </router-link>
            <router-link to="/register" class="flex items-center gap-md px-md py-sm rounded-lg bg-primary text-on-primary transition-colors text-sm font-bold" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">person_add</span> Đăng ký
            </router-link>
          </template>
        </nav>
      </div>
    </Transition>
  </header>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import Menu from 'primevue/menu'
import apiClient from '@/services/axios'

const router = useRouter()
const authStore = useAuthStore()
const cartStore = useCartStore()
const logoExists = ref(false)
const mobileMenuOpen = ref(false)
const userMenu = ref()
const searchQuery = ref('')
const unreadNotificationsCount = ref(0)

const fetchUnreadCount = async () => {
  if (!authStore.isAuthenticated) return
  try {
    const res = await apiClient.get('/api/notifications')
    unreadNotificationsCount.value = res.data.unread_count || 0
  } catch (err) {
    console.error('Failed to fetch unread notification count:', err)
  }
}

const userMenuItems = computed(() => {
  const items = [
    {
      label: 'Thông tin cá nhân',
      icon: 'pi pi-user-edit',
      command: () => router.push('/profile')
    },
    {
      label: 'Tủ sách',
      icon: 'pi pi-book',
      command: () => router.push('/my-library')
    },
    {
      label: 'Lịch sử mua hàng',
      icon: 'pi pi-shopping-bag',
      command: () => router.push('/orders')
    },
    {
      label: 'Danh sách yêu thích',
      icon: 'pi pi-heart',
      command: () => router.push('/wishlist')
    },
    {
      label: 'Thông báo',
      icon: 'pi pi-bell',
      command: () => router.push('/notifications')
    },
    { separator: true }
  ]

  if (authStore.isAuthenticated) {
    const authorProfile = authStore.user?.author_profile
    if (authorProfile) {
      if (authorProfile.status === 'active') {
        items.push({
          label: 'Kênh sáng tác Tác giả',
          icon: 'pi pi-pencil',
          command: () => router.push('/vendor/dashboard')
        })
      } else if (authorProfile.status === 'pending') {
        items.push({
          label: 'Đang duyệt Tác giả',
          icon: 'pi pi-clock',
          disabled: true
        })
      } else if (authorProfile.status === 'rejected') {
        items.push({
          label: 'Hồ sơ Tác giả bị từ chối',
          icon: 'pi pi-times-circle',
          command: () => router.push('/author/register')
        })
      }
    } else {
      items.push({
        label: 'Đăng ký làm Tác giả',
        icon: 'pi pi-user-plus',
        command: () => router.push('/author/register')
      })
    }
  }

  items.push({
    label: 'Liên hệ hỗ trợ',
    icon: 'pi pi-question-circle',
    command: () => router.push('/support')
  })
  items.push({
    label: 'Trung tâm trợ giúp',
    icon: 'pi pi-info-circle',
    command: () => router.push('/help-center')
  })

  items.push({ separator: true })
  items.push({
    label: 'Đăng xuất',
    icon: 'pi pi-sign-out',
    command: async () => {
      await authStore.logout()
      router.push({ name: 'home' })
    }
  })

  return items
})

const toggleUserMenu = (event) => {
  userMenu.value.toggle(event)
}

const doSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ name: 'catalog', query: { search: searchQuery.value.trim() } })
    mobileMenuOpen.value = false
  }
}

const handleLogout = async () => {
  mobileMenuOpen.value = false
  await authStore.logout()
  router.push({ name: 'home' })
}

onMounted(() => {
  const img = new Image()
  img.onload = () => logoExists.value = true
  img.onerror = () => logoExists.value = false
  img.src = new URL('@/assets/logo.png', import.meta.url).href
  
  if (authStore.isAuthenticated) {
    fetchUnreadCount()
    setInterval(fetchUnreadCount, 60000)
  }
})

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
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-down-enter-from, .slide-down-leave-to {
  opacity: 0;
  max-height: 0;
  overflow: hidden;
}
.slide-down-enter-to, .slide-down-leave-from {
  max-height: 500px;
}
</style>

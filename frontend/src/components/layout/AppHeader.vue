<template>
  <!-- TopAppBar - Material Design 3 -->
  <header class="sticky top-0 z-50 bg-surface shadow-sm">
    <div class="mx-auto flex w-full max-w-[1280px] items-center justify-between px-4 py-3 md:px-gutter md:py-md">
      <!-- Brand Logo -->
      <router-link to="/" class="flex items-center gap-sm no-underline shrink-0">
        <div class="h-11 w-11 overflow-hidden rounded-xl shadow-md">
          <img v-if="logoExists" src="@/assets/logo.png" alt="KomiBook Logo" class="w-full h-full object-cover" />
          <div v-else class="w-full h-full bg-primary flex items-center justify-center">
            <span class="material-symbols-outlined text-on-primary text-xl">auto_stories</span>
          </div>
        </div>
        <span class="font-inter text-xl md:text-2xl font-bold text-primary tracking-tight">Komibook</span>
      </router-link>

      <!-- Navigation Links (Desktop) -->
      <nav class="hidden items-center gap-md xl:flex" aria-label="Điều hướng chính">
        <router-link
          to="/"
          :class="[
            'flex min-h-11 min-w-11 items-center justify-center border-b-2 border-transparent font-inter text-sm font-medium transition-colors duration-200',
            $route.name === 'home'
              ? 'text-secondary font-bold border-b-2 border-secondary'
              : 'text-outline hover:text-secondary'
          ]"
        >Trang chủ</router-link>
        <div class="relative">
          <button
            type="button"
            :class="[
              'flex min-h-11 min-w-11 items-center justify-center gap-1 border-b-2 border-transparent font-inter text-sm font-medium transition-colors duration-200',
              $route.name === 'catalog' && $route.query.provenance !== 'used_resale'
                ? 'border-secondary font-bold text-secondary'
                : 'text-outline hover:text-secondary'
            ]"
            :aria-expanded="categoryMenuOpen"
            aria-controls="desktop-category-menu"
            @click="categoryMenuOpen = !categoryMenuOpen"
            @keydown.esc="closeNavigationMenus"
          >
            Danh mục
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">
              {{ categoryMenuOpen ? 'expand_less' : 'expand_more' }}
            </span>
          </button>

          <div
            v-if="categoryMenuOpen"
            id="desktop-category-menu"
            class="absolute left-0 top-full z-50 mt-2 w-72 rounded-xl border border-outline-variant bg-surface-container-lowest p-2 shadow-elevated"
          >
            <p class="px-3 pb-2 pt-1 text-sm font-bold text-on-surface">Thể loại nổi bật</p>
            <router-link
              to="/catalog"
              class="flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-primary hover:bg-surface-container-low"
              @click="closeNavigationMenus"
            >
              Tất cả danh mục
            </router-link>
            <p v-if="loadingCategories" class="px-3 py-2 text-sm text-text-muted">Đang tải thể loại…</p>
            <p v-else-if="categoryLoadFailed" class="px-3 py-2 text-sm text-error">
              Chưa thể tải danh sách thể loại.
            </p>
            <router-link
              v-for="category in topCategories"
              :key="category.id"
              :to="{ name: 'catalog', query: { category_id: category.id } }"
              class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 text-sm text-on-surface-variant hover:bg-surface-container-low hover:text-primary"
              @click="closeNavigationMenus"
            >
              <span>{{ category.name }}</span>
              <span class="text-sm text-outline">{{ category.published_books_count }}</span>
            </router-link>
          </div>
        </div>
        <router-link
          :to="{ name: 'catalog', query: { provenance: 'used_resale' } }"
          :class="[
            'flex min-h-11 min-w-11 items-center justify-center border-b-2 border-transparent font-inter text-sm font-medium transition-colors duration-200',
            $route.name === 'catalog' && $route.query.provenance === 'used_resale'
              ? 'border-secondary font-bold text-secondary'
              : 'text-outline hover:text-secondary'
          ]"
        >Sách cũ</router-link>
        <router-link
          to="/blog"
          :class="[
            'flex min-h-11 min-w-11 items-center justify-center border-b-2 border-transparent font-inter text-sm font-medium transition-colors duration-200',
            $route.name === 'blog'
              ? 'text-secondary font-bold border-b-2 border-secondary'
              : 'text-outline hover:text-secondary'
          ]"
        >Tin tức</router-link>
        <router-link
          v-if="canApplyAsVendor"
          :to="vendorRegistrationTarget"
          :class="[
            'flex min-h-11 min-w-11 items-center justify-center border-b-2 border-transparent font-inter text-sm font-semibold transition-colors duration-200',
            $route.name === 'vendor-register'
              ? 'border-secondary text-secondary'
              : 'text-primary hover:text-secondary'
          ]"
        >Đăng ký Nhà bán</router-link>
        <router-link
          v-if="authStore.isAuthenticated"
          to="/my-library"
          :class="[
            'flex min-h-11 min-w-11 items-center justify-center border-b-2 border-transparent font-inter text-sm font-medium transition-colors duration-200',
            $route.name === 'my-library'
              ? 'text-secondary font-bold border-b-2 border-secondary'
              : 'text-outline hover:text-secondary'
          ]"
        >Tủ sách</router-link>
      </nav>

      <!-- Search + Actions -->
      <div class="flex items-center gap-2 md:gap-md">
        <!-- Search Bar (Desktop) -->
        <div class="relative hidden items-center xl:flex">
          <input
            v-model="searchQuery"
            aria-label="Tìm kiếm sách"
            class="min-h-11 pl-10 pr-4 py-2 bg-surface-container-low border border-outline-variant rounded-full text-sm font-inter font-medium focus:border-primary focus:ring-2 focus:ring-primary-fixed-dim transition-all w-56 lg:w-64 text-on-surface placeholder:text-outline"
            placeholder="Tìm kiếm sách..."
            type="text"
            @keyup.enter="doSearch"
          />
          <span class="material-symbols-outlined absolute left-3 text-outline text-[20px]">search</span>
        </div>

        <!-- Cart -->
        <button
          class="relative flex h-11 w-11 cursor-pointer items-center justify-center rounded-lg text-primary transition-colors duration-200 hover:bg-surface-container-low hover:text-secondary"
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
          class="relative hidden h-11 w-11 cursor-pointer items-center justify-center rounded-lg text-primary transition-colors duration-200 hover:bg-surface-container-low hover:text-secondary sm:flex"
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
          <button
            type="button"
            class="hidden min-h-11 cursor-pointer items-center gap-sm rounded-lg px-1 sm:flex"
            aria-label="Mở menu tài khoản"
            aria-haspopup="menu"
            aria-controls="overlay_menu"
            @click="toggleUserMenu"
          >
            <div class="hidden lg:flex flex-col items-end mr-1">
              <span class="text-sm text-outline font-medium">Xin chào,</span>
              <span class="text-sm font-bold text-on-surface">{{ authStore.user?.name }}</span>
            </div>
            <div v-if="authStore.user?.avatar" class="h-10 w-10 overflow-hidden rounded-full border-2 border-surface-container-high shadow-sm">
              <img :src="getAvatarUrl(authStore.user.avatar)" alt="Avatar" class="w-full h-full object-cover" />
            </div>
            <div v-else class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-container">
              <span class="material-symbols-outlined text-on-primary-container text-[20px]">person</span>
            </div>
          </button>
          <Menu ref="userMenu" id="overlay_menu" :model="userMenuItems" :popup="true" />
        </template>

        <template v-else>
          <div class="flex gap-sm items-center">
            <router-link
              to="/login"
              class="hidden min-h-11 items-center px-md py-sm text-sm font-bold text-primary transition-colors hover:text-secondary xl:inline-flex"
            >Đăng nhập</router-link>
            <router-link
              to="/register"
              class="hidden min-h-11 items-center rounded-lg bg-primary px-md py-sm text-sm font-bold text-on-primary shadow-sm transition-colors hover:bg-primary-container xl:inline-flex"
            >Đăng ký</router-link>
          </div>
        </template>

        <!-- Mobile Menu Button -->
        <button
          type="button"
          class="flex h-11 w-11 items-center justify-center rounded-lg text-primary xl:hidden"
          :aria-label="mobileMenuOpen ? 'Đóng menu chính' : 'Mở menu chính'"
          :aria-expanded="mobileMenuOpen"
          aria-controls="mobile-main-menu"
          @click="mobileMenuOpen = !mobileMenuOpen"
          @keydown.esc="closeNavigationMenus"
        >
          <span class="material-symbols-outlined">{{ mobileMenuOpen ? 'close' : 'menu' }}</span>
        </button>
      </div>
    </div>

    <!-- Mobile Menu Dropdown -->
    <Transition name="slide-down">
      <div
        v-if="mobileMenuOpen"
        id="mobile-main-menu"
        class="border-t border-outline-variant/20 bg-surface px-4 pb-lg xl:hidden"
      >
        <!-- Mobile Search -->
        <div class="relative mt-md mb-md">
          <input
            v-model="searchQuery"
            aria-label="Tìm kiếm sách"
            class="min-h-11 w-full pl-10 pr-4 py-2.5 bg-surface-container-low border border-outline-variant rounded-lg text-sm font-inter text-on-surface placeholder:text-outline"
            placeholder="Tìm kiếm sách..."
            type="text"
            @keyup.enter="doSearch"
          />
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
        </div>

        <!-- Mobile Nav Links -->
        <nav class="flex flex-col gap-xs" aria-label="Điều hướng chính trên điện thoại">
          <router-link to="/" class="flex min-h-11 items-center gap-md rounded-lg px-md py-sm text-sm font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low" @click="mobileMenuOpen = false">
            <span class="material-symbols-outlined text-[20px]">home</span> Trang chủ
          </router-link>
          <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between rounded-lg px-md py-sm text-left text-sm font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low"
            :aria-expanded="mobileCategoriesOpen"
            aria-controls="mobile-category-menu"
            @click="mobileCategoriesOpen = !mobileCategoriesOpen"
          >
            <span class="flex items-center gap-md">
              <span class="material-symbols-outlined text-[20px]" aria-hidden="true">category</span>
              Danh mục sách
            </span>
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">
              {{ mobileCategoriesOpen ? 'expand_less' : 'expand_more' }}
            </span>
          </button>
          <div
            v-if="mobileCategoriesOpen"
            id="mobile-category-menu"
            class="ml-4 border-l border-outline-variant pl-3"
          >
            <router-link
              to="/catalog"
              class="flex min-h-11 items-center rounded-lg px-md py-sm text-sm font-semibold text-primary hover:bg-surface-container-low"
              @click="closeNavigationMenus"
            >
              Tất cả danh mục
            </router-link>
            <p v-if="loadingCategories" class="px-md py-sm text-sm text-text-muted">Đang tải thể loại…</p>
            <p v-else-if="categoryLoadFailed" class="px-md py-sm text-sm text-error">
              Chưa thể tải danh sách thể loại.
            </p>
            <router-link
              v-for="category in topCategories"
              :key="category.id"
              :to="{ name: 'catalog', query: { category_id: category.id } }"
              class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-md py-sm text-sm text-on-surface-variant hover:bg-surface-container-low hover:text-primary"
              @click="closeNavigationMenus"
            >
              <span>{{ category.name }}</span>
              <span class="text-sm text-outline">{{ category.published_books_count }}</span>
            </router-link>
          </div>
          <router-link
            :to="{ name: 'catalog', query: { provenance: 'used_resale' } }"
            class="flex min-h-11 items-center gap-md rounded-lg px-md py-sm text-sm font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low"
            @click="closeNavigationMenus"
          >
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">auto_stories</span>
            Sách cũ
          </router-link>
          <router-link to="/blog" class="flex min-h-11 items-center gap-md rounded-lg px-md py-sm text-sm font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low" @click="mobileMenuOpen = false">
            <span class="material-symbols-outlined text-[20px]">newspaper</span> Tin tức
          </router-link>
          <router-link
            v-if="canApplyAsVendor"
            :to="vendorRegistrationTarget"
            class="flex min-h-11 items-center gap-md rounded-lg px-md py-sm text-sm font-bold text-primary transition-colors hover:bg-surface-container-low"
            @click="closeNavigationMenus"
          >
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">storefront</span>
            Đăng ký Nhà bán
          </router-link>
          <template v-if="authStore.isAuthenticated">
            <router-link to="/my-library" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">local_library</span> Tủ sách
            </router-link>
            <router-link to="/orders" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">shopping_bag</span> Đơn hàng
            </router-link>
            <router-link to="/notifications" class="flex min-h-11 items-center justify-between px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="flex items-center gap-md">
                <span class="material-symbols-outlined text-[20px]">notifications</span> Thông báo
              </span>
              <span v-if="unreadNotificationsCount > 0" class="bg-error text-on-error text-[10px] font-bold px-2 py-0.5 rounded-full">
                {{ unreadNotificationsCount }}
              </span>
            </router-link>
            <router-link to="/profile" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors text-sm font-medium" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">person</span> Tài khoản
            </router-link>
            <button @click="handleLogout" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-error hover:bg-error-container/30 transition-colors text-sm font-medium w-full text-left">
              <span class="material-symbols-outlined text-[20px]">logout</span> Đăng xuất
            </button>
          </template>
          <template v-else>
            <router-link to="/login" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg text-primary hover:bg-surface-container-low transition-colors text-sm font-bold" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">login</span> Đăng nhập
            </router-link>
            <router-link to="/register" class="flex min-h-11 items-center gap-md px-md py-sm rounded-lg bg-primary text-on-primary transition-colors text-sm font-bold" @click="mobileMenuOpen = false">
              <span class="material-symbols-outlined text-[20px]">person_add</span> Đăng ký
            </router-link>
          </template>
        </nav>
      </div>
    </Transition>
  </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import Menu from 'primevue/menu'
import apiClient from '@/services/axios'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const cartStore = useCartStore()
const logoExists = ref(false)
const mobileMenuOpen = ref(false)
const categoryMenuOpen = ref(false)
const mobileCategoriesOpen = ref(false)
const topCategories = ref([])
const loadingCategories = ref(false)
const categoryLoadFailed = ref(false)
const userMenu = ref()
const searchQuery = ref('')
const unreadNotificationsCount = ref(0)
let notificationTimer

const canApplyAsVendor = computed(() => (
  !authStore.isAdmin
  && !authStore.user?.capabilities?.active_vendor
))

const vendorRegistrationTarget = computed(() => (
  authStore.isAuthenticated
    ? { name: 'vendor-register' }
    : { name: 'login', query: { redirect: '/vendor/register' } }
))

const closeNavigationMenus = () => {
  mobileMenuOpen.value = false
  categoryMenuOpen.value = false
  mobileCategoriesOpen.value = false
}

const handleGlobalKeydown = (event) => {
  if (event.key === 'Escape') closeNavigationMenus()
}

watch(() => route.fullPath, closeNavigationMenus)

const fetchTopCategories = async () => {
  loadingCategories.value = true
  categoryLoadFailed.value = false
  try {
    const response = await apiClient.get('/api/categories', {
      params: { popular: 1, limit: 10 },
    })
    topCategories.value = Array.isArray(response.data?.data) ? response.data.data : []
  } catch (error) {
    categoryLoadFailed.value = true
    console.error('Failed to fetch public categories:', error)
  } finally {
    loadingCategories.value = false
  }
}

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
    if (authStore.isWarehouseManager) {
      items.push({
        label: 'Không gian Quản kho',
        icon: 'pi pi-box',
        command: () => router.push('/warehouse-manager/dashboard')
      })
    }

    const vendorProfile = authStore.user?.vendor_profile
    if (!authStore.user?.capabilities?.active_vendor) {
      items.push({
        label: vendorProfile?.onboarding_status === 'changes_requested' ? 'Bổ sung hồ sơ Nhà bán' : (vendorProfile ? 'Hồ sơ Nhà bán' : 'Đăng ký Nhà bán'),
        icon: 'pi pi-store',
        command: () => router.push('/vendor/register')
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
      window.location.assign('/')
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
    closeNavigationMenus()
  }
}

const handleLogout = async () => {
  closeNavigationMenus()
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
    notificationTimer = window.setInterval(fetchUnreadCount, 60000)
  }

  fetchTopCategories()
  window.addEventListener('keydown', handleGlobalKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
  if (notificationTimer) window.clearInterval(notificationTimer)
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
  transition:
    opacity 0.22s var(--ui-ease-standard),
    max-height 0.22s var(--ui-ease-standard);
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

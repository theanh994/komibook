<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/services/axios'
import Avatar from 'primevue/avatar'
import Menu from 'primevue/menu'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const sidebarCollapsed = ref(false)
const isMobile = ref(false)
const unreadNotificationsCount = ref(0)
let notificationTimer

// ─── Menu động theo Role ───────────────────────────────────────────────────
const vendorMenuItems = [
  {
    label: 'Tổng quan',
    icon: 'pi pi-th-large',
    route: '/vendor/dashboard',
  },
  {
    label: 'Phân tích Độc giả',
    icon: 'pi pi-chart-line',
    route: '/vendor/analytics',
  },
  {
    label: 'Quản lý Sách',
    icon: 'pi pi-book',
    children: [
      {
        label: 'Tất cả sách',
        icon: 'pi pi-list',
        route: '/vendor/books',
      },
      {
        label: 'Quản lý Bộ Sách',
        icon: 'pi pi-clone',
        route: '/vendor/series',
      },
    ],
  },
  {
    label: 'Quản lý Kho hàng',
    icon: 'pi pi-box',
    route: '/vendor/warehouses',
  },
  {
    label: 'Phiếu nhập / xuất kho',
    icon: 'pi pi-file',
    route: '/vendor/warehouse-documents',
  },
  {
    label: 'Nhân sự kho',
    icon: 'pi pi-users',
    route: '/vendor/warehouse-managers',
  },
  {
    label: 'NXB & Nhà cung cấp',
    icon: 'pi pi-building',
    route: '/vendor/organizations',
  },
  {
    label: 'Quản lý Đơn hàng',
    icon: 'pi pi-shopping-bag',
    route: '/vendor/orders',
  },
  {
    label: 'Trả hàng & Hoàn tiền',
    icon: 'pi pi-replay',
    route: '/vendor/returns',
  },
  {
    label: 'Hỗ trợ khách hàng',
    icon: 'pi pi-comments',
    route: '/vendor/support',
  },
  {
    label: 'Ví KomiBook & Rút tiền',
    icon: 'pi pi-wallet',
    route: '/wallet',
  },
  {
    label: 'Báo cáo doanh thu',
    icon: 'pi pi-chart-pie',
    route: '/vendor/finance',
  },
  {
    label: 'Đăng ký Flash Sale',
    icon: 'pi pi-bolt',
    route: '/vendor/flash-sales',
  },
  {
    label: 'Bài viết & Tin tức',
    icon: 'pi pi-file-edit',
    route: '/vendor/articles',
  },
]

const expandedSubmenus = ref({})

const adminMenuItems = [
  {
    label: 'Tổng quan',
    icon: 'pi pi-chart-bar',
    route: '/admin/dashboard',
  },
  {
    label: 'Quản lý Sách',
    icon: 'pi pi-book',
    children: [
      {
        label: 'Tất cả sách',
        icon: 'pi pi-list',
        route: '/admin/books',
      },
      {
        label: 'Duyệt sách cũ',
        icon: 'pi pi-check-circle',
        route: '/admin/used-books/moderation',
      },
      {
        label: 'Thể loại sách',
        icon: 'pi pi-tags',
        route: '/admin/books/categories',
      },
    ],
  },
  {
    label: 'Kiểm duyệt nội dung',
    icon: 'pi pi-comments',
    route: '/admin/moderation',
  },
  {
    label: 'Khuyến mãi',
    icon: 'pi pi-ticket',
    route: '/admin/coupons',
  },
  {
    label: 'Trả hàng & Hoàn tiền',
    icon: 'pi pi-replay',
    route: '/admin/returns',
  },
  {
    label: 'Quản lý rút tiền',
    icon: 'pi pi-check-square',
    route: '/admin/reconciliation',
  },
  {
    label: 'Báo cáo doanh thu',
    icon: 'pi pi-chart-line',
    route: '/admin/finance-report',
  },
  {
    label: 'Quản lý người dùng',
    icon: 'pi pi-users',
    route: '/admin/users',
  },
  {
    label: 'Hồ sơ Nhà bán',
    icon: 'pi pi-shop',
    route: '/admin/approvals',
  },
  {
    label: 'Chiến dịch thông báo',
    icon: 'pi pi-megaphone',
    route: '/admin/notifications',
  },
  {
    label: 'Hỗ trợ khách hàng',
    icon: 'pi pi-comments',
    route: '/admin/support',
  },
  {
    label: 'Newsroom',
    icon: 'pi pi-file-edit',
    children: [
      { label: 'Bài viết & Tin tức', icon: 'pi pi-list', route: '/admin/articles' },
      { label: 'Review tiềm năng', icon: 'pi pi-star', route: '/admin/article-submissions' },
    ],
  },
  {
    label: 'Tổ chức & cung ứng',
    icon: 'pi pi-building',
    route: '/admin/organization-reviews',
  },
  {
    label: 'Cấu hình hệ thống',
    icon: 'pi pi-cog',
    route: '/admin/system-config',
  },
]

const warehouseManagerMenuItems = [
  {
    label: 'Tổng quan',
    icon: 'pi pi-th-large',
    route: '/warehouse-manager/dashboard',
  },
  {
    label: 'Tồn kho',
    icon: 'pi pi-box',
    route: '/warehouse-manager/inventory',
  },
  {
    label: 'Phiếu nhập / xuất kho',
    icon: 'pi pi-file',
    route: '/warehouse-manager/documents',
  },
]

const menuItems = computed(() => {
  if (authStore.isAdmin) return adminMenuItems
  if (route.path.startsWith('/warehouse-manager')) return warehouseManagerMenuItems
  return vendorMenuItems
})

const panelLabel = computed(() => {
  if (authStore.isAdmin) return 'Admin Panel'
  if (route.path.startsWith('/warehouse-manager')) return 'Kênh Quản lý kho'
  return 'Vendor Panel'
})

const toggleSubmenu = (label) => {
  const nextState = !expandedSubmenus.value[label]
  expandedSubmenus.value = Object.fromEntries(
    Object.keys(expandedSubmenus.value).map((key) => [key, false]),
  )
  expandedSubmenus.value[label] = nextState
}

const isActive = (item) => {
  if (item.route) {
    return route.path === item.route || route.path.startsWith(item.route + '/')
  }
  if (item.children) {
    return item.children.some((child) => route.path === child.route || route.path.startsWith(child.route + '/'))
  }
  return false
}

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

const closeMobileSidebar = () => {
  if (isMobile.value) sidebarCollapsed.value = true
}

const syncExpandedSubmenus = () => {
  const next = {}
  menuItems.value.forEach((item) => {
    if (item.children) next[item.label] = isActive(item)
  })
  expandedSubmenus.value = next
}

const fetchUnreadCount = async () => {
  try {
    const response = await apiClient.get('/api/notifications')
    unreadNotificationsCount.value = Number(response.data?.unread_count) || 0
  } catch {
    unreadNotificationsCount.value = 0
  }
}

const handleGlobalKeydown = (event) => {
  if (event.key === 'Escape') closeMobileSidebar()
}

const syncViewport = () => {
  isMobile.value = window.innerWidth <= 1024
  if (isMobile.value) sidebarCollapsed.value = true
}

watch(() => route.fullPath, () => {
  closeMobileSidebar()
  syncExpandedSubmenus()
  fetchUnreadCount()
})

const userMenuOpen = ref(false)
const userMenuRef = ref(null)

const userRoleLabel = computed(() => {
  if (authStore.isAdmin) return 'Quản trị'
  if (authStore.isVendor || authStore.user?.capabilities?.active_vendor) return 'Nhà bán'
  if (authStore.isWarehouseManager) return 'Quản kho'
  return 'Quản lý'
})

const toggleUserMenu = () => {
  userMenuOpen.value = !userMenuOpen.value
}

const openCustomerSupport = () => {
  userMenuOpen.value = false
  if (authStore.isVendor) {
    router.push('/vendor/support')
  } else {
    router.push('/profile')
  }
}

const handleWindowScroll = () => {
  userMenuOpen.value = false
}

const handleClickOutside = (event) => {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    userMenuOpen.value = false
  }
}

const logout = async () => {
  userMenuOpen.value = false
  await authStore.logout()
  window.location.assign('/')
}

const shopName = computed(() => {
  if (authStore.isAdmin) return 'Quản trị viên'
  return authStore.user?.vendor?.shop_name || authStore.user?.name || 'Vendor'
})

const userAvatarUrl = computed(() => {
  const avatar = authStore.user?.avatar || authStore.user?.vendor?.logo
  if (!avatar) return ''
  if (avatar.startsWith('http://') || avatar.startsWith('https://')) return avatar
  if (avatar.startsWith('/storage/')) return avatar
  if (avatar.includes('/storage/')) {
    return avatar.substring(avatar.indexOf('/storage/'))
  }
  return `/storage/${avatar}`
})

onMounted(() => {
  syncViewport()
  syncExpandedSubmenus()
  fetchUnreadCount()
  notificationTimer = window.setInterval(fetchUnreadCount, 60000)
  window.addEventListener('click', handleClickOutside)
  window.addEventListener('keydown', handleGlobalKeydown)
  window.addEventListener('resize', syncViewport)
  window.addEventListener('scroll', handleWindowScroll, { passive: true, capture: true })
})

onUnmounted(() => {
  if (notificationTimer) window.clearInterval(notificationTimer)
  window.removeEventListener('click', handleClickOutside)
  window.removeEventListener('keydown', handleGlobalKeydown)
  window.removeEventListener('resize', syncViewport)
  window.removeEventListener('scroll', handleWindowScroll, { capture: true })
})
</script>

<template>
  <div class="admin-layout">
    <a class="skip-link" href="#management-content">Bỏ qua điều hướng quản lý</a>

    <!-- Mobile Sidebar Backdrop -->
    <div
      v-if="isMobile && !sidebarCollapsed"
      class="sidebar-backdrop"
      aria-hidden="true"
      @click="sidebarCollapsed = true"
    ></div>

    <!-- ═══ SIDEBAR ═══ -->
    <aside
      id="management-sidebar"
      class="admin-sidebar"
      :class="{ 'sidebar-collapsed': sidebarCollapsed }"
      aria-label="Điều hướng trang quản lý"
      :aria-hidden="isMobile && sidebarCollapsed"
      :inert="isMobile && sidebarCollapsed"
    >
      <!-- Sidebar Header -->
      <div class="sidebar-header">
        <router-link to="/" class="sidebar-brand" title="Về trang chủ KomiBook">
          <div class="brand-icon overflow-hidden flex items-center justify-center">
            <img src="@/assets/logo.png" alt="KomiBook Logo" class="h-full w-full object-contain" />
          </div>
          <Transition name="fade">
            <div v-if="!sidebarCollapsed" class="brand-text">
              <span class="brand-name">Komi<span class="brand-accent">Book</span></span>
              <span class="brand-sub">{{ panelLabel }}</span>
            </div>
          </Transition>
        </router-link>

        <!-- Toggle Collapse Button Inside Sidebar -->
        <button
          type="button"
          class="sidebar-toggle-btn"
          :title="sidebarCollapsed ? 'Mở rộng thanh điều hướng' : 'Thu gọn thanh điều hướng'"
          :aria-label="sidebarCollapsed ? 'Mở rộng thanh điều hướng' : 'Thu gọn thanh điều hướng'"
          @click="toggleSidebar"
        >
          <i :class="sidebarCollapsed ? 'pi pi-angle-right' : 'pi pi-angle-left'" aria-hidden="true"></i>
        </button>
      </div>

      <!-- Navigation -->
      <nav class="sidebar-nav">
        <div class="nav-section">
          <ul class="nav-list">
            <template v-for="item in menuItems" :key="item.label || item.route">
              <!-- Regular Item -->
              <li v-if="!item.children">
                <router-link
                  :to="item.route"
                  class="nav-item"
                  :class="{ active: isActive(item) }"
                  :aria-label="item.label"
                  :aria-current="isActive(item) ? 'page' : undefined"
                  @click="closeMobileSidebar"
                >
                  <div class="nav-indicator"></div>
                  <i :class="item.icon" class="nav-icon"></i>
                  <Transition name="fade">
                    <span v-if="!sidebarCollapsed" class="nav-text">{{ item.label }}</span>
                  </Transition>
                </router-link>
              </li>

              <!-- Item with Children Submenu -->
              <li v-else class="nav-group-wrapper">
                <button
                  type="button"
                  class="nav-item nav-parent-item"
                  :class="{ active: isActive(item) }"
                  :aria-label="item.label"
                  :aria-expanded="expandedSubmenus[item.label]"
                  @click="toggleSubmenu(item.label)"
                >
                  <div class="nav-indicator"></div>
                  <i :class="item.icon" class="nav-icon"></i>
                  <Transition name="fade">
                    <div v-if="!sidebarCollapsed" class="flex items-center justify-between w-full pr-1">
                      <span class="nav-text">{{ item.label }}</span>
                      <i
                        class="pi text-[10px] transition-transform duration-200"
                        :class="expandedSubmenus[item.label] ? 'pi-chevron-down' : 'pi-chevron-right'"
                      ></i>
                    </div>
                  </Transition>
                </button>

                <!-- Submenu Items -->
                <ul
                  v-if="expandedSubmenus[item.label] && !sidebarCollapsed"
                  class="sub-nav-list"
                >
                  <li
                    v-for="child in item.children"
                    :key="child.route"
                  >
                    <router-link
                      :to="child.route"
                      class="nav-item sub-nav-item"
                      :class="{ active: isActive(child) }"
                      :aria-label="child.label"
                      :aria-current="isActive(child) ? 'page' : undefined"
                      @click="closeMobileSidebar"
                    >
                      <div class="nav-indicator"></div>
                      <i :class="child.icon" class="nav-icon sub-nav-icon"></i>
                      <span class="nav-text text-sm font-medium">{{ child.label }}</span>
                    </router-link>
                  </li>
                </ul>
              </li>
            </template>
          </ul>
        </div>
      </nav>

      <!-- Sidebar Bottom -->
      <div class="sidebar-bottom">
        <div class="nav-divider"></div>
        <div class="sidebar-account" :title="authStore.user?.name">
          <div class="sidebar-account-avatar">
            <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="authStore.user?.name" />
            <i v-else class="pi pi-user" aria-hidden="true"></i>
          </div>
          <div v-if="!sidebarCollapsed" class="sidebar-account-copy">
            <strong>{{ authStore.user?.name }}</strong>
            <span>{{ shopName }}</span>
          </div>
        </div>
        <ul class="nav-list">
          <li>
            <button class="nav-item nav-item-bottom sidebar-logout" type="button" aria-label="Đăng xuất" @click="logout">
              <i class="pi pi-sign-out nav-icon"></i>
              <span v-if="!sidebarCollapsed" class="nav-text">Đăng xuất</span>
            </button>
          </li>
        </ul>
      </div>
    </aside>

    <!-- ═══ MAIN CONTENT AREA ═══ -->
    <div class="admin-main" :class="{ 'main-expanded': sidebarCollapsed }">
      <!-- Topbar -->
      <header class="admin-topbar">
        <div class="topbar-left">
          <div class="breadcrumb">
            <span class="breadcrumb-text">{{ route.meta.title || 'Dashboard' }}</span>
          </div>
        </div>

        <div class="topbar-right">
          <!-- Notifications Button -->
          <button
            class="topbar-icon-btn"
            type="button"
            :aria-label="unreadNotificationsCount > 0 ? `Thông báo, ${unreadNotificationsCount} chưa đọc` : 'Thông báo'"
            @click="router.push('/notifications')"
          >
            <i class="pi pi-bell"></i>
            <span v-if="unreadNotificationsCount > 0" class="notification-badge" aria-hidden="true">{{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}</span>
          </button>

          <!-- User Profile & Custom Dropdown -->
          <div ref="userMenuRef" class="relative">
            <button
              type="button"
              class="topbar-user"
              :class="{ 'bg-slate-100 dark:bg-slate-800': userMenuOpen }"
              aria-label="Mở menu tài khoản"
              aria-haspopup="menu"
              :aria-expanded="userMenuOpen"
              @click.stop="toggleUserMenu"
            >
              <div v-if="userAvatarUrl" class="w-8 h-8 rounded-full overflow-hidden border border-slate-200 shadow-xs shrink-0 bg-slate-100 flex items-center justify-center">
                <img :src="userAvatarUrl" :alt="authStore.user?.name" class="w-full h-full object-cover" />
              </div>
              <Avatar
                v-else
                icon="pi pi-user"
                shape="circle"
                class="user-avatar"
              />
              <div class="user-info">
                <span class="user-name">{{ authStore.user?.name }}</span>
                <span class="user-role">{{ shopName }}</span>
              </div>
              <i class="pi pi-chevron-down user-chevron transition-transform duration-200" :class="{ 'rotate-180': userMenuOpen }"></i>
            </button>

            <!-- Custom User Dropdown Panel -->
            <Transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 -translate-y-2"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 -translate-y-2"
            >
              <div
                v-if="userMenuOpen"
                class="absolute right-0 top-full mt-2 w-72 z-[100] overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2 shadow-2xl origin-top-right text-slate-800 dark:text-slate-100"
                role="menu"
                @click.stop
              >
                <!-- User Profile Header Card -->
                <div class="mb-2 flex items-center gap-3 rounded-xl bg-slate-100 dark:bg-slate-800/80 p-3 border border-slate-200/50 dark:border-slate-700/50">
                  <div class="h-11 w-11 shrink-0 overflow-hidden rounded-full border-2 border-emerald-500/40 shadow-xs">
                    <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="authStore.user?.name" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full w-full items-center justify-center bg-indigo-100 text-indigo-700 font-bold">
                      <i class="pi pi-user text-lg"></i>
                    </div>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-1">
                      <h4 class="truncate text-sm font-bold">{{ authStore.user?.name }}</h4>
                      <span class="shrink-0 rounded-full bg-emerald-100 dark:bg-emerald-950 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                        {{ userRoleLabel }}
                      </span>
                    </div>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ authStore.user?.email || 'Tài khoản quản lý' }}</p>
                  </div>
                </div>

                <!-- Navigation List -->
                <div class="space-y-0.5 text-sm">
                  <router-link to="/profile" class="flex items-center justify-between rounded-xl px-3 py-2 text-slate-700 dark:text-slate-200 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-user-edit text-base text-indigo-500"></i>Thông tin cá nhân</span>
                  </router-link>

                  <router-link to="/notifications" class="flex items-center justify-between rounded-xl px-3 py-2 text-slate-700 dark:text-slate-200 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-bell text-base text-indigo-500"></i>Thông báo</span>
                    <span v-if="unreadNotificationsCount > 0" class="rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}</span>
                  </router-link>

                  <button type="button" class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-slate-700 dark:text-slate-200 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" @click="openCustomerSupport">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-comments text-base text-indigo-500"></i>Hộp thư hỗ trợ</span>
                  </button>

                  <router-link to="/help-center" class="flex items-center justify-between rounded-xl px-3 py-2 text-slate-700 dark:text-slate-200 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 no-underline" @click="userMenuOpen = false">
                    <span class="flex items-center gap-2.5 font-medium"><i class="pi pi-question-circle text-base text-indigo-500"></i>Trung tâm trợ giúp</span>
                  </router-link>
                </div>

                <!-- Featured Role Switcher Box (Switch Space back to Customer Storefront / Home) -->
                <div class="mt-2 pt-1.5 border-t border-slate-200/50 dark:border-slate-800">
                  <router-link
                    to="/"
                    class="group flex w-full items-center justify-between rounded-xl border border-emerald-500/30 bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-100/50 dark:from-emerald-950/40 dark:to-teal-950/40 p-2.5 text-left no-underline transition-all hover:scale-[1.01] hover:border-emerald-500 hover:shadow-md cursor-pointer"
                    @click="userMenuOpen = false"
                  >
                    <div class="flex items-center gap-2.5">
                      <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs group-hover:bg-emerald-500">
                        <i class="pi pi-home text-sm"></i>
                      </div>
                      <div>
                        <span class="block text-xs font-bold leading-tight text-slate-900 dark:text-slate-100">Trang chủ KomiBook</span>
                        <span class="mt-0.5 block text-[11px] font-medium text-slate-500 dark:text-slate-400">Không gian mua sắm</span>
                      </div>
                    </div>
                    <span class="material-symbols-outlined text-base text-emerald-600 dark:text-emerald-400 transition-transform group-hover:translate-x-0.5">arrow_forward</span>
                  </router-link>
                </div>

                <!-- Log Out Button -->
                <div class="mt-1 pt-1 border-t border-slate-200/50 dark:border-slate-800">
                  <button
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-semibold text-red-600 dark:text-red-400 transition-colors hover:bg-red-50 dark:hover:bg-red-950/50 cursor-pointer"
                    @click="logout"
                  >
                    <i class="pi pi-sign-out text-base"></i>
                    <span>Đăng xuất</span>
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main id="management-content" class="admin-content" data-route-focus tabindex="-1">
        <RouterView v-slot="{ Component, route: currentRoute }">
          <component :is="Component" :key="currentRoute.fullPath" />
        </RouterView>
      </main>
    </div>
  </div>
</template>

<style scoped>
/* ═══ LAYOUT GRID ═══ */
.admin-layout {
  --color-primary: #007a37;
  --color-on-primary: #ffffff;
  --color-primary-container: #d8f5e3;
  --color-on-primary-container: #064e2b;
  --color-commerce: #007a37;
  --color-secondary: #ba0035;
  display: flex;
  min-height: 100vh;
  background: var(--color-background);
}

/* ═══ SIDEBAR ═══ */
.admin-sidebar {
  width: 260px;
  background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
  color: #cbd5e1;
  display: flex;
  flex-direction: column;
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 50;
  overflow: hidden;
}

.sidebar-collapsed {
  width: 72px;
}

.sidebar-collapsed .sidebar-header {
  flex-direction: column;
  padding: 12px 8px;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.sidebar-collapsed .sidebar-brand {
  flex: 0 0 auto;
}

/* Sidebar Header */
.sidebar-header {
  padding: 16px 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  flex: 1;
  text-decoration: none;
  color: inherit;
}

.sidebar-toggle-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.06);
  color: #94a3b8;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.sidebar-toggle-btn:hover {
  background: rgba(255, 255, 255, 0.18);
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.25);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.sidebar-edge-toggle:hover,
.sidebar-edge-toggle:focus-visible {
  color: #ffffff;
  background: transparent;
}

.sidebar-edge-toggle:focus-visible {
  outline: 3px solid var(--color-primary-fixed-dim);
  outline-offset: 2px;
}

.brand-text {
  display: flex;
  flex-direction: column;
  white-space: nowrap;
}

.brand-name {
  font-size: 18px;
  font-weight: 800;
  color: #f1f5f9;
  letter-spacing: -0.02em;
}

.brand-accent {
  color: var(--color-brand-green);
}

.brand-sub {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.15em;
  color: #64748b;
  margin-top: -2px;
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 16px 0;
  overflow-y: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.sidebar-nav::-webkit-scrollbar {
  display: none;
}

.nav-section {
  padding: 0 12px;
}

.nav-label {
  display: block;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #475569;
}

.nav-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.nav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 44px;
  padding: 10px 12px;
  margin: 2px 0;
  border-radius: 10px;
  cursor: pointer;
  transition:
    background-color var(--ui-duration-normal) var(--ui-ease-standard),
    color var(--ui-duration-normal) var(--ui-ease-standard);
  color: #94a3b8;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  width: 100%;
  border: 0;
  background: transparent;
  text-align: left;
  text-decoration: none;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.06);
  color: #e2e8f0;
}

.nav-item.active {
  background: rgba(99, 102, 241, 0.15);
  color: #a5b4fc;
}

.nav-indicator {
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 0;
  background: var(--color-brand-green);
  border-radius: 0 4px 4px 0;
  transition: height 0.2s ease;
}

.nav-item.active .nav-indicator {
  height: 20px;
}

.sub-nav-list {
  list-style: none;
  padding: 0 0 0 20px;
  margin: 2px 0 6px 0;
}

.sub-nav-item {
  padding: 10px 12px;
  font-size: 14px;
}

.sub-nav-icon {
  font-size: 14px;
}

.nav-icon {
  font-size: 16px;
  min-width: 20px;
  text-align: center;
}

.nav-text {
  white-space: nowrap;
}

/* Sidebar Bottom */
.sidebar-bottom {
  padding: 12px;
}

.sidebar-account {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 52px;
  margin-bottom: 8px;
  padding: 6px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.05);
}

.sidebar-account-avatar {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  display: grid;
  place-items: center;
  overflow: hidden;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
  color: #f8fafc;
}

.sidebar-account-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sidebar-account-copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.sidebar-account-copy strong,
.sidebar-account-copy span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sidebar-account-copy strong {
  color: #f8fafc;
  font-size: 13px;
}

.sidebar-account-copy span {
  color: #94a3b8;
  font-size: 11px;
}

.sidebar-logout {
  width: 100%;
  border: 0;
  background: transparent;
  font: inherit;
  cursor: pointer;
}

.nav-divider {
  height: 1px;
  background: rgba(255, 255, 255, 0.06);
  margin-bottom: 8px;
}

.nav-item-bottom {
  color: #64748b;
}

.nav-item-bottom:hover {
  color: #f1f5f9;
}

/* ═══ MAIN AREA ═══ */
.admin-main {
  flex: 1;
  margin-left: 260px;
  width: calc(100% - 260px);
  max-width: 100%;
  min-width: 0;
  overflow-x: clip;
  transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.main-expanded {
  margin-left: 72px;
  width: calc(100% - 72px);
}

/* ═══ TOPBAR ═══ */
.admin-topbar {
  height: 64px;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  position: sticky;
  top: 0;
  z-index: 40;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.breadcrumb-text {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.topbar-icon-btn {
  position: relative;
  width: 44px;
  height: 44px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition:
    background-color var(--ui-duration-normal) var(--ui-ease-standard),
    color var(--ui-duration-normal) var(--ui-ease-standard);
}

.topbar-icon-btn:hover {
  background: #f1f5f9;
  color: #334155;
}

.notification-badge {
  position: absolute;
  top: 4px;
  right: 3px;
  display: grid;
  min-width: 17px;
  height: 17px;
  place-items: center;
  padding: 0 4px;
  background: #ef4444;
  color: #ffffff;
  border-radius: 999px;
  border: 2px solid white;
  font-size: 9px;
  font-weight: 800;
  line-height: 1;
}

.topbar-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px;
  border-radius: 10px;
  cursor: pointer;
  min-height: 44px;
  background: transparent;
  transition:
    background-color var(--ui-duration-normal) var(--ui-ease-standard),
    border-color var(--ui-duration-normal) var(--ui-ease-standard);
  border: 1px solid transparent;
}

.topbar-user:hover {
  background: #f1f5f9;
  border-color: #e2e8f0;
}

.user-avatar {
  background: linear-gradient(135deg, var(--color-brand-green-strong), var(--color-brand-green)) !important;
  color: white !important;
  width: 34px !important;
  height: 34px !important;
  font-size: 14px !important;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.2;
}

.user-role {
  font-size: 12px;
  color: #64748b;
}

.user-chevron {
  font-size: 10px;
  color: #94a3b8;
}

/* ═══ CONTENT ═══ */
.admin-content {
  flex: 1;
  padding: 24px;
}

/* ═══ TRANSITIONS ═══ */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ═══ RESPONSIVE ═══ */
.sidebar-backdrop {
  display: none;
}

@media (max-width: 1024px) {
  .admin-sidebar {
    width: 72px;
  }

  .admin-main {
    margin-left: 72px;
    width: calc(100% - 72px);
  }

  .brand-text,
  .nav-text,
  .nav-label {
    display: none !important;
  }

  .user-info {
    display: none;
  }

  .user-chevron {
    display: none;
  }

  .admin-content {
    padding: 16px;
  }
}

@media (max-width: 1024px) {
  .sidebar-backdrop {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    z-index: 990;
    transition: opacity 0.3s ease;
  }

  .admin-sidebar {
    width: 260px !important;
    position: fixed;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    z-index: 999;
  }

  .sidebar-collapsed {
    transform: translateX(-100%) !important;
  }

  .admin-sidebar:not(.sidebar-collapsed) {
    transform: translateX(0) !important;
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.25);
  }

  .admin-main {
    margin-left: 0 !important;
    width: 100% !important;
  }

  .main-expanded {
    margin-left: 0 !important;
    width: 100% !important;
  }

  .brand-text,
  .nav-text,
  .nav-label {
    display: block !important;
  }

  .admin-topbar {
    padding-inline: 12px;
  }

  .breadcrumb-text {
    display: block;
    max-width: 12rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>

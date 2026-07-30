<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Avatar from 'primevue/avatar'
import Menu from 'primevue/menu'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const sidebarCollapsed = ref(false)
const isMobile = ref(false)
const userMenu = ref()

// ─── Menu động theo Role ───────────────────────────────────────────────────
const vendorMenuItems = [
  {
    label: 'Dashboard',
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
    label: 'Nhân sự kho',
    icon: 'pi pi-users',
    route: '/vendor/warehouse-managers',
  },
  {
    label: 'Phiếu nhập / xuất kho',
    icon: 'pi pi-file',
    route: '/vendor/warehouse-documents',
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
    label: 'Doanh thu & Rút tiền',
    icon: 'pi pi-wallet',
    route: '/vendor/finance',
  },
  {
    label: 'Đăng ký Flash Sale',
    icon: 'pi pi-bolt',
    route: '/vendor/flash-sales',
  },
]

const expandedSubmenus = ref({
  'Quản lý Sách': true,
  'Sách cũ & Kho': true,
})

const adminMenuItems = [
  {
    label: 'Tổng quan',
    icon: 'pi pi-chart-bar',
    route: '/admin/dashboard',
  },
  {
    label: 'Quản lý Users',
    icon: 'pi pi-users',
    route: '/admin/users',
  },
  {
    label: 'Trả hàng & Hoàn tiền',
    icon: 'pi pi-replay',
    route: '/admin/returns',
  },
  {
    label: 'Kiểm duyệt đánh giá',
    icon: 'pi pi-comments',
    route: '/admin/reviews/moderation',
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
        label: 'Thể loại sách',
        icon: 'pi pi-tags',
        route: '/admin/books/categories',
      },
      {
        label: 'Kiểm duyệt xuất bản',
        icon: 'pi pi-check-circle',
        route: '/admin/publishing-reviews',
      },
    ],
  },
  {
    label: 'Khuyến mãi',
    icon: 'pi pi-ticket',
    route: '/admin/coupons',
  },
  {
    label: 'Báo cáo tài chính',
    icon: 'pi pi-chart-line',
    route: '/admin/finance-report',
  },
  {
    label: 'Đối soát',
    icon: 'pi pi-check-square',
    route: '/admin/reconciliation',
  },
  {
    label: 'Chiến dịch thông báo',
    icon: 'pi pi-megaphone',
    route: '/admin/notifications',
  },
  {
    label: 'CMS bài viết',
    icon: 'pi pi-file-edit',
    route: '/admin/articles',
  },
  {
    label: 'Cấu hình hệ thống',
    icon: 'pi pi-cog',
    route: '/admin/system-config',
  },
  {
    label: 'Tổ chức & cung ứng',
    icon: 'pi pi-building',
    route: '/admin/organization-reviews',
  },
]

const warehouseManagerMenuItems = [
  {
    label: 'Tổng quan',
    icon: 'pi pi-th-large',
    route: '/warehouse-manager/dashboard',
  },
  {
    label: 'Tồn kho & vị trí kệ',
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

const bottomItems = [
  {
    label: 'Quay lại Trang chủ',
    icon: 'pi pi-arrow-left',
    route: '/',
  },
]

const toggleSubmenu = (label) => {
  expandedSubmenus.value[label] = !expandedSubmenus.value[label]
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

const handleGlobalKeydown = (event) => {
  if (event.key === 'Escape') closeMobileSidebar()
}

const syncViewport = () => {
  isMobile.value = window.innerWidth <= 1024
  if (isMobile.value) sidebarCollapsed.value = true
}

watch(() => route.fullPath, closeMobileSidebar)

const userMenuItems = ref([
  {
    label: 'Thông tin cá nhân',
    icon: 'pi pi-user-edit',
    command: () => router.push('/profile'),
  },
  { separator: true },
  {
    label: 'Đăng xuất',
    icon: 'pi pi-sign-out',
    command: async () => {
      await authStore.logout()
      router.push({ name: 'home' })
    },
  },
])

const toggleUserMenu = (event) => {
  userMenu.value.toggle(event)
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
  window.addEventListener('keydown', handleGlobalKeydown)
  window.addEventListener('resize', syncViewport)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
  window.removeEventListener('resize', syncViewport)
})
</script>

<template>
  <div class="admin-layout">
    <a class="skip-link" href="#management-content">Bỏ qua điều hướng quản lý</a>

    <!-- Mobile Sidebar Backdrop -->
    <div
      v-if="!sidebarCollapsed"
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
          <div class="brand-icon overflow-hidden bg-white/10 p-0.5 shadow-md flex items-center justify-center">
            <img src="@/assets/logo.png" alt="KomiBook Logo" class="w-full h-full object-cover rounded-lg" />
          </div>
          <Transition name="fade">
            <div v-if="!sidebarCollapsed" class="brand-text">
              <span class="brand-name">Komi<span class="brand-accent">Book</span></span>
              <span class="brand-sub">{{ panelLabel }}</span>
            </div>
          </Transition>
        </router-link>
      </div>

      <!-- Navigation -->
      <nav class="sidebar-nav">
        <div class="nav-section">
          <span v-if="!sidebarCollapsed" class="nav-label">MENU CHÍNH</span>
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
        <ul class="nav-list">
          <li
            v-for="item in bottomItems"
            :key="item.route"
          >
            <router-link
              :to="item.route"
              class="nav-item nav-item-bottom"
              :aria-label="item.label"
              @click="closeMobileSidebar"
            >
              <i :class="item.icon" class="nav-icon"></i>
              <Transition name="fade">
                <span v-if="!sidebarCollapsed" class="nav-text">{{ item.label }}</span>
              </Transition>
            </router-link>
          </li>
        </ul>
      </div>
    </aside>

    <!-- ═══ MAIN CONTENT AREA ═══ -->
    <div class="admin-main" :class="{ 'main-expanded': sidebarCollapsed }">
      <!-- Topbar -->
      <header class="admin-topbar">
        <div class="topbar-left">
          <button
            class="toggle-btn"
            type="button"
            :aria-label="sidebarCollapsed ? 'Mở thanh điều hướng' : 'Đóng thanh điều hướng'"
            :aria-expanded="!sidebarCollapsed"
            aria-controls="management-sidebar"
            @click="toggleSidebar"
          >
            <i :class="sidebarCollapsed ? 'pi pi-bars' : 'pi pi-times'"></i>
          </button>
          <div class="breadcrumb">
            <span class="breadcrumb-text">{{ route.meta.title || 'Dashboard' }}</span>
          </div>
        </div>

        <div class="topbar-right">
          <!-- Notifications Button -->
          <button
            class="topbar-icon-btn"
            type="button"
            aria-label="Thông báo"
            @click="router.push('/notifications')"
          >
            <i class="pi pi-bell"></i>
            <span class="notification-dot"></span>
          </button>

          <!-- User Profile -->
          <button
            type="button"
            class="topbar-user"
            @click="toggleUserMenu"
            aria-label="Mở menu tài khoản"
            aria-haspopup="menu"
            aria-controls="admin_user_menu"
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
            <i class="pi pi-chevron-down user-chevron"></i>
          </button>
          <Menu ref="userMenu" id="admin_user_menu" :model="userMenuItems" :popup="true" />
        </div>
      </header>

      <!-- Page Content -->
      <main id="management-content" class="admin-content" data-route-focus tabindex="-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
/* ═══ LAYOUT GRID ═══ */
.admin-layout {
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

/* Sidebar Header */
.sidebar-header {
  padding: 20px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  color: inherit;
}

.brand-icon {
  width: 40px;
  height: 40px;
  min-width: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: white;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
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
  color: #818cf8;
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
  background: #818cf8;
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

.toggle-btn {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition:
    background-color var(--ui-duration-normal) var(--ui-ease-standard),
    border-color var(--ui-duration-normal) var(--ui-ease-standard),
    color var(--ui-duration-normal) var(--ui-ease-standard);
}

.toggle-btn:hover {
  background: #f1f5f9;
  color: #334155;
  border-color: #cbd5e1;
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

.notification-dot {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 7px;
  height: 7px;
  background: #ef4444;
  border-radius: 50%;
  border: 2px solid white;
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
  background: linear-gradient(135deg, #6366f1, #818cf8) !important;
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

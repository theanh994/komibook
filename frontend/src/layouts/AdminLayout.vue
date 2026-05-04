<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Menu from 'primevue/menu'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const sidebarCollapsed = ref(false)
const userMenu = ref()

// ─── Menu động theo Role ───────────────────────────────────────────────────
const vendorMenuItems = [
  {
    label: 'Dashboard',
    icon: 'pi pi-th-large',
    route: '/vendor/dashboard',
  },
  {
    label: 'Quản lý Sách',
    icon: 'pi pi-book',
    route: '/vendor/books',
  },
  {
    label: 'Quản lý Đơn hàng',
    icon: 'pi pi-shopping-bag',
    route: '/vendor/orders',
  },
]

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
]

const menuItems = computed(() => {
  return authStore.isAdmin ? adminMenuItems : vendorMenuItems
})

const panelLabel = computed(() => {
  return authStore.isAdmin ? 'Admin Panel' : 'Vendor Panel'
})

const bottomItems = [
  {
    label: 'Quay lại Trang chủ',
    icon: 'pi pi-arrow-left',
    route: '/',
  },
]

const isActive = (item) => {
  return route.path === item.route || route.path.startsWith(item.route + '/')
}

const navigateTo = (item) => {
  router.push(item.route)
}

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

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
</script>

<template>
  <div class="admin-layout">
    <!-- ═══ SIDEBAR ═══ -->
    <aside
      class="admin-sidebar"
      :class="{ 'sidebar-collapsed': sidebarCollapsed }"
    >
      <!-- Sidebar Header -->
      <div class="sidebar-header">
        <router-link to="/" class="sidebar-brand">
          <div class="brand-icon">
            <i class="pi pi-book"></i>
          </div>
          <transition name="fade">
            <div v-if="!sidebarCollapsed" class="brand-text">
              <span class="brand-name">Komi<span class="brand-accent">Book</span></span>
              <span class="brand-sub">{{ panelLabel }}</span>
            </div>
          </transition>
        </router-link>
      </div>

      <!-- Navigation -->
      <nav class="sidebar-nav">
        <div class="nav-section">
          <span v-if="!sidebarCollapsed" class="nav-label">MENU CHÍNH</span>
          <ul class="nav-list">
            <li
              v-for="item in menuItems"
              :key="item.route"
              class="nav-item"
              :class="{ active: isActive(item) }"
              @click="navigateTo(item)"
            >
              <div class="nav-indicator"></div>
              <i :class="item.icon" class="nav-icon"></i>
              <transition name="fade">
                <span v-if="!sidebarCollapsed" class="nav-text">{{ item.label }}</span>
              </transition>
            </li>
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
            class="nav-item nav-item-bottom"
            @click="navigateTo(item)"
          >
            <i :class="item.icon" class="nav-icon"></i>
            <transition name="fade">
              <span v-if="!sidebarCollapsed" class="nav-text">{{ item.label }}</span>
            </transition>
          </li>
        </ul>
      </div>
    </aside>

    <!-- ═══ MAIN CONTENT AREA ═══ -->
    <div class="admin-main" :class="{ 'main-expanded': sidebarCollapsed }">
      <!-- Topbar -->
      <header class="admin-topbar">
        <div class="topbar-left">
          <button class="toggle-btn" @click="toggleSidebar">
            <i :class="sidebarCollapsed ? 'pi pi-bars' : 'pi pi-times'"></i>
          </button>
          <div class="breadcrumb">
            <span class="breadcrumb-text">{{ route.meta.title || 'Dashboard' }}</span>
          </div>
        </div>

        <div class="topbar-right">
          <!-- Notifications placeholder -->
          <button class="topbar-icon-btn">
            <i class="pi pi-bell"></i>
            <span class="notification-dot"></span>
          </button>

          <!-- User Profile -->
          <div
            class="topbar-user"
            @click="toggleUserMenu"
            aria-haspopup="true"
            aria-controls="admin_user_menu"
          >
            <Avatar
              icon="pi pi-user"
              shape="circle"
              class="user-avatar"
            />
            <div class="user-info">
              <span class="user-name">{{ authStore.user?.name }}</span>
              <span class="user-role">{{ shopName }}</span>
            </div>
            <i class="pi pi-chevron-down user-chevron"></i>
          </div>
          <Menu ref="userMenu" id="admin_user_menu" :model="userMenuItems" :popup="true" />
        </div>
      </header>

      <!-- Page Content -->
      <main class="admin-content">
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
  background: #f8fafc;
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
  font-size: 10px;
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
  font-size: 10px;
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
  padding: 10px 12px;
  margin: 2px 0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
  color: #94a3b8;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
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
  transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.main-expanded {
  margin-left: 72px;
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
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
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
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
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
  transition: all 0.2s ease;
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
  font-size: 11px;
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
@media (max-width: 768px) {
  .admin-sidebar {
    width: 72px;
  }

  .admin-main {
    margin-left: 72px;
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
</style>

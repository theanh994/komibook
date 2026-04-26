import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
      meta: { guestOnly: false, requiresAuth: false }
    },
    {
      path: '/book/:slug',
      name: 'book-detail',
      component: () => import('@/views/BookDetailView.vue'),
      meta: { guestOnly: false, requiresAuth: false }
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/ProfileView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guestOnly: true }
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guestOnly: true }
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      redirect: to => {
        const authStore = useAuthStore()
        if (authStore.isAdmin) return { name: 'admin-dashboard' }
        if (authStore.isVendor) return { name: 'vendor-dashboard' }
        return '/' // Customer hoặc khác về home
      }
    },
    {
      path: '/admin/dashboard',
      name: 'admin-dashboard',
      component: () => import('@/views/admin/AdminDashboard.vue'),
      meta: { requiresAuth: true, role: 'admin' }
    },
    {
      path: '/vendor/dashboard',
      name: 'vendor-dashboard',
      component: () => import('@/views/vendor/VendorDashboard.vue'),
      meta: { requiresAuth: true, role: 'vendor' }
    }
  ],
})

// Route guard kiểm tra đăng nhập & phân quyền
router.beforeEach(async (to, from) => {
  const authStore = useAuthStore()
  
  // Khôi phục user state nếu có token nhưng user chưa được load
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser()
  }

  const isAuthenticated = authStore.isAuthenticated

  // 1. Kiểm tra yêu cầu auth chung
  if (to.meta.requiresAuth && !isAuthenticated) {
    return { name: 'login' }
  } 
  
  // 2. Chặn truy cập trang guest (login/register) khi đã đăng nhập
  if (to.meta.guestOnly && isAuthenticated) {
    return { name: 'dashboard' }
  }

  // 3. Kiểm tra quyền hạn (Role-based)
  if (to.meta.role && authStore.user?.role !== to.meta.role) {
    return { name: 'home' } // Hoặc trang 403
  }
  
  return true
})

export default router

import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    // Nếu có vị trí lưu trước (ví dụ nút back) → khôi phục
    if (savedPosition) return savedPosition
    // Luôn cuộn lên đầu trang khi chuyển route
    return { top: 0, behavior: 'smooth' }
  },
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
      meta: { guestOnly: false, requiresAuth: false }
    },
    {
      path: '/catalog',
      name: 'catalog',
      component: () => import('@/views/CatalogView.vue'),
      meta: { guestOnly: false, requiresAuth: false }
    },
    {
      path: '/blog',
      name: 'blog',
      component: () => import('@/views/BlogView.vue'),
      meta: { guestOnly: false, requiresAuth: false }
    },
    {
      path: '/book/:slug',
      name: 'book-detail',
      component: () => import('@/views/BookDetailView.vue'),
      meta: { guestOnly: false, requiresAuth: false }
    },
    {
      path: '/tracking/:orderId',
      name: 'order-tracking',
      component: () => import('@/views/OrderTrackingView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/cart',
      name: 'cart',
      component: () => import('@/views/CartView.vue'),
      meta: { guestOnly: false, requiresAuth: false }
    },
    {
      path: '/checkout/success',
      name: 'checkout-success',
      component: () => import('@/views/CheckoutSuccessView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/ProfileView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/my-library',
      name: 'my-library',
      component: () => import('@/views/MyLibraryView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/reader/:orderId/:bookId',
      name: 'ebook-reader',
      component: () => import('@/views/EbookReaderView.vue'),
      meta: { requiresAuth: true, hideHeader: true }
    },
    {
      path: '/orders',
      name: 'orders',
      component: () => import('@/views/OrdersView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/wishlist',
      name: 'wishlist',
      component: () => import('@/views/WishlistView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: () => import('@/views/NotificationsView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/annotations',
      name: 'annotations',
      component: () => import('@/views/MyAnnotationsView.vue'),
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
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/auth/ForgotPasswordView.vue'),
      meta: { guestOnly: true }
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/auth/ResetPasswordView.vue'),
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
      path: '/admin',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, role: 'admin', hideHeader: true },
      children: [
        {
          path: 'dashboard',
          name: 'admin-dashboard',
          component: () => import('@/views/admin/DashboardView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Tổng quan' }
        },
        {
          path: 'users',
          name: 'admin-users',
          component: () => import('@/views/admin/UsersView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Quản lý Users' }
        },
        {
          path: 'coupons',
          name: 'admin-coupons',
          component: () => import('@/views/admin/PromotionsView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Quản lý Khuyến mãi' }
        },
        {
          path: 'system-config',
          name: 'admin-system-config',
          component: () => import('@/views/admin/SystemConfigView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Cấu hình hệ thống' }
        },
        {
          path: 'finance-report',
          name: 'admin-finance-report',
          component: () => import('@/views/admin/FinanceReportView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Báo cáo tài chính' }
        },
        {
          path: 'customers',
          name: 'admin-customers',
          component: () => import('@/views/admin/CustomersView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Danh sách Khách hàng' }
        },
        {
          path: 'customers/:id',
          name: 'admin-customer-detail',
          component: () => import('@/views/admin/CustomerDetailView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Chi tiết Khách hàng' }
        },
        {
          path: 'reconciliation',
          name: 'admin-reconciliation',
          component: () => import('@/views/admin/ReconciliationView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Đối soát & Báo cáo' }
        },
      ]
    },
    {
      path: '/vendor',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, role: 'vendor', hideHeader: true },
      children: [
        {
          path: 'dashboard',
          name: 'vendor-dashboard',
          component: () => import('@/views/vendor/DashboardView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Dashboard' }
        },
        {
          path: 'analytics',
          name: 'vendor-analytics',
          component: () => import('@/views/vendor/AnalyticsView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Phân tích Độc giả' }
        },
        {
          path: 'books',
          name: 'vendor-books',
          component: () => import('@/views/vendor/BooksView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Quản lý Sách' }
        },
        {
          path: 'warehouses',
          name: 'vendor-warehouses',
          component: () => import('@/views/vendor/WarehousesView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Quản lý Kho hàng' }
        },
        {
          path: 'orders',
          name: 'vendor-orders',
          component: () => import('@/views/vendor/OrdersView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Quản lý Đơn hàng' }
        },
        {
          path: 'orders/:id',
          name: 'vendor-order-detail',
          component: () => import('@/views/vendor/OrderDetailView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Chi tiết Đơn hàng' }
        },
        {
          path: 'finance',
          name: 'vendor-finance',
          component: () => import('@/views/vendor/FinanceView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Doanh thu & Rút tiền' }
        },
      ]
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

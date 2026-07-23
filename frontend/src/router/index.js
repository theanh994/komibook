import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { runRouteGuard } from '@/router/guard.js'

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
      path: '/flash-sale',
      name: 'flash-sale',
      component: () => import('@/views/FlashSaleView.vue'),
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
      path: '/author/register',
      name: 'author-register',
      component: () => import('@/views/auth/AuthorRegisterView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/author/verify',
      name: 'author-verify',
      component: () => import('@/views/auth/AccountVerificationView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/help-center',
      name: 'help-center',
      component: () => import('@/views/HelpCenterView.vue'),
      meta: { requiresAuth: false }
    },
    {
      path: '/support',
      name: 'customer-support',
      component: () => import('@/views/CustomerSupportView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/support/tickets/:id',
      name: 'customer-support-ticket-detail',
      component: () => import('@/views/admin/TicketDetailView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/orders/invoice/:id',
      name: 'order-invoice',
      component: () => import('@/views/orders/InvoicePrintView.vue'),
      meta: { requiresAuth: true, hideHeader: true }
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
      redirect: () => {
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
          path: 'books',
          name: 'admin-books',
          component: () => import('@/views/admin/BooksView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Quản lý Toàn bộ Sách' }
        },
        {
          path: 'books/categories',
          name: 'admin-books-categories',
          component: () => import('@/views/admin/CategoriesView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Quản lý Thể loại Sách' }
        },
        {
          path: 'categories',
          redirect: '/admin/books/categories'
        },
        {
          path: 'coupons',
          name: 'admin-coupons',
          component: () => import('@/views/admin/PromotionsView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Quản lý Khuyến mãi' }
        },
        {
          path: 'flash-sales/:id',
          name: 'admin-flash-sale-detail',
          component: () => import('@/views/admin/FlashSaleDetailView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Chi tiết Flash Sale' }
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
          path: 'users/:id',
          name: 'admin-user-detail',
          component: () => import('@/views/admin/UserDetailView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Chi tiết Users' }
        },
        {
          path: 'reconciliation',
          name: 'admin-reconciliation',
          component: () => import('@/views/admin/ReconciliationView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Đối soát & Báo cáo' }
        },
        {
          path: 'notifications',
          name: 'admin-notifications',
          component: () => import('@/views/admin/NotificationCampaignsView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Quản lý thông báo' }
        },
        {
          path: 'notifications/create',
          name: 'admin-notifications-create',
          component: () => import('@/views/admin/NotificationCreateView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Tạo thông báo mới' }
        },
        {
          path: 'notifications/:id/analytics',
          name: 'admin-notifications-analytics',
          component: () => import('@/views/admin/NotificationAnalyticsView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Hiệu quả thông báo' }
        },
        {
          path: 'approvals',
          name: 'admin-approvals',
          component: () => import('@/views/admin/VendorApprovalsView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Kiểm duyệt đối tác' }
        },
        {
          path: 'support/tickets',
          name: 'admin-support-tickets',
          component: () => import('@/views/admin/HelpDeskView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Hỗ trợ khách hàng' }
        },
        {
          path: 'support/tickets/:id',
          name: 'admin-support-ticket-detail',
          component: () => import('@/views/admin/TicketDetailView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Chi tiết Yêu cầu Hỗ trợ' }
        },
        {
          path: 'membership-tiers',
          name: 'admin-membership-tiers',
          component: () => import('@/views/admin/MembershipTiersView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Quản lý Hạng thành viên' }
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
          path: 'series',
          name: 'vendor-series',
          component: () => import('@/views/vendor/SeriesView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Quản lý Bộ Sách' }
        },
        {
          path: 'books/create',
          name: 'vendor-book-create',
          component: () => import('@/views/vendor/BookFormView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Thêm Sách Mới' }
        },
        {
          path: 'books/:id/edit',
          name: 'vendor-book-edit',
          component: () => import('@/views/vendor/BookFormView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Chỉnh Sửa Sách' }
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
        {
          path: 'flash-sales',
          name: 'vendor-flash-sales',
          component: () => import('@/views/vendor/FlashSalesView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Đăng ký Flash Sale' }
        },
        {
          path: 'live-editor/:bookId',
          name: 'author-live-editor',
          component: () => import('@/views/vendor/LiveEditorView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Trình soạn thảo' }
        },
        {
          path: 'preview/:bookId',
          name: 'author-device-preview',
          component: () => import('@/views/vendor/MultiDevicePreviewView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Xem trước đa thiết bị' }
        },
        {
          path: 'books/:bookId/drm',
          name: 'vendor-book-drm',
          component: () => import('@/views/vendor/DrmSettingsView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Quản lý DRM & Bản quyền' }
        },
        {
          path: 'books/:bookId/chapters',
          name: 'vendor-book-chapters',
          component: () => import('@/views/vendor/BookChaptersView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Thiết lập Giá và Đọc thử' }
        },
        {
          path: 'inventory/audits',
          name: 'vendor-inventory-audits',
          component: () => import('@/views/vendor/InventoryAuditView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Kiểm kê kho' }
        },
        {
          path: 'inventory/transfers',
          name: 'vendor-inventory-transfers',
          component: () => import('@/views/vendor/StockTransferView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Điều chuyển kho' }
        },
        {
          path: 'inventory/transfers/print/:id',
          name: 'vendor-inventory-transfers-print',
          component: () => import('@/views/vendor/StockTransferPrintView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'In phiếu chuyển kho' }
        },
        {
          path: 'author-dashboard',
          name: 'author-dashboard',
          component: () => import('@/views/vendor/AuthorDashboardView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Bảng điều khiển Tác giả' }
        },
      ]
    }
  ],
})

// Route guard kiểm tra đăng nhập & phân quyền
router.beforeEach(async (to) => {
  const authStore = useAuthStore()
  return await runRouteGuard(to, authStore)
})

export default router

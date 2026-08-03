import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getDashboardRedirect, runRouteGuard } from '@/router/guard.js'
import { ROUTER_BASE } from '@/router/base.js'

const router = createRouter({
  // Asset files may be deployed below a versioned CDN path, but public routes
  // always live at the domain root (for example /login, not /assets/.../login).
  history: createWebHistory(ROUTER_BASE),
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
      path: '/blog/contribute',
      name: 'article-contribute',
      component: () => import('@/views/ReviewSubmissionView.vue'),
      meta: { requiresAuth: true, title: 'Gửi review sách' }
    },
    {
      path: '/blog/:slug',
      name: 'article-detail',
      component: () => import('@/views/ArticleView.vue'),
      meta: { guestOnly: false, requiresAuth: false }
    },
    {
      path: '/book/:slug',
      name: 'book-detail',
      component: () => import('@/views/BookDetailView.vue'),
      meta: { guestOnly: false, requiresAuth: false }
    },
    {
      path: '/shops/:slug',
      name: 'vendor-storefront',
      component: () => import('@/views/VendorStorefrontView.vue'),
      meta: { guestOnly: false, requiresAuth: false, title: 'Gian hàng' }
    },
    {
      path: '/organizations/:slug',
      name: 'organization-public',
      component: () => import('@/views/OrganizationView.vue'),
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
      path: '/returns',
      name: 'returns',
      component: () => import('@/views/ReturnsView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/wallet',
      name: 'wallet',
      component: () => import('@/views/WalletView.vue'),
      meta: { requiresAuth: true, title: 'Ví KomiBook & Rút tiền' }
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
      path: '/used-books/manage',
      name: 'used-book-seller',
      component: () => import('@/views/used-books/UsedBookManagerView.vue'),
      meta: { requiresAuth: true, title: 'Sách cũ của tôi' }
    },
    {
      path: '/vendor/register',
      name: 'vendor-register',
      component: () => import('@/views/auth/VendorRegisterView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/organization-portal',
      name: 'organization-portal',
      component: () => import('@/views/OrganizationPortalView.vue'),
      meta: { requiresAuth: true, title: 'Tổ chức & phân phối' }
    },
    {
      path: '/help-center',
      name: 'help-center',
      component: () => import('@/views/HelpCenterView.vue'),
      meta: { requiresAuth: false }
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('@/views/PublicInfoView.vue'),
      meta: { requiresAuth: false, pageKey: 'about', title: 'Về KomiBook' }
    },
    {
      path: '/contact',
      name: 'contact',
      redirect: '/help-center'
    },
    {
      path: '/faq',
      name: 'faq',
      component: () => import('@/views/PublicInfoView.vue'),
      meta: { requiresAuth: false, pageKey: 'faq', title: 'Câu hỏi thường gặp' }
    },
    {
      path: '/terms',
      name: 'terms',
      component: () => import('@/views/PolicyPageView.vue'),
      meta: { requiresAuth: false, pageKey: 'terms', title: 'Điều khoản sử dụng' }
    },
    {
      path: '/privacy',
      name: 'privacy',
      component: () => import('@/views/PolicyPageView.vue'),
      meta: { requiresAuth: false, pageKey: 'privacy', title: 'Chính sách bảo mật' }
    },
    {
      path: '/policies/ebooks',
      name: 'ebook-policy',
      component: () => import('@/views/PolicyPageView.vue'),
      meta: { requiresAuth: false, pageKey: 'ebooks', title: 'Chính sách ebook' }
    },
    {
      path: '/policies/used-books',
      name: 'used-book-policy',
      component: () => import('@/views/PolicyPageView.vue'),
      meta: { requiresAuth: false, pageKey: 'usedBooks', title: 'Sách cũ, trả hàng và hoàn tiền' }
    },
    {
      path: '/policies/copyright',
      name: 'copyright-policy',
      component: () => import('@/views/PolicyPageView.vue'),
      meta: { requiresAuth: false, pageKey: 'copyright', title: 'Bản quyền và hàng giả' }
    },
    {
      path: '/support',
      name: 'customer-support',
      redirect: '/help-center'
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

        return getDashboardRedirect({
          isAdmin: authStore.isAdmin,
          isActiveVendor: authStore.user?.capabilities?.active_vendor === true,
          isWarehouseManager: authStore.isWarehouseManager,
        })
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
          path: 'returns',
          name: 'admin-returns',
          component: () => import('@/views/shared/ReturnManagementView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Trả hàng & hoàn tiền' }
        },
        {
          path: 'reviews/moderation',
          name: 'admin-review-moderation',
          redirect: '/admin/moderation',
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Kiểm duyệt nội dung' }
        },
        {
          path: 'moderation',
          name: 'admin-content-moderation',
          component: () => import('@/views/admin/ContentModerationView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Kiểm duyệt nội dung' }
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
          path: 'fee-schedules',
          name: 'admin-fee-schedules',
          redirect: { name: 'admin-system-config', query: { section: 'fees' } },
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Commission & phí dịch vụ' }
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
          path: 'users/:id/print',
          name: 'admin-user-print',
          component: () => import('@/views/admin/UserInformationPrintView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Phiếu thông tin người dùng' }
        },
        {
          path: 'reconciliation',
          name: 'admin-reconciliation',
          component: () => import('@/views/admin/ReconciliationView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Quản lý yêu cầu rút tiền' }
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
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Quản lý đăng ký Nhà bán' }
        },
        {
          path: 'articles',
          name: 'admin-articles',
          component: () => import('@/views/admin/ArticlesView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Newsroom' }
        },
        {
          path: 'articles/create',
          name: 'admin-article-create',
          component: () => import('@/views/shared/ArticleEditorView.vue'),
          props: { role: 'admin' },
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Viết bài mới' }
        },
        {
          path: 'articles/:id/edit',
          name: 'admin-article-edit',
          component: () => import('@/views/shared/ArticleEditorView.vue'),
          props: { role: 'admin' },
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Biên tập bài viết' }
        },
        {
          path: 'article-comments',
          name: 'admin-article-comments',
          redirect: '/admin/moderation',
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Kiểm duyệt nội dung' }
        },
        {
          path: 'article-submissions',
          name: 'admin-article-submissions',
          component: () => import('@/views/admin/ArticleSubmissionsView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Review tiềm năng' }
        },
        {
          path: 'support',
          name: 'admin-support',
          component: () => import('@/views/vendor/CustomerSupportView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Hỗ trợ khách hàng (Live Chat)' }
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
        {
          path: 'organization-reviews',
          name: 'admin-organization-reviews',
          component: () => import('@/views/admin/OrganizationReviewsView.vue'),
          meta: { requiresAuth: true, role: 'admin', hideHeader: true, title: 'Tổ chức & quan hệ cung ứng' }
        },
      ]
    },
    {
      path: '/vendor',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, role: 'vendor', capability: 'active_vendor', hideHeader: true },
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
          path: 'books/:bookId/publishing',
          name: 'vendor-book-publishing',
          component: () => import('@/views/vendor/PublishingWorkflowView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Quy trình xuất bản' }
        },
        {
          path: 'warehouses',
          name: 'vendor-warehouses',
          component: () => import('@/views/vendor/WarehousesView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Quản lý Kho hàng' }
        },
        {
          path: 'warehouse-managers',
          name: 'vendor-warehouse-managers',
          component: () => import('@/views/vendor/WarehouseManagersView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Nhân sự kho' }
        },
        {
          path: 'warehouse-documents',
          name: 'vendor-warehouse-documents',
          component: () => import('@/views/warehouse-manager/DocumentsView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Phiếu nhập / xuất kho' }
        },
        {
          path: 'organizations',
          name: 'vendor-organizations',
          component: () => import('@/views/vendor/OrganizationPartnersView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Nhà xuất bản & Nhà cung cấp' }
        },
        {
          path: 'orders',
          name: 'vendor-orders',
          component: () => import('@/views/vendor/OrdersView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Quản lý Đơn hàng' }
        },
        {
          path: 'returns',
          name: 'vendor-returns',
          component: () => import('@/views/shared/ReturnManagementView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Trả hàng & hoàn tiền' }
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
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Báo cáo doanh thu' }
        },
        {
          path: 'flash-sales',
          name: 'vendor-flash-sales',
          component: () => import('@/views/vendor/FlashSalesView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Đăng ký Flash Sale' }
        },
        {
          path: 'articles',
          name: 'vendor-articles',
          component: () => import('@/views/vendor/ArticlesView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Bài viết & Tin tức' }
        },
        {
          path: 'articles/create',
          name: 'vendor-article-create',
          component: () => import('@/views/shared/ArticleEditorView.vue'),
          props: { role: 'vendor' },
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Viết bài mới' }
        },
        {
          path: 'articles/:id/edit',
          name: 'vendor-article-edit',
          component: () => import('@/views/shared/ArticleEditorView.vue'),
          props: { role: 'vendor' },
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Biên tập bài viết' }
        },
        {
          path: 'live-editor/:bookId',
          name: 'vendor-live-editor',
          component: () => import('@/views/vendor/LiveEditorView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Trình soạn thảo' }
        },
        {
          path: 'preview/:bookId',
          name: 'vendor-device-preview',
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
          path: 'support',
          name: 'vendor-support',
          component: () => import('@/views/vendor/CustomerSupportView.vue'),
          meta: { requiresAuth: true, role: 'vendor', hideHeader: true, title: 'Hỗ trợ khách hàng' }
        },
      ]
    },
    {
      path: '/warehouse-manager',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, capability: 'warehouse_manager', hideHeader: true },
      children: [
        {
          path: 'dashboard',
          name: 'warehouse-manager-dashboard',
          component: () => import('@/views/warehouse-manager/DashboardView.vue'),
          meta: { requiresAuth: true, capability: 'warehouse_manager', hideHeader: true, title: 'Tổng quan kho được giao' }
        },
        {
          path: 'inventory',
          name: 'warehouse-manager-inventory',
          component: () => import('@/views/warehouse-manager/InventoryView.vue'),
          meta: { requiresAuth: true, capability: 'warehouse_manager', hideHeader: true, title: 'Tồn kho' }
        },
        {
          path: 'documents',
          name: 'warehouse-manager-documents',
          component: () => import('@/views/warehouse-manager/DocumentsView.vue'),
          meta: { requiresAuth: true, capability: 'warehouse_manager', hideHeader: true, title: 'Phiếu kho' }
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

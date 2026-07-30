<?php

use App\Http\Controllers\Api\AccountSessionController;
use App\Http\Controllers\Api\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Api\Admin\BookPublishingController as AdminBookPublishingController;
use App\Http\Controllers\Api\Admin\CommerceFeeScheduleController;
use App\Http\Controllers\Api\Admin\FinanceReportController;
use App\Http\Controllers\Api\Admin\MembershipTierController;
use App\Http\Controllers\Api\Admin\NotificationCampaignController;
use App\Http\Controllers\Api\Admin\OrganizationReviewController;
use App\Http\Controllers\Api\Admin\ReconciliationController;
use App\Http\Controllers\Api\Admin\SystemConfigController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VendorApprovalController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookAnnotationController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookPublishingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChapterController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DrmController;
use App\Http\Controllers\Api\EmailRegistrationOtpController;
use App\Http\Controllers\Api\HelpCenterController;
use App\Http\Controllers\Api\InventoryAuditController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PhoneAuthController;
use App\Http\Controllers\Api\PolicyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReadingProgressController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\UsedBookDisputeController;
use App\Http\Controllers\Api\UsedBookSellerController;
use App\Http\Controllers\Api\UserNotificationController;
use App\Http\Controllers\Api\Vendor\AnalyticsController;
use App\Http\Controllers\Api\Vendor\DashboardController;
use App\Http\Controllers\Api\Vendor\FinanceController;
use App\Http\Controllers\Api\Vendor\FlashSaleController;
use App\Http\Controllers\Api\Vendor\SeriesController;
use App\Http\Controllers\Api\Vendor\WarehouseController;
use App\Http\Controllers\Api\Vendor\WarehouseManagerController as VendorWarehouseManagerController;
use App\Http\Controllers\Api\VendorOnboardingController;
use App\Http\Controllers\Api\WarehouseManagerPortalController;
use App\Http\Controllers\Api\WarehouseDocumentController;
use App\Http\Controllers\Api\VnpayController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════════
// Public routes — Không cần xác thực
// ═══════════════════════════════════════════════════════════════════════════════

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login')->middleware('throttle:login');
    Route::post('/google-login', [AuthController::class, 'googleLogin'])->name('google-login');
    Route::post('/facebook-login', [AuthController::class, 'facebookLogin'])->name('facebook-login');
    Route::post('/email/send-otp', [EmailRegistrationOtpController::class, 'sendOtp'])->name('email.send-otp');
    Route::post('/email/verify-otp', [EmailRegistrationOtpController::class, 'verifyOtp'])->name('email.verify-otp');
    Route::post('/phone/send-otp', [PhoneAuthController::class, 'sendOtp'])->name('phone.send-otp');
    Route::post('/phone/verify-otp', [PhoneAuthController::class, 'verifyOtp'])->name('phone.verify-otp');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password')->middleware('throttle:forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
});

// Các endpoint Catalog công cộng
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/series', [BookController::class, 'allSeries']);
Route::get('/books', [BookController::class, 'index']);
Route::get('/policies/returns', [PolicyController::class, 'returnPolicies']);
Route::get('/books/top-selling', [BookController::class, 'topSelling']);
Route::get('/books/recommendations', [BookController::class, 'recommendations']);
Route::get('/books/{slug}', [BookController::class, 'show']);
Route::get('/books/{id}/series', [BookController::class, 'seriesBooks']);
Route::get('/books/{id}/contributors', [BookController::class, 'contributorBooks']);
Route::get('/books/{id}/related', [BookController::class, 'relatedBooks']);

// Help Center công khai
Route::get('/help-center/articles', [HelpCenterController::class, 'index']);
Route::get('/help-center/articles/{id}', [HelpCenterController::class, 'show']);
Route::post('/help-center/articles/{id}/helpful', [HelpCenterController::class, 'helpful']);
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);
Route::get('/organizations', [OrganizationController::class, 'index']);
Route::get('/organizations/{slug}', [OrganizationController::class, 'show']);

// ═══════════════════════════════════════════════════════════════════════════════
// Protected routes — Yêu cầu Sanctum token hợp lệ
// ═══════════════════════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->prefix('auth')->name('auth.')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/confirm-password', [AuthController::class, 'confirmRecentAuthentication'])->middleware('verified-email')->name('confirm-password');
});

Route::middleware('auth:sanctum')->prefix('profile')->name('profile.')->group(function () {
    Route::put('/info', [ProfileController::class, 'updateInfo'])->name('updateInfo');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->middleware(['verified-email', 'recent-auth'])->name('updatePassword');
    Route::get('/sessions', [AccountSessionController::class, 'index'])->name('sessions.index');
    Route::delete('/sessions/{sessionId}', [AccountSessionController::class, 'destroy'])->middleware(['verified-email', 'recent-auth'])->name('sessions.destroy');
    Route::post('/avatar', [ProfileController::class, 'uploadAvatar'])->name('uploadAvatar');
    Route::get('/addresses', [ProfileController::class, 'getAddresses'])->name('getAddresses');
    Route::post('/addresses', [ProfileController::class, 'addAddress'])->name('addAddress');
    Route::put('/addresses/{id}', [ProfileController::class, 'updateAddress'])->name('updateAddress');
    Route::delete('/addresses/{id}', [ProfileController::class, 'deleteAddress'])->name('deleteAddress');
    Route::patch('/addresses/{id}/default', [ProfileController::class, 'setDefaultAddress'])->name('setDefaultAddress');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');
    Route::post('/books/{book}/reviews', [ReviewController::class, 'upsert']);
    Route::post('/reviews/{review}/reports', [ReviewController::class, 'report']);
    Route::get('/books/{id}/check-ownership', [BookController::class, 'checkOwnership']);
    Route::post('/coupons/apply', [CouponController::class, 'apply']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::get('/my-orders/{order}', [OrderController::class, 'myOrderDetail']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::get('/returns', [ReturnRequestController::class, 'customerIndex']);
    Route::post('/orders/{order}/returns', [ReturnRequestController::class, 'store']);
    Route::get('/returns/{returnRequest}', [ReturnRequestController::class, 'show']);
    Route::get('/my-library', [OrderController::class, 'myLibrary']);
    Route::get('/orders/{order}/ebooks/{book}/generate-link', [OrderController::class, 'generateEbookLink']);

    // VNPAY Create payment
    Route::post('/vnpay/create', [VnpayController::class, 'createPayment']);

    // Book Annotations
    Route::get('/annotations', [BookAnnotationController::class, 'index']);
    Route::post('/annotations', [BookAnnotationController::class, 'store']);
    Route::put('/annotations/{id}', [BookAnnotationController::class, 'update']);
    Route::delete('/annotations/{id}', [BookAnnotationController::class, 'destroy']);
    Route::get('/books/{id}/recent-annotations', [BookAnnotationController::class, 'recent']);
    Route::get('/books/{book}/reading-progress', [ReadingProgressController::class, 'show']);
    Route::put('/books/{book}/reading-progress', [ReadingProgressController::class, 'update']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{bookId}/toggle', [WishlistController::class, 'toggle']);
    Route::get('/wishlist/{bookId}/check', [WishlistController::class, 'check']);

    // User Notifications
    Route::get('/notifications', [UserNotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [UserNotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [UserNotificationController::class, 'markAllAsRead']);

    Route::get('/used-book-seller/listings', [UsedBookSellerController::class, 'index']);
    Route::post('/used-book-seller/listings', [UsedBookSellerController::class, 'store']);
    Route::patch('/used-book-seller/listings/{listing}/inventory', [UsedBookSellerController::class, 'updateInventory']);
    Route::get('/used-book-seller/fulfillment-address', [UsedBookSellerController::class, 'showAddress']);
    Route::put('/used-book-seller/fulfillment-address', [UsedBookSellerController::class, 'upsertAddress']);
    Route::post('/used-books/disputes', [UsedBookDisputeController::class, 'store']);
    Route::post('/vendor-onboarding/register', [VendorOnboardingController::class, 'register']);
    Route::patch('/vendor-onboarding/draft', [VendorOnboardingController::class, 'saveDraft']);
    Route::post('/vendor-onboarding/submit', [VendorOnboardingController::class, 'submit']);
    Route::get('/vendor-onboarding/status', [VendorOnboardingController::class, 'status']);
    Route::get('/vendors/{vendor}/documents/{type}', [VendorOnboardingController::class, 'downloadDocument']);
    Route::get('/warehouse-manager/assignments', [WarehouseManagerPortalController::class, 'assignments']);
    Route::post('/warehouse-manager/assignments/{assignment}/accept', [WarehouseManagerPortalController::class, 'accept']);
    Route::get('/warehouse-manager/assignments/{assignment}/dashboard', [WarehouseManagerPortalController::class, 'dashboard']);
    Route::get('/warehouse-manager/documents', [WarehouseDocumentController::class, 'index']);
    Route::get('/warehouse-manager/document-scope', [WarehouseDocumentController::class, 'scope']);
    Route::post('/warehouse-manager/documents', [WarehouseDocumentController::class, 'store']);
    Route::get('/warehouse-manager/documents/{document}', [WarehouseDocumentController::class, 'show']);
    Route::patch('/warehouse-manager/documents/{document}/transition', [WarehouseDocumentController::class, 'transition']);

    // Tickets yêu cầu hỗ trợ (Khách hàng)
    Route::get('/support/tickets', [SupportTicketController::class, 'index']);
    Route::post('/support/tickets', [SupportTicketController::class, 'store']);
    Route::get('/support/tickets/{id}', [SupportTicketController::class, 'show']);
    Route::post('/support/tickets/{id}/message', [SupportTicketController::class, 'reply']);
    Route::get('/support/tickets/{ticket}/messages/{message}/attachment', [SupportTicketController::class, 'downloadAttachment']);
});

// ═══════════════════════════════════════════════════════════════════════════════
// Vendor routes — Quản lý gian hàng (yêu cầu role: vendor)
// ═══════════════════════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'role:vendor', 'active-vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    // Thống kê dashboard
    Route::get('dashboard-stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    Route::post('books/bulk-series', [App\Http\Controllers\Api\Vendor\BookController::class, 'bulkSeries'])->name('books.bulkSeries');
    Route::post('books/bulk-discount', [App\Http\Controllers\Api\Vendor\BookController::class, 'bulkDiscount'])->name('books.bulkDiscount');

    // Quản lý Bộ Sách (Vendor Series)
    Route::get('series', [SeriesController::class, 'index'])->name('series.index');
    Route::put('series/{id}', [SeriesController::class, 'update'])->name('series.update');
    Route::post('series/{id}/apply-discount', [SeriesController::class, 'applyDiscount'])->name('series.applyDiscount');
    Route::delete('series/{id}', [SeriesController::class, 'destroy'])->name('series.destroy');

    Route::apiResource('books', App\Http\Controllers\Api\Vendor\BookController::class);
    Route::get('books/{book}/publishing', [BookPublishingController::class, 'show'])->name('books.publishing.show');
    Route::post('books/{book}/submit', [BookPublishingController::class, 'submit'])->name('books.publish.submit');
    Route::patch('books/{book}/return-to-draft', [BookPublishingController::class, 'returnToDraft'])->name('books.publish.draft');
    Route::post('books/{book}/publish', [BookPublishingController::class, 'publish'])->name('books.publish');

    // Quản lý đơn hàng
    Route::get('orders', [App\Http\Controllers\Api\Vendor\OrderController::class, 'index'])->name('orders.index');
    Route::patch('orders/bulk-status', [App\Http\Controllers\Api\Vendor\OrderController::class, 'bulkUpdateStatus'])->name('orders.bulkUpdateStatus');
    Route::get('orders/{order}', [App\Http\Controllers\Api\Vendor\OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [App\Http\Controllers\Api\Vendor\OrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::get('returns', [ReturnRequestController::class, 'vendorIndex'])->name('returns.index');
    Route::patch('returns/{returnRequest}/transition', [ReturnRequestController::class, 'transition'])->middleware(['verified-email', 'recent-auth'])->name('returns.transition');
    Route::post('returns/{returnRequest}/refund', [ReturnRequestController::class, 'processRefund'])->middleware(['verified-email', 'recent-auth'])->name('returns.refund');
    Route::post('returns/{returnRequest}/refund/reconcile', [ReturnRequestController::class, 'reconcileRefund'])->middleware(['verified-email', 'recent-auth'])->name('returns.refund.reconcile');

    // Quản lý kho hàng (Warehouse)
    Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::get('warehouses/stats', [WarehouseController::class, 'stats'])->name('warehouses.stats');
    Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::post('warehouses/adjust', [WarehouseController::class, 'adjustStock'])->name('warehouses.adjust');
    Route::get('warehouse-documents', [WarehouseDocumentController::class, 'index'])->name('warehouse-documents.index');
    Route::get('warehouse-document-scope', [WarehouseDocumentController::class, 'scope'])->name('warehouse-documents.scope');
    Route::post('warehouse-documents', [WarehouseDocumentController::class, 'store'])->name('warehouse-documents.store');
    Route::get('warehouse-documents/{document}', [WarehouseDocumentController::class, 'show'])->name('warehouse-documents.show');
    Route::patch('warehouse-documents/{document}/transition', [WarehouseDocumentController::class, 'transition'])->name('warehouse-documents.transition');
    Route::get('warehouse-managers', [VendorWarehouseManagerController::class, 'index'])->name('warehouse-managers.index');
    Route::post('warehouse-managers/invite', [VendorWarehouseManagerController::class, 'invite'])->name('warehouse-managers.invite');
    Route::patch('warehouse-managers/{assignment}/transition', [VendorWarehouseManagerController::class, 'transition'])->name('warehouse-managers.transition');
    Route::get('organizations', [App\Http\Controllers\Api\Vendor\OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('organizations', [App\Http\Controllers\Api\Vendor\OrganizationController::class, 'storeOrganization'])->name('organizations.store');
    Route::post('organization-relationships', [App\Http\Controllers\Api\Vendor\OrganizationController::class, 'storeRelationship'])->name('organization-relationships.store');
    Route::post('organization-relationships/{relationship}/submit', [App\Http\Controllers\Api\Vendor\OrganizationController::class, 'submit'])->name('organization-relationships.submit');
    Route::put('books/{book}/commercial-parties', [App\Http\Controllers\Api\Vendor\OrganizationController::class, 'assignBookParties'])->name('books.commercial-parties.update');

    // Quản lý tài chính / doanh thu (Finance)
    Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('finance/payout', [FinanceController::class, 'requestPayout'])->middleware(['verified-email', 'recent-auth'])->name('finance.payout');

    // Phân tích độc giả / báo cáo (Analytics)
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Đăng ký Flash Sale
    Route::get('flash-sales', [FlashSaleController::class, 'index'])->name('flash-sales.index');
    Route::get('flash-sales/{flash_sale}/registered-books', [FlashSaleController::class, 'registeredBooks'])->name('flash-sales.registered-books');
    Route::post('flash-sales/{flash_sale}/register', [FlashSaleController::class, 'register'])->name('flash-sales.register');

    // Quản lý chương sách tự viết (Chapters)
    Route::get('books/{book}/chapters', [ChapterController::class, 'index'])->name('books.chapters.index');
    Route::post('books/{book}/chapters', [ChapterController::class, 'store'])->name('books.chapters.store');
    Route::put('books/{book}/chapters/{chapter}', [ChapterController::class, 'update'])->name('books.chapters.update');
    Route::delete('books/{book}/chapters/{chapter}', [ChapterController::class, 'destroy'])->name('books.chapters.destroy');
    Route::patch('books/{book}/chapters/{chapter}/autosave', [ChapterController::class, 'autosave'])->name('books.chapters.autosave');
    Route::post('books/{book}/chapters/{chapter}/restore/{revision}', [ChapterController::class, 'restore'])->name('books.chapters.restore');
    Route::patch('books/{book}/chapters-order', [ChapterController::class, 'reorder'])->name('books.chapters.reorder');
    Route::post('books/{book}/chapters-import', [ChapterController::class, 'import'])->name('books.chapters.import');

    // Cấu hình bản quyền & DRM
    Route::get('books/{book}/drm-settings', [DrmController::class, 'show'])->name('books.drm.show');
    Route::put('books/{book}/drm-settings', [DrmController::class, 'update'])->name('books.drm.update');

    // Kiểm kê kho hàng định kỳ (Inventory Audit)
    Route::get('inventory/audits', [InventoryAuditController::class, 'index'])->name('inventory.audits.index');
    Route::get('inventory/audits/{id}', [InventoryAuditController::class, 'show'])->name('inventory.audits.show');
    Route::post('inventory/audits', [InventoryAuditController::class, 'store'])->name('inventory.audits.store');
    Route::post('inventory/audits/{id}/complete', [InventoryAuditController::class, 'complete'])->name('inventory.audits.complete');

    // Điều chuyển kho (Stock Transfer)
    Route::get('inventory/transfers', [StockTransferController::class, 'index'])->name('inventory.transfers.index');
    Route::get('inventory/transfers/{id}', [StockTransferController::class, 'show'])->name('inventory.transfers.show');
    Route::post('inventory/transfers', [StockTransferController::class, 'store'])->name('inventory.transfers.store');
    Route::post('inventory/transfers/{id}/ship', [StockTransferController::class, 'ship'])->name('inventory.transfers.ship');
    Route::post('inventory/transfers/{id}/receive', [StockTransferController::class, 'receive'])->name('inventory.transfers.receive');

    // Trạng thái vận chuyển mô phỏng (Shipping)
    Route::patch('orders/{order}/shipping', [OrderController::class, 'updateShippingStatus'])->name('orders.shipping.update');
});

// ═══════════════════════════════════════════════════════════════════════════════
// Admin routes — Quản trị hệ thống (yêu cầu role: admin)
// ═══════════════════════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('fee-schedules', [CommerceFeeScheduleController::class, 'index'])->name('fee-schedules.index');
    Route::post('fee-schedules', [CommerceFeeScheduleController::class, 'store'])->name('fee-schedules.store');
    Route::post('fee-schedules/preview', [CommerceFeeScheduleController::class, 'preview'])->name('fee-schedules.preview');

    Route::get('articles', [AdminArticleController::class, 'index'])->name('cms.articles.index');
    Route::post('articles', [AdminArticleController::class, 'store'])->name('cms.articles.store');
    Route::patch('articles/{article}', [AdminArticleController::class, 'update'])->name('cms.articles.update');
    Route::patch('articles/{article}/transition', [AdminArticleController::class, 'transition'])->name('cms.articles.transition');

    // Thống kê tổng quan
    Route::get('stats', [App\Http\Controllers\Api\Admin\DashboardController::class, 'stats'])->name('stats');

    // Quản lý Users
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{id}', [UserController::class, 'show'])->name('users.show');
    Route::patch('users/{id}/role', [UserController::class, 'updateRole'])->name('users.updateRole');
    Route::get('reviews/moderation', [ReviewController::class, 'moderationQueue'])->name('reviews.moderation.index');
    Route::patch('reviews/{review}/moderate', [ReviewController::class, 'moderate'])->middleware(['verified-email', 'recent-auth'])->name('reviews.moderation.update');

    // Quản lý Toàn bộ Sách (Admin)
    Route::get('books', [App\Http\Controllers\Api\Admin\BookController::class, 'index'])->name('books.index');
    Route::get('books/{book}', [App\Http\Controllers\Api\Admin\BookController::class, 'show'])->name('books.show');
    Route::patch('books/{book}/status', [App\Http\Controllers\Api\Admin\BookController::class, 'updateStatus'])->name('books.updateStatus');
    Route::delete('books/{book}', [App\Http\Controllers\Api\Admin\BookController::class, 'destroy'])->name('books.destroy');

    // Quản lý Thể loại Sách
    Route::apiResource('categories', App\Http\Controllers\Api\Admin\CategoryController::class);

    // Quản lý Coupons và Flash Sales
    Route::apiResource('coupons', App\Http\Controllers\Api\Admin\CouponController::class);
    Route::apiResource('flash-sales', App\Http\Controllers\Api\Admin\FlashSaleController::class);
    Route::patch('flash-sales/{flashSale}/transition', [App\Http\Controllers\Api\Admin\FlashSaleController::class, 'transition']);
    Route::post('flash-sales/{flash_sale}/items', [App\Http\Controllers\Api\Admin\FlashSaleController::class, 'addItem']);
    Route::delete('flash-sales/{flash_sale}/items/{item}', [App\Http\Controllers\Api\Admin\FlashSaleController::class, 'removeItem']);
    Route::post('flash-sales/{flash_sale}/items/bulk-delete', [App\Http\Controllers\Api\Admin\FlashSaleController::class, 'bulkRemoveItems']);
    Route::put('flash-sales/items/{item_id}/approve', [App\Http\Controllers\Api\Admin\FlashSaleController::class, 'approveItem']);
    Route::put('flash-sales/items/{item_id}/reject', [App\Http\Controllers\Api\Admin\FlashSaleController::class, 'rejectItem']);

    // Admin Notification Campaigns
    Route::apiResource('notifications/campaigns', NotificationCampaignController::class);
    Route::post('notifications/campaigns/{id}/send', [NotificationCampaignController::class, 'send'])->middleware(['verified-email', 'recent-auth']);
    Route::post('notifications/campaigns/{id}/retry', [NotificationCampaignController::class, 'retry'])->middleware(['verified-email', 'recent-auth']);

    // Báo cáo tài chính
    Route::get('finance-report', [FinanceReportController::class, 'index'])->name('finance-report.index');

    // Đối soát doanh thu
    Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::patch('reconciliation/{id}/approve', [ReconciliationController::class, 'approve'])->middleware(['verified-email', 'recent-auth'])->name('reconciliation.approve');
    Route::patch('reconciliation/{id}/reject', [ReconciliationController::class, 'reject'])->middleware(['verified-email', 'recent-auth'])->name('reconciliation.reject');
    Route::patch('reconciliation/payouts/{payout}/transition', [ReconciliationController::class, 'transition'])->middleware(['verified-email', 'recent-auth'])->name('reconciliation.payouts.transition');
    Route::get('returns', [ReturnRequestController::class, 'adminIndex'])->name('returns.index');
    Route::patch('returns/{returnRequest}/transition', [ReturnRequestController::class, 'transition'])->middleware(['verified-email', 'recent-auth'])->name('returns.transition');
    Route::post('returns/{returnRequest}/refund', [ReturnRequestController::class, 'processRefund'])->middleware(['verified-email', 'recent-auth'])->name('returns.refund');
    Route::post('returns/{returnRequest}/refund/reconcile', [ReturnRequestController::class, 'reconcileRefund'])->middleware(['verified-email', 'recent-auth'])->name('returns.refund.reconcile');

    // Cấu hình hệ thống
    Route::get('config', [SystemConfigController::class, 'show'])->name('config.show');
    Route::put('config', [SystemConfigController::class, 'update'])->name('config.update');

    // Phê duyệt Nhà bán và quan hệ thương mại
    Route::get('approvals/vendors', [VendorApprovalController::class, 'index'])->name('approvals.vendors.index');
    Route::patch('approvals/vendors/{id}/approve', [VendorApprovalController::class, 'approveVendor'])->name('approvals.vendors.approve');
    Route::patch('approvals/vendors/{vendor}/transition', [VendorApprovalController::class, 'transitionVendor'])->name('approvals.vendors.transition');
    Route::patch('used-books/disputes/{dispute}/resolve', [UsedBookDisputeController::class, 'resolve'])->name('used-books.disputes.resolve');
    Route::patch('approvals/partners/{type}/{id}/reject', [VendorApprovalController::class, 'reject'])->name('approvals.partners.reject');
    Route::get('organization-reviews', [OrganizationReviewController::class, 'index'])->name('organization-reviews.index');
    Route::patch('organizations/{organization}/transition', [OrganizationReviewController::class, 'transitionOrganization'])->name('organizations.transition');
    Route::patch('organization-relationships/{relationship}/transition', [OrganizationReviewController::class, 'transitionRelationship'])->name('organization-relationships.transition');
    Route::patch('books/{book}/publishing-transition', [AdminBookPublishingController::class, 'transition'])->name('books.publishing.transition');

    // Quản lý Tickets hội thoại (Admin)
    Route::get('support/tickets', [SupportTicketController::class, 'adminIndex'])->name('support.tickets.adminIndex');
    Route::patch('support/tickets/{id}/assign', [SupportTicketController::class, 'adminAssign'])->name('support.tickets.assign');
    Route::patch('support/tickets/{id}/status', [SupportTicketController::class, 'adminStatus'])->name('support.tickets.status');
    Route::post('support/tickets/{id}/reply', [SupportTicketController::class, 'reply'])->name('support.tickets.reply');

    // Quản trị câu hỏi trợ giúp Knowledge Base
    Route::apiResource('help-center/articles', HelpCenterController::class);

    // CRM hạng thành viên và điểm tích lũy
    Route::apiResource('membership-tiers', MembershipTierController::class);
    Route::patch('users/{user}/tier', [MembershipTierController::class, 'updateUserTier'])->name('users.tier.update');
});

Route::get('/flash-sales', [CouponController::class, 'flashSales']);
Route::get('/flash-sales/active', [CouponController::class, 'activeFlashSale']);
Route::get('/books/{book}/chapters/{chapter}/preview', [ChapterController::class, 'preview']);

// Route để stream e-book (Dùng signed URL, xử lý bảo mật bên trong controller bằng relative signature)
Route::get('/ebooks/{filename}/stream', [OrderController::class, 'streamEbook'])
    ->name('api.ebook.stream');

Route::get('/vnpay/return', [VnpayController::class, 'vnpayReturn'])->name('vnpay.return');
Route::get('/vnpay/ipn', [VnpayController::class, 'vnpayIpn'])->name('vnpay.ipn');

// Catch-all route cho API trả về JSON 404
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint không tồn tại trên hệ thống.',
    ], 404);
});

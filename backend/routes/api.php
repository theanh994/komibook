<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PhoneAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════════
// Public routes — Không cần xác thực
// ═══════════════════════════════════════════════════════════════════════════════

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login',    [AuthController::class, 'login'])->name('login')->middleware('throttle:login');
    Route::post('/google-login', [AuthController::class, 'googleLogin'])->name('google-login');
    Route::post('/phone/send-otp', [PhoneAuthController::class, 'sendOtp'])->name('phone.send-otp');
    Route::post('/phone/verify-otp', [PhoneAuthController::class, 'verifyOtp'])->name('phone.verify-otp');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password')->middleware('throttle:forgot-password');
    Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->name('reset-password');
});

// Các endpoint Catalog công cộng
Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::get('/series',     [\App\Http\Controllers\Api\BookController::class, 'allSeries']);
Route::get('/books',      [\App\Http\Controllers\Api\BookController::class, 'index']);
Route::get('/books/top-selling', [\App\Http\Controllers\Api\BookController::class, 'topSelling']);
Route::get('/books/{slug}', [\App\Http\Controllers\Api\BookController::class, 'show']);
Route::get('/books/{id}/series', [\App\Http\Controllers\Api\BookController::class, 'seriesBooks']);
Route::get('/books/{id}/author', [\App\Http\Controllers\Api\BookController::class, 'authorBooks']);
Route::get('/books/{id}/related', [\App\Http\Controllers\Api\BookController::class, 'relatedBooks']);

// Help Center công khai
Route::get('/help-center/articles', [\App\Http\Controllers\Api\HelpCenterController::class, 'index']);
Route::get('/help-center/articles/{id}', [\App\Http\Controllers\Api\HelpCenterController::class, 'show']);
Route::post('/help-center/articles/{id}/helpful', [\App\Http\Controllers\Api\HelpCenterController::class, 'helpful']);

// ═══════════════════════════════════════════════════════════════════════════════
// Protected routes — Yêu cầu Sanctum token hợp lệ
// ═══════════════════════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->prefix('auth')->name('auth.')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me',      [AuthController::class, 'me'])->name('me');
});

Route::middleware('auth:sanctum')->prefix('profile')->name('profile.')->group(function () {
    Route::put('/info',     [\App\Http\Controllers\Api\ProfileController::class, 'updateInfo'])->name('updateInfo');
    Route::put('/password', [\App\Http\Controllers\Api\ProfileController::class, 'updatePassword'])->name('updatePassword');
    Route::post('/avatar',  [\App\Http\Controllers\Api\ProfileController::class, 'uploadAvatar'])->name('uploadAvatar');
    Route::get('/addresses', [\App\Http\Controllers\Api\ProfileController::class, 'getAddresses'])->name('getAddresses');
    Route::post('/addresses', [\App\Http\Controllers\Api\ProfileController::class, 'addAddress'])->name('addAddress');
    Route::put('/addresses/{id}', [\App\Http\Controllers\Api\ProfileController::class, 'updateAddress'])->name('updateAddress');
    Route::delete('/addresses/{id}', [\App\Http\Controllers\Api\ProfileController::class, 'deleteAddress'])->name('deleteAddress');
    Route::patch('/addresses/{id}/default', [\App\Http\Controllers\Api\ProfileController::class, 'setDefaultAddress'])->name('setDefaultAddress');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/checkout', [\App\Http\Controllers\Api\CheckoutController::class, 'store'])->middleware('throttle:checkout');
    Route::post('/books/{id}/reviews', [\App\Http\Controllers\Api\BookController::class, 'addReview']);
    Route::get('/books/{id}/check-ownership', [\App\Http\Controllers\Api\BookController::class, 'checkOwnership']);
    Route::post('/coupons/apply', [\App\Http\Controllers\Api\CouponController::class, 'apply']);
    Route::get('/my-orders', [\App\Http\Controllers\Api\OrderController::class, 'myOrders']);
    Route::get('/my-library', [\App\Http\Controllers\Api\OrderController::class, 'myLibrary']);
    Route::get('/orders/{order}/ebooks/{book}/generate-link', [\App\Http\Controllers\Api\OrderController::class, 'generateEbookLink']);
    
    // VNPAY Create payment
    Route::post('/vnpay/create', [\App\Http\Controllers\Api\VnpayController::class, 'createPayment']);

    // Book Annotations
    Route::get('/annotations', [\App\Http\Controllers\Api\BookAnnotationController::class, 'index']);
    Route::post('/annotations', [\App\Http\Controllers\Api\BookAnnotationController::class, 'store']);
    Route::put('/annotations/{id}', [\App\Http\Controllers\Api\BookAnnotationController::class, 'update']);
    Route::delete('/annotations/{id}', [\App\Http\Controllers\Api\BookAnnotationController::class, 'destroy']);
    Route::get('/books/{id}/recent-annotations', [\App\Http\Controllers\Api\BookAnnotationController::class, 'recent']);

    // Wishlist
    Route::get('/wishlist', [\App\Http\Controllers\Api\WishlistController::class, 'index']);
    Route::post('/wishlist/{bookId}/toggle', [\App\Http\Controllers\Api\WishlistController::class, 'toggle']);
    Route::get('/wishlist/{bookId}/check', [\App\Http\Controllers\Api\WishlistController::class, 'check']);

    // User Notifications
    Route::get('/notifications', [\App\Http\Controllers\Api\UserNotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [\App\Http\Controllers\Api\UserNotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\UserNotificationController::class, 'markAllAsRead']);

    // Đăng ký và Trạng thái Tác giả
    Route::post('/auth/register-author', [\App\Http\Controllers\Api\AuthorController::class, 'register']);
    Route::get('/author/status',          [\App\Http\Controllers\Api\AuthorController::class, 'status']);
    Route::get('/author/dashboard-stats', [\App\Http\Controllers\Api\AuthorController::class, 'dashboardStats']);
    Route::get('/authors/{id}/identity-document', [\App\Http\Controllers\Api\AuthorController::class, 'downloadIdentityDocument']);

    // Tickets yêu cầu hỗ trợ (Khách hàng)
    Route::get('/support/tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'index']);
    Route::post('/support/tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'store']);
    Route::get('/support/tickets/{id}', [\App\Http\Controllers\Api\SupportTicketController::class, 'show']);
    Route::post('/support/tickets/{id}/message', [\App\Http\Controllers\Api\SupportTicketController::class, 'reply']);
    Route::get('/support/tickets/{ticket}/messages/{message}/attachment', [\App\Http\Controllers\Api\SupportTicketController::class, 'downloadAttachment']);
});

// ═══════════════════════════════════════════════════════════════════════════════
// Vendor routes — Quản lý gian hàng (yêu cầu role: vendor)
// ═══════════════════════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    // Thống kê dashboard
    Route::get('dashboard-stats', [\App\Http\Controllers\Api\Vendor\DashboardController::class, 'stats'])->name('dashboard.stats');

    Route::post('books/bulk-series', [\App\Http\Controllers\Api\Vendor\BookController::class, 'bulkSeries'])->name('books.bulkSeries');
    Route::post('books/bulk-discount', [\App\Http\Controllers\Api\Vendor\BookController::class, 'bulkDiscount'])->name('books.bulkDiscount');

    // Quản lý Bộ Sách (Vendor Series)
    Route::get('series', [\App\Http\Controllers\Api\Vendor\SeriesController::class, 'index'])->name('series.index');
    Route::put('series/{id}', [\App\Http\Controllers\Api\Vendor\SeriesController::class, 'update'])->name('series.update');
    Route::post('series/{id}/apply-discount', [\App\Http\Controllers\Api\Vendor\SeriesController::class, 'applyDiscount'])->name('series.applyDiscount');
    Route::delete('series/{id}', [\App\Http\Controllers\Api\Vendor\SeriesController::class, 'destroy'])->name('series.destroy');

    Route::apiResource('books', \App\Http\Controllers\Api\Vendor\BookController::class);

    // Quản lý đơn hàng
    Route::get('orders',              [\App\Http\Controllers\Api\Vendor\OrderController::class, 'index'])->name('orders.index');
    Route::patch('orders/bulk-status', [\App\Http\Controllers\Api\Vendor\OrderController::class, 'bulkUpdateStatus'])->name('orders.bulkUpdateStatus');
    Route::get('orders/{order}',      [\App\Http\Controllers\Api\Vendor\OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [\App\Http\Controllers\Api\Vendor\OrderController::class, 'updateStatus'])->name('orders.updateStatus');

    // Quản lý kho hàng (Warehouse)
    Route::get('warehouses', [\App\Http\Controllers\Api\Vendor\WarehouseController::class, 'index'])->name('warehouses.index');
    Route::get('warehouses/stats', [\App\Http\Controllers\Api\Vendor\WarehouseController::class, 'stats'])->name('warehouses.stats');
    Route::post('warehouses', [\App\Http\Controllers\Api\Vendor\WarehouseController::class, 'store'])->name('warehouses.store');
    Route::post('warehouses/adjust', [\App\Http\Controllers\Api\Vendor\WarehouseController::class, 'adjustStock'])->name('warehouses.adjust');

    // Quản lý tài chính / doanh thu (Finance)
    Route::get('finance', [\App\Http\Controllers\Api\Vendor\FinanceController::class, 'index'])->name('finance.index');
    Route::post('finance/payout', [\App\Http\Controllers\Api\Vendor\FinanceController::class, 'requestPayout'])->name('finance.payout');
    
    // Phân tích độc giả / báo cáo (Analytics)
    Route::get('analytics', [\App\Http\Controllers\Api\Vendor\AnalyticsController::class, 'index'])->name('analytics.index');

    // Đăng ký Flash Sale
    Route::get('flash-sales', [\App\Http\Controllers\Api\Vendor\FlashSaleController::class, 'index'])->name('flash-sales.index');
    Route::get('flash-sales/{flash_sale}/registered-books', [\App\Http\Controllers\Api\Vendor\FlashSaleController::class, 'registeredBooks'])->name('flash-sales.registered-books');
    Route::post('flash-sales/{flash_sale}/register', [\App\Http\Controllers\Api\Vendor\FlashSaleController::class, 'register'])->name('flash-sales.register');

    // Quản lý chương sách tự viết (Chapters)
    Route::get('books/{book}/chapters', [\App\Http\Controllers\Api\ChapterController::class, 'index'])->name('books.chapters.index');
    Route::post('books/{book}/chapters', [\App\Http\Controllers\Api\ChapterController::class, 'store'])->name('books.chapters.store');
    Route::put('books/{book}/chapters/{chapter}', [\App\Http\Controllers\Api\ChapterController::class, 'update'])->name('books.chapters.update');
    Route::delete('books/{book}/chapters/{chapter}', [\App\Http\Controllers\Api\ChapterController::class, 'destroy'])->name('books.chapters.destroy');

    // Cấu hình bản quyền & DRM
    Route::get('books/{book}/drm-settings', [\App\Http\Controllers\Api\DrmController::class, 'show'])->name('books.drm.show');
    Route::put('books/{book}/drm-settings', [\App\Http\Controllers\Api\DrmController::class, 'update'])->name('books.drm.update');

    // Kiểm kê kho hàng định kỳ (Inventory Audit)
    Route::get('inventory/audits', [\App\Http\Controllers\Api\InventoryAuditController::class, 'index'])->name('inventory.audits.index');
    Route::get('inventory/audits/{id}', [\App\Http\Controllers\Api\InventoryAuditController::class, 'show'])->name('inventory.audits.show');
    Route::post('inventory/audits', [\App\Http\Controllers\Api\InventoryAuditController::class, 'store'])->name('inventory.audits.store');
    Route::post('inventory/audits/{id}/complete', [\App\Http\Controllers\Api\InventoryAuditController::class, 'complete'])->name('inventory.audits.complete');

    // Điều chuyển kho (Stock Transfer)
    Route::get('inventory/transfers', [\App\Http\Controllers\Api\StockTransferController::class, 'index'])->name('inventory.transfers.index');
    Route::get('inventory/transfers/{id}', [\App\Http\Controllers\Api\StockTransferController::class, 'show'])->name('inventory.transfers.show');
    Route::post('inventory/transfers', [\App\Http\Controllers\Api\StockTransferController::class, 'store'])->name('inventory.transfers.store');
    Route::post('inventory/transfers/{id}/ship', [\App\Http\Controllers\Api\StockTransferController::class, 'ship'])->name('inventory.transfers.ship');
    Route::post('inventory/transfers/{id}/receive', [\App\Http\Controllers\Api\StockTransferController::class, 'receive'])->name('inventory.transfers.receive');

    // Trạng thái vận chuyển mô phỏng (Shipping)
    Route::patch('orders/{order}/shipping', [\App\Http\Controllers\Api\OrderController::class, 'updateShippingStatus'])->name('orders.shipping.update');
});

// ═══════════════════════════════════════════════════════════════════════════════
// Admin routes — Quản trị hệ thống (yêu cầu role: admin)
// ═══════════════════════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Thống kê tổng quan
    Route::get('stats', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'stats'])->name('stats');

    // Quản lý Users
    Route::get('users',              [\App\Http\Controllers\Api\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('users/{id}',         [\App\Http\Controllers\Api\Admin\UserController::class, 'show'])->name('users.show');
    Route::patch('users/{id}/role',  [\App\Http\Controllers\Api\Admin\UserController::class, 'updateRole'])->name('users.updateRole');

    // Quản lý Toàn bộ Sách (Admin)
    Route::get('books',                [\App\Http\Controllers\Api\Admin\BookController::class, 'index'])->name('books.index');
    Route::get('books/{book}',          [\App\Http\Controllers\Api\Admin\BookController::class, 'show'])->name('books.show');
    Route::patch('books/{book}/status', [\App\Http\Controllers\Api\Admin\BookController::class, 'updateStatus'])->name('books.updateStatus');
    Route::delete('books/{book}',       [\App\Http\Controllers\Api\Admin\BookController::class, 'destroy'])->name('books.destroy');

    // Quản lý Thể loại Sách
    Route::apiResource('categories', \App\Http\Controllers\Api\Admin\CategoryController::class);

    // Quản lý Coupons và Flash Sales
    Route::apiResource('coupons', \App\Http\Controllers\Api\Admin\CouponController::class);
    Route::apiResource('flash-sales', \App\Http\Controllers\Api\Admin\FlashSaleController::class);
    Route::post('flash-sales/{flash_sale}/items', [\App\Http\Controllers\Api\Admin\FlashSaleController::class, 'addItem']);
    Route::delete('flash-sales/{flash_sale}/items/{item}', [\App\Http\Controllers\Api\Admin\FlashSaleController::class, 'removeItem']);
    Route::post('flash-sales/{flash_sale}/items/bulk-delete', [\App\Http\Controllers\Api\Admin\FlashSaleController::class, 'bulkRemoveItems']);
    Route::put('flash-sales/items/{item_id}/approve', [\App\Http\Controllers\Api\Admin\FlashSaleController::class, 'approveItem']);
    Route::put('flash-sales/items/{item_id}/reject', [\App\Http\Controllers\Api\Admin\FlashSaleController::class, 'rejectItem']);

    // Admin Notification Campaigns
    Route::apiResource('notifications/campaigns', \App\Http\Controllers\Api\Admin\NotificationCampaignController::class);
    Route::post('notifications/campaigns/{id}/send', [\App\Http\Controllers\Api\Admin\NotificationCampaignController::class, 'send']);

    // Báo cáo tài chính
    Route::get('finance-report', [\App\Http\Controllers\Api\Admin\FinanceReportController::class, 'index'])->name('finance-report.index');

    // Đối soát doanh thu
    Route::get('reconciliation', [\App\Http\Controllers\Api\Admin\ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::patch('reconciliation/{id}/approve', [\App\Http\Controllers\Api\Admin\ReconciliationController::class, 'approve'])->name('reconciliation.approve');
    Route::patch('reconciliation/{id}/reject', [\App\Http\Controllers\Api\Admin\ReconciliationController::class, 'reject'])->name('reconciliation.reject');

    // Cấu hình hệ thống
    Route::get('config', [\App\Http\Controllers\Api\Admin\SystemConfigController::class, 'show'])->name('config.show');
    Route::put('config', [\App\Http\Controllers\Api\Admin\SystemConfigController::class, 'update'])->name('config.update');

    // Phê duyệt nhà bán & tác giả
    Route::get('approvals/vendors', [\App\Http\Controllers\Api\Admin\VendorApprovalController::class, 'index'])->name('approvals.vendors.index');
    Route::patch('approvals/vendors/{id}/approve', [\App\Http\Controllers\Api\Admin\VendorApprovalController::class, 'approveVendor'])->name('approvals.vendors.approve');
    Route::patch('approvals/authors/{id}/approve', [\App\Http\Controllers\Api\Admin\VendorApprovalController::class, 'approveAuthor'])->name('approvals.authors.approve');
    Route::patch('approvals/partners/{type}/{id}/reject', [\App\Http\Controllers\Api\Admin\VendorApprovalController::class, 'reject'])->name('approvals.partners.reject');

    // Quản lý Tickets hội thoại (Admin)
    Route::get('support/tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'adminIndex'])->name('support.tickets.adminIndex');
    Route::patch('support/tickets/{id}/assign', [\App\Http\Controllers\Api\SupportTicketController::class, 'adminAssign'])->name('support.tickets.assign');
    Route::patch('support/tickets/{id}/status', [\App\Http\Controllers\Api\SupportTicketController::class, 'adminStatus'])->name('support.tickets.status');
    Route::post('support/tickets/{id}/reply', [\App\Http\Controllers\Api\SupportTicketController::class, 'reply'])->name('support.tickets.reply');

    // Quản trị câu hỏi trợ giúp Knowledge Base
    Route::apiResource('help-center/articles', \App\Http\Controllers\Api\HelpCenterController::class);

    // CRM hạng thành viên và điểm tích lũy
    Route::apiResource('membership-tiers', \App\Http\Controllers\Api\Admin\MembershipTierController::class);
    Route::patch('users/{user}/tier', [\App\Http\Controllers\Api\Admin\MembershipTierController::class, 'updateUserTier'])->name('users.tier.update');
});

Route::get('/flash-sales', [\App\Http\Controllers\Api\CouponController::class, 'flashSales']);
Route::get('/flash-sales/active', [\App\Http\Controllers\Api\CouponController::class, 'activeFlashSale']);

// Route để stream e-book (Dùng signed URL, xử lý bảo mật bên trong controller bằng relative signature)
Route::get('/ebooks/{filename}/stream', [\App\Http\Controllers\Api\OrderController::class, 'streamEbook'])
    ->name('api.ebook.stream');

Route::get('/vnpay/return', [\App\Http\Controllers\Api\VnpayController::class, 'vnpayReturn'])->name('vnpay.return');
Route::get('/vnpay/ipn', [\App\Http\Controllers\Api\VnpayController::class, 'vnpayIpn'])->name('vnpay.ipn');

// Catch-all route cho API trả về JSON 404
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint không tồn tại trên hệ thống.'
    ], 404);
});

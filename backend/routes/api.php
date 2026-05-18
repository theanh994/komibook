<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════════
// Public routes — Không cần xác thực
// ═══════════════════════════════════════════════════════════════════════════════

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login',    [AuthController::class, 'login'])->name('login')->middleware('throttle:login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password')->middleware('throttle:forgot-password');
    Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->name('reset-password');
});

// Các endpoint Catalog công cộng
Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::get('/books',      [\App\Http\Controllers\Api\BookController::class, 'index']);
Route::get('/books/top-selling', [\App\Http\Controllers\Api\BookController::class, 'topSelling']);
Route::get('/books/{slug}', [\App\Http\Controllers\Api\BookController::class, 'show']);
Route::get('/books/{id}/series', [\App\Http\Controllers\Api\BookController::class, 'seriesBooks']);

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
});

// ═══════════════════════════════════════════════════════════════════════════════
// Vendor routes — Quản lý gian hàng (yêu cầu role: vendor)
// ═══════════════════════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    // Thống kê dashboard
    Route::get('dashboard-stats', [\App\Http\Controllers\Api\Vendor\DashboardController::class, 'stats'])->name('dashboard.stats');

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

    // Quản lý Coupons
    Route::apiResource('coupons', \App\Http\Controllers\Api\Admin\CouponController::class);

    // Báo cáo tài chính
    Route::get('finance-report', [\App\Http\Controllers\Api\Admin\FinanceReportController::class, 'index'])->name('finance-report.index');

    // Đối soát doanh thu
    Route::get('reconciliation', [\App\Http\Controllers\Api\Admin\ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::patch('reconciliation/{id}/approve', [\App\Http\Controllers\Api\Admin\ReconciliationController::class, 'approve'])->name('reconciliation.approve');
    Route::patch('reconciliation/{id}/reject', [\App\Http\Controllers\Api\Admin\ReconciliationController::class, 'reject'])->name('reconciliation.reject');

    // Cấu hình hệ thống
    Route::get('config', [\App\Http\Controllers\Api\Admin\SystemConfigController::class, 'show'])->name('config.show');
    Route::put('config', [\App\Http\Controllers\Api\Admin\SystemConfigController::class, 'update'])->name('config.update');
});

Route::get('/flash-sales', [\App\Http\Controllers\Api\CouponController::class, 'flashSales']);

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

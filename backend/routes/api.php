<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════════
// Public routes — Không cần xác thực
// ═══════════════════════════════════════════════════════════════════════════════

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login',    [AuthController::class, 'login'])->name('login');
});

// Các endpoint Catalog công cộng
Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::get('/books',      [\App\Http\Controllers\Api\BookController::class, 'index']);
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
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/checkout', [\App\Http\Controllers\Api\CheckoutController::class, 'store']);
    Route::post('/books/{id}/reviews', [\App\Http\Controllers\Api\BookController::class, 'addReview']);
    Route::get('/books/{id}/check-ownership', [\App\Http\Controllers\Api\BookController::class, 'checkOwnership']);
    Route::post('/coupons/apply', [\App\Http\Controllers\Api\CouponController::class, 'apply']);
    Route::get('/my-orders', [\App\Http\Controllers\Api\OrderController::class, 'myOrders']);
    Route::get('/orders/{order}/ebooks/{book}/generate-link', [\App\Http\Controllers\Api\OrderController::class, 'generateEbookLink']);
});

// ═══════════════════════════════════════════════════════════════════════════════
// Vendor routes — Quản lý gian hàng (yêu cầu role: vendor)
// ═══════════════════════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->prefix('vendor')->name('vendor.')->group(function () {
    Route::apiResource('books', \App\Http\Controllers\Api\Vendor\BookController::class);

    // Quản lý đơn hàng
    Route::get('orders',              [\App\Http\Controllers\Api\Vendor\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}',      [\App\Http\Controllers\Api\Vendor\OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [\App\Http\Controllers\Api\Vendor\OrderController::class, 'updateStatus'])->name('orders.updateStatus');
});

// ═══════════════════════════════════════════════════════════════════════════════
// Admin routes — Quản trị hệ thống (yêu cầu role: admin)
// ═══════════════════════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function () {
    // Thống kê tổng quan
    Route::get('stats', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'stats'])->name('stats');

    // Quản lý Users
    Route::get('users',              [\App\Http\Controllers\Api\Admin\UserController::class, 'index'])->name('users.index');
    Route::patch('users/{id}/role',  [\App\Http\Controllers\Api\Admin\UserController::class, 'updateRole'])->name('users.updateRole');
});

// Route để stream e-book (Dùng signed URL, không cần auth middleware vì link chỉ có hiệu lực với chữ ký hợp lệ)
Route::get('/ebooks/{filename}/stream', [\App\Http\Controllers\Api\OrderController::class, 'streamEbook'])
    ->middleware('signed')
    ->name('api.ebook.stream');

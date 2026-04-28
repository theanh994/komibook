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
    Route::post('/coupons/apply', [\App\Http\Controllers\Api\CouponController::class, 'apply']);
});


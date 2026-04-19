<?php

use App\Http\Controllers\TestAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Default Sanctum user route ─────────────────────────────────────────────
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── Test Auth endpoint (kiểm tra kết nối FE ↔ BE) ─────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/test-auth', [TestAuthController::class, 'me']);
});

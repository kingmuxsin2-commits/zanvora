<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MagicLinkController;
use App\Http\Controllers\Api\V1\Admin\SupplierController;

Route::post('/test-register', function (Request $request) {
    return response()->json(['message' => 'Route works', 'data' => $request->all()]);
}); 

Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::get('/suppliers/pending', [SupplierController::class, 'pending']);
    Route::post('/suppliers/{supplier}/approve', [SupplierController::class, 'approve']);
    Route::post('/suppliers/{supplier}/reject', [SupplierController::class, 'reject']);
});


Route::post('/magic-link/request', [MagicLinkController::class, 'request']);
Route::post('/magic-link/verify', [MagicLinkController::class, 'verify']);

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'database' => DB::connection()->getPdo() ? 'connected' : 'failed',
        'redis' => rescue(fn() => Redis::ping(), 'failed'),
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


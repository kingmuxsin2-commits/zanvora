<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MagicLinkController;
use App\Http\Controllers\Api\V1\Admin\SupplierController;
use App\Http\Controllers\Api\V1\Supplier\ProductController as SupplierProductController;
use App\Http\Controllers\Api\V1\Admin\ProductApprovalController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentVerificationController;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use App\Http\Controllers\Api\V1\Supplier\FulfillmentController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\CommissionTierController;
use App\Http\Controllers\Api\V1\Admin\CreditController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'database' => DB::connection()->getPdo() ? 'connected' : 'failed',
        'redis' => rescue(fn() => Redis::ping(), 'failed'),
    ]);
});

Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);

// Authentication
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/magic-link/request', [MagicLinkController::class, 'request']);
Route::post('/magic-link/verify', [MagicLinkController::class, 'verify']);

// Public Products
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Authenticated)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // User profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/orders', [OrderController::class, 'supplierOrders']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Orders (Customer)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/claim-payment', [OrderController::class, 'claimPayment']);
    Route::put('/fulfillments/{fulfillment}', [FulfillmentController::class, 'update']);

    /*
    |----------------------------------------------------------------------
    | Supplier Routes
    |----------------------------------------------------------------------
    */
    Route::prefix('supplier')->group(function () {
        Route::apiResource('products', SupplierProductController::class);
        Route::get('/orders', [OrderController::class, 'supplierOrders']);
        Route::put('/fulfillments/{fulfillment}', [FulfillmentController::class, 'update']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin Routes
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')->group(function () {
        // Supplier Management
        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::get('/suppliers/pending', [SupplierController::class, 'pending']);
        Route::post('/suppliers/{supplier}/approve', [SupplierController::class, 'approve']);
        Route::post('/suppliers/{supplier}/reject', [SupplierController::class, 'reject']);
        Route::get('/reports/payout', [ReportController::class, 'payoutReport']);
        Route::post('/suppliers/{supplier}/mark-paid', [ReportController::class, 'markPaid']);
        Route::post('/orders/{order}/payment-reference', [OrderController::class, 'updatePaymentReference']);
        Route::apiResource('commission-tiers', CommissionTierController::class);
        Route::post('/orders/{order}/credits', [CreditController::class, 'store']);
        Route::get('/orders/{order}/credits', [CreditController::class, 'index']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        // Product Approval
        Route::get('/products/pending', [ProductApprovalController::class, 'index']);
        Route::get('/products/all', [ProductApprovalController::class, 'all']);
        Route::post('/products/{product}/approve', [ProductApprovalController::class, 'approve']);
        Route::post('/products/{product}/reject', [ProductApprovalController::class, 'reject']);

        // Payment Verification
        Route::get('/orders/pending-payment', [PaymentVerificationController::class, 'pending']);
        Route::post('/orders/{order}/confirm-payment', [PaymentVerificationController::class, 'confirm']);
        Route::post('/orders/{order}/reject-payment', [PaymentVerificationController::class, 'reject']);
        Route::post('/orders/{order}/mark-checking', [PaymentVerificationController::class, 'markChecking']);
    });
});

// Test route (development only)
Route::post('/test-register', function (Request $request) {
    return response()->json(['message' => 'Route works', 'data' => $request->all()]);
});
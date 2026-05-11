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
use App\Http\Controllers\Api\V1\DegmoController;
use App\Http\Controllers\Api\V1\XaafadController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\Admin\AdminControlsController;
use App\Http\Controllers\Api\V1\Admin\PaymentVerificationController;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use App\Http\Controllers\Api\V1\Supplier\FulfillmentController;
use App\Http\Controllers\Api\V1\Admin\LocationController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\CommissionTierController;
use App\Http\Controllers\Api\V1\Admin\CreditController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Supplier\SupplierProfileController;
use App\Http\Controllers\Api\V1\Supplier\InventoryItemController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\DeliveryFeeController;
use App\Http\Controllers\Api\V1\RatingController;
use App\Http\Controllers\Api\V1\Supplier\InventoryTransactionController;
use App\Http\Controllers\Api\V1\Admin\RevenueController;
use App\Http\Controllers\Api\V1\Supplier\SupplierAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\FulfillmentController as AdminFulfillmentController;

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
Route::get('/delivery-fee', [DeliveryFeeController::class, 'show']);
Route::get('/degmo', [DegmoController::class, 'index']);
Route::get('/xaafad', [XaafadController::class, 'index']);

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
    Route::post('/orders/{order}/confirm-delivery', [OrderController::class, 'confirmDelivery']);
    Route::post('/products/{product}/rate', [RatingController::class, 'store']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Orders (Customer)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/claim-payment', [OrderController::class, 'claimPayment']);

    /*
    |----------------------------------------------------------------------
    | Supplier Routes
    |----------------------------------------------------------------------
    */
    Route::prefix('supplier')->group(function () {
        Route::apiResource('products', SupplierProductController::class);
        Route::get('/orders', [OrderController::class, 'supplierOrders']);
        Route::put('/products/{product}/stock', [ProductController::class, 'updateStock']);
        Route::put('/fulfillments/{fulfillment}', [FulfillmentController::class, 'update']);
        Route::get('/earnings', [OrderController::class, 'earnings']);
        Route::get('/inventory/transactions', [InventoryTransactionController::class, 'index']);
        Route::post('/inventory/transactions', [InventoryTransactionController::class, 'store']);
        Route::delete('/inventory/transactions/{transaction}', [InventoryTransactionController::class, 'destroy']);
        Route::get('/inventory/stock-summary', [InventoryTransactionController::class, 'stockSummary']);
        Route::apiResource('inventory-items', InventoryItemController::class)->only(['index', 'store', 'destroy']);
        Route::get('/inventory', [SupplierProductController::class, 'inventory']);
        Route::put('/settings', [SupplierProfileController::class, 'updateSettings']);
        Route::get('/analytics', [SupplierAnalyticsController::class, 'index']);
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
        Route::get('/analytics/regions', [AnalyticsController::class, 'regionAnalytics']);
        Route::apiResource('delivery-fees', DeliveryFeeController::class);
        Route::get('/orders/delivery-list', [OrderController::class, 'deliveryList']);
        Route::get('/location/customers', [LocationController::class, 'index']);
        Route::post('/location/customers/{user}/gps', [LocationController::class, 'updateGps']);
        Route::post('/suppliers/{supplier}/mark-paid', [ReportController::class, 'markPaid']);
        Route::post('/orders/{order}/payment-reference', [OrderController::class, 'updatePaymentReference']);
        Route::apiResource('commission-tiers', CommissionTierController::class);
        Route::post('/orders/{order}/credits', [CreditController::class, 'store']);
        Route::post('/controls/users/{user}/reset-password', [AdminControlsController::class, 'resetPassword']);
        Route::get('/controls/customers', [AdminControlsController::class, 'customers']);
        Route::get('/controls/suppliers', [AdminControlsController::class, 'suppliers']);
        Route::post('/controls/users/{user}/toggle-active', [AdminControlsController::class, 'toggleUserActive']);
        Route::post('/controls/suppliers/{supplier}/toggle-status', [AdminControlsController::class, 'toggleSupplierStatus']);
        Route::get('/orders/{order}/credits', [CreditController::class, 'index']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::get('/controls/customers', [AdminControlsController::class, 'customers']);
        Route::get('/controls/suppliers', [AdminControlsController::class, 'suppliers']);
        Route::post('/controls/users/{user}/toggle-active', [AdminControlsController::class, 'toggleUserActive']);
        Route::post('/controls/suppliers/{supplier}/toggle-status', [AdminControlsController::class, 'toggleSupplierStatus']);
        Route::get('/analytics/inventory', [AnalyticsController::class, 'inventoryAnalytics']);
        Route::post('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/revenue/platform', [RevenueController::class, 'index']);
        Route::get('/revenue/supplier-breakdown', [RevenueController::class, 'supplierBreakdown']);
        Route::get('/suppliers/all', [SupplierController::class, 'allSuppliers']);
        Route::apiResource('admin-users', AdminUserController::class);
        Route::post('/products/bulk-reject', [ProductApprovalController::class, 'bulkReject']);
        Route::post('/products/bulk-approve', [ProductApprovalController::class, 'bulkApprove']);
        Route::post('/fulfillments/{fulfillment}/deliver', [AdminFulfillmentController::class, 'markDelivered']);

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index']);
        Route::get('/analytics/rfm', [AnalyticsController::class, 'rfmAnalysis']);
        Route::get('/analytics/ltv-distribution', [AnalyticsController::class, 'ltvDistribution']);
        Route::get('/analytics/products', [AnalyticsController::class, 'productAnalytics']);
        Route::get('/analytics/suppliers', [AnalyticsController::class, 'supplierAnalytics']);
        Route::get('/analytics/cohort-retention', [AnalyticsController::class, 'cohortRetention']);
        Route::get('/analytics/suppliers/cohort', [AnalyticsController::class, 'supplierCohortRetention']);
        Route::get('/analytics/suppliers/arps', [AnalyticsController::class, 'arpsTrend']);
        Route::get('/analytics/suppliers/concentration', [AnalyticsController::class, 'revenueConcentration']);
        Route::get('/analytics/suppliers/churn', [AnalyticsController::class, 'supplierChurn']);

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
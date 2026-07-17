<?php

use App\Http\Controllers\Api\Admin\VendorManagementController;
use App\Http\Controllers\Api\Vendor\VendorAuthController;
use App\Http\Controllers\Api\Vendor\VendorComparisonController;
use App\Http\Controllers\Api\Vendor\VendorDashboardController;
use App\Http\Controllers\Api\Vendor\VendorInventoryController;
use App\Http\Controllers\Api\Vendor\VendorOrderController;
use App\Http\Controllers\Api\Vendor\VendorCategoryController;
use App\Http\Controllers\Api\Vendor\VendorPerformanceAnalyticsController;
use App\Http\Controllers\Api\Vendor\VendorProductController;
use App\Http\Controllers\Api\Vendor\VendorProductOptionsController;
use App\Http\Controllers\Api\Vendor\VendorProfileController;
use App\Http\Controllers\Api\Vendor\VendorServiceController;
use App\Http\Controllers\Api\Vendor\VendorSupportChatController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vendor module — public
|--------------------------------------------------------------------------
*/
Route::prefix('vendor')->group(function () {
    Route::post('/auth/register', [VendorAuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']); // body: email, password, roles: "vendor"

    Route::get('/compare/products/{productId}', [VendorComparisonController::class, 'byProduct']);
    Route::post('/compare/products', [VendorComparisonController::class, 'byProducts']);
});

/*
|--------------------------------------------------------------------------
| Vendor module — authenticated vendor
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:vendor', 'vendor.account'])->prefix('vendor')->group(function () {
    Route::get('/auth/me', [VendorAuthController::class, 'me']);
    Route::post('/auth/logout', [VendorAuthController::class, 'logout']);

    Route::get('/profile', [VendorProfileController::class, 'show']);
    Route::put('/profile', [VendorProfileController::class, 'update']);
    Route::post('/profile', [VendorProfileController::class, 'update']);

    Route::get('/notifications', [\App\Http\Controllers\Api\RoleNotificationsApiController::class, 'index']);
    Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Api\RoleNotificationsApiController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Api\RoleNotificationsApiController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\RoleNotificationsApiController::class, 'destroy']);
    Route::post('/notifications/clear-all', [\App\Http\Controllers\Api\RoleNotificationsApiController::class, 'clearAll']);

    Route::get('/support/chat', [VendorSupportChatController::class, 'show']);
    Route::get('/support/chat/messages', [VendorSupportChatController::class, 'messages']);
    Route::post('/support/chat/messages', [VendorSupportChatController::class, 'sendMessage']);

    Route::get('/application', [\App\Http\Controllers\Api\Vendor\VendorApplicationController::class, 'show']);
    Route::post('/application/submit', [\App\Http\Controllers\Api\Vendor\VendorApplicationController::class, 'submit']);
    Route::post('/application/resubmit', [\App\Http\Controllers\Api\Vendor\VendorApplicationController::class, 'resubmit']);

    Route::get('/documents', [\App\Http\Controllers\Api\Vendor\VendorDocumentController::class, 'index']);
    Route::post('/documents', [\App\Http\Controllers\Api\Vendor\VendorDocumentController::class, 'store']);
    Route::delete('/documents/{id}', [\App\Http\Controllers\Api\Vendor\VendorDocumentController::class, 'destroy']);

    Route::middleware('vendor.approved')->group(function () {
        Route::get('/dashboard/stats', [VendorDashboardController::class, 'stats']);
        Route::get('/dashboard/summary', [VendorDashboardController::class, 'summary']);
        Route::get('/analytics/performance', [VendorPerformanceAnalyticsController::class, 'show']);
        Route::get('/analytics/performance/export', [VendorPerformanceAnalyticsController::class, 'export']);
        Route::post('/analytics/performance/share', [VendorPerformanceAnalyticsController::class, 'share']);

        Route::get('/product-options/categories', [VendorProductOptionsController::class, 'categories']);
        Route::get('/product-options/services', [VendorProductOptionsController::class, 'services']);

        Route::get('/categories', [VendorCategoryController::class, 'index']);
        Route::post('/categories', [VendorCategoryController::class, 'store']);
        Route::get('/categories/{id}', [VendorCategoryController::class, 'show']);
        Route::put('/categories/{id}', [VendorCategoryController::class, 'update']);
        Route::post('/categories/{id}', [VendorCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [VendorCategoryController::class, 'destroy']);

        Route::get('/services', [VendorServiceController::class, 'index']);
        Route::post('/services', [VendorServiceController::class, 'store']);
        Route::get('/services/{id}', [VendorServiceController::class, 'show']);
        Route::put('/services/{id}', [VendorServiceController::class, 'update']);
        Route::post('/services/{id}', [VendorServiceController::class, 'update']);
        Route::delete('/services/{id}', [VendorServiceController::class, 'destroy']);

        Route::get('/products', [VendorProductController::class, 'index']);
        Route::post('/products', [VendorProductController::class, 'store']);
        Route::get('/products/{id}', [VendorProductController::class, 'show']);
        Route::put('/products/{id}', [VendorProductController::class, 'update']);
        Route::post('/products/{id}', [VendorProductController::class, 'update']);
        Route::delete('/products/{id}', [VendorProductController::class, 'destroy']);

        Route::get('/inventory/{vendorProductId}', [VendorInventoryController::class, 'show']);
        Route::put('/inventory/{vendorProductId}', [VendorInventoryController::class, 'update']);
        Route::post('/inventory/{vendorProductId}', [VendorInventoryController::class, 'update']);

        Route::get('/orders', [VendorOrderController::class, 'index']);
        Route::get('/orders/{id}/contact', [VendorOrderController::class, 'contact']);
        Route::get('/orders/{id}/invoice', [VendorOrderController::class, 'invoice']);
        Route::get('/orders/{id}/download', [VendorOrderController::class, 'download']);
        Route::get('/orders/{id}', [VendorOrderController::class, 'show']);
        Route::post('/orders/{id}/status', [VendorOrderController::class, 'updateStatus']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin vendor management API
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/vendors')->group(function () {
    Route::get('/stats', [VendorManagementController::class, 'stats']);
    Route::get('/management', [VendorManagementController::class, 'management']);
    Route::get('/recent-requests', [VendorManagementController::class, 'recentRequests']);
    Route::get('/', [VendorManagementController::class, 'index']);
    Route::get('/{id}/management', [VendorManagementController::class, 'managementDetail']);
    Route::post('/{vendorId}/products/{productId}/toggle', [VendorManagementController::class, 'toggleProduct']);
    Route::delete('/{vendorId}/products/{productId}', [VendorManagementController::class, 'destroyProduct']);
    Route::get('/{id}/analytics', [VendorManagementController::class, 'analytics']);
    Route::get('/{id}/products', [VendorManagementController::class, 'products']);
    Route::get('/{id}/orders', [VendorManagementController::class, 'orders']);
    Route::get('/{id}/application-detail', [VendorManagementController::class, 'applicationDetail']);
    Route::get('/{id}', [VendorManagementController::class, 'show']);
    Route::put('/{id}', [VendorManagementController::class, 'update']);
    Route::post('/{id}', [VendorManagementController::class, 'update']);
    Route::post('/{id}/approve', [VendorManagementController::class, 'approve']);
    Route::post('/{id}/reject', [VendorManagementController::class, 'reject']);
    Route::post('/{id}/suspend', [VendorManagementController::class, 'suspend']);
    Route::post('/{id}/activate', [VendorManagementController::class, 'activate']);
    Route::post('/{id}/under-review', [VendorManagementController::class, 'underReview']);
    Route::post('/{id}/disable', [VendorManagementController::class, 'disable']);
    Route::post('/{id}/delete', [VendorManagementController::class, 'destroy']);
    Route::delete('/{id}', [VendorManagementController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/marketplace')->group(function () {
    Route::get('/analytics', [\App\Http\Controllers\Api\Admin\MarketplaceAdminController::class, 'analytics']);
    Route::get('/settings', [\App\Http\Controllers\Api\Admin\MarketplaceAdminController::class, 'settings']);
    Route::put('/settings', [\App\Http\Controllers\Api\Admin\MarketplaceAdminController::class, 'updateSettings']);
    Route::get('/products', [\App\Http\Controllers\Api\Admin\MarketplaceAdminController::class, 'products']);
    Route::post('/products/{id}/approve', [\App\Http\Controllers\Api\Admin\MarketplaceAdminController::class, 'approveProduct']);
    Route::post('/products/{id}/reject', [\App\Http\Controllers\Api\Admin\MarketplaceAdminController::class, 'rejectProduct']);
    Route::get('/orders', [\App\Http\Controllers\Api\Admin\MarketplaceAdminController::class, 'vendorOrders']);
    Route::post('/documents/{id}/verify', [\App\Http\Controllers\Api\Admin\MarketplaceAdminController::class, 'verifyDocument']);
});

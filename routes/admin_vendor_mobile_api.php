<?php

use App\Http\Controllers\Api\Admin\ContractorRegistrationAdminController;
use App\Http\Controllers\Api\Admin\MarketplaceAdminController;
use App\Http\Controllers\Api\Admin\VendorManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin vendor / marketplace mobile API (Product Settings, vendor manage)
| Loaded from routes/api.php with other admin routes (not only at file end).
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/vendors')->group(function () {
    Route::get('/stats', [VendorManagementController::class, 'stats']);
    Route::get('/management', [VendorManagementController::class, 'management']);
    Route::get('/recent-requests', [VendorManagementController::class, 'recentRequests']);
    Route::get('/', [VendorManagementController::class, 'index']);
    Route::post('/', [VendorManagementController::class, 'store']);
    Route::get('/{id}/management', [VendorManagementController::class, 'managementDetail']);
    Route::post('/{vendorId}/products', [VendorManagementController::class, 'storeProduct']);
    Route::get('/{vendorId}/products/{productId}', [VendorManagementController::class, 'showProduct']);
    Route::put('/{vendorId}/products/{productId}', [VendorManagementController::class, 'updateProduct']);
    Route::post('/{vendorId}/products/{productId}', [VendorManagementController::class, 'updateProduct']);
    Route::post('/{vendorId}/products/{productId}/toggle', [VendorManagementController::class, 'toggleProduct']);
    Route::delete('/{vendorId}/products/{productId}', [VendorManagementController::class, 'destroyProduct']);
    Route::get('/{id}/analytics', [VendorManagementController::class, 'analytics']);
    Route::get('/{id}/products', [VendorManagementController::class, 'products']);
    Route::get('/{id}/orders', [VendorManagementController::class, 'orders']);
    Route::get('/{id}/application-detail', [VendorManagementController::class, 'applicationDetail']);
    Route::get('/{id}', [VendorManagementController::class, 'show']);
    Route::put('/{id}', [VendorManagementController::class, 'update']);
    Route::post('/{id}', [VendorManagementController::class, 'update']);
    Route::post('/{id}/account-status', [VendorManagementController::class, 'updateAccountStatus']);
    Route::post('/{id}/approve', [VendorManagementController::class, 'approve']);
    Route::post('/{id}/reject', [VendorManagementController::class, 'reject']);
    Route::post('/{id}/request-documents', [ContractorRegistrationAdminController::class, 'requestDocuments']);
    Route::post('/{id}/suspend', [VendorManagementController::class, 'suspend']);
    Route::post('/{id}/activate', [VendorManagementController::class, 'activate']);
    Route::post('/{id}/under-review', [VendorManagementController::class, 'underReview']);
    Route::post('/{id}/disable', [VendorManagementController::class, 'disable']);
    Route::post('/{id}/delete', [VendorManagementController::class, 'destroy']);
    Route::delete('/{id}', [VendorManagementController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/contractor-registration')->group(function () {
    Route::get('/fields', [ContractorRegistrationAdminController::class, 'fields']);
    Route::put('/fields/{id}', [ContractorRegistrationAdminController::class, 'updateField']);
    Route::post('/fields/reorder', [ContractorRegistrationAdminController::class, 'reorderFields']);
    Route::get('/banks', [ContractorRegistrationAdminController::class, 'banks']);
    Route::post('/banks', [ContractorRegistrationAdminController::class, 'storeBank']);
    Route::put('/banks/{id}', [ContractorRegistrationAdminController::class, 'updateBank']);
    Route::delete('/banks/{id}', [ContractorRegistrationAdminController::class, 'destroyBank']);
    Route::get('/cities', [ContractorRegistrationAdminController::class, 'cities']);
    Route::post('/cities', [ContractorRegistrationAdminController::class, 'storeCity']);
    Route::put('/cities/{id}', [ContractorRegistrationAdminController::class, 'updateCity']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/marketplace')->group(function () {
    Route::get('/analytics', [MarketplaceAdminController::class, 'analytics']);
    Route::get('/settings', [MarketplaceAdminController::class, 'settings']);
    Route::put('/settings', [MarketplaceAdminController::class, 'updateSettings']);
    Route::get('/products', [MarketplaceAdminController::class, 'products']);
    Route::post('/products/{id}/approve', [MarketplaceAdminController::class, 'approveProduct']);
    Route::post('/products/{id}/reject', [MarketplaceAdminController::class, 'rejectProduct']);
    Route::get('/orders', [MarketplaceAdminController::class, 'vendorOrders']);
    Route::post('/documents/{id}/verify', [MarketplaceAdminController::class, 'verifyDocument']);
});

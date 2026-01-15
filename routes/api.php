<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API HEALTH CHECK
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return ['status' => 'API is working'];
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATION (PUBLIC)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [\App\Http\Controllers\Auth\AuthController::class, 'register']);
    Route::post('/login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
    // Password reset endpoints (placeholder - implement if needed)
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp', [\App\Http\Controllers\Auth\AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [\App\Http\Controllers\Auth\AuthController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATION (PROTECTED)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout']);
    Route::get('/profile', [\App\Http\Controllers\Auth\AuthController::class, 'profile']);
    Route::get('/user', [\App\Http\Controllers\Auth\AuthController::class, 'profile']); // Alias for /profile

    // Payments
    Route::post('payments/paypal/create', [\App\Http\Controllers\PaymentController::class, 'createPaypalOrder']);
    Route::post('payments/paypal/webhook', [\App\Http\Controllers\PaymentController::class, 'paypalWebhook']);

    // Shop / Orders (under /api/auth/shop)
    Route::middleware('role:client|admin|supervisor|area_manager')->prefix('shop')->group(function () {
        Route::post('/checkout', [\App\Http\Controllers\Shop\OrderController::class, 'checkout']);
        Route::post('/orders/{id}/mark-paid', [\App\Http\Controllers\Shop\OrderController::class, 'markPaid']);
    });

    /*
    |--------------------------------------------------------------------------
    | COMPLAINTS
    |--------------------------------------------------------------------------
    */
    Route::prefix('complaints')->group(function () {
        Route::get('/', [\App\Http\Controllers\ComplaintController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\ComplaintController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\ComplaintController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\ComplaintController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\ComplaintController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | CATEGORIES (Admin Only)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->apiResource('categories', \App\Http\Controllers\CategoryController::class);
});

/*
|--------------------------------------------------------------------------
| SUBSCRIPTIONS (PUBLIC PLANS ENDPOINT)
|--------------------------------------------------------------------------
*/
Route::get('/subscriptions/plans', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'plans']);

/*
|--------------------------------------------------------------------------
| SUBSCRIPTIONS (PROTECTED)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|admin'])->prefix('subscriptions')->group(function () {
    Route::get('/', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'update']);
    Route::post('/{id}/mark-paid', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'markPaid']);
    Route::delete('/{id}', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| VISITS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:technician|supervisor|area_manager|client|admin'])->prefix('visits')->group(function () {
    Route::get('/', [\App\Http\Controllers\Visit\VisitController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Visit\VisitController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Visit\VisitController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Visit\VisitController::class, 'update']);
    Route::post('/{id}/upload-photo', [\App\Http\Controllers\Visit\VisitController::class, 'uploadPhoto']);
});

/*
|--------------------------------------------------------------------------
| TECHNICIAN ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:technician'])->prefix('tech')->group(function () {
    Route::get('/visits', [\App\Http\Controllers\Technician\TechnicianController::class, 'assigned']);
    Route::post('/visits/{id}/accept', [\App\Http\Controllers\Technician\TechnicianController::class, 'accept']);
    Route::post('/visits/{id}/start', [\App\Http\Controllers\Technician\TechnicianController::class, 'start']);
    Route::post('/visits/{id}/complete', [\App\Http\Controllers\Technician\TechnicianController::class, 'complete']);
    Route::post('/visits/{id}/photos', [\App\Http\Controllers\Technician\TechnicianController::class, 'uploadPhoto']);
});

/*
|--------------------------------------------------------------------------
| SUPERVISOR ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:supervisor'])->prefix('supervisor')->group(function () {
    Route::get('/visits', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'listVisits']);
    Route::get('/visits/{id}', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'reviewVisit']);
    Route::post('/visits/{id}/recommend', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'recommendProducts']);
    Route::post('/visits/{id}/finalize', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'finalizeReport']);
    Route::post('/visits/{id}/status', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'updateVisitStatus']);
    Route::get('/areas', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'listAreas']);
    Route::get('/complaints', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'listComplaints']);
    Route::post('/complaints/{id}/escalate', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'escalateComplaint']);
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // User Management
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index']);
    Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store']);
    Route::get('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'show']);
    Route::put('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'update']);
    Route::delete('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'destroy']);

    // Roles
    Route::get('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'index']);
    Route::post('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'store']);

    // Products Management
    Route::get('/products', [\App\Http\Controllers\Admin\ProductController::class, 'index']);
    Route::post('/products', [\App\Http\Controllers\Admin\ProductController::class, 'store']);
    Route::get('/products/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'show']);
    Route::put('/products/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'update']);
    Route::delete('/products/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'destroy']);
    Route::post('/products/{id}/toggle-status', [\App\Http\Controllers\Admin\ProductController::class, 'toggleStatus']);
    Route::post('/products/bulk-delete', [\App\Http\Controllers\Admin\ProductController::class, 'bulkDelete']);
    Route::post('/products/bulk-update-status', [\App\Http\Controllers\Admin\ProductController::class, 'bulkUpdateStatus']);
    Route::post('/products/bulk-update-stock', [\App\Http\Controllers\Admin\ProductController::class, 'bulkUpdateStock']);
});

/*
|--------------------------------------------------------------------------
| HR ROUTES (HR and Admin can access)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:hr|admin'])->prefix('admin/hr')->group(function () {
    Route::get('/employees', [\App\Http\Controllers\HR\EmployeeController::class, 'index']);
    Route::post('/employees', [\App\Http\Controllers\HR\EmployeeController::class, 'store']);
    Route::get('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'show']);
    Route::put('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| REPORTS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|technician|supervisor|area_manager|admin'])->prefix('reports')->group(function () {
    Route::get('/', [\App\Http\Controllers\Report\ReportController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Report\ReportController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Report\ReportController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| SHOP MODULE
|--------------------------------------------------------------------------
*/
Route::prefix('shop')->group(function () {
    // Public product routes
    Route::get('/products', [\App\Http\Controllers\Shop\ProductController::class, 'index']);
    Route::get('/products/{id}', [\App\Http\Controllers\Shop\ProductController::class, 'show']);
    
    // Public category routes
    Route::get('/categories', [\App\Http\Controllers\Shop\CategoryController::class, 'index']);
    Route::get('/categories/{id}', [\App\Http\Controllers\Shop\CategoryController::class, 'show']);

    // Protected cart and order routes
    Route::middleware(['auth:sanctum', 'role:client|admin|supervisor|area_manager'])->group(function () {
        Route::post('/cart/add', [\App\Http\Controllers\Shop\CartController::class, 'add']);
        Route::get('/cart', [\App\Http\Controllers\Shop\CartController::class, 'view']);
        Route::delete('/cart/{id}', [\App\Http\Controllers\Shop\CartController::class, 'remove']);

        Route::post('/checkout', [\App\Http\Controllers\Shop\OrderController::class, 'checkout']);
        Route::get('/orders', [\App\Http\Controllers\Shop\OrderController::class, 'index']);
        Route::get('/orders/{id}', [\App\Http\Controllers\Shop\OrderController::class, 'show']);
        Route::post('/orders/{id}/mark-paid', [\App\Http\Controllers\Shop\OrderController::class, 'markPaid']);
        
        // Payment/Transaction routes
        Route::get('/payments', [\App\Http\Controllers\Shop\PaymentController::class, 'index']);
        Route::get('/payments/{id}', [\App\Http\Controllers\Shop\PaymentController::class, 'show']);
        Route::get('/transactions', [\App\Http\Controllers\Shop\PaymentController::class, 'index']); // Alias
        Route::get('/transactions/{id}', [\App\Http\Controllers\Shop\PaymentController::class, 'show']); // Alias
    });
});

/*
|--------------------------------------------------------------------------
| PRODUCTS (Frontend-compatible routes)
|--------------------------------------------------------------------------
*/
Route::prefix('products')->group(function () {
    Route::get('/', [\App\Http\Controllers\Shop\ProductController::class, 'index']);
    Route::get('/search', [\App\Http\Controllers\Shop\ProductController::class, 'index']); // Search via query param
    Route::get('/{id}', [\App\Http\Controllers\Shop\ProductController::class, 'show']);
    Route::get('/categories', [\App\Http\Controllers\Shop\ProductController::class, 'getCategories']);
    Route::get('/category/{id}', [\App\Http\Controllers\Shop\ProductController::class, 'getByCategory']);
});

/*
|--------------------------------------------------------------------------
| SERVICES (Frontend-compatible routes)
|--------------------------------------------------------------------------
*/
Route::prefix('services')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ServiceController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'show']);
    Route::get('/categories', [\App\Http\Controllers\Api\ServiceController::class, 'getCategories']);
    Route::get('/category/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'getByCategory']);
});

/*
|--------------------------------------------------------------------------
| ORDERS (Frontend-compatible routes)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|admin|supervisor|area_manager'])->prefix('orders')->group(function () {
    Route::get('/', [\App\Http\Controllers\Shop\OrderController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Shop\OrderController::class, 'show']);
    Route::post('/', [\App\Http\Controllers\Shop\OrderController::class, 'checkout']);
    Route::put('/{id}', [\App\Http\Controllers\Shop\OrderController::class, 'update']);
    Route::post('/{id}/cancel', [\App\Http\Controllers\Shop\OrderController::class, 'cancel']);
    Route::get('/{id}/track', [\App\Http\Controllers\Shop\OrderController::class, 'track']);
    Route::post('/{id}/rate', [\App\Http\Controllers\Shop\OrderController::class, 'rate']);
});

/*
|--------------------------------------------------------------------------
| USER PROFILE & SETTINGS (Frontend-compatible routes)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\Api\UserController::class, 'getProfile']);
    Route::put('/profile', [\App\Http\Controllers\Api\UserController::class, 'updateProfile']);
    Route::get('/addresses', [\App\Http\Controllers\Api\UserController::class, 'getAddresses']);
    Route::post('/addresses', [\App\Http\Controllers\Api\UserController::class, 'createAddress']);
    Route::put('/addresses/{id}', [\App\Http\Controllers\Api\UserController::class, 'updateAddress']);
    Route::delete('/addresses/{id}', [\App\Http\Controllers\Api\UserController::class, 'deleteAddress']);
    Route::get('/loyalty', [\App\Http\Controllers\Api\UserController::class, 'getLoyalty']);
    Route::get('/notifications', [\App\Http\Controllers\Api\UserController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\UserController::class, 'markNotificationAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\UserController::class, 'markAllNotificationsAsRead']);
});

/*
|--------------------------------------------------------------------------
| TIPS & NOTIFICATIONS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|admin|supervisor|area_manager|hr'])->group(function () {
    Route::get('/tips', [\App\Http\Controllers\Tips\TipsController::class, 'index']);
    Route::get('/tips/{id}', [\App\Http\Controllers\Tips\TipsController::class, 'show']);
    Route::get('/notifications', [\App\Http\Controllers\Notification\NotificationController::class, 'index']);
    Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Notification\NotificationController::class, 'markAsRead']);
});

/*
|--------------------------------------------------------------------------
| AREAS (AREA MANAGER)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:area_manager'])->prefix('areas')->group(function () {
    Route::get('/', [\App\Http\Controllers\AreaController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\AreaController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\AreaController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\AreaController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\AreaController::class, 'destroy']);
});

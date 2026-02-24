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

// Performance diagnostic endpoint - helps identify server slowness
Route::get('/debug/performance', function () {
    $start = microtime(true);
    $results = [];
    
    // 1. Basic PHP speed
    $phpStart = microtime(true);
    for ($i = 0; $i < 10000; $i++) { $x = $i * $i; }
    $results['php_loop_10k'] = round((microtime(true) - $phpStart) * 1000, 2) . 'ms';
    
    // 2. Database connection
    $dbStart = microtime(true);
    try {
        \DB::select('SELECT 1');
        $results['db_connection'] = round((microtime(true) - $dbStart) * 1000, 2) . 'ms';
    } catch (\Exception $e) {
        $results['db_connection'] = 'FAILED: ' . $e->getMessage();
    }
    
    // 3. Simple query
    $queryStart = microtime(true);
    try {
        $count = \App\Models\Product::count();
        $results['product_count_query'] = round((microtime(true) - $queryStart) * 1000, 2) . 'ms (' . $count . ' products)';
    } catch (\Exception $e) {
        $results['product_count_query'] = 'FAILED: ' . $e->getMessage();
    }
    
    // 4. Eager loading test
    $eagerStart = microtime(true);
    try {
        $products = \App\Models\Product::with(['category', 'images', 'primaryImage'])->limit(10)->get();
        $results['eager_load_10_products'] = round((microtime(true) - $eagerStart) * 1000, 2) . 'ms';
    } catch (\Exception $e) {
        $results['eager_load_10_products'] = 'FAILED: ' . $e->getMessage();
    }
    
    // 5. OPcache status
    $results['opcache_enabled'] = function_exists('opcache_get_status') && opcache_get_status() ? 'YES' : 'NO';
    
    // 6. PHP version
    $results['php_version'] = PHP_VERSION;
    
    // 7. Total time
    $results['total_time'] = round((microtime(true) - $start) * 1000, 2) . 'ms';
    
    return response()->json([
        'success' => true,
        'message' => 'Performance diagnostics',
        'data' => $results,
        'recommendation' => $results['opcache_enabled'] === 'NO' 
            ? 'Enable OPcache on your server for 3-10x faster PHP performance' 
            : 'OPcache is enabled - good!'
    ]);
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
    Route::put('/{id}/upload-photo', [\App\Http\Controllers\Visit\VisitController::class, 'uploadPhoto']);
    Route::post('/{id}/upload-photo', [\App\Http\Controllers\Visit\VisitController::class, 'uploadPhoto']);
    Route::delete('/{id}/photos/{photoId}', [\App\Http\Controllers\Visit\VisitController::class, 'deletePhoto']);
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
    Route::put('/visits/{id}/photos', [\App\Http\Controllers\Technician\TechnicianController::class, 'uploadPhoto']);
    Route::post('/visits/{id}/photos', [\App\Http\Controllers\Technician\TechnicianController::class, 'uploadPhoto']);
});

/*
|--------------------------------------------------------------------------
| TECHNICIAN DASHBOARD (dashboard, profile, tasks, jobs, availability, breaks, vacations, schedule)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:technician'])->prefix('technician')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'dashboard']);
    Route::get('/profile', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'profile']);
    Route::put('/profile', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateProfile']);
    Route::post('/profile', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateProfile']); // POST so form-data (file upload) is parsed by PHP
    Route::get('/tasks', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'tasks']);
    Route::get('/tasks/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'taskShow']);
    Route::put('/tasks/{id}/status', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'taskUpdateStatus']);
    Route::post('/tasks/{id}/accept', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'taskAccept']);
    Route::post('/tasks/{id}/reject', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'taskReject']);
    Route::get('/jobs', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'jobs']);
    Route::get('/jobs/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'jobShow']);
    Route::get('/payout-summary', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'payoutSummary']);
    Route::get('/payouts', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'payouts']);
    Route::get('/settings/payout', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'payoutSettings']);
    Route::put('/settings/payout', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updatePayoutSettings']);
    Route::get('/bank-accounts', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'bankAccounts']);
    Route::post('/bank-accounts', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'bankAccountStore']);
    Route::put('/bank-accounts/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'bankAccountUpdate']);
    Route::delete('/bank-accounts/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'bankAccountDestroy']);
    Route::get('/availability', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'availability']);
    Route::put('/availability', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateAvailability']);
    Route::get('/breaks', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'breaks']);
    Route::post('/breaks', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'breakStore']);
    Route::put('/breaks/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'breakUpdate']);
    Route::delete('/breaks/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'breakDestroy']);
    Route::get('/vacations', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'vacations']);
    Route::post('/vacations', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'vacationStore']);
    Route::put('/vacations/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'vacationUpdate']);
    Route::delete('/vacations/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'vacationDestroy']);
    Route::get('/schedule', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'schedule']);
});

/*
|--------------------------------------------------------------------------
| SUPPORT (FAQs + submit ticket – shared for all authenticated roles)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|technician|supervisor|area_manager|hr|admin'])->prefix('support')->group(function () {
    Route::get('/help-center', [\App\Http\Controllers\Api\SupportController::class, 'helpCenter']);
    Route::get('/faqs', [\App\Http\Controllers\Api\SupportController::class, 'faqs']);
    Route::post('/tickets', [\App\Http\Controllers\Api\SupportController::class, 'storeTicket']);
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
    // User Management - Moved to dedicated admin/users group below to avoid route conflicts
    // Routes are now defined at lines 297-302 under 'admin/users' prefix

    // Roles
    Route::get('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'index']);
    Route::post('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'store']);

    // Categories Management (add / list / show / update / delete). Update: PUT or POST (use POST with multipart for image).
    Route::get('/categories', [\App\Http\Controllers\CategoryController::class, 'index']);
    Route::post('/categories', [\App\Http\Controllers\CategoryController::class, 'store']);
    Route::get('/categories/{id}', [\App\Http\Controllers\CategoryController::class, 'show']);
    Route::put('/categories/{id}', [\App\Http\Controllers\CategoryController::class, 'update']);
    Route::post('/categories/{id}', [\App\Http\Controllers\CategoryController::class, 'update']);
    Route::post('/categories/{id}/toggle-status', [\App\Http\Controllers\CategoryController::class, 'toggleStatus']);
    Route::delete('/categories/{id}', [\App\Http\Controllers\CategoryController::class, 'destroy']);

    // Services API (separate CRUD; services = categories). Same data, routes under /api/admin/services.
    Route::get('/services', [\App\Http\Controllers\Api\Admin\ServiceController::class, 'index']);
    Route::post('/services', [\App\Http\Controllers\Api\Admin\ServiceController::class, 'store']);
    Route::get('/services/{id}', [\App\Http\Controllers\Api\Admin\ServiceController::class, 'show']);
    Route::put('/services/{id}', [\App\Http\Controllers\Api\Admin\ServiceController::class, 'update']);
    Route::post('/services/{id}', [\App\Http\Controllers\Api\Admin\ServiceController::class, 'update']);
    Route::post('/services/{id}/toggle-status', [\App\Http\Controllers\Api\Admin\ServiceController::class, 'toggleStatus']);
    Route::delete('/services/{id}', [\App\Http\Controllers\Api\Admin\ServiceController::class, 'destroy']);

    // Products Management
    Route::get('/products', [\App\Http\Controllers\Admin\ProductController::class, 'index']);
    Route::post('/products', [\App\Http\Controllers\Admin\ProductController::class, 'store']);
    // Bulk operations must come before {id} route to avoid route conflicts
    Route::post('/products/bulk-delete', [\App\Http\Controllers\Admin\ProductController::class, 'bulkDelete']);
    Route::post('/products/bulk-update-status', [\App\Http\Controllers\Admin\ProductController::class, 'bulkUpdateStatus']);
    Route::post('/products/bulk-update-stock', [\App\Http\Controllers\Admin\ProductController::class, 'bulkUpdateStock']);
    // Individual product routes
    Route::get('/products/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'show']);
    Route::put('/products/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'update']);
    Route::delete('/products/{id}', [\App\Http\Controllers\Admin\ProductController::class, 'destroy']);
    Route::post('/products/{id}/toggle-status', [\App\Http\Controllers\Admin\ProductController::class, 'toggleStatus']);
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
    // Shop settings: GET public, PUT requires client auth
    Route::get('/settings', [\App\Http\Controllers\Shop\ShopSettingsController::class, 'index']);
    Route::middleware(['auth:sanctum', 'role:client'])->put('/settings', [\App\Http\Controllers\Shop\ShopSettingsController::class, 'update']);

    // Public product routes (single canonical API for public products)
    Route::get('/products/featured', [\App\Http\Controllers\Shop\ProductController::class, 'featured']);
    Route::get('/products/categories', [\App\Http\Controllers\Shop\ProductController::class, 'getCategories']);
    Route::get('/products/category/{id}', [\App\Http\Controllers\Shop\ProductController::class, 'getByCategory']);
    Route::get('/products', [\App\Http\Controllers\Shop\ProductController::class, 'index']);
    Route::get('/products/{id}', [\App\Http\Controllers\Shop\ProductController::class, 'show']);

    // Public category routes
    Route::get('/categories', [\App\Http\Controllers\Shop\CategoryController::class, 'index']);
    Route::get('/categories/{id}', [\App\Http\Controllers\Shop\CategoryController::class, 'show']);

    // Checkout.com: create payment session (optional auth = guest or logged-in). Webhook = no auth.
    Route::middleware('optional.sanctum')->post('/create-payment-session', [\App\Http\Controllers\Shop\CheckoutComController::class, 'createPaymentSession']);
    Route::post('/webhooks/checkout-com', [\App\Http\Controllers\Shop\CheckoutComController::class, 'webhook']);

    // Legacy: use POST /create-payment-session instead
    Route::middleware('optional.sanctum')->post('/checkout', function () {
        return response()->json([
            'success' => false,
            'message' => 'Use POST /api/shop/create-payment-session for payment. PayPal and COD are removed.',
        ], 400);
    });

    // Guest order lookup (no auth): order_number + email
    Route::get('/orders/guest', [\App\Http\Controllers\Shop\OrderController::class, 'guestShow']);
    Route::get('/orders/guest/track', [\App\Http\Controllers\Shop\OrderController::class, 'guestTrack']);

    // Protected cart and order routes
    Route::middleware(['auth:sanctum', 'role:client|admin|supervisor|area_manager'])->group(function () {
        Route::post('/cart/add', [\App\Http\Controllers\Shop\CartController::class, 'add']);
        Route::get('/cart', [\App\Http\Controllers\Shop\CartController::class, 'view']);
        Route::get('/order-summary', [\App\Http\Controllers\Shop\CartController::class, 'orderSummary']);
        Route::put('/cart/{id}', [\App\Http\Controllers\Shop\CartController::class, 'update']);
        Route::patch('/cart/{id}', [\App\Http\Controllers\Shop\CartController::class, 'update']);
        Route::delete('/cart/{id}', [\App\Http\Controllers\Shop\CartController::class, 'remove']);

        Route::get('/checkout/payment-methods', [\App\Http\Controllers\Shop\CheckoutController::class, 'paymentMethods']);
        Route::get('/checkout/review', [\App\Http\Controllers\Shop\CheckoutController::class, 'review']);
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
| SERVICES (Frontend-compatible routes)
|--------------------------------------------------------------------------
*/
Route::prefix('services')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ServiceController::class, 'index']);
    Route::get('/categories', [\App\Http\Controllers\Api\ServiceController::class, 'getCategories']);
    Route::get('/category/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'getByCategory']);
    Route::get('/products', [\App\Http\Controllers\Api\ServiceController::class, 'allProductsOfService']);
    Route::get('/{id}/products', [\App\Http\Controllers\Api\ServiceController::class, 'productsByService']);
    Route::get('/{id}', [\App\Http\Controllers\Api\ServiceController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| MAINTENANCE PHOTOS (Client app – home screen "Maintenance Photos" section)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|admin'])->prefix('maintenance-photos')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\MaintenancePhotosController::class, 'index']);
    Route::get('/visit/{visitId}', [\App\Http\Controllers\Api\MaintenancePhotosController::class, 'byVisit']);
});

/*
|--------------------------------------------------------------------------
| ORDERS (Frontend-compatible routes)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|admin|supervisor|area_manager'])->prefix('orders')->group(function () {
    Route::get('/', [\App\Http\Controllers\Shop\OrderController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Shop\OrderController::class, 'show']);
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
    Route::match(['put', 'post', 'patch'], '/profile', [\App\Http\Controllers\Api\UserController::class, 'updateProfile']);
    Route::get('/addresses', [\App\Http\Controllers\Api\UserController::class, 'getAddresses']);
    Route::post('/addresses', [\App\Http\Controllers\Api\UserController::class, 'createAddress']);
    Route::put('/addresses/{id}', [\App\Http\Controllers\Api\UserController::class, 'updateAddress']);
    Route::patch('/addresses/{id}', [\App\Http\Controllers\Api\UserController::class, 'updateAddress']);
    Route::post('/addresses/{id}', [\App\Http\Controllers\Api\UserController::class, 'updateAddress']); // POST for form-data
    Route::delete('/addresses/{id}', [\App\Http\Controllers\Api\UserController::class, 'deleteAddress']);
    Route::get('/payment-methods', [\App\Http\Controllers\Api\UserController::class, 'getPaymentMethods']);
    Route::get('/notifications', [\App\Http\Controllers\Api\UserController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\UserController::class, 'markNotificationAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\UserController::class, 'markAllNotificationsAsRead']);
});

/*
|--------------------------------------------------------------------------
| CLIENT DASHBOARD SETTINGS (for client app – title, subtitle, section toggles)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client'])->prefix('client')->group(function () {
    Route::get('/settings/dashboard', [\App\Http\Controllers\Api\ClientSettingsController::class, 'dashboard']);
    Route::get('/settings/sections', [\App\Http\Controllers\Api\ClientSettingsController::class, 'sections']);
    Route::get('/memberships', [\App\Http\Controllers\Api\ClientSettingsController::class, 'memberships']);
});

/*
|--------------------------------------------------------------------------
| BANNERS (Public - for customer home screen)
|--------------------------------------------------------------------------
*/
Route::get('/banners', [\App\Http\Controllers\Api\BannerController::class, 'index']);

/*
|--------------------------------------------------------------------------
| EXCLUSIVE OFFERS (Public - for customer home screen "Exclusive Offers" section)
|--------------------------------------------------------------------------
*/
Route::prefix('exclusive-offers')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ExclusiveOfferController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\ExclusiveOfferController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| PACKAGES (Public - for customer home page: Combined, Fruit, Vegetable)
|--------------------------------------------------------------------------
*/
Route::get('/packages', [\App\Http\Controllers\Api\PackageController::class, 'index']);
Route::get('/shop/packages', [\App\Http\Controllers\Api\PackageController::class, 'index']);

/*
|--------------------------------------------------------------------------
| ADMIN BANNERS (upload, reorder, enable/disable, set link/action)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/banners')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Admin\BannerController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\Admin\BannerController::class, 'store']);
    Route::post('/update-order', [\App\Http\Controllers\Api\Admin\BannerController::class, 'updateOrder']);
    Route::get('/{id}', [\App\Http\Controllers\Api\Admin\BannerController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\Admin\BannerController::class, 'update']);
    Route::post('/{id}', [\App\Http\Controllers\Api\Admin\BannerController::class, 'update']); // POST for multipart (image replace)
    Route::post('/{id}/toggle-status', [\App\Http\Controllers\Api\Admin\BannerController::class, 'toggleStatus']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\Admin\BannerController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| ADMIN PACKAGES (set price, image, view order count)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/packages')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Admin\PackageController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\Admin\PackageController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Api\Admin\PackageController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\Admin\PackageController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\Admin\PackageController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| ADMIN EXCLUSIVE OFFERS (create, update, delete – for home screen offers)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/exclusive-offers')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Admin\ExclusiveOfferController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\Admin\ExclusiveOfferController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Api\Admin\ExclusiveOfferController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\Admin\ExclusiveOfferController::class, 'update']);
    Route::post('/{id}', [\App\Http\Controllers\Api\Admin\ExclusiveOfferController::class, 'update']); // POST for multipart image
    Route::delete('/{id}', [\App\Http\Controllers\Api\Admin\ExclusiveOfferController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| ADMIN ORDERS EXPORT & SEND TO SUPPLIER
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/orders')->group(function () {
    Route::get('/export', [\App\Http\Controllers\Api\Admin\OrderExportController::class, 'export']);
    Route::post('/send-to-supplier', [\App\Http\Controllers\Api\Admin\OrderExportController::class, 'sendToSupplier']);
});

/*
|--------------------------------------------------------------------------
| ADMIN DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/dashboard')->group(function () {
    Route::get('/statistics', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'statistics']);
    Route::get('/recent-activities', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'recentActivities']);
    Route::get('/quick-overview', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'quickOverview']);
    Route::get('/profile', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'profile']);
    Route::get('/top-selling-products', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'topSellingProducts']);
});

/*
|--------------------------------------------------------------------------
| ADMIN REPORTS MANAGEMENT
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/reports')->group(function () {
    Route::get('/statistics', [\App\Http\Controllers\Admin\AdminReportController::class, 'statistics']);
    Route::post('/generate', [\App\Http\Controllers\Admin\AdminReportController::class, 'generate']);
    Route::post('/schedule', [\App\Http\Controllers\Admin\AdminReportController::class, 'schedule']);
    Route::get('/', [\App\Http\Controllers\Admin\AdminReportController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Admin\AdminReportController::class, 'show']);
    Route::get('/{id}/download', [\App\Http\Controllers\Admin\AdminReportController::class, 'download'])->name('api.admin.reports.download');
    Route::delete('/{id}/cancel', [\App\Http\Controllers\Admin\AdminReportController::class, 'cancel']);
    Route::post('/{id}/share', [\App\Http\Controllers\Admin\AdminReportController::class, 'share']);
    Route::delete('/{id}', [\App\Http\Controllers\Admin\AdminReportController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| ADMIN USERS MANAGEMENT
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/users')->group(function () {
    // Statistics route MUST be before {id} route to avoid route conflict
    Route::get('statistics', [\App\Http\Controllers\Admin\UserController::class, 'statistics'])->name('api.admin.users.statistics');
    Route::get('/', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('api.admin.users.index');
    Route::post('/', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('api.admin.users.store');
    // {id} route must be LAST to avoid catching 'statistics' as an ID
    Route::get('{id}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('api.admin.users.show');
    Route::put('{id}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('api.admin.users.update');
    Route::delete('{id}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('api.admin.users.destroy');
});

/*
|--------------------------------------------------------------------------
| ADMIN SETTINGS (MOBILE / REACT NATIVE)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/settings')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'index']);
    Route::get('/system', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'getSystem']);
    Route::put('/system', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'updateSystem']);
    Route::get('/theme', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'getTheme']);
    Route::put('/theme', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'updateTheme']);
    Route::get('/language', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'getLanguage']);
    Route::put('/language', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'updateLanguage']);
    Route::get('/payment', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'getPayment']);
    Route::put('/payment', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'updatePayment']);
    Route::get('/shop', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'getShop']);
    Route::put('/shop', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'updateShop']);
    Route::get('/legal', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'getLegal']);
    Route::post('/export-data', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'exportData']);
    Route::get('/debug-logs', [\App\Http\Controllers\Admin\AdminSettingsApiController::class, 'debugLogs']);
});

/*
|--------------------------------------------------------------------------
| TIPS & NOTIFICATIONS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|admin|supervisor|area_manager|hr'])->group(function () {
    Route::get('/tips', [\App\Http\Controllers\Tips\TipsController::class, 'index']);
    Route::post('/tips', [\App\Http\Controllers\Tips\TipsController::class, 'store']);
    Route::get('/tips/{id}', [\App\Http\Controllers\Tips\TipsController::class, 'show']);
    Route::put('/tips/{id}', [\App\Http\Controllers\Tips\TipsController::class, 'update']);
    Route::delete('/tips/{id}', [\App\Http\Controllers\Tips\TipsController::class, 'destroy']);
    Route::get('/notifications', [\App\Http\Controllers\Notification\NotificationController::class, 'index']);
    Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Notification\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Notification\NotificationController::class, 'markAllAsRead']);
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

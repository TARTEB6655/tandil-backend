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
| AUTHENTICATION (REGISTER, LOGIN, PROFILE, LOGOUT)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {

    // Public routes
    Route::post('/register', [\App\Http\Controllers\Auth\AuthController::class, 'register']);
    Route::post('/login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout']);
        Route::get('/profile', [\App\Http\Controllers\Auth\AuthController::class, 'profile']);

        // Payments
        Route::post('payments/paypal/create', [\App\Http\Controllers\PaymentController::class, 'createPaypalOrder']);
        Route::post('payments/paypal/webhook', [\App\Http\Controllers\PaymentController::class, 'paypalWebhook']);

        // Technician routes (only for role technician)
        Route::middleware('role:technician')->group(function () {
            Route::get('tech/visits', [\App\Http\Controllers\Technician\TechnicianController::class, 'assigned']);
            Route::post('tech/visits/{id}/accept', [\App\Http\Controllers\Technician\TechnicianController::class, 'accept']);
            Route::post('tech/visits/{id}/start', [\App\Http\Controllers\Technician\TechnicianController::class, 'start']);
            Route::post('tech/visits/{id}/complete', [\App\Http\Controllers\Technician\TechnicianController::class, 'complete']);
            Route::post('tech/visits/{id}/photos', [\App\Http\Controllers\Technician\TechnicianController::class, 'uploadPhoto']);
        });

        // Supervisor routes (only for role supervisor)
        Route::middleware('role:supervisor')->group(function () {
            // Existing routes
            Route::get('supervisor/visits/{id}', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'reviewVisit']);
            Route::post('supervisor/visits/{id}/recommend', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'recommendProducts']);
            Route::post('supervisor/visits/{id}/finalize', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'finalizeReport']);

            // New routes for extended functionality
            Route::get('supervisor/visits', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'listVisits']);
            Route::get('supervisor/areas', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'listAreas']);
            Route::post('supervisor/visits/{id}/status', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'updateVisitStatus']);
            Route::get('supervisor/complaints', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'listComplaints']);
            Route::post('supervisor/complaints/{id}/escalate', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'escalateComplaint']);
        });

        // Shop / Orders (roles allowed to shop: client, admin, supervisor, area_manager)
        Route::middleware('role:client|admin|supervisor|area_manager')->group(function () {
            Route::post('shop/checkout', [\App\Http\Controllers\Shop\OrderController::class, 'checkout']);
            Route::post('shop/orders/{id}/mark-paid', [\App\Http\Controllers\Shop\OrderController::class, 'markPaid']);
        });

        /*
         |--------------------------------------------------------------------------
         | Complaints routes (authenticated users)
         |--------------------------------------------------------------------------
         */
        Route::prefix('complaints')->group(function () {
            Route::get('/', [\App\Http\Controllers\ComplaintController::class, 'index']);         // list complaints
            Route::post('/', [\App\Http\Controllers\ComplaintController::class, 'store']);        // create complaint
            Route::get('/{id}', [\App\Http\Controllers\ComplaintController::class, 'show']);      // view complaint
            Route::put('/{id}', [\App\Http\Controllers\ComplaintController::class, 'update']);    // update complaint (status, notes)
            Route::delete('/{id}', [\App\Http\Controllers\ComplaintController::class, 'destroy']); // delete complaint (admin only)
        });
    });
});

/*
|--------------------------------------------------------------------------
| ADMIN PANEL (USERS, ROLES, EMPLOYEES)
|--------------------------------------------------------------------------
| Only admin users can access these routes.
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // USER MANAGEMENT
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index']);
    Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store']);
    Route::get('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'show']);
    Route::put('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'update']);
    Route::delete('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'destroy']);

    // ROLE MANAGEMENT
    Route::get('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'index']);
    Route::post('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'store']);

    // EMPLOYEE MANAGEMENT (HR)
    Route::prefix('hr')->group(function () {
        Route::get('/employees', [\App\Http\Controllers\HR\EmployeeController::class, 'index']);
        Route::post('/employees', [\App\Http\Controllers\HR\EmployeeController::class, 'store']);
        Route::get('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'show']);
        Route::put('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| SUBSCRIPTIONS (CLIENT / ADMIN)
|--------------------------------------------------------------------------
*/
// Public plans endpoint (no auth required)
Route::get('/subscriptions/plans', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'plans']);

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
| VISITS (TECHNICIAN, SUPERVISOR, AREA_MANAGER)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:technician|supervisor|area_manager'])->prefix('visits')->group(function () {
    Route::get('/', [\App\Http\Controllers\Visit\VisitController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Visit\VisitController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Visit\VisitController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Visit\VisitController::class, 'update']);

    // Upload visit photos
    Route::post('/{id}/upload-photo', [\App\Http\Controllers\Visit\VisitController::class, 'uploadPhoto']);
});

// Technician, Supervisor and Shop protected routes (top-level paths, not under /auth)
Route::middleware('auth:sanctum')->group(function () {

    // Technician
    Route::middleware('role:technician')->group(function () {
        Route::get('tech/visits', [\App\Http\Controllers\Technician\TechnicianController::class, 'assigned']);
        Route::post('tech/visits/{id}/accept', [\App\Http\Controllers\Technician\TechnicianController::class, 'accept']);
        Route::post('tech/visits/{id}/start', [\App\Http\Controllers\Technician\TechnicianController::class, 'start']);
        Route::post('tech/visits/{id}/complete', [\App\Http\Controllers\Technician\TechnicianController::class, 'complete']);
        Route::post('tech/visits/{id}/photos', [\App\Http\Controllers\Technician\TechnicianController::class, 'uploadPhoto']);
    });

    // Supervisor
    Route::middleware('role:supervisor')->group(function () {
        // Already declared inside /auth prefix; can optionally remove redundancy here if needed
        Route::get('supervisor/visits/{id}', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'reviewVisit']);
        Route::post('supervisor/visits/{id}/recommend', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'recommendProducts']);
        Route::post('supervisor/visits/{id}/finalize', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'finalizeReport']);

        // New extended routes
        Route::get('supervisor/visits', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'listVisits']);
        Route::get('supervisor/areas', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'listAreas']);
        Route::post('supervisor/visits/{id}/status', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'updateVisitStatus']);
        Route::get('supervisor/complaints', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'listComplaints']);
        Route::post('supervisor/complaints/{id}/escalate', [\App\Http\Controllers\Supervisor\SupervisorController::class, 'escalateComplaint']);
    });

    // Shop / Orders
    Route::middleware('role:client|admin|supervisor|area_manager')->group(function () {
        Route::post('shop/checkout', [\App\Http\Controllers\Shop\OrderController::class, 'checkout']);
        Route::post('shop/orders/{id}/mark-paid', [\App\Http\Controllers\Shop\OrderController::class, 'markPaid']);
    });
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
| SHOP MODULE (PRODUCTS, CART, ORDERS)
|--------------------------------------------------------------------------
*/
Route::prefix('shop')->group(function () {
    // Public product catalog
    Route::get('/products', [\App\Http\Controllers\Shop\ProductController::class, 'index']);
    Route::get('/products/{id}', [\App\Http\Controllers\Shop\ProductController::class, 'show']);

    // Auth protected SHOP operations
    Route::middleware(['auth:sanctum', 'role:client|admin|supervisor|area_manager'])->group(function () {
        // Cart
        Route::post('/cart/add', [\App\Http\Controllers\Shop\CartController::class, 'add']);
        Route::get('/cart', [\App\Http\Controllers\Shop\CartController::class, 'view']);
        Route::delete('/cart/{id}', [\App\Http\Controllers\Shop\CartController::class, 'remove']);

        // Orders
        Route::post('/checkout', [\App\Http\Controllers\Shop\OrderController::class, 'checkout']);
        Route::get('/orders', [\App\Http\Controllers\Shop\OrderController::class, 'index']);
        Route::get('/orders/{id}', [\App\Http\Controllers\Shop\OrderController::class, 'show']);
    });
});

/*
|--------------------------------------------------------------------------
| TIPS & NOTIFICATIONS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|admin|supervisor|area_manager|hr'])->group(function () {
    Route::get('/tips', [\App\Http\Controllers\Tips\TipsController::class, 'index']);
    Route::get('/notifications', [\App\Http\Controllers\Notification\NotificationController::class, 'index']);
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

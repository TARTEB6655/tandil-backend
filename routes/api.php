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
    });
});


/*
|--------------------------------------------------------------------------
| ADMIN PANEL (USERS, ROLES, EMPLOYEES)
|--------------------------------------------------------------------------
| Only admin users can access these routes.
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    /*
    |-----------------------
    | USER MANAGEMENT
    |-----------------------
    */
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index']);
    Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store']);
    Route::get('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'show']);
    Route::put('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'update']);
    Route::delete('/users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'destroy']);

    /*
    |-----------------------
    | ROLE MANAGEMENT
    |-----------------------
    */
    Route::get('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'index']);
    Route::post('/roles', [\App\Http\Controllers\Admin\RoleController::class, 'store']);

    /*
    |-----------------------
    | EMPLOYEE MANAGEMENT (HR)
    |-----------------------
    */
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
Route::middleware('auth:sanctum')->prefix('subscriptions')->group(function () {

    Route::get('/', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Subscription\SubscriptionController::class, 'destroy']);
});


/*
|--------------------------------------------------------------------------
| VISITS (TECHNICIAN)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('visits')->group(function () {

    Route::get('/', [\App\Http\Controllers\Visit\VisitController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Visit\VisitController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Visit\VisitController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Visit\VisitController::class, 'update']);

    // Upload visit photos
    Route::post('/{id}/upload-photo', [\App\Http\Controllers\Visit\VisitController::class, 'uploadPhoto']);
});


/*
|--------------------------------------------------------------------------
| REPORTS
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('reports')->group(function () {

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
    Route::middleware('auth:sanctum')->group(function () {

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
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tips', [\App\Http\Controllers\Tips\TipsController::class, 'index']);
    Route::get('/notifications', [\App\Http\Controllers\Notification\NotificationController::class, 'index']);
});

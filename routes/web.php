<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;

// Dashboard Controllers for roles
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\TipController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\VisitController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\HrController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BannerController;

use App\Http\Controllers\Supervisor\SupervisorDashboardController;
use App\Http\Controllers\Technician\TechnicianDashboardController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\AreaManager\AreaManagerDashboardController;
use App\Http\Controllers\HR\HrDashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root '/' to login or dashboard redirect
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard.redirect');
    }
    return redirect()->route('login');
});

// Alias route 'dashboard' that redirects to 'dashboard.redirect' (fix missing route errors)
Route::middleware('auth')->get('/dashboard', function () {
    return redirect()->route('dashboard.redirect');
})->name('dashboard');

// Role-based dashboard redirect route
Route::middleware('auth')->get('/dashboard-redirect', function () {
    $user = auth()->user();
    switch ($user->role) {
        case 'admin':
            return redirect()->route('admin.dashboard');
        case 'supervisor':
            return redirect()->route('supervisor.dashboard');
        case 'technician':
            return redirect()->route('technician.dashboard');
        case 'client':
            return redirect()->route('client.dashboard');
        case 'area_manager':
            return redirect()->route('areamanager.dashboard');
        case 'hr':
            return redirect()->route('hr.dashboard');
        default:
            auth()->logout();
            return redirect()->route('login');
    }
})->name('dashboard.redirect');

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Resource routes
        Route::resource('users', UserController::class);
        Route::post('users/{id}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        
        Route::resource('roles', RoleController::class);
        Route::resource('products', ProductController::class);
        Route::resource('subscriptions', SubscriptionController::class);
        Route::post('subscriptions/{id}/extend', [SubscriptionController::class, 'extend'])->name('subscriptions.extend');
        Route::post('subscriptions/{id}/activate', [SubscriptionController::class, 'activate'])->name('subscriptions.activate');
        Route::post('subscriptions/{id}/deactivate', [SubscriptionController::class, 'deactivate'])->name('subscriptions.deactivate');
        
        Route::resource('subscription-plans', SubscriptionPlanController::class)->except(['create', 'store', 'destroy']);
        
        Route::resource('visits', VisitController::class);
        Route::post('visits/{id}/assign-technician', [VisitController::class, 'assignTechnician'])->name('visits.assign-technician');
        Route::post('visits/{id}/assign-supervisor', [VisitController::class, 'assignSupervisor'])->name('visits.assign-supervisor');
        
        Route::resource('reports', ReportController::class);
        Route::post('reports/{id}/approve', [ReportController::class, 'approve'])->name('reports.approve');
        Route::post('reports/{id}/send-to-client', [ReportController::class, 'sendToClient'])->name('reports.send-to-client');
        
        Route::resource('areas', AreaController::class);
        Route::resource('orders', OrderController::class)->only(['index', 'show']);
        Route::post('orders/{id}/update-status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('orders/{id}/mark-paid', [OrderController::class, 'markPaid'])->name('orders.mark-paid');
        
        Route::resource('complaints', ComplaintController::class)->only(['index', 'show']);
        Route::post('complaints/{id}/update-status', [ComplaintController::class, 'updateStatus'])->name('complaints.update-status');
        Route::post('complaints/{id}/assign-supervisor', [ComplaintController::class, 'assignSupervisor'])->name('complaints.assign-supervisor');
        
        Route::resource('hr', HrController::class);
        Route::resource('settings', SettingController::class)->only(['index']);
        Route::post('settings/app', [SettingController::class, 'updateAppSettings'])->name('settings.app');
        Route::post('settings/payment', [SettingController::class, 'updatePaymentSettings'])->name('settings.payment');
        Route::post('settings/notification', [SettingController::class, 'updateNotificationSettings'])->name('settings.notification');
        Route::post('settings/email', [SettingController::class, 'updateEmailSettings'])->name('settings.email');
        
        Route::resource('audit-logs', AuditLogController::class)->only(['index', 'show']);
        Route::resource('banners', BannerController::class);
        Route::resource('tips', TipController::class);
    });

// Supervisor routes
Route::middleware(['auth', 'role:supervisor'])
    ->prefix('supervisor')
    ->name('supervisor.')
    ->group(function () {
        Route::get('/dashboard', [SupervisorDashboardController::class, 'index'])->name('dashboard');
        // add more supervisor routes here
    });

// Technician routes
Route::middleware(['auth', 'role:technician'])
    ->prefix('technician')
    ->name('technician.')
    ->group(function () {
        Route::get('/dashboard', [TechnicianDashboardController::class, 'index'])->name('dashboard');
        // add more technician routes here
    });

// Client routes
Route::middleware(['auth', 'role:client'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
        // add more client routes here
    });

// Area Manager routes
Route::middleware(['auth', 'role:area_manager'])
    ->prefix('areamanager')
    ->name('areamanager.')
    ->group(function () {
        Route::get('/dashboard', [AreaManagerDashboardController::class, 'index'])->name('dashboard');
        // add more area manager routes here
    });

// HR routes
Route::middleware(['auth', 'role:hr'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {
        Route::get('/dashboard', [HrDashboardController::class, 'index'])->name('dashboard');
        // add more HR routes here
    });

// Breeze auth routes (login/logout/password/reset)
require __DIR__.'/auth.php';

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
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Admin\PaymentController;

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
        Route::get('products/import', [ProductController::class, 'showImport'])->name('products.import');
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import.store');
        Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
        Route::post('products/bulk-delete', [ProductController::class, 'bulkDelete'])->name('products.bulk-delete');
        Route::post('products/bulk-update-stock', [ProductController::class, 'bulkUpdateStock'])->name('products.bulk-update-stock');
        Route::post('products/{id}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
        
        Route::resource('categories', CategoryController::class);
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
        Route::post('orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{id}/refund', [OrderController::class, 'refund'])->name('orders.refund');
        
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/gateway/{gateway}', [PaymentController::class, 'updateGateway'])->name('payments.update-gateway');
        Route::get('payments/transaction/{id}', [PaymentController::class, 'showTransaction'])->name('payments.transaction');
        
        Route::resource('complaints', ComplaintController::class)->only(['index', 'show']);
        Route::post('complaints/{id}/update-status', [ComplaintController::class, 'updateStatus'])->name('complaints.update-status');
        Route::post('complaints/{id}/assign-supervisor', [ComplaintController::class, 'assignSupervisor'])->name('complaints.assign-supervisor');
        
        Route::resource('hr', HrController::class);
        Route::resource('settings', SettingController::class)->only(['index']);
        Route::post('settings/app', [SettingController::class, 'updateAppSettings'])->name('settings.app');
        Route::post('settings/payment', [SettingController::class, 'updatePaymentSettings'])->name('settings.payment');
        Route::post('settings/notification', [SettingController::class, 'updateNotificationSettings'])->name('settings.notification');
        Route::post('settings/email', [SettingController::class, 'updateEmailSettings'])->name('settings.email');
        Route::post('settings/social', [SettingController::class, 'updateSocialSettings'])->name('settings.social');
        Route::post('settings/contact', [SettingController::class, 'updateContactSettings'])->name('settings.contact');
        Route::get('settings/email-templates', [SettingController::class, 'emailTemplates'])->name('settings.email-templates');
        Route::post('settings/email-templates/{id}', [SettingController::class, 'updateEmailTemplate'])->name('settings.email-template.update');
        Route::post('settings/security', [SettingController::class, 'updateSecuritySettings'])->name('settings.security');
        Route::post('settings/integrations', [SettingController::class, 'updateIntegrationsSettings'])->name('settings.integrations');
        
        Route::resource('audit-logs', AuditLogController::class)->only(['index', 'show']);
        Route::resource('banners', BannerController::class);
        Route::resource('tips', TipController::class);
        Route::post('tips/{id}/toggle-status', [TipController::class, 'toggleStatus'])->name('tips.toggle-status');
        
        // Notifications routes
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/create', [NotificationController::class, 'create'])->name('notifications.create');
        Route::post('notifications/send', [NotificationController::class, 'send'])->name('notifications.send');
        Route::post('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::get('notifications/unread-count', [NotificationController::class, 'getUnreadCount'])->name('notifications.unread-count');
        
        // Analytics API routes
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('revenue', [\App\Http\Controllers\Admin\AnalyticsController::class, 'revenue'])->name('revenue');
            Route::get('visits', [\App\Http\Controllers\Admin\AnalyticsController::class, 'visits'])->name('visits');
            Route::get('subscriptions', [\App\Http\Controllers\Admin\AnalyticsController::class, 'subscriptions'])->name('subscriptions');
            Route::get('visit-status', [\App\Http\Controllers\Admin\AnalyticsController::class, 'visitStatus'])->name('visit-status');
            Route::get('technician-performance', [\App\Http\Controllers\Admin\AnalyticsController::class, 'technicianPerformance'])->name('technician-performance');
            Route::get('area-performance', [\App\Http\Controllers\Admin\AnalyticsController::class, 'areaPerformance'])->name('area-performance');
            Route::get('order-status', [\App\Http\Controllers\Admin\AnalyticsController::class, 'orderStatus'])->name('order-status');
            Route::get('payment-status', [\App\Http\Controllers\Admin\AnalyticsController::class, 'paymentStatus'])->name('payment-status');
        });
    });

// Supervisor routes
Route::middleware(['auth', 'role:supervisor'])
    ->prefix('supervisor')
    ->name('supervisor.')
    ->group(function () {
        Route::get('/dashboard', [SupervisorDashboardController::class, 'index'])->name('dashboard');
        
        // Visits
        Route::get('/visits', [\App\Http\Controllers\Supervisor\VisitController::class, 'index'])->name('visits.index');
        Route::get('/visits/{id}', [\App\Http\Controllers\Supervisor\VisitController::class, 'show'])->name('visits.show');
        Route::post('/visits/{id}/approve', [\App\Http\Controllers\Supervisor\VisitController::class, 'approve'])->name('visits.approve');
        Route::post('/visits/{id}/reject', [\App\Http\Controllers\Supervisor\VisitController::class, 'reject'])->name('visits.reject');
        
        // Reports
        Route::get('/reports', [\App\Http\Controllers\Supervisor\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{id}', [\App\Http\Controllers\Supervisor\ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{id}/review', [\App\Http\Controllers\Supervisor\ReportController::class, 'review'])->name('reports.review');
        Route::post('/reports/{id}/finalize', [\App\Http\Controllers\Supervisor\ReportController::class, 'finalize'])->name('reports.finalize');
        
        // Complaints
        Route::get('/complaints', [\App\Http\Controllers\Supervisor\ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/{id}', [\App\Http\Controllers\Supervisor\ComplaintController::class, 'show'])->name('complaints.show');
        Route::post('/complaints/{id}/update', [\App\Http\Controllers\Supervisor\ComplaintController::class, 'update'])->name('complaints.update');
        
        // Areas
        Route::get('/areas', [\App\Http\Controllers\Supervisor\AreaController::class, 'index'])->name('areas.index');
        
        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Supervisor\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Supervisor\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Supervisor\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    });

// Technician routes
Route::middleware(['auth', 'role:technician'])
    ->prefix('technician')
    ->name('technician.')
    ->group(function () {
        Route::get('/dashboard', [TechnicianDashboardController::class, 'index'])->name('dashboard');
        
        // Visits
        Route::get('/visits', [\App\Http\Controllers\Technician\VisitController::class, 'index'])->name('visits.index');
        Route::get('/visits/{id}', [\App\Http\Controllers\Technician\VisitController::class, 'show'])->name('visits.show');
        Route::post('/visits/{id}/accept', [\App\Http\Controllers\Technician\VisitController::class, 'accept'])->name('visits.accept');
        Route::post('/visits/{id}/start', [\App\Http\Controllers\Technician\VisitController::class, 'start'])->name('visits.start');
        Route::post('/visits/{id}/complete', [\App\Http\Controllers\Technician\VisitController::class, 'complete'])->name('visits.complete');
        Route::post('/visits/{id}/update-notes', [\App\Http\Controllers\Technician\VisitController::class, 'updateNotes'])->name('visits.update-notes');
        Route::post('/visits/{id}/photos', [\App\Http\Controllers\Technician\VisitController::class, 'uploadPhoto'])->name('visits.upload-photo');
        
        // Reports
        Route::get('/reports', [\App\Http\Controllers\Technician\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/create', [\App\Http\Controllers\Technician\ReportController::class, 'create'])->name('reports.create');
        Route::post('/reports', [\App\Http\Controllers\Technician\ReportController::class, 'store'])->name('reports.store');
        Route::get('/reports/{id}', [\App\Http\Controllers\Technician\ReportController::class, 'show'])->name('reports.show');
        
        // Complaints
        Route::get('/complaints', [\App\Http\Controllers\Technician\ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/{id}', [\App\Http\Controllers\Technician\ComplaintController::class, 'show'])->name('complaints.show');
        
        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Technician\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Technician\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Technician\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    });

// Client routes
Route::middleware(['auth', 'role:client'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
        
        // Subscriptions
        Route::get('/subscriptions', [\App\Http\Controllers\Client\SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('/subscriptions/{id}', [\App\Http\Controllers\Client\SubscriptionController::class, 'show'])->name('subscriptions.show');
        
        // Visits
        Route::get('/visits', [\App\Http\Controllers\Client\VisitController::class, 'index'])->name('visits.index');
        Route::get('/visits/{id}', [\App\Http\Controllers\Client\VisitController::class, 'show'])->name('visits.show');
        
        // Reports
        Route::get('/reports', [\App\Http\Controllers\Client\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{id}', [\App\Http\Controllers\Client\ReportController::class, 'show'])->name('reports.show');
        
        // Orders
        Route::get('/orders', [\App\Http\Controllers\Client\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{id}', [\App\Http\Controllers\Client\OrderController::class, 'show'])->name('orders.show');
        
        // Complaints
        Route::get('/complaints', [\App\Http\Controllers\Client\ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/create', [\App\Http\Controllers\Client\ComplaintController::class, 'create'])->name('complaints.create');
        Route::post('/complaints', [\App\Http\Controllers\Client\ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/complaints/{id}', [\App\Http\Controllers\Client\ComplaintController::class, 'show'])->name('complaints.show');
        
        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Client\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Client\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Client\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
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

<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\AuditLogController;
// Dashboard Controllers for roles
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\HrController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportManagementController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\SupportTicketWebController;
use App\Http\Controllers\Admin\TipController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VisitController;
use App\Http\Controllers\AreaManager\AreaManagerDashboardController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\HR\HrDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Supervisor\SupervisorDashboardController;
use App\Http\Controllers\Technician\TechnicianDashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect old URLs to clean /media/ path (for existing bookmarks/API responses)
Route::get('/storage/{path}', function (string $path) {
    return redirect('/media/'.$path, 301);
})->where('path', '.*');
Route::get('/app-storage/{path}', function (string $path) {
    return redirect('/media/'.$path, 301);
})->where('path', '.*');

// Serve public files at clean URL: https://your-domain.com/media/products/xxx.jpg
Route::get('/media/{path}', function (string $path) {
    $path = str_replace(['..', '\\'], ['', '/'], $path);
    if (! Storage::disk('public')->exists($path)) {
        abort(404);
    }
    $fullPath = Storage::disk('public')->path($path);
    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

    return response()->file($fullPath, ['Content-Type' => $mime]);
})->where('path', '.*')->name('storage.serve');

// Redirect root '/' to login or dashboard redirect
Route::get('/', function () {
    try {
        if (auth()->check()) {
            return redirect()->route('dashboard.redirect');
        }

        return redirect()->route('login');
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Root route error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return redirect('/login');
    }
});

// Alias route 'dashboard' that redirects to 'dashboard.redirect' (fix missing route errors)
Route::middleware('auth')->get('/dashboard', function () {
    return redirect()->route('dashboard.redirect');
})->name('dashboard');

// Role-based dashboard redirect route
Route::middleware('auth')->get('/dashboard-redirect', function () {
    try {
        $user = auth()->user();
        $role = $user->role ?? null;
        if ($role === null && method_exists($user, 'getRoleNames')) {
            $role = $user->getRoleNames()->first();
        }
        switch ($role) {
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
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Dashboard redirect error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return redirect('/login');
    }
})->name('dashboard.redirect');

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes (locale for dashboard translations, prevent.admin.cache so theme/settings changes show immediately)
Route::middleware(['auth', 'role:admin', 'set.admin.locale', 'prevent.admin.cache'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('/locale', function (\Illuminate\Http\Request $request) {
            $locale = $request->validate(['locale' => 'required|string|in:en,ar,ur'])['locale'];
            session(['admin_locale' => $locale]);

            return redirect()->back();
        })->name('locale');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/recent-activities', [AdminDashboardController::class, 'recentActivitiesPage'])->name('recent-activities.index');

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
        Route::post('products/bulk-update-status', [ProductController::class, 'bulkUpdateStatus'])->name('products.bulk-update-status');
        Route::post('products/bulk-update-stock', [ProductController::class, 'bulkUpdateStock'])->name('products.bulk-update-stock');
        Route::post('products/{id}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');

        Route::resource('categories', CategoryController::class);
        Route::get('services', [\App\Http\Controllers\Admin\ServicesController::class, 'index'])->name('services.index');
        Route::get('services/create', [\App\Http\Controllers\Admin\ServicesController::class, 'create'])->name('services.create');
        Route::post('services', [\App\Http\Controllers\Admin\ServicesController::class, 'store'])->name('services.store');
        Route::get('services/category/{id}', [\App\Http\Controllers\Admin\ServicesController::class, 'showCategory'])->name('services.category');
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

        // Generated reports (AdminReport) – generate, schedule, download, share
        Route::get('report-management', [ReportManagementController::class, 'index'])->name('report-management.index');
        Route::get('report-management/create', [ReportManagementController::class, 'create'])->name('report-management.create');
        Route::post('report-management', [ReportManagementController::class, 'store'])->name('report-management.store');
        Route::get('report-management/schedule', [ReportManagementController::class, 'createSchedule'])->name('report-management.schedule.create');
        Route::post('report-management/schedule', [ReportManagementController::class, 'storeSchedule'])->name('report-management.schedule.store');
        Route::get('report-management/{id}', [ReportManagementController::class, 'show'])->name('report-management.show');
        Route::get('report-management/{id}/download', [ReportManagementController::class, 'download'])->name('report-management.download');
        Route::post('report-management/{id}/cancel', [ReportManagementController::class, 'cancel'])->name('report-management.cancel');
        Route::delete('report-management/{id}', [ReportManagementController::class, 'destroy'])->name('report-management.destroy');

        Route::get('zone-assignment', [AreaController::class, 'zoneAssignment'])->name('zone-assignment.index');
        Route::resource('areas', AreaController::class);
        Route::resource('orders', OrderController::class)->only(['index', 'show']);
        Route::get('orders/export', [OrderController::class, 'export'])->name('orders.export');
        Route::post('orders/send-to-supplier', [OrderController::class, 'sendToSupplier'])->name('orders.send-to-supplier');
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
        Route::get('settings/all', [SettingController::class, 'all'])->name('settings.all');
        Route::get('settings/theme', [SettingController::class, 'theme'])->name('settings.theme');
        Route::post('settings/theme', [SettingController::class, 'updateTheme'])->name('settings.theme.store');
        Route::get('settings/language', [SettingController::class, 'language'])->name('settings.language');
        Route::post('settings/language', [SettingController::class, 'updateLanguage'])->name('settings.language.store');
        Route::get('settings/privacy-policy', [SettingController::class, 'privacyPolicy'])->name('settings.privacy-policy');
        Route::post('settings/privacy-policy', [SettingController::class, 'updatePrivacyPolicy'])->name('settings.privacy-policy.store');
        Route::get('settings/terms', [SettingController::class, 'termsOfService'])->name('settings.terms');
        Route::post('settings/terms', [SettingController::class, 'updateTermsOfService'])->name('settings.terms.store');
        Route::post('settings/clear-cache', [SettingController::class, 'clearCache'])->name('settings.clear-cache');
        Route::get('settings/developer-options', [SettingController::class, 'developerOptions'])->name('settings.developer-options');
        Route::get('settings/debug-logs', [SettingController::class, 'debugLogs'])->name('settings.debug-logs');
        Route::post('settings/export-data', [SettingController::class, 'exportData'])->name('settings.export-data');
        Route::post('settings/system', [SettingController::class, 'updateSystem'])->name('settings.system.store');
        Route::get('settings/general', [SettingController::class, 'general'])->name('settings.general');
        Route::get('settings/contact', [SettingController::class, 'contact'])->name('settings.contact');
        Route::get('settings/social', [SettingController::class, 'social'])->name('settings.social');
        Route::get('settings/payment', [SettingController::class, 'payment'])->name('settings.payment');
        Route::get('settings/email', [SettingController::class, 'email'])->name('settings.email');
        Route::get('settings/notifications', [SettingController::class, 'notifications'])->name('settings.notifications');
        Route::get('settings/security', [SettingController::class, 'security'])->name('settings.security');
        Route::get('settings/integrations', [SettingController::class, 'integrations'])->name('settings.integrations');
        Route::post('settings/app', [SettingController::class, 'updateAppSettings'])->name('settings.app');
        Route::post('settings/payment', [SettingController::class, 'updatePaymentSettings'])->name('settings.payment');
        Route::post('settings/notification', [SettingController::class, 'updateNotificationSettings'])->name('settings.notification');
        Route::post('settings/email', [SettingController::class, 'updateEmailSettings'])->name('settings.email.store');
        Route::post('settings/social', [SettingController::class, 'updateSocialSettings'])->name('settings.social');
        Route::post('settings/contact', [SettingController::class, 'updateContactSettings'])->name('settings.contact');
        Route::get('settings/email-templates', [SettingController::class, 'emailTemplates'])->name('settings.email-templates');
        Route::post('settings/email-templates/{id}', [SettingController::class, 'updateEmailTemplate'])->name('settings.email-template.update');
        Route::post('settings/security', [SettingController::class, 'updateSecuritySettings'])->name('settings.security');
        Route::post('settings/integrations', [SettingController::class, 'updateIntegrationsSettings'])->name('settings.integrations');
        Route::get('settings/client-dashboard', [SettingController::class, 'clientDashboardDesign'])->name('settings.client-dashboard');
        Route::post('settings/client-dashboard', [SettingController::class, 'updateClientDashboardDesign'])->name('settings.client-dashboard.store');

        Route::resource('audit-logs', AuditLogController::class)->only(['index', 'show']);

        // Banner routes - define specific routes before resource to avoid conflicts
        Route::post('banners/update-order', [BannerController::class, 'updateOrder'])->name('banners.update-order');
        Route::post('banners/{id}/toggle-status', [BannerController::class, 'toggleStatus'])->name('banners.toggle-status');
        Route::resource('banners', BannerController::class);
        Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
        Route::get('packages/{id}/edit', [PackageController::class, 'edit'])->name('packages.edit');
        Route::put('packages/{id}', [PackageController::class, 'update'])->name('packages.update');
        Route::resource('tips', TipController::class);
        Route::post('tips/{id}/toggle-status', [TipController::class, 'toggleStatus'])->name('tips.toggle-status');

        // Notifications routes
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/create', [NotificationController::class, 'create'])->name('notifications.create');
        Route::post('notifications/send', [NotificationController::class, 'send'])->name('notifications.send');
        Route::get('notifications/{id}', [NotificationController::class, 'show'])->whereUuid('id')->name('notifications.show');
        Route::get('notifications/{id}/read-and-redirect', [NotificationController::class, 'readAndRedirect'])->name('notifications.read-and-redirect');
        Route::post('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::post('notifications/delete-selected', [NotificationController::class, 'destroyBulk'])->name('notifications.destroy-bulk');
        Route::post('notifications/delete-all', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
        Route::get('notifications/unread-count', [NotificationController::class, 'getUnreadCount'])->name('notifications.unread-count');

        // Support tickets (client submitted tickets with admin reply thread)
        Route::get('support-tickets', [SupportTicketWebController::class, 'index'])->name('support-tickets.index');
        Route::get('support-tickets/attachment/{id}', [SupportTicketWebController::class, 'downloadAttachment'])->name('support-tickets.attachment');
        Route::get('support-tickets/{id}', [SupportTicketWebController::class, 'show'])->name('support-tickets.show');
        Route::post('support-tickets/{id}/reply', [SupportTicketWebController::class, 'reply'])->name('support-tickets.reply');
        Route::put('support-tickets/{id}/status', [SupportTicketWebController::class, 'updateStatus'])->name('support-tickets.update-status');
        Route::delete('support-tickets/{id}', [SupportTicketWebController::class, 'destroy'])->name('support-tickets.destroy');

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

        // My Team & Assign Jobs
        Route::get('/team', [\App\Http\Controllers\Supervisor\TeamController::class, 'index'])->name('team.index');
        Route::get('/team/{id}', [\App\Http\Controllers\Supervisor\TeamController::class, 'show'])->name('team.show');
        Route::get('/assign-jobs', [\App\Http\Controllers\Supervisor\TeamController::class, 'assignJobs'])->name('assign-jobs.index');
        Route::post('/assign-jobs', [\App\Http\Controllers\Supervisor\TeamController::class, 'assignJobStore'])->name('assign-jobs.store');

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

        // Leave requests (apply & view my requests – HR approves)
        Route::get('/leave-requests', [\App\Http\Controllers\Supervisor\LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::get('/leave-requests/create', [\App\Http\Controllers\Supervisor\LeaveRequestController::class, 'create'])->name('leave-requests.create');
        Route::post('/leave-requests', [\App\Http\Controllers\Supervisor\LeaveRequestController::class, 'store'])->name('leave-requests.store');

        // Tips
        Route::get('/tips', [\App\Http\Controllers\Tips\TipWebController::class, 'index'])->name('tips.index');
        Route::get('/tips/{id}', [\App\Http\Controllers\Tips\TipWebController::class, 'show'])->name('tips.show');

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Supervisor\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}', [\App\Http\Controllers\Supervisor\NotificationController::class, 'show'])->whereUuid('id')->name('notifications.show');
        Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Supervisor\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Supervisor\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Supervisor\NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::post('/notifications/delete-selected', [\App\Http\Controllers\Supervisor\NotificationController::class, 'destroyBulk'])->name('notifications.destroy-bulk');
        Route::post('/notifications/delete-all', [\App\Http\Controllers\Supervisor\NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');

        // Help & Support (tickets + chat with admin)
        Route::get('/help-support', [\App\Http\Controllers\HelpSupportWebController::class, 'index'])->name('help-support.index');
        Route::post('/help-support', [\App\Http\Controllers\HelpSupportWebController::class, 'store'])->name('help-support.store');
        Route::get('/help-support/{id}', [\App\Http\Controllers\HelpSupportWebController::class, 'show'])->name('help-support.show');
        Route::post('/help-support/{id}/reply', [\App\Http\Controllers\HelpSupportWebController::class, 'reply'])->name('help-support.reply');
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

        // Tips
        Route::get('/tips', [\App\Http\Controllers\Tips\TipWebController::class, 'index'])->name('tips.index');
        Route::get('/tips/{id}', [\App\Http\Controllers\Tips\TipWebController::class, 'show'])->name('tips.show');

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Technician\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}', [\App\Http\Controllers\Technician\NotificationController::class, 'show'])->whereUuid('id')->name('notifications.show');
        Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Technician\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Technician\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Technician\NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::post('/notifications/delete-selected', [\App\Http\Controllers\Technician\NotificationController::class, 'destroyBulk'])->name('notifications.destroy-bulk');
        Route::post('/notifications/delete-all', [\App\Http\Controllers\Technician\NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');

        // Help & Support (tickets + chat with admin)
        Route::get('/help-support', [\App\Http\Controllers\HelpSupportWebController::class, 'index'])->name('help-support.index');
        Route::post('/help-support', [\App\Http\Controllers\HelpSupportWebController::class, 'store'])->name('help-support.store');
        Route::get('/help-support/{id}', [\App\Http\Controllers\HelpSupportWebController::class, 'show'])->name('help-support.show');
        Route::post('/help-support/{id}/reply', [\App\Http\Controllers\HelpSupportWebController::class, 'reply'])->name('help-support.reply');
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

        // Shop (cart & checkout – Stripe / PayPal only)
        Route::get('/shop', [\App\Http\Controllers\Client\ShopController::class, 'index'])->name('shop.index');
        Route::get('/cart', [\App\Http\Controllers\Client\CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/add', [\App\Http\Controllers\Client\CartController::class, 'add'])->name('cart.add');
        Route::put('/cart/{id}', [\App\Http\Controllers\Client\CartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/{id}', [\App\Http\Controllers\Client\CartController::class, 'remove'])->name('cart.remove');
        Route::post('/cart/clear', [\App\Http\Controllers\Client\CartController::class, 'clear'])->name('cart.clear');
        Route::get('/checkout', [\App\Http\Controllers\Client\CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout/process', [\App\Http\Controllers\Client\CheckoutController::class, 'process'])->name('checkout.process');
        Route::get('/checkout/success/{order_id}', [\App\Http\Controllers\Client\CheckoutController::class, 'success'])->name('checkout.success');
        Route::get('/checkout/cancel/{order_id}', [\App\Http\Controllers\Client\CheckoutController::class, 'cancel'])->name('checkout.cancel');

        // Services (place service orders – categories with products)
        Route::get('/services', [\App\Http\Controllers\Client\ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/category/{id}', [\App\Http\Controllers\Client\ServiceController::class, 'showCategory'])->name('services.category');

        // Complaints
        Route::get('/complaints', [\App\Http\Controllers\Client\ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/create', [\App\Http\Controllers\Client\ComplaintController::class, 'create'])->name('complaints.create');
        Route::post('/complaints', [\App\Http\Controllers\Client\ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/complaints/{id}', [\App\Http\Controllers\Client\ComplaintController::class, 'show'])->name('complaints.show');

        // Tips
        Route::get('/tips', [\App\Http\Controllers\Tips\TipWebController::class, 'index'])->name('tips.index');
        Route::get('/tips/{id}', [\App\Http\Controllers\Tips\TipWebController::class, 'show'])->name('tips.show');

        // Profile / Account (aligned with API: memberships, addresses, payment methods, loyalty, help & support)
        Route::get('/memberships', [\App\Http\Controllers\Client\MembershipController::class, 'index'])->name('memberships.index');
        Route::get('/addresses', [\App\Http\Controllers\Client\AddressController::class, 'index'])->name('addresses.index');
        Route::get('/addresses/create', [\App\Http\Controllers\Client\AddressController::class, 'create'])->name('addresses.create');
        Route::post('/addresses', [\App\Http\Controllers\Client\AddressController::class, 'store'])->name('addresses.store');
        Route::get('/addresses/{id}/edit', [\App\Http\Controllers\Client\AddressController::class, 'edit'])->name('addresses.edit');
        Route::put('/addresses/{id}', [\App\Http\Controllers\Client\AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{id}', [\App\Http\Controllers\Client\AddressController::class, 'destroy'])->name('addresses.destroy');
        Route::get('/payment-methods', [\App\Http\Controllers\Client\PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::get('/loyalty', [\App\Http\Controllers\Client\LoyaltyController::class, 'index'])->name('loyalty.index');
        Route::get('/help-support', [\App\Http\Controllers\Client\HelpSupportController::class, 'index'])->name('help-support.index');
        Route::post('/help-support', [\App\Http\Controllers\Client\HelpSupportController::class, 'store'])->name('help-support.store');
        Route::get('/help-support/{id}', [\App\Http\Controllers\Client\HelpSupportController::class, 'show'])->name('help-support.show');
        Route::post('/help-support/{id}/reply', [\App\Http\Controllers\Client\HelpSupportController::class, 'reply'])->name('help-support.reply');

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Client\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}', [\App\Http\Controllers\Client\NotificationController::class, 'show'])->whereUuid('id')->name('notifications.show');
        Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Client\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Client\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Client\NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::post('/notifications/delete-selected', [\App\Http\Controllers\Client\NotificationController::class, 'destroyBulk'])->name('notifications.destroy-bulk');
        Route::post('/notifications/delete-all', [\App\Http\Controllers\Client\NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
    });

// Area Manager routes
Route::middleware(['auth', 'role:area_manager'])
    ->prefix('areamanager')
    ->name('areamanager.')
    ->group(function () {
        Route::get('/dashboard', [AreaManagerDashboardController::class, 'index'])->name('dashboard');

        // Areas
        Route::get('/areas', [\App\Http\Controllers\AreaManager\AreaController::class, 'index'])->name('areas.index');
        Route::get('/areas/{id}', [\App\Http\Controllers\AreaManager\AreaController::class, 'show'])->name('areas.show');

        // Visits
        Route::get('/visits', [\App\Http\Controllers\AreaManager\VisitController::class, 'index'])->name('visits.index');
        Route::get('/visits/{id}', [\App\Http\Controllers\AreaManager\VisitController::class, 'show'])->name('visits.show');

        // Reports
        Route::get('/reports', [\App\Http\Controllers\AreaManager\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{id}', [\App\Http\Controllers\AreaManager\ReportController::class, 'show'])->name('reports.show');

        // Generated Reports (Generate PDF)
        Route::get('/generated-reports', [\App\Http\Controllers\AreaManager\GeneratedReportController::class, 'index'])->name('generated-reports.index');
        Route::post('/generated-reports', [\App\Http\Controllers\AreaManager\GeneratedReportController::class, 'store'])->name('generated-reports.store');
        Route::get('/generated-reports/{id}/download', [\App\Http\Controllers\AreaManager\GeneratedReportController::class, 'download'])->name('generated-reports.download');
        Route::get('/generated-reports/{id}/view', [\App\Http\Controllers\AreaManager\GeneratedReportController::class, 'view'])->name('generated-reports.view');
        Route::post('/generated-reports/{id}/destroy', [\App\Http\Controllers\AreaManager\GeneratedReportController::class, 'destroy'])->name('generated-reports.destroy');

        // Tips
        Route::get('/tips', [\App\Http\Controllers\Tips\TipWebController::class, 'index'])->name('tips.index');
        Route::get('/tips/{id}', [\App\Http\Controllers\Tips\TipWebController::class, 'show'])->name('tips.show');

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\AreaManager\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}', [\App\Http\Controllers\AreaManager\NotificationController::class, 'show'])->name('notifications.show');
        Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\AreaManager\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\AreaManager\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('/notifications/{id}', [\App\Http\Controllers\AreaManager\NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::post('/notifications/delete-selected', [\App\Http\Controllers\AreaManager\NotificationController::class, 'destroyBulk'])->name('notifications.destroy-bulk');
        Route::post('/notifications/delete-all', [\App\Http\Controllers\AreaManager\NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');

        // Help & Support (tickets + chat with admin)
        Route::get('/help-support', [\App\Http\Controllers\HelpSupportWebController::class, 'index'])->name('help-support.index');
        Route::post('/help-support', [\App\Http\Controllers\HelpSupportWebController::class, 'store'])->name('help-support.store');
        Route::get('/help-support/{id}', [\App\Http\Controllers\HelpSupportWebController::class, 'show'])->name('help-support.show');
        Route::post('/help-support/{id}/reply', [\App\Http\Controllers\HelpSupportWebController::class, 'reply'])->name('help-support.reply');
    });

// HR routes
Route::middleware(['auth', 'role:hr'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {
        Route::get('/dashboard', [HrDashboardController::class, 'index'])->name('dashboard');

        Route::get('/visit-assignments', [\App\Http\Controllers\HR\HrVisitAssignmentWebController::class, 'index'])->name('visit-assignments.index');
        Route::post('/visit-assignments/{visit}/assign', [\App\Http\Controllers\HR\HrVisitAssignmentWebController::class, 'assign'])->name('visit-assignments.assign');

        Route::get('/reports/technician-monthly', [\App\Http\Controllers\HR\HrReportWebController::class, 'technicianMonthlyForm'])->name('reports.technician-monthly');
        Route::post('/reports/technician-monthly/preview', [\App\Http\Controllers\HR\HrReportWebController::class, 'technicianMonthlyPreview'])->name('reports.technician-monthly.preview');
        Route::post('/reports/technician-monthly/generate', [\App\Http\Controllers\HR\HrReportWebController::class, 'technicianMonthlyGenerate'])->name('reports.technician-monthly.generate');
        Route::get('/reports/generated/{id}/download', [\App\Http\Controllers\HR\HrReportWebController::class, 'downloadGenerated'])->name('reports.generated.download');
        Route::delete('/reports/generated/{id}', [\App\Http\Controllers\HR\HrReportWebController::class, 'destroyGenerated'])->name('reports.generated.destroy');

        // Employees
        Route::get('/employees', [\App\Http\Controllers\HR\EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/create', [\App\Http\Controllers\HR\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [\App\Http\Controllers\HR\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/employees/{id}/edit', [\App\Http\Controllers\HR\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'destroy'])->name('employees.destroy');
        Route::post('/employees/{id}/create-user', [\App\Http\Controllers\HR\EmployeeController::class, 'createUser'])->name('employees.create-user');
        Route::post('/employees/{id}/update-user-status', [\App\Http\Controllers\HR\EmployeeController::class, 'updateUserStatus'])->name('employees.update-user-status');

        // Leave requests (manage leaves – list, approve, reject)
        Route::get('/leave-requests', [\App\Http\Controllers\HR\LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::get('/leave-requests/{id}', [\App\Http\Controllers\HR\LeaveRequestController::class, 'show'])->name('leave-requests.show');
        Route::post('/leave-requests/{id}/approve', [\App\Http\Controllers\HR\LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('/leave-requests/{id}/reject', [\App\Http\Controllers\HR\LeaveRequestController::class, 'reject'])->name('leave-requests.reject');

        // Tips
        Route::get('/tips', [\App\Http\Controllers\Tips\TipWebController::class, 'index'])->name('tips.index');
        Route::get('/tips/{id}', [\App\Http\Controllers\Tips\TipWebController::class, 'show'])->name('tips.show');

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\HR\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}', [\App\Http\Controllers\HR\NotificationController::class, 'show'])->whereUuid('id')->name('notifications.show');
        Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\HR\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\HR\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('/notifications/{id}', [\App\Http\Controllers\HR\NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::post('/notifications/delete-selected', [\App\Http\Controllers\HR\NotificationController::class, 'destroyBulk'])->name('notifications.destroy-bulk');
        Route::post('/notifications/delete-all', [\App\Http\Controllers\HR\NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');

        // Help & Support (tickets + chat with admin)
        Route::get('/help-support', [\App\Http\Controllers\HelpSupportWebController::class, 'index'])->name('help-support.index');
        Route::post('/help-support', [\App\Http\Controllers\HelpSupportWebController::class, 'store'])->name('help-support.store');
        Route::get('/help-support/{id}', [\App\Http\Controllers\HelpSupportWebController::class, 'show'])->name('help-support.show');
        Route::post('/help-support/{id}/reply', [\App\Http\Controllers\HelpSupportWebController::class, 'reply'])->name('help-support.reply');
    });

// Breeze auth routes (login/logout/password/reset)
require __DIR__.'/auth.php';

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

// Alias (same handler as api/auth/technician-signup-areas) — useful if proxies/docs use a shorter path; always deploy latest routes.
Route::get('/technician-signup-areas', [\App\Http\Controllers\Auth\AuthController::class, 'technicianSignupAreas'])
    ->name('api.technician-signup-areas');

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
    Route::get('/technician-signup-areas', [\App\Http\Controllers\Auth\AuthController::class, 'technicianSignupAreas']);
    Route::post('/register', [\App\Http\Controllers\Auth\AuthController::class, 'register']);
    Route::post('/register-technician', [\App\Http\Controllers\Auth\AuthController::class, 'registerTechnician']);
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
    Route::get('/areas', [\App\Http\Controllers\Visit\VisitController::class, 'areas']);
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
| TECHNICIAN DASHBOARD (dashboard, profile, tasks, jobs, availability with breaks & vacations & service_areas, schedule — no separate vacation routes)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:technician'])->prefix('technician')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'dashboard']);
    Route::get('/profile', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'profile']);
    Route::put('/profile', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateProfile']);
    Route::post('/profile', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateProfile']); // POST so form-data (file upload) is parsed by PHP
    Route::get('/service-areas', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'getServiceAreas']);
    Route::put('/service-areas', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateServiceAreas']);
    Route::post('/service-areas', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateServiceAreas']);
    Route::get('/specializations', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'getSpecializations']);
    Route::put('/specializations', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateSpecializations']);
    Route::post('/specializations', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateSpecializations']);
    Route::get('/tasks', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'tasks']);
    Route::get('/tasks/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'taskShow']);
    Route::get('/tasks/{id}/detail', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'taskDetail']);
    Route::put('/tasks/{id}/status', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'taskUpdateStatus']);
    Route::post('/tasks/{id}/accept', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'taskAccept']);
    Route::post('/tasks/{id}/reject', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'taskReject']);
    Route::get('/jobs', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'jobs']);
    Route::get('/jobs/accepted', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'jobsAccepted']);
    Route::get('/jobs/rejected', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'jobsRejected']);
    Route::get('/jobs/status-counts', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'jobsStatusCounts']);
    Route::get('/payout-summary', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'payoutSummary']);
    Route::get('/payouts', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'payouts']);
    Route::get('/settings/payout', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'payoutSettings']);
    Route::put('/settings/payout', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updatePayoutSettings']);
    Route::get('/bank-accounts', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'bankAccounts']);
    Route::post('/bank-accounts', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'bankAccountStore']);
    Route::put('/bank-accounts/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'bankAccountUpdate']);
    Route::delete('/bank-accounts/{id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'bankAccountDestroy']);
    Route::get('/leave-types', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'leaveTypes']);
    Route::get('/leave-request-types', [\App\Http\Controllers\Api\EmployeeLeaveRequestController::class, 'leaveTypes']);
    Route::get('/leave-requests', [\App\Http\Controllers\Api\EmployeeLeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [\App\Http\Controllers\Api\EmployeeLeaveRequestController::class, 'store']);
    Route::get('/leave-requests/{id}', [\App\Http\Controllers\Api\EmployeeLeaveRequestController::class, 'show']);
    Route::get('/alerts', [\App\Http\Controllers\Api\AlertsController::class, 'index']);
    Route::get('/availability', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'availability']);
    Route::put('/availability', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'updateAvailability']);
    Route::get('/schedule', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'schedule']);
    // Notifications (sent by admin – title, message)
    Route::get('/notifications', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'markNotificationRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'markAllNotificationsRead']);
    Route::post('/notifications/clear-all', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'clearAllNotifications']);
    // Field notes: GET list (by supervisor_id); POST submit report
    Route::get('/field-notes', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'fieldNotesIndex']);
    Route::post('/reports', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'submitReport']);
    Route::post('/report/{visit_id}', [\App\Http\Controllers\Technician\TechnicianDashboardController::class, 'submitReportForVisit']);
    // Help & Support (same as /api/support/*, under technician prefix for dashboard)
    Route::get('/support/help-center', [\App\Http\Controllers\Api\SupportController::class, 'helpCenter']);
    Route::get('/support/faqs', [\App\Http\Controllers\Api\SupportController::class, 'faqs']);
    Route::post('/support/tickets', [\App\Http\Controllers\Api\SupportController::class, 'storeTicket']);
});

/*
|--------------------------------------------------------------------------
| SUPPORT (FAQs + submit ticket – shared for all authenticated roles)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:client|technician|supervisor|area_manager|hr|admin'])->prefix('support')->group(function () {
    Route::get('/help-center', [\App\Http\Controllers\Api\SupportController::class, 'helpCenter']);
    Route::get('/faqs', [\App\Http\Controllers\Api\SupportController::class, 'faqs']);
    Route::get('/tickets', [\App\Http\Controllers\Api\SupportController::class, 'indexMyTickets']);
    Route::post('/tickets', [\App\Http\Controllers\Api\SupportController::class, 'storeTicket']);
    Route::get('/tickets/{id}', [\App\Http\Controllers\Api\SupportController::class, 'showMyTicket']);
    Route::post('/tickets/{id}/reply', [\App\Http\Controllers\Api\SupportController::class, 'replyToMyTicket']);
});

/*
|--------------------------------------------------------------------------
| SUPERVISOR ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:supervisor'])->prefix('supervisor')->group(function () {
    // Dashboard
    Route::get('/dashboard/summary', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'dashboardSummary']);
    Route::get('/dashboard/kpis', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'dashboardKpis']);
    Route::get('/dashboard/alerts', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'dashboardAlerts']);

    // Zones list (for supervisor to set service areas in profile)
    Route::get('/areas', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'areasList']);
    // Team (list + detail by id)
    Route::get('/team', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'myTeam']);
    Route::post('/team/update', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'teamMembersBulkUpdate']);
    Route::get('/team/{id}', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'teamMemberShow']);
    Route::post('/team/{id}', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'teamMemberUpdate']);
    Route::get('/team-stats', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'teamStats']);
    Route::get('/technician-signup-requests', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'technicianSignupRequests']);
    Route::post('/technician-signup-requests/{id}/confirm', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'technicianSignupRequestConfirm']);
    Route::post('/technician-signup-requests/{id}/cancel', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'technicianSignupRequestCancel']);

    // Assignments: GET list; GET one detail; GET assign-tasks; POST /assignments/{id} (body: technician_id, scheduled_date)
    Route::get('/assignments', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'assignmentsPending']);
    Route::get('/assignments/{id}', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'assignmentsShow']);
    Route::get('/assign-tasks', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'assignTasksPage']);
    Route::post('/assignments', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'assignmentsStore']);
    Route::post('/assignments/{id}', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'assignmentsAssignOrUpdate']);
    Route::post('/assignments/{id}/reassign', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'assignmentsReassign']);

    // Reports (field reports from technicians)
    Route::get('/reports', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'reportsIndex']);
    Route::post('/reports/{id}/accept', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'reportAccept']);
    Route::post('/reports/{id}/reject', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'reportReject']);
    Route::post('/reports/generate', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'reportsGenerate']);
    Route::get('/reports/{id}', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'reportsShow']);
    Route::get('/reports/{id}/download', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'reportsDownload']);

    // Profile (single API: GET profile, PUT/POST profile with form-data: name, email, phone, profile_picture, password, password_confirmation; no current_password)
    Route::get('/profile', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'profile']);
    Route::put('/profile', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'updateProfile']);
    Route::post('/profile', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'updateProfile']);
    Route::get('/profile/preferences', [\App\Http\Controllers\Api\SupervisorDashboardApiController::class, 'profilePreferences']);

    Route::get('/leave-request-types', [\App\Http\Controllers\Api\EmployeeLeaveRequestController::class, 'leaveTypes']);
    Route::get('/leave-requests', [\App\Http\Controllers\Api\EmployeeLeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [\App\Http\Controllers\Api\EmployeeLeaveRequestController::class, 'store']);
    Route::get('/leave-requests/{id}', [\App\Http\Controllers\Api\EmployeeLeaveRequestController::class, 'show']);
    Route::get('/alerts', [\App\Http\Controllers\Api\AlertsController::class, 'index']);

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
| AREA MANAGER – Report download/view (works with ID + Bearer OR signed URL)
| GET .../generated-reports/{id}/download and .../view
| - With Bearer token (area_manager): allow if report created_by = user.
| - With valid signed URL (signature + expires): allow without auth.
|--------------------------------------------------------------------------
*/
Route::get('/area-manager/generated-reports/{id}/download', [\App\Http\Controllers\Api\GeneratedReportPublicController::class, 'download'])
    ->name('api.area-manager.generated-reports.download.public')
    ->middleware('optional.sanctum');
Route::get('/area-manager/generated-reports/{id}/view', [\App\Http\Controllers\Api\GeneratedReportPublicController::class, 'view'])
    ->name('api.area-manager.generated-reports.view.public')
    ->middleware('optional.sanctum');
Route::get('/hr/reports/{id}/download-public', [\App\Http\Controllers\Api\HrReportsApiController::class, 'downloadPublic'])
    ->name('api.hr.reports.download.public')
    ->withoutMiddleware(['auth:sanctum']);

/*
|--------------------------------------------------------------------------
| AREA MANAGER ROUTES (dashboard, alerts, teams, reports)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:area_manager'])->prefix('area-manager')->group(function () {
    Route::get('/dashboard/summary', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'dashboardSummary']);
    Route::get('/dashboard/alerts', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'dashboardAlerts']);
    Route::get('/region-map', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'regionMap']);

    /* Teams (All Teams screen → team members → member jobs). Use these to avoid confusion with "team leaders". */
    Route::get('/teams', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamLeaders']);
    Route::get('/teams/members/{id}/jobs', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamMemberJobs']);
    Route::get('/teams/{id}/members', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamLeaderMembers']);
    Route::get('/teams/{id}/jobs', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamLeaderJobs']);
    Route::get('/teams/{id}', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamLeaderShow']);

    /* Legacy team-leaders paths (prefer /teams for new use). */
    Route::get('/team-leaders', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamLeaders']);
    Route::get('/team-leaders/{id}/members', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamLeaderMembers']);
    Route::get('/team-leaders/{id}/jobs', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamLeaderJobs']);
    Route::get('/team-leaders/{id}', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamLeaderShow']);
    Route::get('/team-members/{id}/jobs', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'teamMemberJobs']);
    Route::get('/analytics', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'analytics']);
    Route::get('/reports', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'reportsIndex']);
    Route::post('/reports/generate', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'reportGenerate']);
    Route::get('/generated-reports', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'generatedReportsIndex']);
    /* Help & Support (talk to admin – same as /api/support/*) */
    Route::get('/support/help-center', [\App\Http\Controllers\Api\SupportController::class, 'helpCenter']);
    Route::get('/support/faqs', [\App\Http\Controllers\Api\SupportController::class, 'faqs']);
    Route::get('/support/tickets', [\App\Http\Controllers\Api\SupportController::class, 'indexMyTickets']);
    Route::post('/support/tickets', [\App\Http\Controllers\Api\SupportController::class, 'storeTicket']);
    Route::get('/support/tickets/{id}', [\App\Http\Controllers\Api\SupportController::class, 'showMyTicket']);
    Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\Api\SupportController::class, 'replyToMyTicket']);
    Route::get('/profile', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'profile']);
    Route::put('/profile', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'updateProfile']);
    Route::post('/profile', [\App\Http\Controllers\Api\AreaManagerApiController::class, 'updateProfile']);
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

    // Zones (Areas): assign supervisors and technicians to zones at setup. area_supervisor, area_technician.
    Route::get('/technicians', [\App\Http\Controllers\Api\Admin\AreaController::class, 'technicians']);
    Route::get('/technicians-for-zones', [\App\Http\Controllers\Api\Admin\AreaController::class, 'techniciansForZones']);
    Route::get('/supervisors-for-zones', [\App\Http\Controllers\Api\Admin\AreaController::class, 'supervisorsForZones']);

    // Supervisors & Teams: list supervisors, get team, add/remove team member
    Route::get('/supervisors', [\App\Http\Controllers\Api\Admin\SupervisorController::class, 'index']);
    Route::get('/supervisors/{id}/team', [\App\Http\Controllers\Api\Admin\SupervisorController::class, 'team']);
    Route::post('/supervisors/{id}/team', [\App\Http\Controllers\Api\Admin\SupervisorController::class, 'addTeamMember']);
    Route::delete('/supervisors/{id}/team', [\App\Http\Controllers\Api\Admin\SupervisorController::class, 'removeTeamMember']);
    Route::get('/areas', [\App\Http\Controllers\Api\Admin\AreaController::class, 'index']);
    Route::post('/areas', [\App\Http\Controllers\Api\Admin\AreaController::class, 'store']);
    Route::get('/areas/{id}', [\App\Http\Controllers\Api\Admin\AreaController::class, 'show']);
    Route::put('/areas/{id}', [\App\Http\Controllers\Api\Admin\AreaController::class, 'update']);
    Route::post('/areas/{id}', [\App\Http\Controllers\Api\Admin\AreaController::class, 'update']);
    Route::delete('/areas/{id}', [\App\Http\Controllers\Api\Admin\AreaController::class, 'destroy']);

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

    // Support Tickets (submitted by clients)
    Route::get('/support/tickets', [\App\Http\Controllers\Api\Admin\SupportTicketController::class, 'index']);
    Route::get('/support/tickets/{id}', [\App\Http\Controllers\Api\Admin\SupportTicketController::class, 'show']);
    Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\Api\Admin\SupportTicketController::class, 'reply']);
    Route::put('/support/tickets/{id}/status', [\App\Http\Controllers\Api\Admin\SupportTicketController::class, 'updateStatus']);
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
| HR DASHBOARD API (same structure as Area Manager: /api/hr/*)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:hr|admin'])->prefix('hr')->group(function () {
    Route::get('/dashboard/summary', [\App\Http\Controllers\Api\HrApiController::class, 'dashboardSummary']);
    Route::get('/dashboard/visit-assignments', [\App\Http\Controllers\Api\HrApiController::class, 'visitAssignments']);
    Route::get('/visit-assignments/summary', [\App\Http\Controllers\Api\HrApiController::class, 'visitAssignmentsSummary']);
    Route::get('/visit-assignments/assign-screen', [\App\Http\Controllers\Api\HrApiController::class, 'visitAssignmentsAssignScreen']);
    Route::get('/visit-assignments', [\App\Http\Controllers\Api\HrApiController::class, 'visitAssignmentsIndex']);
    Route::post('/visit-assignments/{visitId}', [\App\Http\Controllers\Api\HrApiController::class, 'visitAssignmentsAssign']);
    Route::get('/reports/technician-monthly', [\App\Http\Controllers\Api\HrReportsApiController::class, 'technicianMonthlyPreview']);
    Route::get('/reports', [\App\Http\Controllers\Api\HrReportsApiController::class, 'index']);
    Route::post('/reports/generate', [\App\Http\Controllers\Api\HrReportsApiController::class, 'generate']);
    Route::get('/reports/{id}/download', [\App\Http\Controllers\Api\HrReportsApiController::class, 'download'])->name('api.hr.reports.download');
    Route::delete('/reports/{id}', [\App\Http\Controllers\Api\HrReportsApiController::class, 'destroy']);
    Route::get('/positions', [\App\Http\Controllers\Api\HrApiController::class, 'positions']);
    Route::get('/leave-requests', [\App\Http\Controllers\Api\HrApiController::class, 'leaveRequestsIndex']);
    Route::post('/leave-requests', [\App\Http\Controllers\Api\HrApiController::class, 'leaveRequestStore']);
    Route::get('/leave-requests/{id}', [\App\Http\Controllers\Api\HrApiController::class, 'leaveRequestShow']);
    Route::post('/leave-requests/{id}/approve', [\App\Http\Controllers\Api\HrApiController::class, 'leaveRequestApprove']);
    Route::post('/leave-requests/{id}/reject', [\App\Http\Controllers\Api\HrApiController::class, 'leaveRequestReject']);
    Route::get('/employees', [\App\Http\Controllers\HR\EmployeeController::class, 'index']);
    Route::post('/employees', [\App\Http\Controllers\HR\EmployeeController::class, 'store']);
    Route::get('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'show']);
    Route::put('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'update']);
    Route::post('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [\App\Http\Controllers\HR\EmployeeController::class, 'destroy']);
    /* Help & Support (talk to admin – same as /api/support/*, under hr prefix for dashboard) */
    Route::get('/support/help-center', [\App\Http\Controllers\Api\SupportController::class, 'helpCenter']);
    Route::get('/support/faqs', [\App\Http\Controllers\Api\SupportController::class, 'faqs']);
    Route::get('/support/tickets', [\App\Http\Controllers\Api\SupportController::class, 'indexMyTickets']);
    Route::post('/support/tickets', [\App\Http\Controllers\Api\SupportController::class, 'storeTicket']);
    Route::get('/support/tickets/{id}', [\App\Http\Controllers\Api\SupportController::class, 'showMyTicket']);
    Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\Api\SupportController::class, 'replyToMyTicket']);
    Route::get('/profile', [\App\Http\Controllers\Api\HrApiController::class, 'profile']);
    Route::put('/profile', [\App\Http\Controllers\Api\HrApiController::class, 'updateProfile']);
    Route::post('/profile', [\App\Http\Controllers\Api\HrApiController::class, 'updateProfile']);
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
        Route::post('/buy-now/summary', [\App\Http\Controllers\Shop\CartController::class, 'buyNowSummary']);
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
    Route::post('/notifications/clear-all', [\App\Http\Controllers\Api\UserController::class, 'clearAllNotifications']);
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
    Route::put('/profile', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'updateProfile']);
    Route::post('/profile', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'updateProfile']);
    Route::get('/top-selling-products', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'topSellingProducts']);
});

/*
|--------------------------------------------------------------------------
| ADMIN SUPPORT TICKETS (tickets from clients/technicians via /api/support/tickets)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/support-tickets')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Admin\SupportTicketController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\Admin\SupportTicketController::class, 'show']);
    Route::post('/{id}/reply', [\App\Http\Controllers\Api\Admin\SupportTicketController::class, 'reply']);
    Route::put('/{id}/status', [\App\Http\Controllers\Api\Admin\SupportTicketController::class, 'updateStatus']);
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
Route::middleware(['auth:sanctum', 'role:client|technician|supervisor|area_manager|hr|admin'])->group(function () {
    Route::get('/tips', [\App\Http\Controllers\Tips\TipsController::class, 'index']);
    Route::post('/tips', [\App\Http\Controllers\Tips\TipsController::class, 'store']);
    Route::get('/tips/{id}', [\App\Http\Controllers\Tips\TipsController::class, 'show']);
    Route::put('/tips/{id}', [\App\Http\Controllers\Tips\TipsController::class, 'update']);
    Route::delete('/tips/{id}', [\App\Http\Controllers\Tips\TipsController::class, 'destroy']);
    Route::get('/notifications', [\App\Http\Controllers\Notification\NotificationController::class, 'index']);
    Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Notification\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Notification\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Notification\NotificationController::class, 'destroy']);
    Route::post('/notifications/clear-all', [\App\Http\Controllers\Notification\NotificationController::class, 'clearAll']);
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

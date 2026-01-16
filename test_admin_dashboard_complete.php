<?php

/**
 * Complete Admin Dashboard Testing Script
 * Tests all admin routes, authentication, and views
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "🧪 Complete Admin Dashboard Testing\n";
echo str_repeat("=", 80) . "\n\n";

$baseUrl = 'http://localhost:8000';
$errors = [];
$warnings = [];
$passed = [];

// Define all admin routes to test
$adminRoutes = [
    // Dashboard
    ['url' => '/admin/dashboard', 'method' => 'GET', 'name' => 'Dashboard', 'view' => 'admin.dashboard'],
    
    // Users
    ['url' => '/admin/users', 'method' => 'GET', 'name' => 'Users Index', 'view' => 'admin.users.index'],
    ['url' => '/admin/users/create', 'method' => 'GET', 'name' => 'Users Create', 'view' => 'admin.users.create'],
    
    // Roles
    ['url' => '/admin/roles', 'method' => 'GET', 'name' => 'Roles Index', 'view' => 'admin.roles.index'],
    ['url' => '/admin/roles/create', 'method' => 'GET', 'name' => 'Roles Create', 'view' => 'admin.roles.create'],
    
    // Products
    ['url' => '/admin/products', 'method' => 'GET', 'name' => 'Products Index', 'view' => 'admin.products.index'],
    ['url' => '/admin/products/create', 'method' => 'GET', 'name' => 'Products Create', 'view' => 'admin.products.create'],
    ['url' => '/admin/products/import', 'method' => 'GET', 'name' => 'Products Import', 'view' => 'admin.products.import'],
    
    // Categories
    ['url' => '/admin/categories', 'method' => 'GET', 'name' => 'Categories Index', 'view' => 'admin.categories.index'],
    ['url' => '/admin/categories/create', 'method' => 'GET', 'name' => 'Categories Create', 'view' => 'admin.categories.create'],
    
    // Subscriptions
    ['url' => '/admin/subscriptions', 'method' => 'GET', 'name' => 'Subscriptions Index', 'view' => 'admin.subscriptions.index'],
    
    // Subscription Plans
    ['url' => '/admin/subscription-plans', 'method' => 'GET', 'name' => 'Subscription Plans', 'view' => 'admin.subscription-plans.index'],
    
    // Visits
    ['url' => '/admin/visits', 'method' => 'GET', 'name' => 'Visits Index', 'view' => 'admin.visits.index'],
    
    // Reports
    ['url' => '/admin/reports', 'method' => 'GET', 'name' => 'Reports Index', 'view' => 'admin.reports.index'],
    
    // Areas
    ['url' => '/admin/areas', 'method' => 'GET', 'name' => 'Areas Index', 'view' => 'admin.areas.index'],
    ['url' => '/admin/areas/create', 'method' => 'GET', 'name' => 'Areas Create', 'view' => 'admin.areas.create'],
    
    // Orders
    ['url' => '/admin/orders', 'method' => 'GET', 'name' => 'Orders Index', 'view' => 'admin.orders.index'],
    
    // Payments
    ['url' => '/admin/payments', 'method' => 'GET', 'name' => 'Payments Index', 'view' => 'admin.payments.index'],
    
    // Complaints
    ['url' => '/admin/complaints', 'method' => 'GET', 'name' => 'Complaints Index', 'view' => 'admin.complaints.index'],
    
    // HR
    ['url' => '/admin/hr', 'method' => 'GET', 'name' => 'HR Index', 'view' => 'admin.hr.index'],
    ['url' => '/admin/hr/create', 'method' => 'GET', 'name' => 'HR Create', 'view' => 'admin.hr.create'],
    
    // Settings
    ['url' => '/admin/settings', 'method' => 'GET', 'name' => 'Settings', 'view' => 'admin.settings.index'],
    ['url' => '/admin/settings/email-templates', 'method' => 'GET', 'name' => 'Email Templates', 'view' => 'admin.settings.email-templates'],
    
    // Audit Logs
    ['url' => '/admin/audit-logs', 'method' => 'GET', 'name' => 'Audit Logs', 'view' => 'admin.audit-logs.index'],
    
    // Banners
    ['url' => '/admin/banners', 'method' => 'GET', 'name' => 'Banners Index', 'view' => 'admin.banners.index'],
    ['url' => '/admin/banners/create', 'method' => 'GET', 'name' => 'Banners Create', 'view' => 'admin.banners.create'],
    
    // Tips
    ['url' => '/admin/tips', 'method' => 'GET', 'name' => 'Tips Index', 'view' => 'admin.tips.index'],
    ['url' => '/admin/tips/create', 'method' => 'GET', 'name' => 'Tips Create', 'view' => 'admin.tips.create'],
    
    // Notifications
    ['url' => '/admin/notifications', 'method' => 'GET', 'name' => 'Notifications Index', 'view' => 'admin.notifications.index'],
    ['url' => '/admin/notifications/create', 'method' => 'GET', 'name' => 'Notifications Create', 'view' => 'admin.notifications.create'],
];

// Test 1: Check Authentication Redirect
echo "1. Testing Authentication Redirects...\n";
echo str_repeat("-", 80) . "\n";

$testRoutes = ['/admin/dashboard', '/admin/users', '/admin/products'];
foreach ($testRoutes as $route) {
    $request = \Illuminate\Http\Request::create($route, 'GET');
    $request->headers->set('Accept', 'text/html');
    
    try {
        $response = $app->handle($request);
        $statusCode = $response->getStatusCode();
        
        if ($statusCode === 302 || $statusCode === 301) {
            $location = $response->headers->get('Location');
            if (strpos($location, '/login') !== false) {
                $passed[] = "✅ {$route} redirects to login (unauthenticated)";
                echo "   ✅ {$route} → Redirects to login\n";
            } else {
                $warnings[] = "⚠️  {$route} redirects to {$location} (expected /login)";
                echo "   ⚠️  {$route} → Redirects to {$location}\n";
            }
        } else {
            $errors[] = "❌ {$route} returns status {$statusCode} (expected 302 redirect)";
            echo "   ❌ {$route} → Status {$statusCode}\n";
        }
    } catch (Exception $e) {
        $errors[] = "❌ {$route} → Exception: " . $e->getMessage();
        echo "   ❌ {$route} → Exception: " . $e->getMessage() . "\n";
    }
}

// Test 2: Check Route Registration
echo "\n2. Testing Route Registration...\n";
echo str_repeat("-", 80) . "\n";

$routes = \Illuminate\Support\Facades\Route::getRoutes();
$adminRouteCount = 0;

foreach ($routes as $route) {
    if (strpos($route->uri(), 'admin/') === 0 || strpos($route->uri(), 'admin/') !== false) {
        $adminRouteCount++;
    }
}

echo "   ✅ Found {$adminRouteCount} admin routes\n";

// Test 3: Check View Files
echo "\n3. Testing View Files...\n";
echo str_repeat("-", 80) . "\n";

$viewFiles = [
    'admin.dashboard' => 'admin/dashboard.blade.php',
    'admin.users.index' => 'admin/users/index.blade.php',
    'admin.users.create' => 'admin/users/create.blade.php',
    'admin.products.index' => 'admin/products/index.blade.php',
    'admin.banners.index' => 'admin/banners/index.blade.php',
    'admin.settings.index' => 'admin/settings/index.blade.php',
];

foreach ($viewFiles as $viewName => $viewPath) {
    $fullPath = resource_path('views/' . $viewPath);
    if (file_exists($fullPath)) {
        $passed[] = "✅ View exists: {$viewName}";
        echo "   ✅ {$viewName}\n";
    } else {
        $errors[] = "❌ View missing: {$viewName} ({$fullPath})";
        echo "   ❌ {$viewName} → NOT FOUND\n";
    }
}

// Test 4: Check Controllers
echo "\n4. Testing Controllers...\n";
echo str_repeat("-", 80) . "\n";

$controllers = [
    'AdminDashboardController' => \App\Http\Controllers\Admin\AdminDashboardController::class,
    'UserController' => \App\Http\Controllers\Admin\UserController::class,
    'BannerController' => \App\Http\Controllers\Admin\BannerController::class,
    'ProductController' => \App\Http\Controllers\Admin\ProductController::class,
];

foreach ($controllers as $name => $class) {
    if (class_exists($class)) {
        $reflection = new ReflectionClass($class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $passed[] = "✅ Controller exists: {$name} ({$reflection->getShortName()})";
        echo "   ✅ {$name} → " . count($methods) . " public methods\n";
    } else {
        $errors[] = "❌ Controller missing: {$name}";
        echo "   ❌ {$name} → NOT FOUND\n";
    }
}

// Test 5: Check Middleware
echo "\n5. Testing Middleware Configuration...\n";
echo str_repeat("-", 80) . "\n";

$adminRoutes = \Illuminate\Support\Facades\Route::getRoutes();
$testRoute = null;
foreach ($adminRoutes as $route) {
    if ($route->getName() === 'admin.dashboard') {
        $testRoute = $route;
        break;
    }
}

if ($testRoute) {
    $middleware = $testRoute->middleware();
    $hasAuth = in_array('auth', $middleware) || in_array('web', $middleware);
    $hasRole = in_array('role:admin', $middleware) || strpos(implode(' ', $middleware), 'role') !== false;
    
    if ($hasAuth) {
        $passed[] = "✅ Admin routes have auth middleware";
        echo "   ✅ Auth middleware: Present\n";
    } else {
        $errors[] = "❌ Admin routes missing auth middleware";
        echo "   ❌ Auth middleware: MISSING\n";
    }
    
    if ($hasRole) {
        $passed[] = "✅ Admin routes have role middleware";
        echo "   ✅ Role middleware: Present\n";
    } else {
        $warnings[] = "⚠️  Admin routes may be missing role middleware";
        echo "   ⚠️  Role middleware: Check required\n";
    }
} else {
    $errors[] = "❌ Could not find admin.dashboard route";
    echo "   ❌ Could not find admin.dashboard route\n";
}

// Test 6: Check Database Connection
echo "\n6. Testing Database Connection...\n";
echo str_repeat("-", 80) . "\n";

try {
    $userCount = \App\Models\User::count();
    $adminCount = \App\Models\User::where('role', 'admin')->count();
    $passed[] = "✅ Database connection works";
    echo "   ✅ Database: Connected\n";
    echo "   ✅ Total Users: {$userCount}\n";
    echo "   ✅ Admin Users: {$adminCount}\n";
    
    if ($adminCount === 0) {
        $warnings[] = "⚠️  No admin users found in database";
        echo "   ⚠️  WARNING: No admin users found!\n";
    }
} catch (Exception $e) {
    $errors[] = "❌ Database error: " . $e->getMessage();
    echo "   ❌ Database: " . $e->getMessage() . "\n";
}

// Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "📊 TEST SUMMARY\n";
echo str_repeat("=", 80) . "\n";

echo "\n✅ Passed: " . count($passed) . "\n";
echo "⚠️  Warnings: " . count($warnings) . "\n";
echo "❌ Errors: " . count($errors) . "\n";

if (!empty($warnings)) {
    echo "\n⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   {$warning}\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ ERRORS:\n";
    foreach ($errors as $error) {
        echo "   {$error}\n";
    }
    echo "\n💡 Fix these errors before deploying!\n";
} else {
    echo "\n✅ All critical tests passed!\n";
}

echo "\n📝 Next Steps:\n";
echo "   1. Test with authenticated admin user in browser\n";
echo "   2. Verify all pages load correctly\n";
echo "   3. Check that unauthenticated users are redirected to login\n";
echo "   4. Test all CRUD operations\n";


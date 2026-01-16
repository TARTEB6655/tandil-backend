<?php

/**
 * Test Admin Routes with Authenticated User
 * Simulates logged-in admin user accessing all pages
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "🧪 Testing Admin Routes with Authenticated User\n";
echo str_repeat("=", 80) . "\n\n";

// Get an admin user
$adminUser = \App\Models\User::where('role', 'admin')->first();

if (!$adminUser) {
    echo "❌ No admin user found in database!\n";
    echo "💡 Create an admin user first:\n";
    echo "   php artisan tinker\n";
    echo "   \$user = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'password', 'role' => 'admin']);\n";
    exit(1);
}

echo "✅ Using admin user: {$adminUser->name} ({$adminUser->email})\n\n";

// Authenticate the user
auth()->login($adminUser);

// Define routes to test
$routesToTest = [
    ['url' => '/admin/dashboard', 'name' => 'Dashboard'],
    ['url' => '/admin/users', 'name' => 'Users'],
    ['url' => '/admin/users/create', 'name' => 'Create User'],
    ['url' => '/admin/roles', 'name' => 'Roles'],
    ['url' => '/admin/products', 'name' => 'Products'],
    ['url' => '/admin/products/create', 'name' => 'Create Product'],
    ['url' => '/admin/categories', 'name' => 'Categories'],
    ['url' => '/admin/subscriptions', 'name' => 'Subscriptions'],
    ['url' => '/admin/subscription-plans', 'name' => 'Subscription Plans'],
    ['url' => '/admin/visits', 'name' => 'Visits'],
    ['url' => '/admin/reports', 'name' => 'Reports'],
    ['url' => '/admin/areas', 'name' => 'Areas'],
    ['url' => '/admin/orders', 'name' => 'Orders'],
    ['url' => '/admin/payments', 'name' => 'Payments'],
    ['url' => '/admin/complaints', 'name' => 'Complaints'],
    ['url' => '/admin/hr', 'name' => 'HR'],
    ['url' => '/admin/settings', 'name' => 'Settings'],
    ['url' => '/admin/audit-logs', 'name' => 'Audit Logs'],
    ['url' => '/admin/banners', 'name' => 'Banners'],
    ['url' => '/admin/banners/create', 'name' => 'Create Banner'],
    ['url' => '/admin/tips', 'name' => 'Tips'],
    ['url' => '/admin/notifications', 'name' => 'Notifications'],
];

$passed = [];
$errors = [];

echo "Testing Routes...\n";
echo str_repeat("-", 80) . "\n";

foreach ($routesToTest as $route) {
    try {
        $request = \Illuminate\Http\Request::create($route['url'], 'GET');
        $request->setUserResolver(function () use ($adminUser) {
            return $adminUser;
        });
        
        // Set session for authenticated user
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('_token', 'test-token');
        
        $response = $app->handle($request);
        $statusCode = $response->getStatusCode();
        
        if ($statusCode === 200) {
            $passed[] = $route['name'];
            echo "   ✅ {$route['name']} ({$route['url']}) → 200 OK\n";
        } elseif ($statusCode === 302) {
            $location = $response->headers->get('Location');
            $warnings[] = "{$route['name']} redirects to {$location}";
            echo "   ⚠️  {$route['name']} ({$route['url']}) → 302 Redirect to {$location}\n";
        } else {
            $errors[] = "{$route['name']} returns {$statusCode}";
            echo "   ❌ {$route['name']} ({$route['url']}) → {$statusCode}\n";
        }
    } catch (\Illuminate\Auth\AuthenticationException $e) {
        $errors[] = "{$route['name']} - Authentication failed";
        echo "   ❌ {$route['name']} ({$route['url']}) → Authentication failed\n";
    } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
        $errors[] = "{$route['name']} - Authorization failed";
        echo "   ❌ {$route['name']} ({$route['url']}) → Authorization failed\n";
    } catch (Exception $e) {
        $errors[] = "{$route['name']} - " . $e->getMessage();
        echo "   ❌ {$route['name']} ({$route['url']}) → " . $e->getMessage() . "\n";
    }
}

// Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "📊 RESULTS\n";
echo str_repeat("=", 80) . "\n";
echo "✅ Passed: " . count($passed) . " / " . count($routesToTest) . "\n";
echo "❌ Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\n❌ ERRORS:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
}

echo "\n✅ Test Complete!\n";
echo "\n💡 To test in browser:\n";
echo "   1. Login as admin user\n";
echo "   2. Visit: http://localhost:8000/admin/dashboard\n";
echo "   3. Navigate through all pages\n";


<?php

/**
 * Test script for Banner Features and Admin Dashboard Statistics
 * Run: php test_banner_features.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Banner;
use App\Models\User;
use App\Models\Employee;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Banner Features and Admin Dashboard Statistics\n";
echo str_repeat("=", 60) . "\n\n";

$errors = [];
$success = [];

// Test 1: Banner Model and Migration
echo "1. Testing Banner Model and Database...\n";
try {
    $bannerCount = Banner::count();
    echo "   ✓ Banner table exists (Current count: {$bannerCount})\n";
    $success[] = "Banner model";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Banner model: " . $e->getMessage();
}

// Test 2: Create a test banner
echo "\n2. Testing Banner Creation...\n";
try {
    // Check if test banner exists
    $testBanner = Banner::where('title', 'Test Banner')->first();
    if (!$testBanner) {
        $banner = Banner::create([
            'title' => 'Test Banner',
            'image' => 'banners/test-image.jpg',
            'link' => 'https://example.com',
            'action_type' => 'link',
            'action_value' => 'https://example.com',
            'priority' => 0,
            'is_active' => true,
        ]);
        echo "   ✓ Test banner created (ID: {$banner->id})\n";
        $success[] = "Banner creation";
    } else {
        echo "   ✓ Test banner already exists (ID: {$testBanner->id})\n";
        $success[] = "Banner creation";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Banner creation: " . $e->getMessage();
}

// Test 3: Banner Scopes
echo "\n3. Testing Banner Scopes...\n";
try {
    $activeBanners = Banner::active()->count();
    $orderedBanners = Banner::ordered()->count();
    echo "   ✓ Active banners scope works ({$activeBanners} active)\n";
    echo "   ✓ Ordered banners scope works ({$orderedBanners} total)\n";
    $success[] = "Banner scopes";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Banner scopes: " . $e->getMessage();
}

// Test 4: Banner Image URL Attribute
echo "\n4. Testing Banner Image URL...\n";
try {
    $banner = Banner::first();
    if ($banner) {
        $imageUrl = $banner->image_url;
        echo "   ✓ Image URL attribute works: {$imageUrl}\n";
        $success[] = "Banner image URL";
    } else {
        echo "   ⚠ No banners found to test image URL\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Banner image URL: " . $e->getMessage();
}

// Test 5: Admin Dashboard Statistics - Customers
echo "\n5. Testing Admin Dashboard Statistics...\n";
try {
    $now = \Carbon\Carbon::now();
    $startDate = $now->copy()->startOfMonth();
    $endDate = $now->copy()->endOfMonth();
    
    $customersCurrent = User::where('role', 'client')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->count();
    echo "   ✓ Customers count for current month: {$customersCurrent}\n";
    $success[] = "Customers statistics";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Customers statistics: " . $e->getMessage();
}

// Test 6: Admin Dashboard Statistics - Technicians
try {
    $techniciansCurrent = User::where('role', 'technician')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->count();
    echo "   ✓ Technicians count for current month: {$techniciansCurrent}\n";
    $success[] = "Technicians statistics";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Technicians statistics: " . $e->getMessage();
}

// Test 7: Admin Dashboard Statistics - Employees
try {
    $employeesCurrent = Employee::whereBetween('created_at', [$startDate, $endDate])->count()
        + User::where('role', 'hr')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    echo "   ✓ Employees count for current month: {$employeesCurrent}\n";
    $success[] = "Employees statistics";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Employees statistics: " . $e->getMessage();
}

// Test 8: Test API Route exists
echo "\n6. Testing API Routes...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $bannerRoute = null;
    foreach ($routes as $route) {
        if ($route->uri() === 'api/banners' && $route->methods()[0] === 'GET') {
            $bannerRoute = $route;
            break;
        }
    }
    if ($bannerRoute) {
        echo "   ✓ Banner API route exists: GET /api/banners\n";
        $success[] = "Banner API route";
    } else {
        echo "   ✗ Banner API route not found\n";
        $errors[] = "Banner API route not found";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "API route check: " . $e->getMessage();
}

// Test 9: Test Banner Controller Methods
echo "\n7. Testing Banner Controller...\n";
try {
    $controller = new \App\Http\Controllers\Api\BannerController();
    $reflection = new ReflectionClass($controller);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $hasIndex = false;
    foreach ($methods as $method) {
        if ($method->getName() === 'index') {
            $hasIndex = true;
            break;
        }
    }
    if ($hasIndex) {
        echo "   ✓ Banner API controller has index method\n";
        $success[] = "Banner controller";
    } else {
        echo "   ✗ Banner API controller missing index method\n";
        $errors[] = "Banner controller missing index method";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Banner controller check: " . $e->getMessage();
}

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 Test Summary\n";
echo str_repeat("=", 60) . "\n";
echo "✅ Successful: " . count($success) . " tests\n";
if (count($errors) > 0) {
    echo "❌ Errors: " . count($errors) . " tests\n";
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
} else {
    echo "❌ Errors: 0 tests\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
if (count($errors) === 0) {
    echo "🎉 All tests passed! Everything is working correctly.\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the errors above.\n";
    exit(1);
}


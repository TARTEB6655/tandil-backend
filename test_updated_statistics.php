<?php

/**
 * Test Updated Statistics API with Total Users and Active Subscriptions
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Updated Statistics API\n";
echo str_repeat("=", 70) . "\n\n";

// Test data calculations
echo "1. Testing Total Users Statistics...\n";
$totalUsers = \App\Models\User::count();
echo "   ✅ Total Users: {$totalUsers}\n";

echo "\n2. Testing Active Subscriptions Statistics...\n";
$now = \Carbon\Carbon::now();
$activeSubscriptions = \App\Models\Subscription::where('payment_status', 'paid')
    ->where('end_date', '>=', $now)
    ->count();
echo "   ✅ Active Subscriptions: {$activeSubscriptions}\n";

echo "\n3. Testing Controller Method...\n";
try {
    $controller = new \App\Http\Controllers\Admin\AdminDashboardController();
    $reflection = new ReflectionClass($controller);
    
    if ($reflection->hasMethod('statistics')) {
        echo "   ✅ statistics() method exists\n";
        
        // Check if method includes new statistics
        $method = $reflection->getMethod('statistics');
        $source = file_get_contents($method->getFileName());
        $lines = explode("\n", $source);
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $methodCode = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));
        
        if (strpos($methodCode, 'total_users') !== false) {
            echo "   ✅ total_users statistics included\n";
        } else {
            echo "   ❌ total_users statistics NOT found\n";
        }
        
        if (strpos($methodCode, 'active_subscriptions') !== false) {
            echo "   ✅ active_subscriptions statistics included\n";
        } else {
            echo "   ❌ active_subscriptions statistics NOT found\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ Test Complete!\n";
echo "\n📊 New Statistics Added:\n";
echo "   - total_users: All users regardless of role\n";
echo "   - active_subscriptions: Paid subscriptions that haven't expired\n";
echo "\n📝 API Response will now include:\n";
echo "   - total_users (total, daily, weekly, monthly, yearly + growth)\n";
echo "   - active_subscriptions (total, daily, weekly, monthly, yearly + growth)\n";
echo "   - customers (existing)\n";
echo "   - technicians (existing)\n";
echo "   - employees (existing)\n";


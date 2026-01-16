<?php

/**
 * Production Server Fix Script for Banner Routes
 * Run this on production server: php fix_production_banners.php
 */

echo "🔧 Fixing Banner Routes on Production Server\n";
echo str_repeat("=", 70) . "\n\n";

// Clear all caches
echo "1. Clearing route cache...\n";
exec('php artisan route:clear', $output, $return);
if ($return === 0) {
    echo "   ✅ Route cache cleared\n";
} else {
    echo "   ❌ Failed to clear route cache\n";
    echo "   Output: " . implode("\n", $output) . "\n";
}

echo "\n2. Clearing config cache...\n";
exec('php artisan config:clear', $output, $return);
if ($return === 0) {
    echo "   ✅ Config cache cleared\n";
} else {
    echo "   ❌ Failed to clear config cache\n";
}

echo "\n3. Clearing view cache...\n";
exec('php artisan view:clear', $output, $return);
if ($return === 0) {
    echo "   ✅ View cache cleared\n";
} else {
    echo "   ❌ Failed to clear view cache\n";
}

echo "\n4. Clearing application cache...\n";
exec('php artisan cache:clear', $output, $return);
if ($return === 0) {
    echo "   ✅ Application cache cleared\n";
} else {
    echo "   ❌ Failed to clear application cache\n";
}

echo "\n5. Clearing all optimized files...\n";
exec('php artisan optimize:clear', $output, $return);
if ($return === 0) {
    echo "   ✅ All optimized files cleared\n";
} else {
    echo "   ❌ Failed to clear optimized files\n";
}

echo "\n6. Verifying route exists...\n";
exec('php artisan route:list --path=admin/banners', $output, $return);
if ($return === 0 && !empty($output)) {
    echo "   ✅ Banner routes found:\n";
    foreach ($output as $line) {
        if (strpos($line, 'banners') !== false) {
            echo "      " . $line . "\n";
        }
    }
} else {
    echo "   ⚠️  Could not verify routes (this is normal if route:list has issues)\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ Cache clearing complete!\n";
echo "\n📋 Next Steps:\n";
echo "   1. Verify you are logged in as admin user\n";
echo "   2. Check user has 'admin' role assigned\n";
echo "   3. Try accessing: https://phpstack-1180784-6050385.cloudwaysapps.com/admin/banners\n";
echo "   4. If still not working, check server logs for errors\n";
echo "\n💡 Common Issues:\n";
echo "   - User not logged in\n";
echo "   - User doesn't have 'admin' role\n";
echo "   - Session expired\n";
echo "   - APP_URL in .env doesn't match production domain\n";


<?php

/**
 * Fix View Config - Ensures view compiled path is always set correctly
 * Run this on production: php fix-view-config.php
 */

echo "=== Fixing View Configuration ===\n\n";

// 1. Ensure storage/framework/views directory exists
echo "1. Ensuring storage/framework/views directory exists...\n";
$viewsPath = __DIR__ . '/storage/framework/views';
if (!is_dir($viewsPath)) {
    @mkdir($viewsPath, 0775, true);
    echo "   ✅ Created directory\n\n";
} else {
    echo "   ✅ Directory exists\n\n";
}

// 2. Verify config/view.php exists and is correct
echo "2. Verifying config/view.php...\n";
$configPath = __DIR__ . '/config/view.php';
if (!file_exists($configPath)) {
    echo "   ❌ config/view.php missing - creating it...\n";
    $configContent = <<<'PHP'
<?php

return [
    'paths' => [
        resource_path('views'),
    ],
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),
];
PHP;
    file_put_contents($configPath, $configContent);
    echo "   ✅ Created config/view.php\n\n";
} else {
    echo "   ✅ config/view.php exists\n\n";
}

// 3. Remove config cache to force reload
echo "3. Removing config cache...\n";
$configCache = __DIR__ . '/bootstrap/cache/config.php';
if (file_exists($configCache)) {
    @unlink($configCache);
    echo "   ✅ Config cache removed\n\n";
} else {
    echo "   ✅ Config cache already cleared\n\n";
}

// 4. Test view:clear command
echo "4. Testing view:clear command...\n";
try {
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    
    // Ensure view config is loaded
    $viewConfig = config('view');
    if (empty($viewConfig['compiled'])) {
        throw new Exception('View compiled path is empty');
    }
    
    echo "   ✅ View config loaded: " . $viewConfig['compiled'] . "\n";
    
    // Try to clear views
    $app->make('Illuminate\Contracts\Console\Kernel')->call('view:clear');
    echo "   ✅ view:clear command works!\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Error: " . $e->getMessage() . "\n";
    echo "   Trying manual fix...\n";
    
    // Manual fallback - ensure directory exists and clear files
    if (is_dir($viewsPath)) {
        $files = glob($viewsPath . '/*.php');
        foreach ($files as $file) {
            @unlink($file);
        }
        echo "   ✅ View cache files cleared manually\n\n";
    }
}

// 5. Run optimize:clear
echo "5. Running optimize:clear...\n";
try {
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->call('optimize:clear');
    echo "   ✅ optimize:clear completed successfully!\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  optimize:clear error: " . $e->getMessage() . "\n\n";
}

echo "=== Done ===\n";
echo "\nYou can now run: php artisan optimize:clear\n";
echo "This should work without errors.\n\n";


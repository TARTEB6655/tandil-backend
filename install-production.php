<?php

/**
 * Production Installation Script for Tandil Backend
 * Run: php install-production.php
 */

echo "==========================================\n";
echo "  Tandil Backend - Production Installation\n";
echo "==========================================\n\n";

$errors = [];
$warnings = [];

// Step 1: Check PHP version
echo "[1/10] Checking PHP version...\n";
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $errors[] = "PHP 8.2+ required. Found: " . PHP_VERSION;
    echo "  ❌ PHP version too old\n\n";
} else {
    echo "  ✅ PHP " . PHP_VERSION . " detected\n\n";
}

// Step 2: Check required extensions
echo "[2/10] Checking PHP extensions...\n";
$required = ['pdo', 'pdo_mysql', 'mbstring', 'xml', 'ctype', 'json', 'bcmath', 'openssl', 'fileinfo', 'tokenizer'];
$missing = [];
foreach ($required as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
if (!empty($missing)) {
    $errors[] = "Missing PHP extensions: " . implode(', ', $missing);
    echo "  ❌ Missing extensions: " . implode(', ', $missing) . "\n\n";
} else {
    echo "  ✅ All required extensions installed\n\n";
}

// Step 3: Check Composer
echo "[3/10] Checking Composer...\n";
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    $errors[] = "Composer dependencies not installed. Run: composer install";
    echo "  ❌ Composer dependencies missing\n\n";
} else {
    echo "  ✅ Composer dependencies found\n\n";
}

// Step 4: Setup .env file
echo "[4/10] Setting up environment file...\n";
if (!file_exists(__DIR__ . '/.env')) {
    if (file_exists(__DIR__ . '/.env.example')) {
        copy(__DIR__ . '/.env.example', __DIR__ . '/.env');
        echo "  ✅ Created .env from .env.example\n\n";
    } else {
        // Create basic .env
        $envContent = <<<'EOF'
APP_NAME=Tandil
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
EOF;
        file_put_contents(__DIR__ . '/.env', $envContent);
        echo "  ✅ Created basic .env file\n\n";
    }
} else {
    echo "  ✅ .env file already exists\n\n";
}

// Step 5: Generate application key
echo "[5/10] Generating application key...\n";
$envFile = file_get_contents(__DIR__ . '/.env');
if (strpos($envFile, 'APP_KEY=base64:') === false && strpos($envFile, 'APP_KEY=') !== false) {
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->call('key:generate', ['--force' => true]);
    echo "  ✅ Application key generated\n\n";
} else {
    echo "  ✅ Application key already exists\n\n";
}

// Step 6: Create directories
echo "[6/10] Creating storage directories...\n";
$dirs = [
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}
echo "  ✅ Storage directories created\n\n";

// Step 7: Clear caches
echo "[7/10] Clearing all caches...\n";
// Remove config cache
$cacheFiles = [
    'bootstrap/cache/config.php',
    'bootstrap/cache/routes.php',
    'bootstrap/cache/services.php',
    'bootstrap/cache/packages.php',
];
foreach ($cacheFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        @unlink($path);
    }
}
// Clear view cache
$viewPath = __DIR__ . '/storage/framework/views';
if (is_dir($viewPath)) {
    $files = glob($viewPath . '/*.php');
    foreach ($files as $file) {
        @unlink($file);
    }
}
echo "  ✅ Caches cleared\n\n";

// Step 8: Create storage link
echo "[8/10] Creating storage symlink...\n";
if (!is_link(__DIR__ . '/public/storage')) {
    try {
        require __DIR__.'/vendor/autoload.php';
        $app = require_once __DIR__.'/bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->call('storage:link');
        echo "  ✅ Storage link created\n\n";
    } catch (\Exception $e) {
        $warnings[] = "Could not create storage link: " . $e->getMessage();
        echo "  ⚠️  Storage link: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "  ✅ Storage link already exists\n\n";
}

// Step 9: Final optimization
echo "[9/10] Running final optimization...\n";
try {
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->call('config:clear');
    $app->make('Illuminate\Contracts\Console\Kernel')->call('route:clear');
    $app->make('Illuminate\Contracts\Console\Kernel')->call('view:clear');
    echo "  ✅ Optimization completed\n\n";
} catch (\Exception $e) {
    $warnings[] = "Optimization warning: " . $e->getMessage();
    echo "  ⚠️  Optimization: " . $e->getMessage() . "\n\n";
}

// Step 10: Summary
echo "[10/10] Installation Summary\n";
echo "==========================================\n";
if (empty($errors)) {
    echo "✅ Installation Complete!\n\n";
    echo "Next steps:\n";
    echo "1. Edit .env file and set your database credentials:\n";
    echo "   - DB_DATABASE=your_database_name\n";
    echo "   - DB_USERNAME=your_database_user\n";
    echo "   - DB_PASSWORD=your_database_password\n";
    echo "   - APP_URL=your_domain_url\n\n";
    echo "2. Run migrations:\n";
    echo "   php artisan migrate --force\n\n";
    echo "3. Seed database (optional):\n";
    echo "   php artisan db:seed --force\n\n";
    echo "4. Optimize for production:\n";
    echo "   php artisan config:cache\n";
    echo "   php artisan route:cache\n";
    echo "   php artisan view:cache\n\n";
} else {
    echo "❌ Installation failed with errors:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
    echo "\n";
    exit(1);
}

if (!empty($warnings)) {
    echo "⚠️  Warnings:\n";
    foreach ($warnings as $warning) {
        echo "   - $warning\n";
    }
    echo "\n";
}


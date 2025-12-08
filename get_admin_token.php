<?php

/**
 * Quick script to get admin token
 * Run: php get_admin_token.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== Get Admin Token ===\n\n";

// Find admin user
$admin = User::where('role', 'admin')->first();

if (!$admin) {
    echo "❌ No admin user found. Creating one...\n";
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);
    $admin->assignRole('admin');
    echo "✅ Admin user created!\n\n";
}

echo "Admin User:\n";
echo "  ID: {$admin->id}\n";
echo "  Name: {$admin->name}\n";
echo "  Email: {$admin->email}\n";
echo "  Role: {$admin->role}\n\n";

// Create or get existing token
$token = $admin->createToken('api_token')->plainTextToken;

echo "✅ Admin Token:\n";
echo $token . "\n\n";

echo "Use this token in Postman:\n";
echo "Authorization: Bearer {$token}\n\n";

echo "Now you can update complaint ID 19!\n";


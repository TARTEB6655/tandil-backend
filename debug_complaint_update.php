<?php

/**
 * Debug script to check complaint update authorization
 * Run: php debug_complaint_update.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Complaint;

echo "=== Debug Complaint Update Authorization ===\n\n";

// Get all users with their roles
echo "Users and their roles:\n";
$users = User::with('roles')->get(['id', 'name', 'email', 'role']);

foreach ($users as $user) {
    $spatieRoles = $user->roles->pluck('name')->implode(', ');
    echo "ID: {$user->id}, Name: {$user->name}, Email: {$user->email}\n";
    echo "  Role column: {$user->role}\n";
    echo "  Spatie roles: " . ($spatieRoles ?: 'NONE') . "\n";
    echo "  hasAnyRole(['admin', 'area_manager', 'supervisor']): " . ($user->hasAnyRole(['admin', 'area_manager', 'supervisor']) ? 'YES' : 'NO') . "\n";
    echo "\n";
}

// Check complaints
echo "=== Complaints ===\n";
$complaints = Complaint::all(['id', 'visit_id', 'client_id', 'status']);
if ($complaints->isEmpty()) {
    echo "No complaints found.\n";
} else {
    foreach ($complaints as $c) {
        echo "ID: {$c->id}, Visit ID: {$c->visit_id}, Client ID: {$c->client_id}, Status: {$c->status}\n";
    }
}

echo "\n✅ Done!\n";


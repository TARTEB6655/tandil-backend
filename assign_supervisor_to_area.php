<?php

/**
 * Quick script to assign supervisor to area
 * Run: php assign_supervisor_to_area.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Area;

echo "=== Assign Supervisor to Area ===\n\n";

// List all supervisors first
echo "Available supervisors:\n";
$supervisors = User::where('role', 'supervisor')->get(['id', 'name', 'email']);
if ($supervisors->isEmpty()) {
    echo "  ❌ No supervisors found. Please register a supervisor first!\n";
    echo "\nTo register: POST /api/auth/register with role: 'supervisor'\n\n";
    exit(1);
}
foreach ($supervisors as $s) {
    echo "  ID: {$s->id}, Name: {$s->name}, Email: {$s->email}\n";
}

// Get supervisor (change ID to your supervisor user ID)
$supervisorId = $supervisors->first()->id; // Use first supervisor, or change to specific ID
echo "\nUsing supervisor ID: {$supervisorId}\n";
$supervisor = User::find($supervisorId);

if (!$supervisor) {
    echo "❌ Supervisor not found with ID: {$supervisorId}\n";
    exit(1);
}

if ($supervisor->role !== 'supervisor') {
    echo "❌ User {$supervisorId} is not a supervisor (role: {$supervisor->role})\n";
    exit(1);
}

echo "Supervisor: {$supervisor->name} (ID: {$supervisor->id})\n\n";

// Get or create area (change ID or name as needed)
$areaId = 1; // Change this to your area ID, or use name below
$area = Area::find($areaId);

// Or create area if it doesn't exist:
// $area = Area::firstOrCreate(
//     ['name' => 'Dubai'],
//     ['description' => 'Service area for Dubai']
// );

if (!$area) {
    echo "❌ Area not found with ID: {$areaId}\n";
    echo "Available areas:\n";
    $areas = Area::all(['id', 'name']);
    foreach ($areas as $a) {
        echo "  ID: {$a->id}, Name: {$a->name}\n";
    }
    exit(1);
}

echo "Area: {$area->name} (ID: {$area->id})\n\n";

// Check if already assigned
$isAssigned = $area->supervisors()->where('users.id', $supervisor->id)->exists();

if ($isAssigned) {
    echo "✅ Supervisor is already assigned to this area\n";
} else {
    // Assign supervisor to area
    $area->supervisors()->syncWithoutDetaching([$supervisor->id]);
    echo "✅ Supervisor assigned to area successfully!\n";
}

echo "\n=== Verification ===\n";
$supervisedAreas = $supervisor->supervisedAreas;
echo "Supervisor now supervises " . $supervisedAreas->count() . " area(s):\n";
foreach ($supervisedAreas as $a) {
    echo "  - {$a->name} (ID: {$a->id})\n";
}

echo "\n✅ Done!\n";
echo "\nNext steps:\n";
echo "1. Create a visit with area_id: {$area->id}\n";
echo "2. Test: GET /api/supervisor/visits (should show visits now)\n";


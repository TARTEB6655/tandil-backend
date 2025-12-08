<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Area;

$supervisorId = 67;
$supervisor = User::find($supervisorId);

if (!$supervisor) {
    echo "❌ Supervisor not found with ID: {$supervisorId}\n";
    exit(1);
}

echo "Supervisor: {$supervisor->name} (ID: {$supervisor->id})\n";
echo "Email: {$supervisor->email}\n\n";

$areas = $supervisor->supervisedAreas;
echo "Currently assigned to " . $areas->count() . " area(s):\n";
foreach ($areas as $a) {
    echo "  - {$a->name} (ID: {$a->id})\n";
}

if ($areas->isEmpty()) {
    echo "\n⚠️  No areas assigned! Assigning to area 1 (Dubai)...\n";
    $area = Area::find(1);
    if ($area) {
        $area->supervisors()->syncWithoutDetaching([$supervisorId]);
        echo "✅ Supervisor assigned to area 1!\n";
    } else {
        echo "❌ Area 1 not found\n";
        exit(1);
    }
} else {
    $hasArea1 = $areas->contains(function($area) {
        return $area->id == 1;
    });
    
    if (!$hasArea1) {
        echo "\n⚠️  Supervisor not assigned to area 1. Assigning...\n";
        $area = Area::find(1);
        if ($area) {
            $area->supervisors()->syncWithoutDetaching([$supervisorId]);
            echo "✅ Supervisor assigned to area 1!\n";
        }
    } else {
        echo "\n✅ Supervisor is already assigned to area 1!\n";
    }
}

echo "\n=== Verification ===\n";
$supervisedAreas = $supervisor->fresh()->supervisedAreas;
echo "Supervisor now supervises " . $supervisedAreas->count() . " area(s):\n";
foreach ($supervisedAreas as $a) {
    echo "  - {$a->name} (ID: {$a->id})\n";
}

echo "\n✅ Done!\n";
echo "\nNow test: GET /api/supervisor/visits (should show visit ID 69)\n";


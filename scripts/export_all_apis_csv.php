<?php
/**
 * Export all APIs from Postman collection + phase2 list to a single CSV.
 * Run: php scripts/export_all_apis_csv.php
 */

$postmanPath = __DIR__ . '/../postman/tandil_backend.json';
$phase2Path = __DIR__ . '/../docs/remaining-apis-phase2-with-payments.csv';
$outputPath = __DIR__ . '/../docs/all-dashboards-apis-status.csv';

$postman = json_decode(file_get_contents($postmanPath), true);
if (!$postman) {
    fwrite(STDERR, "Failed to load Postman JSON\n");
    exit(1);
}

$apis = [];
$sno = 0;

// Map top-level folder name to Dashboard name
$folderToDashboard = [
    '1. Health Check - START HERE' => 'System',
    '2. Authentication' => 'System',
    '3. Client Dashboard – Profile' => 'Client',
    '4. Client Dashboard – Subscriptions' => 'Client',
    '5. Client Dashboard – Banners (Public)' => 'Client',
    '6. Client Dashboard – Visits' => 'Client',
    '7. Client Dashboard – Reports' => 'Client',
    '8. Client Dashboard – Shop & Orders' => 'Client',
    '9. Technician Dashboard – All APIs (Technician Only)' => 'Technician',
    '10. Supervisor Dashboard – All APIs' => 'Supervisor',
    '11. Admin Dashboard – Stats, Users & HR' => 'Admin',
    '12. Admin Dashboard – Reports Management' => 'Admin',
    '13. Admin Dashboard – Settings (Mobile)' => 'Admin',
    '14. Other Modules' => 'Other',
];

function extractApis(array $items, array &$apis, int &$sno, string $dashboard, string $module, array $folderToDashboard): void {
    foreach ($items as $item) {
        $name = $item['name'] ?? '';
        if (isset($item['request'])) {
            $req = $item['request'];
            $method = strtoupper($req['method'] ?? 'GET');
            $url = $req['url'] ?? [];
            $raw = is_array($url) ? ($url['raw'] ?? '') : (string) $url;
            if (preg_match('#\{\{base_url\}\}(/api/[^\s?]+)#', $raw, $m)) {
                $endpoint = preg_replace('#/\{\{[^}]+\}\}#', '/{id}', $m[1]);
                $endpoint = preg_replace('#\?.*$#', '', $endpoint);
                $apis[] = [
                    'sno' => ++$sno,
                    'dashboard' => $dashboard,
                    'module' => $module ?: $name,
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => 'Done',
                ];
            }
            continue;
        }
        if (isset($item['item'])) {
            $subDashboard = $folderToDashboard[$name] ?? $dashboard;
            $subModule = ($dashboard === $subDashboard) ? $name : $module;
            extractApis($item['item'], $apis, $sno, $subDashboard, $subModule, $folderToDashboard);
        }
    }
}

foreach ($postman['item'] ?? [] as $top) {
    $name = $top['name'] ?? '';
    $dashboard = $folderToDashboard[$name] ?? 'Other';
    $module = $name;
    if (isset($top['item'])) {
        extractApis($top['item'], $apis, $sno, $dashboard, $module, $folderToDashboard);
    }
}

// Add phase2 APIs that are not already in the list (by method+endpoint)
$seen = [];
foreach ($apis as $row) {
    $key = $row['method'] . ' ' . $row['endpoint'];
    $seen[$key] = true;
}

if (($fh = fopen($phase2Path, 'r')) !== false) {
    $header = fgetcsv($fh);
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) < 6) continue;
        list($sNo, $dashboard, $module, $method, $endpoint, $status) = $row;
        $endpoint = preg_replace('#/\{\{[^}]+\}\}#', '/{id}', $endpoint);
        $key = strtoupper(trim($method)) . ' ' . trim($endpoint);
        if (!isset($seen[$key])) {
            $apis[] = [
                'sno' => ++$sno,
                'dashboard' => trim($dashboard),
                'module' => trim($module),
                'method' => strtoupper(trim($method)),
                'endpoint' => trim($endpoint),
                'status' => 'Pending',
            ];
            $seen[$key] = true;
        }
    }
    fclose($fh);
}

// Dashboard order: 1.System, 2.Client, 3.Technician, 4.Supervisor, 5.Admin, 6.Area Manager, 7.HR Manager, 8.Other
$dashboardOrder = [
    'System' => 1,
    'Client' => 2,
    'Technician' => 3,
    'Supervisor' => 4,
    'Admin' => 5,
    'Area Manager' => 6,
    'HR Manager' => 7,
    'Other' => 8,
];
$methodOrder = ['GET' => 1, 'POST' => 2, 'PUT' => 3, 'PATCH' => 4, 'DELETE' => 5];

// Shorten module name: take part after " – " or "N. " (e.g. "3. Client Dashboard – Profile" -> "Profile")
$shortModuleName = function (string $name): string {
    if (preg_match('/\s*–\s*(.+)$/', $name, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/^\d+\.\s*(.+)$/', $name, $m)) {
        return trim($m[1]);
    }
    return $name;
};

// Build unique modules per dashboard, then sort by short name for consistent Module 1, 2, 3... order
$moduleList = [];
foreach ($apis as $row) {
    $d = $row['dashboard'];
    $m = $row['module'];
    if (!isset($moduleList[$d])) {
        $moduleList[$d] = [];
    }
    if (!in_array($m, $moduleList[$d], true)) {
        $moduleList[$d][] = $m;
    }
}
foreach ($moduleList as $d => $list) {
    usort($moduleList[$d], function ($a, $b) use ($shortModuleName) {
        return strcasecmp($shortModuleName($a), $shortModuleName($b));
    });
}

// Assign module number (1, 2, 3...) and short label per dashboard
foreach ($apis as $i => $row) {
    $d = $row['dashboard'];
    $m = $row['module'];
    $list = $moduleList[$d] ?? [];
    $idx = array_search($m, $list, true);
    $num = ($idx === false) ? 0 : $idx + 1;
    $short = $shortModuleName($m);
    $apis[$i]['_moduleNum'] = $num;
    $apis[$i]['module_label'] = $num . '. ' . $short;
}

// Sort: Dashboard order -> Module number -> Method -> Endpoint
usort($apis, function ($a, $b) use ($dashboardOrder, $methodOrder) {
    $da = $dashboardOrder[$a['dashboard']] ?? 99;
    $db = $dashboardOrder[$b['dashboard']] ?? 99;
    if ($da !== $db) return $da <=> $db;

    if ($a['_moduleNum'] !== $b['_moduleNum']) return $a['_moduleNum'] <=> $b['_moduleNum'];

    $ja = $methodOrder[$a['method']] ?? 99;
    $jb = $methodOrder[$b['method']] ?? 99;
    if ($ja !== $jb) return $ja <=> $jb;

    return strcmp($a['endpoint'], $b['endpoint']);
});

// Re-number SNo 1, 2, 3... and use module_label in output
foreach ($apis as $i => $row) {
    $apis[$i]['sno'] = $i + 1;
}

$out = @fopen($outputPath, 'w');
if (!$out) {
    $outputPath = __DIR__ . '/../docs/all-dashboards-apis-status-ordered.csv';
    $out = fopen($outputPath, 'w');
}
if ($out) {
    fputcsv($out, ['SNo', 'Dashboard', 'Module', 'Method', 'Endpoint', 'Status']);
    foreach ($apis as $row) {
        fputcsv($out, [
            $row['sno'],
            $row['dashboard'],
            $row['module_label'],
            $row['method'],
            $row['endpoint'],
            $row['status'],
        ]);
    }
    fclose($out);
}
echo "Written " . count($apis) . " APIs to " . $outputPath . "\n";

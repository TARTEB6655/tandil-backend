<?php

/**
 * Reads postman/tandil_backend.json and writes a deduplicated API index CSV.
 * Dedupe key: HTTP method + URL path (no host, no query string).
 * Adds Audience + Postman root folder, preamble for clients, section separator rows.
 *
 * Usage: php scripts/export_postman_collection_to_csv.php [path/to/collection.json] [output.csv]
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$input = $argv[1] ?? $root . '/postman/tandil_backend.json';
$output = $argv[2] ?? $root . '/postman/tandil_backend_api_index.csv';

if (!is_readable($input)) {
    fwrite(STDERR, "Cannot read: {$input}\n");
    exit(1);
}

$json = json_decode((string) file_get_contents($input), true);
if (!is_array($json) || !isset($json['item']) || !is_array($json['item'])) {
    fwrite(STDERR, "Invalid Postman collection JSON.\n");
    exit(1);
}

/** @param  array<string, mixed>  $url */
function pathFromUrl(array $url): string
{
    if (!empty($url['path']) && is_array($url['path'])) {
        $segments = [];
        foreach ($url['path'] as $seg) {
            if ($seg === null || $seg === '') {
                continue;
            }
            $segments[] = (string) $seg;
        }

        return '/' . implode('/', $segments);
    }

    if (!empty($url['raw']) && is_string($url['raw'])) {
        $raw = $url['raw'];
        if (preg_match('#https?://[^/]+(/[^?#]*)#', $raw, $m)) {
            return $m[1] === '' ? '/' : $m[1];
        }
        $raw = preg_replace('#^\{\{base_url\}\}#i', '', $raw) ?? $raw;
        $path = parse_url('https://_' . $raw, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            return $path === '/' ? '/' : preg_replace('#^//#', '/', $path) ?? '/';
        }
    }

    return '';
}

/**
 * First Postman folder under collection root (e.g. "8. Client Dashboard – Shop & Orders").
 *
 * @param  array<int, string>  $moduleTrail
 */
function postmanRootFolder(array $moduleTrail): string
{
    return $moduleTrail[0] ?? '';
}

/**
 * Who primarily uses this block (matches numbered folders in tandil_backend.json).
 */
function audienceLabelAndRank(string $rootFolder): array
{
    if ($rootFolder === '') {
        return ['Other / ungrouped', 99];
    }
    if (preg_match('/^(\d+)\.\s*/', $rootFolder, $m)) {
        $n = (int) $m[1];
        if ($n === 1) {
            return ['Shared — start here (health, debug, wallet hub under folder 1)', 1];
        }
        if ($n === 2) {
            return ['Authentication — all roles (register, login, …)', 2];
        }
        if ($n >= 3 && $n <= 8) {
            return ['Client — folders 3–8 in Postman (profile → shop)', 3];
        }
        if ($n === 9) {
            return ['Technician — folder 9', 4];
        }
        if ($n === 10) {
            return ['Supervisor — folder 10', 5];
        }
        if ($n === 11) {
            return ['Area Manager — folder 11', 6];
        }
        if ($n === 12) {
            return ['HR Manager — folder 12', 7];
        }
        if ($n >= 13 && $n <= 16) {
            return ['Admin — folders 13–16 in Postman', 8];
        }
        if ($n === 17) {
            return ['Cross-role / other modules — folder 17', 9];
        }
    }

    return ['Other / ungrouped', 99];
}

// Collect unique APIs (first occurrence wins); keep discovery order index for stable sort within group
$orderedRows = [];
$seenKey = [];
$totalRequestNodes = 0;
$skippedDuplicates = 0;
$skippedNoPath = 0;
$discoveryIndex = 0;

$walk = function (array $items, array $moduleTrail) use (&$walk, &$orderedRows, &$seenKey, &$totalRequestNodes, &$skippedDuplicates, &$skippedNoPath, &$discoveryIndex): void {
    foreach ($items as $item) {
        $folderName = isset($item['name']) ? (string) $item['name'] : '';
        $nextTrail = $moduleTrail;
        if ($folderName !== '' && !empty($item['item'])) {
            $nextTrail[] = $folderName;
        }

        if (!empty($item['item']) && is_array($item['item'])) {
            $walk($item['item'], $nextTrail);
        }

        if (!empty($item['request']) && is_array($item['request'])) {
            $req = $item['request'];
            $method = isset($req['method']) ? strtoupper((string) $req['method']) : '';
            if ($method === '') {
                continue;
            }
            $path = pathFromUrl($req['url'] ?? []);
            if ($path === '') {
                $skippedNoPath++;

                continue;
            }
            $totalRequestNodes++;
            $key = $method . ' ' . $path;
            if (isset($seenKey[$key])) {
                $skippedDuplicates++;

                continue;
            }
            $seenKey[$key] = true;

            $rootFolder = postmanRootFolder($moduleTrail);
            [$audience, $audienceRank] = audienceLabelAndRank($rootFolder);
            $status = !empty($item['disabled']) ? 'Disabled' : 'Active';

            $orderedRows[] = [
                'key' => $key,
                'audience_rank' => $audienceRank,
                'audience' => $audience,
                'postman_root' => $rootFolder,
                'name' => isset($item['name']) ? (string) $item['name'] : '',
                'module' => implode(' > ', $moduleTrail),
                'method' => $method,
                'endpoint' => $path,
                'status' => $status,
                'discovery' => $discoveryIndex++,
            ];
        }
    }
};

$walk($json['item'], []);

usort($orderedRows, static function (array $a, array $b): int {
    if ($a['audience_rank'] !== $b['audience_rank']) {
        return $a['audience_rank'] <=> $b['audience_rank'];
    }
    if ($a['postman_root'] !== $b['postman_root']) {
        return strcmp($a['postman_root'], $b['postman_root']);
    }

    return $a['discovery'] <=> $b['discovery'];
});

$fp = fopen($output, 'wb');
if ($fp === false) {
    fwrite(STDERR, "Cannot write: {$output}\n");
    exit(1);
}

fprintf($fp, "\xEF\xBB\xBF");

$e = ['', '', '', '', '', '', ''];
$preamble = [
    array_merge(['TANDIL BACKEND — API INDEX (each row = one unique API: METHOD + path; duplicates from Postman removed)'], $e),
    array_merge([''], $e),
    array_merge(['HOW TO USE THIS FILE'], $e),
    array_merge(['Sort or filter by column "Audience" for Client, Admin, Supervisor, etc. Use "Postman root folder" to jump to the same top folder in Postman.'], $e),
    array_merge([''], $e),
    array_merge(['WHERE EACH USER TYPE STARTS IN POSTMAN (folder numbers match tandil_backend.json)'], $e),
    array_merge(['Shared / entry', 'Folder "1. Health Check - START HERE" (health check first; wallet summaries listed here are shared across roles).'], $e),
    array_merge(['All roles', 'Folder "2. Authentication" (register, login, password, email verification).'], $e),
    array_merge(['Client', 'Folders "3." through "8." (profile, subscriptions, banners, visits, reports, shop & orders; payments under "8.").'], $e),
    array_merge(['Technician', 'Folder "9. Technician Dashboard – All APIs".'], $e),
    array_merge(['Supervisor', 'Folder "10. Supervisor Dashboard – All APIs".'], $e),
    array_merge(['Area Manager', 'Folder "11. Area Manager Dashboard – All APIs".'], $e),
    array_merge(['HR Manager', 'Folder "12. HR Manager Dashboard – All APIs".'], $e),
    array_merge(['Admin', 'Folders "13." through "16." (stats & users, reports, support tickets, settings).'], $e),
    array_merge(['Cross-role / other', 'Folder "17. Other Modules" (e.g. notifications hub, shared utilities).'], $e),
    array_merge([''], $e),
];

foreach ($preamble as $row) {
    fputcsv($fp, $row);
}

$headers = ['Sr. No.', 'Audience', 'Postman root folder', 'Name', 'Module (full path in Postman)', 'Methods', 'Endpoint', 'Status'];
fputcsv($fp, $headers);

$n = 1;
$prevAudience = null;
foreach ($orderedRows as $r) {
    if ($prevAudience !== null && $prevAudience !== $r['audience']) {
        fputcsv($fp, ['', '--- ' . $r['audience'] . ' ---', '', '', '', '', '', '']);
    }
    $prevAudience = $r['audience'];

    fputcsv($fp, [
        $n++,
        $r['audience'],
        $r['postman_root'],
        $r['name'],
        $r['module'],
        $r['method'],
        $r['endpoint'],
        $r['status'],
    ]);
}

fclose($fp);

$count = count($orderedRows);
echo "Wrote {$count} unique APIs to {$output}\n";
echo "Postman request nodes (with path): {$totalRequestNodes}; duplicates skipped: {$skippedDuplicates}; skipped (no path): {$skippedNoPath}\n";

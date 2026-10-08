<?php

/**
 * PERMANENT Postman sidebar order fix.
 *
 * ROOT BUG (Postman app):
 *   On import/open, Postman silently moves FOLDERS above REQUESTS in every
 *   sibling group. Our old shape was R,R,R… then F (e.g. Legal last as 20).
 *   After import the folder jumps to the top → numbers look like 20,01,02… / random.
 *   See: https://github.com/postmanlabs/postman-app-support/issues/12173
 *
 * FIX:
 *   1) Never mix requests + folders as siblings.
 *      If mixed: wrap all requests into one folder ("… APIs"), siblings = folders only.
 *   2) Order siblings by integer number, renumber 01, 02, 03… (ASCII-safe).
 *   3) Strip item ids. New collection identity every run.
 *
 * Usage:  php scripts/force_sort_postman_collection.php
 * Verify: php scripts/smoke_postman_number_order.php   ← must PASS
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$proofPath = __DIR__.'/../postman/SIDEBAR_ORDER_PROOF.txt';
$smokeReportPath = __DIR__.'/../postman/NUMBER_ORDER_SMOKE_REPORT.txt';
$collection = json_decode((string) file_get_contents($path), true);
if (! is_array($collection)) {
    fwrite(STDERR, 'Invalid JSON: '.json_last_error_msg()."\n");
    exit(1);
}

const PAD = 2;

function stripAllPrefixes(string $name): string
{
    $title = trim($name);
    // Only strip ONE leading order prefix (01. or 01.02.) — never loop (that ate role names).
    if (preg_match('/^(\d+(?:\.\d+)*)\.\s+(.+)$/u', $title, $m)) {
        $title = trim($m[2]);
    }

    return $title !== '' ? $title : trim($name);
}

/** Wrappers created by this script only — not "Technician Dashboard – All APIs" containers. */
function isSyntheticApisWrapper(array $folder): bool
{
    $t = stripAllPrefixes((string) ($folder['name'] ?? ''));
    if (strcasecmp($t, 'APIs') === 0 || strcasecmp($t, 'Settings APIs') === 0) {
        return true;
    }
    $desc = (string) ($folder['description'] ?? '');

    return str_contains($desc, 'Wrapped so Postman')
        || str_contains($desc, 'Requests wrapped into a folder');
}

function numberPrefixInt(string $name): int
{
    if (preg_match('/^([0-9]+)/u', trim($name), $m)) {
        return (int) $m[1];
    }

    return PHP_INT_MAX;
}

function pad(int $n): string
{
    return str_pad((string) $n, PAD, '0', STR_PAD_LEFT);
}

function newUuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff)
    );
}

function isFolder(array $it): bool
{
    return isset($it['item']) && is_array($it['item']);
}

/**
 * Settings (Mobile): APIs folder first, Legal second (both folders → Postman-safe).
 *
 * @param  list<array<string, mixed>>  $items
 * @return list<array<string, mixed>>
 */
function orderSettingsMobile(array $items): array
{
    $apiOrder = [
        'Get All Settings',
        'Get System Settings',
        'Update System Settings',
        'Get Theme',
        'Update Theme',
        'Get Language',
        'Update Language',
        'Get Payment Settings',
        'Update Payment Settings',
        'Get Shop Settings (Shipping & Tax)',
        'Update Shop Settings (Shipping & Tax)',
        'Get Instant Order Fee',
        'Update Instant Order Fee',
        'Get Service Pricing Settings',
        'Update Service Pricing Settings (form-data)',
        'Update Service Pricing Settings (PUT form-data)',
        'Get Tree Palm Pricing Settings',
        'Update Tree Palm Pricing Settings (form-data)',
        'Export Data',
    ];

    $requests = [];
    $folders = [];
    foreach ($items as $it) {
        if (isFolder($it)) {
            // Unwrap previous "Settings APIs" / "APIs" wrapper if re-running
            $t = stripAllPrefixes((string) ($it['name'] ?? ''));
            if (strcasecmp($t, 'Settings APIs') === 0 || strcasecmp($t, 'APIs') === 0) {
                foreach ($it['item'] as $child) {
                    if (isFolder($child)) {
                        $folders[] = $child;
                    } else {
                        $requests[] = $child;
                    }
                }
            } else {
                $folders[] = $it;
            }
        } else {
            $requests[] = $it;
        }
    }

    $byTitle = [];
    foreach ($requests as $it) {
        $byTitle[stripAllPrefixes((string) ($it['name'] ?? ''))] = $it;
    }
    $orderedReqs = [];
    foreach ($apiOrder as $title) {
        if (isset($byTitle[$title])) {
            $orderedReqs[] = $byTitle[$title];
            unset($byTitle[$title]);
        }
    }
    foreach ($byTitle as $it) {
        $orderedReqs[] = $it;
    }

    $out = [];
    if ($orderedReqs !== []) {
        $out[] = [
            'name' => 'Settings APIs',
            'item' => $orderedReqs,
            'description' => 'All Settings (Mobile) requests. Wrapped so Postman cannot float Legal above these APIs.',
        ];
    }
    foreach ($folders as $f) {
        $out[] = $f;
    }

    return $out;
}

/**
 * If requests + folders are mixed, wrap requests into one folder.
 * Postman always shows folders before requests — mixed siblings scramble numbers.
 *
 * @param  list<array<string, mixed>>  $items
 * @return array{0: list<array<string, mixed>>, 1: bool}  [items, didWrapOrSpecialOrder]
 */
function unmixRequestsAndFolders(array $items, string $folderTitle): array
{
    if (stripos($folderTitle, 'Settings (Mobile)') !== false) {
        return [orderSettingsMobile($items), true];
    }

    $requests = [];
    $folders = [];
    foreach ($items as $it) {
        $t = stripAllPrefixes((string) ($it['name'] ?? ''));
        if (isFolder($it) && isSyntheticApisWrapper($it)) {
            foreach ($it['item'] as $child) {
                if (isFolder($child)) {
                    $folders[] = $child;
                } else {
                    $requests[] = $child;
                }
            }
            continue;
        }
        if (isFolder($it)) {
            $folders[] = $it;
        } else {
            $requests[] = $it;
        }
    }

    if ($requests === [] || $folders === []) {
        return [array_merge($requests, $folders), false];
    }

    $wrapperName = trim($folderTitle) !== '' && $folderTitle !== 'ROOT'
        ? stripAllPrefixes($folderTitle).' APIs'
        : 'APIs';

    return [array_merge(
        [[
            'name' => $wrapperName,
            'item' => $requests,
            'description' => 'Requests wrapped into a folder so Postman import cannot reorder folders above requests.',
        ]],
        $folders
    ), true];
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return list<array<string, mixed>>
 */
function orderByNumberThenRenumber(array $items, string $folderTitle = ''): array
{
    [$items, $preserveOrder] = unmixRequestsAndFolders($items, $folderTitle);

    // Only number-sort when we did not wrap (wrapping already set the correct order).
    if (! $preserveOrder) {
        usort($items, static function (array $a, array $b): int {
            return numberPrefixInt((string) ($a['name'] ?? ''))
                <=> numberPrefixInt((string) ($b['name'] ?? ''));
        });
    }

    $out = [];
    foreach (array_values($items) as $i => $it) {
        unset($it['id'], $it['_postman_id'], $it['uid']);
        $title = stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
        $it['name'] = pad($i + 1).'. '.$title;

        if (isFolder($it)) {
            $it['item'] = orderByNumberThenRenumber($it['item'], $title);
        }
        $out[] = $it;
    }

    return $out;
}

function walkProof(array $items, string $indent, array &$lines): void
{
    foreach ($items as $i => $it) {
        $name = (string) ($it['name'] ?? '');
        $kind = isFolder($it) ? 'FOLDER' : 'REQUEST';
        $lines[] = $indent.($i + 1).". [{$kind}] {$name}";
        if (isFolder($it)) {
            walkProof($it['item'], $indent.'  ', $lines);
        }
    }
}

$collection['item'] = orderByNumberThenRenumber($collection['item'] ?? [], 'ROOT');

$newId = newUuid();
$sortedAt = gmdate('Y-m-d\TH:i:s\Z');
$version = '6.2.0';
$collectionName = 'Tandil Backend v'.$version;

$collection['info']['_postman_id'] = $newId;
$collection['info']['_exporter_id'] = 'tandil-safe-'.substr($newId, 0, 8);
$collection['info']['version'] = $version;
$collection['info']['name'] = $collectionName;
$collection['info']['schema'] = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';
$collection['info']['description'] = <<<MD
Tandil Backend API. Env: base_url, token.

Hierarchy: main folders (Client Dashboard, Admin Dashboard, …) → subfolders → APIs.
Order fix (v6.2.0): 2-digit prefixes; optional "… APIs" wrapper only when requests
and folders are mixed at the same level. Does NOT flatten "Dashboard – All APIs" modules.

After adding APIs: append in the correct subfolder, then optionally run:
  php scripts/force_sort_postman_collection.php
  php scripts/smoke_postman_number_order.php

LAST_SORTED_AT: {$sortedAt}
COLLECTION_ID: {$newId}
MD;

$encoded = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    fwrite(STDERR, "JSON encode failed\n");
    exit(1);
}
$encoded = preg_replace_callback('/^(?:    )+/m', function (array $m): string {
    return str_repeat('  ', intdiv(strlen($m[0]), 4));
}, $encoded);
file_put_contents($path, $encoded."\n");

$proof = [];
$proof[] = $collectionName;
$proof[] = 'FIX: no mixed request/folder siblings; 2-digit number order';
$proof[] = 'LAST_SORTED_AT: '.$sortedAt;
$proof[] = 'COLLECTION_ID: '.$newId;
$proof[] = '';
$proof[] = '=== SIDEBAR ORDER ===';
walkProof($collection['item'], '', $proof);
file_put_contents($proofPath, implode(PHP_EOL, $proof).PHP_EOL);

ob_start();
$smokeExit = 0;
passthru('php '.escapeshellarg(__DIR__.'/smoke_postman_number_order.php'), $smokeExit);
$smokeOut = ob_get_clean();
echo $smokeOut;
file_put_contents($smokeReportPath, "collection={$collectionName}\n".$smokeOut);

echo "\nversion={$version}\nname={$collectionName}\ncollection_id={$newId}\n";
exit($smokeExit !== 0 ? 1 : 0);

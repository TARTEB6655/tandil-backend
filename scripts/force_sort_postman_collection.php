<?php

/**
 * Fix Postman sidebar order for REAL (including Sort by name ON).
 *
 * BUG: plain "1." "2." "10." "18." → Postman ASCII name-sort shows 1,10,18,2…
 * FIX: 2-digit prefixes "01." "02." … "20." so string-sort == number order.
 *
 * Also: integer number sort per folder, Settings (Mobile) business sequence,
 * strip item ids, new collection identity.
 *
 * Usage: php scripts/force_sort_postman_collection.php
 * Verify: php scripts/smoke_postman_number_order.php  (must PASS)
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
    while (preg_match('/^([0-9]+(?:\.[0-9]+)*|[A-Z]{1,3}[0-9]?)\.\s+(.+)$/u', $title, $m)) {
        $title = trim($m[2]);
    }

    return $title !== '' ? $title : trim($name);
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

/**
 * @param  list<array<string, mixed>>  $items
 * @return list<array<string, mixed>>
 */
function orderSettingsMobile(array $items): array
{
    $desired = [
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
        'Legal & Contact Content',
    ];

    $byTitle = [];
    foreach ($items as $it) {
        $byTitle[stripAllPrefixes((string) ($it['name'] ?? ''))] = $it;
    }

    $ordered = [];
    foreach ($desired as $title) {
        if (isset($byTitle[$title])) {
            $ordered[] = $byTitle[$title];
            unset($byTitle[$title]);
        }
    }
    foreach ($byTitle as $it) {
        $ordered[] = $it;
    }

    return $ordered;
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return list<array<string, mixed>>
 */
function orderByNumberThenRenumber(array $items, string $folderTitle = ''): array
{
    if (stripos($folderTitle, 'Settings (Mobile)') !== false) {
        $items = orderSettingsMobile($items);
    } else {
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

        if (isset($it['item']) && is_array($it['item'])) {
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
        $kind = isset($it['item']) ? 'FOLDER' : 'REQUEST';
        $lines[] = $indent.($i + 1).". [{$kind}] {$name}";
        if (isset($it['item']) && is_array($it['item'])) {
            walkProof($it['item'], $indent.'  ', $lines);
        }
    }
}

$collection['item'] = orderByNumberThenRenumber($collection['item'] ?? [], 'ROOT');

$newId = newUuid();
$sortedAt = gmdate('Y-m-d\TH:i:s\Z');
$version = '6.1.0';
$collectionName = 'Tandil Backend v'.$version.' FIXED ORDER';

$collection['info']['_postman_id'] = $newId;
$collection['info']['_exporter_id'] = 'tandil-fixed-'.substr($newId, 0, 8);
$collection['info']['version'] = $version;
$collection['info']['name'] = $collectionName;
$collection['info']['schema'] = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';
$collection['info']['description'] = <<<MD
Tandil Backend API. Env: base_url, token.

BUG FIX v6.1.0:
Postman Sort-by-name uses ASCII sort. Unpadded names (1, 10, 18, 2) scramble the sidebar.
This collection uses 01, 02, 03 … 20 so number order stays correct in Postman.

IMPORT:
1) DELETE every old Tandil Backend collection
2) Import as NEW (do not Merge)
3) Title must be exactly: {$collectionName}
4) Admin → Settings must show: 01, 02, 03 … 20 (not 1,18,2)

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
$proof[] = 'BUGFIX: 2-digit pad so Postman Sort-by-name == number order';
$proof[] = 'LAST_SORTED_AT: '.$sortedAt;
$proof[] = 'COLLECTION_ID: '.$newId;
$proof[] = '';
$proof[] = '=== SIDEBAR ORDER ===';
walkProof($collection['item'], '', $proof);
file_put_contents($proofPath, implode(PHP_EOL, $proof).PHP_EOL);

// Run smoke checks inline
ob_start();
$smokeExit = 0;
passthru('php '.escapeshellarg(__DIR__.'/smoke_postman_number_order.php'), $smokeExit);
$smokeOut = ob_get_clean();
echo $smokeOut;

file_put_contents($smokeReportPath, "collection={$collectionName}\n".$smokeOut);

echo "\nversion={$version}\n";
echo "name={$collectionName}\n";
echo "collection_id={$newId}\n";
exit($smokeExit !== 0 ? 1 : 0);

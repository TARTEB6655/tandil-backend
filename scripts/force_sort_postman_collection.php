<?php

/**
 * Force-sort Postman collection + NEW identity.
 *
 * CRITICAL: Use 2-digit prefixes (01, 02, … 20).
 * Unpadded "1." / "18." / "2." breaks Postman "Sort by name" (ASCII: 1,10,18,2…).
 * Do NOT write item-level id/_postman_id — those can pin stale sidebar order on re-import.
 *
 * Usage: php scripts/force_sort_postman_collection.php
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$proofPath = __DIR__.'/../postman/SIDEBAR_ORDER_PROOF.txt';
$collection = json_decode((string) file_get_contents($path), true);
if (! is_array($collection)) {
    fwrite(STDERR, 'Invalid JSON: '.json_last_error_msg()."\n");
    exit(1);
}

const PAD_WIDTH = 2;

function stripAllPrefixes(string $name): string
{
    $title = trim($name);
    while (preg_match('/^([0-9]+(?:\.[0-9]+)*|[A-Z]{1,3}[0-9]?)\.\s+(.+)$/u', $title, $m)) {
        $title = trim($m[2]);
    }

    return $title !== '' ? $title : trim($name);
}

function numberKey(string $name): array
{
    if (preg_match('/^([0-9]+(?:\.[0-9]+)*)\.\s+/u', $name, $m)) {
        return array_map('intval', explode('.', $m[1]));
    }

    return [PHP_INT_MAX];
}

function cmpKeys(array $a, array $b): int
{
    $n = max(count($a), count($b));
    for ($i = 0; $i < $n; $i++) {
        $av = $a[$i] ?? 0;
        $bv = $b[$i] ?? 0;
        if ($av !== $bv) {
            return $av <=> $bv;
        }
    }

    return 0;
}

function padNum(int $n): string
{
    return str_pad((string) $n, PAD_WIDTH, '0', STR_PAD_LEFT);
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
function forceSortAndRenumber(array $items): array
{
    foreach ($items as &$it) {
        if (isset($it['item']) && is_array($it['item'])) {
            $it['item'] = forceSortAndRenumber($it['item']);
        }
        // Never persist item ids — Postman re-import/sync can pin old sidebar order from them.
        unset($it['id'], $it['_postman_id'], $it['uid']);
    }
    unset($it);

    $requests = [];
    $folders = [];
    foreach ($items as $it) {
        if (isset($it['item']) && is_array($it['item'])) {
            $folders[] = $it;
        } else {
            $requests[] = $it;
        }
    }

    $byNum = static function (array $x, array $y): int {
        return cmpKeys(
            numberKey((string) ($x['name'] ?? '')),
            numberKey((string) ($y['name'] ?? ''))
        );
    };
    usort($requests, $byNum);
    usort($folders, $byNum);
    $items = array_merge($requests, $folders);

    foreach ($items as $i => &$it) {
        $it['name'] = padNum($i + 1).'. '.stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
    }
    unset($it);

    return array_values($items);
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

$collection['item'] = forceSortAndRenumber($collection['item'] ?? []);

$newId = newUuid();
$sortedAt = gmdate('Y-m-d\TH:i:s\Z');
$version = '5.1.0';
$collectionName = 'Tandil Backend v'.$version.' SEQ';

$collection['info']['_postman_id'] = $newId;
$collection['info']['_exporter_id'] = 'tandil-seq-'.substr($newId, 0, 8);
$collection['info']['version'] = $version;
$collection['info']['name'] = $collectionName;
$collection['info']['schema'] = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';
$collection['info']['description'] = <<<MD
Tandil Backend API. Env: base_url, token.

IMPORT:
1) DELETE every old Tandil Backend collection
2) Import as NEW (do not Merge / Update)
3) Title must be: {$collectionName}
4) Numbers are 01, 02, 03… (required so Postman Sort-by-name cannot become 1,10,18,2)

BUG FIXED IN 5.1.0: v5.0.0 used unpadded 1/2/18 — Postman name-sort scrambled sidebar.
Item-level UUIDs removed so re-import cannot pin stale order.

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
$proof[] = 'LAST_SORTED_AT: '.$sortedAt;
$proof[] = 'COLLECTION_ID: '.$newId;
$proof[] = 'FILE: postman/tandil_backend.json';
$proof[] = '';
$proof[] = '=== EXPECTED POSTMAN SIDEBAR ORDER ===';
walkProof($collection['item'], '', $proof);
file_put_contents($proofPath, implode(PHP_EOL, $proof).PHP_EOL);

$issues = 0;
$verify = function (array $items, string $p) use (&$verify, &$issues): void {
    $seenFolder = false;
    foreach ($items as $i => $it) {
        $want = padNum($i + 1);
        $name = (string) ($it['name'] ?? '');
        $isFolder = isset($it['item']) && is_array($it['item']);
        if (! str_starts_with($name, $want.'. ')) {
            echo "BAD {$p} want {$want} got {$name}\n";
            $issues++;
        }
        if ($isFolder) {
            $seenFolder = true;
        } elseif ($seenFolder) {
            echo "BAD ORDER {$p} request after folder → {$name}\n";
            $issues++;
        }
        if (! empty($it['id']) || ! empty($it['_postman_id'])) {
            echo "BAD ID present on {$p}/{$name}\n";
            $issues++;
        }
        if ($isFolder) {
            $verify($it['item'], $p.'/'.$name);
        }
    }
};
$verify($collection['item'], 'ROOT');

// Prove Sort-by-name is safe for Settings (Mobile)
$admin = null;
foreach ($collection['item'] as $it) {
    if (stripos((string) ($it['name'] ?? ''), 'Admin Dashboard') !== false) {
        $admin = $it;
        break;
    }
}
$set = null;
foreach ($admin['item'] ?? [] as $it) {
    if (stripos((string) ($it['name'] ?? ''), 'Settings (Mobile)') !== false) {
        $set = $it;
        break;
    }
}
$names = array_map(static fn ($it) => (string) ($it['name'] ?? ''), $set['item'] ?? []);
$alpha = $names;
sort($alpha, SORT_STRING);
$sortSafe = $names === $alpha;

echo "version={$version}\n";
echo "name={$collectionName}\n";
echo "collection_id={$newId}\n";
echo "issues={$issues}\n";
echo 'settings_sort_by_name_safe='.($sortSafe ? 'YES' : 'NO')."\n";
echo "\nSETTINGS (Mobile):\n";
foreach ($set['item'] ?? [] as $i => $it) {
    echo ($i + 1).'. '.($it['name'] ?? '').PHP_EOL;
}
exit($issues > 0 || ! $sortSafe ? 1 : 0);

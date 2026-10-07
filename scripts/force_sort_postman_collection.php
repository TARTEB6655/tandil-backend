<?php

/**
 * Force-sort Postman collection + give it a NEW identity so Postman cannot
 * merge/keep the old broken sidebar order.
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

/**
 * Numeric sort, then requests first / folders second (both groups sorted),
 * then renumber 01..N so array index always matches the prefix.
 *
 * @param  list<array<string, mixed>>  $items
 * @return list<array<string, mixed>>
 */
function forceSortAndRenumber(array $items): array
{
    foreach ($items as &$it) {
        if (isset($it['item']) && is_array($it['item'])) {
            $it['item'] = forceSortAndRenumber($it['item']);
        }
        // Drop any stale Postman ids that can pin old order after merge
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
        $prefix = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $it['name'] = $prefix.'. '.stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
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

$newId = sprintf(
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

$sortedAt = gmdate('Y-m-d\TH:i:s\Z');
$collection['info']['_postman_id'] = $newId;
$collection['info']['_exporter_id'] = 'tandil-backend-sorted-'.substr($newId, 0, 8);
$collection['info']['version'] = '3.9.0';
$collection['info']['name'] = 'Tandil Backend SORTED v3.9.0';
$collection['info']['description'] = <<<MD
Tandil Backend API. Env: base_url, token.

*** IMPORT INSTRUCTIONS (IMPORTANT) ***
1) In Postman DELETE every old "Tandil Backend..." collection
2) Import this file as NEW (do NOT Merge / Update)
3) You must see collection name exactly: Tandil Backend SORTED v3.9.0
4) Root order must be: 01 Health, 02 Notifications, 03 Auth, 04 Client, 05 Admin, ... 08 Products

LAST_SORTED_AT: {$sortedAt}
COLLECTION_ID: {$newId}
SORT: numeric 01..N; requests before folders in each group
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
$proof[] = 'Tandil Backend SORTED v3.9.0';
$proof[] = 'LAST_SORTED_AT: '.$sortedAt;
$proof[] = 'COLLECTION_ID: '.$newId;
$proof[] = 'FILE: postman/tandil_backend.json';
$proof[] = '';
$proof[] = '=== EXPECTED POSTMAN SIDEBAR ORDER ===';
walkProof($collection['item'], '', $proof);
file_put_contents($proofPath, implode(PHP_EOL, $proof).PHP_EOL);

// Verify sequence
$issues = 0;
$verify = function (array $items, string $p) use (&$verify, &$issues): void {
    foreach ($items as $i => $it) {
        $want = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $name = (string) ($it['name'] ?? '');
        if (! str_starts_with($name, $want.'. ')) {
            echo "BAD {$p} want {$want} got {$name}\n";
            $issues++;
        }
        if (isset($it['item']) && is_array($it['item'])) {
            $verify($it['item'], $p.'/'.$name);
        }
    }
};
$verify($collection['item'], 'ROOT');

echo "version=3.9.0\n";
echo "name=Tandil Backend SORTED v3.9.0\n";
echo "collection_id={$newId}\n";
echo "issues={$issues}\n";
echo "proof={$proofPath}\n";
echo "\nROOT:\n";
foreach ($collection['item'] as $i => $it) {
    echo ($i + 1).'. '.($it['name'] ?? '').PHP_EOL;
}
exit($issues > 0 ? 1 : 0);

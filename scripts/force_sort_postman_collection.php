<?php

/**
 * Force-sort Postman collection by numeric name prefix at every level.
 * Pure numeric order for ALL siblings (folders + requests mixed by number).
 * Writes a visible LAST_SORTED_AT marker so the file change is unmistakable.
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$raw = (string) file_get_contents($path);
$collection = json_decode($raw, true);
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
 * Sort every sibling by numeric prefix only (folder vs request does not matter).
 *
 * @param  list<array<string, mixed>>  $items
 * @return array{0: list<array<string, mixed>>, 1: int}
 */
function forceSortAndRenumber(array $items): array
{
    $moved = 0;

    // 1) Force shuffle detection: capture before order
    $before = array_map(fn ($it) => (string) ($it['name'] ?? ''), $items);

    // 2) Sort by existing numeric prefix
    usort($items, function (array $x, array $y): int {
        return cmpKeys(
            numberKey((string) ($x['name'] ?? '')),
            numberKey((string) ($y['name'] ?? ''))
        );
    });

    // 3) Recurse into folders first (so children are sorted before we rename parents)
    foreach ($items as &$it) {
        if (isset($it['item']) && is_array($it['item'])) {
            [$kids, $c] = forceSortAndRenumber($it['item']);
            $it['item'] = $kids;
            $moved += $c;
        }
    }
    unset($it);

    // 4) Re-assign clean 01..N names
    foreach ($items as $i => &$it) {
        $prefix = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $title = stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
        $newName = $prefix.'. '.$title;
        if (($it['name'] ?? '') !== $newName) {
            $it['name'] = $newName;
            $moved++;
        }
    }
    unset($it);

    $after = array_map(fn ($it) => (string) ($it['name'] ?? ''), $items);
    if ($before !== $after) {
        $moved += count($before); // count as structural reorder
    }

    return [$items, $moved];
}

[$collection['item'], $changed] = forceSortAndRenumber($collection['item'] ?? []);

$sortedAt = gmdate('Y-m-d\TH:i:s\Z');
$collection['info']['version'] = '3.7.0';
$collection['info']['name'] = 'Tandil Backend - Flow-Based Collection (v3.7.0)';
$collection['info']['description'] = <<<MD
Tandil Backend API. Env: base_url, token.

LAST_SORTED_AT: {$sortedAt}
SORT_RULE: numeric prefix ascending at every folder/request level (01, 02, 03…).

NUMBERING:
- Every folder / subfolder / API: 01, 02, 03… (restarts inside each folder)
- After ANY add/edit: php scripts/force_sort_postman_collection.php
- In Postman: DELETE old collection → Import this file (do not Merge)

Key paths: 03→03 Contractor · 05→14 Contractor registrations · 05→15 Signup options · 04→16 Shop · 12 Vendor
MD;

$encoded = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    fwrite(STDERR, "JSON encode failed\n");
    exit(1);
}
// 2-space indent (Postman-friendly)
$encoded = preg_replace_callback('/^(?:    )+/m', function (array $m): string {
    return str_repeat('  ', intdiv(strlen($m[0]), 4));
}, $encoded);

file_put_contents($path, $encoded."\n");

echo "changed_units={$changed}\n";
echo "version=3.7.0\n";
echo "LAST_SORTED_AT={$sortedAt}\n";
echo "path={$path}\n";
echo "bytes=".filesize($path)."\n";

echo "\nROOT ORDER NOW:\n";
foreach ($collection['item'] as $i => $it) {
    echo ($i + 1).'. '.($it['name'] ?? '').PHP_EOL;
}

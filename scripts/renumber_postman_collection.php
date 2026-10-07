<?php

/**
 * Fix Postman sidebar order + numbering in one pass.
 *
 * 1) Sort every item[] by numeric prefix (so JSON order = 01,02,03…)
 * 2) Re-assign clean numbers at EVERY level: 001, 002, 003… (restart per folder)
 * 3) Sibling rule: keep numeric order (force_sort puts folders first, then requests)
 *
 * NEW API workflow:
 *   - Append request at END of the correct parent folder
 *   - Run: php scripts/renumber_postman_collection.php
 *   - Check: php scripts/find_postman_gaps.php   (must be count=0)
 *   - Re-import tandil_backend.json in Postman (replace old collection)
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$collection = json_decode((string) file_get_contents($path), true);
if (! is_array($collection)) {
    fwrite(STDERR, 'Invalid Postman JSON: '.json_last_error_msg()."\n");
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
 * Sort siblings strictly by numeric prefix (folders + requests mixed together).
 *
 * @param  list<array<string, mixed>>  $items
 * @return list<array<string, mixed>>
 */
function sortSiblings(array $items): array
{
    usort($items, function (array $x, array $y): int {
        return cmpKeys(
            numberKey((string) ($x['name'] ?? '')),
            numberKey((string) ($y['name'] ?? ''))
        );
    });

    return $items;
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array{0: list<array<string, mixed>>, 1: int}
 */
function normalize(array $items): array
{
    $changed = 0;
    $items = sortSiblings($items);

    foreach ($items as $i => &$it) {
        $prefix = str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
        $title = stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
        $newName = $prefix.'. '.$title;
        if (($it['name'] ?? '') !== $newName) {
            $it['name'] = $newName;
            $changed++;
        }
        if (isset($it['item']) && is_array($it['item'])) {
            [$kids, $c] = normalize($it['item']);
            $it['item'] = $kids;
            $changed += $c;
        }
    }
    unset($it);

    return [$items, $changed];
}

/**
 * @param  list<array<string, mixed>>  $root
 * @return array{0: list<array<string, mixed>>, 1: int}
 */
function restructureHealthCheck(array $root): array
{
    $moved = 0;
    $healthIdx = $otherIdx = $adminIdx = null;
    foreach ($root as $i => $it) {
        $title = stripAllPrefixes((string) ($it['name'] ?? ''));
        if (stripos($title, 'Health Check') !== false) {
            $healthIdx = $i;
        }
        if (stripos($title, 'Other Modules') !== false) {
            $otherIdx = $i;
        }
        if (stripos($title, 'Admin Dashboard') !== false) {
            $adminIdx = $i;
        }
    }
    if ($healthIdx === null) {
        return [$root, 0];
    }

    $keep = [];
    $toOther = [];
    $toAdmin = [];
    foreach ($root[$healthIdx]['item'] ?? [] as $child) {
        $title = stripAllPrefixes((string) ($child['name'] ?? ''));
        if (stripos($title, 'Language APIs') !== false || stripos($title, 'Localized articles') !== false) {
            $toOther[] = $child;
            $moved++;
        } elseif (stripos($title, 'Admin Wallet') !== false) {
            $toAdmin[] = $child;
            $moved++;
        } else {
            $keep[] = $child;
        }
    }
    $root[$healthIdx]['item'] = $keep;
    if ($adminIdx !== null) {
        foreach ($toAdmin as $child) {
            $root[$adminIdx]['item'][] = $child;
        }
    }
    if ($otherIdx !== null) {
        foreach ($toOther as $child) {
            $root[$otherIdx]['item'][] = $child;
        }
    }

    return [$root, $moved];
}

[$collection['item'], $moved] = restructureHealthCheck($collection['item'] ?? []);
[$collection['item'], $changed] = normalize($collection['item'] ?? []);

$version = (string) ($collection['info']['version'] ?? '3.6.54');
if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $vm)) {
    $collection['info']['version'] = $vm[1].'.'.$vm[2].'.'.((int) $vm[3] + 1);
}
$ver = $collection['info']['version'];
$collection['info']['name'] = 'Tandil Backend - Flow-Based Collection (v'.$ver.')';
$collection['info']['description'] = <<<'MD'
Tandil Backend API. Env: base_url, token.

NUMBERING (fixed order in file = order in Postman sidebar):
- Every folder / subfolder / API: 001, 002, 003… (3-digit; restarts inside each folder)
- After ANY add/edit: php scripts/force_sort_postman_collection.php
- In Postman: DELETE old collection → Import as NEW (do not Merge)

Key paths: 03→03 Contractor · 05→14 Contractor registrations · 05→15 Signup options · 04→16 Shop · 12 Vendor
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

echo "Moved from Health Check: {$moved}\n";
echo "Names/order updated: {$changed}\n";
echo "Version: {$ver}\n";
echo "OK {$path}\n";

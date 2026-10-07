<?php

/**
 * Deterministic Postman numbering for tandil_backend.json
 *
 * Sibling rules:
 * - Root folders: 01, 02, 03, ...
 * - All-folder groups: A, B, C, ... (AA after Z)
 * - Requests or mixed request+folder groups: 01, 02, 03, ...
 *
 * Also moves misplaced folders out of "Health Check":
 * - Language APIs + Localized articles → Other Modules (end)
 * - Admin Wallet APIs → Admin Dashboard (end, keeps N/O contractor letters stable)
 *
 * Usage: php scripts/renumber_postman_collection.php
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$collection = json_decode((string) file_get_contents($path), true);
if (! is_array($collection)) {
    fwrite(STDERR, "Invalid Postman JSON\n");
    exit(1);
}

function stripAllPrefixes(string $name): string
{
    $title = trim($name);
    while (preg_match('/^([0-9]{1,3}|[A-Z]{1,3}[0-9]?)\.\s+(.+)$/u', $title, $m)) {
        $title = trim($m[2]);
    }

    return $title !== '' ? $title : trim($name);
}

function letterPrefix(int $index): string
{
    $n = $index;
    $out = '';
    while ($n > 0) {
        $n--;
        $out = chr(65 + ($n % 26)).$out;
        $n = intdiv($n, 26);
    }

    return $out;
}

function numberPrefix(int $index): string
{
    return str_pad((string) $index, 2, '0', STR_PAD_LEFT);
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array{0: list<array<string, mixed>>, 1: int}
 */
function renumberItems(array $items, bool $forceNumeric = false): array
{
    $changed = 0;
    if ($items === []) {
        return [$items, 0];
    }

    $allFolders = true;
    foreach ($items as $it) {
        if (! isset($it['item']) || ! is_array($it['item'])) {
            $allFolders = false;
            break;
        }
    }
    $useLetters = ! $forceNumeric && $allFolders;

    foreach ($items as $i => &$it) {
        $index = $i + 1;
        $prefix = $useLetters ? letterPrefix($index) : numberPrefix($index);
        $title = stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
        $newName = $prefix.'. '.$title;
        if (($it['name'] ?? '') !== $newName) {
            $it['name'] = $newName;
            $changed++;
        }
        if (isset($it['item']) && is_array($it['item'])) {
            [$childItems, $childChanged] = renumberItems($it['item'], false);
            $it['item'] = $childItems;
            $changed += $childChanged;
        }
    }
    unset($it);

    return [$items, $changed];
}

/**
 * @param  list<array<string, mixed>>  $root
 */
function findRootIndexByTitle(array $root, string $needle): ?int
{
    foreach ($root as $i => $it) {
        if (stripos(stripAllPrefixes((string) ($it['name'] ?? '')), $needle) !== false) {
            return $i;
        }
    }

    return null;
}

/**
 * @param  list<array<string, mixed>>  $children
 * @return array{0: list<array<string, mixed>>, 1: array<string, array<string, mixed>>}
 */
function extractChildrenByTitle(array $children, array $needles): array
{
    $keep = [];
    $found = [];
    foreach ($children as $child) {
        $title = stripAllPrefixes((string) ($child['name'] ?? ''));
        $matched = null;
        foreach ($needles as $key => $needle) {
            if (stripos($title, $needle) !== false) {
                $matched = $key;
                break;
            }
        }
        if ($matched !== null) {
            $found[$matched] = $child;
        } else {
            $keep[] = $child;
        }
    }

    return [$keep, $found];
}

function childTitleExists(array $children, string $title): bool
{
    foreach ($children as $c) {
        if (strcasecmp(stripAllPrefixes((string) ($c['name'] ?? '')), $title) === 0) {
            return true;
        }
    }

    return false;
}

$moved = 0;
$root = $collection['item'] ?? [];
if (! is_array($root)) {
    $root = [];
}

$healthIdx = findRootIndexByTitle($root, 'Health Check');
$otherIdx = findRootIndexByTitle($root, 'Other Modules');
$adminIdx = findRootIndexByTitle($root, 'Admin Dashboard');

if ($healthIdx !== null) {
    $healthChildren = $root[$healthIdx]['item'] ?? [];
    if (! is_array($healthChildren)) {
        $healthChildren = [];
    }
    [$keep, $found] = extractChildrenByTitle($healthChildren, [
        'language' => 'Language APIs',
        'articles' => 'Localized articles',
        'wallet' => 'Admin Wallet',
    ]);
    $root[$healthIdx]['item'] = $keep;
    $moved = count($found);

    if (isset($found['wallet']) && $adminIdx !== null) {
        $adminChildren = $root[$adminIdx]['item'] ?? [];
        if (! is_array($adminChildren)) {
            $adminChildren = [];
        }
        $title = 'Admin Wallet APIs';
        if (! childTitleExists($adminChildren, $title)) {
            $found['wallet']['name'] = $title;
            $adminChildren[] = $found['wallet'];
            $root[$adminIdx]['item'] = $adminChildren;
        }
    }

    if ($otherIdx !== null) {
        $otherChildren = $root[$otherIdx]['item'] ?? [];
        if (! is_array($otherChildren)) {
            $otherChildren = [];
        }
        foreach (['language' => 'Language APIs (All Dashboards)', 'articles' => 'Localized articles (multilingual demo)'] as $key => $title) {
            if (! isset($found[$key])) {
                continue;
            }
            if (! childTitleExists($otherChildren, $title) && ! childTitleExists($otherChildren, stripAllPrefixes($title))) {
                $found[$key]['name'] = $title;
                $otherChildren[] = $found[$key];
            }
        }
        $root[$otherIdx]['item'] = $otherChildren;
    }
}

[$root, $changed] = renumberItems($root, true);
$collection['item'] = $root;

$version = (string) ($collection['info']['version'] ?? '3.6.48');
if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $vm)) {
    $collection['info']['version'] = $vm[1].'.'.$vm[2].'.'.((int) $vm[3] + 1);
}
$ver = $collection['info']['version'];
if (isset($collection['info']['name']) && is_string($collection['info']['name'])) {
    $collection['info']['name'] = preg_replace('/v\d+\.\d+\.\d+/', 'v'.$ver, $collection['info']['name'])
        ?: $collection['info']['name'];
}

$encoded = json_encode(
    $collection,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
if ($encoded === false) {
    fwrite(STDERR, "JSON encode failed\n");
    exit(1);
}

// Keep 2-space indentation (Postman / existing repo style).
$encoded = preg_replace_callback('/^(?:    )+/m', function (array $m): string {
    $levels = intdiv(strlen($m[0]), 4);

    return str_repeat('  ', $levels);
}, $encoded);

file_put_contents($path, $encoded."\n");

echo "Moved from Health Check: {$moved}\n";
echo "Names updated: {$changed}\n";
echo "Version: {$ver}\n";
echo "OK {$path}\n";

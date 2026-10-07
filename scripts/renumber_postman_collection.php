<?php

/**
 * Hierarchical Postman numbering for tandil_backend.json
 *
 * Every level is sequence-ordered and nested under its parent:
 *   01. Health Check
 *     01.01 Health Check
 *     01.02 Debug Performance
 *   05. Admin Dashboard
 *     05.14 Admin – Supervisor (Contractor) Registrations
 *       05.14.01 List
 *       05.14.07 Suspend account
 *
 * Rules for NEW APIs (must follow):
 * 1. Append the new request/folder at the END of the correct parent.
 * 2. Run: php scripts/renumber_postman_collection.php
 * 3. Verify: php scripts/find_postman_gaps.php  (must be count=0)
 * Never hand-pick numbers. Never insert in the middle unless you accept renumber of later siblings.
 *
 * Usage: php scripts/renumber_postman_collection.php
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
    // Strip "01. ", "05.14.03. ", "A. ", "N2. " repeatedly.
    while (preg_match('/^([0-9]+(?:\.[0-9]+)*|[A-Z]{1,3}[0-9]?)\.\s+(.+)$/u', $title, $m)) {
        $title = trim($m[2]);
    }

    return $title !== '' ? $title : trim($name);
}

function padSegment(int $index): string
{
    return str_pad((string) $index, 2, '0', STR_PAD_LEFT);
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array{0: list<array<string, mixed>>, 1: int}
 */
function renumberItems(array $items, string $parentPrefix = ''): array
{
    $changed = 0;
    foreach ($items as $i => &$it) {
        $segment = padSegment($i + 1);
        $prefix = $parentPrefix === '' ? $segment : $parentPrefix.'.'.$segment;
        $title = stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
        $newName = $prefix.'. '.$title;
        if (($it['name'] ?? '') !== $newName) {
            $it['name'] = $newName;
            $changed++;
        }
        if (isset($it['item']) && is_array($it['item'])) {
            [$kids, $childChanged] = renumberItems($it['item'], $prefix);
            $it['item'] = $kids;
            $changed += $childChanged;
        }
    }
    unset($it);

    return [$items, $changed];
}

/**
 * Keep Health Check clean: move misplaced modules if they reappear.
 *
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
    $extras = [];
    foreach ($root[$healthIdx]['item'] ?? [] as $child) {
        $title = stripAllPrefixes((string) ($child['name'] ?? ''));
        if (stripos($title, 'Language APIs') !== false || stripos($title, 'Localized articles') !== false) {
            $extras['other'][] = $child;
            $moved++;
        } elseif (stripos($title, 'Admin Wallet') !== false) {
            $extras['admin'][] = $child;
            $moved++;
        } else {
            $keep[] = $child;
        }
    }
    $root[$healthIdx]['item'] = $keep;

    if (! empty($extras['admin']) && $adminIdx !== null) {
        foreach ($extras['admin'] as $child) {
            $root[$adminIdx]['item'][] = $child;
        }
    }
    if (! empty($extras['other']) && $otherIdx !== null) {
        foreach ($extras['other'] as $child) {
            $root[$otherIdx]['item'][] = $child;
        }
    }

    return [$root, $moved];
}

[$collection['item'], $moved] = restructureHealthCheck($collection['item'] ?? []);
[$collection['item'], $changed] = renumberItems($collection['item'] ?? []);

$version = (string) ($collection['info']['version'] ?? '3.6.53');
if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $vm)) {
    $collection['info']['version'] = $vm[1].'.'.$vm[2].'.'.((int) $vm[3] + 1);
}
$ver = $collection['info']['version'];
$collection['info']['name'] = 'Tandil Backend - Flow-Based Collection (v'.$ver.')';
$collection['info']['description'] = <<<'MD'
Tandil Backend API. JSON responses. Env: base_url, token.

NUMBERING (hierarchical — do not hand-edit):
- Root: 01, 02, 03, …
- Subfolders/APIs: 05.14, 05.14.01, 05.14.07, …
- Always APPEND new items at the end of the parent folder, then run:
  php scripts/renumber_postman_collection.php
  php scripts/find_postman_gaps.php

Key paths:
- Contractor auth: 03.03
- Admin contractor review: 05.14 (account-status/suspend|inactive|activate — no body)
- Admin contractor signup options: 05.15
- Client shop: 04.16
- Vendor dashboard: 12
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
echo "Names updated: {$changed}\n";
echo "Version: {$ver}\n";
echo "OK {$path}\n";

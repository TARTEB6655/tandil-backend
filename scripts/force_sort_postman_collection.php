<?php

/**
 * End-to-end: every folder ordered BY NUMBER (integer), then named 1, 2, 3…
 *
 * - Sort key = integer prefix only (NOT ASCII/string "sort by name")
 * - Plain numbers: 1, 2, 3 … 19, 20 (no 01 / 001)
 * - Strip item ids (stale Postman sidebar pins)
 * - Does not use strcmp/name alphabetical ordering
 *
 * Usage: php scripts/force_sort_postman_collection.php
 * Verify: php scripts/smoke_postman_number_order.php
 *
 * In Postman UI: Sort by name must be OFF (Default order). The file cannot
 * disable that client toggle; number-order lives in the JSON item[] array.
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$proofPath = __DIR__.'/../postman/SIDEBAR_ORDER_PROOF.txt';
$smokeReportPath = __DIR__.'/../postman/NUMBER_ORDER_SMOKE_REPORT.txt';
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

/** Integer number from prefix — NOT string/name sort. */
function numberPrefixInt(string $name): int
{
    if (preg_match('/^([0-9]+)/u', trim($name), $m)) {
        return (int) $m[1];
    }

    return PHP_INT_MAX;
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
 * Fixed business sequence for Admin → Settings (Mobile), then numbered 1..N.
 *
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
 * Sort siblings BY NUMBER (int), recurse, renumber 1..N plain.
 *
 * @param  list<array<string, mixed>>  $items
 * @param  string  $folderTitle  stripped parent title (for special cases)
 * @return list<array<string, mixed>>
 */
function orderByNumberThenRenumber(array $items, string $folderTitle = ''): array
{
    // Special: Settings (Mobile) business sequence first
    if (stripos($folderTitle, 'Settings (Mobile)') !== false) {
        $items = orderSettingsMobile($items);
    } else {
        // BY NUMBER only (integer compare) — never strcmp on full name
        usort($items, static function (array $a, array $b): int {
            $na = numberPrefixInt((string) ($a['name'] ?? ''));
            $nb = numberPrefixInt((string) ($b['name'] ?? ''));
            if ($na !== $nb) {
                return $na <=> $nb;
            }
            // Stable tie-break: keep relative title only if both lack numbers
            return stripAllPrefixes((string) ($a['name'] ?? ''))
                <=> stripAllPrefixes((string) ($b['name'] ?? ''));
        });
    }

    $out = [];
    foreach (array_values($items) as $i => $it) {
        unset($it['id'], $it['_postman_id'], $it['uid']);
        $title = stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
        $it['name'] = ($i + 1).'. '.$title;

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
$version = '6.0.0';
$collectionName = 'Tandil Backend v'.$version.' BY NUMBER';

$collection['info']['_postman_id'] = $newId;
$collection['info']['_exporter_id'] = 'tandil-bynum-'.substr($newId, 0, 8);
$collection['info']['version'] = $version;
$collection['info']['name'] = $collectionName;
$collection['info']['schema'] = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';
$collection['info']['description'] = <<<MD
Tandil Backend API. Env: base_url, token.

*** ORDER = BY NUMBER ONLY (1, 2, 3 … 19, 20) ***
- Every folder / subfolder / request is numbered in JSON array order
- NOT sorted by name (ASCII). Sort key = integer number only.

IMPORT STEPS (required):
1) Postman → DELETE all old "Tandil Backend…" collections
2) Import this file as NEW (never Merge / Update)
3) Collection title must be: {$collectionName}
4) Sidebar → click collection → turn OFF "Sort by name" (use Default)

If Sort by name stays ON, Postman will show 1,10,18,2… — that is Postman UI, not this file.

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
$proof[] = 'MODE: by number (integer) → renumber 1,2,3…';
$proof[] = 'LAST_SORTED_AT: '.$sortedAt;
$proof[] = 'COLLECTION_ID: '.$newId;
$proof[] = '';
$proof[] = '=== SIDEBAR ORDER (Default view) ===';
walkProof($collection['item'], '', $proof);
file_put_contents($proofPath, implode(PHP_EOL, $proof).PHP_EOL);

// Inline smoke verify
$issues = 0;
$folders = 0;
$requests = 0;
$verify = function (array $items, string $p) use (&$verify, &$issues, &$folders, &$requests): void {
    $prev = 0;
    foreach ($items as $i => $it) {
        $want = $i + 1;
        $name = (string) ($it['name'] ?? '');
        $isFolder = isset($it['item']) && is_array($it['item']);
        $isFolder ? $folders++ : $requests++;
        if (! preg_match('/^(\d+)\.\s+/u', $name, $m) || (int) $m[1] !== $want) {
            echo "BAD {$p} want {$want} got {$name}\n";
            $issues++;
        }
        if (preg_match('/^0\d+\.\s+/u', $name)) {
            echo "ZERO_PAD {$p} {$name}\n";
            $issues++;
        }
        if ((int) ($m[1] ?? 0) !== $prev + 1 && $i > 0) {
            echo "JUMP {$p} {$name}\n";
            $issues++;
        }
        $prev = (int) ($m[1] ?? 0);
        if (! empty($it['id']) || ! empty($it['_postman_id'])) {
            echo "ID {$p}/{$name}\n";
            $issues++;
        }
        if ($isFolder) {
            $verify($it['item'], $p.'/'.$name);
        }
    }
};
$verify($collection['item'], 'ROOT');

$report = [];
$report[] = $collectionName;
$report[] = 'SMOKE E2E number order';
$report[] = 'folders='.$folders;
$report[] = 'requests='.$requests;
$report[] = 'issues='.$issues;
$report[] = 'sorted_at='.$sortedAt;
$report[] = 'mode=by-number-integer-then-renumber-plain-1-2-3';
$report[] = 'sort_by_name_in_file=REMOVED';
file_put_contents($smokeReportPath, implode(PHP_EOL, $report).PHP_EOL);

echo "version={$version}\n";
echo "name={$collectionName}\n";
echo "collection_id={$newId}\n";
echo "folders={$folders}\n";
echo "requests={$requests}\n";
echo "issues={$issues}\n";
echo "mode=by-number-only\n";
echo "\nROOT:\n";
foreach ($collection['item'] as $i => $it) {
    echo ($i + 1).'. '.($it['name'] ?? '').PHP_EOL;
}
echo "\nADMIN → SETTINGS (Mobile):\n";
foreach ($collection['item'] as $it) {
    if (stripos((string) ($it['name'] ?? ''), 'Admin Dashboard') === false) {
        continue;
    }
    foreach ($it['item'] ?? [] as $c) {
        if (stripos((string) ($c['name'] ?? ''), 'Settings (Mobile)') === false) {
            continue;
        }
        foreach ($c['item'] ?? [] as $k => $r) {
            echo ($k + 1).'. '.($r['name'] ?? '').PHP_EOL;
        }
        break 2;
    }
}
exit($issues > 0 ? 1 : 0);

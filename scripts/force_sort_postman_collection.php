<?php

/**
 * Renumber Postman collection by ARRAY ORDER only (1, 2, 3…).
 *
 * Does NOT sort by name. Does NOT zero-pad for "Sort by name".
 * Just walks each folder's item[] in current order and sets names to 1. 2. 3…
 * Strips item ids so Postman cannot pin a stale sidebar order.
 *
 * Usage: php scripts/force_sort_postman_collection.php
 *
 * Postman: use Default / manual order — turn OFF "Sort by name".
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
 * Renumber in place — keep existing item[] order, only rewrite 1. 2. 3… names.
 *
 * @param  list<array<string, mixed>>  $items
 * @return list<array<string, mixed>>
 */
function renumberByArrayOrderOnly(array $items): array
{
    $out = [];
    foreach (array_values($items) as $i => $it) {
        unset($it['id'], $it['_postman_id'], $it['uid']);
        $it['name'] = ($i + 1).'. '.stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
        if (isset($it['item']) && is_array($it['item'])) {
            $it['item'] = renumberByArrayOrderOnly($it['item']);
        }
        $out[] = $it;
    }

    return $out;
}

/**
 * Ensure Settings (Mobile) business order once, then only number-order applies.
 *
 * @param  list<array<string, mixed>>  $root
 * @return list<array<string, mixed>>
 */
function ensureSettingsMobileLogicalOrder(array $root): array
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

    foreach ($root as &$folder) {
        if (stripos(stripAllPrefixes((string) ($folder['name'] ?? '')), 'Admin Dashboard') === false) {
            continue;
        }
        if (! isset($folder['item']) || ! is_array($folder['item'])) {
            continue;
        }
        foreach ($folder['item'] as &$child) {
            if (stripos(stripAllPrefixes((string) ($child['name'] ?? '')), 'Settings (Mobile)') === false) {
                continue;
            }
            if (! isset($child['item']) || ! is_array($child['item'])) {
                continue;
            }

            $byTitle = [];
            foreach ($child['item'] as $it) {
                $byTitle[stripAllPrefixes((string) ($it['name'] ?? ''))] = $it;
            }

            $ordered = [];
            foreach ($desired as $title) {
                if (isset($byTitle[$title])) {
                    $ordered[] = $byTitle[$title];
                    unset($byTitle[$title]);
                }
            }
            // Keep any unexpected leftovers at the end (still number-ordered later).
            foreach ($byTitle as $it) {
                $ordered[] = $it;
            }
            $child['item'] = $ordered;
        }
        unset($child);
    }
    unset($folder);

    return $root;
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

$collection['item'] = ensureSettingsMobileLogicalOrder($collection['item'] ?? []);
$collection['item'] = renumberByArrayOrderOnly($collection['item'] ?? []);

$newId = newUuid();
$sortedAt = gmdate('Y-m-d\TH:i:s\Z');
$version = '5.2.0';
$collectionName = 'Tandil Backend v'.$version.' NUMBER ORDER';

$collection['info']['_postman_id'] = $newId;
$collection['info']['_exporter_id'] = 'tandil-num-'.substr($newId, 0, 8);
$collection['info']['version'] = $version;
$collection['info']['name'] = $collectionName;
$collection['info']['schema'] = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';
$collection['info']['description'] = <<<MD
Tandil Backend API. Env: base_url, token.

ORDERING: number order only (1, 2, 3…) = JSON array order.
No Sort-by-name, no zero-padding for name sort.

IMPORT:
1) DELETE every old Tandil Backend collection
2) Import as NEW (do not Merge)
3) Title must be: {$collectionName}
4) In Postman sidebar: turn OFF "Sort by name" → use Default / manual order

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
$proof[] = 'MODE: number order only (array index = 1,2,3…)';
$proof[] = '';
$proof[] = '=== EXPECTED POSTMAN SIDEBAR ORDER (Default view, Sort by name OFF) ===';
walkProof($collection['item'], '', $proof);
file_put_contents($proofPath, implode(PHP_EOL, $proof).PHP_EOL);

$issues = 0;
$verify = function (array $items, string $p) use (&$verify, &$issues): void {
    foreach ($items as $i => $it) {
        $want = (string) ($i + 1);
        $name = (string) ($it['name'] ?? '');
        if (! preg_match('/^(\d+)\.\s+/u', $name, $m) || $m[1] !== $want) {
            echo "BAD {$p} want {$want} got {$name}\n";
            $issues++;
        }
        if (! empty($it['id']) || ! empty($it['_postman_id'])) {
            echo "BAD ID on {$p}/{$name}\n";
            $issues++;
        }
        if (isset($it['item']) && is_array($it['item'])) {
            $verify($it['item'], $p.'/'.$name);
        }
    }
};
$verify($collection['item'], 'ROOT');

echo "version={$version}\n";
echo "name={$collectionName}\n";
echo "collection_id={$newId}\n";
echo "mode=number-order-only\n";
echo "issues={$issues}\n";
echo "\nSETTINGS (Mobile):\n";
foreach ($collection['item'] as $it) {
    if (stripos((string) ($it['name'] ?? ''), 'Admin Dashboard') === false) {
        continue;
    }
    foreach ($it['item'] ?? [] as $child) {
        if (stripos((string) ($child['name'] ?? ''), 'Settings (Mobile)') === false) {
            continue;
        }
        foreach ($child['item'] ?? [] as $k => $req) {
            $m = $req['request']['method'] ?? (isset($req['item']) ? 'FOLDER' : '?');
            echo ($k + 1).". [{$m}] ".($req['name'] ?? '').PHP_EOL;
        }
        break 2;
    }
}
exit($issues > 0 ? 1 : 0);

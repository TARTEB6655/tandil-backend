<?php

/**
 * E2E smoke — permanent Postman order guards.
 *
 * FAIL if:
 * 1) Any sibling group mixes requests + folders (Postman floats folders above requests)
 * 2) Numbers not zero-padded 01..N matching array index
 * 3) ASCII Sort-by-name would scramble the group
 * 4) Stale item ids present
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$j = json_decode((string) file_get_contents($path), true);
if (! is_array($j)) {
    fwrite(STDERR, "INVALID JSON\n");
    exit(1);
}

$issues = [];
$folderCount = 0;
$requestCount = 0;
$padWidth = 2;

function isFolder(array $it): bool
{
    return isset($it['item']) && is_array($it['item']);
}

function walk(array $items, string $path): void
{
    global $issues, $folderCount, $requestCount, $padWidth;

    $hasR = false;
    $hasF = false;
    $names = [];

    foreach ($items as $i => $it) {
        $want = str_pad((string) ($i + 1), $padWidth, '0', STR_PAD_LEFT);
        $name = (string) ($it['name'] ?? '');
        $folder = isFolder($it);
        $folder ? $folderCount++ : $requestCount++;
        $folder ? $hasF = true : $hasR = true;
        $names[] = $name;

        if (! preg_match('/^([0-9]{'.$padWidth.'})\.\s+/u', $name, $m) || $m[1] !== $want) {
            $issues[] = "PAD_OR_SEQ {$path} index=".($i + 1)." want={$want}. … got={$name}";
        }
        if (! empty($it['id']) || ! empty($it['_postman_id'])) {
            $issues[] = "STALE_ID {$path} / {$name}";
        }
        if ($folder) {
            walk($it['item'], $path.' / '.$name);
        }
    }

    if ($hasR && $hasF) {
        $issues[] = "MIXED_REQUEST_FOLDER_SIBLINGS {$path} — Postman will float folders above requests and scramble numbers";
    }

    if (count($names) >= 2) {
        $alpha = $names;
        sort($alpha, SORT_STRING);
        if ($alpha !== $names) {
            $issues[] = "POSTMAN_SORT_BY_NAME_WOULD_SCRAMBLE {$path}";
        }
    }
}

echo "=== SMOKE E2E (Postman-safe permanent order) ===\n";
echo 'collection: '.($j['info']['name'] ?? '')."\n";
echo 'version: '.($j['info']['version'] ?? '')."\n\n";

walk($j['item'] ?? [], 'ROOT');

echo "folders={$folderCount} requests={$requestCount}\n";
echo 'issues='.count($issues)."\n";
foreach (array_slice($issues, 0, 80) as $x) {
    echo " - {$x}\n";
}

function find(array $items, string $needle): ?array
{
    foreach ($items as $it) {
        if (stripos((string) ($it['name'] ?? ''), $needle) !== false && isFolder($it)) {
            return $it;
        }
        if (isFolder($it)) {
            $f = find($it['item'], $needle);
            if ($f) {
                return $f;
            }
        }
    }

    return null;
}

$set = find($j['item'] ?? [], 'Settings (Mobile)');
echo "\n=== Admin → Settings (Mobile) ===\n";
foreach ($set['item'] ?? [] as $i => $it) {
    $kind = isFolder($it) ? 'FOLDER' : 'REQUEST';
    echo ($i + 1).". [{$kind}] ".($it['name'] ?? '')."\n";
    if (isFolder($it) && stripos((string) $it['name'], 'Settings APIs') !== false) {
        foreach ($it['item'] as $j => $r) {
            echo '   '.($j + 1).'. '.($r['name'] ?? '')."\n";
        }
    }
}

$fail = count($issues) > 0;
echo "\nRESULT: ".($fail ? 'FAIL' : 'PASS')."\n";
exit($fail ? 1 : 0);

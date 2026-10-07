<?php

/**
 * E2E smoke: every folder must display correctly in Postman even with Sort by name.
 *
 * Postman "Sort by name" = ASCII string sort.
 * Plain "1." / "10." / "18." / "2." SCRAMBLES → FAIL.
 * Zero-padded "01." / "02." / "10." / "18." stays correct → PASS.
 *
 * Also checks: array index == number, no jumps, no stale item ids.
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

function walk(array $items, string $path): void
{
    global $issues, $folderCount, $requestCount, $padWidth;

    $names = [];
    foreach ($items as $i => $it) {
        $want = str_pad((string) ($i + 1), $padWidth, '0', STR_PAD_LEFT);
        $name = (string) ($it['name'] ?? '');
        $isFolder = isset($it['item']) && is_array($it['item']);
        $isFolder ? $folderCount++ : $requestCount++;
        $names[] = $name;

        if (! preg_match('/^(\d+)\.\s+(.+)$/u', $name, $m)) {
            $issues[] = "NO_NUMBER {$path} [{$i}] {$name}";
            continue;
        }

        // Must be zero-padded to padWidth (Postman Sort-by-name safe)
        if (! preg_match('/^([0-9]{'.$padWidth.'})\.\s+/u', $name, $pm) || $pm[1] !== $want) {
            $issues[] = "PAD_OR_SEQ {$path} index=".($i + 1)." want={$want}. … got={$name}";
        }

        if (! empty($it['id']) || ! empty($it['_postman_id'])) {
            $issues[] = "STALE_ID {$path} / {$name}";
        }

        if ($isFolder) {
            walk($it['item'], $path.' / '.$name);
        }
    }

    if (count($names) >= 2) {
        $alpha = $names;
        sort($alpha, SORT_STRING);
        if ($alpha !== $names) {
            $issues[] = "POSTMAN_SORT_BY_NAME_WOULD_SCRAMBLE {$path}";
            $issues[] = "  file_order: ".implode(' | ', array_map(static function ($n) {
                return preg_match('/^(\d+)\./', $n, $m) ? $m[1] : '?';
            }, $names));
            $issues[] = "  name_sort:  ".implode(' | ', array_map(static function ($n) {
                return preg_match('/^(\d+)\./', $n, $m) ? $m[1] : '?';
            }, $alpha));
        }
    }
}

echo "=== SMOKE E2E (Postman display-safe number order) ===\n";
echo 'collection: '.($j['info']['name'] ?? '')."\n";
echo 'version: '.($j['info']['version'] ?? '')."\n";
echo "pad_width={$padWidth} (required so Sort-by-name == number order)\n\n";

walk($j['item'] ?? [], 'ROOT');

echo "folders={$folderCount} requests={$requestCount}\n";
echo 'issues='.count($issues)."\n";
foreach (array_slice($issues, 0, 60) as $x) {
    echo " - {$x}\n";
}
if (count($issues) > 60) {
    echo ' ... +'.(count($issues) - 60)." more\n";
}

function find(array $items, string $needle): ?array
{
    foreach ($items as $it) {
        if (stripos((string) ($it['name'] ?? ''), $needle) !== false && isset($it['item'])) {
            return $it;
        }
        if (isset($it['item'])) {
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
    echo ($i + 1).'. '.($it['name'] ?? '')."\n";
}

$fail = count($issues) > 0;
echo "\nRESULT: ".($fail ? 'FAIL — Postman sidebar will look wrong' : 'PASS — file order safe for Postman')."\n";
exit($fail ? 1 : 0);

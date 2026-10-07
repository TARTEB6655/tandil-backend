<?php

/**
 * End-to-end smoke test: every folder/subfolder/API must be 1,2,3… in array order.
 * Exit 1 on any failure. Prints a full report.
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
$maxDepth = 0;

function walk(array $items, string $path, int $depth): void
{
    global $issues, $folderCount, $requestCount, $maxDepth;
    $maxDepth = max($maxDepth, $depth);
    $prevNum = 0;

    foreach ($items as $i => $it) {
        $want = $i + 1;
        $name = (string) ($it['name'] ?? '');
        $isFolder = isset($it['item']) && is_array($it['item']);
        if ($isFolder) {
            $folderCount++;
        } else {
            $requestCount++;
        }

        if (! preg_match('/^(\d+)\.\s+(.+)$/u', $name, $m)) {
            $issues[] = "NO_NUMBER {$path} [{$i}] {$name}";
            continue;
        }
        $num = (int) $m[1];
        // Reject zero-padded "01" style if user wants plain 1,2,3 — allow but flag leading zero
        if (strlen($m[1]) > 1 && $m[1][0] === '0') {
            $issues[] = "ZERO_PAD {$path} got={$name} (want plain 1,2,3 not 01)";
        }
        if ($num !== $want) {
            $issues[] = "SEQ {$path} index=".($i + 1)." want={$want} got={$num} name={$name}";
        }
        if ($num !== $prevNum + 1 && $i > 0) {
            $issues[] = "JUMP {$path} after {$prevNum} got {$num} ({$name})";
        }
        $prevNum = $num;

        if (! empty($it['id']) || ! empty($it['_postman_id'])) {
            $issues[] = "STALE_ID {$path} / {$name}";
        }

        // Detect string-sort trap risk for this sibling group (informational via issues if broken seq)
        if ($isFolder) {
            walk($it['item'], $path.' / '.$name, $depth + 1);
        }
    }

    // Sibling group: simulate ASCII name-sort vs number order
    if (count($items) >= 2) {
        $names = array_map(static fn ($it) => (string) ($it['name'] ?? ''), $items);
        $byNum = $names;
        $alpha = $names;
        usort($byNum, static function ($a, $b) {
            preg_match('/^(\d+)/', $a, $ma);
            preg_match('/^(\d+)/', $b, $mb);

            return ((int) ($ma[1] ?? 0)) <=> ((int) ($mb[1] ?? 0));
        });
        sort($alpha, SORT_STRING);
        if ($names !== $byNum) {
            $issues[] = "ARRAY_NOT_NUMBER_SORTED {$path}";
        }
        // Note only: alpha differs from number (expected with plain 1..20)
        if ($alpha !== $byNum) {
            // not an error — Postman Sort-by-name would break; file uses number order
        }
    }
}

echo "=== SMOKE: Postman number order E2E ===\n";
echo 'collection: '.($j['info']['name'] ?? '')."\n";
echo 'version: '.($j['info']['version'] ?? '')."\n";
echo '_postman_id: '.($j['info']['_postman_id'] ?? '')."\n\n";

walk($j['item'] ?? [], 'ROOT', 0);

echo "folders={$folderCount} requests={$requestCount} max_depth={$maxDepth}\n";
echo 'issues='.count($issues)."\n";
foreach (array_slice($issues, 0, 80) as $x) {
    echo " - {$x}\n";
}
if (count($issues) > 80) {
    echo ' ... +'.(count($issues) - 80)." more\n";
}

// Print Admin → Settings sample
function find(array $items, string $needle): ?array
{
    foreach ($items as $it) {
        $n = (string) ($it['name'] ?? '');
        if (stripos($n, $needle) !== false && isset($it['item'])) {
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
echo "\n=== SAMPLE: 5 Admin → Settings (Mobile) ===\n";
foreach ($set['item'] ?? [] as $i => $it) {
    echo ($i + 1).'. '.($it['name'] ?? '')."\n";
}

echo "\n=== ROOT ===\n";
foreach ($j['item'] ?? [] as $i => $it) {
    echo ($i + 1).'. '.($it['name'] ?? '')."\n";
}

exit(count($issues) > 0 ? 1 : 0);

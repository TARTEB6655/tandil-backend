<?php

/**
 * Verify every sibling group in Postman collection is 01..N sequential.
 * Exit code 1 if any gap/wrong prefix.
 */

$j = json_decode(file_get_contents(__DIR__.'/../postman/tandil_backend.json'), true);
$issues = [];

function strip(string $name): ?string
{
    if (preg_match('/^([0-9]{1,3})\.\s+/u', $name, $m)) {
        return str_pad((string) ((int) $m[1]), 2, '0', STR_PAD_LEFT);
    }

    return null;
}

function walk(array $items, string $path): void
{
    global $issues;
    $n = count($items);
    for ($i = 1; $i <= $n; $i++) {
        $want = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $name = (string) ($items[$i - 1]['name'] ?? '');
        $got = strip($name);
        if ($got === null) {
            $issues[] = "MISSING under {$path} → {$name}";
        } elseif ($got !== $want) {
            $issues[] = "SEQ {$path}[{$i}] want={$want} got={$got} → {$name}";
        }
        // Reject leftover letter prefixes
        if (preg_match('/^[A-Z]{1,3}\.\s+/u', $name)) {
            $issues[] = "LETTER_PREFIX under {$path} → {$name}";
        }
    }
    foreach ($items as $it) {
        if (isset($it['item']) && is_array($it['item'])) {
            walk($it['item'], $path.' / '.($it['name'] ?? '?'));
        }
    }
}

walk($j['item'] ?? [], 'ROOT');
echo 'count='.count($issues).PHP_EOL;
foreach (array_slice($issues, 0, 100) as $x) {
    echo $x, PHP_EOL;
}
exit(count($issues) > 0 ? 1 : 0);

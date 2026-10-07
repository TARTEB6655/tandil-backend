<?php

/**
 * Verify every sibling group is exactly 01..N in array order (2-digit).
 * Requests must appear before folders in each group.
 * No item-level id/_postman_id.
 */

$j = json_decode(file_get_contents(__DIR__.'/../postman/tandil_backend.json'), true);
$issues = [];

function walk(array $items, string $path): void
{
    global $issues;
    $seenFolder = false;
    foreach ($items as $i => $it) {
        $want = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $name = (string) ($it['name'] ?? '');
        $isFolder = isset($it['item']) && is_array($it['item']);

        if (! preg_match('/^([0-9]{2})\.\s+/u', $name, $m) || $m[1] !== $want) {
            $issues[] = "SEQ {$path} want={$want} got={$name}";
        }
        if (! empty($it['id']) || ! empty($it['_postman_id'])) {
            $issues[] = "ID {$path} / {$name}";
        }
        if ($isFolder) {
            $seenFolder = true;
        } elseif ($seenFolder) {
            $issues[] = "ORDER {$path} request after folder → {$name}";
        }
        if ($isFolder) {
            walk($it['item'], $path.' / '.$name);
        }
    }
}

walk($j['item'] ?? [], 'ROOT');
echo 'count='.count($issues).PHP_EOL;
foreach (array_slice($issues, 0, 40) as $x) {
    echo $x, PHP_EOL;
}
exit(count($issues) > 0 ? 1 : 0);

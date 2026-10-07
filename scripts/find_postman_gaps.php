<?php

/**
 * Verify every sibling group is exactly 1..N in array order (plain numbers).
 * No item-level id/_postman_id.
 */

$j = json_decode(file_get_contents(__DIR__.'/../postman/tandil_backend.json'), true);
$issues = [];

function walk(array $items, string $path): void
{
    global $issues;
    foreach ($items as $i => $it) {
        $want = (string) ($i + 1);
        $name = (string) ($it['name'] ?? '');
        $isFolder = isset($it['item']) && is_array($it['item']);

        if (! preg_match('/^(\d+)\.\s+/u', $name, $m) || $m[1] !== $want) {
            $issues[] = "SEQ {$path} want={$want} got={$name}";
        }
        if (! empty($it['id']) || ! empty($it['_postman_id'])) {
            $issues[] = "ID {$path} / {$name}";
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

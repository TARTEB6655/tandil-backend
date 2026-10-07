<?php

/**
 * Verify every sibling group is exactly 01..N in array order.
 * Exit 1 on any mismatch. Also fails if a folder appears before a request
 * in the same group (would look wrong in Postman).
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
        if (preg_match('/^[A-Z]/u', $name)) {
            $issues[] = "LETTER {$path} → {$name}";
        }

        if ($isFolder) {
            $seenFolder = true;
        } elseif ($seenFolder) {
            $issues[] = "ORDER {$path} request after folder → {$name} (requests must come before folders)";
        }

        if ($isFolder) {
            walk($it['item'], $path.' / '.$name);
        }
    }
}

walk($j['item'] ?? [], 'ROOT');
echo 'count='.count($issues).PHP_EOL;
foreach (array_slice($issues, 0, 60) as $x) {
    echo $x, PHP_EOL;
}
exit(count($issues) > 0 ? 1 : 0);

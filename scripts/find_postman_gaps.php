<?php

/**
 * Verify hierarchical numbering: each sibling group is parent.01, parent.02, …
 * Exit 1 if any gap / wrong prefix / letter prefix.
 */

$j = json_decode(file_get_contents(__DIR__.'/../postman/tandil_backend.json'), true);
$issues = [];

function stripFull(string $name): ?string
{
    if (preg_match('/^([0-9]+(?:\.[0-9]+)*)\.\s+/u', $name, $m)) {
        return $m[1];
    }

    return null;
}

function padSegment(int $index): string
{
    return str_pad((string) $index, 2, '0', STR_PAD_LEFT);
}

function walk(array $items, string $parentPrefix, string $path): void
{
    global $issues;
    foreach ($items as $i => $it) {
        $segment = padSegment($i + 1);
        $want = $parentPrefix === '' ? $segment : $parentPrefix.'.'.$segment;
        $name = (string) ($it['name'] ?? '');
        $got = stripFull($name);

        if ($got === null) {
            $issues[] = "MISSING under {$path} → {$name}";
        } elseif ($got !== $want) {
            $issues[] = "SEQ {$path} want={$want} got={$got} → {$name}";
        }
        if (preg_match('/^[A-Z]/u', $name)) {
            $issues[] = "LETTER_PREFIX under {$path} → {$name}";
        }

        if (isset($it['item']) && is_array($it['item'])) {
            walk($it['item'], $want, $path.' / '.$name);
        }
    }
}

walk($j['item'] ?? [], '', 'ROOT');
echo 'count='.count($issues).PHP_EOL;
foreach (array_slice($issues, 0, 80) as $x) {
    echo $x, PHP_EOL;
}
exit(count($issues) > 0 ? 1 : 0);

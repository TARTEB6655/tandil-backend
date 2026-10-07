<?php

$j = json_decode(file_get_contents(__DIR__.'/../postman/tandil_backend.json'), true);
$issues = [];

function strip(string $name): ?string
{
    if (preg_match('/^([0-9]{1,3}|[A-Z]{1,2})\.\s+/u', $name, $m)) {
        return $m[1];
    }

    return null;
}

function walk(array $items, string $path): void
{
    global $issues;
    $n = count($items);
    if ($n === 0) {
        return;
    }

    $prefs = [];
    foreach ($items as $it) {
        $prefs[] = strip((string) ($it['name'] ?? ''));
    }

    $allLetter = true;
    $allNumber = true;
    foreach ($prefs as $p) {
        if ($p === null) {
            $issues[] = "MISSING under {$path}";
            $allLetter = false;
            $allNumber = false;
            continue;
        }
        if (! preg_match('/^[A-Z]+$/', $p)) {
            $allLetter = false;
        }
        if (! preg_match('/^\d+$/', $p)) {
            $allNumber = false;
        }
    }

    if ($allNumber) {
        for ($i = 1; $i <= $n; $i++) {
            $want = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $got = $prefs[$i - 1];
            $gotNorm = $got !== null ? str_pad((string) ((int) $got), 2, '0', STR_PAD_LEFT) : null;
            if ($gotNorm !== $want) {
                $issues[] = "SEQ {$path}[{$i}] want={$want} got={$got} name=".($items[$i - 1]['name'] ?? '');
            }
        }
    } elseif ($allLetter) {
        for ($i = 1; $i <= $n; $i++) {
            $want = chr(64 + $i);
            $got = $prefs[$i - 1];
            if ($got !== $want) {
                $issues[] = "SEQ {$path}[{$i}] want={$want} got={$got} name=".($items[$i - 1]['name'] ?? '');
            }
        }
    } else {
        $issues[] = "MIXED under {$path}";
        foreach ($items as $it) {
            $issues[] = '  '.$it['name'];
        }
    }

    foreach ($items as $it) {
        if (isset($it['item'])) {
            walk($it['item'], $path.' / '.($it['name'] ?? '?'));
        }
    }
}

walk($j['item'] ?? [], 'ROOT');
echo 'count='.count($issues).PHP_EOL;
foreach (array_slice($issues, 0, 200) as $x) {
    echo $x, PHP_EOL;
}

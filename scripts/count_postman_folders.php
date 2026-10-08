<?php

function countFolders(array $items, int &$n): void
{
    foreach ($items as $it) {
        if (isset($it['item']) && is_array($it['item'])) {
            $n++;
            countFolders($it['item'], $n);
        }
    }
}

$refs = array_slice($argv, 1);
if ($refs === []) {
    $refs = ['ccbc561', 'd5dabe8', 'HEAD'];
}

foreach ($refs as $ref) {
    $cmd = 'git show '.$ref.':postman/tandil_backend.json';
    $j = shell_exec($cmd);
    if (! is_string($j) || $j === '') {
        echo "$ref: missing\n";
        continue;
    }
    $c = json_decode($j, true);
    $n = 0;
    countFolders($c['item'] ?? [], $n);
    $top = array_map(static fn ($i) => (string) ($i['name'] ?? ''), $c['item'] ?? []);
    echo "$ref: folders=$n top_level=".count($top)."\n";
    foreach (array_slice($top, 0, 20) as $name) {
        echo "  - $name\n";
    }
    echo "---\n";
}

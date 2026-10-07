<?php

/**
 * Number-order only: rewrite names to 1, 2, 3… in current array order.
 * Does not sort by name. Prefer: php scripts/force_sort_postman_collection.php
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$collection = json_decode((string) file_get_contents($path), true);
if (! is_array($collection)) {
    fwrite(STDERR, 'Invalid Postman JSON: '.json_last_error_msg()."\n");
    exit(1);
}

function stripAllPrefixes(string $name): string
{
    $title = trim($name);
    while (preg_match('/^([0-9]+(?:\.[0-9]+)*|[A-Z]{1,3}[0-9]?)\.\s+(.+)$/u', $title, $m)) {
        $title = trim($m[2]);
    }

    return $title !== '' ? $title : trim($name);
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array{0: list<array<string, mixed>>, 1: int}
 */
function normalize(array $items): array
{
    $changed = 0;
    $out = [];
    foreach (array_values($items) as $i => $it) {
        unset($it['id'], $it['_postman_id'], $it['uid']);
        $newName = ($i + 1).'. '.stripAllPrefixes((string) ($it['name'] ?? 'Untitled'));
        if (($it['name'] ?? '') !== $newName) {
            $it['name'] = $newName;
            $changed++;
        }
        if (isset($it['item']) && is_array($it['item'])) {
            [$kids, $c] = normalize($it['item']);
            $it['item'] = $kids;
            $changed += $c;
        }
        $out[] = $it;
    }

    return [$out, $changed];
}

[$collection['item'], $changed] = normalize($collection['item'] ?? []);

$version = (string) ($collection['info']['version'] ?? '5.2.0');
if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $vm)) {
    $collection['info']['version'] = $vm[1].'.'.$vm[2].'.'.((int) $vm[3] + 1);
}
$ver = $collection['info']['version'];
$collection['info']['name'] = 'Tandil Backend v'.$ver.' NUMBER ORDER';

$encoded = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$encoded = preg_replace_callback('/^(?:    )+/m', function (array $m): string {
    return str_repeat('  ', intdiv(strlen($m[0]), 4));
}, $encoded);
file_put_contents($path, $encoded."\n");

echo "Names updated: {$changed}\n";
echo "Version: {$ver}\n";
echo "OK {$path}\n";

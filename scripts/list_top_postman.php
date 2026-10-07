<?php

$j = json_decode(file_get_contents(__DIR__.'/../postman/tandil_backend.json'), true);

foreach (($j['item'] ?? []) as $i => $it) {
    echo ($i + 1).'. '.($it['name'] ?? '').PHP_EOL;
    if (! empty($it['item'])) {
        foreach ($it['item'] as $j2 => $child) {
            $t = isset($child['item']) ? 'F' : 'R';
            echo '   '.$t.' '.($j2 + 1).'. '.($child['name'] ?? '').PHP_EOL;
        }
    }
}

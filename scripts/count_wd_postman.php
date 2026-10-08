<?php

$c = json_decode((string) file_get_contents(__DIR__.'/../postman/tandil_backend.json'), true);
$n = 0;
function cf(array $items, int &$n): void
{
    foreach ($items as $it) {
        if (isset($it['item'])) {
            $n++;
            cf($it['item'], $n);
        }
    }
}
cf($c['item'] ?? [], $n);
echo 'name='.($c['info']['name'] ?? '').PHP_EOL;
echo "top_level=".count($c['item'] ?? []).PHP_EOL;
echo "folders=$n".PHP_EOL;
foreach ($c['item'] ?? [] as $it) {
    echo '  - '.($it['name'] ?? '').PHP_EOL;
}

<?php
$baseDir = dirname(__DIR__);
$mainPath = $baseDir . '/postman/tandil_backend.json';
$productsPath = $baseDir . '/postman/Products_All_APIs.postman_collection.json';

$main = json_decode(file_get_contents($mainPath), true);
$products = json_decode(file_get_contents($productsPath), true);

if (!$main || !$products) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

$productsModule = [
    'name' => '9. Products - All APIs',
    'item' => $products['item'],
    'description' => 'Single module for all product APIs. Public (no auth) and Admin (Bearer token). Set token from Login for Admin requests.',
];

$items = $main['item'];
$newItems = [];
foreach ($items as $folder) {
    if (($folder['name'] ?? '') === '10. Admin & HR Routes') {
        $newItems[] = $productsModule;
    }
    $newItems[] = $folder;
}

$main['item'] = $newItems;
file_put_contents($mainPath, json_encode($main, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Added 9. Products - All APIs to main collection\n";

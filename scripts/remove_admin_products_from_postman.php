<?php
$path = dirname(__DIR__) . '/postman/tandil_backend.json';
$json = file_get_contents($path);
$data = json_decode($json, true);
if (!$data) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

foreach ($data['item'] as &$folder) {
    if (($folder['name'] ?? '') !== '10. Admin & HR Routes') {
        continue;
    }
    $folder['item'] = array_values(array_filter($folder['item'] ?? [], function ($entry) {
        return ($entry['name'] ?? '') !== 'Admin - Products Management';
    }));
    break;
}

file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Removed Admin - Products Management from 10. Admin & HR Routes\n";

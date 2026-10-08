<?php

/**
 * Insert category/service convert requests into the v6-style hierarchy (no flatten).
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$collection = json_decode((string) file_get_contents($path), true);
if (! is_array($collection)) {
    fwrite(STDERR, "Invalid collection JSON\n");
    exit(1);
}

$convertCategory = [
    'name' => '06. Categories - Convert to Service',
    'event' => [[
        'listen' => 'test',
        'script' => [
            'exec' => [
                "if (pm.response.code === 200) {",
                "    var jsonData = pm.response.json();",
                "    pm.test('success true', function () { pm.expect(jsonData.success).to.eql(true); });",
                "    pm.test('has service id', function () { pm.expect(jsonData.data && jsonData.data.id).to.be.a('number'); });",
                "    if (jsonData.data && jsonData.data.id) {",
                "        pm.environment.set('service_id', jsonData.data.id);",
                "    }",
                "}",
            ],
            'type' => 'text/javascript',
        ],
    ]],
    'request' => [
        'method' => 'POST',
        'header' => [
            ['key' => 'Authorization', 'value' => 'Bearer {{token}}'],
            ['key' => 'Accept', 'value' => 'application/json'],
            ['key' => 'Content-Type', 'value' => 'application/json'],
        ],
        'body' => ['mode' => 'raw', 'raw' => '{}'],
        'url' => [
            'raw' => '{{base_url}}/api/admin/categories/{{category_id}}/convert-to-service',
            'host' => ['{{base_url}}'],
            'path' => ['api', 'admin', 'categories', '{{category_id}}', 'convert-to-service'],
        ],
        'description' => 'POST /api/admin/categories/{category_id}/convert-to-service — Body `{}`. Response: AdminService object (same as GET /api/admin/services/{id}). Sets service_id.',
    ],
];

$convertService = [
    'name' => '06. Services - Convert to Category',
    'event' => [[
        'listen' => 'test',
        'script' => [
            'exec' => [
                "if (pm.response.code === 200) {",
                "    var jsonData = pm.response.json();",
                "    pm.test('success true', function () { pm.expect(jsonData.success).to.eql(true); });",
                "    pm.test('has shipping_cost', function () { pm.expect(jsonData.data).to.have.property('shipping_cost'); });",
                "    if (jsonData.data && jsonData.data.id) {",
                "        pm.environment.set('category_id', jsonData.data.id);",
                "    }",
                "}",
            ],
            'type' => 'text/javascript',
        ],
    ]],
    'request' => [
        'method' => 'POST',
        'header' => [
            ['key' => 'Authorization', 'value' => 'Bearer {{token}}'],
            ['key' => 'Accept', 'value' => 'application/json'],
            ['key' => 'Content-Type', 'value' => 'application/json'],
        ],
        'body' => ['mode' => 'raw', 'raw' => '{}'],
        'url' => [
            'raw' => '{{base_url}}/api/admin/services/{{service_id}}/convert-to-category',
            'host' => ['{{base_url}}'],
            'path' => ['api', 'admin', 'services', '{{service_id}}', 'convert-to-category'],
        ],
        'description' => 'POST /api/admin/services/{service_id}/convert-to-category — Body `{}`. Response: AdminCategory (shipping_cost, tax_percentage, …). Sets category_id.',
    ],
];

/**
 * @return array<string, mixed>|null
 */
function &findFolderBySuffixRef(array &$items, string $suffix): ?array
{
    foreach ($items as &$it) {
        if (! isset($it['item']) || ! is_array($it['item'])) {
            continue;
        }
        $name = (string) ($it['name'] ?? '');
        if (str_contains($name, $suffix)) {
            return $it;
        }
        $nested = &findFolderBySuffixRef($it['item'], $suffix);
        if ($nested !== null) {
            return $nested;
        }
    }
    unset($it);

    $null = null;

    return $null;
}

function insertBeforeDelete(array &$items, array $newItem, string $deleteNeedle): bool
{
    foreach ($items as $i => &$it) {
        if (isset($it['item']) && is_array($it['item']) && ! isset($it['request'])) {
            if (insertBeforeDelete($it['item'], $newItem, $deleteNeedle)) {
                return true;
            }
            continue;
        }
        $name = (string) ($it['name'] ?? '');
        if (str_contains($name, $deleteNeedle)) {
            array_splice($items, $i, 0, [$newItem]);

            return true;
        }
    }
    unset($it);

    return false;
}

function renumberFlatRequests(array &$items): void
{
    $n = 1;
    foreach ($items as &$it) {
        if (isset($it['item']) && is_array($it['item']) && ! isset($it['request'])) {
            continue;
        }
        $title = preg_replace('/^\d+\.\s+/', '', (string) ($it['name'] ?? 'Untitled'));
        $it['name'] = str_pad((string) $n, 2, '0', STR_PAD_LEFT).'. '.$title;
        $n++;
    }
    unset($it);
}

$root = &$collection['item'];
$other = &findFolderBySuffixRef($root, 'Other Modules');
if ($other === null) {
    fwrite(STDERR, "Other Modules folder not found\n");
    exit(1);
}

$catFolder = &findFolderBySuffixRef($other['item'], 'Admin - Categories');
$svcFolder = &findFolderBySuffixRef($other['item'], 'Admin - Services');
if ($catFolder === null || $svcFolder === null) {
    fwrite(STDERR, "Admin Categories/Services folders not found\n");
    exit(1);
}

foreach ($catFolder['item'] as $child) {
    if (str_contains((string) ($child['name'] ?? ''), 'Convert to Service')) {
        echo "Convert requests already present.\n";
        exit(0);
    }
}

if (! insertBeforeDelete($catFolder['item'], $convertCategory, 'Categories - Delete')) {
    fwrite(STDERR, "Categories - Delete not found\n");
    exit(1);
}
if (! insertBeforeDelete($svcFolder['item'], $convertService, 'Services - Delete')) {
    fwrite(STDERR, "Services - Delete not found\n");
    exit(1);
}

renumberFlatRequests($catFolder['item']);
renumberFlatRequests($svcFolder['item']);

file_put_contents(
    $path,
    json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
);

echo "Patched convert requests into Admin - Categories / Services.\n";

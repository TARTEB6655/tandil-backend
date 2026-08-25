<?php

$path = dirname(__DIR__) . '/postman/tandil_backend.json';
$j = json_decode(file_get_contents($path), true);

function &findByPath(array &$items, array $names)
{
    $null = null;
    if ($names === []) {
        return $null;
    }
    $want = array_shift($names);
    foreach ($items as &$item) {
        if (($item['name'] ?? '') !== $want) {
            continue;
        }
        if ($names === []) {
            return $item;
        }
        if (! isset($item['item'])) {
            return $null;
        }

        return findByPath($item['item'], $names);
    }

    return $null;
}

$v = &findByPath($j['item'], ['12. Vendor Dashboard – All APIs', 'J. Products (approved only)']);
if ($v !== null) {
    $v['description'] = "Vendor catalog products (multipart). Required: name, price. Optional: category_id, service_id / service_ids, description, stock, status, is_featured, weight_unit, sku, handle, product_type, option_groups_json, main_image, images[], image_urls.\n\n**category_id and service_id are both optional** — omit them if not needed.\n\n**Fulfillment:**\n- Simple / product (no service link / type=product) → vendor OTP + horizontal client track.\n- Service (type=service or service_id) → supervisor vertical flow.\n\nList/filter: ?category_id= returns only this vendor's products.";
}

$create = &findByPath($j['item'], ['12. Vendor Dashboard – All APIs', 'J. Products (approved only)', '2. Create product (multipart – same as Admin)']);
if ($create !== null && isset($create['request']['body']['formdata'])) {
    foreach ($create['request']['body']['formdata'] as &$f) {
        if (($f['key'] ?? '') === 'category_id') {
            $f['description'] = 'Optional. Platform category ID from GET /api/vendor/categories.';
        }
        if (($f['key'] ?? '') === 'service_id') {
            $f['description'] = 'Optional. Platform service ID from GET /api/vendor/services.';
        }
    }
    unset($f);
    $create['request']['description'] = 'POST multipart. Required: name, price. Optional: category_id, service_id. Response: data.vendor_product. Auto-sets {{vendor_product_id}}.';
}

$update = &findByPath($j['item'], ['12. Vendor Dashboard – All APIs', 'J. Products (approved only)', '4. Update product (multipart – same as Admin)']);
if ($update !== null && isset($update['request']['body']['formdata'])) {
    foreach ($update['request']['body']['formdata'] as &$f) {
        if (($f['key'] ?? '') === 'category_id') {
            $f['description'] = 'Optional. Platform category ID.';
        }
        if (($f['key'] ?? '') === 'service_id') {
            $f['description'] = 'Optional. Platform service ID.';
        }
    }
    unset($f);
}

$adminCreate = &findByPath($j['item'], ['12. Vendor Dashboard – All APIs', 'N. Admin – Vendor Management', 'A. Vendor Management (Mobile)', '3. Create vendor product (admin)']);
if ($adminCreate !== null) {
    if (isset($adminCreate['request']['body']['formdata'])) {
        foreach ($adminCreate['request']['body']['formdata'] as &$f) {
            if (($f['key'] ?? '') === 'category_id') {
                $f['description'] = 'Optional. Platform category ID.';
            }
            if (($f['key'] ?? '') === 'service_id') {
                $f['description'] = 'Optional. Platform service ID.';
            }
        }
        unset($f);
    }
    $adminCreate['request']['description'] = "POST multipart /api/admin/vendors/{vendor_id}/products — Admin creates a product for an approved vendor.\n\n**Required:** name, price. **Optional:** category_id, service_id, description, stock, status, images, etc.\n\n**201 Response:** vendor_id, vendor_product, product. Sets {{vendor_product_id}}.";
}

$adminUpdate = &findByPath($j['item'], ['12. Vendor Dashboard – All APIs', 'N. Admin – Vendor Management', 'A. Vendor Management (Mobile)', '5. Update vendor product (admin)']);
if ($adminUpdate !== null && isset($adminUpdate['request']['body']['formdata'])) {
    foreach ($adminUpdate['request']['body']['formdata'] as &$f) {
        if (($f['key'] ?? '') === 'category_id') {
            $f['description'] = 'Optional. Platform category ID.';
        }
        if (($f['key'] ?? '') === 'service_id') {
            $f['description'] = 'Optional. Platform service ID.';
        }
    }
    unset($f);
}

$j['info']['version'] = '3.6.30';
file_put_contents($path, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo "Postman updated to 3.6.30\n";

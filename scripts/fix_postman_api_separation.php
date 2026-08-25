<?php

/**
 * Fix Postman collection: platform catalog vs vendor products separation (v3.6.29).
 */

$path = dirname(__DIR__) . '/postman/tandil_backend.json';
$j = json_decode(file_get_contents($path), true);
if (! is_array($j)) {
    exit(1);
}

$apiMap = <<<'MD'

---

## API separation — do NOT mix (v3.6.29)

| Who | Product type | Postman folder | Endpoint | Notes |
|-----|--------------|----------------|----------|-------|
| **Client** | Browse / buy only | `4. Client Dashboard` → `O. Shop & Orders` | `/api/shop/products`, `/api/orders/{id}/track` | Client **never** creates products. **No vendor_id** in any client request. |
| **Admin — platform catalog** | Simple (checkout only) | `8. Products - All APIs` → `Admin` → **Add Platform Simple Product** | `POST /api/admin/products` | **No vendor_id**, **no OTP**. `fulfillment_type=platform` on track after paid. |
| **Admin — platform catalog** | Service | Same → **Add Service Product** | `POST /api/admin/products` | `type=service` / `service_id`. Supervisor flow. |
| **Vendor** | Simple (OTP) | `12. Vendor Dashboard` → `J. Products` | `/api/vendor/products` | Vendor-owned; OTP after ship. |
| **Admin — vendor management** | Vendor's products | `12` → `N. Admin – Vendor Management` | `POST /api/admin/vendors/{vendor_id}/products` | Admin creates product **for a vendor** — different from platform catalog. |

MD;

$j['info']['version'] = '3.6.29';
if (strpos($j['info']['description'] ?? '', 'API separation — do NOT mix (v3.6.29)') === false) {
    $j['info']['description'] = rtrim($j['info']['description'] ?? '') . $apiMap;
}

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

// Rename and fix Add Simple Product request
$simple = &findByPath($j['item'], ['8. Products - All APIs', 'Admin (Bearer token)', 'Add Simple Product (physical + vendor_id)']);
if ($simple !== null) {
    $simple['name'] = 'Add Platform Simple Product (checkout only — NO vendor_id)';
    $fd = &$simple['request']['body']['formdata'];
    foreach ($fd as $i => $row) {
        if (($row['key'] ?? '') === 'vendor_id') {
            unset($fd[$i]);
        }
        if (($row['key'] ?? '') === 'type') {
            $fd[$i]['description'] = 'product = platform simple (checkout only)';
        }
    }
    $fd = array_values($fd);
    $simple['request']['description'] = "POST `/api/admin/products` — **Platform catalog simple product** (Tandil shop).\n\n**Do NOT send vendor_id** — use Vendor Management API for vendor listings.\n\nAfter client pays: **checkout only** (`fulfillment_type=platform`). No OTP, no vendor ship flow, no supervisor.\n\nAuth: Bearer admin {{token}}.";
}

// Remove vendor_id from generic Add Product form
$addGeneric = &findByPath($j['item'], ['8. Products - All APIs', 'Admin (Bearer token)', 'Add Product (Multipart – with image files)']);
if ($addGeneric !== null) {
    $fd = &$addGeneric['request']['body']['formdata'];
    foreach ($fd as $i => $row) {
        if (($row['key'] ?? '') === 'vendor_id') {
            unset($fd[$i]);
        }
    }
    $fd = array_values($fd);
    $addGeneric['request']['description'] = "Generic admin platform product (multipart). **Do not use vendor_id** — see **Add Platform Simple Product** or **Add Service Product**.\n\nPlatform simple = checkout only. Vendor products = `12 → N → Admin Vendor Management`.";
}

$adminFolder = &findByPath($j['item'], ['8. Products - All APIs', 'Admin (Bearer token)']);
if ($adminFolder !== null) {
    $adminFolder['description'] = "**Admin platform catalog only** (`/api/admin/products`).\n\n| Request | Use |\n|---------|-----|\n| Add Platform Simple Product | Checkout-only simple SKU — **no vendor_id** |\n| Add Service Product | Supervisor service SKU |\n| Add Product (Multipart) | Generic — prefer dedicated requests above |\n\n**Vendor products:** `12 → N. Admin – Vendor Management` → `POST /api/admin/vendors/{vendor_id}/products` (NOT this folder).";
}

$productsRoot = &findByPath($j['item'], ['8. Products - All APIs']);
if ($productsRoot !== null) {
    $productsRoot['description'] = "Public shop (`/api/shop/products`) + **Admin platform catalog** (`/api/admin/products`).\n\nPlatform simple = checkout only (no vendor_id/OTP). Vendor listings = separate Vendor APIs.";
}

// Client track description
$track = &findByPath($j['item'], ['4. Client Dashboard', 'O. Shop & Orders', 'E. My orders (app routes — track / cancel / service report)', '4. Orders - Track']);
if ($track !== null) {
    $track['request']['description'] = "GET /api/orders/{id}/track\n\n**fulfillment_type values:**\n- `platform` — admin platform simple (checkout only, no OTP/vendor/supervisor)\n- `product` — **vendor** simple (OTP when shipped)\n- `service` — supervisor vertical timeline\n\nClient never sends vendor_id.";
}

file_put_contents($path, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo "Postman updated to v3.6.29\n";

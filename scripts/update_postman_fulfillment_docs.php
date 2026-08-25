<?php

/**
 * Update postman/tandil_backend.json for dual product/service fulfillment
 * (admin catalog + vendor + client track/notifications).
 */

$path = dirname(__DIR__) . '/postman/tandil_backend.json';
$j = json_decode(file_get_contents($path), true);
if (! is_array($j)) {
    fwrite(STDERR, "Failed to parse Postman JSON\n");
    exit(1);
}

$fulfillmentDoc = <<<'MD'

---

## Product vs Service fulfillment (v3.6.28)

**Same rule for Vendor catalog AND Admin/client catalog products.**

| Catalog type | How to create | After payment | Client Track timeline | Notifications |
|--------------|---------------|---------------|----------------------|---------------|
| **Simple / physical product** | `type=product` (or omit) + **`vendor_id`** (fulfillment vendor) | Stays vendor OTP flow (`pending` → ship → OTP → delivered). **No** supervisor claim / “goes to supervisor”. | `fulfillment_type=product`, **horizontal** timeline, `delivery_otp` when shipped (in-app, 5 min) | Vendor notified; client gets **Delivery OTP** in-app notification. No supervisor pool alerts. |
| **Service product** | `type=service` and/or link `service_id` | Supervisor area pool → claim → technician visit | `fulfillment_type=service`, **vertical** timeline with supervisor steps | Supervisor / technician alerts as before |

**Where to test in Postman:**
1. **Admin catalog CRUD:** `8. Products - All APIs` → **Admin (Bearer token)** → Add Simple Product / Add Service Product
2. **Vendor catalog CRUD:** `12. Vendor Dashboard` → **J. Products**
3. **Client track:** `4. Client Dashboard` → **O. Shop & Orders** → **E** (auth track) / **F** (guest track)
4. **Vendor OTP:** `12` → **L. Orders** → Update status (`shipped`) → Confirm delivery (OTP) / Resend OTP
5. **Login:** `3. Authentication` → Login (admin/client) or Login Vendor

MD;

// --- Collection info ---
$j['info']['version'] = '3.6.28';
$desc = $j['info']['description'] ?? '';
if (strpos($desc, 'Product vs Service fulfillment (v3.6.28)') === false) {
    // Remove older fulfillment blurb if any short stub exists
    $j['info']['description'] = rtrim($desc) . $fulfillmentDoc;
}

/**
 * Recursively find folder/request by name path (exact match on each segment).
 *
 * @return array|null reference via path of keys — we mutate by walking with &$ref
 */
function &findByPath(array &$items, array $names)
{
    $null = null;
    if ($names === []) {
        return $null;
    }
    $want = array_shift($names);
    foreach ($items as $i => &$item) {
        if (($item['name'] ?? '') !== $want) {
            continue;
        }
        if ($names === []) {
            return $item;
        }
        if (! isset($item['item']) || ! is_array($item['item'])) {
            return $null;
        }

        return findByPath($item['item'], $names);
    }

    return $null;
}

function setRequestDescription(array &$root, array $path, string $description): bool
{
    $ref = &findByPath($root['item'], $path);
    if ($ref === null) {
        return false;
    }
    if (isset($ref['request'])) {
        $ref['request']['description'] = $description;

        return true;
    }
    // folder
    $ref['description'] = $description;

    return true;
}

function upsertFormField(array &$formdata, string $key, array $field): void
{
    foreach ($formdata as $i => $row) {
        if (($row['key'] ?? '') === $key) {
            $formdata[$i] = array_merge($row, $field);
            $formdata[$i]['key'] = $key;

            return;
        }
    }
    // Insert after category_id if present, else append
    $insertAt = count($formdata);
    foreach ($formdata as $i => $row) {
        if (($row['key'] ?? '') === 'category_id') {
            $insertAt = $i + 1;
            break;
        }
    }
    array_splice($formdata, $insertAt, 0, [$field + ['key' => $key]]);
}

$trackDesc = <<<'MD'
GET /api/orders/{id}/track – Timeline + order_summary + fulfillment-aware fields.

**Fulfillment split (admin catalog OR vendor catalog — same behaviour):**

### Product / simple order (`fulfillment_type` = `product`)
- **Horizontal** delivery timeline (pending → confirmed → processing → shipped → delivered).
- **No** supervisor steps / “goes to supervisor” messaging.
- When status is `shipped`: `delivery_otp` (6-digit, 5 min TTL) shown here + **in-app** Delivery OTP notification (no SMS).
- Vendor confirms with `POST /api/vendor/orders/{id}/confirm-delivery`.

### Service order (`fulfillment_type` = `service`)
- **Vertical** service timeline (area pool → supervisor claim → technician → report).
- `service_report` block: `available`, `report_id`, `can_view_report`, `can_mark_delivered`, `pending_message`.
- Show report card only when `service_report.can_view_report` is true (after supervisor finalize `sent_to_client`).
- After payment, job goes to area supervisor pool. Order may stay **`processing`** until claim (`POST /api/supervisor/assignments/{id}/claim`) → then **`confirmed`**.

Also: placed_at, delivery_address, payment_method, total, order_number_short, can_cancel, maintenance_photos.

**Booking — per product, not per order:** each row in `data.order.items[]` has its own `booking_date` + `booking_slot`. For multi-slot orders treat item-level booking as source of truth.
MD;

$guestTrackDesc = <<<'MD'
GET /api/shop/orders/guest/track — Guest order tracking (no auth). Query: `order_number`, `email`.

Same fulfillment-aware payload as auth track:
- `fulfillment_type`: `product` | `service`
- Product: horizontal timeline + `delivery_otp` when shipped (OTP also delivered in-app if the guest later has an account; track screen still shows code when shipped)
- Service: vertical supervisor timeline + `service_report` flags
- **Product orders do not include supervisor “goes to supervisor” steps**
MD;

$folderEDesc = <<<'MD'
Same orders, shorter URLs for the app: `/api/orders`.

**Track** returns fulfillment-aware timeline:
- **Product** → horizontal + OTP fields (no supervisor)
- **Service** → vertical + `service_report` flags

**Service Report** = full report after supervisor `sent_to_client` (service orders only).
**Mark as Delivered** = client confirms (service flow). Product delivery is completed by vendor OTP confirm.
**Cancel** = POST cancel.
MD;

$adminAddDesc = <<<'MD'
Add admin catalog product (multipart/form-data). Auth: Bearer {{token}}.

### Dual fulfillment (required understanding)

| Intent | Fields | Order flow after paid |
|--------|--------|------------------------|
| **Simple / physical product** | `type=product` (or omit), **`vendor_id` required** (approved vendor who fulfills stock) | Vendor OTP / horizontal track — **no supervisor** |
| **Service product** | `type=service` and/or `service_id` | Supervisor pool / vertical track |

Other fields: name, description, price, stock, status, category_id, weight_unit, sku, handle, main_image, images[]. Variable: `product_type=variable`, option_groups_json, option_images[temp_key].

Response 201: data includes `type`, `vendor_id`, images, service_ids, option_groups.
MD;

$adminFolderDesc = <<<'MD'
Admin platform catalog (`/api/admin/products`). Bearer admin {{token}}.

**Simple product:** set `vendor_id` (fulfillment vendor) → client track/notifications use **product** flow (OTP, no supervisor).
**Service product:** set `type=service` / `service_id` → **service** flow (supervisor).

See requests: **Add Simple Product (physical + vendor_id)** and **Add Service Product (type=service)**.
MD;

$productsModuleDesc = <<<'MD'
Public shop products + Admin catalog CRUD.

**Fulfillment (v3.6.28):** Admin simple products need `vendor_id`. Service products use `type=service`. Client track/notifications follow the same product vs service split as vendor-created listings.
MD;

$vendorJDesc = <<<'MD'
Vendor catalog products (multipart same shape as Admin Add Product). Required: name, price, category_id (platform). Optional: description, stock, status, is_featured, service_id, weight_unit, sku, handle, product_type, option_groups_json, main_image, images[], image_urls.

**Fulfillment:**
- **Simple / product** (no service link / `type=product`) → after paid: vendor OTP + horizontal client track. No supervisor.
- **Service** (`type=service` or `service_id`) → supervisor vertical flow.

**Remove gallery / main images (update only):** `removed_image_ids[]`, `keep_image_ids[]`, or `remove_main_image=1`.

List/filter: `?category_id=` returns only this vendor's products (admin products excluded).
MD;

$vendorLDesc = <<<'MD'
Vendor orders — list, track, status POST, **OTP confirm** (product only).

| # | API | Use |
|---|-----|-----|
| 1 | GET `/orders` | List (`id` mapping + `order_id` shop) |
| 2 | GET `/orders/{id}` | Order details |
| 3 | GET `/orders/{id}/track` | Track — `{id}` = `order_id` or mapping `id` |
| 4–6 | contact / invoice / download | Optional |
| 7 | POST `/orders/{id}/status` | form-data `status` (+ optional `note`, `tracking_number`) |
| 8 | POST `/orders/{id}/confirm-delivery` | **Product only** — body `otp` |
| 9 | POST `/orders/{id}/resend-delivery-otp` | **Product only** — in-app OTP resend |

**Product order flow:** `pending` → … → `shipped` (OTP pushed in-app + track `delivery_otp`) → customer tells vendor OTP → **confirm-delivery**.
**Service lines** do not use vendor OTP; they go through supervisor.

Status values: `pending` | `confirmed` | `processing` | `shipped` | `delivered` | `cancelled`

Auth: `{{vendor_token}}`, approved vendor only.
MD;

$vendorModuleDescExtra = <<<'MD'

**Product vs Service (v3.6.28):** Vendor **J. Products** simple listings and Admin catalog simple listings both use OTP + horizontal track. Service listings use supervisor vertical track. Client APIs: folder **4 → O → E/F**. OTP APIs: **L. Orders** items 8–9.
MD;

$ok = [];
$ok[] = setRequestDescription($j, ['4. Client Dashboard', 'O. Shop & Orders', 'E. My orders (app routes — track / cancel / service report)', '4. Orders - Track'], $trackDesc);
$ok[] = setRequestDescription($j, ['4. Client Dashboard', 'O. Shop & Orders', 'F. Guest orders (no token)', '2. Orders - Guest Track'], $guestTrackDesc);
$ok[] = setRequestDescription($j, ['4. Client Dashboard', 'O. Shop & Orders', 'E. My orders (app routes — track / cancel / service report)'], $folderEDesc);
$ok[] = setRequestDescription($j, ['8. Products - All APIs', 'Admin (Bearer token)', 'Add Product (Multipart – with image files)'], $adminAddDesc);
$ok[] = setRequestDescription($j, ['8. Products - All APIs', 'Admin (Bearer token)'], $adminFolderDesc);
$ok[] = setRequestDescription($j, ['8. Products - All APIs'], $productsModuleDesc);
$ok[] = setRequestDescription($j, ['12. Vendor Dashboard – All APIs', 'J. Products (approved only)'], $vendorJDesc);
$ok[] = setRequestDescription($j, ['12. Vendor Dashboard – All APIs', 'L. Orders (approved only)'], $vendorLDesc);

$vendorRoot = &findByPath($j['item'], ['12. Vendor Dashboard – All APIs']);
if ($vendorRoot !== null && strpos($vendorRoot['description'] ?? '', 'Product vs Service (v3.6.28)') === false) {
    $vendorRoot['description'] = rtrim($vendorRoot['description'] ?? '') . $vendorModuleDescExtra;
    $ok[] = true;
}

// Patch Add Product form-data: type, vendor_id; soften product_type default
$addProduct = &findByPath($j['item'], ['8. Products - All APIs', 'Admin (Bearer token)', 'Add Product (Multipart – with image files)']);
if ($addProduct !== null && isset($addProduct['request']['body']['formdata'])) {
    $fd = &$addProduct['request']['body']['formdata'];
    upsertFormField($fd, 'type', [
        'key' => 'type',
        'value' => 'product',
        'type' => 'text',
        'description' => 'product = simple/physical (needs vendor_id) | service = supervisor flow. Prefer dedicated Add Simple / Add Service requests below.',
    ]);
    upsertFormField($fd, 'vendor_id', [
        'key' => 'vendor_id',
        'value' => '{{vendor_id}}',
        'type' => 'text',
        'description' => 'REQUIRED for simple/physical admin catalog products — approved vendor who fulfills the order (OTP flow). Leave empty only for type=service.',
    ]);
    foreach ($fd as &$row) {
        if (($row['key'] ?? '') === 'product_type') {
            $row['value'] = 'simple';
            $row['description'] = 'Optional UI shape: simple | variable. Not the same as fulfillment `type` (product|service).';
        }
        if (($row['key'] ?? '') === 'service_id') {
            $row['description'] = 'For service products: platform service ID. Linking service_id / type=service → supervisor fulfillment.';
        }
    }
    unset($row);
    $ok[] = true;
}

// Add dedicated example requests if missing
$adminFolder = &findByPath($j['item'], ['8. Products - All APIs', 'Admin (Bearer token)']);
if ($adminFolder !== null && isset($adminFolder['item'])) {
    $names = array_column($adminFolder['item'], 'name');

    $simpleReq = [
        'name' => 'Add Simple Product (physical + vendor_id)',
        'event' => [
            [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        "if (pm.response.code === 201 || pm.response.code === 200) {",
                        "    var j = pm.response.json();",
                        "    if (j.data && j.data.id) {",
                        "        pm.environment.set('product_id', String(j.data.id));",
                        "        pm.collectionVariables.set('product_id', String(j.data.id));",
                        "    }",
                        "}",
                    ],
                ],
            ],
        ],
        'request' => [
            'method' => 'POST',
            'header' => [
                ['key' => 'Authorization', 'value' => 'Bearer {{token}}'],
                ['key' => 'Accept', 'value' => 'application/json'],
            ],
            'body' => [
                'mode' => 'formdata',
                'formdata' => [
                    ['key' => 'name', 'value' => 'Admin Simple Fertilizer', 'type' => 'text'],
                    ['key' => 'description', 'value' => 'Physical product fulfilled by vendor (OTP flow)', 'type' => 'text'],
                    ['key' => 'price', 'value' => '49.99', 'type' => 'text'],
                    ['key' => 'stock', 'value' => '25', 'type' => 'text'],
                    ['key' => 'status', 'value' => 'active', 'type' => 'text'],
                    ['key' => 'category_id', 'value' => '{{category_id}}', 'type' => 'text'],
                    ['key' => 'type', 'value' => 'product', 'type' => 'text', 'description' => 'product = simple fulfillment'],
                    ['key' => 'vendor_id', 'value' => '{{vendor_id}}', 'type' => 'text', 'description' => 'Required — fulfillment vendor'],
                    ['key' => 'product_type', 'value' => 'simple', 'type' => 'text'],
                    ['key' => 'sku', 'value' => 'ADM-SIMPLE-001', 'type' => 'text'],
                    ['key' => 'handle', 'value' => 'admin-simple-fertilizer', 'type' => 'text'],
                ],
            ],
            'url' => [
                'raw' => '{{base_url}}/api/admin/products',
                'host' => ['{{base_url}}'],
                'path' => ['api', 'admin', 'products'],
            ],
            'description' => "POST `/api/admin/products` — **Admin simple/physical catalog product**.\n\nRequires **`vendor_id`** (approved vendor). After client pays: vendor OTP flow + horizontal track. **No supervisor / goes-to-supervisor messaging.**\n\nSet `{{vendor_id}}` from Admin Vendor Management list or Login Vendor → auth/me.\n\nAuth: Bearer admin {{token}}.",
        ],
    ];

    $serviceReq = [
        'name' => 'Add Service Product (type=service)',
        'event' => [
            [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        "if (pm.response.code === 201 || pm.response.code === 200) {",
                        "    var j = pm.response.json();",
                        "    if (j.data && j.data.id) {",
                        "        pm.environment.set('product_id', String(j.data.id));",
                        "        pm.collectionVariables.set('product_id', String(j.data.id));",
                        "    }",
                        "}",
                    ],
                ],
            ],
        ],
        'request' => [
            'method' => 'POST',
            'header' => [
                ['key' => 'Authorization', 'value' => 'Bearer {{token}}'],
                ['key' => 'Accept', 'value' => 'application/json'],
            ],
            'body' => [
                'mode' => 'formdata',
                'formdata' => [
                    ['key' => 'name', 'value' => 'Admin Lawn Care Visit', 'type' => 'text'],
                    ['key' => 'description', 'value' => 'Service product — supervisor flow', 'type' => 'text'],
                    ['key' => 'price', 'value' => '120.00', 'type' => 'text'],
                    ['key' => 'stock', 'value' => '0', 'type' => 'text'],
                    ['key' => 'status', 'value' => 'active', 'type' => 'text'],
                    ['key' => 'category_id', 'value' => '{{category_id}}', 'type' => 'text'],
                    ['key' => 'type', 'value' => 'service', 'type' => 'text', 'description' => 'service = supervisor fulfillment'],
                    ['key' => 'service_id', 'value' => '{{service_id}}', 'type' => 'text', 'description' => 'Platform service ID'],
                    ['key' => 'product_type', 'value' => 'simple', 'type' => 'text'],
                    ['key' => 'sku', 'value' => 'ADM-SERVICE-001', 'type' => 'text'],
                    ['key' => 'handle', 'value' => 'admin-lawn-care-visit', 'type' => 'text'],
                ],
            ],
            'url' => [
                'raw' => '{{base_url}}/api/admin/products',
                'host' => ['{{base_url}}'],
                'path' => ['api', 'admin', 'products'],
            ],
            'description' => "POST `/api/admin/products` — **Admin service catalog product**.\n\nSet `type=service` and/or `service_id`. After client pays: supervisor area pool → claim → technician. Client track is **vertical** with supervisor steps.\n\n`vendor_id` not required for service.\n\nAuth: Bearer admin {{token}}.",
        ],
    ];

    // Insert after "Add Product (Multipart – with image files)"
    $insertAt = 1;
    foreach ($adminFolder['item'] as $i => $it) {
        if (($it['name'] ?? '') === 'Add Product (Multipart – with image files)') {
            $insertAt = $i + 1;
            break;
        }
    }

    if (! in_array('Add Simple Product (physical + vendor_id)', $names, true)) {
        array_splice($adminFolder['item'], $insertAt, 0, [$simpleReq]);
        $insertAt++;
        $ok[] = true;
        echo "Added: Add Simple Product\n";
    }
    $names = array_column($adminFolder['item'], 'name');
    if (! in_array('Add Service Product (type=service)', $names, true)) {
        // find insert after simple if present
        foreach ($adminFolder['item'] as $i => $it) {
            if (($it['name'] ?? '') === 'Add Simple Product (physical + vendor_id)') {
                $insertAt = $i + 1;
                break;
            }
        }
        array_splice($adminFolder['item'], $insertAt, 0, [$serviceReq]);
        $ok[] = true;
        echo "Added: Add Service Product\n";
    }
}

// Confirm-delivery / resend descriptions refresh
setRequestDescription(
    $j,
    ['12. Vendor Dashboard – All APIs', 'L. Orders (approved only)', '8. Confirm delivery (OTP)'],
    "POST `/api/vendor/orders/{id}/confirm-delivery` — **product orders only** (vendor OR admin-catalog simple products assigned to this vendor).\n\nCustomer gives OTP (Tandil **in-app** notification + Track `delivery_otp`, 5 min TTL) to supplier. Body: `otp`.\n\nSets mapping `delivered` + shop `order_status=delivered`. OTP single-use.\n\n**Not used for service orders** (those use supervisor / mark-delivered).\n\n**Token:** `{{vendor_token}}`."
);

setRequestDescription(
    $j,
    ['12. Vendor Dashboard – All APIs', 'L. Orders (approved only)', '12. Resend delivery OTP'],
    "POST `/api/vendor/orders/{id}/resend-delivery-otp` — **product orders only**, status `shipped`.\n\nInvalidates previous OTP and sends a new 6-digit code **inside the Tandil app** (notification + track). 5 min expiry, 60s cooldown. No SMS.\n\nWorks for vendor-created and admin-catalog simple products fulfilled by this vendor.\n\n**Token:** `{{vendor_token}}`."
);

$json = json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "json_encode failed\n");
    exit(1);
}

// Postman exports often use 4-space indent; PHP JSON_PRETTY_PRINT uses 4 spaces already with default.
file_put_contents($path, $json . "\n");

$passed = count(array_filter($ok));
echo "Updates applied checks: {$passed}/" . count($ok) . "\n";
echo "Version: {$j['info']['version']}\n";
echo "Done.\n";

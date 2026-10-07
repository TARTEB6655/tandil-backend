<?php

/**
 * 1) Numeric-only renumber of every folder/request name
 * 2) Rewrite collection + key folder descriptions to numeric paths (no A/B/C)
 */

$path = __DIR__.'/../postman/tandil_backend.json';
$raw = (string) file_get_contents($path);
$collection = json_decode($raw, true);
if (! is_array($collection)) {
    fwrite(STDERR, 'Invalid JSON: '.json_last_error_msg()."\n");
    exit(1);
}

function stripPrefix(string $name): string
{
    $title = trim($name);
    while (preg_match('/^([0-9]{1,3}|[A-Z]{1,3}[0-9]?)\.\s+(.+)$/u', $title, $m)) {
        $title = trim($m[2]);
    }

    return $title !== '' ? $title : trim($name);
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array{0: list<array<string, mixed>>, 1: int}
 */
function renumber(array $items): array
{
    $changed = 0;
    foreach ($items as $i => &$it) {
        $prefix = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $new = $prefix.'. '.stripPrefix((string) ($it['name'] ?? 'Untitled'));
        if (($it['name'] ?? '') !== $new) {
            $it['name'] = $new;
            $changed++;
        }
        if (isset($it['item']) && is_array($it['item'])) {
            [$kids, $c] = renumber($it['item']);
            $it['item'] = $kids;
            $changed += $c;
        }
    }
    unset($it);

    return [$items, $changed];
}

function setDesc(array &$node, string $text): void
{
    $node['description'] = $text;
}

[$collection['item'], $nameChanged] = renumber($collection['item'] ?? []);

$collection['info']['description'] = <<<'MD'
Tandil Backend API. All responses JSON. Set env: base_url, token. Bearer token for protected routes.

NUMBERING: every folder, subfolder, and request uses 01. 02. 03. only (no A/B/C letters).

Root:
01 Health Check · 02 Notifications hub · 03 Authentication · 04 Client Dashboard · 05 Admin Dashboard · 06 Technician · 07 Supervisor · 08 Products · 09 Area Manager · 10 HR Manager · 11 Other Modules · 12 Vendor Dashboard

Key paths:
- Contractor auth: 03 → 03. Contractor
- Admin contractor review: 05 → 14 (account-status/suspend|inactive|activate — no body)
- Admin contractor signup options: 05 → 15
- Client shop: 04 → 16. Shop & Orders
- Vendor products/orders: 12 → 10 / 12 → 12
- Admin vendor management: 12 → 14
MD;

// Patch known folder descriptions by title match
$folderDocs = [
    'Authentication' => "Auth for all apps.\n\n| Subfolder | Use |\n|-----------|-----|\n| **01. Client** | Customer register / login / social |\n| **02. Technician** | Technician signup |\n| **03. Contractor** | Contractor dropdowns + register + login |\n| **04. Vendor** | Marketplace vendor |\n\nContractor dropdowns: GET /api/contractor/auth/registration-options → 03 → 01.",
    'Client Dashboard' => 'All client app APIs. Subfolders 01→16. Auth: Bearer {{token}} (client role unless noted).',
    'Admin Dashboard' => 'All admin APIs. Subfolders 01→16. Auth: Bearer {{token}} (admin). Contractor review: 14 · Signup options: 15 · Wallet: 16.',
    'Shop & Orders' => 'Client shop, orders, payments, coupons, maintenance photos. Subfolders 01→12.',
    'Other Modules' => 'Subfolders 01→18: Support Tickets, Offers, Complaints, Tips, Notifications, Admin catalog/areas, Alternative Routes, Services, User Profile/Addresses/Payments/Notifications, Language APIs, Localized articles.',
    'Vendor Dashboard' => "Login: 03 → 04. Vendor.\n\nSubfolders 01→18: Business Profile, Application, Documents, Dashboard, Analytics, Support Chat, Product dropdowns, Categories, Services, Products, Inventory, Orders, Compare vendors, Admin Vendor Management, Admin Marketplace, Vendor Store, Legal, Notifications.\n\nAdmin contractor APIs: 05 → 14 (registrations) · 05 → 15 (signup options).",
    'Support' => 'Admin support tickets and vendor live chat. Subfolders 01 (tickets) and 02 (live chat). Auth: Bearer {{token}} (admin).',
    'Loyalty Points (Admin)' => "Admin Loyalty Points APIs.\n\n01 Dashboard · 02 Loyalty Settings · 03 Rewards · 04 Customers & Points · 05 Campaigns · 06 Reports & Export.\nAuth: admin Bearer {{token}}.",
];

$patch = function (array &$items) use (&$patch, $folderDocs): int {
    $n = 0;
    foreach ($items as &$it) {
        $title = stripPrefix((string) ($it['name'] ?? ''));
        foreach ($folderDocs as $needle => $doc) {
            if (stripos($title, $needle) !== false && isset($it['item'])) {
                // Prefer exact-ish matches for short names like Support
                if ($needle === 'Support' && strcasecmp($title, 'Support') !== 0) {
                    continue;
                }
                if (($it['description'] ?? null) !== $doc) {
                    $it['description'] = $doc;
                    $n++;
                }
            }
        }
        if (isset($it['item']) && is_array($it['item'])) {
            $n += $patch($it['item']);
        }
    }
    unset($it);

    return $n;
};

$docsChanged = $patch($collection['item']);

$version = (string) ($collection['info']['version'] ?? '3.6.51');
if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $vm)) {
    $collection['info']['version'] = $vm[1].'.'.$vm[2].'.'.((int) $vm[3] + 1);
}
$ver = $collection['info']['version'];
$collection['info']['name'] = 'Tandil Backend - Flow-Based Collection (v'.$ver.')';

$encoded = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    fwrite(STDERR, "Encode failed\n");
    exit(1);
}
$encoded = preg_replace_callback('/^(?:    )+/m', function (array $m): string {
    return str_repeat('  ', intdiv(strlen($m[0]), 4));
}, $encoded);

file_put_contents($path, $encoded."\n");

// Verify
$check = json_decode((string) file_get_contents($path), true);
if (! is_array($check)) {
    fwrite(STDERR, 'Wrote invalid JSON: '.json_last_error_msg()."\n");
    exit(1);
}

echo "names_changed={$nameChanged}\n";
echo "docs_changed={$docsChanged}\n";
echo "version={$ver}\n";
echo "json=ok\n";

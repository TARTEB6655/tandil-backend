"""One-off: insert Postman folder 8a and cleanup duplicates."""
import json

path = "postman/tandil_backend.json"
with open(path, encoding="utf-8") as f:
    data = json.load(f)


def find_folder(items, name):
    for i, it in enumerate(items):
        if it.get("name") == name:
            return i, it
    return None, None


def remove_payment_dup(items):
    out = []
    for it in items:
        if it.get("name") == "Payment (Shop: Stripe & PayPal)":
            continue
        if "item" in it and isinstance(it["item"], list):
            it = dict(it)
            it["item"] = remove_payment_dup(it["item"])
        out.append(it)
    return out


root = data.get("item", [])
idx8, f8 = find_folder(root, "8. Client Dashboard – Shop & Orders")
idx8b, _ = find_folder(root, "8b. Client Dashboard – Maintenance Photos")
if idx8 is None or idx8b is None:
    raise SystemExit("Could not find folder 8 or 8b")

new_folder = {
    "name": "8a. Shop – Payments (Stripe & PayPal only)",
    "description": (
        "**Sirf payment APIs.** Test flow: (1) Client login → set {{token}} + {{base_url}}. "
        "(2) GET 1 (no auth) ya GET 2 (Bearer token zaroori—warna Unauthenticated). "
        "(3) POST 3 (Stripe) ya 4 (PayPal guest). (4) PayPal: browser se approve → POST 5 capture. "
        "(5) Stripe: checkout_url browser me; webhook order paid karega. "
        "(6) Webhook = Stripe/CLI—Postman se signature ke bina mushkil."
    ),
    "item": [
        {
            "name": "1. GET Payment gateways (public — no token)",
            "request": {
                "method": "GET",
                "header": [{"key": "Accept", "value": "application/json"}],
                "url": {
                    "raw": "{{base_url}}/api/shop/payment-gateways",
                    "host": ["{{base_url}}"],
                    "path": ["api", "shop", "payment-gateways"],
                },
                "description": "GET /api/shop/payment-gateways — No auth. Stripe + PayPal with enabled.",
            },
        },
        {
            "name": "2. GET Payment methods (client — Bearer token)",
            "request": {
                "method": "GET",
                "header": [
                    {"key": "Authorization", "value": "Bearer {{token}}"},
                    {"key": "Accept", "value": "application/json"},
                ],
                "url": {
                    "raw": "{{base_url}}/api/shop/checkout/payment-methods",
                    "host": ["{{base_url}}"],
                    "path": ["api", "shop", "checkout", "payment-methods"],
                },
                "description": "GET /api/shop/checkout/payment-methods — **Auth required** (client Sanctum).",
            },
        },
        {
            "name": "3. POST Start checkout — Stripe (logged-in)",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 201) {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.data && jsonData.data.order_id && !pm.environment.get('order_id')) {",
                            "        pm.environment.set('order_id', jsonData.data.order_id);",
                            "    }",
                            "}",
                        ],
                        "type": "text/javascript",
                    },
                }
            ],
            "request": {
                "method": "POST",
                "header": [
                    {"key": "Authorization", "value": "Bearer {{token}}"},
                    {"key": "Accept", "value": "application/json"},
                    {"key": "Content-Type", "value": "application/json"},
                ],
                "body": {
                    "mode": "raw",
                    "raw": '{\n  "payment_method": "stripe",\n  "address_id": 1,\n  "success_url": "https://example.com/ok",\n  "cancel_url": "https://example.com/cancel"\n}',
                },
                "url": {
                    "raw": "{{base_url}}/api/shop/checkout/start",
                    "host": ["{{base_url}}"],
                    "path": ["api", "shop", "checkout", "start"],
                },
                "description": "POST /api/shop/checkout/start — Stripe. success_url / cancel_url must be valid https.",
            },
        },
        {
            "name": "4. POST Start checkout — PayPal (guest)",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 201) {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.data && jsonData.data.order_id) {",
                            "        pm.environment.set('order_id', jsonData.data.order_id);",
                            "    }",
                            "    if (jsonData.data && jsonData.data.paypal_order_id) {",
                            "        pm.environment.set('paypal_order_id', jsonData.data.paypal_order_id);",
                            "    }",
                            "}",
                        ],
                        "type": "text/javascript",
                    },
                }
            ],
            "request": {
                "method": "POST",
                "header": [
                    {"key": "Accept", "value": "application/json"},
                    {"key": "Content-Type", "value": "application/json"},
                ],
                "body": {
                    "mode": "raw",
                    "raw": '{\n  "payment_method": "paypal",\n  "email": "guest@example.com",\n  "full_name": "Guest User",\n  "phone_number": "+971501234567",\n  "street_address": "Sheikh Zayed Road",\n  "city": "Dubai",\n  "country": "UAE",\n  "items": [{"product_id": 1, "qty": 2}],\n  "success_url": "https://example.com/ok",\n  "cancel_url": "https://example.com/cancel"\n}',
                },
                "url": {
                    "raw": "{{base_url}}/api/shop/checkout/start",
                    "host": ["{{base_url}}"],
                    "path": ["api", "shop", "checkout", "start"],
                },
                "description": "POST /api/shop/checkout/start — Guest PayPal. No Authorization.",
            },
        },
        {
            "name": "5. POST PayPal capture",
            "request": {
                "method": "POST",
                "header": [
                    {"key": "Accept", "value": "application/json"},
                    {"key": "Content-Type", "value": "application/json"},
                ],
                "body": {
                    "mode": "raw",
                    "raw": '{\n  "paypal_order_id": "{{paypal_order_id}}",\n  "order_id": {{order_id}}\n}',
                },
                "url": {
                    "raw": "{{base_url}}/api/shop/paypal/capture",
                    "host": ["{{base_url}}"],
                    "path": ["api", "shop", "paypal", "capture"],
                },
                "description": "POST /api/shop/paypal/capture — After PayPal approval.",
            },
        },
        {
            "name": "6. POST Stripe webhook (reference only)",
            "request": {
                "method": "POST",
                "header": [
                    {"key": "Content-Type", "value": "application/json"},
                    {
                        "key": "Stripe-Signature",
                        "value": "{{stripe_webhook_signature}}",
                        "description": "Stripe sends this; use CLI or Dashboard",
                    },
                ],
                "body": {
                    "mode": "raw",
                    "raw": '{\n  "id": "evt_xxx",\n  "type": "checkout.session.completed",\n  "data": {\n    "object": {\n      "id": "cs_xxx",\n      "client_reference_id": "123",\n      "metadata": { "order_id": "123" }\n    }\n  }\n}',
                },
                "url": {
                    "raw": "{{base_url}}/api/shop/webhooks/stripe",
                    "host": ["{{base_url}}"],
                    "path": ["api", "shop", "webhooks", "stripe"],
                },
                "description": "Stripe hits this URL. STRIPE_WEBHOOK_SECRET on server.",
            },
        },
    ],
}

insert_at = idx8 + 1
if insert_at < len(root) and root[insert_at].get("name") == new_folder["name"]:
    print("8a already present, skip insert")
else:
    root.insert(insert_at, new_folder)

f8["description"] = (
    "Shop: settings, cart, buy-now summary, checkout review, orders, payments list. "
    "**Payment start / capture / webhook** → folder **8a. Shop – Payments (Stripe & PayPal only)**."
)

data["item"] = remove_payment_dup(data["item"])

info = data.get("info", {})
desc = info.get("description", "")
if "Payment (Shop: Stripe webhook + PayPal capture)" in desc:
    info["description"] = desc.replace(
        "Payment (Shop: Stripe webhook + PayPal capture), ",
        "Shop payments → **8a. Shop – Payments**. ",
    )
    data["info"] = info

with open(path, "w", encoding="utf-8") as f:
    json.dump(data, f, indent=4, ensure_ascii=False)
print("done")

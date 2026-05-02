import fs from "fs";

const path = "postman/tandil_backend.json";
const data = JSON.parse(fs.readFileSync(path, "utf8"));

function findFolder(items, name) {
  for (let i = 0; i < items.length; i++) {
    if (items[i].name === name) return [i, items[i]];
  }
  return [null, null];
}

function removePaymentDup(items) {
  const out = [];
  for (const it of items) {
    if (it.name === "Payment (Shop: Stripe & PayPal)") continue;
    if (Array.isArray(it.item)) {
      out.push({ ...it, item: removePaymentDup(it.item) });
    } else {
      out.push(it);
    }
  }
  return out;
}

const root = data.item;
const [idx8, f8] = findFolder(root, "8. Client Dashboard – Shop & Orders");
const [idx8b] = findFolder(root, "8b. Client Dashboard – Maintenance Photos");
if (idx8 == null || idx8b == null) throw new Error("folder 8 or 8b not found");

const newFolder = {
  name: "8a. Shop – Payments (Stripe & PayPal only)",
  description:
    "**Sirf payment APIs.** Test flow: (1) Client login → {{token}} + {{base_url}}. " +
    "(2) GET 1 (no auth) ya GET 2 (Bearer token — warna Unauthenticated). " +
    "(3) POST 3 (Stripe) ya 4 (PayPal guest). (4) PayPal: browser approve → POST 5 capture. " +
    "(5) Stripe: checkout_url browser; webhook paid karega. " +
    "(6) Webhook = Stripe/CLI — Postman par signature mushkil.",
  item: [
    {
      name: "1. GET Payment gateways (public — no token)",
      request: {
        method: "GET",
        header: [{ key: "Accept", value: "application/json" }],
        url: {
          raw: "{{base_url}}/api/shop/payment-gateways",
          host: ["{{base_url}}"],
          path: ["api", "shop", "payment-gateways"],
        },
        description: "GET /api/shop/payment-gateways — No auth. Stripe + PayPal enabled.",
      },
    },
    {
      name: "2. GET Payment methods (client — Bearer token)",
      request: {
        method: "GET",
        header: [
          { key: "Authorization", value: "Bearer {{token}}" },
          { key: "Accept", value: "application/json" },
        ],
        url: {
          raw: "{{base_url}}/api/shop/checkout/payment-methods",
          host: ["{{base_url}}"],
          path: ["api", "shop", "checkout", "payment-methods"],
        },
        description:
          "GET /api/shop/checkout/payment-methods — **Bearer {{token}}** required (client).",
      },
    },
    {
      name: "3. POST Start checkout — Stripe (logged-in)",
      event: [
        {
          listen: "test",
          script: {
            exec: [
              "if (pm.response.code === 201) {",
              "    var jsonData = pm.response.json();",
              "    if (jsonData.data && jsonData.data.order_id && !pm.environment.get('order_id')) {",
              "        pm.environment.set('order_id', jsonData.data.order_id);",
              "    }",
              "}",
            ],
            type: "text/javascript",
          },
        },
      ],
      request: {
        method: "POST",
        header: [
          { key: "Authorization", value: "Bearer {{token}}" },
          { key: "Accept", value: "application/json" },
          { key: "Content-Type", value: "application/json" },
        ],
        body: {
          mode: "raw",
          raw: `{
  "payment_method": "stripe",
  "address_id": 1,
  "success_url": "https://example.com/ok",
  "cancel_url": "https://example.com/cancel"
}`,
        },
        url: {
          raw: "{{base_url}}/api/shop/checkout/start",
          host: ["{{base_url}}"],
          path: ["api", "shop", "checkout", "start"],
        },
        description:
          "POST /api/shop/checkout/start — Stripe Checkout. success_url / cancel_url = valid https.",
      },
    },
    {
      name: "4. POST Start checkout — PayPal (guest)",
      event: [
        {
          listen: "test",
          script: {
            exec: [
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
            type: "text/javascript",
          },
        },
      ],
      request: {
        method: "POST",
        header: [
          { key: "Accept", value: "application/json" },
          { key: "Content-Type", value: "application/json" },
        ],
        body: {
          mode: "raw",
          raw: `{
  "payment_method": "paypal",
  "email": "guest@example.com",
  "full_name": "Guest User",
  "phone_number": "+971501234567",
  "street_address": "Sheikh Zayed Road",
  "city": "Dubai",
  "country": "UAE",
  "items": [{"product_id": 1, "qty": 2}],
  "success_url": "https://example.com/ok",
  "cancel_url": "https://example.com/cancel"
}`,
        },
        url: {
          raw: "{{base_url}}/api/shop/checkout/start",
          host: ["{{base_url}}"],
          path: ["api", "shop", "checkout", "start"],
        },
        description: "POST /api/shop/checkout/start — Guest PayPal. No Authorization header.",
      },
    },
    {
      name: "5. POST PayPal capture",
      request: {
        method: "POST",
        header: [
          { key: "Accept", value: "application/json" },
          { key: "Content-Type", value: "application/json" },
        ],
        body: {
          mode: "raw",
          raw: `{
  "paypal_order_id": "{{paypal_order_id}}",
  "order_id": {{order_id}}
}`,
        },
        url: {
          raw: "{{base_url}}/api/shop/paypal/capture",
          host: ["{{base_url}}"],
          path: ["api", "shop", "paypal", "capture"],
        },
        description: "POST /api/shop/paypal/capture — After PayPal approval in browser.",
      },
    },
    {
      name: "6. POST Stripe webhook (reference only)",
      request: {
        method: "POST",
        header: [
          { key: "Content-Type", value: "application/json" },
          {
            key: "Stripe-Signature",
            value: "{{stripe_webhook_signature}}",
            description: "Stripe sends valid signature",
          },
        ],
        body: {
          mode: "raw",
          raw: `{
  "id": "evt_xxx",
  "type": "checkout.session.completed",
  "data": {
    "object": {
      "id": "cs_xxx",
      "client_reference_id": "123",
      "metadata": { "order_id": "123" }
    }
  }
}`,
        },
        url: {
          raw: "{{base_url}}/api/shop/webhooks/stripe",
          host: ["{{base_url}}"],
          path: ["api", "shop", "webhooks", "stripe"],
        },
        description:
          "Stripe Dashboard/CLI calls this. Server needs STRIPE_WEBHOOK_SECRET.",
      },
    },
  ],
};

const insertAt = idx8 + 1;
if (!(root[insertAt] && root[insertAt].name === newFolder.name)) {
  root.splice(insertAt, 0, newFolder);
}

f8.description =
  "Shop: settings, cart, buy-now summary, checkout review, orders, payments list. " +
  "**Payment start / capture / webhook** → **8a. Shop – Payments (Stripe & PayPal only)**.";

data.item = removePaymentDup(data.item);

if (data.info && data.info.description) {
  data.info.description = data.info.description.replace(
    "Organized by dashboard:",
    "**Shop payments:** folder **8a. Shop – Payments**. Organized by dashboard:"
  );
}

fs.writeFileSync(path, JSON.stringify(data, null, 4), "utf8");
console.log("ok");

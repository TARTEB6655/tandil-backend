# Coupon APIs – Client app integration (React / mobile)

Short guide for frontend developers. Full API reference: [`COUPON_API.md`](./COUPON_API.md).

**Base URL:** `{API_BASE}/api/shop`  
**Auth:** `Authorization: Bearer {client_token}`  
**Postman:** folder **8. Client Dashboard – Shop & Orders** → **K. Shop – Coupons**

---

## What you need to build

| Screen | API | When |
|--------|-----|------|
| Category / Service product list | `GET /coupons/browse` | Optional promo banners |
| Checkout – “View offers” | `POST /coupons/checkout-offers` | Open promo picker modal |
| Checkout – Apply button | `POST /coupons/apply` | User enters code and taps Apply |
| Payment (Stripe) | `POST /checkout/stripe/payment-intent` | Same `coupon_code` as applied |

Coupon **create/edit** is **admin only** (`POST /api/admin/coupons`) — not in the client app.

---

## 1. Browse promos (all products, category, or service page)

**One endpoint.** Send **exactly one** query param:

```http
GET /api/shop/coupons/browse?all=1
```

```http
GET /api/shop/coupons/browse?category_id=5
```

```http
GET /api/shop/coupons/browse?service_id=12
```

Do **not** combine `all`, `category_id`, and `service_id` (422).

`all=1` returns only coupons created with admin **All products** (`applies_to: all`).

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "code": "SAVE10",
      "discount_label": "10% OFF",
      "scope_label": "All products",
      "applies_to_label": "All products",
      "title": "10% off",
      "description": "..."
    }
  ],
  "meta": { "total": 1, "scope": "all" }
}
```

| Admin setting | Shows on browse when |
|---------------|----------------------|
| **All products** (`applies_to: all`) | `?all=1`; also on every `category_id` / `service_id` browse |
| **Specific category** | That `category_id` only |
| **Specific service** | That `service_id` only |

---

## 2. Checkout – “Choose a promo code” modal

```http
POST /api/shop/coupons/checkout-offers
Content-Type: application/json

{}
```

Cart already on server → **empty body is OK**. Server uses logged-in user’s cart.

Optional body (only if you don’t use server cart):

```json
{
  "subtotal": 26.10,
  "catalog_discount": 0,
  "cart_category_ids": [1, 2],
  "cart_catalog": "products"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "available_for_order": [ /* can apply now */ ],
    "not_eligible_for_cart": [ /* show grey + ineligible_reason */ ],
    "available_count": 1,
    "not_eligible_count": 4
  }
}
```

**UI:**

- `available_for_order` → show **Apply**
- `not_eligible_for_cart` → disabled card + red/grey `ineligible_reason`
- Use `available_count` for **“View N available offers”**

Each card has: `code`, `discount_label`, `scope_summary` (e.g. `All products`, `Categories: +1 more`).

---

## 3. Apply coupon (main checkout flow)

User types code and taps **Apply**. Send **only the code**:

```http
POST /api/shop/coupons/apply
Content-Type: application/json

{
  "code": "SAVE10"
}
```

Do **not** send `subtotal` in normal flow (server reads cart).

**Success response – bind UI to `data.order_summary`:**

```json
{
  "success": true,
  "message": "Coupon applied.",
  "data": {
    "code": "SAVE10",
    "discount_type": "percentage",
    "discount_value": 10,
    "discount_label": "10% OFF",
    "coupon_discount": 10,
    "order_summary": {
      "subtotal": 100,
      "discount": 0,
      "coupon_discount": 10,
      "coupon_code": "SAVE10",
      "tax": 4.5,
      "vat": 4.5,
      "shipping": 10,
      "total": 104.5,
      "currency": "AED"
    },
    "payment": null
  }
}
```

| `discount_type` | Meaning |
|-----------------|--------|
| `percentage` | % off subtotal (after catalog discount), optional cap |
| `fixed_amount` | Fixed AED off |

**If PaymentIntent already exists**, update Stripe amount:

```json
{
  "code": "SAVE10",
  "payment_intent_id": "pi_xxxxxxxx"
}
```

Then use `data.payment.client_secret` for Payment Sheet.

**Error:** HTTP `422` → show `message` (invalid code, min order, wrong category in cart, etc.).

---

## 4. Recommended checkout flow

```
1. Load checkout
   → GET /api/shop/order-summary
   (no coupon_code)

2. User taps "View available offers"
   → POST /api/shop/coupons/checkout-offers
   → Show modal from available_for_order / not_eligible_for_cart

3. User enters code + Apply
   → POST /api/shop/coupons/apply  { "code": "SAVE10" }
   → Refresh Subtotal / VAT / Total from order_summary

4. Pay with card
   → POST /api/shop/checkout/stripe/payment-intent
   Body must include: "coupon_code": "SAVE10"
   (same code as step 3)

   OR: create PI first, then apply with payment_intent_id (step 3)
```

Also works:

- `GET /api/shop/cart?coupon_code=SAVE10`
- `GET /api/shop/checkout/review?coupon_code=SAVE10`

---

## 5. Admin creates coupons (for reference)

Not client app — **admin dashboard** only.

```http
POST /api/admin/coupons
Authorization: Bearer {admin_token}
```

| UI choice | `applies_to` | IDs |
|-----------|--------------|-----|
| **All products** | `all` | Leave `category_ids` and `service_ids` empty |
| Specific category | `categories` | `category_ids: [1, 2]` |
| Specific service | `services` | `service_ids: [3]` |

**Postman:** **5f. Admin Dashboard – Coupons** → Add coupon (percentage / fixed).

**Example – all products:**

```json
{
  "code": "SAVE10",
  "title": "10% off store",
  "discount_type": "percentage",
  "discount_value": 10,
  "min_order_amount": 50,
  "max_discount_amount": 30,
  "is_active": true,
  "applies_to": "all",
  "category_ids": [],
  "service_ids": []
}
```

---

## 6. Postman quick map

| # | Request | Folder |
|---|---------|--------|
| Browse | `GET .../coupons/browse?category_id=` | 8 → K → **0** |
| Offers modal | `POST .../coupons/checkout-offers` | 8 → K → **1** |
| Validate | `POST .../coupons/validate` | 8 → K → **2** |
| **Apply** | `POST .../coupons/apply` `{ "code": "SAVE10" }` | 8 → K → **3** |
| Order summary | `GET .../order-summary?coupon_code=` | 8 → K → **5** |
| Stripe PI | `POST .../checkout/stripe/payment-intent` | 8 → **I** |

---

## 7. Common mistakes

1. Sending `subtotal` on apply — not needed if user has items in cart.  
2. `browse` with both `category_id` and `service_id` — use only one.  
3. Apply works but Stripe charge is wrong — pass `coupon_code` on payment-intent **or** `payment_intent_id` on apply.  
4. Expecting client to create coupons — use admin API / admin app only.

---

## Questions?

- Full fields & errors: [`COUPON_API.md`](./COUPON_API.md)  
- Test with Postman collection: `postman/tandil_backend.json` (v3.3.44+)
